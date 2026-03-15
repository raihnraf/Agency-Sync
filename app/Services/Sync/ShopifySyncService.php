<?php

namespace App\Services\Sync;

use App\Models\Tenant;
use App\Models\SyncLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ShopifySyncService
{
    private ProductValidator $validator;
    private string $accessToken;
    private string $shopDomain;
    private string $apiVersion = '2025-01';
    private float $lastRequestTime = 0.0;
    private float $minRequestInterval = 0.5; // 500ms minimum between requests
    private bool $testingMode = false;
    private ?Tenant $tenant = null;

    public function __construct(ProductValidator $validator, bool $testingMode = false)
    {
        $this->validator = $validator;
        $this->testingMode = $testingMode;
    }

    /**
     * Authenticate with Shopify API.
     */
    public function authenticate(Tenant $tenant): void
    {
        $credentials = $tenant->api_credentials;

        if (empty($credentials['access_token']) || empty($credentials['shop_domain'])) {
            throw new Exception('Missing Shopify API credentials');
        }

        $this->accessToken = $credentials['access_token'];
        $this->shopDomain = $credentials['shop_domain'];
        $this->tenant = $tenant;

        // Validate shop domain format
        if (!str_ends_with($this->shopDomain, '.myshopify.com') && !str_ends_with($this->shopDomain, '.myshopify.io')) {
            throw new Exception('Invalid Shopify shop domain format');
        }
    }

    /**
     * Fetch products from Shopify API.
     */
    public function fetchProducts(Tenant $tenant, SyncLog $syncLog): Collection
    {
        $this->authenticate($tenant);

        $products = collect();
        $pageInfo = null;

        do {
            $this->respectRateLimit();

            $url = "https://{$this->shopDomain}/admin/api/{$this->apiVersion}/products.json";
            $query = ['limit' => 250];

            if ($pageInfo) {
                $query['page_info'] = $pageInfo;
            }

            $response = Http::withToken($this->accessToken)
                ->timeout(30)
                ->retry(3, 100, throw: false)  // Don't throw exceptions automatically
                ->get($url, $query);

            if (!$response->successful()) {
                $errorPayload = [
                    'type' => 'api_error',
                    'source' => 'shopify',
                    'status_code' => $response->status(),
                    'response_body' => $response->json() ?? $response->body(),
                    'request_url' => $url,
                    'request_method' => 'GET',
                    'timestamp' => now()->toIso8601String(),
                ];

                // Capture rate limit headers
                $rateLimitHeader = $response->header('X-Shopify-Shop-Api-Call-Limit');
                if ($rateLimitHeader) {
                    [$used, $limit] = array_pad(explode('/', $rateLimitHeader), 2, null);
                    $errorPayload['rate_limit_info'] = [
                        'used' => $used,
                        'limit' => $limit,
                        'retry_after' => $response->header('Retry-After'),
                    ];
                }

                // Store in sync log metadata
                $currentMetadata = $syncLog->metadata ?? [];
                $syncLog->update(['metadata' => array_merge($currentMetadata, ['error_details' => $errorPayload])]);

                // Log error (existing behavior)
                Log::error('Shopify API error', $errorPayload);

                throw new Exception("Shopify API error: {$response->status()}");
            }

            $data = $response->json();
            $products = $products->merge($data['products'] ?? []);

            // Check rate limit header
            $rateLimitHeader = $response->header('X-Shopify-Shop-Api-Call-Limit');
            if ($rateLimitHeader) {
                $this->handleRateLimit($rateLimitHeader);
            }

            // Parse Link header for pagination
            $linkHeader = $response->header('Link');
            $pageInfo = $this->parsePageInfo($linkHeader);

        } while ($pageInfo);

        $syncLog->update(['total_products' => $products->count()]);

        return $products;
    }

    /**
     * Normalize Shopify product data.
     */
    public function normalizeProduct(array $shopifyProduct): array
    {
        return $this->validator->normalizeShopifyProduct($shopifyProduct);
    }

    /**
     * Respect minimum request interval.
     */
    private function respectRateLimit(): void
    {
        $currentTime = microtime(true);
        $timeSinceLastRequest = $currentTime - $this->lastRequestTime;

        if ($timeSinceLastRequest < $this->minRequestInterval) {
            if (!$this->testingMode) {
                $sleepTime = ($this->minRequestInterval - $timeSinceLastRequest) * 1000000;
                usleep((int) $sleepTime);
            }
        }

        $this->lastRequestTime = microtime(true);
    }

    /**
     * Handle rate limit from response header.
     */
    private function handleRateLimit(string $rateLimitHeader): void
    {
        // Format: "used/limit" e.g., "35/40"
        preg_match('/(\d+)\/(\d+)/', $rateLimitHeader, $matches);

        if (count($matches) === 3) {
            $used = (int) $matches[1];
            $limit = (int) $matches[2];
            $usagePercent = ($used / $limit) * 100;

            // If approaching 80% of limit, slow down
            if ($usagePercent >= 80) {
                $this->minRequestInterval = 1.0; // Increase to 1s
            } else {
                $this->minRequestInterval = 0.5; // Reset to 0.5s
            }
        }
    }

    /**
     * Parse page_info from Link header.
     */
    private function parsePageInfo(?string $linkHeader): ?string
    {
        if (!$linkHeader) {
            return null;
        }

        // Format: <url>; rel="next", <url>; rel="previous"
        preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches);

        if (count($matches) === 2) {
            $url = $matches[1];
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            return $query['page_info'] ?? null;
        }

        return null;
    }
}
