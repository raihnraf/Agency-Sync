<?php

namespace Tests\Unit\Sync;

use Tests\TestCase;
use App\Services\Sync\ProductValidator;
use Illuminate\Validation\ValidationException;

class ProductValidatorTest extends TestCase
{
    public function test_valid_product_data_passes_validation(): void
    {
        $validator = new ProductValidator();
        $productData = [
            'external_id' => 'shop123',
            'name' => 'Test Product',
            'description' => '<p>A great product</p>',
            'sku' => 'SKU123',
            'price' => 29.99,
            'stock' => 100,
            'platform' => 'shopify',
        ];

        $result = $validator->validate($productData);

        $this->assertEquals('shop123', $result['external_id']);
        $this->assertEquals('Test Product', $result['name']);
        $this->assertEquals(29.99, $result['price']);
        $this->assertEquals(100, $result['stock']);
    }

    public function test_missing_required_fields_fail_validation(): void
    {
        $validator = new ProductValidator();

        $this->expectException(ValidationException::class);
        $validator->validate([
            'external_id' => 'shop123',
            // missing name
            'price' => 29.99,
            'stock' => 100,
            'platform' => 'shopify',
        ]);
    }

    public function test_invalid_data_types_fail_validation(): void
    {
        $validator = new ProductValidator();

        $this->expectException(ValidationException::class);
        $validator->validate([
            'external_id' => 'shop123',
            'name' => 'Test Product',
            'description' => '<p>A great product</p>',
            'sku' => 'SKU123',
            'price' => 'not_a_number',  // Invalid: should be numeric
            'stock' => 'not_an_int',     // Invalid: should be integer
            'platform' => 'shopify',
        ]);
    }

    public function test_description_with_unsafe_html_tags_fails(): void
    {
        $validator = new ProductValidator();

        $this->expectException(ValidationException::class);
        $validator->validate([
            'external_id' => 'shop123',
            'name' => 'Test Product',
            'description' => '<script>alert("xss")</script>',  // Unsafe HTML
            'sku' => 'SKU123',
            'price' => 29.99,
            'stock' => 100,
            'platform' => 'shopify',
        ]);
    }

    public function test_description_with_safe_html_tags_passes(): void
    {
        $validator = new ProductValidator();
        $productData = [
            'external_id' => 'shop123',
            'name' => 'Test Product',
            'description' => '<p>Great product with <strong>bold</strong> and <em>italic</em> text</p>',
            'sku' => 'SKU123',
            'price' => 29.99,
            'stock' => 100,
            'platform' => 'shopify',
        ];

        $result = $validator->validate($productData);

        $this->assertStringContainsString('<p>', $result['description']);
        $this->assertStringContainsString('<strong>', $result['description']);
        $this->assertStringContainsString('<em>', $result['description']);
    }

    public function test_platform_must_be_valid(): void
    {
        $validator = new ProductValidator();

        $this->expectException(ValidationException::class);
        $validator->validate([
            'external_id' => 'shop123',
            'name' => 'Test Product',
            'description' => '<p>A great product</p>',
            'sku' => 'SKU123',
            'price' => 29.99,
            'stock' => 100,
            'platform' => 'invalid_platform',  // Invalid platform
        ]);
    }

    public function test_price_cannot_be_negative(): void
    {
        $validator = new ProductValidator();

        $this->expectException(ValidationException::class);
        $validator->validate([
            'external_id' => 'shop123',
            'name' => 'Test Product',
            'description' => '<p>A great product</p>',
            'sku' => 'SKU123',
            'price' => -10.00,  // Negative price
            'stock' => 100,
            'platform' => 'shopify',
        ]);
    }

    public function test_stock_cannot_be_negative(): void
    {
        $validator = new ProductValidator();

        $this->expectException(ValidationException::class);
        $validator->validate([
            'external_id' => 'shop123',
            'name' => 'Test Product',
            'description' => '<p>A great product</p>',
            'sku' => 'SKU123',
            'price' => 29.99,
            'stock' => -5,  // Negative stock
            'platform' => 'shopify',
        ]);
    }

    public function test_normalize_shopify_product(): void
    {
        $validator = new ProductValidator();
        $shopifyProduct = [
            'id' => 'shopify_123',
            'title' => 'Shopify Product',
            'body_html' => '<p>Great product</p>',
            'variants' => [
                [
                    'sku' => 'SKU123',
                    'price' => '29.99',
                    'inventory_quantity' => 100,
                ],
            ],
        ];

        $result = $validator->normalizeShopifyProduct($shopifyProduct);

        $this->assertEquals('shopify_123', $result['external_id']);
        $this->assertEquals('Shopify Product', $result['name']);
        $this->assertEquals('SKU123', $result['sku']);
        $this->assertEquals(29.99, $result['price']);
        $this->assertEquals(100, $result['stock']);
        $this->assertEquals('shopify', $result['platform']);
    }

    public function test_normalize_shopware_product(): void
    {
        $validator = new ProductValidator();
        $shopwareProduct = [
            'id' => 'shopware_456',
            'name' => 'Shopware Product',
            'description' => '<p>Another great product</p>',
            'stock' => 50,
            'price' => [
                ['gross' => 19.99],  // Shopware price structure
            ],
        ];

        $result = $validator->normalizeShopwareProduct($shopwareProduct);

        $this->assertEquals('shopware_456', $result['external_id']);
        $this->assertEquals('Shopware Product', $result['name']);
        $this->assertEquals(19.99, $result['price']);
        $this->assertEquals(50, $result['stock']);
        $this->assertEquals('shopware', $result['platform']);
    }
}
