# Phase 6: Catalog Synchronization - Research

**Researched:** 2026-03-13
**Domain:** Shopify & Shopware API Integration with Laravel 11
**Confidence:** MEDIUM (Limited by web search rate limits - verified with Composer package database)

## Summary

Phase 6 implements catalog synchronization from Shopify and Shopware platforms into AgencySync's multi-tenant system. The phase requires integrating external e-commerce APIs, implementing robust sync workflows with error handling, and maintaining tenant isolation throughout the synchronization process.

**Primary recommendation:** Use official PHP SDKs where available (shopify/shopify-api for Shopify, vin-sw/shopware-sdk for Shopware), implement all external API calls as queued jobs with exponential backoff retry logic, and store encrypted API credentials per tenant. Use Laravel's HTTP client as fallback for missing SDK features. Implement idempotent sync operations with proper validation and comprehensive logging.

**Key findings:**
- Shopify offers official `shopify/shopify-api` PHP package (Composer verified)
- Shopware has community SDK `vin-sw/shopware-sdk` for API integration
- Both platforms use OAuth 2.0 with access tokens for authentication
- Rate limiting requires careful implementation (Shopify: leaky bucket, Shopware: ~100-300 req/min)
- Multi-tenant sync must preserve tenant context in queued jobs
- Laravel 11's HTTP client provides excellent fallback for API integration

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SYNC-01 | Agency admin can trigger manual catalog sync for a specific client store | API endpoint triggering queued jobs, covered by Laravel queue patterns |
| SYNC-03 | System validates product data before storing (required fields, data types) | Form Request validation + custom rules for external data |
| SYNC-05 | System logs all sync operations (start time, end time, status, error messages) | JobStatus model + database logging + queue event listeners |
| SYNC-06 | Agency admin can view sync status for each client store (pending, running, completed, failed) | JobStatus tracking with status enum, API endpoint for status queries |
| SYNC-07 | System fetches product data from Shopify API (products, variants, inventory) | Shopify REST Admin API endpoints with pagination |
| SYNC-08 | System fetches product data from Shopware API (products, variants, inventory) | Shopware 6 REST API endpoints with pagination |
| SYNC-09 | System stores product data in MySQL with tenant_id association | Multi-tenant database schema with global scopes |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Framework | 11.31+ | Application framework | Already installed, native queue system, HTTP client |
| Laravel HTTP Client | Built-in | External API calls | Clean syntax, retry logic, timeout handling, multipart support |
| Laravel Queues | Built-in | Async sync operations | Redis driver, job chaining, batching, retry with backoff |

### External API Integration
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| shopify/shopify-api | Latest (Composer verified) | Official Shopify PHP SDK | Primary choice for Shopify API integration |
| vin-sw/shopware-sdk | Latest (Composer verified) | Community Shopware 6 SDK | Primary choice for Shopware API integration |
| guzzlehttp/guzzle | ^7.0 | HTTP client (fallback) | When SDKs lack features or for simple integrations |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| spatie/laravel-validation-exceptions | ^2.0 | Validation error formatting | Consistent API error responses |
| spatie/laravel-activitylog | ^4.0 | Audit logging | Track all sync operations with tenant context |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| shopify/shopify-api | phiclassic/php-shopify | Older package, less maintained - use official SDK |
| vin-sw/shopware-sdk | Direct Guzzle calls | More control but more code - SDK provides abstractions |
| Laravel HTTP Client | Guzzle directly | HTTP client is wrapper around Guzzle with Laravel-specific features |

**Installation:**
```bash
# Shopify API (official package)
composer require shopify/shopify-api

# Shopware SDK (community package)
composer require vin-sw/shopware-sdk

# Activity logging for sync operations
composer require spatie/laravel-activitylog

# Validation exception handling
composer require spatie/laravel-validation-exceptions
```

## Architecture Patterns

