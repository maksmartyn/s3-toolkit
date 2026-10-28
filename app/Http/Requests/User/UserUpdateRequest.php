<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Concerns\ProfileValidationRules;
use App\Domain\DTO\User\UserUpdateData;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;

final class UserUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->authUser()->can('update', $this->target());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->target()->id);
    }

    public function toData(): UserUpdateData
    {
        return new UserUpdateData($this->safe()->only(['name', 'email']));
    }

    public function target(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
