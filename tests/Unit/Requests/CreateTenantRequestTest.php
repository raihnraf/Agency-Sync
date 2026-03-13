<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\CreateTenantRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CreateTenantRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new CreateTenantRequest();

        return Validator::make($data, $request->rules());
    }

    public function test_name_is_required()
    {
        $validator = $this->validate([
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_name_must_be_string()
    {
        $validator = $this->validate([
            'name' => 123,
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_name_max_255()
    {
        $validator = $this->validate([
            'name' => str_repeat('a', 256),
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_platform_type_is_required()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform_type', $validator->errors()->toArray());
    }

    public function test_platform_type_must_be_in_shopify_or_shopware()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'invalid',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform_type', $validator->errors()->toArray());
    }

    public function test_platform_url_is_required()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform_url', $validator->errors()->toArray());
    }

    public function test_platform_url_must_be_valid_url()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'not-a-url',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform_url', $validator->errors()->toArray());
    }

    public function test_platform_url_max_500()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.com/' . str_repeat('a', 500),
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('platform_url', $validator->errors()->toArray());
    }

    public function test_api_credentials_is_required()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('api_credentials', $validator->errors()->toArray());
    }

    public function test_api_credentials_must_be_array()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => 'not-an-array',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('api_credentials', $validator->errors()->toArray());
    }

    public function test_api_credentials_api_key_is_required()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('api_credentials.api_key', $validator->errors()->toArray());
    }

    public function test_api_credentials_api_key_must_be_string()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 123,
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('api_credentials.api_key', $validator->errors()->toArray());
    }

    public function test_api_credentials_api_secret_is_required()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('api_credentials.api_secret', $validator->errors()->toArray());
    }

    public function test_api_credentials_api_secret_must_be_string()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 123,
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('api_credentials.api_secret', $validator->errors()->toArray());
    }

    public function test_valid_data_passes_validation()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_settings_is_optional_array()
    {
        $validator = $this->validate([
            'name' => 'Test Tenant',
            'platform_type' => 'shopify',
            'platform_url' => 'https://example.myshopify.com',
            'api_credentials' => [
                'api_key' => 'test_key',
                'api_secret' => 'test_secret',
            ],
            'settings' => ['webhook_enabled' => true],
        ]);

        $this->assertFalse($validator->fails());
    }
}
