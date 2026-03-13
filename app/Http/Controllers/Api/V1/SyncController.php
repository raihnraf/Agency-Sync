<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PlatformType;
use App\Enums\SyncStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SyncLogResource;
use App\Jobs\ExampleSyncJob;
use App\Jobs\Sync\FetchShopifyProductsJob;
use App\Jobs\Sync\FetchShopwareProductsJob;
use App\Models\SyncLog;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    /**
     * Dispatch a sync job for the given tenant.
     */
    public function dispatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|string|exists:tenants,id',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->input('tenant_id');
        $data = $request->input('data', []);

        // Create a unique job ID
        $jobId = (string) Str::uuid();

        // Dispatch the example sync job
        ExampleSyncJob::dispatch($tenantId, $data);

        return response()->json([
            'data' => [
                'job_id' => $jobId,
                'status' => 'pending',
                'message' => 'Sync job dispatched successfully',
            ],
        ], 202);
    }

    /**
     * Dispatch a Shopify product sync job.
     */
    public function syncShopify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|string|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->input('tenant_id');
        $tenant = Tenant::find($tenantId);

        if (!$tenant || $tenant->platform_type !== PlatformType::SHOPIFY) {
            return response()->json([
                'message' => 'Tenant is not a Shopify tenant',
            ], 422);
        }

        $syncLog = SyncLog::create([
            'tenant_id' => $tenantId,
            'platform_type' => PlatformType::SHOPIFY,
            'status' => SyncStatus::PENDING,
        ]);

        FetchShopifyProductsJob::dispatch($tenantId, $syncLog->id);

        return response()->json([
            'data' => [
                'sync_id' => $syncLog->id,
                'status' => 'pending',
                'message' => 'Shopify product sync dispatched successfully',
            ],
        ], 202);
    }

    /**
     * Dispatch a Shopware product sync job.
     */
    public function syncShopware(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|string|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tenantId = $request->input('tenant_id');
        $tenant = Tenant::find($tenantId);

        if (!$tenant || $tenant->platform_type !== PlatformType::SHOPIWARE) {
            return response()->json([
                'message' => 'Tenant is not a Shopware tenant',
            ], 422);
        }

        $syncLog = SyncLog::create([
            'tenant_id' => $tenantId,
            'platform_type' => PlatformType::SHOPIWARE,
            'status' => SyncStatus::PENDING,
        ]);

        FetchShopwareProductsJob::dispatch($tenantId, $syncLog->id);

        return response()->json([
            'data' => [
                'sync_id' => $syncLog->id,
                'status' => 'pending',
                'message' => 'Shopware product sync dispatched successfully',
            ],
        ], 202);
    }

    /**
     * Get the status of a sync operation.
     */
    public function status(string $id): JsonResponse
    {
        $syncLog = SyncLog::find($id);

        if (!$syncLog) {
            return response()->json([
                'message' => 'Sync log not found',
            ], 404);
        }

        // Tenant validation: ensure user can access this sync log
        $userTenants = auth()->user()->tenants->pluck('id');
        if (!$userTenants->contains($syncLog->tenant_id)) {
            return response()->json([
                'message' => 'Sync log not found',
            ], 404);
        }

        return response()->json([
            'data' => SyncLogResource::make($syncLog)->toArray(request()),
            'meta' => [],
        ]);
    }
}
