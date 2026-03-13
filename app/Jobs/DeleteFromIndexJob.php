<?php

namespace App\Jobs;

use App\Jobs\TenantAwareJob;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Delete From Index Job
 * 
 * Asynchronously removes a product from Elasticsearch index.
 * Extends TenantAwareJob for automatic tenant context restoration.
 */
class DeleteFromIndexJob extends TenantAwareJob
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
        // Try to find the product (may be soft-deleted)
        $product = Product::withTrashed()->find($this->productId);
        
        if (!$product) {
            // Create a minimal product instance for unsearchable()
            $product = new Product();
            $product->id = $this->productId;
            $product->tenant_id = $this->tenantId;
        }

        // Remove from Elasticsearch
        $product->unsearchable();

        Log::info("DeleteFromIndexJob: Product removed from index", [
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("DeleteFromIndexJob: Failed to remove product from index", [
            'product_id' => $this->productId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }
}
