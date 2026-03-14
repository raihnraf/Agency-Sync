<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InvalidateTenantCacheTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_listener_clears_agency_tenants_list_cache()
    {
        // Set cache
        Cache::put('agency:tenants:list', ['cached' => true]);

        // Create tenant (should trigger event)
        Tenant::factory()->create();

        // Assert cache was cleared
        $this->assertNull(Cache::get('agency:tenants:list'));
    }

    public function test_listener_clears_tenant_specific_dashboard_metrics_cache()
    {
        $tenant = Tenant::factory()->create();

        // Set cache for specific tenant
        Cache::put("agency:dashboard:metrics:{$tenant->id}", ['cached' => true]);

        // Update tenant (should trigger event)
        $tenant->update(['name' => 'Updated Name']);

        // Assert cache was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$tenant->id}"));
    }

    public function test_listener_clears_global_dashboard_metrics_cache()
    {
        // Set global cache
        Cache::put('agency:dashboard:global', ['cached' => true]);

        // Delete tenant (should trigger event)
        $tenant = Tenant::factory()->create();
        $tenant->delete();

        // Assert cache was cleared
        $this->assertNull(Cache::get('agency:dashboard:global'));
    }

    public function test_listener_handles_tenant_created_event()
    {
        // Set all relevant caches
        Cache::put('agency:tenants:list', ['cached' => true]);
        $tenant = Tenant::factory()->create();
        Cache::put("agency:dashboard:metrics:{$tenant->id}", ['cached' => true]);
        Cache::put('agency:dashboard:global', ['cached' => true]);

        // Clear caches manually to test created event
        Cache::flush();

        // Reset caches for testing
        Cache::put('agency:tenants:list', ['cached' => true]);
        Cache::put("agency:dashboard:metrics:{$tenant->id}", ['cached' => true]);
        Cache::put('agency:dashboard:global', ['cached' => true]);

        // Create new tenant
        $newTenant = Tenant::factory()->create();

        // Assert all caches cleared
        $this->assertNull(Cache::get('agency:tenants:list'));
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$newTenant->id}"));
        $this->assertNull(Cache::get('agency:dashboard:global'));
    }

    public function test_listener_handles_tenant_updated_event()
    {
        $tenant = Tenant::factory()->create();

        // Set all relevant caches
        Cache::put('agency:tenants:list', ['cached' => true]);
        Cache::put("agency:dashboard:metrics:{$tenant->id}", ['cached' => true]);
        Cache::put('agency:dashboard:global', ['cached' => true]);

        // Update tenant
        $tenant->update(['name' => 'Updated Name']);

        // Assert all caches cleared
        $this->assertNull(Cache::get('agency:tenants:list'));
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$tenant->id}"));
        $this->assertNull(Cache::get('agency:dashboard:global'));
    }

    public function test_listener_handles_tenant_deleted_event()
    {
        $tenant = Tenant::factory()->create();

        // Set all relevant caches
        Cache::put('agency:tenants:list', ['cached' => true]);
        Cache::put("agency:dashboard:metrics:{$tenant->id}", ['cached' => true]);
        Cache::put('agency:dashboard:global', ['cached' => true]);

        // Delete tenant
        $tenant->delete();

        // Assert all caches cleared
        $this->assertNull(Cache::get('agency:tenants:list'));
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$tenant->id}"));
        $this->assertNull(Cache::get('agency:dashboard:global'));
    }
}
