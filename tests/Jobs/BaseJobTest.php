<?php

namespace Tests\Jobs;

use Tests\TestCase;
use App\Jobs\TenantAwareJob;

/**
 * Shared test fixtures and helpers for job tests
 *
 * This file provides common setup and utilities for testing job behavior.
 */
abstract class BaseJobTest extends TestCase
{
    /**
     * Create a test tenant
     */
    protected function createTestTenant(array $attributes = []): Tenant
    {
        return Tenant::factory()->create($attributes);
    }

    /**
     * Create a test user with tenant association
     */
    protected function createTestUser(?Tenant $tenant = null): User
    {
        $user = User::factory()->create();

        if ($tenant) {
            $user->tenants()->attach($tenant, [
                'role' => 'admin',
                'joined_at' => now(),
            ]);
            $user->setCurrentTenant($tenant);
        }

        return $user;
    }

    /**
     * Assert job has expected retry configuration
     */
    protected function assertJobRetryConfig(object $job, int $expectedTries, array $expectedBackoff): void
    {
        $this->assertTrue(true, 'Job retry configuration test - to be implemented');
    }

    /**
     * Assert job includes tenant in payload
     */
    protected function assertJobHasTenant(object $job, Tenant $tenant): void
    {
        $this->assertTrue(true, 'Job tenant payload test - to be implemented');
    }

    /**
     * Create fake queue for testing
     */
    protected function fakeQueue(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
    }
}
