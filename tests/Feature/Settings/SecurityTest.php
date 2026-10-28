<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the security page without password confirmation', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/Security')->has('passwordRules'));
});

it('changes the password and keeps the current session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('security.edit'))->assertOk();

    $this->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password-123', $user->refresh()->password))->toBeTrue();

    $this->get(route('security.edit'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

it('requires the correct current password', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('user-password.update'), [
            'current_password' => 'wrong',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors('current_password');
});
