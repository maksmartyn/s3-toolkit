<?php

declare(strict_types=1);

namespace App\Domain\DTO\Object;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Объект или конкретная его версия.
 */
final class ObjectReferenceData extends DTO
{
    public string $key;

    public ?string $versionId = null;
}
