# Pitfalls Research

**Domain:** Laravel 11 Multi-tenant E-commerce Agency Backend with Elasticsearch
**Researched:** 2026-03-13
**Confidence:** MEDIUM

## Critical Pitfalls

### Pitfall 1: Tenant Data Leakage via Global Scopes

**What goes wrong:**
Queries accidentally return data from other tenants due to missing Global Scopes, raw SQL queries without tenant filtering, or relationship eager loading that bypasses tenant isolation. Users see products, sync jobs, or client data from agencies they shouldn't access.

**Why it happens:**
Developers forget to add `tenant_id` to every query, use `DB::table()` or `Model::withoutGlobalScopes()`, or define relationships that don't automatically scope to tenant. Laravel's default behavior doesn't enforce tenant isolation — you must implement it explicitly.

**How to avoid:**
1. Implement a `TenantScope` global scope on all tenant-aware models
2. Use a middleware that sets `tenant_id` in request context and validates access
3. Never use `DB::table()` directly — always go through Eloquent models with scopes
4. Add database-level `CHECK` constraints on `tenant_id` columns (MySQL 8.0+)
5. Write tests that create multiple tenants and verify no cross-tenant data access
6. Use query-level assertions in tests: `assertDatabaseHas('products', ['tenant_id' => $wrongTenant])` should fail

**Warning signs:**
- Count queries return higher numbers than expected
- Dashboard shows products from unknown clients
- Sync jobs appear for wrong agencies
- Tests occasionally fail with "found unexpected records"

**Phase to address:**
Phase 1 (Foundation) — Must be implemented before any tenant data is created. This is architectural, not add-on.

---

### Pitfall 2: Elasticsearch Sync Race Conditions

**What goes wrong:**
Product updates in MySQL don't reflect in Elasticsearch immediately, or worse — Elasticsearch shows stale/inconsistent data. Searches return deleted products, miss new products, or show outdated prices. Sync jobs overwrite newer data with older data.

**Why it happens:**
Race conditions between:
- Database transaction commits and Elasticsearch indexing
- Multiple sync jobs running simultaneously for same product
- API webhooks arriving faster than queue processing
- Manual database updates bypassing model events

Laravel Scout's `shouldSyncSearch()` doesn't prevent concurrent updates.

**How to avoid:**
1. Use database transactions + Elasticsearch indexing in atomic operation pattern:
   ```php
   DB::transaction(function () {
       $product->update($data);
       $product->searchable(); // Queued, happens after commit
   });
   ```
2. Implement optimistic locking with `updated_at` timestamp checks in sync jobs
3. Use Laravel Scout's queue configuration with `queue` database driver for reliability
4. Add sync job uniqueness by `tenant_id + product_id` to prevent concurrent syncs
5. Implement "sync lag" monitoring — alert if Elasticsearch index age > 30 seconds
6. Create a reconciliation job that runs hourly to fix drift between MySQL and Elasticsearch

**Warning signs:**
- Search results count doesn't match database count
- Product prices differ between detail page and search
- Recently deleted products still appear in search
- Sync jobs pile up in `failed_jobs` table

**Phase to address:**
Phase 2 (Elasticsearch Integration) — Sync strategy must be designed before first product is indexed. Test with concurrent updates.

---

### Pitfall 3: Queue Job Tenant Context Loss

**What goes wrong:**
Background jobs (sync jobs, notifications, cleanup) run with wrong or no tenant context, causing them to:
- Query wrong database tables
- Send notifications to wrong users
- Update wrong Elasticsearch indices
- Log to wrong tenant channels

**Why it happens:**
Queue workers run in separate processes without HTTP request context. Tenant identification (from subdomain, header, or session) isn't available in worker. Jobs dispatch without capturing current tenant, or use `dispatch()` instead of `dispatch_sync()` in wrong places.

**How to avoid:**
1. Store `tenant_id` in job payload:
   ```php
   class SyncProductsJob implements ShouldQueue {
       public int $tenantId;
       public function __construct(public int $clientId) {
           $this->tenantId = Auth::user()->tenant_id;
       }
   }
   ```
