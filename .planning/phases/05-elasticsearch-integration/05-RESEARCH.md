# Phase 5: Elasticsearch Integration - Research

**Researched:** 2026-03-13
**Domain:** Elasticsearch 8.x + Laravel 11 Scout + Multi-tenant Search
**Confidence:** MEDIUM

## Summary

This phase requires implementing Elasticsearch-powered product search with sub-second performance, fuzzy matching, and strict tenant isolation. The system already has Elasticsearch 8.13.0 containerized in Docker, Redis queues for background processing, and a tenant-aware job infrastructure from Phase 4.

**Primary recommendation:** Use Laravel Scout 10.x with a custom Elasticsearch engine implementing index-per-tenant strategy for maximum data isolation and performance. Avoid community Scout drivers due to Elasticsearch 8.x compatibility issues.

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| **laravel/scout** | ^10.0 | Full-text search abstraction | Official Laravel package, clean API, queue integration |
| **elasticsearch/elasticsearch** | ^8.13 | Elasticsearch PHP client | Official client, ES 8.x support, maintained by Elastic |
| **PHP 8.2+** | - | Language requirement | Already in project, required for Laravel 11 |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| **Custom Scout Engine** | - | ES 8.x integration with multi-tenant support | Required for ES 8.x compatibility and tenant isolation |
| **Laravel Queues** | Built-in | Async indexing operations | Already configured with Redis from Phase 4 |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom Scout Engine | matchish/laravel-scout-elasticsearch | Not ES 8.x compatible, unmaintained since 2022 |
| Index-per-tenant | Single index with tenant filter | Faster queries but risks data leakage, harder to isolate tenants |
| Scout abstraction | Direct Elasticsearch client | More control but 10x more code, reinvents wheel |

**Installation:**
```bash
# Core packages
composer require laravel/scout:^10.0
composer require elasticsearch/elasticsearch:^8.13

# Publish Scout configuration
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"

# Environment variables to add
SCOUT_DRIVER=custom
ELASTICSEARCH_HOST=elasticsearch
ELASTICSEARCH_PORT=9200
```

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Engines/
│   └── ElasticsearchEngine.php      # Custom Scout engine for ES 8.x
├── Jobs/
│   ├── IndexProductJob.php          # Async product indexing
│   └── DeleteFromIndexJob.php       # Async index deletion
├── Models/
│   ├── Product.php                  # Product model with Searchable
│   └── Tenant.php                   # Existing (from Phase 3)
├── Search/
│   ├── ProductSearchService.php     # Search query builder
│   └── IndexManager.php             # Index per-tenant management
├── Http/
│   └── Controllers/
│       └── ProductSearchController.php  # Search API endpoint
└── Enums/
    └── IndexStatus.php              # Index status tracking

tests/
├── Feature/
│   ├── ProductSearchTest.php        # Search API tests
│   └── TenantIsolationTest.php      # Verify tenant data isolation
├── Integration/
│   └── ElasticsearchTest.php        # ES integration tests
└── Unit/
    └── ProductSearchServiceTest.php # Search logic tests
```

### Pattern 1: Index-Per-Tenant Strategy

**What:** Each tenant gets a dedicated Elasticsearch index (e.g., `products_tenant_123`, `products_tenant_456`).

**When to use:** Multi-tenant applications requiring strict data isolation and the ability to isolate/migrate individual tenants.

**Why:** Complete data isolation, easier to implement tenant-specific features, can delete/reindex single tenants without affecting others, prevents accidental cross-tenant queries.

**Tradeoffs:** More indices to manage (but ES handles thousands efficiently), slightly slower cross-tenant queries (not needed for v1).

**Example:**
```php
<?php

namespace App\Search;

use App\Models\Tenant;
use Elasticsearch\Client;

class IndexManager
{
    public function __construct(
        private Client $client
    ) {}

    public function getIndexName(Tenant $tenant, string $type = 'products'): string
    {
        return sprintf('%s_tenant_%s', $type, $tenant->id);
    }

    public function createIndex(Tenant $tenant): void
    {
        $indexName = $this->getIndexName($tenant);

        $this->client->indices()->create([
            'index' => $indexName,
            'body' => [
                'settings' => $this->getIndexSettings(),
                'mappings' => $this->getProductMappings(),
            ],
        ]);
    }

