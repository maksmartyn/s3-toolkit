<?php

declare(strict_types=1);

namespace App\Jobs\Bucket;

use App\Services\Bucket\BucketDeletionRunner;

final class DeleteBucketJob extends BucketOperationJob
{
    protected function runner(): string
    {
        return BucketDeletionRunner::class;
    }
}
