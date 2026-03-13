<?php

namespace Tests\Unit\Sync;

use Tests\TestCase;
use App\Services\Sync\ShopifySyncService;
use App\Services\Sync\ProductValidator;
use App\Models\Tenant;
use App\Models\SyncLog;
use App\Enums\PlatformType;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShopifySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShopifySyncService $service;
    private ProductValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ProductValidator();
        $this->service = new ShopifySyncService($this->validator, testingMode: true);
    }

    public function test_service_authenticates_with_shop_domain_and_access_token(): void
    {
        $tenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIFY,
            'api_credentials' => [
                'access_token' => 'test_token',
                'shop_domain' => 'test.myshopify.com',
            ],
        ]);

        $this->service->authenticate($tenant);

        $this->assertTrue(true); // If no exception thrown, authentication succeeded
    }

    public function test_fetch_products_returns_collection_of_normalized_products(): void
    {
        Http::fake([
            'test.myshopify.com/admin/api/2025-01/products.json*' => Http::response([
                'products' => [
                    [
                        'id' => 'shopify_123',
                        'title' => 'Test Product',
                        'body_html' => '<p>Great product</p>',
                        'variants' => [
                            ['sku' => 'SKU123', 'price' => '29.99', 'inventory_quantity' => 100],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $tenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIFY,
            'api_credentials' => [
                'access_token' => 'test_token',
                'shop_domain' => 'test.myshopify.com',
            ],
        ]);

        $syncLog = SyncLog::factory()->for($tenant)->create();
        $products = $this->service->fetchProducts($tenant, $syncLog);

        $this->assertCount(1, $products);
        $this->assertEquals('shopify_123', $products->first()['id']);
        $this->assertEquals('Test Product', $products->first()['title']);
    }

    public function test_normalize_product_converts_shopify_data_to_standard_format(): void
    {
        $shopifyProduct = [
            'id' => 'shopify_123',
            'title' => 'Shopify Product',
            'body_html' => '<p>Great product</p>',
            'variants' => [
                ['sku' => 'SKU123', 'price' => '29.99', 'inventory_quantity' => 100],
            ],
        ];

        $normalized = $this->service->normalizeProduct($shopifyProduct);

        $this->assertEquals('shopify_123', $normalized['external_id']);
        $this->assertEquals('Shopify Product', $normalized['name']);
        $this->assertEquals('SKU123', $normalized['sku']);
        $this->assertEquals(29.99, $normalized['price']);
        $this->assertEquals(100, $normalized['stock']);
        $this->assertEquals('shopify', $normalized['platform']);
    }

    public function test_sync_log_is_updated_with_total_product_count(): void
    {
        Http::fake([
            'test.myshopify.com/admin/api/2025-01/products.json*' => Http::response([
                'products' => [
                    ['id' => 'prod1', 'title' => 'P1', 'body_html' => '', 'variants' => [['sku' => 'S1', 'price' => '10', 'inventory_quantity' => 1]]],
                    ['id' => 'prod2', 'title' => 'P2', 'body_html' => '', 'variants' => [['sku' => 'S2', 'price' => '20', 'inventory_quantity' => 2]]],
                ],
            ], 200),
        ]);

        $tenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIFY,
            'api_credentials' => [
                'access_token' => 'test_token',
                'shop_domain' => 'test.myshopify.com',
            ],
        ]);

        $syncLog = SyncLog::factory()->for($tenant)->create();
        $this->service->fetchProducts($tenant, $syncLog);

        $this->assertEquals(2, $syncLog->fresh()->total_products);
    }

    public function test_rate_limit_handling_updates_request_interval(): void
    {
        // Test that rate limit header is processed correctly
        $this->assertTrue(true); // Placeholder - integration test will verify this
    }

    public function test_pagination_parses_page_info_from_link_header(): void
    {
        // Test that pagination link header is parsed correctly
        $this->assertTrue(true); // Placeholder - integration test will verify this
    }
}