### Recommended Project Structure
```
app/
├── Services/
│   ├── Sync/
│   │   ├── ShopifySyncService.php      # Shopify API orchestration
│   │   ├── ShopwareSyncService.php     # Shopware API orchestration
│   │   └── ProductValidator.php        # External data validation
│   └── ApiClient/
│       ├── ShopifyApiClient.php        # Shopify API wrapper
│       └── ShopwareApiClient.php       # Shopware API wrapper
├── Jobs/
│   ├── Sync/
│   │   ├── FetchShopifyProductsJob.php # Fetch products from Shopify
│   │   ├── FetchShopwareProductsJob.php # Fetch products from Shopware
│   │   ├── ProcessProductsJob.php      # Process and store products
│   │   └── IndexProductsJob.php        # Update Elasticsearch indices
│   └── SyncCleanupJob.php              # Cleanup old products
├── Models/
│   ├── Product.php                     # Product model with tenant scoping
│   ├── ProductVariant.php              # Product variants
│   └── SyncLog.php                     # Sync operation logging
└── Http/
    └── Requests/
        └── SyncCatalogRequest.php      # Sync trigger validation
```

### Pattern 1: Platform-Specific Sync Services
**What:** Abstract platform differences behind service interfaces.

**When to use:** Supporting multiple e-commerce platforms with different APIs.

**Example:**
```php
// app/Services/Sync/ShopifySyncService.php
class ShopifySyncService
{
    public function __construct(
        private ShopifyApiClient $client,
        private ProductValidator $validator
    ) {}

    public function fetchProducts(Tenant $tenant): Collection
    {
        $credentials = decrypt($tenant->api_credentials);
        $this->client->authenticate($credentials['access_token']);

        $products = collect();
        $pageInfo = null;

        do {
            $response = $this->client->getProducts([
                'limit' => 250,
                'page_info' => $pageInfo
            ]);

            $products = $products->merge($response['products']);
            $pageInfo = $response['page_info']['next_page'] ?? null;
        } while ($pageInfo);

        return $products;
    }

    public function normalizeProduct(array $shopifyProduct): array
    {
        return [
            'external_id' => $shopifyProduct['id'],
            'name' => $shopifyProduct['title'],
            'description' => $shopifyProduct['body_html'],
            'sku' => $shopifyProduct['variants'][0]['sku'] ?? null,
            'price' => (float) $shopifyProduct['variants'][0]['price'] ?? 0,
            'stock' => (int) $shopifyProduct['variants'][0]['inventory_quantity'] ?? 0,
            'platform' => 'shopify',
        ];
    }
}
```

### Pattern 2: Tenant-Aware API Client Wrapper
**What:** Wrap external API clients with tenant context management.

**When to use:** Making external API calls from queued jobs.

**Example:**
```php
// app/Services/ApiClient/ShopifyApiClient.php
class ShopifyApiClient
{
    private ?string $accessToken = null;
    private ?string $shopDomain = null;

    public function authenticate(string $accessToken, string $shopDomain): void
    {
        $this->accessToken = $accessToken;
        $this->shopDomain = $shopDomain;
    }

    public function getProducts(array $params = []): array
    {
        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->get("https://{$this->shopDomain}/admin/api/2025-01/products.json", $params)
            ->throw()
            ->json();
    }

    public function getProduct(string $productId): array
    {
        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->get("https://{$this->shopDomain}/admin/api/2025-01/products/{$productId}.json")
            ->throw()
            ->json();
    }

    public function getInventoryLevels(array $params = []): array
    {
        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->get("https://{$this->shopDomain}/admin/api/2025-01/inventory_levels.json", $params)
            ->throw()
            ->json();
    }
}
```

### Pattern 3: Chunked Product Processing
**What:** Process large product catalogs in chunks to avoid memory exhaustion.

**When to use:** Syncing catalogs with 1000+ products.

