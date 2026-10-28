<?php

declare(strict_types=1);

namespace App\Jobs\Bucket;

use App\Services\Bucket\BucketStatsRunner;

final class CalculateBucketStatsJob extends BucketOperationJob
{
    protected function runner(): string
    {
        return BucketStatsRunner::class;
    }
}
