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

class SyncStatusEndpointsTest extends TestCase
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

    public function test_get_sync_status_returns_sync_log_details()
    {
        $syncLog = SyncLog::factory()->for($this->tenant)->create([
            'status' => SyncStatus::COMPLETED,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'total_products' => 100,
            'processed_products' => 95,
            'failed_products' => 5,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/sync/status/{$syncLog->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'tenant_id',
                    'platform_type',
                    'status',
                    'started_at',
                    'completed_at',
                    'total_products',
                    'processed_products',
                    'failed_products',
                    'error_message',
                    'duration',
                    'progress_percentage',
                ],
            ])
            ->assertJsonPath('data.id', $syncLog->id)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total_products', 100);
    }

    public function test_endpoint_requires_authentication()
    {
        $syncLog = SyncLog::factory()->for($this->tenant)->create();

        $response = $this->getJson("/api/v1/sync/status/{$syncLog->id}");

        $response->assertStatus(401);
    }

    public function test_endpoint_returns_404_if_sync_log_not_found()
    {
        $nonExistentId = (string) \Illuminate\Support\Str::uuid();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/sync/status/{$nonExistentId}");

        $response->assertStatus(404);
    }

    public function test_endpoint_returns_404_if_sync_log_belongs_to_different_tenant()
    {
        // Create another user and tenant
        $otherUser = User::factory()->create();
        $otherTenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIFY,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, [
            'role' => 'admin',
            'joined_at' => now(),
        ]);

        // Create a sync log for the other tenant
        $syncLog = SyncLog::factory()->for($otherTenant)->create();

        // Try to access it with the first user
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/sync/status/{$syncLog->id}");

        $response->assertStatus(404);
    }

    public function test_response_includes_all_fields()
    {
        $syncLog = SyncLog::factory()->for($this->tenant)->completed()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/sync/status/{$syncLog->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $syncLog->id)
            ->assertJsonPath('data.tenant_id', $this->tenant->id)
            ->assertJsonPath('data.platform_type', $syncLog->platform_type->value)
            ->assertJsonPath('data.status', $syncLog->status->value)
            ->assertJsonPath('data.total_products', $syncLog->total_products)
            ->assertJsonPath('data.processed_products', $syncLog->processed_products)
            ->assertJsonPath('data.failed_products', $syncLog->failed_products);
    }

    public function test_response_includes_derived_fields()
    {
        $syncLog = SyncLog::factory()->for($this->tenant)->create([
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'total_products' => 1000,
            'processed_products' => 750,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/sync/status/{$syncLog->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.duration', 300) // 5 minutes = 300 seconds
            ->assertJsonPath('data.progress_percentage', 75); // JSON converts 75.0 to 75
    }

    public function test_response_includes_error_message_for_failed_syncs()
    {
        $syncLog = SyncLog::factory()->for($this->tenant)->create([
            'status' => SyncStatus::FAILED,
            'error_message' => 'API rate limit exceeded',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/sync/status/{$syncLog->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.error_message', 'API rate limit exceeded');
    }
}