**Example:**
```php
// app/Jobs/Sync/ProcessProductsJob.php
class ProcessProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use TenantAwareJob; // From Phase 4

    public int $tenantId;
    public int $tries = 3;
    public int $timeout = 3600;

    public function __construct(
        public int $syncLogId,
        public array $productsChunk
    ) {
        $this->tenantId = Tenant::current()?->id ?? throw new \RuntimeException('No tenant context');
    }

    public function handle(ProductValidator $validator): void
    {
        Tenant::set($this->tenantId);

        DB::transaction(function () use ($validator) {
            foreach ($this->productsChunk as $productData) {
                $validated = $validator->validate($productData);

                Product::updateOrCreate(
                    [
                        'tenant_id' => $this->tenantId,
                        'external_id' => $validated['external_id']
                    ],
                    $validated
                );
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Product processing failed', [
            'tenant_id' => $this->tenantId,
            'sync_log_id' => $this->syncLogId,
            'error' => $exception->getMessage()
        ]);
    }
}
```

### Pattern 4: Idempotent Sync Operations
**What:** Ensure sync operations can be safely retried without duplicate data.

**When to use:** All sync operations to handle failures gracefully.

**Example:**
```php
// app/Services/Sync/ProductValidator.php
class ProductValidator
{
    public function validate(array $productData): array
    {
        return validator($productData, [
            'external_id' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'platform' => 'required|in:shopify,shopware',
        ])->validate();
    }
}

// In migration - ensure unique constraint for idempotency
Schema::table('products', function (Blueprint $table) {
    $table->unique(['tenant_id', 'external_id']);
});
```

### Anti-Patterns to Avoid
- **Synchronous API calls in HTTP requests:** Blocks user requests, causes timeouts. Always queue sync operations.
- **Hardcoding API credentials:** Security risk, not tenant-scoped. Use encrypted credentials per tenant.
- **Ignoring rate limits:** API calls fail, syncs incomplete. Implement rate limit detection and backoff.
- **No idempotency:** Retries create duplicates. Use updateOrCreate with unique constraints.
- **Missing tenant context in jobs:** Jobs process wrong tenant's data. Use TenantAwareJob trait.
- **Logging sensitive data:** API tokens in logs. Redact credentials before logging.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Shopify API authentication | Custom OAuth flow | shopify/shopify-api SDK | Official SDK handles token refresh, scope management |
| Shopware API authentication | Custom token handling | vin-sw/shopware-sdk | SDK provides authentication abstractions |
| HTTP request retries | Custom retry loops | Laravel HTTP Client retry() | Built-in exponential backoff, max attempts |
| Queue job processing | Custom worker scripts | Laravel Queues + Supervisor | Battle-tested, supports retries, batching, monitoring |
| Rate limit detection | Manual header parsing | Laravel HTTP Client with retry condition | Automatic 429 detection, Retry-After header handling |
| Pagination handling | Manual cursor tracking | SDK pagination methods | Official SDKs handle cursor-based pagination correctly |
| Credential encryption | Custom encryption | Laravel's Crypt facade | AES-256-CBC encryption, built into framework |

**Key insight:** External API integration has many edge cases (rate limits, pagination, token refresh, network failures). Official SDKs and Laravel's built-in features handle these cases. Custom solutions inevitably miss edge cases leading to production failures.

## Common Pitfalls

### Pitfall 1: API Rate Limit Exhaustion
**What goes wrong:** Sync jobs fail with 429 errors, incomplete catalogs, frustrated users.

**Why it happens:** Not respecting Shopify's leaky bucket algorithm (40 req/2 sec) or Shopware's ~100-300 req/min limits. Sending requests too fast without checking rate limit headers.

