<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Domain\DTO\User\UserFilterData;
use App\Domain\Enum\User\UserStatus;
use App\Http\Requests\Abstracts\FormRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authUser()->can('viewAny', User::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
        ];
    }

    public function toData(): UserFilterData
    {
        return new UserFilterData([
            'search' => $this->validated('search'),
            'status' => $this->validated('status'),
        ]);
    }
}
