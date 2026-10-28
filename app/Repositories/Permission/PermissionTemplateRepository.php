<?php

declare(strict_types=1);

namespace App\Repositories\Permission;

use App\Domain\Enum\Permission\PermissionScope;
use App\Models\PermissionTemplate;
use App\Repositories\Abstracts\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<PermissionTemplate>
 */
final class PermissionTemplateRepository extends BaseRepository implements PermissionTemplateRepositoryInterface
{
    protected function model(): string
    {
        return PermissionTemplate::class;
    }

    public function allOrdered(?PermissionScope $scope = null): Collection
    {
        return $this->query()
            ->when($scope !== null, fn (Builder $q) => $q->where('scope', $scope))
            ->orderBy('scope')
            ->orderBy('name')
            ->get();
    }

    public function nameTaken(PermissionScope $scope, string $name, ?int $exceptId = null): bool
    {
        return $this->newQuery()
            ->where('scope', $scope)
            ->where('name', $name)
            ->when($exceptId !== null, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->exists();
    }
}
