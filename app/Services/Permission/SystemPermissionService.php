<?php

declare(strict_types=1);

namespace App\Services\Permission;

use App\Domain\DTO\User\UserData;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Exceptions\DomainException;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\User\UserMapper;

/**
 * Выдача базовых прав. Правило: выдавать можно любые права, но не себе.
 */
final readonly class SystemPermissionService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UserMapper $mapper,
    ) {}

    /**
     * Заменяет набор базовых прав пользователя целиком.
     *
     * @param  PermissionMask<SystemPermission>  $permissions
     */
    public function set(int $actorId, int $targetId, PermissionMask $permissions): UserData
    {
        if ($actorId === $targetId) {
            throw new DomainException(__('You cannot change your own permissions.'));
        }

        $target = $this->users->findOrFail($targetId);

        if ($target->is_superadmin) {
            throw new DomainException(__('A superadmin already has all permissions.'));
        }

        return $this->mapper->toData($this->users->update($targetId, ['permissions' => $permissions]));
    }
}
