# Project Research Summary

**Project:** AgencySync
**Domain:** Multi-tenant E-commerce Agency Management System (Laravel 11)
**Researched:** 2026-03-13
**Confidence:** MEDIUM

## Executive Summary

AgencySync is a multi-tenant backend system that enables e-commerce agencies to manage product catalogs across multiple client stores (Shopify, Shopware) from a unified interface. Expert-built systems in this domain use **Laravel 11 with tenant_id discriminator patterns**, **Redis-based queue processing** for background sync operations, and **Elasticsearch** for fast cross-tenant product search. The research strongly recommends using the **stancl/tenancy** package for multi-tenancy, **Laravel Scout with Scout Driver Plus** for search abstraction, and **index-per-tenant Elasticsearch strategy** for data isolation.

The recommended approach prioritizes **tenant data isolation as foundational architecture**—implementing global scopes, tenant-aware repositories, and queue job context management before building any features. Critical risks include tenant data leakage (mitigated by global query scopes and database constraints), Elasticsearch sync race conditions (mitigated by atomic transaction patterns and optimistic locking), and queue job tenant context loss (mitigated by storing tenant_id in job payloads). The research suggests a **6-phase build order** starting with tenant resolution middleware and authentication, then progressing through services, background processing, search integration, external API integration, and finally the admin dashboard.

## Key Findings

### Recommended Stack

**Core technologies:**
- **PHP 8.2+ / Laravel 11.31+** — Application framework with LTS support until 2027, minimal structure, improved queue system, native health endpoints. Already installed in project.
- **MySQL 8.0+** — Primary database with tenant_id columns for multi-tenant isolation, JSON columns for flexible schemas, native Laravel support.
- **Redis 7.x+** — Cache, queue, and session store required for Laravel queues. Use `predis/predis` for easier Docker deployment (no PHP extension compilation).
- **Elasticsearch 8.x+** — Full-text search engine with sub-second performance for product catalogs. Fuzzy matching, relevance scoring, bulk operations.
- **stancl/tenancy ^4.0** — Multi-tenancy package with active development (v9.3.0 released Feb 2026), supports domain/subdomain/path-based isolation, automatic tenant resolution.
- **Laravel Scout ^10.0 + elastic/scout-driver-plus ^4.0** — Search abstraction layer with advanced query builder, pagination, and aggregation support.
- **Supervisor 3.x+** — Process manager for queue workers with auto-restart on failure. Standard for production Laravel queues.

**What NOT to use:**
- **hyn/multi-tenant** — Deprecated, not maintained for Laravel 11
- **MySQL full-text search** — Performance degradation at scale, no fuzzy matching
- **Database queue driver** — Blocks workers, no retry backoff
- **Laravel Horizon** — Overkill for single-tenant agency setup, adds complexity
- **Spatie/laravel-multitenancy** — Less actively maintained than stancl/tenancy

### Expected Features

**Must have (table stakes):**
- **Client/Tenant Management** — Agencies must manage multiple client stores from one interface
- **Catalog Synchronization** — Core value prop for keeping product data in sync across platforms
- **Global Product Search** — Agencies need to find products across all clients instantly
- **Background Job Monitoring** — Long-running sync jobs need visibility and retry logic
- **Authentication & Authorization** — Multi-user agencies need controlled access with tenant isolation
- **API Credential Management** — Connecting to client stores requires secure encrypted token storage
- **Sync Status Dashboard** — Agencies need visibility into what's synced and what's pending
- **Data Validation** — Prevent bad data from entering the system and breaking search/indexing

**Should have (competitive):**
- **Cross-Client Product Search** — Search across ALL client catalogs from one query with unified Elasticsearch index
- **Change Detection** — Only sync what changed, not full catalogs (delta sync via webhooks or hash comparison)
- **Bulk Operations** — Update products across multiple clients simultaneously (mass updates, price changes)
- **Sync Scheduling** — Automated sync on schedules (hourly, daily, weekly) per client
- **Webhook Integration** — Real-time updates from platforms for near-instant sync triggers
- **Search Relevance Tuning** — Custom boosting for search results by popularity, margin, stock status

