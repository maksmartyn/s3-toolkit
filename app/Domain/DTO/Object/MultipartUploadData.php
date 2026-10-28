<?php

declare(strict_types=1);

namespace App\Domain\DTO\Object;

use App\Domain\DTO\Abstracts\DTO;
use Carbon\CarbonImmutable;

final class MultipartUploadData extends DTO
{
    public string $key;

    public string $uploadId;

    public ?CarbonImmutable $initiated = null;
}
