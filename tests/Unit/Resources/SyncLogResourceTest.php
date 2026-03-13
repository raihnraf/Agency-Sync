<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use Tests\TestCase;
use App\Http\Resources\SyncLogResource;
use App\Models\SyncLog;
use App\Enums\PlatformType;
use App\Enums\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_transforms_sync_log_to_array()
    {
        $syncLog = SyncLog::factory()->create([
            'platform_type' => PlatformType::SHOPIFY,
            'status' => SyncStatus::COMPLETED,
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('platform_type', $result);
        $this->assertArrayHasKey('status', $result);
    }

    public function test_resource_includes_basic_fields()
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $syncLog = SyncLog::factory()->for($tenant)->create([
            'platform_type' => PlatformType::SHOPIFY,
            'status' => SyncStatus::RUNNING,
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertEquals($syncLog->id, $result['id']);
        $this->assertEquals($tenant->id, $result['tenant_id']);
        $this->assertEquals('shopify', $result['platform_type']);
        $this->assertEquals('running', $result['status']);
    }

    public function test_resource_includes_timestamps()
    {
        $syncLog = SyncLog::factory()->create([
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('started_at', $result);
        $this->assertArrayHasKey('completed_at', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);

        $this->assertNotNull($result['started_at']);
        $this->assertNotNull($result['completed_at']);
    }

    public function test_resource_includes_counters()
    {
        $syncLog = SyncLog::factory()->create([
            'total_products' => 1000,
            'processed_products' => 950,
            'failed_products' => 50,
            'indexed_products' => 900,
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertEquals(1000, $result['total_products']);
        $this->assertEquals(950, $result['processed_products']);
        $this->assertEquals(50, $result['failed_products']);
        $this->assertEquals(900, $result['indexed_products']);
    }

    public function test_resource_includes_error_message_if_present()
    {
        $syncLog = SyncLog::factory()->create([
            'error_message' => 'API rate limit exceeded',
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertEquals('API rate limit exceeded', $result['error_message']);
    }

    public function test_resource_includes_metadata_if_present()
    {
        $metadata = [
            'api_version' => '2025-01',
            'last_page' => 5,
            'errors' => [
                ['error' => 'Product 123 invalid', 'timestamp' => '2026-03-13T10:00:00Z'],
            ],
        ];

        $syncLog = SyncLog::factory()->create([
            'metadata' => $metadata,
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertIsArray($result['metadata']);
        $this->assertEquals('2025-01', $result['metadata']['api_version']);
        $this->assertEquals(5, $result['metadata']['last_page']);
        $this->assertCount(1, $result['metadata']['errors']);
    }

    public function test_resource_excludes_sensitive_credentials()
    {
        $syncLog = SyncLog::factory()->create();

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        // Ensure tenant credentials are not exposed
        $this->assertArrayNotHasKey('api_credentials', $result);
        $this->assertArrayNotHasKey('credentials', $result);
    }

    public function test_resource_converts_enums_to_strings()
    {
        $syncLog = SyncLog::factory()->create([
            'platform_type' => PlatformType::SHOPIWARE,
            'status' => SyncStatus::PARTIALLY_FAILED,
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertIsString($result['platform_type']);
        $this->assertEquals('shopware', $result['platform_type']);

        $this->assertIsString($result['status']);
        $this->assertEquals('partially_failed', $result['status']);
    }

    public function test_resource_calculates_duration()
    {
        $syncLog = SyncLog::factory()->create([
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('duration', $result);
        $this->assertIsInt($result['duration']);
        $this->assertGreaterThan(0, $result['duration']);
    }

    public function test_resource_calculates_progress_percentage()
    {
        $syncLog = SyncLog::factory()->create([
            'total_products' => 1000,
            'processed_products' => 750,
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertArrayHasKey('progress_percentage', $result);
        $this->assertEquals(75.0, $result['progress_percentage']);
    }

    public function test_resource_handles_null_started_at_for_duration()
    {
        $syncLog = SyncLog::factory()->create([
            'started_at' => null,
            'completed_at' => null,
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertNull($result['duration']);
    }

    public function test_resource_handles_zero_total_for_progress()
    {
        $syncLog = SyncLog::factory()->create([
            'total_products' => 0,
            'processed_products' => 0,
        ]);

        $resource = SyncLogResource::make($syncLog);
        $result = $resource->toArray(request());

        $this->assertNull($result['progress_percentage']);
    }
}
