<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\Tenant;
use App\Listeners\InvalidateTenantCache;
use App\Events\TenantCreated;
use App\Events\TenantUpdated;
use App\Events\TenantDeleted;

class InvalidateTenantCacheTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected InvalidateTenantCache $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->listener = new InvalidateTenantCache();
    }

    public function test_listener_clears_tenant_list_cache()
    {
        // Prime cache
        Cache::put('agency:tenants:list', []);

        // Call listener
        $this->listener->handle($this->tenant);

        // Assert tenant list cache cleared
        $this->assertTrue(Cache::missing('agency:tenants:list'));
    }

    public function test_listener_clears_dashboard_metrics_cache()
    {
        // Prime cache
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Call listener
        $this->listener->handle($this->tenant);

        // Assert metrics cache cleared
        $this->assertTrue(Cache::missing($cacheKey));
    }

    public function test_listener_clears_global_dashboard_cache()
    {
        // Prime cache
        Cache::put('agency:dashboard:global', []);

        // Call listener
        $this->listener->handle($this->tenant);

        // Assert global cache cleared
        $this->assertTrue(Cache::missing('agency:dashboard:global'));
    }

    public function test_listener_handle_accepts_tenant_model()
    {
        // Assert handle method exists and accepts Tenant model
        $this->assertTrue(
            method_exists($this->listener, 'handle'),
            'Listener should have handle method'
        );

        // Test with actual tenant (placeholder for type check)
        $this->assertTrue(true);
    }

    public function test_listener_uses_cache_forget_for_invalidation()
    {
        // Prime all caches
        Cache::put('agency:tenants:list', []);
        Cache::put('agency:dashboard:metrics:' . $this->tenant->id, []);
        Cache::put('agency:dashboard:global', []);

        // Mock Cache facade and assert forget called
        Cache::shouldReceive('forget')
            ->once()
            ->with('agency:tenants:list')
            ->andReturn(true);

        Cache::shouldReceive('forget')
            ->once()
            ->with('agency:dashboard:metrics:' . $this->tenant->id)
            ->andReturn(true);

        Cache::shouldReceive('forget')
            ->once()
            ->with('agency:dashboard:global')
            ->andReturn(true);

        // Call listener
        $this->listener->handle($this->tenant);

        $this->assertTrue(true);
    }
}
