<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\Enum\Permission\SystemPermission;
use App\Models\Storage;
use App\Models\User;
use App\Policies\Concerns\ChecksSystemPermissions;
use App\Services\Permission\StoragePermissionResolver;

/**
 * Управление учётными записями хранилищ (базовое право) отделено от работы с хранилищем (права хранилища):
 * тот, кто добавил хранилище, не получает прав на работу с ним автоматически.
 */
final class StoragePolicy
{
    use ChecksSystemPermissions;

    public function __construct(private readonly StoragePermissionResolver $permissions) {}

    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Storage $storage): bool
    {
        return $this->manages($actor) || $this->grants($actor) || ! $this->permissions->mask($actor, $storage->id)->isEmpty();
    }

    public function create(User $actor): bool
    {
        return $this->manages($actor);
    }

    /**
     * Параметры подключения (endpoint, регион, скрытый access key) видят только управляющие хранилищами.
     */
    public function viewConnection(User $actor, Storage $storage): bool
    {
        return $this->manages($actor);
    }

    public function update(User $actor, Storage $storage): bool
    {
        return $this->manages($actor);
    }

    public function delete(User $actor, Storage $storage): bool
    {
        return $this->manages($actor);
    }

    public function manageAccess(User $actor, Storage $storage): bool
    {
        return $this->grants($actor);
    }

    /**
     * Работа с содержимым хранилища: проверка конкретного права хранилища.
     */
    public function use(User $actor, Storage $storage, StoragePermission $permission): bool
    {
        return $this->permissions->mask($actor, $storage->id)->has($permission);
    }

    private function manages(User $actor): bool
    {
        return $this->hasSystemPermission($actor, SystemPermission::ManageStorages);
    }

    private function grants(User $actor): bool
    {
        return $this->hasSystemPermission($actor, SystemPermission::GrantStoragePermissions);
    }
}
