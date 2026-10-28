<?php

namespace Database\Factories;

use App\Domain\Enum\Permission\PermissionScope;
use App\Models\PermissionTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermissionTemplate>
 */
class PermissionTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'scope' => PermissionScope::System,
            'permissions' => 0,
        ];
    }

    public function storage(): static
    {
        return $this->state(fn () => ['scope' => PermissionScope::Storage]);
    }
}
