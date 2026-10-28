<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\User;

/**
 * Кто и с кем может работать в разделе пользователей.
 *
 * Инварианты, не зависящие от того, кто действует (последний суперадмин, уникальность email), проверяет UserService.
 */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->canManageUsers($actor);
    }

    public function create(User $actor): bool
    {
        return $this->canManageUsers($actor);
    }

    /**
     * Карточка пользователя, в том числе удалённого (чтобы его можно было восстановить).
     */
    public function view(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target) && ! $target->trashed();
    }

    public function block(User $actor, User $target): bool
    {
        return $this->canManageOther($actor, $target) && ! $target->trashed() && $target->is_active;
    }

    public function unblock(User $actor, User $target): bool
    {
        return $this->canManageOther($actor, $target) && ! $target->trashed() && ! $target->is_active;
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->canManageOther($actor, $target) && ! $target->trashed();
    }

    public function restore(User $actor, User $target): bool
    {
        return $this->canManageOther($actor, $target) && $target->trashed();
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $this->canManageOther($actor, $target) && ! $target->trashed();
    }

    private function canManageUsers(User $actor): bool
    {
        return $actor->is_superadmin || $actor->permissions->has(SystemPermission::ManageUsers);
    }

    /**
     * Суперадминов может менять только суперадмин.
     */
    private function canManage(User $actor, User $target): bool
    {
        return $this->canManageUsers($actor) && (! $target->is_superadmin || $actor->is_superadmin);
    }

    /**
     * Себя нельзя заблокировать, удалить или сбросить себе пароль через раздел пользователей.
     */
    private function canManageOther(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $this->canManage($actor, $target);
    }
}
