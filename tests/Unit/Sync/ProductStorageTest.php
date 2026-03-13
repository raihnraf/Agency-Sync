<?php

declare(strict_types=1);

namespace Tests\Unit\Sync;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Product;
use App\Models\Tenant;
use App\Enums\PlatformType;

class ProductStorageTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test tenant
        $this->tenant = Tenant::factory()->create();
    }

    public function test_product_has_fillable_fields()
    {
        $product = new Product([
            'tenant_id' => $this->tenant->id,
            'external_id' => 'shopify_123',
            'name' => 'Test Product',
            'description' => 'Test Description',
            'sku' => 'TEST-SKU-001',
            'price' => 99.99,
            'stock_quantity' => 10,
            'platform' => 'shopify',
        ]);

        $this->assertEquals('shopify_123', $product->external_id);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals('Test Description', $product->description);
        $this->assertEquals('TEST-SKU-001', $product->sku);
        $this->assertEquals(99.99, $product->price);
        $this->assertEquals(10, $product->stock_quantity);
        $this->assertEquals('shopify', $product->platform);
    }

    public function test_product_casts_price_and_stock()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'price' => '99.99',
            'stock_quantity' => '10',
        ]);

        $this->assertIsFloat($product->price);
        $this->assertIsInt($product->stock_quantity);
        $this->assertEquals(99.99, $product->price);
        $this->assertEquals(10, $product->stock_quantity);
    }

    public function test_product_belongs_to_tenant()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertInstanceOf(Tenant::class, $product->tenant);
        $this->assertEquals($this->tenant->id, $product->tenant->id);
    }

    public function test_product_global_scope_scopes_by_tenant_id()
    {
        // Create products for two different tenants
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        Product::factory()->create(['tenant_id' => $tenant1->id, 'external_id' => 'ext_1']);
        Product::factory()->create(['tenant_id' => $tenant1->id, 'external_id' => 'ext_2']);
        Product::factory()->create(['tenant_id' => $tenant2->id, 'external_id' => 'ext_3']);

        // Set current tenant to tenant1
        Tenant::setCurrent($tenant1);

        // Should only see tenant1's products
        $products = Product::all();
        $this->assertCount(2, $products);
        $this->assertTrue($products->every(fn($p) => $p->tenant_id === $tenant1->id));

        Tenant::clearCurrent();
    }

    public function test_product_can_be_created()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'external_id' => 'shopify_test_123',
            'name' => 'New Product',
            'platform' => 'shopify',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'tenant_id' => $this->tenant->id,
            'external_id' => 'shopify_test_123',
            'name' => 'New Product',
            'platform' => 'shopify',
        ]);
    }

    public function test_update_or_create_works_with_unique_constraint()
    {
        // Create initial product
        $product = Product::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'external_id' => 'shopify_upsert_123',
            ],
            [
                'name' => 'Original Name',
                'price' => 50.00,
                'stock_quantity' => 5,
                'platform' => 'shopify',
            ]
        );

        $this->assertEquals('Original Name', $product->name);
        $this->assertEquals(50.00, $product->price);

        // Update using updateOrCreate (should update, not create new)
        $updated = Product::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'external_id' => 'shopify_upsert_123',
            ],
            [
                'name' => 'Updated Name',
                'price' => 75.00,
                'stock_quantity' => 10,
                'platform' => 'shopify',
            ]
        );

        $this->assertEquals($product->id, $updated->id, 'Should update existing product');
        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals(75.00, $updated->price);

        // Verify only one product exists
        $this->assertEquals(1, Product::where('external_id', 'shopify_upsert_123')->count());
    }

    public function test_external_id_unique_per_tenant()
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Same external_id for different tenants should be allowed
        $product1 = Product::factory()->create([
            'tenant_id' => $tenant1->id,
            'external_id' => 'shopify_duplicate',
            'platform' => 'shopify',
        ]);

        $product2 = Product::factory()->create([
            'tenant_id' => $tenant2->id,
            'external_id' => 'shopify_duplicate',
            'platform' => 'shopify',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product1->id,
            'tenant_id' => $tenant1->id,
            'external_id' => 'shopify_duplicate',
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product2->id,
            'tenant_id' => $tenant2->id,
            'external_id' => 'shopify_duplicate',
        ]);

        // Same external_id for same tenant should fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        Product::factory()->create([
            'tenant_id' => $tenant1->id,
            'external_id' => 'shopify_duplicate',
            'platform' => 'shopify',
        ]);
    }

    public function test_soft_deletes_work()
    {
        $product = Product::factory()->create([
            'tenant_id' => $this->tenant->id,
            'external_id' => 'shopify_soft_delete',
            'platform' => 'shopify',
        ]);

        $productId = $product->id;

        // Soft delete
        $product->delete();

        // Should not appear in regular queries
        $this->assertNull(Product::find($productId));

        // Should still be in database
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'external_id' => 'shopify_soft_delete',
        ]);

        // Should appear with trashed
        $this->assertNotNull(Product::withTrashed()->find($productId));

        // Restore
        $product->restore();
        $this->assertNotNull(Product::find($productId));
    }
}
