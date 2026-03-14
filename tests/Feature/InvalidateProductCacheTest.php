<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InvalidateProductCacheTest extends TestCase
{
    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_listener_clears_tenant_dashboard_metrics_cache()
    {
        // Set cache for tenant
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['cached' => true]);

        // Create product (should trigger event)
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Assert cache was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_listener_handles_product_created_event()
    {
        // Set cache
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['cached' => true]);

        // Create product
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Assert cache was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_listener_handles_product_updated_event()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Set cache
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['cached' => true]);

        // Update product
        $product->update(['name' => 'Updated Product Name']);

        // Assert cache was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_listener_handles_product_deleted_event()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Set cache
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['cached' => true]);

        // Delete product
        $product->delete();

        // Assert cache was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_listener_reads_tenant_id_from_product_model()
    {
        // Set cache for specific tenant
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['cached' => true]);

        // Create product with tenant_id
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify product has correct tenant_id
        $this->assertEquals($this->tenant->id, $product->tenant_id);

        // Assert cache for that tenant was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_listener_clears_only_tenant_specific_cache()
    {
        $tenant2 = Tenant::factory()->create();

        // Set cache for both tenants
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['tenant1' => true]);
        Cache::put("agency:dashboard:metrics:{$tenant2->id}", ['tenant2' => true]);

        // Create product for tenant1
        Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Assert tenant1 cache cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));

        // Assert tenant2 cache still present
        $this->assertNotNull(Cache::get("agency:dashboard:metrics:{$tenant2->id}"));
    }
}
