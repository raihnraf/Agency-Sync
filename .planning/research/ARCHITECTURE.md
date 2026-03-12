# Architecture Patterns

**Domain:** Laravel 11 Multi-tenant E-commerce Agency Management
**Researched:** 2026-03-13
**Overall Confidence:** MEDIUM (Cannot verify with current web sources due to rate limits)

## Executive Summary

Laravel 11 multi-tenant systems for e-commerce agency management typically use a **tenant_id column approach** (shared database) rather than database-per-tenant for self-hosted deployments. The architecture centers around tenant resolution middleware, scoped queries, queue-based background processing, and Elasticsearch integration for fast cross-tenant product search. This document outlines the recommended architecture for AgencySync.

## Recommended Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         API Layer                                │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │ Subdomain    │  │    API       │  │   Admin      │         │
│  │ Routing      │  │  Endpoints   │  │  Dashboard   │         │
│  │ (tenant.*)   │  │  (/api/*)    │  │  (/admin/*)  │         │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘         │
│         │                  │                  │                  │
│         └──────────────────┴──────────────────┘                  │
│                            │                                     │
│                   ┌────────▼────────┐                            │
│                   │  Tenant         │                            │
│                   │  Resolution     │                            │
│                   │  Middleware     │                            │
│                   └────────┬────────┘                            │
└────────────────────────────┼────────────────────────────────────┘
                               │
┌──────────────────────────────┼──────────────────────────────────┐
│                      Application Layer                          │
├──────────────────────────────┼──────────────────────────────────┤
│  ┌───────────────────────────┴───────────────────────────┐     │
│  │              Service Layer (Tenant-Scoped)             │     │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐│     │
│  │  │  Tenant      │  │  Catalog     │  │   Search     ││     │
│  │  │  Service     │  │  Sync        │  │  Service     ││     │
│  │  └──────────────┘  └──────────────┘  └──────────────┘│     │
│  └───────────────────────────┬───────────────────────────┘     │
│                              │                                  │
│  ┌───────────────────────────┴───────────────────────────┐     │
│  │                 Repository Layer                       │     │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐│     │
│  │  │  Tenant      │  │  Product     │  │    Job       ││     │
│  │  │  Repository  │  │  Repository  │  │  Repository  ││     │
│  │  └──────────────┘  └──────────────┘  └──────────────┘│     │
│  └───────────────────────────┬───────────────────────────┘     │
└──────────────────────────────┼──────────────────────────────────┘
                               │
┌──────────────────────────────┴──────────────────────────────────┐
│                      Infrastructure Layer                        │
├─────────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐         │
│  │   MySQL      │  │  Elasticsearch│  │    Redis     │         │
│  │  (tenant_id  │  │  (Multi-tenant│  │    Queue     │         │
│  │   columns)   │  │   indices)   │  │              │         │
│  └──────────────┘  └──────────────┘  └──────────────┘         │
│                                                                   │
│  ┌──────────────┐  ┌──────────────┐                            │
│  │  Supervisor  │  │    Nginx     │                            │
│  │  (Queue      │  │  (Reverse    │                            │
│  │   Workers)   │  │   Proxy)     │                            │
│  └──────────────┘  └──────────────┘                            │
└─────────────────────────────────────────────────────────────────┘
```

## Component Boundaries

### 1. Tenant Resolution Middleware

**Responsibility:** Identify the current tenant from the incoming request and make it available throughout the application lifecycle.

**Communicates With:**
- HTTP Kernel (registered as global middleware)
- Tenant Repository (fetches tenant data)
- Request object (stores tenant context)
- Service Layer (provides tenant scoping)

**Key Behaviors:**
- Extract tenant identifier from subdomain (e.g., `client1.agency.local`) or API token
- Query tenant repository for validation
- Store tenant in request context (singleton pattern)
- Throw 404 if tenant not found or inactive
- Apply tenant scoping to all Eloquent queries

**Implementation Pattern:**
```php
// app/Http/Middleware/IdentifyTenant.php
class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenantIdentifier = $this->extractTenantIdentifier($request);
        $tenant = Tenant::where('slug', $tenantIdentifier)->firstOrFail();

        app()->instance('currentTenant', $tenant);
        Tenant::setCurrent($tenant);

        return $next($request);
    }

    private function extractTenantIdentifier(Request $request): string
    {
        // Extract from subdomain for web routes
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        // Or from API token for API routes
        if ($request->bearerToken()) {
            return $this->extractFromToken($request);
        }

        return $subdomain;
    }
}
```

### 2. Service Layer

**Responsibility:** Business logic coordination, orchestration of repositories, and tenant-aware operations.

**Communicates With:**
- Controllers (receives requests)
- Repositories (data access)
- External APIs (Shopify, Shopware)
- Queue system (dispatches jobs)
- Elasticsearch client (search operations)

**Key Services:**

#### TenantService
- Tenant CRUD operations
- Tenant validation
- Subscription/plan management

#### CatalogSyncService
- Orchestrates sync jobs for multiple tenants
- Handles rate limiting and API quota management
- Tracks sync status and progress
- Implements retry logic with exponential backoff

#### SearchService
- Elasticsearch query construction
- Multi-tenant search scoping
- Fuzzy search and relevance scoring
- Aggregation and filtering

### 3. Repository Layer

**Responsibility:** Data access abstraction with automatic tenant scoping.

**Communicates With:**
- Service Layer
- Eloquent Models
- Database

**Pattern:**
```php
// app/Repositories/ProductRepository.php
class ProductRepository
{
    public function forTenant(Tenant $tenant): Builder
    {
        return Product::where('tenant_id', $tenant->id);
    }

    public function findForTenant(Tenant $tenant, int $id): ?Product
    {
        return $this->forTenant($tenant)->findOrFail($id);
    }

    public function searchForTenant(
        Tenant $tenant,
        string $query,
        array $filters = []
    ): Collection {
        return $this->forTenant($tenant)
            ->where('name', 'like', "%{$query}%")
            ->when($filters['category'] ?? null, fn($q, $cat) => $q->where('category', $cat))
            ->get();
    }
}
```

### 4. Queue Job System

**Responsibility:** Asynchronous processing of long-running tasks (catalog sync, Elasticsearch indexing).

**Communicates With:**
- Service Layer (dispatches jobs)
- Redis (job storage)
- Supervisor (process management)
- External APIs (Shopify, Shopware)

**Job Types:**

#### SyncCatalogJob
- Fetches products from external API
- Processes and normalizes data
- Stores in database
- Dispatches index update jobs

#### IndexProductJob
- Updates Elasticsearch document
- Handles indexing failures
- Implements retry logic

#### CleanupJob
- Removes old products
- Handles soft deletes
- Cleans up orphaned data

**Queue Worker Architecture:**
```php
// config/queue.php - Recommended setup
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => true, // Prevent job processing if transaction fails
    ],
],

// Separate queues for different job types
'queues' => [
    'sync' => 'high-priority',     // Catalog sync jobs
    'index' => 'default',          // Elasticsearch indexing
    'cleanup' => 'low-priority',   // Maintenance tasks
],
```

**Supervisor Configuration:**
```ini
[program:agencySync-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=sync,index,cleanup --tries=3 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/worker.log
stopwaitsecs=3600
```

### 5. Elasticsearch Integration

**Responsibility:** Fast, full-text product search across tenants with fuzzy matching and relevance scoring.

**Communicates With:**
- SearchService
- Repository Layer
- Elasticsearch cluster

**Indexing Strategy:**

**Option 1: Index-per-Tenant** (Recommended for AgencySync)
- Each tenant gets their own Elasticsearch index
- Pros: Complete isolation, easier to manage per-tenant settings, better security
- Cons: More indices to manage, higher overhead
- Best for: 10-1000 tenants, moderate data volume per tenant

```php
// Index naming: products_{tenant_id}
// Example: products_1, products_2, products_3

class ProductIndexer
{
    public function getIndexName(Tenant $tenant): string
    {
        return "products_{$tenant->id}";
    }

    public function indexProduct(Product $product): void
    {
        $index = $this->getIndexName($product->tenant);
        $document = $this->prepareDocument($product);

        Elasticsearch::index([
            'index' => $index,
            'id' => $product->id,
            'body' => $document,
        ]);
    }
}
```

**Option 2: Shared Index with Tenant Routing**
- All tenants share one index with routing key
- Pros: Fewer indices, efficient for many small tenants
- Cons: Complex query scoping, potential performance issues with large datasets
- Best for: 1000+ tenants, small data volume per tenant

```php
// Single index: products
// Routing key: tenant_id

class ProductIndexer
{
    public function indexProduct(Product $product): void
    {
        Elasticsearch::index([
            'index' => 'products',
            'id' => "{$product->tenant_id}_{$product->id}",
            'routing' => $product->tenant_id,
            'body' => $this->prepareDocument($product),
        ]);
    }
}
```

**Mapping Configuration:**
```json
{
  "settings": {
    "number_of_shards": 1,
    "number_of_replicas": 1,
    "analysis": {
      "filter": {
        "ngram_filter": {
          "type": "edge_ngram",
          "min_gram": 2,
          "max_gram": 15
        }
      },
      "analyzer": {
        "ngram_analyzer": {
          "type": "custom",
          "tokenizer": "standard",
          "filter": ["lowercase", "ngram_filter"]
        }
      }
    }
  },
  "mappings": {
    "properties": {
      "name": {
        "type": "text",
        "analyzer": "ngram_analyzer",
        "fields": {
          "keyword": {"type": "keyword"}
        }
      },
      "description": {"type": "text"},
      "sku": {"type": "keyword"},
      "price": {"type": "double"},
      "tenant_id": {"type": "integer"},
      "categories": {"type": "keyword"},
      "created_at": {"type": "date"},
      "updated_at": {"type": "date"}
    }
  }
}
```

## Data Flow

### 1. Request Lifecycle

```
1. Nginx receives request
   ↓
2. Laravel HTTP Kernel boots
   ↓
3. IdentifyTenant middleware extracts tenant (subdomain/token)
   ↓
4. Tenant stored in app container (singleton)
   ↓
5. Controller receives request with tenant context
   ↓
6. Service layer processes business logic (tenant-scoped)
   ↓
7. Repository queries database (WHERE tenant_id = ?)
   ↓
8. Response returned with tenant-aware data
```

### 2. Catalog Sync Flow

```
1. Admin triggers sync for tenant via UI/API
   ↓
2. CatalogSyncService validates tenant + API credentials
   ↓
3. SyncCatalogJob dispatched to Redis queue
   ↓
4. Supervisor worker picks up job
   ↓
5. Job fetches products from Shopify/Shopware API
   ↓
6. Products upserted to database (with tenant_id)
   ↓
7. IndexProductJob dispatched for each product
   ↓
8. Elasticsearch indices updated
   ↓
9. Status notification sent to admin
```

### 3. Search Flow

```
1. User submits search query via admin dashboard
   ↓
2. Request includes tenant context (from middleware)
   ↓
3. SearchService constructs Elasticsearch query
   ↓
4. Query scoped to tenant index/products_{tenant_id}
   ↓
5. Elasticsearch returns results with relevance scores
   ↓
6. Results enriched with database data (prices, inventory)
   ↓
7. Paginated response returned
```

## Patterns to Follow

### Pattern 1: Global Query Scoping

**What:** Automatically scope all Eloquent queries to the current tenant.

**When:** All database queries in multi-tenant context.

**Example:**
```php
// app/Models/Tenant.php
class Tenant extends Model
{
    protected static function booted()
    {
        static::creating(function ($model) {
            if (app()->has('currentTenant')) {
                $model->tenant_id = app('currentTenant')->id;
            }
        });
    }
}

// app/Models/Product.php
class Product extends Model
{
    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (app()->has('currentTenant')) {
                $query->where('tenant_id', app('currentTenant')->id);
            }
        });
    }

    // Relationship always scoped to tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

### Pattern 2: Tenant-Aware Repository Injection

**What:** Use Laravel's service container to inject repositories pre-configured for the current tenant.

**When:** Need clean separation between controllers and data access.

**Example:**
```php
// app/Providers/AppServiceProvider.php
public function register()
{
    $this->app->scoped(ProductRepository::class, function () {
        $tenant = app('currentTenant');
        return new ProductRepository($tenant);
    });
}

// Controller usage
class ProductController extends Controller
{
    public function __construct(
        private ProductRepository $products
    ) {}

    public function index()
    {
        // Repository already has tenant context
        return $this->products->all();
    }
}
```

### Pattern 3: Job Batch Processing

**What:** Group related jobs for atomic operations and progress tracking.

**When:** Catalog syncs affecting thousands of products.

**Example:**
```php
// app/Services/CatalogSyncService.php
class CatalogSyncService
{
    public function syncTenant(Tenant $tenant): Batch
    {
        $batch = Bus::batch([
            new FetchProductsJob($tenant),
            new SyncCategoriesJob($tenant),
            new SyncInventoryJob($tenant),
        ])->then(function (Batch $batch) use ($tenant) {
            // All jobs completed successfully
            $tenant->update(['last_sync_at' => now()]);
        })->catch(function (Batch $batch, Throwable $e) {
            // Batch failed
            Log::error('Sync batch failed', ['batch_id' => $batch->id]);
        })->finally(function (Batch $batch) {
            // Cleanup
        })->dispatch();

        return $batch;
    }
}
```

### Pattern 4: Tenant-Scoped Cache Keys

**What:** Prevent cache key collisions between tenants.

**When:** Using Redis for caching.

**Example:**
```php
// app/Services/CacheService.php
class CacheService
{
    public function getKey(string $key): string
    {
        $tenantId = app('currentTenant')->id;
        return "tenant:{$tenantId}:{$key}";
    }

    public function remember(string $key, $callback)
    {
        return Cache::remember($this->getKey($key), 3600, $callback);
    }
}

// Usage
$products = $cache->remember('products.all', fn() => Product::all());
// Stored as: tenant:1:products.all
```

### Pattern 5: Event-Driven Tenant Isolation

**What:** Use Laravel events to enforce tenant boundaries in real-time.

**When:** Complex workflows requiring side effects.

**Example:**
```php
// app/Events/ProductCreated.php
class ProductCreated
{
    public function __construct(
        public Product $product
    ) {}
}

// app/Listeners/IndexProduct.php
class IndexProduct
{
    public function handle(ProductCreated $event)
    {
        // Automatically indexes to correct tenant index
        IndexProductJob::dispatch($event->product);
    }
}

// app/Providers/EventServiceProvider.php
protected $listen = [
    ProductCreated::class => [
        IndexProduct::class,
    ],
];
```

## Anti-Patterns to Avoid

### Anti-Pattern 1: Manual Tenant Filtering

**What:** Adding `->where('tenant_id', $tenantId)` to every query manually.

**Why bad:**
- Error-prone (easy to forget)
- Violates DRY principle
- Security risk (data leaks if missed)

**Instead:** Use global scopes or repository pattern with automatic scoping.

### Anti-Pattern 2: Shared Index Without Tenant Filtering

**What:** Storing all tenant data in one Elasticsearch index without proper routing or filtering.

**Why bad:**
- Cross-tenant data leakage in search results
- Performance degradation with large datasets
- Security vulnerability

**Instead:** Use index-per-tenant strategy or strict routing with tenant_id filtering.

### Anti-Pattern 3: Synchronous External API Calls

**What:** Calling Shopify/Shopware APIs directly in HTTP requests.

**Why bad:**
- Blocks user requests (slow UX)
- API rate limits cause timeouts
- Cascading failures

**Instead:** Always dispatch jobs for external API calls, use queue workers.

### Anti-Pattern 4: Hardcoded Tenant Context

**What:** Passing `$tenantId` through all method signatures.

**Why bad:**
- Bloated method signatures
- Loss of tenant context in async jobs
- Difficult to maintain

**Instead:** Use Laravel's container to store tenant as singleton.

### Anti-Pattern 5: Mixed Concerns in Controllers

**What:** Putting business logic, database queries, and external API calls in controllers.

**Why bad:**
- Impossible to test
- Can't reuse logic
- Violates single responsibility principle

**Instead:** Use service layer for business logic, repositories for data access.

## Scalability Considerations

### At 10 Tenants
- **Database:** Single MySQL instance, tenant_id columns sufficient
- **Elasticsearch:** Index-per-tenant, single node
- **Queue:** 1-2 Supervisor workers
- **Cache:** Redis single instance

### At 1,000 Tenants
- **Database:** Consider read replicas for reporting queries
- **Elasticsearch:** Index-per-tenant, 3-node cluster with dedicated master nodes
- **Queue:** 5-10 workers, separate queues for high/low priority
- **Cache:** Redis cluster with sharding

### At 100,000+ Tenants
- **Database:** Database sharding by tenant_id range, partitioning
- **Elasticsearch:** Hot/warm architecture, index lifecycle management
- **Queue:** Redis Cluster, Horizon for monitoring, dedicated worker pools per tenant tier
- **Cache:** Redis cluster with consistent hashing

**Performance Optimization Flags:**
- **< 1000 tenants:** Standard setup, no special optimizations
- **1000-10000 tenants:** Add database indexing on tenant_id, consider read replicas
- **10000+ tenants:** Requires deeper research into sharding, partitioning, and dedicated infrastructure

## Build Order Dependencies

### Phase 1: Foundation (Must Build First)
1. **Tenant Resolution Middleware** - Required by everything else
2. **Database Schema with tenant_id** - Foundation for all data
3. **Basic Authentication** - Required for API security

**Dependencies:** None
**Build Time:** 1-2 weeks

### Phase 2: Core Services
1. **Repository Layer** - Required for data access
2. **Service Layer** - Business logic foundation
3. **Basic API Endpoints** - CRUD for tenants and products

**Dependencies:** Phase 1 complete
**Build Time:** 2-3 weeks

### Phase 3: Background Processing
1. **Queue Configuration** - Redis + Supervisor
2. **Job Classes** - Sync and indexing jobs
3. **Job Monitoring** - Failed job tracking

**Dependencies:** Phase 2 complete
**Build Time:** 2 weeks

### Phase 4: Search Integration
1. **Elasticsearch Setup** - Index configuration
2. **Search Service** - Query construction
3. **Indexing Jobs** - Automated sync to ES

**Dependencies:** Phase 3 complete (requires queue workers)
**Build Time:** 2-3 weeks

### Phase 5: External Integration
1. **Shopify/Shopware API Clients**
2. **Catalog Sync Workflows**
3. **Error Handling & Retry Logic**

**Dependencies:** Phase 3 complete (requires jobs)
**Build Time:** 3-4 weeks

### Phase 6: Admin Dashboard
1. **UI Components** - Blade + Alpine.js
2. **Monitoring Views** - Sync status, job queues
3. **Search Interface**

**Dependencies:** Phase 4 complete (requires search API)
**Build Time:** 3-4 weeks

**Critical Path:**
Phase 1 → Phase 2 → Phase 3 → [Phase 4 || Phase 5] → Phase 6

Note: Phase 4 (Search) and Phase 5 (External Integration) can proceed in parallel after Phase 3 is complete.

## Laravel 11 Specific Patterns

### Minimal Application Structure
Laravel 11 removes the `app/Http/Controllers`, `app/Models`, and other directories by default. For AgencySync, **recreate these directories manually** for better organization:

```
app/
├── Actions/          # Single-action classes (Laravel 11 pattern)
├── Contracts/        # Interfaces
├── Enums/           # PHP 8.2+ enums
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
├── Jobs/
├── Listeners/
├── Models/
├── Providers/
├── Repositories/    # Custom, not default
└── Services/        # Custom, not default
```

### PHP 8.2+ Features
```php
// Readonly properties for Value Objects
readonly class TenantConfig
{
    public function __construct(
        public string $name,
        public string $slug,
        public bool $isActive
    ) {}
}

// Enums for tenant states
enum TenantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
}

// Named arguments for clarity
$product = Product::create(
    name: $data['name'],
    price: $data['price'],
    tenantId: $tenant->id
);
```

### Native Laravel 11 Queue Improvements
- **Job Batching:** Built-in batch management (no external packages needed)
- **Job Chaining:** Sequential job execution with error handling
- **Job Middleware:** Apply middleware to jobs for validation/authorization

## API Structure

### Routing Organization
```php
// routes/api.php - Tenant-scoped API routes
Route::middleware(['auth:sanctum', 'tenant.identification'])
    ->prefix('/v1')
    ->group(function () {

        // Tenant Management (Agency Admin only)
        Route::prefix('/admin')->middleware(['role:admin'])->group(function () {
            Route::apiResource('tenants', TenantController::class);
            Route::post('/tenants/{tenant}/sync', [SyncController::class, 'trigger']);
        });

        // Product Management (Scoped to current tenant)
        Route::apiResource('products', ProductController::class);
        Route::get('/products/search', [ProductSearchController::class, 'index']);

        // Catalog Operations
        Route::prefix('/catalog')->group(function () {
            Route::post('/sync', [CatalogSyncController::class, 'sync']);
            Route::get('/sync/status', [CatalogSyncController::class, 'status']);
        });
    });

// routes/web.php - Admin dashboard routes
Route::middleware(['auth', 'tenant.identification'])
    ->prefix('/admin')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::get('/sync/{batch}', [SyncMonitorController::class, 'show']);
    });
```

### Subdomain Routing (Optional Enhancement)
```php
// routes/web.php - Tenant-specific subdomains
Route::domain('{tenant}.agency.local')->group(function () {
    Route::get('/products', [ProductController::class, 'index']);
});

// Or use wildcard subdomain for dynamic resolution
Route::domain('{account}.yourapp.com')->group(function () {
    Route::get('/user/{id}', function ($account, $id) {
        // $account is the subdomain
        // Automatically resolved by IdentifyTenant middleware
    });
});
```

## Authentication and Authorization

### Authentication Strategy
**Recommendation:** Laravel Sanctum for API tokens, Session-based auth for admin dashboard.

```php
// config/auth.php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

### Authorization Patterns
```php
// app/Policies/ProductPolicy.php
class ProductPolicy
{
    public function view(User $user, Product $product): bool
    {
        // User can only view products from their tenant
        return $product->tenant_id === $user->tenant_id;
    }

    public function update(User $user, Product $product): bool
    {
        return $product->tenant_id === $user->tenant_id
            && $user->can('update products');
    }
}

// Controller usage
class ProductController extends Controller
{
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        // Automatically throws 403 if tenant mismatch
    }
}
```

### API Token Management
```php
// Issue tenant-scoped API tokens
$token = $user->createToken(
    name: 'api-token',
    abilities: ['products.read', 'products.write'],
    tokenable_id: $tenant->id // Tie token to tenant
);

// Middleware validates token belongs to current tenant
```

## Security Considerations

### Tenant Isolation Checks
- **Always** validate tenant_id on writes
- **Always** scope queries with tenant_id on reads
- **Never** use `Model::withoutGlobalScopes()` without explicit tenant validation
- **Always** validate API tokens against tenant ownership

### Recommended Middleware Stack
```php
// app/Http/Kernel.php (Laravel 11 uses bootstrap/app.php)
protected $middleware = [
    \App\Http\Middleware\IdentifyTenant::class,
    \App\Http\Middleware\ValidateTenantOwnership::class,
];

protected $middlewareGroups = [
    'api' => [
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];
```

### Data Encryption
- Encrypt sensitive API credentials in database (Shopify tokens, API keys)
- Use Laravel's built-in encryption: `Crypt::encryptString()`
- Store encrypted values in `tenants` table, decrypt at runtime

## Monitoring and Observability

### Job Monitoring
Use Laravel's built-in queue monitoring:
```bash
php artisan queue:monitor redis:sync,redis:index
```

### Tenant-Level Metrics
Track per-tenant:
- API rate limit usage
- Queue job throughput
- Search query volume
- Database query performance
- Elasticsearch index size

### Logging Strategy
```php
// Structured logging with tenant context
Log::info('Catalog sync started', [
    'tenant_id' => $tenant->id,
    'tenant_slug' => $tenant->slug,
    'product_count' => $count,
    'timestamp' => now()->toIso8601String(),
]);
```

## Sources

**LOW Confidence** - Could not verify with official sources due to web search rate limits. Based on:
- Laravel 11 documentation (general knowledge, not accessed during research)
- Standard multi-tenant Laravel patterns (established best practices)
- Project context from `.planning/PROJECT.md`
- Fresh Laravel 11 installation structure (verified locally)

**Research Gaps:**
- Could not verify current Laravel 11 multi-tenant package ecosystem (tenancyforlaravel, etc.)
- Could not access current Elasticsearch 8.x + Laravel integration patterns
- Could not verify Supervisor best practices for Laravel 11 queues specifically
- Could not access 2026-era community patterns for multi-tenant architecture

**Flags for Phase-Specific Research:**
- **Phase 3 (Background Processing):** Verify Supervisor configuration with current Laravel 11 queue implementation
- **Phase 4 (Search Integration):** Research Elasticsearch 8.x + Laravel Scout integration specifics for multi-tenant setups
- **Phase 5 (External Integration):** Investigate current Shopify/Shopware PHP SDK capabilities and rate limiting best practices
