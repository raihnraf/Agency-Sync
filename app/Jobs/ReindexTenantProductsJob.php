<?php

namespace App\Jobs;

use App\Jobs\TenantAwareJob;
use App\Models\JobStatus;
use App\Models\Product;
use App\Models\Tenant;
use App\Search\IndexManager;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Reindex Tenant Products Job
 * 
 * Bulk reindexes all products for a tenant.
 * Deletes and recreates the index, then indexes all products in chunks.
 * Suitable for catalog sync operations.
 */
class ReindexTenantProductsJob extends TenantAwareJob
{
    public ?string $jobStatusId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tenantId, ?string $jobStatusId = null)
    {
        parent::__construct($tenantId);
        $this->jobStatusId = $jobStatusId;
        $this->queue = 'indexing';
        $this->tries = 1; // No retry for bulk operations
        $this->timeout = 3600; // 1 hour for large catalogs
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $productCount = 0;

        // Update job status to running
        if ($this->jobStatusId) {
            $jobStatus = JobStatus::find($this->jobStatusId);
            if ($jobStatus) {
                $jobStatus->markAsRunning();
            }
        }

        // Get tenant
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) {
            Log::error("ReindexTenantProductsJob: Tenant not found", [
                'tenant_id' => $this->tenantId,
            ]);
            $this->markJobAsFailed('Tenant not found');
            return;
        }

        // Set tenant context
        Tenant::setCurrentTenant($tenant);

        // Create IndexManager
        $client = ClientBuilder::create()
            ->setHosts(config('scout.elasticsearch.hosts', ['localhost:9200']))
            ->build();
        $indexManager = new IndexManager($client);

        // Delete existing index
        Log::info("ReindexTenantProductsJob: Deleting existing index", [
            'tenant_id' => $this->tenantId,
        ]);
        $indexManager->deleteIndex($tenant);

        // Create new index
        Log::info("ReindexTenantProductsJob: Creating new index", [
            'tenant_id' => $this->tenantId,
        ]);
        $indexManager->createIndex($tenant);

        // Index products in chunks to avoid memory issues
        Log::info("ReindexTenantProductsJob: Starting product indexing", [
            'tenant_id' => $this->tenantId,
        ]);

        Product::where('tenant_id', $this->tenantId)
            ->chunk(100, function ($products) use (&$productCount) {
                foreach ($products as $product) {
                    try {
                        $product->searchable();
                        $productCount++;
                    } catch (\Exception $e) {
                        Log::warning("ReindexTenantProductsJob: Failed to index product", [
                            'product_id' => $product->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $duration = round(microtime(true) - $startTime, 2);

        Log::info("ReindexTenantProductsJob: Reindex completed", [
            'tenant_id' => $this->tenantId,
            'product_count' => $productCount,
            'duration_seconds' => $duration,
        ]);

        // Update job status to completed
        if ($this->jobStatusId) {
            $jobStatus = JobStatus::find($this->jobStatusId);
            if ($jobStatus) {
                $jobStatus->markAsCompleted();
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ReindexTenantProductsJob: Reindex failed", [
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->markJobAsFailed($exception->getMessage());
    }

    /**
     * Mark job status as failed.
     */
    protected function markJobAsFailed(string $error): void
    {
        if ($this->jobStatusId) {
            $jobStatus = JobStatus::find($this->jobStatusId);
            if ($jobStatus) {
                $jobStatus->markAsFailed($error);
            }
        }
    }
}
