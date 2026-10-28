<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the login screen without password reset link', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login')->missing('canResetPassword'));
});

it('logs in with valid credentials and records last login', function () {
    $user = User::factory()->create(['email' => 'ann@example.com']);

    $this->post(route('login.store'), ['email' => 'ANN@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
});

it('rejects invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects blocked users with an explicit message', function () {
    $user = User::factory()->blocked()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('Your account is blocked.')]);

    $this->assertGuest();
});

it('rejects deleted users like unknown ones', function () {
    $user = User::factory()->create();
    $user->delete();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rate limits login attempts', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [strtolower($user->email), '127.0.0.1'])), amount: 5);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertTooManyRequests();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

it('redirects guests to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('shares the current user as camelCase DTO', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.id', $user->id)
            ->where('auth.user.isSuperadmin', false)
            ->where('auth.user.status', 'active')
            ->missing('auth.user.password')
            ->where('auth.can.viewUsers', false));
});
