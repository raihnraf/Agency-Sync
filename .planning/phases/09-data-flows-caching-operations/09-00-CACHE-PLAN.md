---
phase: 09-data-flows-caching-operations
plan: 00-CACHE
type: execute
wave: 0
depends_on: []
files_modified:
  - tests/Feature/DashboardMetricsCacheTest.php
  - tests/Feature/TenantListCacheTest.php
  - tests/Feature/CacheInvalidationTest.php
  - tests/Unit/InvalidateTenantCacheTest.php
  - tests/Unit/InvalidateProductCacheTest.php
  - tests/Unit/InvalidateSyncLogCacheTest.php
autonomous: true
requirements:
  - CACHE-01
  - CACHE-02
  - CACHE-03
must_haves:
  truths:
    - "Test stub files exist for all caching features"
    - "Tests specify expected behavior before implementation"
    - "Tests provide clear verification criteria for implementation"
  artifacts:
    - path: "tests/Feature/DashboardMetricsCacheTest.php"
      provides: "Test stub for dashboard metrics caching"
      min_lines: 25
    - path: "tests/Feature/TenantListCacheTest.php"
      provides: "Test stub for tenant list caching"
      min_lines: 25
    - path: "tests/Feature/CacheInvalidationTest.php"
      provides: "Test stub for cache invalidation on model changes"
      min_lines: 30
    - path: "tests/Unit/InvalidateTenantCacheTest.php"
      provides: "Test stub for tenant cache invalidation listener"
      min_lines: 20
    - path: "tests/Unit/InvalidateProductCacheTest.php"
      provides: "Test stub for product cache invalidation listener"
      min_lines: 20
    - path: "tests/Unit/InvalidateSyncLogCacheTest.php"
      provides: "Test stub for sync log cache invalidation listener"
      min_lines: 20
  key_links:
    - from: "tests/Feature/DashboardMetricsCacheTest.php"
      to: "app/Http/Controllers/DashboardController.php"
      via: "Cache::remember() pattern tests"
      pattern: "Cache::remember.*agency:dashboard"
    - from: "tests/Unit/InvalidateTenantCacheTest.php"
      to: "app/Listeners/InvalidateTenantCache.php"
      via: "Event listener unit tests"
      pattern: "InvalidateTenantCache"
    - from: "tests/Feature/CacheInvalidationTest.php"
      to: "app/Providers/AppServiceProvider.php"
      via: "Event listener registration tests"
      pattern: "Event::listen"
---

<objective>
Create test stubs that specify expected behavior for caching features, enabling TDD implementation in subsequent plans.

Purpose: Define clear test expectations before implementation, ensuring testable requirements and verification criteria
Output: Test stub files with placeholder assertions that document expected behavior
</objective>

<execution_context>
@/home/raihan/.claude/get-shit-done/workflows/execute-plan.md
@/home/raihan/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-data-flows-caching-operations/09-CONTEXT.md
@.planning/phases/09-data-flows-caching-operations/09-RESEARCH.md
@.planning/REQUIREMENTS.md
@.planning/STATE.md

# Key Models and Patterns from Previous Phases

From Phase 4 (Background Processing):
- **JobStatus model** (`app/Models/JobStatus.php`) — Tracks job lifecycle with status enum (pending, running, completed, failed)
- **TenantAwareJob base class** (`app/Jobs/TenantAwareJob.php`) — Abstract base for tenant-aware queue jobs
- **SetTenantContext middleware** — Restores tenant context during job execution
- **QueueJobTracker service** — Automatic status tracking via queue events

From Phase 3 (Tenant Management):
- **Tenant model** (`app/Models/Tenant.php`) — UUID primary keys, status enum, encrypted credentials
- **User-Tenant relationship** — Many-to-many with pivot data

From Phase 6 (Catalog Synchronization):
- **SyncLog model** (`app/Models/SyncLog.php`) — tenant relationship, status fields, timestamps
- **Product model** (`app/Models/Product.php`) — Scout searchable, tenant-scoped

