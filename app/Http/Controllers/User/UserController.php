<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Domain\DTO\User\TemporaryPasswordData;
use App\Domain\Enum\User\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserBlockRequest;
use App\Http\Requests\User\UserCreateRequest;
use App\Http\Requests\User\UserDeleteRequest;
use App\Http\Requests\User\UserEditRequest;
use App\Http\Requests\User\UserIndexRequest;
use App\Http\Requests\User\UserResetPasswordRequest;
use App\Http\Requests\User\UserRestoreRequest;
use App\Http\Requests\User\UserStoreRequest;
use App\Http\Requests\User\UserUnblockRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(UserIndexRequest $request): Response
    {
        $filter = $request->toData();

        return Inertia::render('users/Index', [
            'users' => $this->users->paginate($filter),
            'filters' => ['search' => $filter->search, 'status' => $filter->status?->value],
            'statuses' => array_map(
                fn (UserStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                UserStatus::cases(),
            ),
        ]);
    }

    public function create(UserCreateRequest $request): Response
    {
        return Inertia::render('users/Create', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(UserStoreRequest $request): RedirectResponse
    {
        $result = $this->users->create($request->toData());

        $this->flashTemporaryPassword($result);

        return to_route('users.edit', $result->user->id);
    }

    public function edit(UserEditRequest $request, User $user): Response
    {
        $actor = $request->authUser();

        return Inertia::render('users/Edit', [
            'user' => $this->users->get($user->id),
            'can' => [
                'update' => $actor->can('update', $user),
                'block' => $actor->can('block', $user),
                'unblock' => $actor->can('unblock', $user),
                'delete' => $actor->can('delete', $user),
                'restore' => $actor->can('restore', $user),
                'resetPassword' => $actor->can('resetPassword', $user),
            ],
        ]);
    }

    public function update(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user->id, $request->toData());

        return $this->success(__('User updated.'));
    }

    public function block(UserBlockRequest $request, User $user): RedirectResponse
    {
        $this->users->block($user->id);

        return $this->success(__('User blocked.'));
    }

    public function unblock(UserUnblockRequest $request, User $user): RedirectResponse
    {
        $this->users->unblock($user->id);

        return $this->success(__('User unblocked.'));
    }

    public function destroy(UserDeleteRequest $request, User $user): RedirectResponse
    {
        $this->users->delete($user->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('users.index');
    }

    public function restore(UserRestoreRequest $request, User $user): RedirectResponse
    {
        $this->users->restore($user->id);

        return $this->success(__('User restored.'));
    }

    public function resetPassword(UserResetPasswordRequest $request, User $user): RedirectResponse
    {
        $this->flashTemporaryPassword($this->users->resetPassword($user->id));

        return back();
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Пароль уходит во flash только на следующий запрос и показывается один раз.
     */
    private function flashTemporaryPassword(TemporaryPasswordData $result): void
    {
        Inertia::flash('temporaryPassword', [
            'email' => $result->user->email,
            'password' => $result->password,
        ]);
    }
}
