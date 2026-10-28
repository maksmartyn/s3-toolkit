<?php

declare(strict_types=1);

namespace App\Domain\DTO\Bucket;

use App\Domain\DTO\Abstracts\DTO;

final class BucketTagData extends DTO
{
    public string $key;

    public string $value;
}
