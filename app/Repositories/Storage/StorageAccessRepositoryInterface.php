<?php

declare(strict_types=1);

namespace App\Repositories\Storage;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Models\StorageAccess;
use Illuminate\Database\Eloquent\Collection;

interface StorageAccessRepositoryInterface
{
    /**
     * Права всех пользователей на хранилище, с пользователями (включая удалённых).
     *
     * @return Collection<int, StorageAccess>
     */
    public function forStorage(int $storageId): Collection;

    public function find(int $storageId, int $userId): ?StorageAccess;

    /**
     * Маски пользователя по хранилищам: [storage_id => mask].
     *
     * @return array<int, int>
     */
    public function masksForUser(int $userId): array;

    public function userHasAnyAccess(int $userId): bool;

    /**
     * @param  PermissionMask<StoragePermission>  $permissions
     */
    public function put(int $storageId, int $userId, PermissionMask $permissions, ?int $grantedBy): void;

    public function remove(int $storageId, int $userId): void;
}
