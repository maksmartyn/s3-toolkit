<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordChangeRequest;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Обязательная смена временного пароля.
 */
final class PasswordChangeController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function edit(PasswordChangeRequest $request): Response|RedirectResponse
    {
        if (! $request->authUser()->password_change_required) {
            return to_route('dashboard');
        }

        return Inertia::render('auth/ChangePassword', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(PasswordChangeRequest $request): RedirectResponse
    {
        $this->users->changePassword($request->authUser()->id, $request->password());
        $this->refreshAuthenticatedUser($request);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('dashboard');
    }
}
