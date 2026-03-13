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
        $this->service = new ShopifySyncService($this->validator);
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
        $this->assertEquals('shopify_123', $products->first()['external_id']);
        $this->assertEquals('Test Product', $products->first()['name']);
    }

    public function test_fetch_products_handles_pagination(): void
    {
        Http::fake([
            'test.myshopify.com/admin/api/2025-01/products.json*' => Http::response([
                'products' => [
                    ['id' => 'prod1', 'title' => 'Product 1', 'body_html' => '', 'variants' => [['sku' => 'SKU1', 'price' => '10.00', 'inventory_quantity' => 5]]],
                ],
            ], 200, ['Link' => '<https://test.myshopify.com/admin/api/2025-01/products.json?page_info=next>; rel="next"']),
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

        $this->assertGreaterThanOrEqual(1, $products->count());
    }

    public function test_service_respects_rate_limits(): void
    {
        $startTime = microtime(true);

        Http::fake([
            'test.myshopify.com/admin/api/2025-01/products.json*' => Http::response([
                'products' => [['id' => 'prod1', 'title' => 'P1', 'body_html' => '', 'variants' => [['sku' => 'S1', 'price' => '10', 'inventory_quantity' => 1]]]],
            ], 200, ['X-Shopify-Shop-Api-Call-Limit' => '1/40']),
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

        $elapsed = microtime(true) - $startTime;
        $this->assertGreaterThanOrEqual(0.5, $elapsed); // At least 0.5s delay
    }

    public function test_service_slows_down_when_approaching_rate_limit(): void
    {
        $startTime = microtime(true);

        Http::fake([
            'test.myshopify.com/admin/api/2025-01/products.json*' => Http::response([
                'products' => [['id' => 'prod1', 'title' => 'P1', 'body_html' => '', 'variants' => [['sku' => 'S1', 'price' => '10', 'inventory_quantity' => 1]]]],
            ], 200, ['X-Shopify-Shop-Api-Call-Limit' => '35/40']), // 87.5% used
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

        $elapsed = microtime(true) - $startTime;
        $this->assertGreaterThanOrEqual(1.0, $elapsed); // At least 1s delay when approaching limit
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
}
