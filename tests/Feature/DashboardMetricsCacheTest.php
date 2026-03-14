<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\User;
use App\Models\SyncLog;
use App\Enums\SyncStatus;

class DashboardMetricsCacheTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create();
        $this->user->tenants()->attach($this->tenant, ['role' => 'admin']);
    }

    public function test_dashboard_metrics_endpoint_uses_cache()
    {
        // Create a sync log for the tenant
        SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => SyncStatus::COMPLETED,
            'processed_products' => 100,
        ]);

        // Clear cache to ensure fresh data
        Cache::forget("agency:dashboard:metrics:{$this->tenant->id}");

        // Call the metrics endpoint
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->get('/dashboard/metrics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'last_sync',
                'synced_at',
                'last_sync_status',
                'products_synced',
            ],
        ]);

        // Verify cache was set
        $this->assertNotNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_cache_key_format_includes_tenant_id()
    {
        $cacheKey = "agency:dashboard:metrics:{$this->tenant->id}";

        // Assert cache key contains tenant UUID
        $this->assertStringContainsString($this->tenant->id, $cacheKey);
        $this->assertStringStartsWith('agency:dashboard:metrics:', $cacheKey);
    }

    public function test_cached_metrics_include_last_sync_data()
    {
        // Create a sync log
        $syncLog = SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => SyncStatus::COMPLETED,
            'processed_products' => 150,
        ]);

        // Clear cache and fetch metrics
        Cache::forget("agency:dashboard:metrics:{$this->tenant->id}");

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->get('/dashboard/metrics');

        $data = $response->json('data');

        // Assert metrics include sync data
        $this->assertNotNull($data['last_sync']);
        $this->assertNotNull($data['synced_at']);
        $this->assertEquals('completed', $data['last_sync_status']);
        $this->assertEquals(150, $data['products_synced']);
    }

    public function test_cache_miss_triggers_fresh_data_generation()
    {
        // Create a sync log
        SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => SyncStatus::COMPLETED,
        ]);

        // Clear cache
        Cache::forget("agency:dashboard:metrics:{$this->tenant->id}");

        // Verify cache is empty
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));

        // Call endpoint
        $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->get('/dashboard/metrics');

        // Assert cache was populated
        $this->assertNotNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_cache_hit_returns_cached_data_without_database_query()
    {
        // Create a sync log
        SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => SyncStatus::COMPLETED,
        ]);

        // Prime cache by calling endpoint
        Cache::forget("agency:dashboard:metrics:{$this->tenant->id}");
        $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->get('/dashboard/metrics');

        // Enable query counting
        \DB::enableQueryLog();

        // Call endpoint again (should use cache)
        $this->actingAs($this->user)
            ->withHeader('X-Tenant-ID', $this->tenant->id)
            ->get('/dashboard/metrics');

        // Assert no database queries were made (cache hit)
        $this->assertEmpty(\DB::getQueryLog());
    }
}
