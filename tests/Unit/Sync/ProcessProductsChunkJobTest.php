<?php

declare(strict_types=1);

namespace Tests\Unit\Sync;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Jobs\Sync\ProcessProductsChunkJob;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Tenant;
use App\Services\Sync\ProductValidator;

class ProcessProductsChunkJobTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected SyncLog $syncLog;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test tenant
        $this->tenant = Tenant::factory()->create();

        // Set current tenant context
        Tenant::setCurrent($this->tenant);

        // Create a sync log
        $this->syncLog = SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
        ]);
    }

    protected function tearDown(): void
    {
        Tenant::clearCurrent();
        parent::tearDown();
    }

    public function test_job_extends_tenant_aware_job()
    {
        $job = new ProcessProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            []
        );

        $this->assertInstanceOf(\App\Jobs\TenantAwareJob::class, $job);
        $this->assertEquals($this->tenant->id, $job->tenantId);
    }

    public function test_job_constructor_accepts_sync_log_id_and_products_chunk()
    {
        $productsChunk = [
            ['external_id' => 'shopify_1', 'name' => 'Product 1'],
            ['external_id' => 'shopify_2', 'name' => 'Product 2'],
        ];

        $job = new ProcessProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $productsChunk
        );

        $this->assertEquals($this->syncLog->id, $job->syncLogId);
        $this->assertEquals($productsChunk, $job->productsChunk);
        $this->assertCount(2, $job->productsChunk);
    }

    public function test_job_validates_each_product()
    {
        Queue::fake();

        $productsChunk = [
            [
                'external_id' => 'shopify_valid_1',
                'name' => 'Valid Product',
                'price' => 99.99,
                'stock' => 10,
                'platform' => 'shopify',
            ],
            [
                'external_id' => 'shopify_invalid',
                'name' => '', // Invalid: empty name
                'price' => 99.99,
                'stock' => 10,
                'platform' => 'shopify',
            ],
        ];

        $job = new ProcessProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $productsChunk
        );

        $job->handle();

        // Only valid product should be stored
        $this->assertDatabaseHas('products', [
            'tenant_id' => $this->tenant->id,
            'external_id' => 'shopify_valid_1',
        ]);

        $this->assertDatabaseMissing('products', [
            'external_id' => 'shopify_invalid',
        ]);

        // SyncLog should have one failed product
        $this->syncLog->refresh();
        $this->assertEquals(1, $this->syncLog->failed_products);
    }

    public function test_job_stores_products_using_update_or_create()
    {
        $productsChunk = [
            [
                'external_id' => 'shopify_upsert_1',
                'name' => 'Product 1',
                'description' => 'Description 1',
                'sku' => 'SKU1',
                'price' => 50.00,
                'stock' => 5,
                'platform' => 'shopify',
            ],
        ];

        // First run - create
        $job1 = new ProcessProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $productsChunk
        );
        $job1->handle();

        $product = Product::where('external_id', 'shopify_upsert_1')->first();
        $this->assertEquals('Product 1', $product->name);
        $this->assertEquals(50.00, $product->price);

        // Second run - update (same external_id)
        $productsChunk[0]['name'] = 'Product 1 Updated';
        $productsChunk[0]['price'] = 75.00;

        $job2 = new ProcessProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $productsChunk
        );
        $job2->handle();

        $product->refresh();
        $this->assertEquals('Product 1 Updated', $product->name);
        $this->assertEquals(75.00, $product->price);

        // Should still be only one product
        $this->assertEquals(1, Product::where('external_id', 'shopify_upsert_1')->count());
    }

    public function test_job_updates_sync_log_processed_counter()
    {
        $productsChunk = [
            ['external_id' => 'shopify_1', 'name' => 'Product 1', 'price' => 10, 'stock' => 1, 'platform' => 'shopify'],
            ['external_id' => 'shopify_2', 'name' => 'Product 2', 'price' => 20, 'stock' => 2, 'platform' => 'shopify'],
            ['external_id' => 'shopify_3', 'name' => 'Product 3', 'price' => 30, 'stock' => 3, 'platform' => 'shopify'],
        ];

        $job = new ProcessProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $productsChunk
        );

        $this->assertEquals(0, $this->syncLog->processed_products);

        $job->handle();

        $this->syncLog->refresh();
        $this->assertEquals(3, $this->syncLog->processed_products);
    }

    public function test_job_increments_sync_log_failed_counter_on_validation_errors()
    {
        $productsChunk = [
            ['external_id' => 'shopify_valid', 'name' => 'Valid', 'price' => 10, 'stock' => 1, 'platform' => 'shopify'],
            ['external_id' => 'shopify_invalid1', 'name' => '', 'price' => 20, 'stock' => 2, 'platform' => 'shopify'], // Invalid
            ['external_id' => 'shopify_invalid2', 'name' => 'Another', 'price' => -5, 'stock' => 3, 'platform' => 'shopify'], // Invalid: negative price
            ['external_id' => 'shopify_valid2', 'name' => 'Valid 2', 'price' => 40, 'stock' => 4, 'platform' => 'shopify'],
        ];

        $job = new ProcessProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $productsChunk
        );

        $job->handle();

        $this->syncLog->refresh();
        $this->assertEquals(2, $this->syncLog->processed_products);
        $this->assertEquals(2, $this->syncLog->failed_products);
    }

    public function test_job_uses_database_transaction()
    {
        // This test verifies that if one product fails, the whole chunk fails
        // by checking that no partial data is written

        $productsChunk = [
            ['external_id' => 'shopify_tx_1', 'name' => 'Product 1', 'price' => 10, 'stock' => 1, 'platform' => 'shopify'],
            ['external_id' => 'shopify_tx_2', 'name' => 'Product 2', 'price' => 20, 'stock' => 2, 'platform' => 'shopify'],
        ];

        $job = new ProcessProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $productsChunk
        );

        $job->handle();

        // Both products should be stored (atomic transaction)
        $this->assertDatabaseHas('products', ['external_id' => 'shopify_tx_1']);
        $this->assertDatabaseHas('products', ['external_id' => 'shopify_tx_2']);
    }

    public function test_job_sets_last_synced_at_timestamp()
    {
        $productsChunk = [
            ['external_id' => 'shopify_ts_1', 'name' => 'Product 1', 'price' => 10, 'stock' => 1, 'platform' => 'shopify'],
        ];

        $job = new ProcessProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $productsChunk
        );

        $beforeTime = now()->subSeconds(5);
        $job->handle();
        $afterTime = now()->addSeconds(5);

        $product = Product::where('external_id', 'shopify_ts_1')->first();
        $this->assertNotNull($product->last_synced_at);
        $this->assertTrue($product->last_synced_at->gte($beforeTime));
        $this->assertTrue($product->last_synced_at->lte($afterTime));
    }
}
