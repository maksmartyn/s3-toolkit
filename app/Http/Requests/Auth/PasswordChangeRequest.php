<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Concerns\PasswordValidationRules;
use App\Http\Requests\Abstracts\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Обязательная смена временного пароля сразу после входа: текущий пароль пользователь только что ввёл.
 */
final class PasswordChangeRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->isMethod('GET') ? [] : ['password' => $this->passwordRules()];
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
