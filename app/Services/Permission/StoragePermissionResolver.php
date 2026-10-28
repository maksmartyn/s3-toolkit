<?php

declare(strict_types=1);

namespace App\Services\Permission;

use App\Domain\Enum\Permission\StoragePermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Models\User;
use App\Repositories\Storage\StorageAccessRepositoryInterface;

/**
 * Итоговые права пользователя на хранилище. У суперадмина — все, включая будущие.
 */
final readonly class StoragePermissionResolver
{
    public function __construct(private StorageAccessRepositoryInterface $accesses) {}

    /**
     * @return PermissionMask<StoragePermission>
     */
    public function mask(User $user, int $storageId): PermissionMask
    {
        if ($user->is_superadmin) {
            return PermissionMask::all(StoragePermission::class);
        }

        return $this->accesses->find($storageId, $user->id)->permissions ?? PermissionMask::none(StoragePermission::class);
    }

    /**
     * Маски пользователя по всем хранилищам одним запросом: [storage_id => mask].
     *
     * @return array<int, PermissionMask<StoragePermission>>
     */
    public function masks(User $user): array
    {
        return array_map(
            fn (int $value) => PermissionMask::fromInt(StoragePermission::class, $value),
            $this->accesses->masksForUser($user->id),
        );
    }
}
