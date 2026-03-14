---
phase: 09-data-flows-caching-operations
plan: 02a
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Listeners/InvalidateTenantCache.php
  - app/Listeners/InvalidateProductCache.php
  - app/Listeners/InvalidateSyncLogCache.php
  - app/Providers/AppServiceProvider.php
  - app/Console/Commands/CacheWarm.php
autonomous: true
requirements:
  - CACHE-01
  - CACHE-02
  - CACHE-03
must_haves:
  truths:
    - "Event listeners invalidate cache automatically on model changes"
    - "InvalidateTenantCache clears tenant-related caches"
    - "InvalidateProductCache clears dashboard metrics cache"
    - "InvalidateSyncLogCache clears dashboard metrics cache"
    - "Event listeners registered in AppServiceProvider"
    - "Cache warming command available for deployment hooks"
  artifacts:
    - path: "app/Listeners/InvalidateTenantCache.php"
      provides: "Event listener for tenant cache invalidation"
      exports: ["handle()"]
      min_lines: 20
    - path: "app/Listeners/InvalidateProductCache.php"
      provides: "Event listener for product cache invalidation"
      exports: ["handle()"]
      min_lines: 20
    - path: "app/Listeners/InvalidateSyncLogCache.php"
      provides: "Event listener for sync log cache invalidation"
      exports: ["handle()"]
      min_lines: 20
    - path: "app/Providers/AppServiceProvider.php"
      provides: "Event listener registration"
      contains: "Tenant::created.*InvalidateTenantCache"
      min_lines: 35
    - path: "app/Console/Commands/CacheWarm.php"
      provides: "Artisan command for cache warming"
      exports: ["handle()", "signature"]
      min_lines: 40
  key_links:
    - from: "app/Providers/AppServiceProvider.php"
      to: "app/Listeners/InvalidateTenantCache.php"
      via: "Model event listener registration"
      pattern: "Tenant::created.*InvalidateTenantCache::class"
    - from: "app/Providers/AppServiceProvider.php"
      to: "app/Listeners/InvalidateProductCache.php"
      via: "Model event listener registration"
      pattern: "Product::created.*InvalidateProductCache::class"
    - from: "app/Providers/AppServiceProvider.php"
      to: "app/Listeners/InvalidateSyncLogCache.php"
      via: "Model event listener registration"
      pattern: "SyncLog::created.*InvalidateSyncLogCache::class"
    - from: "app/Listeners/InvalidateTenantCache.php"
      to: "Cache::forget()"
      via: "Cache invalidation on model changes"
      pattern: "Cache::forget.*agency:"
    - from: "app/Console/Commands/CacheWarm.php"
      to: "Cache::remember()"
      via: "Prime caches on deployment"
      pattern: "Cache::remember.*agency:"
---

<objective>
Build cache invalidation infrastructure with event listeners and warming command for automatic cache management.

Purpose: Ensure cache stays fresh automatically when data changes
Output: Event-driven cache invalidation and cache warming command
</objective>

<execution_context>
@/home/raihan/.claude/get-shit-done/workflows/execute-plan.md
@/home/raihan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-data-flows-caching-operations/09-CONTEXT.md
@.planning/phases/09-data-flows-caching-operations/09-RESEARCH.md
@.planning/phases/09-data-flows-caching-operations/09-00-CACHE-PLAN.md
@.planning/REQUIREMENTS.md
@.planning/STATE.md

# Key Models from Previous Phases

From Phase 3 (Tenant Management):
- **Tenant model** — UUID primary keys, status enum, encrypted credentials

From Phase 6 (Catalog Synchronization):
- **Product model** — tenant_id, name, sku, price, stock_status
- **SyncLog model** — tenant_id, status, products_synced, timestamps

# Cache Configuration from CONTEXT.md

**Cache Key Structure:**
- Hierarchical with colons: `agency:{type}:{id}`
- Per-tenant metrics: `agency:dashboard:metrics:{tenant_uuid}`
- Tenant list: `agency:tenants:list`
- Global metrics: `agency:dashboard:global`

**Cache Invalidation Strategy:**
- Event listeners on model events (created, updated, deleted)
- Tenant events → Clear tenant list, per-tenant metrics, global metrics
- Product events → Clear per-tenant metrics
- SyncLog events → Clear per-tenant metrics

