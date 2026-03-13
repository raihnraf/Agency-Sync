<?php

namespace App\Jobs\Sync;

use App\Jobs\TenantAwareJob;
use App\Models\Product;
use App\Models\SyncLog;
use App\Models\Tenant;
use App\Services\Sync\ProductValidator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProcessProductsChunkJob extends TenantAwareJob
{
    public string $syncLogId;
    public array $productsChunk;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tenantId, string $syncLogId, array $productsChunk)
    {
        parent::__construct($tenantId);
        $this->syncLogId = $syncLogId;
        $this->productsChunk = $productsChunk;
        $this->queue = 'sync';
        $this->tries = 3;
        $this->timeout = 120;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Inject validator
        $validator = app(ProductValidator::class);

        // Load SyncLog
        $syncLog = SyncLog::find($this->syncLogId);

        if (!$syncLog) {
            Log::error('ProcessProductsChunkJob: SyncLog not found', [
                'sync_log_id' => $this->syncLogId,
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        // Get current tenant
        $tenant = Tenant::currentTenant();

        if (!$tenant) {
            Log::error('ProcessProductsChunkJob: Tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        // Process each product in the chunk
        foreach ($this->productsChunk as $productData) {
            try {
                // Validate product data
                $validated = $validator->validate($productData);

                // Store product using updateOrCreate for idempotency
                DB::transaction(function () use ($validated, $syncLog) {
                    Product::updateOrCreate(
                        [
                            'tenant_id' => $this->tenantId,
                            'external_id' => $validated['external_id'],
                        ],
                        array_merge($validated, [
                            'last_synced_at' => now(),
                        ])
                    );

                    // Increment processed counter
                    $syncLog->incrementProcessed();
                });
            } catch (ValidationException $e) {
                // Validation error - log and continue with next product
                $syncLog->incrementFailed($e->getMessage());

                Log::warning('ProcessProductsChunkJob: Product validation failed', [
                    'sync_log_id' => $this->syncLogId,
                    'tenant_id' => $this->tenantId,
                    'external_id' => $productData['external_id'] ?? 'unknown',
                    'errors' => $e->errors(),
                ]);
            } catch (Exception $e) {
                // Critical error - log and rethrow to trigger retry
                Log::error('ProcessProductsChunkJob: Failed to process product', [
                    'sync_log_id' => $this->syncLogId,
                    'tenant_id' => $this->tenantId,
                    'external_id' => $productData['external_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        Log::info('ProcessProductsChunkJob: Completed', [
            'sync_log_id' => $this->syncLogId,
            'tenant_id' => $this->tenantId,
            'chunk_size' => count($this->productsChunk),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessProductsChunkJob: Job failed', [
            'sync_log_id' => $this->syncLogId,
            'tenant_id' => $this->tenantId,
            'chunk_size' => count($this->productsChunk),
            'error' => $exception->getMessage(),
        ]);

        // Mark syncLog as failed if critical error
        $syncLog = SyncLog::find($this->syncLogId);
        if ($syncLog) {
            $syncLog->markAsFailed($exception->getMessage());
        }
    }
}
