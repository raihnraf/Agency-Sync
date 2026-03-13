<?php

declare(strict_types=1);

namespace Tests\Unit\Sync;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Jobs\Sync\IndexProductsChunkJob;
use App\Jobs\IndexProductJob;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Tenant;

class IndexProductsChunkJobTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected SyncLog $syncLog;
    protected array $productIds;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test tenant
        $this->tenant = Tenant::factory()->create();

        // Set current tenant context
        Tenant::setCurrent($this->tenant);

        // Create test products
        $products = Product::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->productIds = $products->pluck('id')->toArray();

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
        $job = new IndexProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $this->productIds
        );

        $this->assertInstanceOf(\App\Jobs\TenantAwareJob::class, $job);
        $this->assertEquals($this->tenant->id, $job->tenantId);
    }

    public function test_job_constructor_accepts_product_ids_array()
    {
        $job = new IndexProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $this->productIds
        );

        $this->assertEquals($this->syncLog->id, $job->syncLogId);
        $this->assertEquals($this->productIds, $job->productIds);
        $this->assertCount(5, $job->productIds);
    }

    public function test_job_dispatches_index_product_job_for_each_id()
    {
        Queue::fake();

        $job = new IndexProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $this->productIds
        );

        $job->handle();

        // Verify IndexProductJob was dispatched for each product
        Queue::assertPushed(IndexProductJob::class, 5);

        foreach ($this->productIds as $productId) {
            Queue::assertPushed(IndexProductJob::class, function ($job) use ($productId) {
                return $job->productId === $productId && $job->tenantId === $this->tenant->id;
            });
        }
    }

    public function test_job_uses_batch_dispatch()
    {
        Queue::fake();

        // Create 250 product IDs to test batching
        $largeProductIds = [];
        $products = Product::factory()->count(250)->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $largeProductIds = $products->pluck('id')->toArray();

        $job = new IndexProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $largeProductIds
        );

        $job->handle();

        // Should dispatch 250 IndexProductJob instances
        Queue::assertPushed(IndexProductJob::class, 250);
    }

    public function test_job_handles_missing_products_gracefully()
    {
        Queue::fake();

        // Mix of valid and invalid product IDs
        $mixedIds = array_merge(
            $this->productIds, // 5 valid IDs
            ['invalid-uuid-1', 'invalid-uuid-2'] // 2 invalid IDs
        );

        $job = new IndexProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $mixedIds
        );

        // Should not throw exception
        $job->handle();

        // Should still dispatch for valid products
        Queue::assertPushed(IndexProductJob::class, 5);
    }

    public function test_job_updates_sync_log_indexed_counter()
    {
        Queue::fake();

        $job = new IndexProductsChunkJob(
            $this->tenant->id,
            $this->syncLog->id,
            $this->productIds
        );

        $this->assertEquals(0, $this->syncLog->indexed_products);

        $job->handle();

        $this->syncLog->refresh();
        $this->assertEquals(5, $this->syncLog->indexed_products);
    }
}
