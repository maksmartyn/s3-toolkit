<?php

declare(strict_types=1);

namespace App\Domain\DTO\Storage;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Storage\StorageProvider;

/**
 * Подсказки формы для провайдера.
 */
final class ProviderPresetData extends DTO
{
    public StorageProvider $value;

    public string $label;

    public ?string $defaultEndpoint;

    public bool $endpointRequired;

    public string $defaultRegion;

    public bool $defaultPathStyle;

    /** @var list<string> функции, поддерживаемые по умолчанию */
    public array $capabilities;
}
