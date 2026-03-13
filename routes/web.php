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
});
