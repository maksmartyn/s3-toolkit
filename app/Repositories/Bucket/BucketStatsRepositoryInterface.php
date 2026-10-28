<?php

declare(strict_types=1);

namespace App\Repositories\Bucket;

use App\Domain\DTO\Bucket\BucketStatsData;
use App\Models\BucketStats;
use Illuminate\Support\Collection;

interface BucketStatsRepositoryInterface
{
    /**
     * @return Collection<string, BucketStats> по имени бакета
     */
    public function forStorage(int $storageId): Collection;

    public function find(int $storageId, string $bucket): ?BucketStats;

    public function put(int $storageId, string $bucket, BucketStatsData $stats): void;

    public function remove(int $storageId, string $bucket): void;
}
