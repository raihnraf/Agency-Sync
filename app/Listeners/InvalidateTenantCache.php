<?php

namespace App\Listeners;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class InvalidateTenantCache
{
    /**
     * Handle the tenant event.
     */
    public function handle(Tenant $tenant): void
    {
        // Clear tenant list cache
        Cache::forget('agency:tenants:list');

        // Clear tenant-specific dashboard metrics
        Cache::forget("agency:dashboard:metrics:{$tenant->id}");

        // Clear global metrics
        Cache::forget('agency:dashboard:global');
    }
}
