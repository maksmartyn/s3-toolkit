<?php

declare(strict_types=1);

namespace App\Domain\Enum\Permission;

use App\Domain\Enum\Permission\Contracts\BitPermission;

/**
 * Базовые права пользователя. Значение — номер бита в users.permissions.
 *
 * Номера битов фиксированы навсегда: нельзя менять и переиспользовать.
 */
enum SystemPermission: int implements BitPermission
{
    case ManageStorages = 0;
    case GrantSystemPermissions = 1;
    case GrantStoragePermissions = 2;
    case ManageUsers = 3;
    case ManagePermissionTemplates = 4;
    case ViewAuditLog = 5;

    public function bit(): int
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::ManageStorages => __('Manage storage accounts'),
            self::GrantSystemPermissions => __('Grant system permissions'),
            self::GrantStoragePermissions => __('Grant storage permissions'),
            self::ManageUsers => __('Manage users'),
            self::ManagePermissionTemplates => __('Manage permission templates'),
            self::ViewAuditLog => __('View audit log'),
        };
    }
}
