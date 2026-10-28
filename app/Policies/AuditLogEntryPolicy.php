<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\User;
use App\Policies\Concerns\ChecksSystemPermissions;

final class AuditLogEntryPolicy
{
    use ChecksSystemPermissions;

    public function viewAny(User $actor): bool
    {
        return $this->hasSystemPermission($actor, SystemPermission::ViewAuditLog);
    }
}
