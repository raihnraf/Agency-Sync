<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SyncLogCollection;
use App\Models\SyncLog;
use Illuminate\Http\Request;

class SyncLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = SyncLog::query();

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by tenant if provided
        if ($request->has('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Date range filtering
        if ($request->has('date_from')) {
            $query->where('started_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('started_at', '<=', $request->date_to);
        }

        // Load tenant relationship
        $query->with('tenant');

        // Paginate
        $logs = $query->orderBy('started_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json(new SyncLogCollection($logs));
    }
}
