<?php

declare(strict_types=1);

namespace App\Repositories\Operation;

use App\Domain\Enum\Operation\OperationStatus;
use App\Domain\Enum\Operation\OperationType;
use App\Models\BackgroundOperation;
use App\Repositories\Abstracts\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<BackgroundOperation>
 */
final class OperationRepository extends BaseRepository implements OperationRepositoryInterface
{
    protected function model(): string
    {
        return BackgroundOperation::class;
    }

    public function activeFor(int $storageId): Collection
    {
        return $this->query()
            ->where('storage_id', $storageId)
            ->whereIn('status', OperationStatus::active())
            ->orderBy('id')
            ->get();
    }

    public function activeForBucket(int $storageId, string $bucket, ?OperationType $type = null): ?BackgroundOperation
    {
        return $this->query()
            ->where('storage_id', $storageId)
            ->where('bucket', $bucket)
            ->whereIn('status', OperationStatus::active())
            ->when($type !== null, fn (Builder $q) => $q->where('type', $type))
            ->first();
    }

    public function recentFinished(int $storageId, int $limit): Collection
    {
        return $this->query()
            ->where('storage_id', $storageId)
            ->whereNotIn('status', OperationStatus::active())
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
