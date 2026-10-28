<?php

namespace Database\Factories;

use App\Domain\Enum\Permission\SystemPermission;
use App\Domain\ValueObject\Permission\PermissionMask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'password_change_required' => false,
            'is_superadmin' => false,
            'is_active' => true,
            'permissions' => 0,
            'remember_token' => Str::random(10),
        ];
    }

    public function superadmin(): static
    {
        return $this->state(fn () => ['is_superadmin' => true]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['password_change_required' => true]);
    }

    public function withPermissions(SystemPermission ...$permissions): static
    {
        return $this->state(fn () => [
            'permissions' => PermissionMask::of(SystemPermission::class, $permissions),
        ]);
    }
}
