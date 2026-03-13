<?php

namespace Tests\Unit\Sync;

use Tests\TestCase;
use App\Services\Sync\ShopwareSyncService;
use App\Services\Sync\ProductValidator;
use App\Models\Tenant;
use App\Models\SyncLog;
use App\Enums\PlatformType;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShopwareSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShopwareSyncService $service;
    private ProductValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ProductValidator();
        $this->service = new ShopwareSyncService($this->validator, testingMode: true);
    }

    public function test_service_authenticates_with_client_credentials(): void
    {
        Http::fake([
            'test.shopware.com/api/oauth/token' => Http::response([
                'access_token' => 'test_access_token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $tenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIWARE,
            'api_credentials' => [
                'client_id' => 'test_id',
                'client_secret' => 'test_secret',
                'shop_url' => 'https://test.shopware.com',
            ],
        ]);

        $token = $this->service->authenticate($tenant);

        $this->assertEquals('test_access_token', $token);
    }

    public function test_fetch_products_returns_collection_of_normalized_products(): void
    {
        Http::fake([
            'test.shopware.com/api/oauth/token' => Http::response(['access_token' => 'test_token'], 200),
            'test.shopware.com/api/product*' => Http::response([
                'data' => [
                    '8a7b...001' => [
                        'id' => '8a7b...001',
                        'name' => 'Test Product',
                        'description' => '<p>Great product</p>',
                        'productNumber' => 'SKU123',
                        'stock' => 100,
                        'price' => [['gross' => 29.99]],
                    ],
                ],
                'total' => 1,
            ], 200),
        ]);

        $tenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIWARE,
            'api_credentials' => [
                'client_id' => 'test_id',
                'client_secret' => 'test_secret',
                'shop_url' => 'https://test.shopware.com',
            ],
        ]);

        $syncLog = SyncLog::factory()->for($tenant)->create();
        $products = $this->service->fetchProducts($tenant, $syncLog);

        $this->assertCount(1, $products);
    }

    public function test_normalize_product_converts_shopware_data_to_standard_format(): void
    {
        $shopwareProduct = [
            'id' => 'shopware_456',
            'name' => 'Shopware Product',
            'description' => '<p>Another great product</p>',
            'stock' => 50,
            'price' => [['gross' => 19.99]],
        ];

        $normalized = $this->service->normalizeProduct($shopwareProduct);

        $this->assertEquals('shopware_456', $normalized['external_id']);
        $this->assertEquals('Shopware Product', $normalized['name']);
        $this->assertEquals(19.99, $normalized['price']);
        $this->assertEquals(50, $normalized['stock']);
        $this->assertEquals('shopware', $normalized['platform']);
    }

    public function test_sync_log_is_updated_with_total_product_count(): void
    {
        Http::fake([
            'test.shopware.com/api/oauth/token' => Http::response(['access_token' => 'test_token'], 200),
            'test.shopware.com/api/product*' => Http::response([
                'data' => [
                    'prod1' => ['id' => 'prod1', 'name' => 'P1', 'stock' => 1, 'price' => [['gross' => 10]]],
                    'prod2' => ['id' => 'prod2', 'name' => 'P2', 'stock' => 2, 'price' => [['gross' => 20]]],
                ],
                'total' => 2,
            ], 200),
        ]);

        $tenant = Tenant::factory()->create([
            'platform_type' => PlatformType::SHOPIWARE,
            'api_credentials' => [
                'client_id' => 'test_id',
                'client_secret' => 'test_secret',
                'shop_url' => 'https://test.shopware.com',
            ],
        ]);

        $syncLog = SyncLog::factory()->for($tenant)->create();
        $this->service->fetchProducts($tenant, $syncLog);

        $this->assertEquals(2, $syncLog->fresh()->total_products);
    }
}