From Phase 7 (Admin Dashboard):
- **Alpine.js components** — Toast notification component for export ready notifications
- **Blade + Alpine.js patterns** — Client-side API calls, loading states, error handling
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Create test stub for dashboard metrics caching</name>
  <files>tests/Feature/DashboardMetricsCacheTest.php</files>
  <behavior>
    Test 1: Dashboard metrics cached with 5-minute TTL
    Test 2: Cache key format: agency:dashboard:metrics:{tenant_id}
    Test 3: Cached metrics include total_products count
    Test 4: Cached metrics include last_sync timestamp and status
    Test 5: Cache miss triggers fresh data generation
    Test 6: Cache hit returns cached data without database query
  </behavior>
  <action>
    Create tests/Feature/DashboardMetricsCacheTest.php with placeholder assertions:

    - Use RefreshDatabase trait
    - Create Tenant and Product factories
    - Test cache storage: Call dashboard metrics API, assert Cache::has('agency:dashboard:metrics:{tenant_id}')
    - Test TTL: Assert cache expires after 300 seconds (use Cache::get() with time travel mock or check cache TTL)
    - Test cache key format: Assert key includes tenant UUID
    - Test metrics content: Assert cached data includes total_products, last_sync fields
    - Test cache miss: Clear cache, call API, assert fresh data generated and cached
    - Test cache hit: Call API twice, assert second call doesn't execute DB query (use QueryCount spy or check log)
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=DashboardMetricsCacheTest</automated>
  </verify>
  <done>Test file created with 6 test cases covering dashboard metrics caching (TTL, key format, content, cache miss/hit behavior)</done>
</task>

<task type="auto" tdd="true">
  <name>Task 2: Create test stub for tenant list caching</name>
  <files>tests/Feature/TenantListCacheTest.php</files>
  <behavior>
    Test 1: Tenant list cached with 15-minute TTL
    Test 2: Cache key: agency:tenants:list
    Test 3: Cached list includes id, name, slug, status fields
    Test 4: Cache miss triggers fresh tenant query
    Test 5: Cache hit returns cached list without database query
  </behavior>
  <action>
    Create tests/Feature/TenantListCacheTest.php with placeholder assertions:

    - Use RefreshDatabase trait
    - Create Tenant factory (10 tenants)
    - Test cache storage: Call tenant list API, assert Cache::has('agency:tenants:list')
    - Test TTL: Assert cache expires after 900 seconds
    - Test cache key format: Assert key matches 'agency:tenants:list'
    - Test cached content: Assert list includes only id, name, slug, status (no sensitive fields)
    - Test cache miss: Clear cache, call API, assert fresh data cached
    - Test cache hit: Call API twice, assert second call uses cache (check query count)
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=TenantListCacheTest</automated>
  </verify>
  <done>Test file created with 5 test cases covering tenant list caching (TTL, key format, content, cache behavior)</done>
</task>

<task type="auto" tdd="true">
  <name>Task 3: Create test stub for cache invalidation</name>
  <files>tests/Feature/CacheInvalidationTest.php</files>
  <behavior>
    Test 1: Tenant creation clears agency:tenants:list cache
    Test 2: Tenant update clears agency:dashboard:metrics:{tenant_id} cache
    Test 3: Tenant deletion clears agency:tenants:list and agency:dashboard:metrics:{tenant_id}
    Test 4: Product creation clears agency:dashboard:metrics:{tenant_id} cache
    Test 5: Product update clears agency:dashboard:metrics:{tenant_id} cache
    Test 6: Product deletion clears agency:dashboard:metrics:{tenant_id} cache
    Test 7: SyncLog creation clears agency:dashboard:metrics:{tenant_id} cache
    Test 8: SyncLog update clears agency:dashboard:metrics:{tenant_id} cache
  </behavior>
  <action>
    Create tests/Feature/CacheInvalidationTest.php with placeholder assertions:

    - Use RefreshDatabase trait
    - Create test data and prime caches (call APIs to populate cache)
    - Test tenant creation: Create tenant, assert Cache::missing('agency:tenants:list')
    - Test tenant update: Update tenant, assert Cache::missing("agency:dashboard:metrics:{$tenantId}")
    - Test tenant deletion: Delete tenant, assert both caches cleared
    - Test product events: Create/update/delete product, assert metrics cache cleared
    - Test sync log events: Create/update sync log, assert metrics cache cleared
    - Test event listener registration: Assert listeners registered in AppServiceProvider
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=CacheInvalidationTest</automated>
  </verify>
  <done>Test file created with 8 test cases covering automatic cache invalidation on model changes (tenant, product, sync log events)</done>
</task>

<task type="auto" tdd="true">
  <name>Task 4: Create test stub for InvalidateTenantCache listener</name>
  <files>tests/Unit/InvalidateTenantCacheTest.php</files>
  <behavior>
    Test 1: Listener clears agency:tenants:list cache
    Test 2: Listener clears agency:dashboard:metrics:{tenant_id} cache
    Test 3: Listener clears agency:dashboard:global cache
    Test 4: Listener handle() method accepts Tenant model
    Test 5: Listener uses Cache::forget() for invalidation
  </behavior>
  <action>
    Create tests/Unit/InvalidateTenantCacheTest.php with placeholder assertions:

    - Use RefreshDatabase trait
    - Create Tenant factory
    - Prime caches: Cache::put('agency:tenants:list', []), Cache::put("agency:dashboard:metrics:{$tenantId}", [])
    - Test tenant list invalidation: Call listener->handle($tenant), assert Cache::missing('agency:tenants:list')
    - Test metrics invalidation: Call listener->handle($tenant), assert Cache::missing("agency:dashboard:metrics:{$tenantId}")
    - Test global cache invalidation: Call listener->handle($tenant), assert Cache::missing('agency:dashboard:global')
    - Test Cache::forget() calls: Mock Cache facade, assert forget called with correct keys
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=InvalidateTenantCacheTest</automated>
  </verify>
  <done>Test file created with 5 test cases covering InvalidateTenantCache listener behavior</done>
