<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Domain\DTO\Storage\ProviderPresetData;
use App\Domain\DTO\Storage\StorageConnectionData;
use App\Domain\DTO\Storage\StorageData;
use App\Domain\DTO\Storage\StorageDetailsData;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Storage\StorageProvider;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Models\Storage;

final readonly class StorageMapper
{
    public function __construct(private StorageCapabilities $capabilities) {}

    /**
     * @param  PermissionMask<StoragePermission>  $permissions  права текущего пользователя
     */
    public function toData(Storage $storage, PermissionMask $permissions): StorageData
    {
        return new StorageData([
            'id' => $storage->id,
            'name' => $storage->name,
            'provider' => $storage->provider,
            'providerLabel' => $storage->provider->label(),
            'capabilities' => StorageCapabilities::values($this->capabilities->enabled($storage)),
            'permissions' => $permissions->values(),
        ]);
    }

    public function toDetails(Storage $storage): StorageDetailsData
    {
        return new StorageDetailsData([
            'id' => $storage->id,
            'name' => $storage->name,
            'provider' => $storage->provider,
            'providerLabel' => $storage->provider->label(),
            'capabilities' => StorageCapabilities::values($this->capabilities->enabled($storage)),
            'endpoint' => $storage->endpoint,
            'publicEndpoint' => $storage->public_endpoint,
            'region' => $storage->region,
            'usePathStyle' => $storage->use_path_style,
            'verifySsl' => $storage->verify_ssl,
            'accessKeyIdMasked' => $this->mask($storage->access_key_id),
            'buckets' => $storage->buckets,
            'createdAt' => $storage->created_at?->toImmutable(),
            'updatedAt' => $storage->updated_at?->toImmutable(),
        ]);
    }

    public function toConnection(Storage $storage): StorageConnectionData
    {
        return new StorageConnectionData([
            'provider' => $storage->provider,
            'endpoint' => $storage->endpoint,
            'publicEndpoint' => $storage->public_endpoint,
            'region' => $storage->region,
            'usePathStyle' => $storage->use_path_style,
            'verifySsl' => $storage->verify_ssl,
            'accessKeyId' => $storage->access_key_id,
            'secretAccessKey' => $storage->secret_access_key,
            'buckets' => $storage->buckets,
        ]);
    }

    public function toPreset(StorageProvider $provider): ProviderPresetData
    {
        return new ProviderPresetData([
            'value' => $provider,
            'label' => $provider->label(),
            'defaultEndpoint' => $provider->defaultEndpoint(),
            'endpointRequired' => $provider->endpointRequired(),
            'defaultRegion' => $provider->defaultRegion(),
            'defaultPathStyle' => $provider->defaultPathStyle(),
            'capabilities' => StorageCapabilities::values($this->capabilities->defaults($provider)),
        ]);
    }

    /**
     * AKIA…WXYZ: первые и последние 4 символа, если ключ достаточно длинный.
     */
    private function mask(string $key): string
    {
        $length = mb_strlen($key);

        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return mb_substr($key, 0, 4).'…'.mb_substr($key, -4);
    }
}
