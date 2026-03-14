<?php

namespace Tests\Feature;

use App\Models\SyncLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InvalidateSyncLogCacheTest extends TestCase
{
    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_listener_clears_tenant_dashboard_metrics_cache()
    {
        // Set cache for tenant
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['cached' => true]);

        // Create sync log (should trigger event)
        SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Assert cache was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_listener_handles_sync_log_created_event()
    {
        // Set cache
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['cached' => true]);

        // Create sync log
        SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Assert cache was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_listener_handles_sync_log_updated_event()
    {
        $syncLog = SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'running',
        ]);

        // Set cache
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['cached' => true]);

        // Update sync log
        $syncLog->update(['status' => 'completed']);

        // Assert cache was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_listener_reads_tenant_id_from_sync_log_model()
    {
        // Set cache for specific tenant
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['cached' => true]);

        // Create sync log with tenant_id
        $syncLog = SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify sync log has correct tenant_id
        $this->assertEquals($this->tenant->id, $syncLog->tenant_id);

        // Assert cache for that tenant was cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));
    }

    public function test_listener_clears_only_tenant_specific_cache()
    {
        $tenant2 = Tenant::factory()->create();

        // Set cache for both tenants
        Cache::put("agency:dashboard:metrics:{$this->tenant->id}", ['tenant1' => true]);
        Cache::put("agency:dashboard:metrics:{$tenant2->id}", ['tenant2' => true]);

        // Create sync log for tenant1
        SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Assert tenant1 cache cleared
        $this->assertNull(Cache::get("agency:dashboard:metrics:{$this->tenant->id}"));

        // Assert tenant2 cache still present
        $this->assertNotNull(Cache::get("agency:dashboard:metrics:{$tenant2->id}"));
    }
}