**Cache Warming:**
- Deploy hook via Artisan command `php artisan cache:warm`
- Optional --tenant flag for selective warming
- Warms tenant list cache by default
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Create InvalidateTenantCache event listener</name>
  <files>app/Listeners/InvalidateTenantCache.php</files>
  <behavior>
    Test 1: Listener clears agency:tenants:list cache
    Test 2: Listener clears agency:dashboard:metrics:{tenant_id} cache
    Test 3: Listener clears agency:dashboard:global cache
    Test 4: Listener handles tenant created event
    Test 5: Listener handles tenant updated event
    Test 6: Listener handles tenant deleted event
  </behavior>
  <action>
    Create app/Listeners/InvalidateTenantCache.php:

    ```php
    <?php

    namespace App\Listeners;

    use App\Models\Tenant;
    use Illuminate\Support\Facades\Cache;

    class InvalidateTenantCache
    {
        public function handle(Tenant $tenant): void
        {
            // Clear tenant list cache
            Cache::forget('agency:tenants:list');

            // Clear tenant-specific dashboard metrics
            Cache::forget("agency:dashboard:metrics:{$tenant->id}");

            // Clear global metrics
            Cache::forget('agency:dashboard:global');
        }
    }
    ```

    Key points:
    - Clears tenant list cache (shared across all users)
    - Clears per-tenant dashboard metrics cache
    - Clears global metrics cache
    - Called on tenant created, updated, deleted events
    - Simple, focused, single-responsibility listener
  </action>
  <verify>
    <automated>php artisan test --filter=InvalidateTenantCacheTest</automated>
  </verify>
  <done>InvalidateTenantCache listener created to clear tenant-related caches</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Create InvalidateProductCache event listener</name>
  <files>app/Listeners/InvalidateProductCache.php</files>
  <behavior>
    Test 1: Listener clears agency:dashboard:metrics:{tenant_id} cache
    Test 2: Listener handles product created event
    Test 3: Listener handles product updated event
    Test 4: Listener handles product deleted event
    Test 5: Listener reads tenant_id from product model
  </behavior>
  <action>
    Create app/Listeners/InvalidateProductCache.php:

    ```php
    <?php

    namespace App\Listeners;

    use App\Models\Product;
    use Illuminate\Support\Facades\Cache;

    class InvalidateProductCache
    {
        public function handle(Product $product): void
        {
            // Clear tenant-specific dashboard metrics (product count changes)
            Cache::forget("agency:dashboard:metrics:{$product->tenant_id}");
        }
    }
    ```

    Key points:
    - Clears per-tenant dashboard metrics cache
    - Product changes affect dashboard metrics (product counts)
    - Reads tenant_id from product relationship
    - Called on product created, updated, deleted events
  </action>
  <verify>
    <automated>php artisan test --filter=InvalidateProductCacheTest</automated>
  </verify>
  <done>InvalidateProductCache listener created to clear product-related caches</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Create InvalidateSyncLogCache event listener</name>
  <files>app/Listeners/InvalidateSyncLogCache.php</files>
  <behavior>
    Test 1: Listener clears agency:dashboard:metrics:{tenant_id} cache
    Test 2: Listener handles sync log created event
    Test 3: Listener handles sync log updated event
    Test 4: Listener reads tenant_id from sync log model
  </behavior>
  <action>
    Create app/Listeners/InvalidateSyncLogCache.php:

    ```php
    <?php

    namespace App\Listeners;

    use App\Models\SyncLog;
    use Illuminate\Support\Facades\Cache;

    class InvalidateSyncLogCache
    {
        public function handle(SyncLog $syncLog): void
        {
            // Clear tenant-specific dashboard metrics (last sync status changes)
            Cache::forget("agency:dashboard:metrics:{$syncLog->tenant_id}");
        }
    }
    ```

    Key points:
    - Clears per-tenant dashboard metrics cache
    - Sync log changes affect dashboard metrics (last sync time, status)
    - Reads tenant_id from sync log relationship
    - Called on sync log created, updated events
  </action>
  <verify>
    <automated>php artisan test --filter=InvalidateSyncLogCacheTest</automated>
  </verify>
  <done>InvalidateSyncLogCache listener created to clear sync log-related caches</done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Register event listeners in AppServiceProvider</name>
  <files>app/Providers/AppServiceProvider.php</files>
  <behavior>
    Test 1: Tenant::created event registered with InvalidateTenantCache
    Test 2: Tenant::updated event registered with InvalidateTenantCache
    Test 3: Tenant::deleted event registered with InvalidateTenantCache
    Test 4: Product::created event registered with InvalidateProductCache
    Test 5: Product::updated event registered with InvalidateProductCache
    Test 6: Product::deleted event registered with InvalidateProductCache
    Test 7: SyncLog::created event registered with InvalidateSyncLogCache
    Test 8: SyncLog::updated event registered with InvalidateSyncLogCache
  </behavior>
  <action>
    Update app/Providers/AppServiceProvider.php boot() method:

    ```php
    <?php

    namespace App\Providers;

    use App\Models\Tenant;
    use App\Models\Product;
    use App\Models\SyncLog;
    use App\Listeners\InvalidateTenantCache;
    use App\Listeners\InvalidateProductCache;
    use App\Listeners\InvalidateSyncLogCache;
    use Illuminate\Support\ServiceProvider;

    class AppServiceProvider extends ServiceProvider
    {
        public function boot(): void
        {
            // Tenant cache invalidation
            Tenant::created(InvalidateTenantCache::class);
            Tenant::updated(InvalidateTenantCache::class);
            Tenant::deleted(InvalidateTenantCache::class);

            // Product cache invalidation
            Product::created(InvalidateProductCache::class);
            Product::updated(InvalidateProductCache::class);
            Product::deleted(InvalidateProductCache::class);

            // SyncLog cache invalidation
            SyncLog::created(InvalidateSyncLogCache::class);
            SyncLog::updated(InvalidateSyncLogCache::class);
        }
    }
    ```

    Key points:
    - Register all event listeners in boot() method
    - Tenant events (created, updated, deleted) → InvalidateTenantCache
    - Product events (created, updated, deleted) → InvalidateProductCache
    - SyncLog events (created, updated) → InvalidateSyncLogCache
    - Automatic cache invalidation without manual Cache::forget() calls
  </action>
  <verify>
    <automated>php artisan test --filter=CacheInvalidationTest</automated>
  </verify>
  <done>Event listeners registered for automatic cache invalidation</done>
