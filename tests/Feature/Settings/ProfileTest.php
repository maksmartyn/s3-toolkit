<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the profile page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/Profile'));
});

it('updates the name without a password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'New Name', 'email' => strtoupper($user->email)])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe('New Name');
});

it('requires the current password to change the email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'changed@example.com'])
        ->assertSessionHasErrors('current_password');

    $this->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'changed@example.com',
        'current_password' => 'wrong',
    ])->assertSessionHasErrors('current_password');

    $this->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => 'changed@example.com',
        'current_password' => 'password',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('changed@example.com');
});

it('soft deletes the own account and logs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect($user->refresh()->trashed())->toBeTrue();
});

it('requires the password to delete the own account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    expect($user->refresh()->trashed())->toBeFalse();
});

it('does not let the last active superadmin delete the own account', function () {
    $admin = superadmin();
    User::factory()->superadmin()->blocked()->create();

    $this->actingAs($admin)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertInertiaFlash('toast.type', 'error');

    $this->assertAuthenticatedAs($admin);
    expect($admin->refresh()->trashed())->toBeFalse();
});
