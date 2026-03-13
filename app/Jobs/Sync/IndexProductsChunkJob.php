<?php

namespace App\Jobs\Sync;

use App\Jobs\IndexProductJob;
use App\Jobs\TenantAwareJob;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Tenant;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class IndexProductsChunkJob extends TenantAwareJob
{
    public string $syncLogId;
    public array $productIds;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tenantId, string $syncLogId, array $productIds)
    {
        parent::__construct($tenantId);
        $this->syncLogId = $syncLogId;
        $this->productIds = $productIds;
        $this->queue = 'sync';
        $this->tries = 3;
        $this->timeout = 120;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load SyncLog
        $syncLog = SyncLog::find($this->syncLogId);

        if (!$syncLog) {
            Log::error('IndexProductsChunkJob: SyncLog not found', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        // Get current tenant
        $tenant = Tenant::currentTenant();

        if (!$tenant) {
            Log::error('IndexProductsChunkJob: Tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        // Query products by IDs (ensures they belong to current tenant via TenantScope)
        $products = Product::whereIn('id', $this->productIds)->get();

        if ($products->isEmpty()) {
            Log::warning('IndexProductsChunkJob: No products found to index', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
                'product_ids' => $this->productIds,
            ]);
            return;
        }

        // Dispatch IndexProductJob for each product
        $indexedCount = 0;
        foreach ($products as $product) {
            try {
                // Dispatch to indexing queue
                Queue::push(new IndexProductJob($product->id, $this->tenantId));
                $indexedCount++;
            } catch (Exception $e) {
                Log::error('IndexProductsChunkJob: Failed to dispatch IndexProductJob', [
                    'sync_log_id' => $this->syncLogId,
                    'tenant_id' => $this->tenantId,
                    'product_id' => $product->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Update SyncLog indexed counter
        $syncLog->incrementIndexed($indexedCount);

        Log::info('IndexProductsChunkJob: Completed', [
            'sync_log_id' => $this->syncLogId,
            'tenant_id' => $this->tenantId,
            'total_products' => count($this->productIds),
            'found_products' => $products->count(),
            'indexed_products' => $indexedCount,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('IndexProductsChunkJob: Job failed', [
            'sync_log_id' => $this->syncLogId,
            'tenant_id' => $this->tenantId,
            'product_ids_count' => count($this->productIds),
            'error' => $exception->getMessage(),
        ]);

        // Mark syncLog as failed if critical error
        $syncLog = SyncLog::find($this->syncLogId);
        if ($syncLog) {
            $syncLog->markAsFailed($exception->getMessage());
        }
    }
}
