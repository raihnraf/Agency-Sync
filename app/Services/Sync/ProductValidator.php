<?php

namespace App\Services\Sync;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

class ProductValidator
{
    /**
     * Validate product data.
     *
     * @param array $productData
     * @return array Validated data
     * @throws ValidationException
     */
    public function validate(array $productData): array
    {
        $validator = Validator::make($productData, [
            'external_id' => 'required|string|max:255',
            'name' => 'required|string|min:1|max:255',
            'description' => 'nullable|string|max:65535',
            'sku' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0',
            'platform' => 'required|in:shopify,shopware',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        // Check for unsafe HTML tags in description before sanitizing
        if (!empty($validated['description'])) {
            $originalDescription = $validated['description'];
            $sanitizedDescription = strip_tags($originalDescription, '<p><br><strong><em><ul><ol><li>');

            // If sanitization changed the content significantly, there were unsafe tags
            // We check if the sanitized version is notably different from original
            if ($sanitizedDescription !== $originalDescription) {
                // Calculate the difference in length
                $lengthDiff = strlen($originalDescription) - strlen($sanitizedDescription);

                // If more than 10 characters were removed, likely unsafe tags
                if ($lengthDiff > 10) {
                    throw ValidationException::withMessages([
                        'description' => ['Description contains unsafe HTML tags. Only <p>, <br>, <strong>, <em>, <ul>, <ol>, <li> tags are allowed.'],
                    ]);
                }
            }

            $validated['description'] = $sanitizedDescription;
        }

        return $validated;
    }

    /**
     * Normalize Shopify product data to standard format.
     *
     * @param array $shopifyProduct
     * @return array
     */
    public function normalizeShopifyProduct(array $shopifyProduct): array
    {
        $variant = $shopifyProduct['variants'][0] ?? [];

        return [
            'external_id' => $shopifyProduct['id'] ?? '',
            'name' => $shopifyProduct['title'] ?? '',
            'description' => $shopifyProduct['body_html'] ?? null,
            'sku' => $variant['sku'] ?? null,
            'price' => isset($variant['price']) ? (float) $variant['price'] : 0.0,
            'stock' => isset($variant['inventory_quantity']) ? (int) $variant['inventory_quantity'] : 0,
            'platform' => 'shopify',
        ];
    }

    /**
     * Normalize Shopware product data to standard format.
     *
     * @param array $shopwareProduct
     * @return array
     */
    public function normalizeShopwareProduct(array $shopwareProduct): array
    {
        $priceData = $shopwareProduct['price'][0] ?? [];
        $grossPrice = $priceData['gross'] ?? 0.0;

        return [
            'external_id' => $shopwareProduct['id'] ?? '',
            'name' => $shopwareProduct['name'] ?? '',
            'description' => $shopwareProduct['description'] ?? null,
            'sku' => $shopwareProduct['productNumber'] ?? null,
            'price' => is_numeric($grossPrice) ? (float) $grossPrice : 0.0,
            'stock' => isset($shopwareProduct['stock']) ? (int) $shopwareProduct['stock'] : 0,
            'platform' => 'shopware',
        ];
    }
}
