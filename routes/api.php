<?php

use App\Http\Controllers\Api\V1\AuthController;
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
    });
});
