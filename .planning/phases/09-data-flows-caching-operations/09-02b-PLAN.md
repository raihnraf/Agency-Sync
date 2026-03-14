---
phase: 09-data-flows-caching-operations
plan: 02b
type: execute
wave: 2
depends_on:
  - 09-02a
files_modified:
  - app/Http/Controllers/DashboardController.php
  - app/Http/Controllers/TenantController.php
  - routes/web.php
autonomous: true
requirements:
  - CACHE-01
  - CACHE-02
  - CACHE-03
must_haves:
  truths:
    - "Dashboard metrics cached with 5-minute TTL using Redis"
    - "Tenant list cached with 15-minute TTL using Redis"
    - "Cache keys follow hierarchical pattern (agency:type:id)"
    - "Per-tenant cache keys include tenant UUID for isolation"
    - "Cached endpoints return data without database queries on cache hit"
  artifacts:
    - path: "app/Http/Controllers/DashboardController.php"
      provides: "Cached dashboard metrics endpoint"
      contains: "Cache::remember.*agency:dashboard:metrics"
      min_lines: 40
    - path: "app/Http/Controllers/TenantController.php"
      provides: "Cached tenant list endpoint"
      contains: "Cache::remember.*agency:tenants:list"
      min_lines: 30
    - path: "routes/web.php"
      provides: "Web route registration for dashboard endpoints"
      contains: "Route::get.*dashboard/metrics"
      min_lines: 5
  key_links:
    - from: "resources/views/dashboard/dashboard.blade.php"
      to: "GET /dashboard/metrics"
      via: "Alpine.js fetch() call for dashboard data"
      pattern: "fetch.*dashboard/metrics"
    - from: "resources/views/dashboard/tenants/index.blade.php"
      to: "GET /tenants"
      via: "Alpine.js fetch() call for tenant list"
      pattern: "fetch.*tenants"
    - from: "app/Http/Controllers/DashboardController.php"
      to: "Cache::remember()"
      via: "Dashboard metrics caching with 5-minute TTL"
      pattern: "Cache::remember.*agency:dashboard:metrics.*300"
    - from: "app/Http/Controllers/TenantController.php"
      to: "Cache::remember()"
      via: "Tenant list caching with 15-minute TTL"
      pattern: "Cache::remember.*agency:tenants:list.*900"
---

<objective>
Implement Redis-based caching for dashboard metrics and tenant list endpoints with automatic invalidation via event listeners.

Purpose: Improve dashboard performance and reduce database load through intelligent caching
Output: Cached API responses with automatic invalidation
</objective>

<execution_context>
@/home/raihan/.claude/get-shit-done/workflows/execute-plan.md
@/home/raihan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-data-flows-caching-operations/09-CONTEXT.md
@.planning/phases/09-data-flows-caching-operations/09-RESEARCH.md
@.planning/phases/09-data-flows-caching-operations/09-00-CACHE-PLAN.md
@.planning/phases/09-data-flows-caching-operations/09-02a-PLAN.md
@.planning/REQUIREMENTS.md
@.planning/STATE.md

# Infrastructure from Plan 02a

From **09-02a-PLAN.md** (Infrastructure):
- **Event listeners created** — InvalidateTenantCache, InvalidateProductCache, InvalidateSyncLogCache
- **Event listeners registered** — All model events wired to cache invalidation
- **CacheWarm command created** — Artisan command for deployment hooks

# Cache Configuration from CONTEXT.md

**Cache Key Structure:**
- Hierarchical with colons: `agency:{type}:{id}`
- Per-tenant metrics: `agency:dashboard:metrics:{tenant_uuid}`
- Tenant list: `agency:tenants:list`
- Global metrics: `agency:dashboard:global`
- Configurable prefix via CACHE_PREFIX env var (default: `agency`)

**Cache Expiration:**
- Dashboard metrics: 5-minute TTL (300 seconds)
- Tenant list: 15-minute TTL (900 seconds)
- Global metrics: 10-minute TTL (600 seconds)

# Existing Infrastructure

- **Redis container** running from Phase 1
- **QUEUE_CONNECTION=redis** configured from Phase 4
- **Tenant, Product, SyncLog models** available from previous phases
- **DashboardController** exists from Phase 7
- **Alpine.js frontend** from Phase 7 for API calls

# UI Integration Points