2. Use middleware to set tenant context in queue workers:
   ```php
   Queue::before(function (JobProcessing $event) {
       if (isset($event->job->payload()['tenant_id'])) {
           Tenant::set($event->job->payload()['tenant_id']);
       }
   });
   ```
3. Add tenant context to all log entries in queued jobs
4. Test queued jobs by creating jobs for multiple tenants in same test
5. Use `dispatch_sync()` for jobs that must run in same request context (rare, but sometimes needed)

**Warning signs:**
- Jobs fail with "tenant not found" errors
- Logs show mixed tenant data in single job execution
- Notifications sent to users from wrong agencies
- Tests pass in browser but fail in queue workers

**Phase to address:**
Phase 1 (Foundation) — Queue architecture must include tenant context from first background job. Test with multi-tenant queue scenarios.

---

### Pitfall 4: N+1 Queries in Multi-Tenant Eager Loading

**What goes wrong:**
What appears to be "slow queries" is actually N+1 problem: fetching tenant's products (1 query) then loading each product's client, sync status, variants (N queries). At 10,000 products across 50 tenants, this generates 10,001 queries instead of 2-3.

**Why it happens:**
Developers forget `with()` when accessing relationships, or relationships are defined but not used consistently. Multi-tenant apps have more relationships (product → client → tenant → settings) making N+1 easier to miss.

**How to avoid:**
1. Enable query logging in development:
   ```php
   DB::listen(function ($query) {
       if ($query->time > 100) {
       Log::warning('Slow query', ['sql' => $query->sql, 'bindings' => $query->bindings]);
       }
   });
   ```
2. Use Laravel Debugbar or Telescope to identify N+1 queries
3. Eager load by default: `$products = Product::with('client', 'syncStatus')->get()`
4. Use `Lazy Eager Loading` (`load()`) when you can't eager load initially
5. Add query count assertions in tests: `assertQueryCount(5)` (custom helper)
6. Create repository classes that encapsulate common query patterns with eager loading

**Warning signs:**
- API responses take > 2 seconds for simple queries
- Database CPU spikes during normal usage
- Telescope shows hundreds of queries per request
- "Time to first byte" grows linearly with data size

**Phase to address:**
Phase 2 (API Development) — Must be profiled before public API launch. Load test with realistic multi-tenant data volumes.

---

### Pitfall 5: Redis Connection Pool Exhaustion

**What goes wrong:**
Under load (e.g., mass sync operations), Redis connections spawn faster than they close, exhausting connection pool. Queue workers stop processing, caching fails, and application becomes unresponsive. Restarting Redis temporarily fixes but doesn't prevent recurrence.

**Why it happens:**
Laravel creates new Redis connections for:
- Queue jobs (each worker process)
- Cache (if using Redis cache)
- Session storage (if using Redis sessions)
- Telescope/Debugbar data
- Custom Redis calls

Default `phpredis`/`predis` settings don't limit connections or reuse them efficiently.

**How to avoid:**
1. Configure Redis connection pool in `config/database.php`:
   ```php
   'redis' => [
       'client' => env('REDIS_CLIENT', 'phpredis'),
       'options' => [
           'persistent' => true, // Reuse connections
           'timeout' => 5.0,
       ],
       'default' => [
           'database' => env('REDIS_DB', 0),
           'max_connections' => 50, // Limit pool size
       ],
   ]
   ```
2. Use separate Redis instances for cache vs queue in Docker Compose
3. Monitor Redis `connected_clients` metric and alert at 80% capacity
4. Limit Supervisor queue workers: `num_procs = 4` not `num_procs = 10`
5. Implement backpressure in sync jobs — pause if queue depth > 1000
6. Add health check endpoint that reports Redis connection count

**Warning signs:**
- Queue workers stop picking up jobs despite jobs being available
- `Redis::connection()->getPool()->getIdleConnections()` returns 0
- "Too many connections" errors in Redis logs
- Docker stats show Redis container using 100% CPU

**Phase to address:**
Phase 1 (Infrastructure) — Redis configuration must be load-tested before production. Test with simulated sync load.

