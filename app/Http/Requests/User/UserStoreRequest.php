<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Concerns\ProfileValidationRules;
use App\Domain\DTO\User\UserCreateData;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

final class UserStoreRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->authUser()->can('create', User::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            // Пустой пароль — сгенерировать временный
            'password' => ['nullable', 'string', Password::default()],
        ];
    }

    public function toData(): UserCreateData
    {
        return new UserCreateData($this->safe()->only(['name', 'email', 'password']));
    }
}
