<?php

namespace App\Jobs\Sync;

use App\Enums\PlatformType;
use App\Jobs\TenantAwareJob;
use App\Models\SyncLog;
use App\Models\Tenant;
use App\Services\Sync\ProductValidator;
use App\Services\Sync\ShopwareSyncService;
use Exception;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class FetchShopwareProductsJob extends TenantAwareJob
{
    public string $syncLogId;

    public function __construct(string $tenantId, string $syncLogId)
    {
        parent::__construct($tenantId);
        $this->syncLogId = $syncLogId;
    }

    public function handle(): void
    {
        $syncLog = SyncLog::find($this->syncLogId);

        if (!$syncLog) {
            Log::error('Sync log not found', ['sync_log_id' => $this->syncLogId]);
            return;
        }

        $syncLog->markAsRunning();

        try {
            $tenant = Tenant::currentTenant();

            if (!$tenant) {
                throw new Exception('Tenant not found for sync job');
            }

            $validator = app(ProductValidator::class);
            $syncService = new ShopwareSyncService($validator, true);

            // Fetch products from Shopware API
            $products = $syncService->fetchProducts($tenant, $syncLog);

            // Update total products count
            $syncLog->update(['total_products' => $products->count()]);

            // Normalize products
            $normalized = $products->map(function ($product) use ($syncService) {
                return $syncService->normalizeProduct($product);
            });

            // Chunk normalized products into batches of 500
            $chunks = $normalized->chunk(500);

            // Build job chain: process chunks -> collect IDs -> index products
            $jobs = [];

            foreach ($chunks as $chunk) {
                $jobs[] = new ProcessProductsChunkJob(
                    $this->tenantId,
                    $this->syncLogId,
                    $chunk->toArray()
                );
            }

            // Add final job to index all products after storage completes
            $jobs[] = new IndexAfterStorageJob(
                $this->tenantId,
                $this->syncLogId,
                $normalized->pluck('external_id')->toArray()
            );

            // Dispatch the job chain
            Bus::chain($jobs)->dispatch();

            Log::info('Shopware product fetch completed, job chain dispatched', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
                'total_products' => $products->count(),
                'chunks' => $chunks->count(),
            ]);

            // Note: SyncLog status will be updated by the final job in the chain
        } catch (Exception $e) {
            // Capture stack trace
            $stackTrace = array_map(function ($frame) {
                return [
                    'file' => $frame['file'] ?? 'unknown',
                    'line' => $frame['line'] ?? 0,
                    'function' => $frame['function'] ?? 'unknown',
                    'class' => $frame['class'] ?? null,
                    'type' => $frame['type'] ?? null,
                ];
            }, $e->getTrace());

            // Build error details
            $errorDetails = [
                'type' => 'internal_error',
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $stackTrace,
                'timestamp' => now()->toIso8601String(),
            ];

            // Store in sync log
            $syncLog->update([
                'error_message' => $e->getMessage(),
                'metadata' => array_merge($syncLog->metadata ?? [], ['error_details' => $errorDetails])
            ]);

            // Existing logging
            Log::error('Shopware product sync failed', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
