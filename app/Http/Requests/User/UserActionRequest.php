<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Http\Requests\Abstracts\FormRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Действие над пользователем без входных данных. Наследник задаёт способность из UserPolicy.
 */
abstract class UserActionRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        return $this->authUser()->can($this->ability(), $this->target());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    public function target(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
