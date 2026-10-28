<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\PermissionTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksSystemPermissions;

final class PermissionTemplatePolicy
{
    use ChecksSystemPermissions;

    public function viewAny(User $actor): bool
    {
        return $this->canManage($actor);
    }

    public function create(User $actor): bool
    {
        return $this->canManage($actor);
    }

    public function update(User $actor, PermissionTemplate $template): bool
    {
        return $this->canManage($actor);
    }

    public function delete(User $actor, PermissionTemplate $template): bool
    {
        return $this->canManage($actor);
    }

    private function canManage(User $actor): bool
    {
        return $this->hasSystemPermission($actor, SystemPermission::ManagePermissionTemplates);
    }
}