**Defer (v2+):**
- **Real-time Sync (WebSocket)** — Adds massive complexity, polling/periodic sync + webhooks covers 99% of use cases
- **Client-facing UI** — Dilutes agency focus, adds auth complexity. Agency-internal only; clients use platform's native UI
- **Full Order Management** — Bloated scope, complex refund logic. Focus on catalog sync; orders stay in native platforms
- **Multi-Currency Support** — Exchange rate volatility, accounting complexity. Store in source currency; display conversion only
- **Predictive Inventory** — Requires historical data, ML infrastructure. Simple low-stock alerts suffice
- **Marketing Automation** — Entirely different domain. Integrate with existing tools via webhooks

### Architecture Approach

The recommended architecture follows a **layered service/repository pattern** with tenant-aware middleware at the HTTP layer, automatic tenant scoping in repositories, and queue-based background processing for long-running operations. Major components include:

1. **Tenant Resolution Middleware** — Extracts tenant identifier from subdomain/API token, validates against database, stores tenant in request context as singleton, applies tenant scoping to all Eloquent queries
2. **Service Layer** — Business logic coordination (TenantService, CatalogSyncService, SearchService) that orchestrates repositories, external APIs, queue system, and Elasticsearch client
3. **Repository Layer** — Data access abstraction with automatic tenant scoping via `forTenant(Tenant $tenant)` methods, prevents manual tenant filtering errors
4. **Queue Job System** — Asynchronous processing (SyncCatalogJob, IndexProductJob, CleanupJob) via Redis + Supervisor workers with separate queues for different job priorities
5. **Elasticsearch Integration** — Index-per-tenant strategy (`products_{tenant_id}`) for complete isolation, explicit mapping definitions before first import to avoid type mismatches

**Data flow:** Requests → Tenant Resolution Middleware → Service Layer (tenant-scoped) → Repository Layer → Database (tenant_id filtering) → Response. Background jobs dispatched to Redis queues, processed by Supervisor workers, with tenant_id stored in job payload to prevent context loss.

### Critical Pitfalls

1. **Tenant Data Leakage via Global Scopes** — Queries accidentally return data from other tenants due to missing global scopes, raw SQL without tenant filtering, or relationship eager loading bypassing isolation. **Prevent with:** TenantScope global scope on all tenant-aware models, tenant validation middleware, database-level CHECK constraints on tenant_id columns, tests creating multiple tenants to verify no cross-tenant access.

2. **Elasticsearch Sync Race Conditions** — Product updates in MySQL don't reflect in Elasticsearch immediately, or worse — shows stale/inconsistent data due to race conditions between transaction commits and indexing, concurrent sync jobs, or webhooks arriving faster than queue processing. **Prevent with:** Database transactions + queued Elasticsearch indexing in atomic pattern, optimistic locking with updated_at checks, sync job uniqueness by tenant_id + product_id, "sync lag" monitoring with alerts if index age > 30 seconds, hourly reconciliation jobs.

3. **Queue Job Tenant Context Loss** — Background jobs run with wrong or no tenant context, querying wrong database tables, sending notifications to wrong users, or updating wrong Elasticsearch indices. **Prevent with:** Store tenant_id in job payload, Queue::before middleware to set tenant context in workers, tenant context in all log entries, tests dispatching jobs for multiple tenants in same test.

4. **N+1 Queries in Multi-Tenant Eager Loading** — Fetching tenant's products (1 query) then loading each product's client, sync status, variants (N queries), generating 10,001 queries instead of 2-3 at scale. **Prevent with:** Enable query logging in development, use Laravel Debugbar/Telescope to identify N+1, eager load by default with `with()`, Lazy Eager Loading with `load()`, query count assertions in tests.

5. **Redis Connection Pool Exhaustion** — Under load (mass sync operations), Redis connections spawn faster than they close, exhausting connection pool, causing queue workers to stop processing and application to become unresponsive. **Prevent with:** Configure persistent connections and max_connections in config/database.php, separate Redis instances for cache vs queue, monitor connected_clients metric and alert at 80% capacity, limit Supervisor workers to 4 not 10, implement backpressure in sync jobs.

6. **Elasticsearch Mapping Mismatches** — Search queries fail or return unexpected results because mappings don't match actual data types (text analyzed when should be keyword, dates as strings, nested objects not configured), requiring reindexing to fix. **Prevent with:** Explicitly define Elasticsearch mappings before first index, use .raw fields for exact matches, migration-like system for mapping changes, test with various data types before production, index aliases for zero-downtime reindexing.

## Implications for Roadmap

