<?php

use App\Models\User;

it('signs out a user on the first request after blocking', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('signs out other sessions after the password is changed elsewhere', function () {
    $user = User::factory()->create();

    // Настоящий вход, а не actingAs: guard должен читать пользователя из БД на каждом запросе
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->get(route('dashboard'))->assertOk();
    app('auth')->forgetGuards();

    // Пароль сменили в другой сессии или сбросил администратор
    User::query()->whereKey($user->id)->update(['password' => bcrypt('another-password')]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});
