<?php

declare(strict_types=1);

namespace App\Domain\DTO\Object;

use App\Domain\DTO\Abstracts\DTO;
use Carbon\CarbonImmutable;

final class ObjectItemData extends DTO
{
    public string $key;

    /** Имя внутри текущей «папки» */
    public string $name;

    public int $size;

    public ?CarbonImmutable $lastModified = null;

    public ?string $storageClass = null;

    public ?string $etag = null;
}
