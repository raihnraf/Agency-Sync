<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Get dashboard metrics for the current tenant.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function metrics(Request $request): JsonResponse
    {
        $tenantId = $request->header('X-Tenant-ID');

        // Cache per-tenant metrics for 5 minutes
        $metrics = Cache::remember("agency:dashboard:metrics:{$tenantId}", 300, function () use ($tenantId) {
            $lastSync = SyncLog::where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->first(['created_at', 'status', 'processed_products']);

            return [
                'last_sync' => $lastSync,
                'synced_at' => $lastSync?->created_at,
                'last_sync_status' => $lastSync?->status->value,
                'products_synced' => $lastSync?->processed_products,
            ];
        });

        return response()->json(['data' => $metrics]);
    }
}
