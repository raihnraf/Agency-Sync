<?php

namespace App\Services\Sync;

use App\Models\Tenant;
use App\Models\SyncLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ShopwareSyncService
{
    private ProductValidator $validator;
    private string $accessToken;
    private string $shopUrl;
    private float $lastRequestTime = 0.0;
    private float $minRequestInterval = 0.3; // 300ms minimum between requests
    private bool $testingMode = false;

    public function __construct(ProductValidator $validator, bool $testingMode = false)
    {
        $this->validator = $validator;
        $this->testingMode = $testingMode;
    }

    /**
     * Authenticate with Shopware API.
     */
    public function authenticate(Tenant $tenant): string
    {
        $credentials = $tenant->api_credentials;

        if (empty($credentials['client_id']) || empty($credentials['client_secret']) || empty($credentials['shop_url'])) {
            throw new Exception('Missing Shopware API credentials');
        }

        $this->shopUrl = rtrim($credentials['shop_url'], '/');

        $response = Http::asForm()->post("{$this->shopUrl}/api/oauth/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
        ]);

        if (!$response->successful()) {
            Log::error('Shopware authentication error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'tenant_id' => $tenant->id,
            ]);
            throw new Exception("Shopware authentication failed: {$response->status()}");
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'];

        return $this->accessToken;
    }

    /**
     * Fetch products from Shopware API.
     */
    public function fetchProducts(Tenant $tenant, SyncLog $syncLog): Collection
    {
        $this->authenticate($tenant);

        $products = collect();
        $offset = 0;
        $limit = 500;
        $total = 0;

        do {
            $this->respectRateLimit();

            $response = Http::withToken($this->accessToken)
                ->timeout(30)
                ->retry(3, 100)
                ->get("{$this->shopUrl}/api/product", [
                    'limit' => $limit,
                    'offset' => $offset,
                ]);

            if (!$response->successful()) {
                Log::error('Shopware API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'tenant_id' => $tenant->id,
                ]);
                throw new Exception("Shopware API error: {$response->status()}");
            }

            $data = $response->json();

            // Shopware returns products as an associative array keyed by ID
            $productsArray = $data['data'] ?? [];
            $products = $products->merge(array_values($productsArray));

            $total = $data['total'] ?? 0;
            $offset += $limit;

        } while ($offset < $total);

        $syncLog->update(['total_products' => $products->count()]);

        return $products;
    }

    /**
     * Normalize Shopware product data.
     */
    public function normalizeProduct(array $shopwareProduct): array
    {
        return $this->validator->normalizeShopwareProduct($shopwareProduct);
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
}