</task>

<task type="auto" tdd="true">
  <name>Task 5: Add cache warming command</name>
  <files>app/Console/Commands/CacheWarm.php</files>
  <behavior>
    Test 1: Command signature is "cache:warm {--tenant=*}"
    Test 2: Command warms tenant list cache by default
    Test 3: Command with --tenant=* warms all tenants' dashboard metrics
    Test 4: Command with --tenant={uuid} warms specific tenant's dashboard metrics
    Test 5: Command outputs "Cache warmed successfully" message
  </behavior>
  <action>
    Create app/Console/Commands/CacheWarm.php:

    ```php
    <?php

    namespace App\Console\Commands;

    use App\Models\Tenant;
    use App\Models\SyncLog;
    use Illuminate\Console\Command;
    use Illuminate\Support\Facades\Cache;

    class CacheWarm extends Command
    {
        protected $signature = 'cache:warm {--tenant=* : Warm cache for specific tenant(s)}';

        protected $description = 'Warm Laravel caches for improved performance';

        public function handle(): int
        {
            $this->info('Warming caches...');

            // Warm tenant list cache
            $this->warmTenantList();

            // Warm dashboard metrics for tenants
            $tenants = $this->option('tenant');

            if (empty($tenants) || in_array('*', $tenants)) {
                // Warm all tenants
                $this->warmAllTenantsMetrics();
            } else {
                // Warm specific tenants
                foreach ($tenants as $tenantId) {
                    $this->warmTenantMetrics($tenantId);
                }
            }

            $this->info('Cache warmed successfully!');

            return Command::SUCCESS;
        }

        private function warmTenantList(): void
        {
            $this->info('Warming tenant list cache...');

            // Prime tenant list cache
            Cache::remember('agency:tenants:list', 900, function () {
                return Tenant::select(['id', 'name', 'slug', 'status'])
                    ->orderBy('name')
                    ->get();
            });
        }

        private function warmAllTenantsMetrics(): void
        {
            $this->info('Warming dashboard metrics for all tenants...');

            Tenant::select('id')->chunk(100, function ($tenants) {
                foreach ($tenants as $tenant) {
                    $this->warmTenantMetrics($tenant->id);
                }
            });
        }

        private function warmTenantMetrics(string $tenantId): void
        {
            $this->line("  Warming metrics for tenant: {$tenantId}");

            // Prime dashboard metrics cache
            Cache::remember("agency:dashboard:metrics:{$tenantId}", 300, function () use ($tenantId) {
                $lastSync = SyncLog::where('tenant_id', $tenantId)
                    ->orderBy('created_at', 'desc')
                    ->first(['created_at', 'status']);

                return [
                    'last_sync' => $lastSync,
                    'synced_at' => $lastSync?->created_at,
                ];
            });
        }
    }
    ```

    Key points:
    - Command signature: `php artisan cache:warm {--tenant=*}`
    - Warms tenant list cache by default
    - Optional --tenant flag to warm specific tenants
    - Outputs progress information
    - Uses same cache keys and TTL as controllers
    - No database queries in production (uses cached data)
  </action>
  <verify>
    <automated>php artisan cache:warm --help | grep "cache:warm"</automated>
  </verify>
  <done>CacheWarm command created for deployment hooks and manual cache warming</done>
</task>

</tasks>

<verification>

### Overall Phase Checks

- [ ] InvalidateTenantCache listener clears tenant-related caches
- [ ] InvalidateProductCache listener clears product-related caches
- [ ] InvalidateSyncLogCache listener clears sync log-related caches
- [ ] Event listeners registered in AppServiceProvider
- [ ] CacheWarm command executable
- [ ] All tests passing (InvalidateTenantCacheTest, InvalidateProductCacheTest, InvalidateSyncLogCacheTest, CacheInvalidationTest)

### Integration Verification

- [ ] Cache keys visible in Redis CLI: `redis-cli KEYS "agency:*"`
- [ ] Cache invalidation triggers on model changes
- [ ] Event listeners fire when models are created/updated/deleted
- [ ] Cache warming command primes caches successfully
- [ ] Manual cache clear works: `php artisan cache:clear`

</verification>

<success_criteria>

1. Event listeners invalidate cache automatically on tenant/product/sync log changes
2. Cache invalidation covers all relevant cache keys (tenant list, metrics, global)
3. Event listeners registered for all model events (created, updated, deleted)
4. Cache warming command available for deployment hooks
5. Command supports selective tenant warming via --tenant flag

</success_criteria>

<output>

After completion, create `.planning/phases/09-data-flows-caching-operations/09-02a-SUMMARY.md`

</output>
