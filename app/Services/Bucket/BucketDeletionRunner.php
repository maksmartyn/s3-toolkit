<?php

declare(strict_types=1);

namespace App\Services\Bucket;

use App\Domain\Enum\Storage\StorageCapability;
use App\Services\Bucket\Contracts\OperationRunner;
use App\Services\Operation\OperationService;
use App\Services\Storage\StorageErrorTranslator;
use App\Services\Storage\StorageGateway;
use App\Services\Storage\StorageGatewayFactory;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Удаление бакета со всем содержимым: версии и маркеры удаления (или объекты), незавершённые загрузки, сам бакет.
 *
 * Каждый раз берётся первая страница списка: удалённое из неё пропадает, поэтому маркеры пагинации не нужны
 * и продолжение после перезапуска не может ничего пропустить.
 */
final readonly class BucketDeletionRunner implements OperationRunner
{
    private const string PHASE_OBJECTS = 'objects';

    private const string PHASE_UPLOADS = 'uploads';

    private const string PHASE_BUCKET = 'bucket';

    public function __construct(
        private OperationService $operations,
        private StorageGatewayFactory $gateways,
        private BucketService $buckets,
        private StorageErrorTranslator $errors,
    ) {}

    public function run(int $operationId, float $budgetSeconds): bool
    {
        $operation = $this->operations->findActive($operationId);

        if ($operation === null) {
            return true;
        }

        $bucket = $operation->bucket;
        $state = $operation->state ?? [];
        $processed = $operation->processed;
        $deadline = microtime(true) + $budgetSeconds;

        try {
            $gateway = $this->gateways->forStorage($operation->storage_id);
            $state['phase'] ??= self::PHASE_OBJECTS;
            $state['versions'] ??= $gateway->supports(StorageCapability::Versioning);

            while (microtime(true) < $deadline) {
                switch ($state['phase']) {
                    case self::PHASE_OBJECTS:
                        $batch = $this->objectsBatch($gateway, $bucket, $state);

                        if ($batch === []) {
                            $state['phase'] = $gateway->supports(StorageCapability::MultipartUploads) ? self::PHASE_UPLOADS : self::PHASE_BUCKET;
                            break;
                        }

                        $failed = $gateway->deleteObjects($bucket, $batch);

                        if ($failed !== []) {
                            $this->operations->fail($operationId, __('Could not delete :count objects, for example :key: :error', [
                                'count' => count($failed),
                                'key' => $failed[0]['key'],
                                'error' => trim($failed[0]['code'].' '.$failed[0]['message']),
                            ]));

                            return true;
                        }

                        $processed += count($batch);
                        $this->operations->progress($operationId, $processed, $state);
                        break;

                    case self::PHASE_UPLOADS:
                        $uploads = $this->uploadsBatch($gateway, $bucket);

                        if ($uploads === []) {
                            $state['phase'] = self::PHASE_BUCKET;
                            break;
                        }

                        foreach ($uploads as $upload) {
                            $gateway->abortUpload($bucket, $upload['key'], $upload['uploadId']);
                        }
                        break;

                    case self::PHASE_BUCKET:
                        $this->deleteBucket($gateway, $bucket);
                        $this->buckets->forgetDeleted($operation->storage_id, $bucket, $operation->user_id, $processed);
                        $this->operations->complete($operationId, ['deleted' => $processed]);

                        return true;
                }
            }

            $this->operations->progress($operationId, $processed, $state);

            return false;
        } catch (Throwable $e) {
            report_if(config('app.debug'), $e);
            Log::warning('Bucket deletion failed', ['operation' => $operationId, 'error' => $e->getMessage()]);
            $this->operations->fail($operationId, $this->errors->message($e));

            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<array{key: string, versionId?: string|null}>
     */
    private function objectsBatch(StorageGateway $gateway, string $bucket, array &$state): array
    {
        if ($state['versions']) {
            try {
                return array_map(
                    fn (array $item) => ['key' => $item['key'], 'versionId' => $item['versionId']],
                    $gateway->listVersionsPage($bucket, null, null)['items'],
                );
            } catch (AwsException $e) {
                if ($e->getAwsErrorCode() !== 'NotImplemented') {
                    throw $e;
                }

                report_if(config('app.debug'), $e);

                // Версионирование отмечено, но хранилище его не поддерживает — удаляем как обычные объекты
                $state['versions'] = false;
            }
        }

        return array_map(fn (array $item) => ['key' => $item['key']], $gateway->listObjectsPage($bucket, null)['items']);
    }

    /**
     * @return list<array{key: string, uploadId: string}>
     */
    private function uploadsBatch(StorageGateway $gateway, string $bucket): array
    {
        try {
            return $gateway->listUploadsPage($bucket, null, null)['items'];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'NotImplemented') {
                report_if(config('app.debug'), $e);

                return [];
            }

            throw $e;
        }
    }

    private function deleteBucket(StorageGateway $gateway, string $bucket): void
    {
        try {
            $gateway->deleteBucket($bucket);
        } catch (AwsException $e) {
            // Уже удалён — цель достигнута
            if ($e->getAwsErrorCode() !== 'NoSuchBucket') {
                throw $e;
            }

            report_if(config('app.debug'), $e);
        }
    }
}
