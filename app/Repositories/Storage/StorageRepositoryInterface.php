<?php

declare(strict_types=1);

namespace App\Repositories\Storage;

use App\Models\Storage;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Storage>
 */
interface StorageRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, Storage>
     */
    public function allOrdered(): Collection;

    /**
     * Хранилища, на которые у пользователя есть хоть какие-то права.
     *
     * @return Collection<int, Storage>
     */
    public function accessibleBy(int $userId): Collection;

    public function nameTaken(string $name, ?int $exceptId = null): bool;
}
