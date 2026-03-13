<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Tenant;
use App\Enums\PlatformType;
use App\Enums\TenantStatus;

class TenantEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_credentials_are_encrypted_in_database(): void
    {
        $credentials = ['api_key' => 'secret123', 'api_secret' => 'topsecret'];
        $tenant = Tenant::factory()->create([
            'api_credentials' => $credentials,
            'name' => 'Test Store',
            'slug' => 'test-store',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
        ]);

        // Raw database query should show encrypted data
        $rawData = \DB::table('tenants')->where('id', $tenant->id)->first();
        $this->assertNotSame(json_encode($credentials), $rawData->api_credentials);
        $this->assertNotEmpty($rawData->api_credentials);
    }

    public function test_credentials_are_decrypted_when_accessed_via_model(): void
    {
        $credentials = ['api_key' => 'secret123', 'api_secret' => 'topsecret'];
        $tenant = Tenant::factory()->create([
            'api_credentials' => $credentials,
            'name' => 'Test Store',
            'slug' => 'test-store',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
        ]);

        $this->assertEquals($credentials, $tenant->api_credentials);
        $this->assertEquals('secret123', $tenant->api_credentials['api_key']);
        $this->assertEquals('topsecret', $tenant->api_credentials['api_secret']);
    }

    public function test_api_credentials_are_hidden_from_json(): void
    {
        $credentials = ['api_key' => 'secret123'];
        $tenant = Tenant::factory()->create([
            'api_credentials' => $credentials,
            'name' => 'Test Store',
            'slug' => 'test-store',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
        ]);

        $json = json_encode($tenant->toArray());
        $this->assertStringNotContainsString('secret123', $json);
        $this->assertArrayNotHasKey('api_credentials', $tenant->toArray());
    }

    public function test_platform_type_cast_to_enum(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
            'api_credentials' => ['api_key' => 'test'],
        ]);

        $this->assertInstanceOf(PlatformType::class, $tenant->platform_type);
        $this->assertEquals(PlatformType::SHOPIFY, $tenant->platform_type);
    }

    public function test_status_cast_to_enum(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Store',
            'slug' => 'test-store',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
            'status' => TenantStatus::ACTIVE,
            'api_credentials' => ['api_key' => 'test'],
        ]);

        $this->assertInstanceOf(TenantStatus::class, $tenant->status);
        $this->assertEquals(TenantStatus::ACTIVE, $tenant->status);
    }

    public function test_slug_auto_generated_from_name(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'My Awesome Store',
            'slug' => null,
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
            'api_credentials' => ['api_key' => 'test'],
        ]);

        $this->assertEquals('my-awesome-store', $tenant->slug);
    }

    public function test_slug_not_overridden_if_provided(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'My Awesome Store',
            'slug' => 'custom-slug',
            'platform_type' => PlatformType::SHOPIFY,
            'platform_url' => 'https://test.myshopify.com',
            'api_credentials' => ['api_key' => 'test'],
        ]);

        $this->assertEquals('custom-slug', $tenant->slug);
    }
}
