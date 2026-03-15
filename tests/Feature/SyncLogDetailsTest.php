<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncLogDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_log_details_endpoint_returns_structured_data()
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);

        $syncLog = SyncLog::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'completed',
            'total_products' => 100,
            'processed_products' => 95,
            'failed_products' => 5,
            'indexed_products' => 90,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/sync-logs/{$syncLog->id}/details");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'tenant_id',
                    'platform_type',
                    'status',
                    'error_message',
                    'metadata',
                    'error_details',
                    'products_summary',
                    'started_at',
                    'completed_at',
                    'duration_seconds',
                ]
            ]);
    }

    public function test_sync_log_details_includes_error_details_when_present()
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);

        $syncLog = SyncLog::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'failed',
            'error_message' => 'API rate limit exceeded',
            'metadata' => [
                'error_details' => [
                    'error_code' => 'RATE_LIMIT_EXCEEDED',
                    'error_message' => '429 Too Many Requests',
                    'raw_response' => json_encode(['error' => 'Rate limit exceeded']),
                    'timestamp' => '2026-03-15T07:00:00Z',
                ],
            ],
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/sync-logs/{$syncLog->id}/details");

        $response->assertStatus(200)
            ->assertJsonPath('data.error_details.error_code', 'RATE_LIMIT_EXCEEDED')
            ->assertJsonPath('data.error_details.error_message', '429 Too Many Requests')
            ->assertJsonPath('data.error_details.raw_response', json_encode(['error' => 'Rate limit exceeded']));
    }

    public function test_sync_log_details_includes_tenant_information()
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create([
            'name' => 'Test Store',
            'platform_type' => 'shopify',
        ]);
        $user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);

        $syncLog = SyncLog::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/sync-logs/{$syncLog->id}/details");

        $response->assertStatus(200)
            ->assertJsonPath('data.tenant.id', $tenant->id)
            ->assertJsonPath('data.tenant.name', 'Test Store')
            ->assertJsonPath('data.tenant.platform_type', 'shopify');
    }

    public function test_sync_log_details_returns_404_for_non_existent_log()
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);

        $fakeUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($user)
            ->getJson("/api/v1/sync-logs/{$fakeUuid}/details");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Sync log not found');
    }

    public function test_sync_log_details_validates_tenant_access()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $user1->tenants()->attach($tenant1->id, ['role' => 'admin', 'joined_at' => now()]);
        $user2->tenants()->attach($tenant2->id, ['role' => 'admin', 'joined_at' => now()]);

        $syncLog = SyncLog::factory()->create([
            'tenant_id' => $tenant1->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user2)
            ->getJson("/api/v1/sync-logs/{$syncLog->id}/details");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Sync log not found');
    }
}
