<?php

declare(strict_types=1);

namespace App\Services\Bucket;

use App\Domain\DTO\Bucket\BucketStatsData;
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
 * Подсчёт объектов и размера бакета. Если у хранилища есть версионирование, считаются и все версии.
 * Промежуточные суммы и маркеры пагинации хранятся в состоянии операции между шагами.
 */
final readonly class BucketStatsRunner implements OperationRunner
{
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
        $deadline = microtime(true) + $budgetSeconds;

        try {
            $gateway = $this->gateways->forStorage($operation->storage_id);

            $state = $operation->state ?? [
                'versions' => $gateway->supports(StorageCapability::Versioning),
                'objects' => 0,
                'size' => 0,
                'versionCount' => 0,
                'versionsSize' => 0,
                'token' => null,
                'keyMarker' => null,
                'versionMarker' => null,
            ];

            while (microtime(true) < $deadline) {
                $done = $state['versions'] ? $this->versionsPage($gateway, $bucket, $state) : $this->objectsPage($gateway, $bucket, $state);

                if ($done) {
                    $stats = new BucketStatsData([
                        'objectCount' => (int) $state['objects'],
                        'totalSize' => (int) $state['size'],
                        'versionCount' => $state['versions'] ? (int) $state['versionCount'] : null,
                        'versionsSize' => $state['versions'] ? (int) $state['versionsSize'] : null,
                        'calculatedAt' => now()->toImmutable(),
                    ]);

                    $this->buckets->saveStats($operation->storage_id, $bucket, $stats);
                    $this->operations->complete($operationId, $stats->toArray());

                    return true;
                }

                $this->operations->progress($operationId, $this->processed($state), $state);
            }

            return false;
        } catch (Throwable $e) {
            Log::warning('Bucket stats failed', ['operation' => $operationId, 'error' => $e->getMessage()]);
            $this->operations->fail($operationId, $this->errors->message($e));

            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function objectsPage(StorageGateway $gateway, string $bucket, array &$state): bool
    {
        $page = $gateway->listObjectsPage($bucket, $state['token']);

        foreach ($page['items'] as $item) {
            $state['objects']++;
            $state['size'] += $item['size'];
        }

        $state['token'] = $page['next'];

        return $page['next'] === null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function versionsPage(StorageGateway $gateway, string $bucket, array &$state): bool
    {
        try {
            $page = $gateway->listVersionsPage($bucket, $state['keyMarker'], $state['versionMarker']);
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() !== 'NotImplemented') {
                throw $e;
            }

            // Версионирование отмечено, но не поддерживается — считаем только текущие объекты с начала
            $state = [...$state, 'versions' => false, 'objects' => 0, 'size' => 0];

            return false;
        }

        foreach ($page['items'] as $item) {
            $state['versionCount']++;
            $state['versionsSize'] += $item['size'];

            if ($item['isLatest'] && ! $item['isDeleteMarker']) {
                $state['objects']++;
                $state['size'] += $item['size'];
            }
        }

        $state['keyMarker'] = $page['nextKey'];
        $state['versionMarker'] = $page['nextVersion'];

        return $page['nextKey'] === null && $page['nextVersion'] === null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function processed(array $state): int
    {
        return (int) ($state['versions'] ? $state['versionCount'] : $state['objects']);
    }
}
