<?php

namespace App\Queue\Middleware;

use Closure;
use App\Models\Tenant;

class SetTenantContext
{
    public function handle(object $job, Closure $next): void
    {
        if (property_exists($job, 'tenantId')) {
            $tenant = Tenant::findOrFail($job->tenantId);
            app()->instance('currentTenant', $tenant);
            Tenant::setCurrentTenant($tenant);
        }

        $next($job);

        // Clear tenant context after job
        app()->forgetInstance('currentTenant');
        Tenant::setCurrentTenant(null);
    }
}
