<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\User;
use App\Policies\Concerns\ChecksSystemPermissions;

/**
 * Кто и с кем может работать в разделе пользователей.
 *
 * Инварианты, не зависящие от того, кто действует (последний суперадмин, уникальность email), проверяет UserService.
 */
final class UserPolicy
{
    use ChecksSystemPermissions;

    /**
     * Список пользователей нужен и тем, кто только выдаёт права: иначе некому их выдать.
     */
    public function viewAny(User $actor): bool
    {
        return $this->hasSystemPermission(
            $actor,
            SystemPermission::ManageUsers,
            SystemPermission::GrantSystemPermissions,
            SystemPermission::GrantStoragePermissions,
        );
    }

    /**
     * Карточка пользователя, в том числе удалённого (чтобы его можно было восстановить).
     */
    public function view(User $actor, User $target): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->canManageUsers($actor);
    }

    /**
     * Себя меняют в профиле: там смена email требует пароль.
     */
    public function update(User $actor, User $target): bool
    {
        return $this->canManageOther($actor, $target) && ! $target->trashed();
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

    /**
     * Базовые права: любые, но не себе. Суперадмину права не выдаются — у него есть все.
     */
    public function updatePermissions(User $actor, User $target): bool
    {
        return $this->hasSystemPermission($actor, SystemPermission::GrantSystemPermissions)
            && $actor->id !== $target->id
            && ! $target->is_superadmin
            && ! $target->trashed();
    }

    private function canManageUsers(User $actor): bool
    {
        return $this->hasSystemPermission($actor, SystemPermission::ManageUsers);
    }

    /**
     * Суперадминов может менять только суперадмин. Пользователя с базовыми правами, которых нет у действующего,
     * менять нельзя: сброс пароля или смена email дали бы чужие права.
     */
    private function canManage(User $actor, User $target): bool
    {
        if ($actor->is_superadmin) {
            return $this->canManageUsers($actor);
        }

        return $this->canManageUsers($actor)
            && ! $target->is_superadmin
            && $target->permissions->isSubsetOf($actor->permissions);
    }

    /**
     * Себя нельзя заблокировать, удалить или сбросить себе пароль через раздел пользователей.
     */
    private function canManageOther(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $this->canManage($actor, $target);
    }
}
