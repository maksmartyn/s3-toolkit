<?php

declare(strict_types=1);

namespace App\Services\Bucket;

use App\Domain\DTO\Audit\AuditRecordData;
use App\Domain\DTO\Bucket\BucketCreateData;
use App\Domain\DTO\Bucket\BucketData;
use App\Domain\DTO\Bucket\BucketListData;
use App\Domain\DTO\Bucket\BucketStatsData;
use App\Domain\DTO\Operation\OperationData;
use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Operation\OperationType;
use App\Domain\Enum\Storage\StorageCapability;
use App\Exceptions\DomainException;
use App\Models\BucketStats;
use App\Repositories\Bucket\BucketStatsRepositoryInterface;
use App\Repositories\Storage\StorageRepositoryInterface;
use App\Services\Audit\AuditDetails;
use App\Services\Audit\AuditService;
use App\Services\Operation\OperationService;
use App\Services\Storage\StorageErrorTranslator;
use App\Services\Storage\StorageGatewayFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class BucketService
{
    public function __construct(
        private StorageGatewayFactory $gateways,
        private StorageRepositoryInterface $storages,
        private BucketStatsRepositoryInterface $stats,
        private OperationService $operations,
        private StorageErrorTranslator $errors,
        private AuditService $audit,
    ) {}

    /**
     * Бакеты хранилища со статистикой и активными операциями. Ошибка хранилища возвращается текстом, а не исключением.
     */
    public function list(int $storageId): BucketListData
    {
        try {
            $gateway = $this->gateways->forStorage($storageId);

            $buckets = $gateway->fixedBuckets !== null
                ? array_map(fn (string $name) => ['name' => $name, 'createdAt' => null], $gateway->fixedBuckets)
                : $gateway->listBuckets();
        } catch (DomainException $e) {
            return new BucketListData(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            return new BucketListData(['error' => $this->errors->message($e)]);
        }

        $stats = $this->stats->forStorage($storageId);
        $operations = collect($this->operations->activeFor($storageId))->groupBy(fn (OperationData $operation) => $operation->bucket);

        usort($buckets, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return new BucketListData(['buckets' => array_map(fn (array $bucket) => new BucketData([
            'name' => $bucket['name'],
            'createdAt' => $bucket['createdAt'],
            'stats' => isset($stats[$bucket['name']]) ? $this->statsData($stats[$bucket['name']]) : null,
            'operations' => array_values($operations->get($bucket['name'], collect())->all()),
        ]), $buckets)]);
    }

    public function create(int $storageId, BucketCreateData $data): void
    {
        $gateway = $this->gateways->forStorage($storageId);

        if ($data->objectLock && ! $gateway->supports(StorageCapability::ObjectLock)) {
            throw new DomainException(__('This storage does not support Object Lock.'));
        }

        try {
            $gateway->createBucket($data->name, $data->objectLock);
        } catch (Throwable $e) {
            throw new DomainException(__('Could not create the bucket: :error', ['error' => $this->errors->message($e)]), previous: $e);
        }

        DB::transaction(function () use ($storageId, $data, $gateway): void {
            $storage = $this->storages->findOrFail($storageId);

            // Ключ ограничен списком бакетов — добавляем новый, иначе он не появится в сервисе
            if ($gateway->fixedBuckets !== null && ! in_array($data->name, $gateway->fixedBuckets, true)) {
                $this->storages->update($storageId, ['buckets' => [...$gateway->fixedBuckets, $data->name]]);
            }

            $this->audit->record(new AuditRecordData([
                'action' => AuditAction::BucketCreated,
                'subjectId' => $storageId,
                'subjectLabel' => $data->name,
                'details' => [
                    ...AuditDetails::storage($storageId, $storage->name),
                    ...($data->objectLock ? AuditDetails::flags(['objectLock' => true]) : []),
                ],
            ]));
        });
    }

    /**
     * Запускает удаление бакета со всем содержимым в фоне. Запуск пишется в журнал сразу:
     * даже если операция прервётся, часть данных уже может быть удалена.
     */
    public function requestDeletion(int $actorId, int $storageId, string $bucket): OperationData
    {
        $this->ensureKnown($storageId, $bucket);

        return DB::transaction(function () use ($actorId, $storageId, $bucket): OperationData {
            $stats = $this->stats->find($storageId, $bucket);
            $operation = $this->operations->start(
                $actorId,
                $storageId,
                OperationType::BucketDelete,
                $bucket,
                $stats === null ? null : ($stats->version_count ?? $stats->object_count),
            );

            $this->recordBucket(AuditAction::BucketDeletionStarted, $storageId, $bucket);

            return $operation;
        });
    }

    public function requestStats(int $actorId, int $storageId, string $bucket): OperationData
    {
        $this->ensureKnown($storageId, $bucket);

        return $this->operations->start($actorId, $storageId, OperationType::BucketStats, $bucket);
    }

    /**
     * Вызывается фоновой операцией после удаления бакета в хранилище.
     */
    public function forgetDeleted(int $storageId, string $bucket, ?int $actorId, int $deletedObjects): void
    {
        DB::transaction(function () use ($storageId, $bucket, $actorId, $deletedObjects): void {
            $storage = $this->storages->findOrFail($storageId);

            $this->stats->remove($storageId, $bucket);

            if ($storage->buckets !== null) {
                $this->storages->update($storageId, [
                    'buckets' => array_values(array_diff($storage->buckets, [$bucket])) ?: null,
                ]);
            }

            $this->recordBucket(AuditAction::BucketDeleted, $storageId, $bucket, $actorId, AuditDetails::values([
                'deleted_objects' => $deletedObjects,
            ]));
        });
    }

    public function stats(int $storageId, string $bucket): ?BucketStatsData
    {
        $stats = $this->stats->find($storageId, $bucket);

        return $stats === null ? null : $this->statsData($stats);
    }

    public function saveStats(int $storageId, string $bucket, BucketStatsData $stats): void
    {
        $this->stats->put($storageId, $bucket, $stats);
    }

    private function statsData(BucketStats $stats): BucketStatsData
    {
        return new BucketStatsData([
            'objectCount' => $stats->object_count,
            'totalSize' => $stats->total_size,
            'versionCount' => $stats->version_count,
            'versionsSize' => $stats->versions_size,
            'calculatedAt' => $stats->calculated_at->toImmutable(),
        ]);
    }

    /**
     * Для ключей со списком бакетов работаем только с ними; иначе существование проверит сама операция.
     */
    private function ensureKnown(int $storageId, string $bucket): void
    {
        $storage = $this->storages->findOrFail($storageId);

        if ($storage->buckets !== null && ! in_array($bucket, $storage->buckets, true)) {
            throw new DomainException(__('The bucket does not exist.'));
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordBucket(AuditAction $action, int $storageId, string $bucket, ?int $actorId = null, array $details = []): void
    {
        $storage = $this->storages->findOrFail($storageId);

        $this->audit->record(new AuditRecordData([
            'action' => $action,
            'subjectId' => $storageId,
            'subjectLabel' => $bucket,
            'actorId' => $actorId,
            'details' => [...AuditDetails::storage($storageId, $storage->name), ...$details],
        ]));
    }
}
