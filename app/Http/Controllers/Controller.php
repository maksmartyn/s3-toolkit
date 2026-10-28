<?php

namespace App\Http\Controllers;

use App\Http\Requests\Abstracts\FormRequest;

abstract class Controller
{
    /**
     * После смены пароля перечитывает пользователя в guard: AuthenticateSession сохраняет в сессию
     * хеш пароля этого экземпляра, и со старым хешем следующий запрос завершил бы текущую сессию.
     */
    protected function refreshAuthenticatedUser(FormRequest $request): void
    {
        $request->authUser()->refresh();
    }
}
