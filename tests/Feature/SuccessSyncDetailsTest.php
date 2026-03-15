<?php

namespace Tests\Feature;

use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuccessSyncDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_success_sync_includes_products_summary()
    {
        $syncLog = SyncLog::factory()->create([
            'status' => 'completed',
            'total_products' => 100,
            'processed_products' => 95,
            'failed_products' => 5,
            'indexed_products' => 90,
        ]);

        $response = $this->getJson("/api/v1/sync-logs/{$syncLog->id}/details");

        $response->assertStatus(200)
            ->assertJsonPath('data.products_summary.total', 100)
            ->assertJsonPath('data.products_summary.processed', 95)
            ->assertJsonPath('data.products_summary.failed', 5)
            ->assertJsonPath('data.products_summary.indexed', 90);
    }

    public function test_products_summary_includes_total_processed_failed_indexed()
    {
        $syncLog = SyncLog::factory()->create([
            'status' => 'completed',
            'total_products' => 250,
            'processed_products' => 240,
            'failed_products' => 10,
            'indexed_products' => 235,
        ]);

        $response = $this->getJson("/api/v1/sync-logs/{$syncLog->id}/details");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'products_summary' => [
                        'total',
                        'processed',
                        'failed',
                        'indexed',
                    ]
                ]
            ]);
    }

    public function test_success_sync_includes_timing_information()
    {
        $syncLog = SyncLog::factory()->create([
            'status' => 'completed',
            'started_at' => '2026-03-15T07:00:00Z',
            'completed_at' => '2026-03-15T07:05:30Z',
        ]);

        $response = $this->getJson("/api/v1/sync-logs/{$syncLog->id}/details");

        $response->assertStatus(200)
            ->assertJsonPath('data.started_at', '2026-03-15T07:00:00+00:00')
            ->assertJsonPath('data.completed_at', '2026-03-15T07:05:30+00:00')
            ->assertJsonPath('data.duration_seconds', 330);
    }

    public function test_success_sync_calculates_duration_correctly()
    {
        $syncLog = SyncLog::factory()->create([
            'status' => 'completed',
            'started_at' => '2026-03-15T07:00:00Z',
            'completed_at' => '2026-03-15T07:02:45Z', // 2 minutes 45 seconds = 165 seconds
        ]);

        $response = $this->getJson("/api/v1/sync-logs/{$syncLog->id}/details");

        $response->assertStatus(200)
            ->assertJsonPath('data.duration_seconds', 165);
    }

    public function test_success_sync_details_endpoint_returns_complete_data()
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'admin', 'joined_at' => now()]);

        $syncLog = SyncLog::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'completed',
            'total_products' => 100,
            'processed_products' => 98,
            'failed_products' => 2,
            'indexed_products' => 95,
            'started_at' => '2026-03-15T07:00:00Z',
            'completed_at' => '2026-03-15T07:03:20Z',
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
                    'tenant',
                    'products_summary',
                    'started_at',
                    'completed_at',
                    'duration_seconds',
                ]
            ])
            ->assertJsonPath('data.products_summary.total', 100)
            ->assertJsonPath('data.products_summary.processed', 98)
            ->assertJsonPath('data.products_summary.failed', 2)
            ->assertJsonPath('data.products_summary.indexed', 95)
            ->assertJsonPath('data.duration_seconds', 200);
    }
}
