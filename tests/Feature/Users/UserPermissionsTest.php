<?php

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lets a granter replace system permissions of another user', function () {
    $target = userWith(SystemPermission::ViewAuditLog);

    $this->actingAs(userWith(SystemPermission::GrantSystemPermissions))
        ->put(route('users.permissions.update', $target), ['permissions' => [0, 3]])
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    expect($target->refresh()->permissions->permissions())
        ->toBe([SystemPermission::ManageStorages, SystemPermission::ManageUsers]);
});

it('allows granting any permission, including ones the granter lacks', function () {
    $target = User::factory()->create();

    $this->actingAs(userWith(SystemPermission::GrantSystemPermissions))
        ->put(route('users.permissions.update', $target), ['permissions' => [SystemPermission::GrantSystemPermissions->value, SystemPermission::ManageUsers->value]])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->permissions->has(SystemPermission::ManageUsers))->toBeTrue();
});

it('clears permissions when the list is empty or missing', function () {
    $target = userWith(SystemPermission::ManageUsers, SystemPermission::ViewAuditLog);

    $this->actingAs(superadmin())
        ->put(route('users.permissions.update', $target), [])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->permissions->isEmpty())->toBeTrue();
});

it('forbids changing own permissions', function () {
    $granter = userWith(SystemPermission::GrantSystemPermissions);

    $this->actingAs($granter)
        ->put(route('users.permissions.update', $granter), ['permissions' => [0, 1, 2, 3, 4, 5]])
        ->assertForbidden();

    expect($granter->refresh()->permissions->permissions())->toBe([SystemPermission::GrantSystemPermissions]);
});

it('forbids changing permissions of a superadmin or a deleted user', function () {
    $this->actingAs(superadmin());

    $this->put(route('users.permissions.update', superadmin()), ['permissions' => [0]])->assertForbidden();

    $deleted = User::factory()->create();
    $deleted->delete();
    $this->put(route('users.permissions.update', $deleted->id), ['permissions' => [0]])->assertNotFound();
});

it('forbids granting without the permission', function () {
    $this->actingAs(userWith(SystemPermission::ManageUsers))
        ->put(route('users.permissions.update', User::factory()->create()), ['permissions' => [0]])
        ->assertForbidden();
});

it('rejects unknown permission values', function () {
    $this->actingAs(superadmin())
        ->put(route('users.permissions.update', User::factory()->create()), ['permissions' => [6]])
        ->assertSessionHasErrors('permissions.0');
});

it('shows grant-only users the user list and cards but no management actions', function () {
    $target = User::factory()->create();

    $this->actingAs(userWith(SystemPermission::GrantSystemPermissions));

    $this->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.create', false));

    $this->get(route('users.edit', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.updatePermissions', true)
            ->where('can.update', false)
            ->where('can.block', false)
            ->has('permissionOptions', count(SystemPermission::cases())));

    $this->post(route('users.store'), ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
    $this->post(route('users.block', $target))->assertForbidden();
});

it('lets users with storage grant permission see the user list', function () {
    $this->actingAs(userWith(SystemPermission::GrantStoragePermissions))
        ->get(route('users.index'))
        ->assertOk();
});
