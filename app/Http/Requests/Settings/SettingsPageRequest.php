<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Abstracts\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Открытие страниц настроек своего профиля: доступно любому авторизованному пользователю.
 */
final class SettingsPageRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
