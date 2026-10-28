<?php

declare(strict_types=1);

namespace App\Http\Requests\Permission;

use App\Domain\DTO\Permission\PermissionTemplateSaveData;
use App\Http\Requests\Concerns\ValidatesPermissions;
use App\Models\PermissionTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class PermissionTemplateUpdateRequest extends PermissionTemplateActionRequest
{
    use ValidatesPermissions;

    protected function ability(): string
    {
        return 'update';
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $template = $this->template();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(PermissionTemplate::class)->where('scope', $template->scope->value)->ignore($template->id),
            ],
            ...$this->permissionRules($template->scope),
        ];
    }

    public function toData(): PermissionTemplateSaveData
    {
        return new PermissionTemplateSaveData([
            'name' => (string) $this->validated('name'),
            'permissions' => $this->permissionMask($this->template()->scope),
        ]);
    }
}
