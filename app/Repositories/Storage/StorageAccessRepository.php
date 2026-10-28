<?php

declare(strict_types=1);

namespace App\Repositories\Storage;

use App\Domain\ValueObject\Permission\PermissionMask;
use App\Models\StorageAccess;
use Illuminate\Database\Eloquent\Collection;

final class StorageAccessRepository implements StorageAccessRepositoryInterface
{
    public function forStorage(int $storageId): Collection
    {
        return StorageAccess::query()
            ->with('user')
            ->where('storage_id', $storageId)
            ->where('permissions', '>', 0)
            ->get()
            ->sortBy(fn (StorageAccess $access) => mb_strtolower($access->user->name))
            ->values();
    }

    public function find(int $storageId, int $userId): ?StorageAccess
    {
        return StorageAccess::query()->where('storage_id', $storageId)->where('user_id', $userId)->first();
    }

    public function masksForUser(int $userId): array
    {
        /** @var array<int, int> */
        return StorageAccess::query()
            ->toBase()
            ->where('user_id', $userId)
            ->pluck('permissions', 'storage_id')
            ->map(fn (mixed $mask): int => (int) $mask)
            ->all();
    }

    public function userHasAnyAccess(int $userId): bool
    {
        return StorageAccess::query()->where('user_id', $userId)->where('permissions', '>', 0)->exists();
    }

    public function put(int $storageId, int $userId, PermissionMask $permissions, ?int $grantedBy): void
    {
        $now = now();

        StorageAccess::query()->upsert(
            [[
                'storage_id' => $storageId,
                'user_id' => $userId,
                'permissions' => $permissions->value,
                'granted_by' => $grantedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['storage_id', 'user_id'],
            ['permissions', 'granted_by', 'updated_at'],
        );
    }

    public function remove(int $storageId, int $userId): void
    {
        StorageAccess::query()->where('storage_id', $storageId)->where('user_id', $userId)->delete();
    }
}
