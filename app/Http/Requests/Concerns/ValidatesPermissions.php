<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Domain\Enum\Permission\Contracts\BitPermission;
use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\ValueObject\Permission\PermissionMask;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Список прав в запросе: permissions[] — значения enum (номера битов). Пустой список — права сняты.
 */
trait ValidatesPermissions
{
    /**
     * @return array<string, array<int, string|Enum>>
     */
    protected function permissionRules(PermissionScope $scope): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'distinct', Rule::enum($scope->enum())],
        ];
    }

    /**
     * @return PermissionMask<BitPermission>
     */
    protected function permissionMask(PermissionScope $scope): PermissionMask
    {
        /** @var list<int|string> $values */
        $values = $this->validated('permissions') ?? [];

        return PermissionMask::fromValues($scope->enum(), array_map(intval(...), $values));
    }
}
