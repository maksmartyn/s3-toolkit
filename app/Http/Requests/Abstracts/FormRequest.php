<?php

declare(strict_types=1);

namespace App\Http\Requests\Abstracts;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Http\FormRequest as BaseFormRequest;

abstract class FormRequest extends BaseFormRequest
{
    /**
     * Авторизованный пользователь для маршрутов под middleware auth.
     *
     * @throws AuthenticationException
     */
    public function authUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
