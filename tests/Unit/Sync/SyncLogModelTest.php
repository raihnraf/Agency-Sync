<?php

namespace Tests\Unit\Sync;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\SyncLog;
use App\Models\Tenant;
use App\Enums\SyncStatus;
use App\Enums\PlatformType;

class SyncLogModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_log_has_fillable_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $syncLog = SyncLog::create([
            'tenant_id' => $tenant->id,
            'platform_type' => PlatformType::SHOPIFY,
            'status' => SyncStatus::PENDING,
        ]);

        $this->assertEquals($tenant->id, $syncLog->tenant_id);
        $this->assertEquals(PlatformType::SHOPIFY, $syncLog->platform_type);
        $this->assertEquals(SyncStatus::PENDING, $syncLog->status);
    }

    public function test_sync_log_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $syncLog = SyncLog::factory()
            ->for($tenant)
            ->create();

        $this->assertEquals($tenant->id, $syncLog->tenant->id);
    }

    public function test_mark_as_running_updates_status_and_started_at(): void
    {
        $syncLog = SyncLog::factory()->create();

        $syncLog->markAsRunning();

        $this->assertEquals(SyncStatus::RUNNING, $syncLog->status);
        $this->assertNotNull($syncLog->started_at);
    }

    public function test_mark_as_completed_updates_status_and_counters(): void
    {
        $syncLog = SyncLog::factory()->create();
        $syncLog->markAsRunning();

        $syncLog->markAsCompleted(100, 95, 5);

        $this->assertEquals(SyncStatus::PARTIALLY_FAILED, $syncLog->status);
        $this->assertNotNull($syncLog->completed_at);
        $this->assertEquals(100, $syncLog->total_products);
        $this->assertEquals(95, $syncLog->processed_products);
        $this->assertEquals(5, $syncLog->failed_products);
    }

    public function test_mark_as_completed_with_no_failures_is_completed(): void
    {
        $syncLog = SyncLog::factory()->create();
        $syncLog->markAsRunning();

        $syncLog->markAsCompleted(100, 100, 0);

        $this->assertEquals(SyncStatus::COMPLETED, $syncLog->status);
    }

    public function test_mark_as_failed_updates_status_and_error_message(): void
    {
        $syncLog = SyncLog::factory()->create();
        $syncLog->markAsRunning();

        $syncLog->markAsFailed('API connection failed');

        $this->assertEquals(SyncStatus::FAILED, $syncLog->status);
        $this->assertNotNull($syncLog->completed_at);
        $this->assertEquals('API connection failed', $syncLog->error_message);
    }

    public function test_increment_processed_increases_counter(): void
    {
        $syncLog = SyncLog::factory()->create(['processed_products' => 5]);

        $syncLog->incrementProcessed();

        $this->assertEquals(6, $syncLog->fresh()->processed_products);
    }

    public function test_increment_failed_increases_counter_and_logs_error(): void
    {
        $syncLog = SyncLog::factory()->create([
            'failed_products' => 2,
            'metadata' => ['errors' => [['error' => 'Previous error', 'timestamp' => now()->toIso8601String()]]],
        ]);

        $syncLog->incrementFailed('New error');

        $this->assertEquals(3, $syncLog->fresh()->failed_products);
        $this->assertCount(2, $syncLog->fresh()->metadata['errors']);
    }

    public function test_increment_failed_without_error_just_increases_counter(): void
    {
        $syncLog = SyncLog::factory()->create(['failed_products' => 2]);

        $syncLog->incrementFailed();

        $this->assertEquals(3, $syncLog->fresh()->failed_products);
    }
}
