<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SyncLogDetailsResource;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;

/**
 * @group Sync Logs
 *
 * API endpoints for accessing detailed sync log information
 */
class SyncLogDetailsController extends Controller
{
    /**
     * Display detailed sync log information.
     *
     * Returns comprehensive details including error payloads, stack traces,
     * products summary, and timing information.
     *
     * @authenticated
     *
     * @urlParam id string required Sync Log UUID
     *
     * @responseField data.id string Sync log UUID
     * @responseField data.tenant_id string Tenant UUID
     * @responseField data.platform_type string Platform (shopify, shopware)
     * @responseField data.status string Sync status
     * @responseField data.error_message string? Error message if failed
     * @responseField data.metadata object Full metadata JSON
     * @responseField data.error_details object? Extracted error details from metadata
     * @responseField data.tenant object? Tenant information (id, name, platform_type)
     * @responseField data.products_summary object Product counts (total, processed, failed, indexed)
     * @responseField data.started_at string? ISO8601 start timestamp
     * @responseField data.completed_at string? ISO8601 completion timestamp
     * @responseField data.duration_seconds int? Duration in seconds
     *
     * @response {
     *   "data": {
     *     "id": "uuid",
     *     "tenant_id": "tenant-uuid",
     *     "platform_type": "shopify",
     *     "status": "completed",
     *     "error_message": null,
     *     "metadata": {},
     *     "error_details": null,
     *     "tenant": {
     *       "id": "tenant-uuid",
     *       "name": "My Store",
     *       "platform_type": "shopify"
     *     },
     *     "products_summary": {
     *       "total": 100,
     *       "processed": 95,
     *       "failed": 5,
     *       "indexed": 90
     *     },
     *     "started_at": "2026-03-15T07:00:00+00:00",
     *     "completed_at": "2026-03-15T07:05:00+00:00",
     *     "duration_seconds": 300
     *   }
     * }
     * @response 404 {
     *   "message": "Sync log not found"
     * }
     */
    public function show(string $id): JsonResponse
    {
        $syncLog = SyncLog::with('tenant')->find($id);

        if (!$syncLog) {
            return response()->json(['message' => 'Sync log not found'], 404);
        }

        // Tenant authorization check
        $userTenants = auth()->user()->tenants->pluck('id');
        if (!$userTenants->contains($syncLog->tenant_id)) {
            return response()->json(['message' => 'Sync log not found'], 404);
        }

        return response()->json(['data' => SyncLogDetailsResource::make($syncLog)->toArray(request())]);
    }
}
