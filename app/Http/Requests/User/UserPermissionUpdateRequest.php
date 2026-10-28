<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Domain\Enum\Permission\PermissionScope;
use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Http\Requests\Concerns\ValidatesPermissions;
use Illuminate\Contracts\Validation\ValidationRule;

final class UserPermissionUpdateRequest extends UserActionRequest
{
    use ValidatesPermissions;

    protected function ability(): string
    {
        return 'updatePermissions';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->permissionRules(PermissionScope::System);
    }

    /**
     * @return PermissionMask<SystemPermission>
     */
    public function toMask(): PermissionMask
    {
        /** @var PermissionMask<SystemPermission> */
        return $this->permissionMask(PermissionScope::System);
    }
}
