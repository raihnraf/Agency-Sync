<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'platform_type' => fake()->randomElement(['shopify', 'shopware']),
            'platform_url' => fake()->url(),
            'status' => fake()->randomElement(['active', 'pending_setup', 'sync_error', 'suspended']),
            'api_credentials' => ['api_key' => fake()->uuid(), 'api_secret' => fake()->sha256()],
            'settings' => null,
            'last_sync_at' => fake()->optional()->dateTime(),
            'sync_status' => fake()->optional()->word(),
        ];
    }
}