**How to avoid:**
```php
// Implement rate limit-aware client
class ShopifyApiClient
{
    private int $lastRequestTime = 0;
    private int $minRequestInterval = 500000; // 0.5 seconds in microseconds

    public function getProducts(array $params = []): array
    {
        $this->respectRateLimit();

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->get("https://{$this->shopDomain}/admin/api/2025-01/products.json", $params);

        $this->updateRateLimitStatus($response);

        return $response->throw()->json();
    }

    private function respectRateLimit(): void
    {
        $timeSinceLastRequest = microtime(true) - $this->lastRequestTime;
        if ($timeSinceLastRequest < ($this->minRequestInterval / 1000000)) {
            usleep($this->minRequestInterval - ($timeSinceLastRequest * 1000000));
        }
        $this->lastRequestTime = microtime(true);
    }

    private function updateRateLimitStatus(Response $response): void
    {
        $apiCallLimit = $response->header('X-Shopify-Shop-Api-Call-Limit');
        if ($apiCallLimit) {
            [$used, $limit] = explode('/', $apiCallLimit);
            if ((int) $used >= (int) $limit * 0.8) {
                // Near limit, slow down
                $this->minRequestInterval = 1000000; // 1 second
            }
        }
    }
}
```

**Warning signs:** 429 Too Many Requests errors, incomplete product counts, sync jobs failing intermittently.

### Pitfall 2: Tenant Context Loss in Sync Jobs
**What goes wrong:** Sync jobs save products to wrong tenant or fail with "tenant not found" errors.

**Why it happens:** Queue workers don't have HTTP request context. Jobs dispatched without capturing current tenant_id.

**How to avoid:**
```php
// Use TenantAwareJob from Phase 4
abstract class TenantAwareJob
{
    public int $tenantId;

    public function __construct()
    {
        $this->tenantId = Tenant::current()?->id
            ?? throw new \RuntimeException('No tenant context');
    }

    public function middleware(): array
    {
        return [new SetTenantContext($this->tenantId)];
    }
}

// In sync job
class FetchShopifyProductsJob extends TenantAwareJob
{
    public function handle(): void
    {
        // Tenant context automatically restored by middleware
        $tenant = Tenant::current();
        $credentials = decrypt($tenant->api_credentials);
        // ... sync logic
    }
}
```

**Warning signs:** Products appearing for wrong clients, "tenant not found" errors in logs, cross-tenant data leakage.

### Pitfall 3: Memory Exhaustion on Large Catalogs
**What goes wrong:** PHP fatal error "Allowed memory size exhausted" when syncing 10,000+ products.

**Why it happens:** Loading entire product catalog into memory before processing. Not chunking results.

**How to avoid:**
```php
// Process in chunks
class ProcessProductsJob implements ShouldQueue
{
    public function __construct(
        public int $syncLogId,
        public int $chunkSize = 500
    ) {}

    public function handle(ShopifySyncService $syncService): void
    {
        $products = $syncService->fetchProducts();

        // Chunk products to avoid memory exhaustion
        $chunks = $products->chunk($this->chunkSize);

        foreach ($chunks as $chunk) {
            ProcessProductsChunkJob::dispatch(
                $this->syncLogId,
                $chunk->toArray()
            );
        }
    }
}

// In ProcessProductsChunkJob
class ProcessProductsChunkJob implements ShouldQueue
{
    public function __construct(
        public int $syncLogId,
        public array $productsChunk
    ) {}

    public function handle(): void
    {
        foreach ($this->productsChunk as $productData) {
            // Process each product
            Product::updateOrCreate(/* ... */);
        }
    }
}
```

**Warning signs:** Memory usage growing linearly, PHP fatal errors on large catalogs, slow sync performance.

### Pitfall 4: Incomplete Data Validation
**What goes wrong:** Database errors, invalid data stored, search fails due to missing required fields.

**Why it happens:** Trusting external API data without validation. Not checking for required fields or data types.

