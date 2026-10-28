<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('forces users with a temporary password to the change page', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->get(route('users.index'))->assertRedirect(route('password.change'));

    $this->get(route('password.change'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ChangePassword'));
});

it('allows logout before changing the password', function () {
    $this->actingAs(User::factory()->mustChangePassword()->create())
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

it('changes the temporary password and keeps the session', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)->get(route('password.change'))->assertOk();

    $this->put(route('password.change.update'), [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->password_change_required)->toBeFalse()
        ->and(Hash::check('new-password-123', $user->password))->toBeTrue();

    // Текущая сессия не должна завершиться из-за AuthenticateSession
    $this->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

it('requires the new password to differ from the temporary one', function () {
    $user = User::factory()->mustChangePassword()->create();

    $this->actingAs($user)
        ->from(route('password.change'))
        ->put(route('password.change.update'), [
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('password.change'))
        ->assertInertiaFlash('toast.type', 'error');

    expect($user->refresh()->password_change_required)->toBeTrue();
});

it('redirects from the change page when no change is required', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('password.change'))
        ->assertRedirect(route('dashboard'));
});
