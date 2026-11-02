<?php

declare(strict_types=1);

namespace App\Services\Object;

use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Operation\OperationType;
use App\Services\Audit\AuditDetails;
use App\Services\Bucket\BucketAuditor;
use App\Services\Bucket\Contracts\OperationRunner;
use App\Services\Operation\OperationService;
use App\Services\Storage\StorageErrorTranslator;
use App\Services\Storage\StorageGatewayFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Удаление, копирование и перемещение «папки» (всех объектов с префиксом).
 *
 * Удаление удаляет текущие объекты: в версионированном бакете это маркеры удаления, данные можно восстановить.
 * Копирование идёт по страницам с маркером; перемещение — копия страницы, затем удаление её оригиналов.
 */
final readonly class FolderOperationRunner implements OperationRunner
{
    public function __construct(
        private OperationService $operations,
        private StorageGatewayFactory $gateways,
        private StorageErrorTranslator $errors,
        private BucketAuditor $auditor,
    ) {}

    public function run(int $operationId, float $budgetSeconds): bool
    {
        $operation = $this->operations->findActive($operationId);

        if ($operation === null) {
            return true;
        }

        $state = $operation->state ?? [];
        $params = (array) ($state['params'] ?? []);
        $prefix = (string) ($params['prefix'] ?? '');
        $processed = $operation->processed;
        $deadline = microtime(true) + $budgetSeconds;
        $bucket = $operation->bucket;

        try {
            $gateway = $this->gateways->forStorage($operation->storage_id);

            while (microtime(true) < $deadline) {
                // При удалении берём первую страницу: удалённое из неё пропадает. При копировании — по маркеру
                $token = $operation->type === OperationType::FolderCopy ? ($state['token'] ?? null) : null;
                $page = $gateway->listPrefixPage($bucket, $prefix, $token);

                if ($page['items'] === []) {
                    return $this->finish($operationId, $operation->storage_id, $bucket, $operation->type, $params, $processed, $operation->user_id);
                }

                if ($operation->type !== OperationType::FolderDelete) {
                    $targetBucket = (string) $params['targetBucket'];
                    $targetPrefix = (string) $params['targetPrefix'];

                    foreach ($page['items'] as $item) {
                        $gateway->copyObject($bucket, $item['key'], null, $targetBucket, $targetPrefix.substr($item['key'], strlen($prefix)));
                    }
                }

                if ($operation->type !== OperationType::FolderCopy) {
                    $failed = $gateway->deleteObjects($bucket, array_map(fn (array $item) => ['key' => $item['key']], $page['items']));

                    if ($failed !== []) {
                        $this->operations->fail($operationId, __('Could not delete :count objects, for example :key: :error', [
                            'count' => count($failed),
                            'key' => $failed[0]['key'],
                            'error' => trim($failed[0]['code'].' '.$failed[0]['message']),
                        ]));

                        return true;
                    }
                }

                $processed += count($page['items']);
                $state['token'] = $page['next'];
                $this->operations->progress($operationId, $processed, $state);

                if ($operation->type === OperationType::FolderCopy && $page['next'] === null) {
                    return $this->finish($operationId, $operation->storage_id, $bucket, $operation->type, $params, $processed, $operation->user_id);
                }
            }

            return false;
        } catch (Throwable $e) {
            report_if(config('app.debug'), $e);
            Log::warning('Folder operation failed', ['operation' => $operationId, 'error' => $e->getMessage()]);
            $this->operations->fail($operationId, $this->errors->message($e));

            return true;
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function finish(int $operationId, int $storageId, string $bucket, OperationType $type, array $params, int $processed, ?int $actorId): bool
    {
        $action = match ($type) {
            OperationType::FolderDelete => AuditAction::FolderDeleted,
            OperationType::FolderMove => AuditAction::FolderMoved,
            default => null,
        };

        if ($action !== null) {
            $this->auditor->record($action, $storageId, $bucket, AuditDetails::values(array_filter([
                'prefix' => (string) ($params['prefix'] ?? ''),
                'target' => isset($params['targetBucket']) ? $params['targetBucket'].'/'.$params['targetPrefix'] : null,
                'deleted_objects' => $type === OperationType::FolderDelete ? $processed : null,
            ], fn (mixed $value) => $value !== null)), $actorId);
        }

        $this->operations->complete($operationId, ['processed' => $processed]);

        return true;
    }
}
