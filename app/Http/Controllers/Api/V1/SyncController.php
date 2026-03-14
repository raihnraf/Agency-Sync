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

/**
 * @group Catalog Synchronization
 *
 * API endpoints for triggering and monitoring product catalog synchronization
 */
class SyncController extends Controller
{
    /**
     * Dispatch a sync job for the given tenant.
     *
     * Triggers an asynchronous sync job for the specified tenant.
     *
     * @authenticated
     *
     * @bodyParam tenant_id string required Tenant UUID to sync. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam data array optional Additional sync data
     *
     * @response 202 {
     *   "data": {
     *     "job_id": "uuid",
     *     "status": "pending",
     *     "message": "Sync job dispatched successfully"
     *   }
     * }
     * @response 422 {
     *   "message": "Validation failed",
     *   "errors": {
     *     "tenant_id": ["The tenant id field is required."]
     *   }
     * }
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
     *
     * Triggers product catalog sync from Shopify platform.
     *
     * @authenticated
     *
     * @bodyParam tenant_id string required Tenant UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 202 {
     *   "data": {
     *     "sync_id": "uuid",
     *     "status": "pending",
     *     "message": "Shopify product sync dispatched successfully"
     *   }
     * }
     * @response 422 {
     *   "message": "Tenant is not a Shopify tenant"
     * }
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
     *
     * Triggers product catalog sync from Shopware platform.
     *
     * @authenticated
     *
     * @bodyParam tenant_id string required Tenant UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 202 {
     *   "data": {
     *     "sync_id": "uuid",
     *     "status": "pending",
     *     "message": "Shopware product sync dispatched successfully"
     *   }
     * }
     * @response 422 {
     *   "message": "Tenant is not a Shopware tenant"
     * }
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
     *
     * Returns the current status of a sync job including progress and error details.
     *
     * @authenticated
     *
     * @urlParam syncLogId string required Sync log UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @responseField data.job_id string Job UUID
     * @responseField data.status string Job status (pending, running, completed, failed)
     * @responseField data.progress integer Progress percentage (0-100)
     * @responseField data.products_indexed integer Number of products processed
     * @responseField data.started_at timestamp Job start time
     * @responseField data.completed_at timestamp Job completion time (null if running)
     * @responseField data.error_message string Error message (null if success)
     *
     * @response {
     *   "data": {
     *     "job_id": "uuid",
     *     "status": "running",
     *     "progress": 45,
     *     "products_indexed": 234,
     *     "started_at": "2026-03-15T10:00:00Z",
     *     "completed_at": null,
     *     "error_message": null
     *   }
     * }
     * @response 404 {
     *   "message": "Sync log not found"
     * }
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

    /**
     * Get sync history with pagination and filtering.
     *
     * Returns paginated list of sync operations for the current tenant.
     *
     * @authenticated
     *
     * @queryParam status string optional Filter by status (pending, running, completed, failed, partially_failed). Example: completed
     * @queryParam page integer Page number (default: 1). Example: 1
     * @queryParam per_page integer Items per page (default: 20, max: 100). Example: 20
     *
     * @responseField data{0}.id string Sync log UUID
     * @responseField data{0}.status string Sync status
     * @responseField data{0}.products_indexed integer Products indexed
     * @responseField data{0}.started_at timestamp Start time
     * @responseField data{0}.completed_at timestamp Completion time
     *
     * @response {
     *   "data": [
     *     {
     *       "id": "uuid",
     *       "status": "completed",
     *       "products_indexed": 1500,
     *       "started_at": "2026-03-15T10:00:00Z",
     *       "completed_at": "2026-03-15T10:05:00Z"
     *     }
     *   ],
     *   "meta": {
     *     "total": 45,
     *     "per_page": 20,
     *     "current_page": 1,
     *     "last_page": 3
     *   }
     * }
     */
    public function history(Request $request): JsonResponse
    {
        // Validate query parameters
        $validated = $request->validate([
            'status' => 'nullable|in:pending,running,completed,failed,partially_failed',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        // Get current tenant from context
        $tenant = Tenant::currentTenant();

        // Build query
        $query = $tenant->syncLogs();

        // Filter by status if provided
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // Order by created_at DESC (most recent first)
        $query->orderBy('created_at', 'desc');

        // Paginate
        $perPage = $validated['per_page'] ?? 20;
        $paginator = $query->paginate($perPage);

        // Transform collection using SyncLogResource
        $data = SyncLogResource::collection($paginator);

        return response()->json([
            'data' => $data->toArray(request()),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
