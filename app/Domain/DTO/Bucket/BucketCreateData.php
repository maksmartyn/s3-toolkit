<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;

final class BucketCreateData extends DTO
{
    public string $name;

    /** Object Lock включается только при создании бакета */
    public bool $objectLock = false;
}
