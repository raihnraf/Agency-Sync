<?php

namespace App\Jobs\Sync;

use App\Enums\PlatformType;
use App\Enums\SyncStatus;
use App\Events\Sync\ProductsFetched;
use App\Jobs\TenantAwareJob;
use App\Models\SyncLog;
use App\Models\Tenant;
use App\Services\Sync\ProductValidator;
use App\Services\Sync\ShopifySyncService;
use Exception;
use Illuminate\Support\Facades\Log;

class FetchShopifyProductsJob extends TenantAwareJob
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

            $validator = new ProductValidator();
            $syncService = new ShopifySyncService($validator, true);

            $products = $syncService->fetchProducts($tenant, $syncLog);

            $processed = 0;
            $failed = 0;

            foreach ($products as $product) {
                try {
                    $syncService->normalizeProduct($product);
                    $syncLog->incrementProcessed();
                    $processed++;
                } catch (Exception $e) {
                    $syncLog->incrementFailed($e->getMessage());
                    $failed++;
                }
            }

            $syncLog->markAsCompleted($products->count(), $processed, $failed);

            ProductsFetched::dispatch(
                PlatformType::SHOPIFY,
                $products,
                $this->tenantId
            );

            Log::info('Shopify product sync completed', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
                'total' => $products->count(),
                'processed' => $processed,
                'failed' => $failed,
            ]);
        } catch (Exception $e) {
            $syncLog->markAsFailed($e->getMessage());

            Log::error('Shopify product sync failed', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
