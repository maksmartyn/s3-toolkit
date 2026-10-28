<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Http\Requests\Abstracts\FormRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;

final class UserCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authUser()->can('create', User::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
