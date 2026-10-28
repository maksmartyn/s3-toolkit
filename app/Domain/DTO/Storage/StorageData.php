<?php

declare(strict_types=1);

namespace App\Domain\DTO\Storage;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Storage\StorageProvider;

/**
 * Хранилище в списке: без параметров подключения, с правами текущего пользователя.
 */
final class StorageData extends DTO
{
    public int $id;

    public string $name;

    public StorageProvider $provider;

    public string $providerLabel;

    /** @var list<string> включённые функции (StorageCapability) */
    public array $capabilities = [];

    /** @var list<int> права текущего пользователя на хранилище */
    public array $permissions = [];
}
