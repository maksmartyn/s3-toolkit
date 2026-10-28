<?php

declare(strict_types=1);

namespace App\Http\Requests\Permission;

use App\Http\Requests\Abstracts\FormRequest;
use App\Models\PermissionTemplate;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Действие над существующим шаблоном. Наследник задаёт способность из PermissionTemplatePolicy.
 */
abstract class PermissionTemplateActionRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        return $this->authUser()->can($this->ability(), $this->template());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function template(): PermissionTemplate
    {
        /** @var PermissionTemplate */
        return $this->route('template');
    }
}
