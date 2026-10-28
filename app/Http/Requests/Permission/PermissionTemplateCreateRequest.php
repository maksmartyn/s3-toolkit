<?php

declare(strict_types=1);

namespace App\Http\Requests\Permission;

use App\Domain\Enum\Permission\PermissionScope;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\PermissionTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Форма нового шаблона; вид прав передаётся в ?scope=.
 */
final class PermissionTemplateCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authUser()->can('create', PermissionTemplate::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['scope' => ['nullable', Rule::enum(PermissionScope::class)]];
    }

    public function scope(): PermissionScope
    {
        return PermissionScope::tryFrom((string) $this->validated('scope')) ?? PermissionScope::System;
    }
}
