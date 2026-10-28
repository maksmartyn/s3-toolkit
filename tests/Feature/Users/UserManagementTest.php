<?php

use App\Domain\Enum\Permission\SystemPermission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

describe('access', function () {
    it('forbids the users section without permission', function () {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create());

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
        $this->post(route('users.store'), ['name' => 'X', 'email' => 'x@example.com'])->assertForbidden();
        $this->get(route('users.edit', $target))->assertForbidden();
        $this->post(route('users.block', $target))->assertForbidden();
    });

    it('allows the users section with ManageUsers permission and for superadmin', function (Closure $actor) {
        $this->actingAs($actor())
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('users/Index')->where('auth.can.viewUsers', true));
    })->with([
        'manager' => fn () => userWith(SystemPermission::ManageUsers),
        'superadmin' => fn () => superadmin(),
    ]);

    it('does not let non-superadmins manage superadmins', function () {
        $admin = superadmin();

        $this->actingAs(userWith(SystemPermission::ManageUsers));

        $this->get(route('users.edit', $admin))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', false)
                ->where('can.block', false)
                ->where('can.delete', false)
                ->where('can.resetPassword', false));
        $this->put(route('users.update', $admin), ['name' => 'X', 'email' => $admin->email])->assertForbidden();
        $this->post(route('users.block', $admin))->assertForbidden();
        $this->delete(route('users.destroy', $admin))->assertForbidden();
        $this->post(route('users.reset-password', $admin))->assertForbidden();
    });

    it('does not let managers block, delete or reset themselves', function () {
        $manager = userWith(SystemPermission::ManageUsers);

        $this->actingAs($manager);

        $this->post(route('users.block', $manager))->assertForbidden();
        $this->delete(route('users.destroy', $manager))->assertForbidden();
        $this->post(route('users.reset-password', $manager))->assertForbidden();

        $this->get(route('users.edit', $manager))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', false)
                ->where('can.block', false)
                ->where('can.delete', false));
    });

    it('does not let managers take over users with permissions they lack', function () {
        $admin = userWith(SystemPermission::ManageUsers, SystemPermission::GrantSystemPermissions);

        $this->actingAs(userWith(SystemPermission::ManageUsers));

        $this->post(route('users.reset-password', $admin))->assertForbidden();
        $this->put(route('users.update', $admin), ['name' => 'x', 'email' => 'mine@example.com'])->assertForbidden();
    });
});

describe('list', function () {
    it('filters by search and status and hides deleted by default', function () {
        $this->actingAs(superadmin());

        User::factory()->create(['name' => 'Alice Active', 'email' => 'alice@example.com']);
        User::factory()->blocked()->create(['name' => 'Bob Blocked']);
        User::factory()->create(['name' => 'Dan Deleted'])->delete();

        $names = fn (array $query) => collect(
            $this->get(route('users.index', $query))->viewData('page')['props']['users']['data'],
        )->pluck('name')->all();

        expect($names([]))->not->toContain('Dan Deleted')->toContain('Alice Active', 'Bob Blocked')
            ->and($names(['status' => 'blocked']))->toBe(['Bob Blocked'])
            ->and($names(['status' => 'deleted']))->toBe(['Dan Deleted'])
            ->and($names(['search' => 'alice@']))->toBe(['Alice Active']);
    });
});

describe('create', function () {
    it('creates a user with a generated temporary password shown once', function () {
        $this->actingAs(userWith(SystemPermission::ManageUsers));

        $response = $this->post(route('users.store'), ['name' => 'New', 'email' => 'New@Example.com']);

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $response->assertRedirect(route('users.edit', $user));
        $flash = session('inertia.flash_data');

        expect($user->password_change_required)->toBeTrue()
            ->and($user->is_superadmin)->toBeFalse()
            ->and($user->permissions->isEmpty())->toBeTrue()
            ->and($flash['temporaryPassword']['email'])->toBe('new@example.com')
            ->and(Hash::check($flash['temporaryPassword']['password'], $user->password))->toBeTrue();
    });

    it('uses a provided temporary password', function () {
        $this->actingAs(superadmin())
            ->post(route('users.store'), ['name' => 'New', 'email' => 'new@example.com', 'password' => 'given-password'])
            ->assertInertiaFlash('temporaryPassword.password', 'given-password');
    });

    it('rejects a duplicate email but allows the email of a deleted user', function () {
        $this->actingAs(superadmin());

        $existing = User::factory()->create();
        $deleted = User::factory()->create();
        $deleted->delete();

        $this->post(route('users.store'), ['name' => 'X', 'email' => strtoupper($existing->email)])
            ->assertSessionHasErrors('email');

        $this->post(route('users.store'), ['name' => 'X', 'email' => $deleted->email])
            ->assertSessionHasNoErrors();

        expect(User::withTrashed()->where('email', $deleted->email)->count())->toBe(2);
    });
});

describe('update and lifecycle', function () {
    beforeEach(function () {
        $this->actingAs(userWith(SystemPermission::ManageUsers));
        $this->target = User::factory()->create();
    });

    it('updates name and email', function () {
        $this->put(route('users.update', $this->target), ['name' => 'Renamed', 'email' => 'renamed@example.com'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.type', 'success');

        expect($this->target->refresh())
            ->name->toBe('Renamed')
            ->email->toBe('renamed@example.com');
    });

    it('blocks and unblocks, rotating the remember token', function () {
        $token = $this->target->remember_token;

        $this->post(route('users.block', $this->target))->assertSessionHasNoErrors();
        $this->target->refresh();
        expect($this->target->is_active)->toBeFalse()->and($this->target->remember_token)->not->toBe($token);

        $this->post(route('users.block', $this->target))->assertForbidden();

        $this->post(route('users.unblock', $this->target));
        expect($this->target->refresh()->is_active)->toBeTrue();
    });

    it('soft deletes and restores', function () {
        $this->delete(route('users.destroy', $this->target))->assertRedirect(route('users.index'));
        expect($this->target->refresh()->trashed())->toBeTrue();

        $this->get(route('users.edit', $this->target))
            ->assertInertia(fn (Assert $page) => $page
                ->where('user.status', 'deleted')
                ->where('can.restore', true)
                ->where('can.update', false));

        $this->post(route('users.restore', $this->target));
        expect($this->target->refresh()->trashed())->toBeFalse();
    });

    it('does not restore a user whose email is taken again', function () {
        $this->target->delete();
        User::factory()->create(['email' => $this->target->email]);

        $this->post(route('users.restore', $this->target))->assertInertiaFlash('toast.type', 'error');

        expect($this->target->refresh()->trashed())->toBeTrue();
    });

    it('resets the password to a new temporary one', function () {
        $this->post(route('users.reset-password', $this->target))
            ->assertInertiaFlash('temporaryPassword.email', $this->target->email);

        $this->target->refresh();
        expect($this->target->password_change_required)->toBeTrue()
            ->and(Hash::check('password', $this->target->password))->toBeFalse();
    });
});

it('lets a superadmin block another superadmin', function () {
    $other = superadmin();

    $this->actingAs(superadmin())->post(route('users.block', $other))->assertSessionHasNoErrors();

    expect($other->refresh()->is_active)->toBeFalse();
});
