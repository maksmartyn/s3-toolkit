<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Domain\DTO\User\UserData;
use App\Domain\Enum\User\UserStatus;
use App\Models\User;

final class UserMapper
{
    public function toData(User $user): UserData
    {
        return new UserData([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'isSuperadmin' => $user->is_superadmin,
            'isActive' => $user->is_active,
            'passwordChangeRequired' => $user->password_change_required,
            'status' => $this->status($user),
            'permissions' => $user->permissions->permissions(),
            'lastLoginAt' => $user->last_login_at?->toImmutable(),
            'createdAt' => $user->created_at?->toImmutable(),
            'deletedAt' => $user->deleted_at?->toImmutable(),
        ]);
    }

    private function status(User $user): UserStatus
    {
        return match (true) {
            $user->trashed() => UserStatus::Deleted,
            ! $user->is_active => UserStatus::Blocked,
            default => UserStatus::Active,
        };
    }
}
