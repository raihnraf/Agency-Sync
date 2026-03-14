<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CacheWarm extends Command
{
    protected $signature = 'cache:warm {--tenant=* : Warm cache for specific tenant(s)}';

    protected $description = 'Warm Laravel caches for improved performance';

    public function handle(): int
    {
        $this->info('Warming caches...');

        // Warm tenant list cache
        $this->warmTenantList();

        // Warm dashboard metrics for tenants
        $tenants = $this->option('tenant');

        if (empty($tenants) || in_array('*', $tenants)) {
            // Warm all tenants
            $this->warmAllTenantsMetrics();
        } else {
            // Warm specific tenants
            foreach ($tenants as $tenantId) {
                $this->warmTenantMetrics($tenantId);
            }
        }

        $this->info('Cache warmed successfully!');

        return Command::SUCCESS;
    }

    private function warmTenantList(): void
    {
        $this->info('Warming tenant list cache...');

        // Prime tenant list cache
        Cache::remember('agency:tenants:list', 900, function () {
            return Tenant::select(['id', 'name', 'slug', 'status'])
                ->orderBy('name')
                ->get();
        });
    }

    private function warmAllTenantsMetrics(): void
    {
        $this->info('Warming dashboard metrics for all tenants...');

        Tenant::select('id')->chunk(100, function ($tenants) {
            foreach ($tenants as $tenant) {
                $this->warmTenantMetrics($tenant->id);
            }
        });
    }

    private function warmTenantMetrics(string $tenantId): void
    {
        $this->line("  Warming metrics for tenant: {$tenantId}");

        // Prime dashboard metrics cache
        Cache::remember("agency:dashboard:metrics:{$tenantId}", 300, function () use ($tenantId) {
            $lastSync = SyncLog::where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->first(['created_at', 'status']);

            return [
                'last_sync' => $lastSync,
                'synced_at' => $lastSync?->created_at,
            ];
        });
    }
}
