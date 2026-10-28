<?php

declare(strict_types=1);

namespace App\Services\Permission;

use App\Domain\DTO\Permission\PermissionOptionData;
use App\Domain\Enum\Permission\Contracts\BitPermission;
use App\Domain\Enum\Permission\PermissionScope;

/**
 * Список прав каждого вида с переведёнными названиями для интерфейса.
 */
final class PermissionCatalog
{
    /**
     * @return list<PermissionOptionData>
     */
    public function options(PermissionScope $scope): array
    {
        return array_map(
            fn (BitPermission $permission) => new PermissionOptionData([
                'value' => (int) $permission->value,
                'name' => $permission->name,
                'label' => $permission->label(),
            ]),
            $scope->enum()::cases(),
        );
    }
}
