<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobStatus>
 */
class JobStatusFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => \App\Models\Tenant::factory(),
            'job_id' => fake()->uuid(),
            'job_type' => fake()->word(),
            'status' => fake()->randomElement(['pending', 'running', 'completed', 'failed']),
            'payload' => ['test' => 'data'],
            'error_message' => fake()->optional()->sentence(),
            'started_at' => fake()->optional()->dateTime(),
            'completed_at' => fake()->optional()->dateTime(),
        ];
    }
}