---

### Pitfall 6: Elasticsearch Mapping Mismatches

**What goes wrong:**
Search queries fail or return unexpected results because Elasticsearch mappings don't match actual data types. Text fields analyzed when they should be `keyword`, dates stored as strings, or nested objects not properly configured. Reindexing required to fix.

**Why it happens:**
Laravel Scout's default mapping uses dynamic mapping, which guesses types from first document. If first product has `price` as string "19.99", mapping becomes `text` not `float`. Subsequent products with numeric prices fail to index or query incorrectly.

**How to avoid:**
1. Explicitly define Elasticsearch mappings before first index:
   ```php
   // In ElasticsearchService
   $params = [
       'index' => 'products',
       'body' => [
           'mappings' => [
               'properties' => [
                   'name' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
                   'price' => ['type' => 'float'],
                   'tenant_id' => ['type' => 'integer'],
                   'client_id' => ['type' => 'integer'],
                   'created_at' => ['type' => 'date'],
               ]
           ]
       ]
   ];
   ```
2. Use `.raw` fields for exact matches and faceted search
3. Add migration-like system for mapping changes (version your indices)
4. Test with various data types before production — index test products with edge cases
5. Use `ignore_unmapped` option in queries to fail gracefully during migrations
6. Implement index aliases for zero-downtime reindexing

**Warning signs:**
- Scout import fails with "mapper_parsing_exception"
- Search returns no results for known products
- Sorting by price gives wrong order
- Kibana shows "dynamic mapping" warnings

**Phase to address:**
Phase 2 (Elasticsearch Integration) — Mappings must be defined before first product import. Test with diverse product data.

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Skipping global scopes for "admin only" queries | Faster query writing | Data leakage if admin role compromised | Never — use proper authorization instead |
| Using `Model::withoutGlobalScopes()` for performance | Marginal query speedup | Breaks tenant isolation guarantees | Only in read-only analytics jobs with explicit tenant filtering |
| Inline Elasticsearch sync instead of queued | Simpler code, immediate search | Request timeouts, race conditions | Never for web requests, acceptable only for console commands |
| Mixed tenant and non-tenant tables without clear separation | Less schema planning | Impossible to audit data access | Never — all tables should be explicitly tenant or global |
| Hardcoding tenant checks in controllers | Quick access control | Duplicated logic, easy to forget | Only in Phase 1, refactor to middleware in Phase 2 |
| Skipping job retry configuration | Less config思考 | Failed jobs pile up, manual intervention | Never — queue jobs must have `tries` and `backoff` |
| Using Redis for everything | Simpler infrastructure | Single point of failure, harder to scale | MVP only, split services before production |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| **Shopify API** | Storing access tokens in `.env` (not tenant-scoped) | Store encrypted tokens in `clients` table, one per client |
| **Shopware API** | Using sync API calls that block web requests | Queue all sync jobs, return 202 Accepted |
| **Elasticsearch** | Creating one index per tenant (hard to manage) | Single index with `tenant_id` field + filtered aliases |
| **Redis Queues** | Not setting `retry_after` correctly for long sync jobs | Calculate max sync time, set `retry_after = max_time + 60s` |
| **Supervisor** | Running queue workers as root (security risk) | Create dedicated `laravel` user in Docker, run workers as that user |
| **MySQL** | Not using `ON DELETE CASCADE` for tenant cleanup | Foreign key constraints ensure orphaned records don't exist |
| **Docker** | Binding Redis to 0.0.0.0 (exposed to internet) | Use Docker networks, expose only on localhost for development |
| **GitHub Actions** | Caching vendor/ but not node_modules/ (slow builds) | Cache both, but use `composer install` not `composer update` |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| **Mass sync without chunking** | Memory exhaustion, PHP timeouts | Use `Job::chunk()` or cursor-based iteration | At 1,000+ products per sync |
| **Elasticsearch deep pagination** | Queries slow down at page 100+ | Use `search_after` for pagination instead of `from/size` | At 10,000+ results |
| **Not using database read replicas** | Write lock slows read queries | Configure `mysql.read` connection in `config/database.php` | At 1,000+ concurrent users |
| **Synchronous Elasticsearch reindex** | API unresponsive during reindex | Queue reindex jobs, use index aliases | At any index size > 10,000 documents |
| **Logging every queue job** | Redis disk fills up, slow writes | Log only failed jobs + sampling for success | At 10,000+ jobs per hour |
| **Not caching tenant configuration** | Database queried on every request | Cache `tenants` table with 1-hour TTL | At 100+ requests per second |
| **Full table scans in multi-tenant queries** | Queries slow as data grows | Composite index on `(tenant_id, created_at)` or `(tenant_id, status)` | At 100,000+ records per table |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| **Tenant enumeration via sequential IDs** | Attackers guess client URLs, access data | Use UUIDs for `client_id`, or random IDs with `Str::random()` |
| **Missing tenant validation in API routes** | Users can impersonate other tenants | Add `TenantMatchMiddleware` to all API routes |
| **Storing API tokens in plaintext** | Tokens leaked in logs or backups | Encrypt tokens using Laravel's `encrypt()` before database insert |
| **Allowing arbitrary Elasticsearch queries** | Users can dump entire index | Whitelist allowed search fields, validate query structure |
| **Not validating webhook signatures** | Fake webhooks can trigger unwanted syncs | Verify HMAC signatures for Shopify/Shopware webhooks |
| **Background jobs processing without tenant isolation** | Jobs process data from wrong tenants | Always validate `tenant_id` in job `handle()` method |
| **Docker containers running as root** | Container escape = root on host | Use `USER laravel` in Dockerfile, run as non-root |
| **Exposing Supervisor/Redis ports** | Unauthorized access to infrastructure | Bind to localhost only, use Docker networks |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| **No progress indication for mass sync** | Users think system hung, resubmit sync | Emit broadcast events, show progress bar in dashboard |
| **Search results don't show tenant context** | Users confused which client product belongs to | Always display client name/logo in search results |
| **Bulk operations don't show error details** | Users can't fix failing imports | Return detailed error log with row numbers and validation messages |
| **API responses include all fields** | Slow responses, over-fetching | Use API resources (`JsonResource`) to shape responses per endpoint |
| **No rate limiting on sync endpoints** | Users overload system with syncs | Implement `throttle:60,1` middleware on sync routes |
| **Hard-to-read job status codes** | Users don't know what "STATUS_PROCESSING" means | Use human-readable labels with machine-readable codes |

