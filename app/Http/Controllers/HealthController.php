<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    /**
     * Health check endpoint for deployment verification
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke()
    {
        try {
            // Check database connection
            DB::connection()->getPdo();

            // Check cache connection
            Cache::get("health_check", "ok");

            return response()->json([
                'status' => 'ok',
                'timestamp' => now()->toIso8601String(),
                'database' => 'connected',
                'cache' => 'connected',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}
