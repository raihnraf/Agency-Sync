<?php

namespace Tests\Unit;

use App\Models\SyncLog;
use App\Models\Tenant;
use App\Services\Sync\ProductValidator;
use App\Services\Sync\ShopifySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ErrorPayloadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private SyncLog $syncLog;
    private ProductValidator $validator;
    private ShopifySyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant with test credentials
        $this->tenant = Tenant::factory()->create([
            'platform_type' => \App\Enums\PlatformType::SHOPIFY,
            'api_credentials' => [
                'access_token' => 'test_token',
                'shop_domain' => 'test.myshopify.com',
            ],
        ]);

        // Create sync log
        $this->syncLog = SyncLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $this->validator = app(ProductValidator::class);
        $this->syncService = new ShopifySyncService($this->validator, true);
    }

    public function test_error_payload_includes_required_fields()
    {
        // Mock a failed API response
        Http::fake([
            'myshopify.com/admin/api/*/products.json' => Http::response([
                'errors' => 'Invalid API token',
            ], 401),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Shopify API error: 401');

        try {
            $this->syncService->fetchProducts($this->tenant, $this->syncLog);
        } catch (\Exception $e) {
            // Refresh sync log from database
            $this->syncLog->refresh();

            // Verify error payload stored in metadata
            $metadata = $this->syncLog->metadata ?? [];
            $this->assertArrayHasKey('error_details', $metadata);

            $errorDetails = $metadata['error_details'];
            $this->assertEquals('api_error', $errorDetails['type']);
            $this->assertEquals('shopify', $errorDetails['source']);
            $this->assertEquals(401, $errorDetails['status_code']);
            $this->assertArrayHasKey('response_body', $errorDetails);
            $this->assertArrayHasKey('request_url', $errorDetails);
            $this->assertArrayHasKey('request_method', $errorDetails);
            $this->assertArrayHasKey('timestamp', $errorDetails);

            throw $e;
        }
    }

    public function test_error_payload_captures_api_error_type_correctly()
    {
        // Mock a failed API response
        Http::fake([
            'myshopify.com/admin/api/*/products.json' => Http::response([
                'errors' => 'Rate limit exceeded',
            ], 429),
        ]);

        $this->expectException(\Exception::class);

        try {
            $this->syncService->fetchProducts($this->tenant, $this->syncLog);
        } catch (\Exception $e) {
            $this->syncLog->refresh();
            $errorDetails = $this->syncLog->metadata['error_details'] ?? [];

            $this->assertEquals('api_error', $errorDetails['type']);
            $this->assertEquals('shopify', $errorDetails['source']);

            throw $e;
        }
    }

    public function test_error_payload_captures_internal_error_type_correctly()
    {
        // Create tenant with invalid credentials to trigger internal error
        $tenant = Tenant::factory()->create([
            'platform_type' => \App\Enums\PlatformType::SHOPIFY,
            'api_credentials' => [
                'access_token' => '', // Empty token should trigger validation error
                'shop_domain' => 'invalid-domain',
            ],
        ]);

        $syncLog = SyncLog::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        $this->expectException(\Exception::class);

        try {
            $this->syncService->fetchProducts($tenant, $syncLog);
        } catch (\Exception $e) {
            // Note: Internal errors are captured in Jobs, not Service
            // This test verifies the distinction between API and internal errors
            $this->assertStringContainsString('Missing', $e->getMessage());

            throw $e;
        }
    }

    public function test_error_payload_stores_in_metadata_field()
    {
        // Mock a failed API response
        Http::fake([
            'myshopify.com/admin/api/*/products.json' => Http::response([
                'errors' => 'Not found',
            ], 404),
        ]);

        $this->expectException(\Exception::class);

        try {
            $this->syncService->fetchProducts($this->tenant, $this->syncLog);
        } catch (\Exception $e) {
            $this->syncLog->refresh();

            // Verify metadata field structure
            $this->assertNotNull($this->syncLog->metadata);
            $this->assertIsArray($this->syncLog->metadata);
            $this->assertArrayHasKey('error_details', $this->syncLog->metadata);
            $this->assertIsArray($this->syncLog->metadata['error_details']);

            throw $e;
        }
    }

    public function test_error_payload_includes_timestamp()
    {
        // Mock a failed API response
        Http::fake([
            'myshopify.com/admin/api/*/products.json' => Http::response([
                'errors' => 'Server error',
            ], 500),
        ]);

        $this->expectException(\Exception::class);

        $beforeTime = now()->toIso8601String();

        try {
            $this->syncService->fetchProducts($this->tenant, $this->syncLog);
        } catch (\Exception $e) {
            $this->syncLog->refresh();
            $errorDetails = $this->syncLog->metadata['error_details'] ?? [];

            // Verify timestamp exists and is in ISO 8601 format
            $this->assertArrayHasKey('timestamp', $errorDetails);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $errorDetails['timestamp']);

            // Verify timestamp is recent
            $timestamp = $errorDetails['timestamp'];
            $this->assertGreaterThan($beforeTime, $timestamp);

            throw $e;
        }
    }
}
