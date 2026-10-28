<?php

declare(strict_types=1);

namespace App\Domain\DTO\Storage;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Storage\StorageProvider;

/**
 * Создание и изменение хранилища. При изменении пустые ключи означают «оставить текущие».
 */
final class StorageSaveData extends DTO
{
    public string $name;

    public StorageProvider $provider;

    public ?string $endpoint = null;

    public string $region;

    public bool $usePathStyle = false;

    public bool $verifySsl = true;

    public ?string $accessKeyId = null;

    public ?string $secretAccessKey = null;

    /** @var list<string>|null */
    public ?array $buckets = null;

    public function hasNewCredentials(): bool
    {
        return $this->accessKeyId !== null && $this->secretAccessKey !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...parent::toArray(), 'secretAccessKey' => $this->secretAccessKey === null ? null : '***'];
    }
}
