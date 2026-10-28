<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Domain\Enum\Storage\StorageCapability;
use App\Domain\Enum\Storage\StorageProvider;
use App\Models\Storage;

/**
 * Итоговые возможности хранилища: таблица по умолчанию для провайдера плюс переопределения хранилища.
 */
final class StorageCapabilities
{
    /**
     * @return list<StorageCapability>
     */
    public function defaults(StorageProvider $provider): array
    {
        return array_values(array_filter(
            StorageCapability::cases(),
            fn (StorageCapability $capability) => $capability->supportedBy($provider),
        ));
    }

    /**
     * @return list<StorageCapability>
     */
    public function enabled(Storage $storage): array
    {
        $overrides = $storage->capabilities ?? [];

        return array_values(array_filter(
            StorageCapability::cases(),
            fn (StorageCapability $capability) => (bool) ($overrides[$capability->value] ?? $capability->supportedBy($storage->provider)),
        ));
    }

    public function has(Storage $storage, StorageCapability $capability): bool
    {
        return in_array($capability, $this->enabled($storage), true);
    }

    /**
     * Сохраняем только отличия от таблицы по умолчанию, чтобы её исправления в коде применялись к хранилищам.
     *
     * @param  list<StorageCapability>  $enabled
     * @return array<string, bool>|null
     */
    public function overrides(StorageProvider $provider, array $enabled): ?array
    {
        $overrides = [];

        foreach (StorageCapability::cases() as $capability) {
            $isEnabled = in_array($capability, $enabled, true);

            if ($isEnabled !== $capability->supportedBy($provider)) {
                $overrides[$capability->value] = $isEnabled;
            }
        }

        return $overrides === [] ? null : $overrides;
    }

    /**
     * @param  list<StorageCapability>  $capabilities
     * @return list<string>
     */
    public static function values(array $capabilities): array
    {
        return array_map(fn (StorageCapability $capability) => $capability->value, $capabilities);
    }
}
