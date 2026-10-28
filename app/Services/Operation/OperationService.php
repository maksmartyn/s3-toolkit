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
    public function start(?int $userId, int $storageId, OperationType $type, string $bucket, ?int $total = null): OperationData
    {
        if ($this->operations->activeForBucket($storageId, $bucket) !== null) {
            throw new DomainException(__('Another operation is already running for this bucket.'));
        }

        $operation = $this->operations->create([
            'user_id' => $userId,
            'storage_id' => $storageId,
            'type' => $type,
            'bucket' => $bucket,
            'status' => OperationStatus::Queued,
            'total' => $total,
        ]);

        $data = $this->broadcast($operation);

        // После коммита: иначе задача может начаться раньше, чем операция появится в БД
        match ($type) {
            OperationType::BucketDelete => DeleteBucketJob::dispatch($operation->id)->afterCommit(),
            OperationType::BucketStats => CalculateBucketStatsJob::dispatch($operation->id)->afterCommit(),
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
            'state' => $state,
            'started_at' => $operation->started_at ?? now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function complete(int $id, array $result = []): OperationData
    {
        return $this->broadcast($this->operations->update($id, [
            'status' => OperationStatus::Completed,
            'result' => $result === [] ? null : $result,
            'state' => null,
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
     * @return list<OperationData>
     */
    public function activeFor(int $storageId): array
    {
        return array_values($this->operations->activeFor($storageId)->map($this->toData(...))->all());
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
     * Недоступность WebSocket-сервера не должна ломать саму операцию: интерфейс догонит состояние при перезагрузке.
     */
    private function broadcast(BackgroundOperation $operation): OperationData
    {
        $data = $this->toData($operation);

        try {
            $this->events->dispatch(new OperationUpdated($data));
        } catch (Throwable $e) {
            Log::warning('Operation broadcast failed', ['operation' => $operation->id, 'error' => $e->getMessage()]);
        }

        return $data;
    }
}
