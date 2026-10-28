<?php

declare(strict_types=1);

namespace App\Http\Requests\Bucket;

use App\Domain\Enum\Permission\StoragePermission;

final class BucketStatsRequest extends BucketRequest
{
    protected function permission(): StoragePermission
    {
        return StoragePermission::ViewBuckets;
    }
}
