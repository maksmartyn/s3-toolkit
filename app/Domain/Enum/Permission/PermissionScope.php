<?php

declare(strict_types=1);

namespace App\Domain\Enum\Permission;

use App\Domain\Enum\Permission\Contracts\BitPermission;

/**
 * Вид прав: базовые (на систему) или права хранилища.
 */
enum PermissionScope: string
{
    case System = 'system';
    case Storage = 'storage';

    /**
     * @return class-string<BitPermission>
     */
    public function enum(): string
    {
        return match ($this) {
            self::System => SystemPermission::class,
            self::Storage => StoragePermission::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::System => __('System permissions'),
            self::Storage => __('Storage permissions'),
        };
    }
}
