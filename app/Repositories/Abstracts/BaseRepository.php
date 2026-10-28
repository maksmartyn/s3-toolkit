<?php

declare(strict_types=1);

namespace App\Repositories\Abstracts;

use App\Domain\DTO\Abstracts\DTO;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Базовые CRUD-операции. Сложные выборки описываются отдельными методами наследника через query().
 *
 * Репозиторий не хранит состояние между вызовами: with() возвращает копию,
 * поэтому его безопасно держать в контейнере под Octane.
 *
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /** @var array<int|string, string|\Closure> */
    private array $relations = [];

    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    public function with(string|array $relations): static
    {
        $clone = clone $this;
        $clone->relations = array_merge($this->relations, is_string($relations) ? [$relations] : $relations);

        return $clone;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->query()->get($columns);
    }

    public function find(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->query()->find($id, $columns);
    }

    public function findOrFail(int|string $id, array $columns = ['*']): Model
    {
        return $this->query()->findOrFail($id, $columns);
    }

    public function findOneWhere(array $where, array $columns = ['*']): ?Model
    {
        return $this->query()->where($where)->first($columns);
    }

    public function findWhere(array $where, array $columns = ['*']): Collection
    {
        return $this->query()->where($where)->get($columns);
    }

    public function findWhereIn(string $column, array $values, array $columns = ['*']): Collection
    {
        return $this->query()->whereIn($column, $values)->get($columns);
    }

    public function exists(array $where): bool
    {
        return $this->newQuery()->where($where)->exists();
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage, $columns);
    }

    public function create(DTO|array $attributes): Model
    {
        $model = $this->newModel();
        $model->fill($this->attributes($attributes))->save();

        // Перечитываем, чтобы получить значения колонок по умолчанию из БД
        return $this->loadRelations($model->refresh());
    }

    public function update(int|string $id, DTO|array $attributes): Model
    {
        $model = $this->newQuery()->findOrFail($id);
        $model->fill($this->attributes($attributes))->save();

        return $this->loadRelations($model);
    }

    public function delete(int|string $id): bool
    {
        return (bool) $this->newQuery()->findOrFail($id)->delete();
    }

    /**
     * Запрос с подгрузкой связей, заданных через with().
     *
     * @return Builder<TModel>
     */
    protected function query(): Builder
    {
        return $this->newQuery()->with($this->relations);
    }

    /**
     * @return Builder<TModel>
     */
    protected function newQuery(): Builder
    {
        return $this->newModel()->newQuery();
    }

    /**
     * @return TModel
     */
    protected function newModel(): Model
    {
        $class = $this->model();

        return new $class;
    }

    /**
     * @param  TModel  $model
     * @return TModel
     */
    protected function loadRelations(Model $model): Model
    {
        return $this->relations === [] ? $model : $model->load($this->relations);
    }

    /**
     * Из DTO берутся только инициализированные поля, чтобы не затереть данные null-ами.
     *
     * @param  DTO|array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function attributes(DTO|array $attributes): array
    {
        return $attributes instanceof DTO ? $attributes->initialized() : $attributes;
    }
}
