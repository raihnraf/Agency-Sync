<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\TenantController;
use App\Http\Controllers\Dashboard\ErrorLogController;

// Health check endpoint (must be at top, outside auth middleware)
Route::get('/health', HealthController::class)->name('health');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Dashboard routes (require authentication)
Route::middleware(['auth'])->prefix('dashboard')->group(function () {
    // Dashboard metrics endpoint (cached)
    Route::get('/metrics', [DashboardController::class, 'metrics'])->name('dashboard.metrics');
    
    // Tenant management
    Route::get('/tenants', [TenantController::class, 'index'])->name('dashboard.tenants.index');
    Route::get('/tenants/create', [TenantController::class, 'create'])->name('dashboard.tenants.create');
    Route::get('/tenants/{id}', [TenantController::class, 'show'])->name('dashboard.tenants.show');
    Route::get('/tenants/{id}/edit', [TenantController::class, 'edit'])->name('dashboard.tenants.edit');
    Route::get('/tenants/{id}/products', [TenantController::class, 'products'])->name('dashboard.tenants.products');

    // Error log
    Route::get('/error-log', [ErrorLogController::class, 'index'])->name('dashboard.error-log.index');
});

require __DIR__.'/auth.php';