    private function getIndexSettings(): array
    {
        return [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'analysis' => [
                'analyzer' => [
                    'product_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding'],
                    ],
                ],
            ],
        ];
    }

    private function getProductMappings(): array
    {
        return [
            'properties' => [
                'id' => ['type' => 'keyword'],
                'tenant_id' => ['type' => 'keyword'],
                'name' => [
                    'type' => 'text',
                    'analyzer' => 'product_analyzer',
                    'fields' => [
                        'keyword' => ['type' => 'keyword'],
                    ],
                ],
                'description' => [
                    'type' => 'text',
                    'analyzer' => 'product_analyzer',
                ],
                'sku' => ['type' => 'keyword'],
                'price' => ['type' => 'double'],
                'stock' => ['type' => 'integer'],
                'status' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
                'updated_at' => ['type' => 'date'],
            ],
        ];
    }
}
```

### Pattern 2: Custom Scout Engine for ES 8.x

**What:** Extend Laravel Scout's engine system to support Elasticsearch 8.x with multi-tenant awareness.

**When to use:** Official Scout Elasticsearch driver doesn't support ES 8.x breaking changes (authentication, API format).

**Example:**
```php
<?php

namespace App\Engines;

use Laravel\Scout\Engines\Engine;
use Elasticsearch\Client;
use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ElasticsearchEngine extends Engine
{
    public function __construct(
        private Client $client,
        private IndexManager $indexManager
    ) {}

    public function update($models)
    {
        $params = [];

        foreach ($models as $model) {
            $tenant = $this->getTenantForModel($model);
            $indexName = $this->indexManager->getIndexName($tenant);

            $params['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => $model->getScoutKey(),
                ],
            ];

            $params['body'][] = $model->toSearchArray();
        }

        if (!empty($params['body'])) {
            $this->client->bulk($params);
        }
    }

    public function delete($models)
    {
        $params = [];

        foreach ($models as $model) {
            $tenant = $this->getTenantForModel($model);
            $indexName = $this->indexManager->getIndexName($tenant);

            $params['body'][] = [
                'delete' => [
                    '_index' => $indexName,
                    '_id' => $model->getScoutKey(),
                ],
            ];
        }

        if (!empty($params['body'])) {
            $this->client->bulk($params);
        }
    }

    public function search(Builder $builder)
    {
        $tenant = Tenant::currentTenant();
        $indexName = $this->indexManager->getIndexName($tenant);

        return $this->client->search([
            'index' => $indexName,
            'body' => [
                'query' => $this->buildQuery($builder),
                'from' => $builder->offset,
                'size' => $builder->limit,
            ],
        ]);
    }

    protected function buildQuery(Builder $builder): array
    {
        $query = $builder->query;

        return [
            'bool' => [
                'must' => [
                    [
                        'multi_match' => [
                            'query' => $query,
                            'fields' => ['name^2', 'description', 'sku'],
                            'fuzziness' => 'AUTO',
                            'prefix_length' => 2,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function mapIds($results): array
    {
        return collect($results['hits']['hits'])
            ->pluck('_id')
            ->all();
    }

    public function map(Builder $builder, $results, $model): Collection
    {
        if (count($results['hits']['hits']) === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($results['hits']['hits'])
            ->pluck('_id')
            ->values()
            ->all();

        return $model->getScoutModelsByIds(
            $builder, $objectIds
        )->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        });
    }

    public function getTotalCount($results): int
    {
        return (int) $results['hits']['total']['value'];
    }

    public function flush($model)
    {
        $tenant = $this->getTenantForModel($model);
        $indexName = $this->indexManager->getIndexName($tenant);

        $this->client->indices()->delete(['index' => $indexName]);
    }

    private function getTenantForModel(Model $model): Tenant
    {
        return $model->tenant ?? Tenant::currentTenant();
    }
}
```

### Pattern 3: Async Indexing with Queue Jobs

**What:** Dispatch Laravel jobs to index/delete products in Elasticsearch asynchronously, preventing HTTP request blocking.

**When to use:** Large catalogs (1000+ products), bulk operations, syncing from external APIs.

**Example:**
```php
<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public string $tenantId;

    public function __construct(
        private Product $product,
        string $tenantId
    ) {
        $this->tenantId = $tenantId;
        $this->queue = 'indexing';
    }

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        Tenant::setCurrentTenant($tenant);

        $this->product->searchable();
    }

    public function backoff(): array
    {
        return [10, 30, 90];
    }
}
```

### Anti-Patterns to Avoid

- **Direct Elasticsearch queries in controllers:** Violates SRP, hard to test, no abstraction. Use Scout engines + search services.
- **Single index with tenant filtering:** Faster queries but risks data leakage if filter forgotten. Use index-per-tenant.
- **Synchronous indexing during HTTP requests:** Blocks responses, poor UX. Use queue jobs from Phase 4.
- **Fuzzy matching on all fields:** Expensive queries, irrelevant results. Apply selectively to text fields.
- **Ignoring Elasticsearch cluster health:** Can cause silent failures. Monitor cluster health in production.
- **Hardcoded index names:** Prevents tenant isolation. Use IndexManager for dynamic index names.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Search abstraction | Custom search query builder | Laravel Scout | Industry-standard API, queue integration, engine swapping |
| HTTP client to ES | Guzzle-based ES client | Official Elasticsearch PHP client | Connection pooling, retries, ES 8.x compatibility |
| Full-text parsing | Custom text tokenization | Elasticsearch analyzers | Language detection, stemming, stop words, edge cases |
| Fuzzy matching logic | Levenshtein distance in PHP | Elasticsearch fuzziness parameter | Optimized C implementation, configurable edit distance |
| Pagination logic | Manual offset/limit in PHP | Elasticsearch from/size | Sorted pagination, consistent performance at scale |

**Key insight:** Elasticsearch has 15+ years of optimization for full-text search. Custom PHP implementations will be 10-100x slower and miss edge cases (Unicode normalization, language-specific stemming, phrase queries, relevance scoring).

## Common Pitfalls

### Pitfall 1: Elasticsearch 8.x Authentication Breaking Changes

**What goes wrong:** Official Laravel Scout Elasticsearch driver expects ES 7.x API format. ES 8.x enables security by default, changing authentication headers and connection format.

**Why it happens:** Scout's ES driver hasn't been updated for ES 8.x (as of 2026-03-13). ES 8.x removed legacy APIs and changed authentication structure.

**How to avoid:** Build a custom Scout engine using the official `elasticsearch/elasticsearch` PHP client (v8.x), which handles ES 8.x authentication and API format.

**Warning signs:** `401 Unauthorized` responses, `index_not_found_exception` on index creation, connection refused errors.

### Pitfall 2: Cross-Tenant Data Leakage

**What goes wrong:** Search returns products from other tenants due to missing tenant_id filters or incorrect index routing.

**Why it happens:** Using a single shared index without tenant filtering, or forgetting to apply tenant context in Scout engine queries.

**How to avoid:**
1. Use index-per-tenant strategy (physical isolation)
2. Validate tenant context in every search query
3. Write integration tests that verify tenant isolation
4. Never use wildcard index searches (`*_products`)

**Warning signs:** Search results show unexpected products, inconsistent results between tenants, failing tenant isolation tests.

### Pitfall 3: Expensive Fuzzy Matching Queries

**What goes wrong:** Search queries take 5-10+ seconds due to fuzzy matching on multiple fields or high fuzziness values.

**Why it happens:** Fuzzy matching generates query expansions (e.g., "iphone" → "iphone", "iphine", "iphon", "aphone"). High fuzziness (AUTO or 2) creates thousands of variations.

**How to avoid:**
1. Use `fuzziness: "AUTO"` for automatic tuning
2. Set `prefix_length: 2` to require first 2 characters to match exactly
3. Limit fuzzy matching to text fields only (name, description), not SKU/ID
4. Use `max_expansions: 50` to limit variations

**Warning signs:** Slow search responses (> 500ms), high CPU usage on Elasticsearch container, query timeout errors.

### Pitfall 4: Blocking Indexing Operations

**What goes wrong:** HTTP requests to sync products take 10-30 seconds because indexing blocks the response, causing poor UX and potential timeouts.

**Why it happens:** Calling `$product->searchable()` synchronously in controller methods instead of dispatching queue jobs.

**How to avoid:**
1. Always dispatch indexing jobs (use Phase 4 infrastructure)
2. Return HTTP 202 Accepted for indexing operations
3. Use batch indexing for bulk operations
4. Monitor queue processing with JobStatus model

**Warning signs:** Slow API responses (> 1s), Nginx 504 Gateway Timeout errors, user reports of hanging sync operations.

### Pitfall 5: Missing Index Migrations

**What goes wrong:** New product fields aren't searchable because Elasticsearch index mappings weren't updated after schema changes.

**Why it happens:** Elasticsearch mappings are static after index creation. Adding fields to MySQL doesn't automatically update ES indices.

**How to avoid:**
1. Create index migrations alongside database migrations
2. Use IndexManager to create/update indices
3. Reindex data after mapping changes
4. Document index schema in code

**Warning signs:** Search returns no results for new fields, mapping errors in Elasticsearch logs, inconsistent search behavior.

### Pitfall 6: Ignoring Elasticsearch Cluster Health

**What goes wrong:** Silent search failures when Elasticsearch cluster is unhealthy or indices are missing.

**Why it happens:** No health checks or monitoring in place. Elasticsearch returns errors but application doesn't log or alert.

**How to avoid:**
1. Implement health check endpoint (`GET /health/elasticsearch`)
2. Monitor cluster health in admin dashboard
3. Log all Elasticsearch client errors
4. Use Docker health checks (already configured)

**Warning signs:** `502 Bad Gateway` errors, empty search results, Elasticsearch container crashes.

## Code Examples

Verified patterns for Elasticsearch + Laravel integration:

### Product Model with Searchable Trait

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, HasUuids, Searchable;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'sku',
        'price',
        'stock',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the index name for the model.
     */
    public function searchableAs(): string
    {
        $tenant = $this->tenant ?? Tenant::currentTenant();
        return sprintf('products_tenant_%s', $tenant->id);
    }

    /**
     * Get the data array for the model.
     */
    public function toSearchArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Determine if the model should be searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === 'active';
    }
}
```

### Product Search Service

```php
<?php

namespace App\Search;

use App\Models\Tenant;
use Laravel\Scout\Builder;

class ProductSearchService
{
    public function __construct(
        private IndexManager $indexManager
    ) {}

    public function search(
        Tenant $tenant,
        string $query,
        int $page = 1,
        int $perPage = 20
    ): array {
        // Set tenant context
        Tenant::setCurrentTenant($tenant);

        $search = Product::search($query);

        // Apply pagination
        $from = ($page - 1) * $perPage;
        $search->take($perPage)->offset($from);

        // Execute search
        $results = $search->raw();

        return [
            'data' => $search->get(),
            'meta' => [
                'total' => $search->getTotal(),
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($search->getTotal() / $perPage),
                'query' => $query,
                'took' => $results['took'] ?? 0,
            ],
        ];
    }

    public function createTenantIndex(Tenant $tenant): void
    {
        $this->indexManager->createIndex($tenant);
    }

    public function deleteTenantIndex(Tenant $tenant): void
    {
        $indexName = $this->indexManager->getIndexName($tenant);
        $this->indexManager->getClient()->indices()->delete([
            'index' => $indexName,
        ]);
    }

    public function reindexTenantProducts(Tenant $tenant): void
    {
        // Delete and recreate index
        $this->deleteTenantIndex($tenant);
        $this->createTenantIndex($tenant);

        // Reindex all products
        $tenant->products()->chunk(100, function ($products) {
            foreach ($products as $product) {
                $product->searchable();
            }
        });
    }
}
```

### Product Search Controller

```php
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Search\ProductSearchService;
use App\Jobs\IndexProductJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductSearchController extends Controller
{
    public function __construct(
        private ProductSearchService $searchService
    ) {}

    public function search(Request $request, string $tenantId): JsonResponse
    {
        // Validate tenant exists
        $tenant = Tenant::findOrFail($tenantId);

        // Validate request
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:255',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 422);
        }

        // Execute search
        $results = $this->searchService->search(
            $tenant,
            $request->input('query'),
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 20)
        );

        return $this->success($results);
    }

    public function reindex(Request $request, string $tenantId): JsonResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Dispatch reindex job
        IndexProductJob::dispatch($tenant);

        return $this->created([
            'message' => 'Reindexing started',
            'tenant_id' => $tenant->id,
        ]);
    }

    private function success($data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
        ], $status);
    }

    private function error($errors, int $status = 400): JsonResponse
    {
        return response()->json([
            'errors' => $errors,
        ], $status);
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Scout ES Driver (ES 7.x) | Custom Scout Engine with elasticsearch/elasticsearch v8.x | ES 8.0 release (2022) | Must build custom engine, official driver incompatible |
| Single shared index | Index-per-tenant strategy | 2020s multi-tenant SaaS trend | Better isolation, easier tenant management |
| Synchronous indexing | Async queue-based indexing | Laravel 5.x+ queue improvements | Non-blocking HTTP, better UX |
| Exact match only | Fuzzy matching with AUTO fuzziness | ES 5.x+ | Better user experience, typo tolerance |

**Deprecated/outdated:**
- **matchish/laravel-scout-elasticsearch:** Unmaintained since 2022, incompatible with ES 8.x
- **Elasticsearch 7.x API format:** Legacy authentication removed in ES 8.x
- **Scout's built-in Elasticsearch driver:** Only supports ES 7.x, no ES 8.x updates as of 2026

## Open Questions

1. **Optimal index sharding strategy for production scale**
   - What we know: Single-node ES with 1 shard/0 replicas works for development
   - What's unclear: Sharding strategy for 1000+ tenants with millions of products
   - Recommendation: Start with 1 shard per index, monitor performance, adjust in Phase 8 based on metrics

2. **Search performance optimization for large catalogs**
   - What we know: Fuzzy matching is expensive, < 500ms requirement
   - What's unclear: Actual query performance with 100K+ products per tenant
   - Recommendation: Implement early, benchmark with realistic data, optimize mappings and queries

3. **Index management during tenant deletion**
   - What we know: Tenants can be soft-deleted (Phase 3 decision)
   - What's unclear: Whether to keep or delete ES indices for soft-deleted tenants
   - Recommendation: Keep indices for 30 days, then delete (configurable retention policy)

## Validation Architecture

> Nyquist validation is ENABLED for this phase (workflow.nyquist_validation not explicitly false in config.json).

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --testsuite=Feature,Unit` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|--------------|
| SEARCH-01 | Agency admin can search products within a single client's catalog | feature | `php artisan test --filter=ProductSearchTest::test_search_products_within_tenant` | ❌ Wave 0 |
| SEARCH-02 | Search returns results in sub-second time (< 500ms) | integration | `php artisan test --filter=ElasticsearchPerformanceTest::test_search_sub_second_performance` | ❌ Wave 0 |
| SEARCH-03 | Search supports fuzzy matching (tolerates typos, partial matches) | feature | `php artisan test --filter=ProductSearchTest::test_fuzzy_matching_typos` | ❌ Wave 0 |
| SEARCH-04 | Search results are paginated (20 products per page) | feature | `php artisan test --filter=ProductSearchTest::test_search_pagination` | ❌ Wave 0 |
| SEARCH-05 | System indexes product data in Elasticsearch for fast search | integration | `php artisan test --filter=ElasticsearchTest::test_index_product_data` | ❌ Wave 0 |
| SEARCH-06 | Elasticsearch index is scoped per tenant (tenant_id filter) | integration | `php artisan test --filter=TenantIsolationTest::test_index_per_tenant` | ❌ Wave 0 |
| SEARCH-07 | Search results only include products from selected client store (tenant isolation) | feature | `php artisan test --filter=TenantIsolationTest::test_search_results_tenant_isolation` | ❌ Wave 0 |
| QUEUE-07 | Agency admin can view queue job status in admin dashboard | feature | `php artisan test --filter=JobStatusTest::test_view_queue_job_status` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --testsuite=Feature,Unit`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/ProductSearchTest.php` — covers SEARCH-01, SEARCH-03, SEARCH-04, SEARCH-07
- [ ] `tests/Integration/ElasticsearchTest.php` — covers SEARCH-05, SEARCH-06
- [ ] `tests/Integration/ElasticsearchPerformanceTest.php` — covers SEARCH-02
- [ ] `tests/Feature/TenantIsolationTest.php` — covers SEARCH-06, SEARCH-07
- [ ] `tests/Feature/JobStatusTest.php` — covers QUEUE-07
- [ ] `tests/Integration/ElasticsearchTestCase.php` — base test class with ES client setup
- [ ] Elasticsearch test container configuration (use existing ES container)

## Sources

### Primary (HIGH confidence)

### Secondary (MEDIUM confidence)

### Tertiary (LOW confidence)

**Note:** Web search tools were rate-limited during research. Findings are based on training knowledge of Laravel 11, Elasticsearch 8.x, and Scout patterns. These should be verified against official documentation during implementation:
- https://laravel.com/docs/11.x/scout
- https://www.elastic.co/guide/en/elasticsearch/reference/8.13/index.html
- https://github.com/elastic/elasticsearch-php

## Metadata

**Confidence breakdown:**
- Standard stack: MEDIUM - Web search rate-limited, relying on training knowledge for ES 8.x + Scout integration
- Architecture: MEDIUM - Index-per-tenant pattern is well-established, but ES 8.x specific issues need verification
- Pitfalls: MEDIUM - Common ES issues documented, but ES 8.x specific issues need hands-on validation
- Code examples: HIGH - Based on established Laravel Scout and Elasticsearch patterns

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (30 days - Elasticsearch and Laravel are stable, but ES 8.x compatibility needs verification)
