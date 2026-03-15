<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\IndexController;
use App\Http\Controllers\Api\V1\ProductSearchController;
use App\Http\Controllers\Api\V1\SyncLogDetailsController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\ExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| All API endpoints are versioned with /api/v1/ prefix.
| Future versions can be added as separate Route::prefix('v2') groups.
|
*/

Route::prefix('v1')->group(function () {
    // Public routes with stricter rate limiting
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Protected routes with auth, token expiration, and rate limiting
    Route::middleware(['auth:sanctum', 'token.expire'])->group(function () {
        Route::middleware('throttle:api-write')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
        });

        Route::middleware('throttle:api-read')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Tenant management routes
        // Index and store don't require tenant context
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);

        // Show, update, delete require tenant context
        Route::middleware(['tenant', 'tenant.scope'])->group(function () {
            Route::get('/tenants/{id}', [TenantController::class, 'show']);
            Route::put('/tenants/{id}', [TenantController::class, 'update']);
            Route::delete('/tenants/{id}', [TenantController::class, 'destroy']);
        });

        // Sync operation routes
        Route::prefix('sync')->group(function () {
            // Sync trigger routes (write operations)
            Route::middleware('throttle:api-write')->group(function () {
                Route::post('/dispatch', [SyncController::class, 'dispatch']);
            });

            // Sync status route (read operation)
            Route::middleware('throttle:api-read')->group(function () {
                Route::get('/status/{syncLogId}', [SyncController::class, 'status']);
            });

            // Sync history route (requires tenant context)
            Route::middleware(['throttle:api-read', 'tenant', 'tenant.scope'])->group(function () {
                Route::get('/history', [SyncController::class, 'history']);
            });
        });

        // Sync log details routes
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('/sync-logs/{id}/details', [SyncLogDetailsController::class, 'show']);
        });

        // Product search routes
        Route::middleware('throttle:api-read')->group(function () {
            Route::get('/tenants/{tenantId}/search', [ProductSearchController::class, 'search']);
            Route::get('/tenants/{tenantId}/search/status', [ProductSearchController::class, 'status']);
            Route::post('/tenants/{tenantId}/search/reindex', [ProductSearchController::class, 'reindex']);
        });

        // Index management routes (async operations)
        Route::middleware('throttle:api-write')->group(function () {
            Route::post('/tenants/{tenantId}/reindex', [IndexController::class, 'reindex']);
        });

        Route::middleware('throttle:api-read')->group(function () {
            Route::get('/jobs/{jobId}/status', [IndexController::class, 'status']);
            Route::get('/tenants/{tenantId}/jobs', [IndexController::class, 'list']);
        });

        // Export routes
        Route::middleware('throttle:api-write')->group(function () {
            Route::post('/exports/sync-logs', [ExportController::class, 'dispatchSyncLogsExport']);
            Route::post('/exports/products', [ExportController::class, 'dispatchProductExport']);
        });

        Route::middleware('throttle:api-read')->group(function () {
            Route::get('/exports/{uuid}', [ExportController::class, 'download']);
        });
    });
});
