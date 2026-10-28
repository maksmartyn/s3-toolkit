<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Domain\DTO\User\UserFilterData;
use App\Domain\Enum\User\UserStatus;
use App\Models\User;
use App\Repositories\Abstracts\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * @extends BaseRepository<User>
 */
final class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function model(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', Str::lower($email))->first();
    }

    public function findWithTrashedOrFail(int $id): User
    {
        return $this->query()->withTrashed()->findOrFail($id);
    }

    public function paginateFiltered(UserFilterData $filter): LengthAwarePaginator
    {
        $query = $this->query();

        match ($filter->status) {
            UserStatus::Active => $query->where('is_active', true),
            UserStatus::Blocked => $query->where('is_active', false),
            UserStatus::Deleted => $query->onlyTrashed(),
            null => $query,
        };

        if ($filter->search !== null && $filter->search !== '') {
            $search = '%'.addcslashes($filter->search, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $search)->orWhere('email', 'like', $search));
        }

        return $query
            ->orderByDesc('is_superadmin')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($filter->perPage)
            ->withQueryString();
    }

    public function countActiveSuperadmins(?int $exceptId = null): int
    {
        return $this->newQuery()
            ->where('is_superadmin', true)
            ->where('is_active', true)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->count();
    }

    public function grantCandidates(int $exceptId): Collection
    {
        return $this->query()
            ->where('is_superadmin', false)
            ->whereKeyNot($exceptId)
            ->orderBy('name')
            ->get();
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return $this->newQuery()
            ->where('email', Str::lower($email))
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    public function createSuperadmin(string $name, string $email, string $password, bool $passwordChangeRequired): User
    {
        $user = $this->newModel();
        $user->forceFill([
            'name' => $name,
            'email' => Str::lower($email),
            'password' => $password,
            'password_change_required' => $passwordChangeRequired,
            'is_superadmin' => true,
            'is_active' => true,
        ])->save();

        return $user->refresh();
    }

    public function setActive(int $id, bool $active): User
    {
        return $this->update($id, ['is_active' => $active]);
    }

    public function setPassword(int $id, string $password, bool $changeRequired): User
    {
        return $this->update($id, ['password' => $password, 'password_change_required' => $changeRequired]);
    }

    public function touchLastLogin(int $id): void
    {
        $this->newQuery()->whereKey($id)->update(['last_login_at' => now()]);
    }

    public function restore(int $id): User
    {
        $user = $this->newQuery()->onlyTrashed()->findOrFail($id);
        $user->restore();

        return $this->loadRelations($user);
    }

    public function rotateRememberToken(int $id): void
    {
        $this->newQuery()->withTrashed()->whereKey($id)->update(['remember_token' => Str::random(60)]);
    }
}
