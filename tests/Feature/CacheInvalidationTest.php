<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\Tenant;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\User;

class CacheInvalidationTest extends TestCase
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

    public function test_tenant_creation_clears_tenant_list_cache()
    {
        // Prime cache
        Cache::put('agency:tenants:list', []);

        // Create tenant
        Tenant::factory()->create();

        // Assert tenant list cache cleared
        $this->assertTrue(Cache::missing('agency:tenants:list'));
    }

    public function test_tenant_update_clears_dashboard_metrics_cache()
    {
        // Prime cache
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Update tenant
        $this->tenant->update(['name' => 'Updated Name']);

        // Assert metrics cache cleared
        $this->assertTrue(Cache::missing($cacheKey));
    }

    public function test_tenant_deletion_clears_both_caches()
    {
        // Prime caches
        Cache::put('agency:tenants:list', []);
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Delete tenant
        $this->tenant->delete();

        // Assert both caches cleared
        $this->assertTrue(Cache::missing('agency:tenants:list'));
        $this->assertTrue(Cache::missing($cacheKey));
    }

    public function test_product_creation_clears_dashboard_metrics_cache()
    {
        // Prime cache
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Create product
        Product::factory()->for($this->tenant)->create();

        // Assert metrics cache cleared
        $this->assertTrue(Cache::missing($cacheKey));
    }

    public function test_product_update_clears_dashboard_metrics_cache()
    {
        // Create product and prime cache
        $product = Product::factory()->for($this->tenant)->create();
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Update product
        $product->update(['name' => 'Updated Product']);

        // Assert metrics cache cleared
        $this->assertTrue(Cache::missing($cacheKey));
    }

    public function test_product_deletion_clears_dashboard_metrics_cache()
    {
        // Create product and prime cache
        $product = Product::factory()->for($this->tenant)->create();
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Delete product
        $product->delete();

        // Assert metrics cache cleared
        $this->assertTrue(Cache::missing($cacheKey));
    }

    public function test_sync_log_creation_clears_dashboard_metrics_cache()
    {
        // Prime cache
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Create sync log
        SyncLog::factory()->for($this->tenant)->create();

        // Assert metrics cache cleared
        $this->assertTrue(Cache::missing($cacheKey));
    }

    public function test_sync_log_update_clears_dashboard_metrics_cache()
    {
        // Create sync log and prime cache
        $syncLog = SyncLog::factory()->for($this->tenant)->create();
        $cacheKey = 'agency:dashboard:metrics:' . $this->tenant->id;
        Cache::put($cacheKey, []);

        // Update sync log
        $syncLog->update(['status' => 'completed']);

        // Assert metrics cache cleared
        $this->assertTrue(Cache::missing($cacheKey));
    }
}
