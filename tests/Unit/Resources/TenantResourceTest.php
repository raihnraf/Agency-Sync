<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Enums\PlatformType;
use App\Enums\TenantStatus;
use Tests\TestCase;

class TenantResourceTest extends TestCase
{
    public function test_tenant_resource_includes_all_fields_except_api_credentials()
    {
        $tenant = Tenant::factory()->make([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
            'status' => TenantStatus::ACTIVE,
            'settings' => ['webhook_enabled' => true],
            'last_sync_at' => now(),
            'sync_status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
            'api_credentials' => [
                'api_key' => 'secret_key',
                'api_secret' => 'secret_value',
            ],
        ]);

        $resource = TenantResource::make($tenant);
        $array = $resource->toArray(request());

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('platform_type', $array);
        $this->assertArrayHasKey('platform_url', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('settings', $array);
        $this->assertArrayHasKey('last_sync_at', $array);
        $this->assertArrayHasKey('sync_status', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
        $this->assertArrayNotHasKey('api_credentials', $array);
    }

    public function test_tenant_resource_transforms_enums_to_string_values()
    {
        $tenant = Tenant::factory()->make([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
            'status' => TenantStatus::ACTIVE,
            'settings' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resource = TenantResource::make($tenant);
        $array = $resource->toArray(request());

        $this->assertIsString($array['platform_type']);
        $this->assertEquals('shopify', $array['platform_type']);

        $this->assertIsString($array['status']);
        $this->assertEquals('active', $array['status']);
    }

    public function test_tenant_resource_formats_timestamps_to_iso8601()
    {
        $now = now();
        $tenant = Tenant::factory()->make([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
            'status' => TenantStatus::ACTIVE,
            'settings' => [],
            'last_sync_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $resource = TenantResource::make($tenant);
        $array = $resource->toArray(request());

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['last_sync_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['created_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['updated_at']);
    }

    public function test_tenant_resource_handles_null_last_sync_at()
    {
        $tenant = Tenant::factory()->make([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
            'status' => TenantStatus::ACTIVE,
            'settings' => [],
            'last_sync_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resource = TenantResource::make($tenant);
        $array = $resource->toArray(request());

        $this->assertNull($array['last_sync_at']);
    }

    public function test_tenant_resource_has_no_data_wrapper()
    {
        $tenant = Tenant::factory()->make([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
            'status' => TenantStatus::ACTIVE,
            'settings' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resource = TenantResource::make($tenant);
        $array = $resource->toArray(request());

        $this->assertArrayNotHasKey('data', $array);
    }
}
