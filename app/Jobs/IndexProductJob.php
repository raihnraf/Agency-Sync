<?php

namespace App\Jobs;

use App\Jobs\TenantAwareJob;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

/**
 * Index Product Job
 * 
 * Asynchronously indexes a product to Elasticsearch.
 * Extends TenantAwareJob for automatic tenant context restoration.
 */
class IndexProductJob extends TenantAwareJob
{
    public string $productId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $productId, string $tenantId)
    {
        parent::__construct($tenantId);
        $this->productId = $productId;
        $this->queue = 'indexing';
        $this->tries = 3;
        $this->timeout = 120;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Tenant context is automatically restored by SetTenantContext middleware
        $product = Product::find($this->productId);
        
        if (!$product) {
            Log::warning("IndexProductJob: Product not found", [
                'product_id' => $this->productId,
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        // Index the product to Elasticsearch
        $product->searchable();

        Log::info("IndexProductJob: Product indexed successfully", [
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
            'product_name' => $product->name,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("IndexProductJob: Failed to index product", [
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
