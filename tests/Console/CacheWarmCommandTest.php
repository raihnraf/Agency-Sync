<?php

namespace Tests\Console;

use App\Models\SyncLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheWarmCommandTest extends TestCase
{
    public function test_command_signature_is_cache_warm_with_tenant_option()
    {
        $this->assertArrayHasKey('cache:warm', \Artisan::all());
    }

    public function test_command_warms_tenant_list_cache_by_default()
    {
        // Clear any existing cache
        Cache::forget('agency:tenants:list');

        // Create some tenants
        Tenant::factory()->count(3)->create();

        // Run the command
        $this->artisan('cache:warm')
            ->assertExitCode(0)
            ->expectsOutput('Warming caches...')
            ->expectsOutput('Warming tenant list cache...')
            ->expectsOutput('Cache warmed successfully!');

        // Assert cache was warmed
        $cachedTenants = Cache::get('agency:tenants:list');
        $this->assertNotNull($cachedTenants);
        $this->assertCount(3, $cachedTenants);
    }

    public function test_command_with_tenant_all_warms_all_tenants_dashboard_metrics()
    {
        // Create tenants with sync logs
        $tenants = Tenant::factory()->count(2)->create();
        foreach ($tenants as $tenant) {
            SyncLog::factory()->create([
                'tenant_id' => $tenant->id,
                'status' => 'completed',
            ]);
        }

        // Clear all tenant metrics cache
        foreach ($tenants as $tenant) {
            Cache::forget("agency:dashboard:metrics:{$tenant->id}");
        }

        // Run the command with --tenant=*
        $this->artisan('cache:warm', ['--tenant' => ['*']])
            ->assertExitCode(0)
            ->expectsOutput('Warming caches...')
            ->expectsOutput('Warming dashboard metrics for all tenants...')
            ->expectsOutput('Cache warmed successfully!');

        // Assert all tenant metrics were warmed
        foreach ($tenants as $tenant) {
            $cachedMetrics = Cache::get("agency:dashboard:metrics:{$tenant->id}");
            $this->assertNotNull($cachedMetrics);
        }
    }

    public function test_command_with_specific_tenant_uuid_warms_that_tenant_metrics()
    {
        $tenants = Tenant::factory()->count(2)->create();
        $tenant1 = $tenants[0];
        $tenant2 = $tenants[1];

        // Create sync logs for both tenants
        SyncLog::factory()->create([
            'tenant_id' => $tenant1->id,
            'status' => 'completed',
        ]);
        SyncLog::factory()->create([
            'tenant_id' => $tenant2->id,
            'status' => 'completed',
        ]);

        // Clear both tenant metrics cache
        Cache::forget("agency:dashboard:metrics:{$tenant1->id}");
        Cache::forget("agency:dashboard:metrics:{$tenant2->id}");

        // Run the command with specific tenant
        $this->artisan('cache:warm', ['--tenant' => [$tenant1->id]])
            ->assertExitCode(0)
            ->expectsOutput('Warming caches...')
            ->expectsOutput("  Warming metrics for tenant: {$tenant1->id}")
            ->expectsOutput('Cache warmed successfully!');

        // Assert only tenant1 metrics were warmed
        $cachedMetrics1 = Cache::get("agency:dashboard:metrics:{$tenant1->id}");
        $this->assertNotNull($cachedMetrics1);

        $cachedMetrics2 = Cache::get("agency:dashboard:metrics:{$tenant2->id}");
        $this->assertNull($cachedMetrics2);
    }

    public function test_command_outputs_success_message()
    {
        $this->artisan('cache:warm')
            ->expectsOutput('Cache warmed successfully!')
            ->assertExitCode(0);
    }
}
