<?php

declare(strict_types=1);

namespace App\Services\Operation;

use App\Domain\DTO\Operation\OperationData;
use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Operation\OperationType;
use App\Events\OperationUpdated;
use App\Exceptions\DomainException;
use App\Jobs\Bucket\CalculateBucketStatsJob;
use App\Jobs\Bucket\DeleteBucketJob;
use App\Jobs\Bucket\FolderOperationJob;
use App\Models\BackgroundOperation;
use App\Repositories\Operation\OperationRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Фоновые операции над бакетами: создание, ход выполнения, завершение. Каждое изменение рассылается по WebSocket.
 */
final readonly class OperationService
{
    public function __construct(
        private OperationRepositoryInterface $operations,
        private Dispatcher $events,
    ) {}

    /**
     * Ставит операцию в очередь. Над одним бакетом одновременно идёт не больше одной операции.
     */
    /**
     * @param  array<string, mixed>  $params  параметры операции (префикс, куда копировать); хранятся в состоянии
     */
    public function start(?int $userId, int $storageId, OperationType $type, string $bucket, ?int $total = null, array $params = []): OperationData
    {
        $this->ensureNoConflict($storageId, $type, $bucket, isset($params['prefix']) ? (string) $params['prefix'] : null);

        $operation = $this->operations->create([
            'user_id' => $userId,
            'storage_id' => $storageId,
            'type' => $type,
            'bucket' => $bucket,
            'status' => OperationStatus::Queued,
            'total' => $total,
            'state' => $params === [] ? null : ['params' => $params],
        ]);

        $data = $this->broadcast($operation);

        // После коммита: иначе задача может начаться раньше, чем операция появится в БД
        match ($type) {
            OperationType::BucketDelete => DeleteBucketJob::dispatch($operation->id)->afterCommit(),
            OperationType::BucketStats => CalculateBucketStatsJob::dispatch($operation->id)->afterCommit(),
            OperationType::FolderDelete, OperationType::FolderCopy, OperationType::FolderMove => FolderOperationJob::dispatch($operation->id)->afterCommit(),
        };

        return $data;
    }

    /**
     * Активная операция для задачи; null — операция завершена или удалена вместе с хранилищем.
     */
    public function findActive(int $id): ?BackgroundOperation
    {
        $operation = $this->operations->find($id);

        return $operation !== null && $operation->status->isActive() ? $operation : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function progress(int $id, int $processed, array $state): void
    {
        $operation = $this->operations->findOrFail($id);

        $this->broadcast($this->operations->update($id, [
            'status' => OperationStatus::Running,
            'processed' => $processed,
            // Шаг продвинулся — счётчик таймаутов подряд обнуляется
            'state' => array_diff_key($state, ['timeouts' => true]),
            'started_at' => $operation->started_at ?? now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function complete(int $id, array $result = []): OperationData
    {
        $params = $this->operations->findOrFail($id)->state['params'] ?? null;

        return $this->broadcast($this->operations->update($id, [
            'status' => OperationStatus::Completed,
            'result' => $result === [] ? null : $result,
            // Параметры оставляем: по ним видно, над какой «папкой» шла операция
            'state' => $params === null ? null : ['params' => $params],
            'finished_at' => now(),
        ]));
    }

    public function fail(int $id, string $error): OperationData
    {
        return $this->broadcast($this->operations->update($id, [
            'status' => OperationStatus::Failed,
            'error' => $error,
            'finished_at' => now(),
        ]));
    }

    /**
     * Шаг задачи не завершился. true — продолжить операцию новой задачей, false — операция завершена (неудачей).
     */
    public function stepFailed(int $id, bool $timedOut): bool
    {
        $operation = $this->findActive($id);

        if ($operation === null) {
            return false;
        }

        $state = $operation->state ?? [];
        $timeouts = (int) ($state['timeouts'] ?? 0) + 1;

        if ($timedOut && $timeouts <= (int) config('s3toolkit.operations.max_timeouts', 3)) {
            Log::warning('Operation step timed out, continuing', ['operation' => $id, 'timeouts' => $timeouts]);
            $this->operations->update($id, ['state' => [...$state, 'timeouts' => $timeouts]]);

            return true;
        }

        $this->fail($id, $timedOut
            ? __('The storage responds too slowly: a step of the operation did not finish in :seconds seconds several times in a row. Try again later.', [
                'seconds' => (string) config('s3toolkit.operations.job_timeout'),
            ])
            : __('The operation was interrupted by an unexpected error.'));

        return false;
    }

    /**
     * @return list<OperationData>
     */
    public function activeFor(int $storageId): array
    {
        return array_map($this->toData(...), $this->active($storageId));
    }

    /**
     * @return list<OperationData>
     */
    public function recentFinished(int $storageId, int $limit = 10): array
    {
        return array_values($this->operations->recentFinished($storageId, $limit)->map($this->toData(...))->all());
    }

    public function toData(BackgroundOperation $operation): OperationData
    {
        return new OperationData([
            'id' => $operation->id,
            'storageId' => $operation->storage_id,
            'type' => $operation->type,
            'typeLabel' => $operation->type->label(),
            'bucket' => $operation->bucket,
            'prefix' => isset($operation->state['params']['prefix']) ? (string) $operation->state['params']['prefix'] : null,
            'status' => $operation->status,
            'statusLabel' => $operation->status->label(),
            'processed' => $operation->processed,
            'total' => $operation->total,
            'error' => $operation->error,
            'userId' => $operation->user_id,
            'startedAt' => $operation->started_at?->toImmutable(),
            'finishedAt' => $operation->finished_at?->toImmutable(),
            'createdAt' => $operation->created_at?->toImmutable(),
        ]);
    }

    /**
     * Над бакетом целиком (удаление, статистика) — только одна операция; над разными «папками» — несколько,
     * но не над одной и той же и не вместе с операцией над всем бакетом.
     */
    private function ensureNoConflict(int $storageId, OperationType $type, string $bucket, ?string $prefix): void
    {
        $bucketLevel = fn (OperationType $t): bool => in_array($t, [OperationType::BucketDelete, OperationType::BucketStats], true);

        foreach ($this->active($storageId) as $active) {
            if ($active->bucket !== $bucket) {
                continue;
            }

            $activePrefix = isset($active->state['params']['prefix']) ? (string) $active->state['params']['prefix'] : null;

            if ($bucketLevel($type) || $bucketLevel($active->type) || $activePrefix === $prefix) {
                throw new DomainException(__('Another operation is already running for this bucket.'));
            }
        }
    }

    /**
     * Активные операции хранилища. Те, что давно не продвигались, помечаются прерванными:
     * их задачу убили (нехватка памяти, перезапуск контейнера), и иначе они навсегда заняли бы бакет.
     *
     * @return list<BackgroundOperation>
     */
    private function active(int $storageId): array
    {
        $minutes = (int) config('s3toolkit.operations.stale_minutes', 30);
        $active = [];

        foreach ($this->operations->activeFor($storageId) as $operation) {
            if ($operation->updated_at !== null && $operation->updated_at->lt(now()->subMinutes($minutes))) {
                Log::warning('Stale operation marked as failed', ['operation' => $operation->id]);
                $this->fail($operation->id, __('The operation was interrupted: no progress for :minutes minutes.', ['minutes' => (string) $minutes]));

                continue;
            }

            $active[] = $operation;
        }

        return $active;
    }

    /**
     * Недоступность WebSocket-сервера не должна ломать саму операцию: интерфейс догонит состояние при перезагрузке.
     */
    private function broadcast(BackgroundOperation $operation): OperationData
    {
        $data = $this->toData($operation);

        try {
            $this->events->dispatch(new OperationUpdated($data));
        } catch (Throwable $e) {
            report_if(config('app.debug'), $e);
            Log::warning('Operation broadcast failed', ['operation' => $operation->id, 'error' => $e->getMessage()]);
        }

        return $data;
    }
}