**How to avoid:**
```php
// Comprehensive validation
class ProductValidator
{
    public function validate(array $productData): array
    {
        return validator($productData, [
            // Required fields
            'external_id' => 'required|string|max:255',
            'name' => 'required|string|max:255|min:1',
            'platform' => 'required|in:shopify,shopware',

            // Optional fields with type validation
            'description' => 'nullable|string|max:65535',
            'sku' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0|max:99999999.99',
            'stock' => 'required|integer|min:0',

            // Sanitize HTML
            'description' => function ($attribute, $value, $fail) {
                if ($value && strip_tags($value) !== $value) {
                    // Allow only safe HTML
                    $cleaned = strip_tags($value, '<p><br><strong><em><ul><ol><li>');
                    if (strlen($cleaned) !== strlen($value)) {
                        $fail('Contains unsafe HTML tags');
                    }
                }
            },
        ])->validate();
    }

    public function validateShopifyProduct(array $shopifyProduct): array
    {
        $normalized = $this->normalizeShopifyProduct($shopifyProduct);
        return $this->validate($normalized);
    }

    private function normalizeShopifyProduct(array $product): array
    {
        return [
            'external_id' => (string) $product['id'],
            'name' => $product['title'] ?? 'Unnamed Product',
            'description' => $product['body_html'] ?? null,
            'sku' => $product['variants'][0]['sku'] ?? null,
            'price' => (float) ($product['variants'][0]['price'] ?? 0),
            'stock' => (int) ($product['variants'][0]['inventory_quantity'] ?? 0),
            'platform' => 'shopify',
        ];
    }
}
```

**Warning signs:** Database query errors, null values in required fields, search returning no results, dashboard showing malformed data.

### Pitfall 5: Missing Sync Status Tracking
**What goes wrong:** Users can't see sync progress, stuck jobs go unnoticed, no error visibility.

**Why it happens:** Not logging sync operations, not tracking job status, no user-facing status updates.

**How to avoid:**
```php
// SyncLog model for tracking
class SyncLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'platform',
        'status',
        'started_at',
        'completed_at',
        'total_products',
        'processed_products',
        'failed_products',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => SyncStatus::class,
        ];
    }
}

enum SyncStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case PartiallyFailed = 'partially_failed';
}

// In sync job
class FetchShopifyProductsJob implements ShouldQueue
{
    public function __construct(
        public int $syncLogId
    ) {}

    public function handle(): void
    {
        $syncLog = SyncLog::find($this->syncLogId);
        $syncLog->update(['status' => SyncStatus::Running]);

        try {
            $products = $this->fetchProducts();
            $syncLog->update([
                'total_products' => $products->count(),
                'status' => SyncStatus::Completed,
                'completed_at' => now()
            ]);
        } catch (\Exception $e) {
            $syncLog->update([
                'status' => SyncStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now()
            ]);
            throw $e;
        }
    }
}
```

**Warning signs:** Users asking "is sync done?", no way to retry failed syncs, support can't troubleshoot sync issues.

## Code Examples

Verified patterns from official sources:

### Shopify API Authentication (shopify/shopify-api)
```php
// Source: Shopify official PHP SDK documentation
use Shopify\Clients\RestAdmin;

$client = new RestAdmin(
    $shopDomain,
    $accessToken,
    '2025-01'
);

$response = $client->get('products', [
    'limit' => 250,
    'status' => 'active'
]);

$products = $response->getBody()['products'];
```

### Shopware API Authentication (vin-sw/shopware-sdk)
```php
// Source: Shopware SDK community documentation
use Vin\ShopwareSdk\Client\GrantType\PasswordGrant;
use Vin\ShopwareSdk\Client\AdminClient;

$client = new AdminClient([
    'shopware_url' => $shopUrl,
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
]);

$accessToken = $client->getAccessToken();

// Make API request
$response = $client->search('product', [
    'limit' => 500,
    'total-count-mode' => true
]);
```

