<?php

declare(strict_types=1);

namespace App\Domain\DTO\Storage;

use App\Domain\DTO\Abstracts\DTO;
use App\Domain\Enum\Storage\StorageProvider;

/**
 * Параметры подключения по S3. Содержит секрет: в toArray() он скрыт, чтобы не утёк в пропсы или логи.
 */
final class StorageConnectionData extends DTO
{
    public StorageProvider $provider;

    public ?string $endpoint = null;

    /** Адрес для ссылок, открываемых в браузере; null — тот же, что endpoint */
    public ?string $publicEndpoint = null;

    public string $region;

    public bool $usePathStyle = false;

    public bool $verifySsl = true;

    public string $accessKeyId;

    public string $secretAccessKey;

    /** @var list<string>|null заданные вручную бакеты; null — список берётся через ListBuckets */
    public ?array $buckets = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [...parent::toArray(), 'secretAccessKey' => '***'];
    }

    /**
     * @return array<string, mixed>
     */
    public function initialized(bool $recursive = false): array
    {
        $values = parent::initialized($recursive);

        if (isset($values['secretAccessKey'])) {
            $values['secretAccessKey'] = '***';
        }

        return $values;
    }
}
