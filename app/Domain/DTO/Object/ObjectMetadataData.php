<?php

declare(strict_types=1);

namespace App\Domain\DTO\Object;

use App\Domain\DTO\Abstracts\DTO;

/**
 * Изменяемые метаданные объекта. Пустые значения стандартных заголовков — не задавать.
 */
final class ObjectMetadataData extends DTO
{
    public ?string $contentType = null;

    public ?string $cacheControl = null;

    public ?string $contentDisposition = null;

    /** @var array<string, string> */
    public array $metadata = [];
}
