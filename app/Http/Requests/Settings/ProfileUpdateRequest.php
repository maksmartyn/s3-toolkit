<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Domain\DTO\User\UserUpdateData;
use App\Http\Requests\Abstracts\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ProfileUpdateRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->authUser()->id),
            // Смена email подтверждается текущим паролем
            'current_password' => [
                Rule::requiredIf(fn (): bool => $this->emailChanged()),
                'nullable',
                'string',
                'current_password',
            ],
        ];
    }

    public function toData(): UserUpdateData
    {
        return new UserUpdateData($this->safe()->only(['name', 'email']));
    }

    private function emailChanged(): bool
    {
        return Str::lower((string) $this->input('email')) !== Str::lower($this->authUser()->email);
    }
}