Frontend will call these endpoints via Alpine.js fetch():
- Dashboard view → GET /dashboard/metrics
- Tenant list view → GET /tenants
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Add caching to DashboardController metrics endpoint</name>
  <files>app/Http/Controllers/DashboardController.php</files>
  <behavior>
    Test 1: Dashboard metrics endpoint uses Cache::remember()
    Test 2: Cache key format is agency:dashboard:metrics:{tenant_id}
    Test 3: Cache TTL is 300 seconds (5 minutes)
    Test 4: Cached metrics include last sync data (created_at, status)
    Test 5: Cache miss triggers database query
    Test 6: Cache hit returns cached data without query
  </behavior>
  <action>
    Update app/Http/Controllers/DashboardController.php to add caching:

    ```php
    <?php

    namespace App\Http\Controllers;

    use App\Models\SyncLog;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Cache;

    class DashboardController extends Controller
    {
        public function metrics(Request $request)
        {
            $tenantId = $request->header('X-Tenant-ID');

            // Cache per-tenant metrics for 5 minutes
            $metrics = Cache::remember("agency:dashboard:metrics:{$tenantId}", 300, function () use ($tenantId) {
                $lastSync = SyncLog::where('tenant_id', $tenantId)
                    ->orderBy('created_at', 'desc')
                    ->first(['created_at', 'status', 'products_synced']);

                return [
                    'last_sync' => $lastSync,
                    'synced_at' => $lastSync?->created_at,
                    'last_sync_status' => $lastSync?->status->value,
                ];
            });

            return response()->json(['data' => $metrics]);
        }
    }
    ```

    Key points:
    - Uses Cache::remember() with 5-minute TTL (300 seconds)
    - Cache key includes tenant UUID for multi-tenant isolation
    - Returns last sync data (created_at, status, products_synced)
    - Automatic cache invalidation via InvalidateSyncLogCache listener from Plan 02a
    - Hierarchical key pattern: agency:dashboard:metrics:{tenant_id}
  </action>
  <verify>
    <automated>php artisan test --filter=DashboardMetricsCacheTest</automated>
  </verify>
  <done>Dashboard metrics endpoint cached with 5-minute TTL</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Add caching to TenantController list endpoint</name>
  <files>app/Http/Controllers/TenantController.php</files>
  <behavior>
    Test 1: Tenant list endpoint uses Cache::remember()
    Test 2: Cache key is agency:tenants:list
    Test 3: Cache TTL is 900 seconds (15 minutes)
    Test 4: Cached list includes only id, name, slug, status fields
    Test 5: Cache miss triggers database query
    Test 6: Cache hit returns cached data without query
  </behavior>
  <action>
    Update app/Http/Controllers/TenantController.php to add caching:

    Add caching to index() method or create cachedTenantList() method:

    ```php
    <?php

    namespace App\Http\Controllers;

    use App\Http\Controllers\Controller;
    use App\Models\Tenant;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Cache;

    class TenantController extends Controller
    {
        public function index(Request $request)
        {
            // Cache tenant list for 15 minutes
            $tenants = Cache::remember('agency:tenants:list', 900, function () {
                return Tenant::select(['id', 'name', 'slug', 'status'])
                    ->orderBy('name')
                    ->get();
            });

            return response()->json(['data' => $tenants]);
        }

        // ... other methods (show, update, delete)
    }
    ```

    Key points:
    - Uses Cache::remember() with 15-minute TTL (900 seconds)
    - Cache key is 'agency:tenants:list' (shared across all users)
    - Selects only safe fields (no sensitive data like api_credentials)
    - Automatic cache invalidation via InvalidateTenantCache listener from Plan 02a
    - Sorted by name for consistent display
  </action>
  <verify>
    <automated>php artisan test --filter=TenantListCacheTest</automated>
  </verify>
  <done>Tenant list endpoint cached with 15-minute TTL</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Register web routes for cached endpoints</name>
  <files>routes/web.php</files>
  <behavior>
    Test 1: GET /dashboard/metrics route registered
    Test 2: GET /tenants route registered
    Test 3: All routes require authentication middleware
    Test 4: Routes use correct controller methods
  </behavior>
  <action>
    Update routes/web.php to register dashboard metrics route:

    ```php
    use App\Http\Controllers\DashboardController;
    use App\Http\Controllers\TenantController;

    Route::middleware('auth')->group(function () {
        // Dashboard routes
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);

        // Tenant routes
        Route::get('/tenants', [TenantController::class, 'index']);
    });
    ```

    Key points:
    - All routes require authentication middleware
    - Routes follow RESTful conventions
    - Dashboard metrics route: GET /dashboard/metrics
    - Tenant list route: GET /tenants
    - These routes will be called by Alpine.js fetch() in frontend
  </action>
  <verify>
    <automated>php artisan route:list --path=dashboard --path=tenants | grep -E "GET|metrics"</automated>
  </verify>
  <done>Web routes registered for cached dashboard and tenant endpoints</done>
</task>

</tasks>

<verification>

### Overall Phase Checks

- [ ] Dashboard metrics endpoint uses Cache::remember() with 5-minute TTL
- [ ] Tenant list endpoint uses Cache::remember() with 15-minute TTL
- [ ] Cache keys follow hierarchical pattern (agency:type:id)
- [ ] Per-tenant cache keys include tenant UUID
- [ ] Web routes registered and require authentication
- [ ] All tests passing (DashboardMetricsCacheTest, TenantListCacheTest)

### Integration Verification

- [ ] Redis cache backend configured (from Phase 4)
- [ ] Cache keys visible in Redis CLI: `redis-cli KEYS "agency:*"`
- [ ] Cache invalidation triggers on model changes (from Plan 02a)
- [ ] Dashboard metrics load faster on subsequent requests
- [ ] Tenant list doesn't query database after first load
- [ ] Cache warming command primes caches successfully (from Plan 02a)

### UI Integration Verification

- [ ] Dashboard view calls GET /dashboard/metrics via Alpine.js
- [ ] Tenant list view calls GET /tenants via Alpine.js
- [ ] Frontend receives cached responses after first load
- [ ] Cache hit reduces page load time

</verification>

<success_criteria>

1. Dashboard metrics cached for 5 minutes using Redis
2. Tenant list cached using Cache::remember() with 15-minute TTL
3. Cache invalidates automatically on tenant/product/sync log changes (from Plan 02a)
4. Cache keys follow hierarchical pattern for easy debugging
5. Per-tenant cache keys include tenant UUID for isolation
6. Web routes registered for frontend consumption
7. Cached endpoints improve dashboard performance

</success_criteria>

<output>

After completion, create `.planning/phases/09-data-flows-caching-operations/09-02b-SUMMARY.md`

</output>
