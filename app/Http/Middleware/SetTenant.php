<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract X-Tenant-ID header
        $tenantId = $request->header('X-Tenant-ID');

        // If missing, return 422 with explicit error
        if (empty($tenantId)) {
            return response()->json([
                'errors' => [
                    ['field' => 'X-Tenant-ID', 'message' => 'X-Tenant-ID header is required'],
                ],
            ], 422);
        }

        // Query tenant that belongs to the user
        $tenant = \App\Models\Tenant::where('id', $tenantId)
            ->whereHas('users', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->first();

        // If not found or user not associated, return 404 with generic error
        if (! $tenant) {
            return response()->json([
                'errors' => [
                    ['message' => 'Tenant not found or access denied'],
                ],
            ], 404);
        }

        // Store tenant in request attributes
        $request->attributes->set('current_tenant', $tenant);

        // Set tenant on user
        $request->user()->setCurrentTenant($tenant);

        // Set tenant in app container for Tenant::currentTenant()
        \App\Models\Tenant::setCurrentTenant($tenant);

        return $next($request);
    }
}