Based on combined research from STACK, FEATURES, ARCHITECTURE, and PITFALLS, the suggested phase structure is:

### Phase 1: Foundation & Tenant Isolation
**Rationale:** Tenant isolation is architectural — not add-on. Must be implemented before any tenant data is created to prevent catastrophic data leakage. Research shows this is the most critical security pitfall.

**Delivers:** Working Laravel 11 application with tenant resolution middleware, database schema with tenant_id columns, basic authentication, and global query scopes ensuring data isolation.

**Addresses:** Client/Tenant Management, Authentication & Authorization, API Credential Management

**Avoids:** Tenant data leakage (Pitfall #1), queue job tenant context loss (Pitfall #3)

**Key components:**
- Tenant resolution middleware (subdomain/token extraction)
- Database migrations with tenant_id columns + foreign keys
- Global scopes on all tenant-aware models
- Laravel Sanctum authentication with tenant-scoped tokens
- Encrypted API credential storage
- Basic CRUD API for tenant management

### Phase 2: Core Services & Single-Tenant Search
**Rationale:** After tenant isolation is proven, build the service layer and repository layer with tenant-aware data access. Single-tenant search validates the core value proposition before adding cross-tenant complexity.

**Delivers:** Repository layer with automatic scoping, service layer for business logic, basic API endpoints for products/clients, single-tenant product search, and admin dashboard UI foundation.

**Addresses:** Single-Tenant Product Search, Data Validation, Basic Admin Dashboard, Background Job Monitoring (basic)

**Uses:** Laravel 11 minimal structure with recreated Models/Controllers directories, repository pattern, API Resources for JSON responses

**Implements:** Repository layer (TenantRepository, ProductRepository), Service layer (TenantService, CatalogSyncService), basic API endpoints with tenant scoping

**Avoids:** N+1 queries (Pitfall #4) by implementing eager loading patterns from the start

### Phase 3: Background Processing & Queues
**Rationale:** Catalog sync is I/O bound and time-consuming — blocking HTTP requests would timeout. Queue infrastructure must be reliable before adding external API integrations. Research highlights Redis connection pool exhaustion and queue job tenant context loss as critical pitfalls.

**Delivers:** Redis-based queue system, Supervisor configuration, job classes (SyncCatalogJob, IndexProductJob, CleanupJob), job monitoring dashboard, retry logic with exponential backoff, and batch processing support.

**Addresses:** Catalog Synchronization (manual trigger), Background Job Processing, Background Job Monitoring, Error Handling & Logging

**Uses:** Redis queues with predis/predis, Supervisor for worker management, Laravel 11 job batching/chaining, separate queues for sync/index/cleanup

**Avoids:** Redis connection pool exhaustion (Pitfall #5), queue job tenant context loss (Pitfall #3)

**Key components:**
- Redis configuration with connection pooling limits
- Supervisor config with 3-4 workers, proper timeouts
- Job classes with tenant_id in payload
- Queue monitoring (queue:monitor, failed job tracking)
- Job batching for atomic multi-job operations

### Phase 4: Elasticsearch Integration
**Rationale:** Search is a core differentiator but depends on background processing for indexing. Elasticsearch integration has high-risk pitfalls (sync race conditions, mapping mismatches) that require dedicated phase to get right.

**Delivers:** Elasticsearch cluster setup, index-per-tenant configuration, explicit mapping definitions, SearchService with fuzzy matching and relevance scoring, automated indexing jobs, and search API endpoints.

**Addresses:** Global Product Search, Single-Tenant Product Search (enhanced), Cross-Client Product Search (if time permits)

**Uses:** Elasticsearch 8.x, Laravel Scout ^10.0, Scout Driver Plus ^4.0, index-per-tenant strategy (products_{tenant_id})

**Implements:** Elasticsearch service layer, mapping configuration, indexing jobs, search controllers with pagination/filters

**Avoids:** Elasticsearch sync race conditions (Pitfall #2), Elasticsearch mapping mismatches (Pitfall #6)

**Key components:**
- Explicit mapping definitions before first import
- Index-per-tenant strategy for isolation
- Atomic transaction + queued indexing pattern
- Sync lag monitoring and reconciliation jobs
- Index aliases for zero-downtime reindexing

### Phase 5: E-commerce Platform Integration
**Rationale:** External API integration (Shopify/Shopware) is the core value prop but depends on reliable queues and search infrastructure. This phase implements the actual sync workflows.

**Delivers:** Shopify/Shopware API clients, catalog sync workflows, rate limiting and quota management, webhook integration (basic), change detection (if time permits), and error handling for external API failures.

**Addresses:** Catalog Synchronization (automated), Webhook Integration (basic), Change Detection (if time), Bulk Operations (basic)

**Implements:** Platform adapters (ShopifyAdapter, ShopwareAdapter), sync workflows, webhook controllers, rate limit tracking

**Avoids:** Synchronous external API calls (anti-pattern), mass sync memory exhaustion

**Key components:**
- Platform adapter interfaces for extensibility
- Rate limit tracking per tenant
- Chunked sync for large catalogs (1000 products per job)
- Webhook signature validation
- Error categorization (retryable vs fatal)

### Phase 6: Admin Dashboard & Polish
**Rationale:** Admin UI depends on all backend APIs being complete. Polish the UX with progress indication, error details, and monitoring views.

**Delivers:** Complete admin dashboard with Blade + Alpine.js, sync status monitoring with progress bars, search interface with filters, bulk operation UI, performance analytics (basic), and user onboarding flow.

**Addresses:** Sync Status Dashboard, Enhanced Job Monitoring, Bulk Operations UI, Search Relevance Tuning (basic)

**Uses:** Tailwind CSS (already installed), Alpine.js (already installed), Laravel API Resources, real-time events (broadcasting)

**Key components:**
- Dashboard with per-tenant statistics
- Sync job progress tracking with real-time updates
- Search interface with filters and pagination
- Bulk operation forms with error details
- Settings pages for tenant configuration

### Phase Ordering Rationale

This order follows **dependency-first principles** identified across all research documents:

- **Foundation first:** Tenant isolation must be implemented before any tenant data is created (ARCHITECTURE — build order dependencies)
- **Services before integration:** Repository/service layer provides tenant-aware data access foundation before adding complexity (ARCHITECTURE — layered pattern)
- **Queues before external APIs:** Background processing infrastructure required before catalog sync (FEATURES — dependency graph shows Catalog Sync requires Background Job Processing)
- **Search after queues:** Elasticsearch indexing depends on queue workers (ARCHITECTURE — Phase 4 requires Phase 3)
- **Integration after search:** External API sync workflows feed into search indexing (FEATURES — Catalog Sync enables Global Product Search)
- **UI last:** Admin dashboard depends on all backend APIs being complete (ARCHITECTURE — Phase 6 requires Phase 4 and 5)

This order specifically **avoids critical pitfalls**:
- Tenant data leakage prevented by Phase 1 foundation
- Queue context loss prevented by Phase 3 architecture
- Elasticsearch race conditions prevented by Phase 4 patterns
- N+1 queries prevented by Phase 2 eager loading

### Research Flags

**Phases likely needing deeper research during planning:**

- **Phase 3 (Background Processing):** Verify current Supervisor configuration best practices for Laravel 11 queues specifically, Redis connection pool tuning for production workloads, and job retry/backoff strategies for external API failures.

- **Phase 4 (Elasticsearch Integration):** Research current Elasticsearch 8.x + Laravel Scout Driver Plus integration patterns for multi-tenant setups, index-per-tenant performance at scale (100+ tenants), and mapping configuration for product catalogs with variants.

- **Phase 5 (E-commerce Platform Integration):** Investigate 2026 Shopify/Shopware API rate limits, pagination patterns, webhook signature verification, and PHP SDK capabilities. Research error handling patterns for transient API failures vs permanent errors.

**Phases with standard patterns (skip research-phase):**

- **Phase 1 (Foundation):** Laravel 11 authentication, middleware, and database patterns are well-documented. Multi-tenant tenant_id approach is established best practice.

- **Phase 2 (Core Services):** Repository pattern, service layer, and API resources are standard Laravel patterns with extensive documentation.

- **Phase 6 (Admin Dashboard):** Blade + Alpine.js is established Laravel stack, well-documented patterns for admin dashboards.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Verified against official Laravel 11 documentation, package repositories (stancl/tenancy v9.3.0, Scout Driver Plus active), and project composer.json showing installed versions. |
| Features | MEDIUM | Based on general knowledge of multi-tenant SaaS patterns and e-commerce agency workflows. External search was rate-limited, so findings rely on training data and architectural patterns rather than current 2026 ecosystem surveys. |
| Architecture | MEDIUM | Could not verify with official sources due to web search rate limits. Based on Laravel 11 documentation (general knowledge), standard multi-tenant Laravel patterns, and project context. Verified local Laravel 11 installation structure. |
| Pitfalls | MEDIUM | Based on Laravel 11 documentation, Elasticsearch best practices, and common multi-tenant SaaS patterns. External search services were rate-limited, so findings rely on established architectural patterns rather than current 2026 sources. |

**Overall confidence:** MEDIUM

**Confidence breakdown by source:**
- **HIGH confidence areas:** Laravel 11 installation status (verified locally), package versions (verified on GitHub/repositories), general multi-tenant patterns (established best practices)
- **MEDIUM confidence areas:** Feature prioritization (based on domain knowledge, not competitor survey), Elasticsearch 8.x specifics (general patterns verified, version-specific details unverified), Shopify/Shopware 2026 API changes (general patterns known, current limits unknown)
- **LOW confidence areas:** Current e-commerce agency tool landscape, 2026-era community patterns for multi-tenant architecture, specific Elasticsearch 8.x mapping syntax changes

### Gaps to Address

- **Shopify/Shopware API specifics:** Research current rate limits, pagination patterns, webhook signatures, and PHP SDK capabilities during Phase 5 planning.

- **Elasticsearch 8.x + Laravel Scout integration:** Verify Scout Driver Plus compatibility with ES 8.x, index-per-tenant performance characteristics, and mapping configuration best practices during Phase 4 planning.

- **Redis connection pool tuning:** Research production-ready Redis configuration for queue-heavy workloads during Phase 3 planning.

- **2026 multi-tenant package ecosystem:** Verify stancl/tenancy v9.3.0 Laravel 11 compatibility details and any breaking changes from v4.0 research during Phase 1 planning.

- **Supervisor configuration for Laravel 11:** Confirm recommended Supervisor settings for Laravel 11 queue workers during Phase 3 planning.

**Handling gaps during planning/execution:**
- Use `/gsd:research-phase` before Phase 3, Phase 4, and Phase 5 to validate assumptions
- Build proof-of-concept for Elasticsearch integration early in Phase 4 to verify Scout Driver Plus + ES 8.x compatibility
- Start with conservative rate limits in Phase 5, tune based on actual API response headers
- Monitor Redis connection metrics in Phase 3 development to validate connection pool assumptions

## Sources

### Primary (HIGH confidence)
- **Laravel 11 Documentation** — Framework architecture, queues, Scout, authentication, middleware patterns
- **Laravel 11 Installation (local verification)** — Confirmed fresh installation structure, composer.json versions
- **stancl/tenancy GitHub** — Latest release v9.3.0 (2026-02-04), Laravel 11 compatibility, active development
- **elastic/elasticsearch-php GitHub** — Latest release v9.3.0 (2026-02-04), ES 8.x support
- **elastic/scout-driver-plus GitHub** — Current version v4.0, Scout 10.x compatibility
- **Project composer.json** — Verified installed versions: Laravel 11.31, PHP 8.2+ requirement

### Secondary (MEDIUM confidence)
- **Laravel Queues Documentation** — Redis queue driver, job batching, Supervisor configuration
- **Elasticsearch Official Documentation** — Index-per-tenant patterns, mapping configuration, sync consistency strategies
- **Multi-tenant SaaS Patterns (established best practices)** — Global scopes, tenant resolution middleware, repository pattern
- **Docker Documentation** — Docker Compose for development, resource limits, container networking
- **Supervisor Documentation** — Process manager configuration for queue workers

### Tertiary (LOW confidence)
- **General e-commerce agency workflow knowledge** — Based on training data, not current 2026 surveys
- **Shopify/Shopware API patterns** — General REST API patterns, not current 2026 documentation
- **Competitor analysis** — Inferred from domain knowledge, external search was rate-limited during research

### Research Constraints
- **Web search rate limiting** — External search services were rate-limited during research, preventing verification of current 2026 sources for some areas
- **Elasticsearch 8.x specifics** — General patterns verified from official docs, but version-specific syntax changes may exist
- **2026 package ecosystem** — Package versions verified from GitHub (active development), but Laravel 11-specific breaking changes may exist

---
*Research completed: 2026-03-13*
*Ready for roadmap: yes*
