<?php

namespace Database\Factories;

use App\Domain\Enum\Storage\StorageProvider;
use App\Models\Storage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Storage>
 */
class StorageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'provider' => StorageProvider::Minio,
            'endpoint' => 'http://minio:9000',
            'region' => 'us-east-1',
            'use_path_style' => true,
            'verify_ssl' => true,
            'access_key_id' => 'AKIA'.fake()->bothify('############'),
            'secret_access_key' => fake()->sha1(),
            'buckets' => null,
        ];
    }
}
