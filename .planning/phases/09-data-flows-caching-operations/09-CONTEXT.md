# Phase 9: Data Flows, Caching & Operations - Context

**Gathered:** 2026-03-14
**Status:** Ready for planning

<domain>
## Phase Boundary

Data export functionality for sync logs and product catalogs, Redis-based web caching for dashboard performance, and operations documentation covering server logs (Nginx, Laravel, Supervisor). Export runs asynchronously via background queue with download links. Dashboard metrics and tenant list cached for performance. Documentation provides troubleshooting guidance for common operational issues.

</domain>

<decisions>
## Implementation Decisions

### Export Delivery Strategy
- **Background job (async)** — All exports generate in background queue to avoid blocking requests
- **Job tracking** — Reuse JobStatus model from Phase 4 for export job lifecycle tracking
- **Download links** — Completed exports stored in storage/exports/ with signed URLs for download
- **Retention** — Export files deleted after 24 hours (cleanup via scheduled command)
- **Notification** — Toast notification when export ready with download link

### Export Formats
- **CSV + Excel** — Support both CSV and Excel (XLSX) formats
- **CSV libraries** — Use League CSV package (league/csv) for UTF-8 handling, proper escaping
- **Excel libraries** — Use PhpSpreadsheet (phpoffice/phpspreadsheet) for XLSX generation
- **Format selection** — User chooses format via radio button before export
- **File naming** — Pattern: `{type}_{tenant_slug}_{date}.{ext}` (e.g., `synclogs_acme-inc_20260314.csv`)

### Export Filters
- **Date range** — Start date and end date pickers for filtering by date
- **Tenant selection** — Dropdown to select specific tenant (for sync logs)
- **Status filter** — Sync status filter (completed, failed, partially_failed, running, pending)
- **Combined filters** — All filters applied together (AND logic)
- **Pre-filtered estimates** — Show estimated row count before user confirms export

### Export Limits
- **100K rows max** — Hard limit per export to prevent abuse
- **Preview warning** — Show estimated count; warn if exceeds limit
- **Pagination hint** — Suggest narrower filters if estimate exceeds 100K
- **Timeout handling** — Background jobs have 5-minute max execution time
- **Failure handling** — Mark JobStatus as failed with error message if timeout/limit exceeded

### Cache Key Structure
- **Hierarchical with colons** — Format: `agency:{type}:{id}` (e.g., `agency:dashboard:metrics:tenant-uuid`)
- **Global metrics** — Format: `agency:dashboard:global` for cross-tenant metrics
- **Tenant list** — Format: `agency:tenants:list` for cached tenant enumeration
- **Debugging** — Keys readable in Redis CLI and Redis Explorer tools
- **Key prefix** — Configurable via CACHE_PREFIX env var (default: `agency`)

### Cache Invalidation Strategy
- **Event listeners** — Automatic cache invalidation via model event listeners
- **Tenant events** — Listen for TenantCreated, TenantUpdated, TenantDeleted events
- **Product events** — Listen for ProductCreated, ProductUpdated, ProductDeleted events
- **SyncLog events** — Listen for SyncLogCreated, SyncLogUpdated events
- **Event listener registration** — Register in AppServiceProvider boot() method
- **Manual fallback** — Cache::forget() available for edge cases

### Cache Scope
- **Per-tenant + Global** — Both tenant-specific and global metrics cached separately
- **Per-tenant metrics** — Dashboard metrics scoped to tenant_id (product counts, sync status)
- **Global metrics** — Agency-wide stats (total tenants, total products across all tenants)
- **Tenant list** — Cached list of tenants for dropdowns (shared across all users)
- **Isolation** — Per-tenant cache keys include tenant UUID for multi-tenant safety

### Cache Expiration
- **TTL-based expiration** — Time-to-live based cache expiration
- **Dashboard metrics** — 5-minute TTL (300 seconds)
- **Tenant list** — 15-minute TTL (900 seconds)
- **Global metrics** — 10-minute TTL (600 seconds)
- **No stale serving** — Cache expires, next request regenerates fresh data
- **Blocking refresh** — First request after expiration waits for fresh data (simplest implementation)

### Cache Warming
- **Deploy hook** — Artisan command `cache:warm` to prime caches on deployment
- **Optional** — Not required for operation, improves initial page load
- **Tenant list only** — Warm tenant list cache on deploy (most common dashboard entry point)
- **CLI command** — `php artisan cache:warm {--tenant=*}` for selective warming

