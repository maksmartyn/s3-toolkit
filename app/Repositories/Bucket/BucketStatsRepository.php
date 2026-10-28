<?php

declare(strict_types=1);

namespace App\Repositories\Bucket;

use App\Domain\DTO\Bucket\BucketStatsData;
use App\Models\BucketStats;
use Illuminate\Support\Collection;

final class BucketStatsRepository implements BucketStatsRepositoryInterface
{
    public function forStorage(int $storageId): Collection
    {
        return BucketStats::query()->where('storage_id', $storageId)->get()->keyBy('bucket')->toBase();
    }

    public function find(int $storageId, string $bucket): ?BucketStats
    {
        return BucketStats::query()->where('storage_id', $storageId)->where('bucket', $bucket)->first();
    }

    public function put(int $storageId, string $bucket, BucketStatsData $stats): void
    {
        BucketStats::query()->updateOrCreate(
            ['storage_id' => $storageId, 'bucket' => $bucket],
            [
                'object_count' => $stats->objectCount,
                'total_size' => $stats->totalSize,
                'version_count' => $stats->versionCount,
                'versions_size' => $stats->versionsSize,
                'calculated_at' => $stats->calculatedAt,
            ],
        );
    }

    public function remove(int $storageId, string $bucket): void
    {
        BucketStats::query()->where('storage_id', $storageId)->where('bucket', $bucket)->delete();
    }
}
