<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a superadmin with a generated temporary password', function () {
    $this->artisan('app:create-superadmin', [
        '--name' => 'Root',
        '--email' => 'Root@Example.com',
        '--generate-password' => true,
    ])->expectsOutputToContain('Временный пароль')->assertSuccessful();

    $user = User::query()->where('email', 'root@example.com')->firstOrFail();

    expect($user->is_superadmin)->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->password_change_required)->toBeTrue();
});

it('creates a superadmin with an entered password without forcing a change', function () {
    $this->artisan('app:create-superadmin', ['--name' => 'Root', '--email' => 'root@example.com'])
        ->expectsQuestion('Пароль (пусто — сгенерировать временный)', 'entered-password')
        ->assertSuccessful();

    $user = User::query()->where('email', 'root@example.com')->firstOrFail();

    expect($user->password_change_required)->toBeFalse()
        ->and(Hash::check('entered-password', $user->password))->toBeTrue();
});

it('fails for an existing email', function () {
    User::factory()->create(['email' => 'root@example.com']);

    $this->artisan('app:create-superadmin', [
        '--name' => 'Root',
        '--email' => 'root@example.com',
        '--generate-password' => true,
    ])->assertFailed();

    expect(User::query()->where('is_superadmin', true)->count())->toBe(0);
});