## "Looks Done But Isn't" Checklist

- [ ] **Multi-tenant isolation:** Often missing tenant validation in queued jobs — verify by creating jobs for 3 tenants, checking each job only processes its own data
- [ ] **Elasticsearch sync:** Often missing error handling for failed syncs — verify by breaking Elasticsearch connection during sync, confirming graceful failure and retry
- [ ] **Background job monitoring:** Often missing visibility into stuck jobs — verify by pausing Redis queue, confirming dashboard shows "processing" status
- [ ] **API authentication:** Often missing tenant-aware authentication — verify by logging in as Tenant A, attempting to access Tenant B's API endpoints (should 403)
- [ ] **Mass sync performance:** Often missing chunking for large catalogs — verify by syncing 10,000 products, monitoring memory usage stays < 256MB
- [ ] **Docker resource limits:** Often missing memory/CPU constraints — verify by checking `docker stats` shows container limits, not unlimited
- [ ] **Redis persistence:** Often missing `save` configuration — verify by crashing Redis container, confirming no queued jobs lost
- [ ] **Elasticsearch backups:** Often missing snapshot configuration — verify by checking for snapshot repository in Elasticsearch

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| **Tenant data leaked** | HIGH | 1. Identify breach scope via audit logs 2. Force password reset for affected users 3. Add missing global scopes 4. Write comprehensive tests 5. Perform security review |
| **Elasticsearch index corrupted** | MEDIUM | 1. Stop all writes to index 2. Create new index with correct mappings 3. Reindex from MySQL using Scout import 4. Update index alias atomically 5. Monitor for missing documents |
| **Queue jobs stuck** | LOW | 1. Identify stuck jobs via `php artisan queue:failed` 2. Fix root cause (e.g., Redis connection) 3. Retry with `queue:retry all` 4. Add monitoring to prevent recurrence |
| **Redis connection pool exhausted** | LOW | 1. Increase `max_connections` in config 2. Restart Laravel workers 3. Implement backpressure 4. Add connection monitoring |
| **Database migration lock** | MEDIUM | 1. Identify stuck migration via `SHOW PROCESSLIST` 2. Kill locking query if safe 3. Rollback migration 4. Fix migration to be non-blocking 5. Retry migration |
| **Docker container disk full** | MEDIUM | 1. Identify largest directories via `du -sh` 2. Clean up logs (`truncate log-file`) 3. Remove old Docker images (`docker system prune -a`) 4. Add disk monitoring 5. Increase volume size |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| **Tenant data leakage** | Phase 1 (Foundation) | Write test: create 3 tenants, each with 10 products. Query from Tenant A, assert can only see Tenant A's products. |
| **Elasticsearch sync race conditions** | Phase 2 (Elasticsearch Integration) | Load test: 100 concurrent product updates, verify Elasticsearch consistent with MySQL after 10 seconds. |
| **Queue job tenant context loss** | Phase 1 (Foundation) | Test: dispatch sync jobs for 5 tenants, verify logs show correct tenant per job. |
| **N+1 queries** | Phase 2 (API Development) | Use Laravel Telescope in development, assert query count ≤ 10 for product listing endpoint. |
| **Redis connection pool exhaustion** | Phase 1 (Infrastructure) | Load test: run 50 concurrent sync jobs, monitor Redis connections stay < 80% of max. |
| **Elasticsearch mapping mismatches** | Phase 2 (Elasticsearch Integration) | Test: index products with edge cases (null dates, string prices), verify mapping handles all types. |
| **Shopify/Shopware token security** | Phase 3 (E-commerce Integration) | Audit: check `clients` table uses `encrypted` columns for tokens, not plaintext. |
| **Mass sync memory exhaustion** | Phase 3 (E-commerce Integration) | Test: sync 10,000 products, monitor PHP memory stays < 256MB using `memory_get_usage()`. |
| **Docker resource limits** | Phase 1 (Infrastructure) | Verify: `docker inspect` shows memory/CPU limits on all containers. |
| **API rate limiting** | Phase 2 (API Development) | Test: send 100 requests/second to sync endpoint, verify 429 responses after 60th request. |

