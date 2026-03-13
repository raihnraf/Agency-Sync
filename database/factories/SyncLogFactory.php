<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Enums\PlatformType;
use App\Enums\SyncStatus;
use App\Models\Tenant;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SyncLog>
 */
class SyncLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'platform_type' => fake()->randomElement([PlatformType::SHOPIFY, PlatformType::SHOPIWARE]),
            'status' => SyncStatus::PENDING,
            'started_at' => null,
            'completed_at' => null,
            'total_products' => 0,
            'processed_products' => 0,
            'failed_products' => 0,
            'error_message' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate the sync is running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::RUNNING,
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate the sync is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::COMPLETED,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'total_products' => fake()->numberBetween(10, 100),
            'processed_products' => fake()->numberBetween(10, 100),
            'failed_products' => 0,
        ]);
    }

    /**
     * Indicate the sync is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SyncStatus::FAILED,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'error_message' => fake()->sentence(),
        ]);
    }
}
