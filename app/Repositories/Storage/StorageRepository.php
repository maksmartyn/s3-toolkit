<?php

declare(strict_types=1);

namespace App\Repositories\Storage;

use App\Models\Storage;
use App\Repositories\Abstracts\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Storage>
 */
final class StorageRepository extends BaseRepository implements StorageRepositoryInterface
{
    protected function model(): string
    {
        return Storage::class;
    }

    public function allOrdered(): Collection
    {
        return $this->query()->orderBy('name')->get();
    }

    public function accessibleBy(int $userId): Collection
    {
        return $this->query()
            ->whereHas('accesses', fn (Builder $q) => $q->where('user_id', $userId)->where('permissions', '>', 0))
            ->orderBy('name')
            ->get();
    }

    public function nameTaken(string $name, ?int $exceptId = null): bool
    {
        return $this->newQuery()
            ->where('name', $name)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->exists();
    }
}
