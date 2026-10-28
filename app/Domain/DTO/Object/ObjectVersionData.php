<?php

declare(strict_types=1);

namespace App\Domain\DTO\Object;

use App\Domain\DTO\Abstracts\DTO;
use Carbon\CarbonImmutable;

final class ObjectVersionData extends DTO
{
    public ?string $versionId;

    public int $size;

    public ?CarbonImmutable $lastModified;

    public bool $isLatest;

    public bool $isDeleteMarker;
}
