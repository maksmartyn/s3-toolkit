<?php

declare(strict_types=1);

namespace App\Http\Requests\Permission;

use App\Domain\DTO\Permission\PermissionTemplateSaveData;
use App\Domain\Enum\Permission\PermissionScope;
use App\Http\Requests\Abstracts\FormRequest;
use App\Http\Requests\Concerns\ValidatesPermissions;
use App\Models\PermissionTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class PermissionTemplateStoreRequest extends FormRequest
{
    use ValidatesPermissions;

    public function authorize(): bool
    {
        return $this->authUser()->can('create', PermissionTemplate::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $scope = PermissionScope::tryFrom((string) $this->input('scope'));

        return [
            'scope' => ['required', Rule::enum(PermissionScope::class)],
            'name' => ['required', 'string', 'max:255', Rule::unique(PermissionTemplate::class)->where('scope', $scope?->value)],
            ...($scope === null ? [] : $this->permissionRules($scope)),
        ];
    }

    public function scope(): PermissionScope
    {
        return PermissionScope::from((string) $this->validated('scope'));
    }

    public function toData(): PermissionTemplateSaveData
    {
        return new PermissionTemplateSaveData([
            'name' => (string) $this->validated('name'),
            'permissions' => $this->permissionMask($this->scope()),
        ]);
    }
}
