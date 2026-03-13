<?php

declare(strict_types=1);

namespace Tests\Feature\Sync;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\SyncLog;
use App\Enums\PlatformType;
use App\Enums\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncHistoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();

        // Create a tenant for the user
        $this->tenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIFY,
        ]);

        // Attach user to tenant
        $this->user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'joined_at' => now(),
        ]);
    }

    public function test_get_sync_history_returns_paginated_logs()
    {
        // Create multiple sync logs
        SyncLog::factory()->for($this->tenant)->count(25)->create();

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/sync/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'tenant_id',
                        'platform_type',
                        'status',
                        'created_at',
                    ],
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page',
                ],
            ])
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(20, 'data'); // Default 20 per page
    }

    public function test_endpoint_requires_authentication()
    {
        $response = $this->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/sync/history');

        $response->assertStatus(401);
    }

    public function test_endpoint_requires_tenant_context()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/sync/history');

        $response->assertStatus(422);
    }

    public function test_endpoint_filters_by_current_tenant()
    {
        // Create another tenant with sync logs
        $otherTenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIFY,
        ]);
        SyncLog::factory()->for($otherTenant)->count(10)->create();

        // Create sync logs for current tenant
        SyncLog::factory()->for($this->tenant)->count(5)->create();

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/sync/history');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 5) // Only current tenant's logs
            ->assertJsonCount(5, 'data');
    }

    public function test_endpoint_supports_status_filter()
    {
        // Create sync logs with different statuses
        SyncLog::factory()->for($this->tenant)->create([
            'status' => SyncStatus::COMPLETED,
        ]);
        SyncLog::factory()->for($this->tenant)->create([
            'status' => SyncStatus::FAILED,
        ]);
        SyncLog::factory()->for($this->tenant)->create([
            'status' => SyncStatus::RUNNING,
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/sync/history?status=completed');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }

    public function test_endpoint_supports_pagination()
    {
        // Create 50 sync logs
        SyncLog::factory()->for($this->tenant)->count(50)->create();

        // Test first page
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/sync/history?page=1&per_page=15');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 50)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 4) // ceil(50/15) = 4
            ->assertJsonCount(15, 'data');

        // Test second page
        $response2 = $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/sync/history?page=2&per_page=15');

        $response2->assertStatus(200)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(15, 'data');
    }

    public function test_returns_logs_ordered_by_created_at_desc()
    {
        // Create sync logs with specific timestamps
        $sync1 = SyncLog::factory()->for($this->tenant)->create([
            'created_at' => now()->subDays(3),
        ]);
        $sync2 = SyncLog::factory()->for($this->tenant)->create([
            'created_at' => now()->subDays(1),
        ]);
        $sync3 = SyncLog::factory()->for($this->tenant)->create([
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/sync/history');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $sync2->id) // Most recent
            ->assertJsonPath('data.1.id', $sync3->id)
            ->assertJsonPath('data.2.id', $sync1->id); // Oldest
    }

    public function test_response_includes_pagination_metadata()
    {
        SyncLog::factory()->for($this->tenant)->count(45)->create();

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->getJson('/api/v1/sync/history?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 45)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 5); // ceil(45/10) = 5
    }
}
