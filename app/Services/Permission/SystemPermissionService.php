<?php

declare(strict_types=1);

namespace App\Services\Permission;

use App\Domain\DTO\Audit\AuditRecordData;
use App\Domain\DTO\User\UserData;
use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Exceptions\DomainException;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Audit\AuditDetails;
use App\Services\Audit\AuditService;
use App\Services\User\UserMapper;
use Illuminate\Support\Facades\DB;

/**
 * Выдача базовых прав. Правило: выдавать можно любые права, но не себе.
 */
final readonly class SystemPermissionService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UserMapper $mapper,
        private AuditService $audit,
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

        return DB::transaction(function () use ($targetId, $permissions): UserData {
            $target = $this->users->findOrFail($targetId);

            if ($target->is_superadmin) {
                throw new DomainException(__('A superadmin already has all permissions.'));
            }

            $before = $target->permissions;
            $user = $this->users->update($targetId, ['permissions' => $permissions]);

            if (! $before->equals($permissions)) {
                $this->audit->record(new AuditRecordData([
                    'action' => AuditAction::UserPermissionsChanged,
                    'subjectId' => $user->id,
                    'subjectLabel' => $user->name,
                    'details' => AuditDetails::permissions(PermissionScope::System, $before, $permissions),
                ]));
            }

            return $this->mapper->toData($user);
        });
    }
}
