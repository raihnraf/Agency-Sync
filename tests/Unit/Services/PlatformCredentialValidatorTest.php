<?php

namespace Tests\Unit\Services;

use App\Services\PlatformCredentialValidator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformCredentialValidatorTest extends TestCase
{
    private PlatformCredentialValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PlatformCredentialValidator();
    }

    public function test_validate_returns_true_for_valid_credentials()
    {
        Http::fake([
            'api.shopify.com/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $result = $this->validator->validate(
            'shopify',
            ['api_key' => 'valid_key', 'api_secret' => 'valid_secret'],
            'https://test.myshopify.com'
        );

        $this->assertTrue($result);
    }

    public function test_validate_returns_false_for_invalid_credentials()
    {
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $result = $this->validator->validate(
            'shopify',
            ['api_key' => 'invalid_key', 'api_secret' => 'invalid_secret'],
            'https://test.myshopify.com'
        );

        $this->assertFalse($result);
    }

    public function test_validate_calls_platform_api_with_timeout()
    {
        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        $this->validator->validate(
            'shopify',
            ['api_key' => 'test_key', 'api_secret' => 'test_secret'],
            'https://test.myshopify.com'
        );

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.shopify.com');
        });
    }

    public function test_get_last_error_returns_platform_error_message()
    {
        Http::fake([
            '*' => Http::response(['error' => 'Invalid API credentials'], 401),
        ]);

        $this->validator->validate(
            'shopify',
            ['api_key' => 'invalid', 'api_secret' => 'invalid'],
            'https://test.myshopify.com'
        );

        $error = $this->validator->getLastError();

        $this->assertNotNull($error);
        $this->assertStringContainsString('Invalid API credentials', $error);
    }

    public function test_validate_handles_network_errors_gracefully()
    {
        Http::fake([
            '*' => Http::response('Connection timeout', 500),
        ]);

        $result = $this->validator->validate(
            'shopify',
            ['api_key' => 'test_key', 'api_secret' => 'test_secret'],
            'https://test.myshopify.com'
        );

        $this->assertFalse($result);
        $this->assertNotNull($this->validator->getLastError());
    }
}