## Sources

**Confidence: MEDIUM** — Based on Laravel 11 documentation, Elasticsearch best practices, and common multi-tenant SaaS patterns. External search services were rate-limited, so findings rely on established architectural patterns rather than current 2026 sources. Verification recommended during Phase 1 and Phase 2 implementation.

### General Multi-tenant SaaS Patterns
- Tenant data isolation via global scopes (Laravel最佳实践)
- Queue job context management (Laravel官方文档)
- Redis connection pooling (Redis官方文档)

### Elasticsearch & Laravel Scout
- Mapping definition importance (Elasticsearch官方指南)
- Sync consistency strategies (分布式系统模式)
- Index aliasing for zero-downtime reindexing

### Infrastructure & DevOps
- Docker resource limits (Docker官方文档)
- Supervisor process management (Supervisor文档)
- Background job monitoring (Laravel Telescope文档)

### Security Considerations
- Tenant enumeration prevention (OWASP多租户安全指南)
- API token encryption (Laravel加密服务文档)
- Webhook signature验证 (Shopify/Shopware API文档)

### Knowledge Gaps Requiring Phase-Specific Research
- Laravel 11-specific breaking changes vs Laravel 10
- Current Elasticsearch 8.x mapping syntax changes
- 2026 updates to Shopify/Shopware API rate limits
- Docker Compose 2026 best practices for production

---
*Pitfalls research for: Laravel 11 Multi-tenant E-commerce Agency Backend with Elasticsearch*
*Researched: 2026-03-13*
