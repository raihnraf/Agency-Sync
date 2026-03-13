<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\TenantController;

Route::get('/', function () {
    return view('welcome');
});

// Dashboard routes (require authentication)
Route::middleware(['auth'])->prefix('dashboard')->group(function () {
    // Tenant management
    Route::get('/tenants', [TenantController::class, 'index'])->name('dashboard.tenants.index');
    Route::get('/tenants/create', [TenantController::class, 'create'])->name('dashboard.tenants.create');
    Route::get('/tenants/{id}', [TenantController::class, 'show'])->name('dashboard.tenants.show');
    Route::get('/tenants/{id}/edit', [TenantController::class, 'edit'])->name('dashboard.tenants.edit');
});
