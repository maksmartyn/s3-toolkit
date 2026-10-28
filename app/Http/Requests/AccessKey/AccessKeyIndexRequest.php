<?php

declare(strict_types=1);

namespace App\Http\Requests\AccessKey;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Страницу видят и те, кто управляет ключами, и те, кто настраивает учётные данные управления.
 */
final class AccessKeyIndexRequest extends AccessKeyRequest
{
    public function authorize(): bool
    {
        return parent::authorize() || $this->authUser()->can('update', $this->storage());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['user' => ['nullable', 'string', 'max:255']];
    }
}