</task>

<task type="auto" tdd="true">
  <name>Task 5: Create test stub for InvalidateProductCache listener</name>
  <files>tests/Unit/InvalidateProductCacheTest.php</files>
  <behavior>
    Test 1: Listener clears agency:dashboard:metrics:{tenant_id} cache
    Test 2: Listener handle() method accepts Product model
    Test 3: Listener reads tenant_id from product model
    Test 4: Listener uses Cache::forget() for invalidation
    Test 5: Listener only clears tenant-specific metrics (not global or tenant list)
  </behavior>
  <action>
    Create tests/Unit/InvalidateProductCacheTest.php with placeholder assertions:

    - Use RefreshDatabase trait
    - Create Product and Tenant factories
    - Prime cache: Cache::put("agency:dashboard:metrics:{$tenantId}", [])
    - Test metrics invalidation: Call listener->handle($product), assert Cache::missing("agency:dashboard:metrics:{$tenantId}")
    - Test tenant_id access: Assert listener reads $product->tenant_id correctly
    - Test selective invalidation: Prime all caches, call listener, assert only metrics cache cleared
    - Test Cache::forget() calls: Mock Cache facade, assert forget called once with correct key
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=InvalidateProductCacheTest</automated>
  </verify>
  <done>Test file created with 5 test cases covering InvalidateProductCache listener behavior</done>
</task>

<task type="auto" tdd="true">
  <name>Task 6: Create test stub for InvalidateSyncLogCache listener</name>
  <files>tests/Unit/InvalidateSyncLogCacheTest.php</files>
  <behavior>
    Test 1: Listener clears agency:dashboard:metrics:{tenant_id} cache
    Test 2: Listener handle() method accepts SyncLog model
    Test 3: Listener reads tenant_id from sync log model
    Test 4: Listener uses Cache::forget() for invalidation
    Test 5: Listener only clears tenant-specific metrics (not global or tenant list)
  </behavior>
  <action>
    Create tests/Unit/InvalidateSyncLogCacheTest.php with placeholder assertions:

    - Use RefreshDatabase trait
    - Create SyncLog and Tenant factories
    - Prime cache: Cache::put("agency:dashboard:metrics:{$tenantId}", [])
    - Test metrics invalidation: Call listener->handle($syncLog), assert Cache::missing("agency:dashboard:metrics:{$tenantId}")
    - Test tenant_id access: Assert listener reads $syncLog->tenant_id correctly
    - Test selective invalidation: Prime all caches, call listener, assert only metrics cache cleared
    - Test Cache::forget() calls: Mock Cache facade, assert forget called once with correct key
    - Use $this->assertTrue(true) placeholders
  </action>
  <verify>
    <automated>php artisan test --filter=InvalidateSyncLogCacheTest</automated>
  </verify>
  <done>Test file created with 5 test cases covering InvalidateSyncLogCache listener behavior</done>
</task>

</tasks>

<verification>

### Overall Phase Checks

- [ ] All 6 cache test stub files created in tests/ directory
- [ ] Test files use appropriate traits (RefreshDatabase for feature tests)
- [ ] Test files reference models that exist (Tenant, Product, SyncLog)
- [ ] Test cases cover all caching requirements (CACHE-01/02/03)
- [ ] Placeholder assertions use $this->assertTrue(true) for Nyquist compliance
- [ ] Test names clearly describe expected behavior
- [ ] Test files committed to git

</verification>

<success_criteria>

1. All cache test stub files exist and are syntactically valid PHP
2. Running `php artisan test` executes all stub tests (all pass with placeholder assertions)
3. Test cases provide clear specifications for implementation in subsequent plans
4. Tests reference correct models and patterns from previous phases
5. All caching requirement IDs (CACHE-*) have corresponding test coverage
6. Unit test stubs created for individual event listeners (InvalidateTenantCache, InvalidateProductCache, InvalidateSyncLogCache)

</success_criteria>

<output>

After completion, create `.planning/phases/09-data-flows-caching-operations/09-00-CACHE-SUMMARY.md`

</output>
