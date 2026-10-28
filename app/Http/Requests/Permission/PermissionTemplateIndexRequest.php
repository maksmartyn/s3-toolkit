<?php

declare(strict_types=1);

namespace App\Http\Requests\Permission;

use App\Http\Requests\Abstracts\FormRequest;
use App\Models\PermissionTemplate;
use Illuminate\Contracts\Validation\ValidationRule;

final class PermissionTemplateIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authUser()->can('viewAny', PermissionTemplate::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
