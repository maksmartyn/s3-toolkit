<?php

declare(strict_types=1);

namespace App\Repositories\Permission;

use App\Domain\Enum\Permission\PermissionScope;
use App\Models\PermissionTemplate;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<PermissionTemplate>
 */
interface PermissionTemplateRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, PermissionTemplate>
     */
    public function allOrdered(?PermissionScope $scope = null): Collection;

    public function nameTaken(PermissionScope $scope, string $name, ?int $exceptId = null): bool;
}
