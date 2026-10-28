<?php

declare(strict_types=1);

namespace App\Domain\DTO\Storage;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Storage\StorageProvider;
use Carbon\CarbonImmutable;

/**
 * Параметры подключения для тех, кто управляет хранилищами. Секрет не передаётся, access key — частично скрыт.
 */
final class StorageDetailsData extends DTO
{
    public int $id;

    public string $name;

    public StorageProvider $provider;

    public string $providerLabel;

    /** @var list<string> включённые функции (StorageCapability) */
    public array $capabilities = [];

    public ?string $endpoint;

    public ?string $publicEndpoint = null;

    public string $region;

    public bool $usePathStyle;

    public bool $verifySsl;

    public string $accessKeyIdMasked;

    /** @var list<string>|null */
    public ?array $buckets;

    public ?CarbonImmutable $createdAt;

    public ?CarbonImmutable $updatedAt;
}
