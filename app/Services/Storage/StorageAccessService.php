<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Domain\DTO\Audit\AuditRecordData;
use App\Domain\DTO\Storage\StorageAccessData;
use App\Domain\DTO\User\UserData;
use App\Domain\Enum\Audit\AuditAction;
use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Exceptions\DomainException;
use App\Models\StorageAccess;
use App\Models\User;
use App\Repositories\Storage\StorageAccessRepositoryInterface;
use App\Repositories\Storage\StorageRepositoryInterface;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Audit\AuditDetails;
use App\Services\Audit\AuditService;
use App\Services\User\UserMapper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Права пользователей на хранилище. Правило то же, что для базовых прав: любые, но не себе.
 */
final readonly class StorageAccessService
{
    public function __construct(
        private StorageAccessRepositoryInterface $accesses,
        private StorageRepositoryInterface $storages,
        private UserRepositoryInterface $users,
        private UserMapper $userMapper,
        private AuditService $audit,
    ) {}

    /**
     * @return list<StorageAccessData>
     */
    public function list(int $storageId): array
    {
        return array_values($this->accesses->forStorage($storageId)->map(
            fn (StorageAccess $access) => $this->toData($access->user, $access->permissions, $access->updated_at?->toImmutable()),
        )->all());
    }

    /**
     * Кому можно выдать права: неудалённые пользователи, кроме суперадминов (у них и так всё) и самого выдающего.
     *
     * @return list<UserData>
     */
    public function candidates(int $actorId): array
    {
        return array_values($this->users->grantCandidates($actorId)->map($this->userMapper->toData(...))->all());
    }

    /**
     * Заменяет права пользователя на хранилище. Пустой набор — доступ отзывается.
     *
     * @param  PermissionMask<StoragePermission>  $permissions
     */
    public function set(int $actorId, int $storageId, int $userId, PermissionMask $permissions): void
    {
        if ($actorId === $userId) {
            throw new DomainException(__('You cannot change your own permissions.'));
        }

        DB::transaction(function () use ($actorId, $storageId, $userId, $permissions): void {
            $storage = $this->storages->findOrFail($storageId);
            $user = $this->users->findOrFail($userId);

            if ($user->is_superadmin) {
                throw new DomainException(__('A superadmin already has all permissions.'));
            }

            $before = $this->accesses->find($storageId, $userId)->permissions ?? PermissionMask::none(StoragePermission::class);

            if ($before->equals($permissions)) {
                return;
            }

            if ($permissions->isEmpty()) {
                $this->accesses->remove($storageId, $userId);
            } else {
                $this->accesses->put($storageId, $userId, $permissions, $actorId);
            }

            $this->audit->record(new AuditRecordData([
                'action' => AuditAction::StorageAccessChanged,
                'subjectId' => $storage->id,
                'subjectLabel' => $storage->name,
                'details' => [
                    ...AuditDetails::user($user->id, $user->name, $user->email),
                    ...AuditDetails::permissions(PermissionScope::Storage, $before, $permissions),
                ],
            ]));
        });
    }

    /**
     * @param  PermissionMask<StoragePermission>  $permissions
     */
    private function toData(User $user, PermissionMask $permissions, ?CarbonImmutable $updatedAt): StorageAccessData
    {
        return new StorageAccessData([
            'userId' => $user->id,
            'userName' => $user->name,
            'userEmail' => $user->email,
            'userStatus' => $this->userMapper->toData($user)->status,
            'permissions' => $permissions->values(),
            'updatedAt' => $updatedAt,
        ]);
    }
}
