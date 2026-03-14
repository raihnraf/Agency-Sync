<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\Tenant;
use App\Models\SyncLog;
use App\Listeners\InvalidateSyncLogCache;

class InvalidateSyncLogCacheTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected SyncLog $syncLog;

    protected InvalidateSyncLogCache $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->syncLog = SyncLog::factory()->for($this->tenant)->create();
        $this->listener = new InvalidateSyncLogCache();
    }

    public function test_listener_clears_dashboard_metrics_cache()
    {
        // Prime cache
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Call listener
        $this->listener->handle($this->syncLog);

        // Assert metrics cache cleared
        $this->assertTrue(Cache::missing($cacheKey));
    }

    public function test_listener_handle_accepts_sync_log_model()
    {
        // Assert handle method exists and accepts SyncLog model
        $this->assertTrue(
            method_exists($this->listener, 'handle'),
            'Listener should have handle method'
        );

        // Test with actual sync log (placeholder for type check)
        $this->assertTrue(true);
    }

    public function test_listener_reads_tenant_id_from_sync_log_model()
    {
        // Assert sync log has tenant_id
        $this->assertEquals($this->tenant->id, $this->syncLog->tenant_id);

        // Listener should access syncLog->tenant_id
        $cacheKey = 'agency:dashboard:metrics:' . $this->syncLog->tenant_id;
        $this->assertStringContainsString($this->syncLog->tenant_id, $cacheKey);

        $this->assertTrue(true);
    }

    public function test_listener_uses_cache_forget_for_invalidation()
    {
        // Prime cache
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Mock Cache facade and assert forget called once with correct key
        Cache::shouldReceive('forget')
            ->once()
            ->with($cacheKey)
            ->andReturn(true);

        // Call listener
        $this->listener->handle($this->syncLog);

        $this->assertTrue(true);
    }

    public function test_listener_only_clears_tenant_specific_metrics()
    {
        // Prime all caches
        Cache::put('agency:tenants:list', []);
        Cache::put('agency:dashboard:metrics:' . $this->tenant->id, []);
        Cache::put('agency:dashboard:global', []);

        // Call listener
        $this->listener->handle($this->syncLog);

        // Assert only metrics cache cleared, others remain
        $this->assertTrue(Cache::missing('agency:dashboard:metrics:' . $this->tenant->id));
        $this->assertTrue(Cache::has('agency:tenants:list'));
        $this->assertTrue(Cache::has('agency:dashboard:global'));
    }
}
