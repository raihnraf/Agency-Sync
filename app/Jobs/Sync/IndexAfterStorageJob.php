<?php

namespace App\Jobs\Sync;

use App\Jobs\TenantAwareJob;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Tenant;
use Exception;
use Illuminate\Support\Facades\Log;

class IndexAfterStorageJob extends TenantAwareJob
{
    public string $syncLogId;
    public array $externalIds;

    public function __construct(string $tenantId, string $syncLogId, array $externalIds)
    {
        parent::__construct($tenantId);
        $this->syncLogId = $syncLogId;
        $this->externalIds = $externalIds;
        $this->queue = 'sync';
    }

    public function handle(): void
    {
        $syncLog = SyncLog::find($this->syncLogId);

        if (!$syncLog) {
            Log::error('IndexAfterStorageJob: SyncLog not found', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        try {
            // Collect all product IDs from stored products
            $products = Product::where('tenant_id', $this->tenantId)
                ->whereIn('external_id', $this->externalIds)
                ->get();

            $productIds = $products->pluck('id')->toArray();

            if (empty($productIds)) {
                Log::warning('IndexAfterStorageJob: No products found to index', [
                    'sync_log_id' => $this->syncLogId,
                    'tenant_id' => $this->tenantId,
                    'external_ids_count' => count($this->externalIds),
                ]);

                // Mark sync as completed even if no products to index
                $syncLog->markAsCompleted(
                    $syncLog->total_products,
                    $syncLog->processed_products,
                    $syncLog->failed_products
                );
                return;
            }

            // Dispatch indexing job
            $indexJob = new IndexProductsChunkJob(
                $this->tenantId,
                $this->syncLogId,
                $productIds
            );

            // Dispatch the indexing job
            dispatch($indexJob);

            Log::info('IndexAfterStorageJob: Dispatched indexing for products', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
                'products_count' => count($productIds),
            ]);
        } catch (Exception $e) {
            Log::error('IndexAfterStorageJob: Failed', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            // Mark sync as failed
            $syncLog->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('IndexAfterStorageJob: Job failed', [
            'sync_log_id' => $this->syncLogId,
            'tenant_id' => $this->tenantId,
            'external_ids_count' => count($this->externalIds),
            'error' => $exception->getMessage(),
        ]);

        $syncLog = SyncLog::find($this->syncLogId);
        if ($syncLog) {
            $syncLog->markAsFailed($exception->getMessage());
        }
    }
}
