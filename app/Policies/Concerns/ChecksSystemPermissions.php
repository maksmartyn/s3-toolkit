<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\User;

trait ChecksSystemPermissions
{
    /**
     * У суперадмина есть все базовые права, в том числе будущие.
     */
    protected function hasSystemPermission(User $user, SystemPermission ...$anyOf): bool
    {
        if ($user->is_superadmin) {
            return true;
        }

        foreach ($anyOf as $permission) {
            if ($user->permissions->has($permission)) {
                return true;
            }
        }

        return false;
    }
}
