<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DTO\Abstracts\DTO;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /**
     * Копия репозитория, которая подгружает указанные связи.
     *
     * @param  string|list<string>|array<string, \Closure>  $relations
     */
    public function with(string|array $relations): static;

    /**
     * @param  list<string>  $columns
     * @return Collection<int, TModel>
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * @param  list<string>  $columns
     * @return TModel|null
     */
    public function find(int|string $id, array $columns = ['*']): ?Model;

    /**
     * @param  list<string>  $columns
     * @return TModel
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int|string $id, array $columns = ['*']): Model;

    /**
     * @param  array<string, mixed>  $where
     * @param  list<string>  $columns
     * @return TModel|null
     */
    public function findOneWhere(array $where, array $columns = ['*']): ?Model;

    /**
     * @param  array<string, mixed>  $where
     * @param  list<string>  $columns
     * @return Collection<int, TModel>
     */
    public function findWhere(array $where, array $columns = ['*']): Collection;

    /**
     * @param  array<mixed>  $values
     * @param  list<string>  $columns
     * @return Collection<int, TModel>
     */
    public function findWhereIn(string $column, array $values, array $columns = ['*']): Collection;

    /**
     * @param  array<string, mixed>  $where
     */
    public function exists(array $where): bool;

    /**
     * @param  list<string>  $columns
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * @param  DTO|array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(DTO|array $attributes): Model;

    /**
     * @param  DTO|array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(int|string $id, DTO|array $attributes): Model;

    public function delete(int|string $id): bool;
}
