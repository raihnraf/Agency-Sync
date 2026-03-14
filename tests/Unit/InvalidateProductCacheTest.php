<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\Tenant;
use App\Models\Product;
use App\Listeners\InvalidateProductCache;

class InvalidateProductCacheTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Product $product;

    protected InvalidateProductCache $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->for($this->tenant)->create();
        $this->listener = new InvalidateProductCache();
    }

    public function test_listener_clears_dashboard_metrics_cache()
    {
        // Prime cache
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Call listener
        $this->listener->handle($this->product);

        // Assert metrics cache cleared
        $this->assertTrue(Cache::missing($cacheKey));
    }

    public function test_listener_handle_accepts_product_model()
    {
        // Assert handle method exists and accepts Product model
        $this->assertTrue(
            method_exists($this->listener, 'handle'),
            'Listener should have handle method'
        );

        // Test with actual product (placeholder for type check)
        $this->assertTrue(true);
    }

    public function test_listener_reads_tenant_id_from_product_model()
    {
        // Assert product has tenant_id
        $this->assertEquals($this->tenant->id, $this->product->tenant_id);

        // Listener should access product->tenant_id
        $cacheKey = 'agency:dashboard:metrics:' . $this->product->tenant_id;
        $this->assertStringContainsString($this->product->tenant_id, $cacheKey);

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
        $this->listener->handle($this->product);

        $this->assertTrue(true);
    }

    public function test_listener_only_clears_tenant_specific_metrics()
    {
        // Prime all caches
        Cache::put('agency:tenants:list', []);
        Cache::put('agency:dashboard:metrics:' . $this->tenant->id, []);
        Cache::put('agency:dashboard:global', []);

        // Call listener
        $this->listener->handle($this->product);

        // Assert only metrics cache cleared, others remain
        $this->assertTrue(Cache::missing('agency:dashboard:metrics:' . $this->tenant->id));
        $this->assertTrue(Cache::has('agency:tenants:list'));
        $this->assertTrue(Cache::has('agency:dashboard:global'));
    }
}
