<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserPermissionUpdateRequest;
use App\Models\User;
use App\Services\Permission\SystemPermissionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class UserPermissionController extends Controller
{
    public function __construct(private readonly SystemPermissionService $permissions) {}

    public function update(UserPermissionUpdateRequest $request, User $user): RedirectResponse
    {
        $this->permissions->set($request->authUser()->id, $user->id, $request->toMask());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permissions updated.')]);

        return back();
    }
}
