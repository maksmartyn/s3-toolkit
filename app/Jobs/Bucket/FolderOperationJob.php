<?php

declare(strict_types=1);

namespace App\Jobs\Bucket;

use App\Services\Object\FolderOperationRunner;

final class FolderOperationJob extends BucketOperationJob
{
    protected function runner(): string
    {
        return FolderOperationRunner::class;
    }
}