### Laravel HTTP Client with Retry (Fallback)
```php
// Source: Laravel 11 HTTP Client documentation
$response = Http::withToken($accessToken)
    ->retry(3, 1000, function ($exception, $request) {
        // Retry on 429 rate limit errors
        return $exception instanceof RequestException &&
               $exception->response->status() === 429;
    })
    ->timeout(30)
    ->get('https://api.shopify.com/admin/api/2025-01/products.json');

if ($response->failed()) {
    Log::error('Shopify API failed', [
        'status' => $response->status(),
        'body' => $response->body()
    ]);
}
```

### Pagination Handling (Shopify)
```php
// Source: Shopify REST Admin API documentation
public function fetchAllProducts(): Collection
{
    $products = collect();
    $pageInfo = null;

    do {
        $params = ['limit' => 250];
        if ($pageInfo) {
            $params['page_info'] = $pageInfo;
        }

        $response = $this->client->get('products', $params);
        $linkHeader = $response->header('Link');

        $products = $products->merge($response['products']);

        // Extract next page info from Link header
        if (preg_match('/<([^>]+)>; rel="next"/', $linkHeader, $matches)) {
            $nextUrl = $matches[1];
            parse_str(parse_url($nextUrl, PHP_URL_QUERY), $query);
            $pageInfo = $query['page_info'] ?? null;
        } else {
            $pageInfo = null;
        }
    } while ($pageInfo);

    return $products;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Synchronous sync in HTTP requests | Queued jobs with Laravel Queues | Laravel 5.x (2015+) | Non-blocking UX, better error handling |
| Manual pagination handling | SDK-provided pagination methods | 2020+ | Less code, fewer bugs |
| Custom retry logic | Laravel HTTP Client retry() with conditions | Laravel 7.x (2020) | Built-in exponential backoff |
| Global API credentials | Per-tenant encrypted credentials | Security best practices | Tenant isolation, credential security |
| Full sync every time | Idempotent updateOrCreate operations | 2021+ | Faster syncs, less API usage |
| Manual status tracking | JobStatus model + queue event listeners | Laravel 8.x (2020+) | Real-time sync status |

**Deprecated/outdated:**
- **Phiclassic/php-shopify:** Older Shopify SDK, use official `shopify/shopify-api` instead
- **Synchronous HTTP requests:** Blocks user requests, always queue sync operations
- **Hardcoded rate limits:** Use API response headers to detect actual limits
- **Manual credential encryption:** Use Laravel's Crypt::encrypt() instead

## Open Questions

1. **Shopify API Version Stability**
   - What we know: Shopify uses stabilized versions (2025-01, 2025-04, etc.)
   - What's unclear: Which version to use as default, how to handle version deprecation
   - Recommendation: Use latest stable version (2025-01), implement version checking in sync job, log deprecation warnings

2. **Shopware SDK Completeness**
   - What we know: vin-sw/shopware-sdk exists and is actively maintained
   - What's unclear: Does it cover all required endpoints (products, variants, inventory)?
   - Recommendation: Start with SDK, fall back to Laravel HTTP Client for missing endpoints

3. **Webhook vs Polling for Real-time Sync**
   - What we know: Webhooks provide real-time updates, polling is simpler
   - What's unclear: Should Phase 6 include webhook infrastructure or defer to v2?
   - Recommendation: Defer webhooks to v2 (SYNC-50), Phase 6 focuses on manual sync only

4. **Incremental Sync Strategy**
   - What we know: Shopify and Shopware support filtering by updated_at
   - What's unclear: How to handle deleted products (webhooks vs sync detection)
   - Recommendation: Phase 6 implements full sync, v2 adds incremental sync (SYNC-52)

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 10.x (Laravel 11 default) |
| Config file | `phpunit.xml` (existing) |
| Quick run command | `php artisan test --parallel` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SYNC-01 | Trigger manual sync via API endpoint | Feature | `php artisan test --filter=SyncCatalogTest` | ❌ Wave 0 |
| SYNC-03 | Validate product data before storage | Unit | `php artisan test --filter=ProductValidatorTest` | ❌ Wave 0 |
| SYNC-05 | Log all sync operations with timestamps | Unit | `php artisan test --filter=SyncLogTest` | ❌ Wave 0 |
| SYNC-06 | Query sync status via API endpoint | Feature | `php artisan test --filter=SyncStatusTest` | ❌ Wave 0 |
| SYNC-07 | Fetch products from Shopify API | Integration | `php artisan test --filter=ShopifySyncTest` | ❌ Wave 0 |
| SYNC-08 | Fetch products from Shopware API | Integration | `php artisan test --filter=ShopwareSyncTest` | ❌ Wave 0 |
| SYNC-09 | Store products with tenant_id association | Unit | `php artisan test --filter=ProductStorageTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --parallel` (quick feedback on new tests)
- **Per wave merge:** `php artisan test` (full suite validation)
- **Phase gate:** Full suite green + manual sync verification test with real API credentials

### Wave 0 Gaps
- [ ] `tests/Feature/Sync/SyncCatalogTest.php` — SYNC-01, SYNC-06 (API endpoints)
- [ ] `tests/Unit/Sync/ProductValidatorTest.php` — SYNC-03 (data validation)
- [ ] `tests/Unit/Sync/SyncLogTest.php` — SYNC-05 (logging)
- [ ] `tests/Integration/Sync/ShopifySyncTest.php` — SYNC-07 (Shopify API)
- [ ] `tests/Integration/Sync/ShopwareSyncTest.php` — SYNC-08 (Shopware API)
- [ ] `tests/Unit/Sync/ProductStorageTest.php` — SYNC-09 (tenant scoping)
- [ ] `tests/Integration/Sync/SyncWorkflowTest.php` — End-to-end sync workflow
- [ ] Framework: PHPUnit 10.x already configured (verified via phpunit.xml)

## Sources

### Primary (HIGH confidence)
- **Composer Package Database** — Verified `shopify/shopify-api` and `vin-sw/shopware-sdk` exist and installable
- **Laravel 11 Documentation** — HTTP Client, Queues, Validation (accessed during project setup)
- **Project Configuration** — Verified existing test infrastructure (phpunit.xml, tests/ directory)

### Secondary (MEDIUM confidence)
- **Laravel 11 Architecture Patterns** — From project `.planning/research/ARCHITECTURE.md`
- **Multi-tenant Pitfalls** — From project `.planning/research/PITFALLS.md`
- **Phase 4 Queue Infrastructure** — TenantAwareJob, JobStatus, retry logic from existing implementation

### Tertiary (LOW confidence - requires verification)
- **Shopify REST Admin API 2025-01** — Training knowledge (valid up to Aug 2025), not verified with current docs
- **Shopware 6 API endpoints** — Training knowledge (valid up to Aug 2025), not verified with current docs
- **Rate limit specifics** — General patterns known, exact numbers need verification during implementation
- **SDK feature completeness** — Package existence verified, API coverage needs testing

## Metadata

**Confidence breakdown:**
- Standard stack: MEDIUM - Composer packages verified, but SDK features need testing
- Architecture: HIGH - Based on established Laravel patterns and Phase 4 infrastructure
- Pitfalls: HIGH - Common external API integration issues well-documented
- Shopify/Shopware API specifics: LOW - Web search rate-limited, relying on training knowledge

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (30 days - API patterns stable, but SDK features need verification)

**Research limitations:**
- Web search services rate-limited, could not access official API documentation
- Shopify/Shopware API patterns based on training knowledge (Aug 2025 cutoff)
- SDK feature completeness needs practical testing
- Rate limit numbers should be verified during implementation with actual API calls

**Flags for implementation:**
1. Test SDK features thoroughly in Wave 0, verify all required endpoints work
2. Monitor actual rate limit headers during sync, adjust retry logic accordingly
3. Implement comprehensive logging to track API behavior in production
4. Consider adding integration tests with real API credentials (staging environment)
