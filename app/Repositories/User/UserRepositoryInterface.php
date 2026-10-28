<?php

declare(strict_types=1);

namespace App\Repositories\User;

use App\Domain\DTO\User\UserFilterData;
use App\Models\User;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<User>
 */
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Неудалённый пользователь по email (без учёта регистра).
     */
    public function findByEmail(string $email): ?User;

    /**
     * Пользователь, в том числе удалённый.
     *
     * @throws ModelNotFoundException
     */
    public function findWithTrashedOrFail(int $id): User;

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateFiltered(UserFilterData $filter): LengthAwarePaginator;

    public function countActiveSuperadmins(?int $exceptId = null): int;

    /**
     * Неудалённые пользователи без флага суперадмина, кроме указанного.
     *
     * @return Collection<int, User>
     */
    public function grantCandidates(int $exceptId): Collection;

    public function emailTaken(string $email, ?int $exceptId = null): bool;

    public function createSuperadmin(string $name, string $email, string $password, bool $passwordChangeRequired): User;

    public function setActive(int $id, bool $active): User;

    public function setPassword(int $id, string $password, bool $changeRequired): User;

    public function touchLastLogin(int $id): void;

    public function restore(int $id): User;

    /**
     * Сбрасывает remember-токен, чтобы «Запомнить меня» на других устройствах перестало работать.
     */
    public function rotateRememberToken(int $id): void;
}