### Documentation Structure
- **Topic-based organization** — docs/ops/ directory with multiple focused files
- **LOGGING.md** — Log file locations, viewing commands, log formats
- **TROUBLESHOOTING.md** — Common errors, solutions, diagnostic steps
- **PERFORMANCE.md** — Cache monitoring, slow query detection, optimization hints
- **Root index** — docs/ops/README.md with table of contents linking to all files

### Documentation Content
- **Quick reference** — Log file locations, basic viewing commands (`docker-compose logs`, `make logs`)
- **Common problems** — Troubleshooting sync failures, queue issues, Elasticsearch errors, slow queries
- **Solutions included** — Each problem has actionable solution steps
- **Log examples** — Sample log entries with explanations
- **No advanced patterns** — Skip log aggregation, custom channels, structured JSON logging (v2 features)
- **Audience** — Developers and ops team maintaining AgencySync deployment

### Claude's Discretion
- Exact toast notification duration and positioning for export ready
- Specific export job queue naming (exports vs default queue)
- Exact cache warming strategy (what to prime, when to run)
- Scheduled command frequency for export file cleanup
- Specific troubleshooting examples to include

</decisions>

<specifics>
## Specific Ideas

- **Portfolio consideration**: CSV/Excel export demonstrates data processing capabilities — valuable for DOITSUYA qualification
- **Background jobs**: Reuse JobStatus model from Phase 4 rather than creating new ExportJob model
- **Event listeners**: Automatic cache invalidation feels magical and demonstrates advanced Laravel patterns
- **100K limit**: Generous enough for real catalogs (most clients <50K products), prevents abuse
- **TTL-based caching**: Simplest implementation, no stale data concerns, acceptable for dashboard metrics
- **Documentation in docs/** — Standard Laravel pattern keeps docs separate from code and planning artifacts
- **Topic-based docs** — Easier to navigate than single massive file, more organized than root-level README

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- **JobStatus model** (`app/Models/JobStatus.php`) — Already tracks job lifecycle with status enum
- **SyncLog model** (`app/Models/SyncLog.php`) — Has tenant relationship, status fields, timestamps
- **Product model** (`app/Models/Product.php`) — Scout searchable, tenant-scoped, complete product data
- **Tenant model** (`app/Models/Tenant.php`) — UUID primary keys, encrypted credentials, status enum
- **Redis infrastructure** — Redis container running, QUEUE_CONNECTION=redis configured
- **Queue workers** — Supervisor monitors 2 workers from Phase 4, ready to process export jobs

### Established Patterns
- **Background job pattern** — TenantAwareJob base class + SetTenantContext middleware from Phase 4
- **Tenant scoping** — Global scopes on models, header-based tenant selection from Phase 3
- **Event listeners** — Laravel model events (creating, updating, deleting) available
- **Signed URLs** — Laravel's temporary signed URLs for secure file downloads
- **Alpine.js** — Toast notification component from Phase 7 for export ready notifications

### Known Gaps
- **No export functionality** — No CSV/Excel generation code exists
- **No cache listeners** — Event listeners for cache invalidation not yet implemented
- **No ops docs** — docs/ops/ directory doesn't exist
- **No export storage** — storage/exports/ directory not configured

### Integration Points
- **API routes** — Add GET /api/v1/exports/{uuid} for download links
- **Job dispatch** — Export jobs dispatched to Redis queue from controllers
- **Event listeners** — Register in AppServiceProvider (app/Providers/AppServiceProvider.php)
- **Scheduled tasks** — Add export cleanup to Console/Kernel.php schedule()
- **Storage config** — Add exports disk to config/filesystems.php
- **Dashboard UI** — Add export buttons to tenant list and product search views

### Libraries to Add
- **league/csv** — `composer require league/csv` for CSV generation
- **phpoffice/phpspreadsheet** — `composer require phpoffice/phpspreadsheet` for Excel XLSX generation

</code_context>

<deferred>
## Deferred Ideas

- **Email export delivery** — Send export file via email for long-running jobs (future enhancement)
- **Scheduled exports** — Auto-generate daily/weekly reports (v2 automation feature)
- **Export templates** — User-customizable export column sets (future enhancement)
- **Log aggregation** — Centralized logging with ELK stack (v2 infrastructure)
- **Real-time metrics** — WebSocket-based dashboard updates (v2, not v1)
- **Advanced cache strategies** — Cache stamping, versioned keys, cache warming service (future optimization)
- **API documentation** — OpenAPI/Swagger docs (deferred to later phase or external tool)

</deferred>

---

*Phase: 09-data-flows-caching-operations*
*Context gathered: 2026-03-14*
