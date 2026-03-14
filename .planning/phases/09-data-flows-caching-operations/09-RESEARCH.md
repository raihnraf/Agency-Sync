# Phase 9: Data Flows, Caching & Operations - Research

**Researched:** 2026-03-14
**Domain:** Data export, Redis caching, event-driven invalidation, operations documentation
**Confidence:** MEDIUM

## Summary

Phase 9 implements data export functionality (CSV/Excel), Redis-based caching for dashboard performance, and operations documentation for server troubleshooting. Exports run asynchronously via background jobs with download links. Cache invalidation uses Laravel model event listeners for automatic updates. Documentation covers Nginx, Laravel, and Supervisor log locations and common issues.

**Primary recommendation:** Use League CSV for CSV generation, PhpSpreadsheet for Excel exports, Laravel's Cache facade with Redis backend, and event listeners for automatic cache invalidation. Implement background jobs for exports with signed URL download links. Create topic-based operations documentation in `docs/ops/` directory.

## User Constraints (from CONTEXT.md)

### Locked Decisions

**Export Delivery Strategy**
- Background job (async) — All exports generate in background queue to avoid blocking requests
- Job tracking — Reuse JobStatus model from Phase 4 for export job lifecycle tracking
- Download links — Completed exports stored in storage/exports/ with signed URLs for download
- Retention — Export files deleted after 24 hours (cleanup via scheduled command)
- Notification — Toast notification when export ready with download link

**Export Formats**
- CSV + Excel — Support both CSV and Excel (XLSX) formats
- CSV libraries — Use League CSV package (league/csv) for UTF-8 handling, proper escaping
- Excel libraries — Use PhpSpreadsheet (phpoffice/phpspreadsheet) for XLSX generation
- Format selection — User chooses format via radio button before export
- File naming — Pattern: `{type}_{tenant_slug}_{date}.{ext}` (e.g., `synclogs_acme-inc_20260314.csv`)

**Export Filters**
- Date range — Start date and end date pickers for filtering by date
- Tenant selection — Dropdown to select specific tenant (for sync logs)
- Status filter — Sync status filter (completed, failed, partially_failed, running, pending)
- Combined filters — All filters applied together (AND logic)
- Pre-filtered estimates — Show estimated row count before user confirms export

**Export Limits**
- 100K rows max — Hard limit per export to prevent abuse
- Preview warning — Show estimated count; warn if exceeds limit
- Pagination hint — Suggest narrower filters if estimate exceeds 100K
- Timeout handling — Background jobs have 5-minute max execution time
- Failure handling — Mark JobStatus as failed with error message if timeout/limit exceeded

**Cache Key Structure**
- Hierarchical with colons — Format: `agency:{type}:{id}` (e.g., `agency:dashboard:metrics:tenant-uuid`)
- Global metrics — Format: `agency:dashboard:global` for cross-tenant metrics
- Tenant list — Format: `agency:tenants:list` for cached tenant enumeration
- Debugging — Keys readable in Redis CLI and Redis Explorer tools
- Key prefix — Configurable via CACHE_PREFIX env var (default: `agency`)

**Cache Invalidation Strategy**
- Event listeners — Automatic cache invalidation via model event listeners
- Tenant events — Listen for TenantCreated, TenantUpdated, TenantDeleted events
- Product events — Listen for ProductCreated, ProductUpdated, ProductDeleted events
- SyncLog events — Listen for SyncLogCreated, SyncLogUpdated events
- Event listener registration — Register in AppServiceProvider boot() method
- Manual fallback — Cache::forget() available for edge cases

**Cache Scope**
- Per-tenant + Global — Both tenant-specific and global metrics cached separately
- Per-tenant metrics — Dashboard metrics scoped to tenant_id (product counts, sync status)
- Global metrics — Agency-wide stats (total tenants, total products across all tenants)
- Tenant list — Cached list of tenants for dropdowns (shared across all users)
- Isolation — Per-tenant cache keys include tenant UUID for multi-tenant safety

**Cache Expiration**
- TTL-based expiration — Time-to-live based cache expiration
- Dashboard metrics — 5-minute TTL (300 seconds)
- Tenant list — 15-minute TTL (900 seconds)
- Global metrics — 10-minute TTL (600 seconds)
- No stale serving — Cache expires, next request regenerates fresh data
- Blocking refresh — First request after expiration waits for fresh data (simplest implementation)

**Cache Warming**
- Deploy hook — Artisan command `cache:warm` to prime caches on deployment
- Optional — Not required for operation, improves initial page load
- Tenant list only — Warm tenant list cache on deploy (most common dashboard entry point)
- CLI command — `php artisan cache:warm {--tenant=*}` for selective warming

**Documentation Structure**
- Topic-based organization — docs/ops/ directory with multiple focused files
- LOGGING.md — Log file locations, viewing commands, log formats
- TROUBLESHOOTING.md — Common errors, solutions, diagnostic steps
- PERFORMANCE.md — Cache monitoring, slow query detection, optimization hints
- Root index — docs/ops/README.md with table of contents linking to all files

**Documentation Content**
- Quick reference — Log file locations, basic viewing commands (`docker-compose logs`, `make logs`)
- Common problems — Troubleshooting sync failures, queue issues, Elasticsearch errors, slow queries
- Solutions included — Each problem has actionable solution steps
- Log examples — Sample log entries with explanations
- No advanced patterns — Skip log aggregation, custom channels, structured JSON logging (v2 features)
- Audience — Developers and ops team maintaining AgencySync deployment

### Claude's Discretion

- Exact toast notification duration and positioning for export ready
- Specific export job queue naming (exports vs default queue)
- Exact cache warming strategy (what to prime, when to run)
- Scheduled command frequency for export file cleanup
- Specific troubleshooting examples to include

### Deferred Ideas (OUT OF SCOPE)

- Email export delivery — Send export file via email for long-running jobs (future enhancement)
- Scheduled exports — Auto-generate daily/weekly reports (v2 automation feature)
- Export templates — User-customizable export column sets (future enhancement)
- Log aggregation — Centralized logging with ELK stack (v2 infrastructure)
- Real-time metrics — WebSocket-based dashboard updates (v2, not v1)
- Advanced cache strategies — Cache stamping, versioned keys, cache warming service (future optimization)
- API documentation — OpenAPI/Swagger docs (deferred to later phase or external tool)

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| DATAFLOW-01 | Agency admin can export sync logs to CSV file | League CSV library provides UTF-8 CSV generation with proper escaping. Background job pattern from Phase 4 supports async exports. |
| DATAFLOW-02 | Agency admin can export product catalog to CSV/Excel file | PhpSpreadsheet supports XLSX generation. Chunking prevents memory issues with large catalogs. |
| DATAFLOW-03 | Export includes tenant information, timestamps, and status | Export jobs query tenant-scoped data via existing relationships. Timestamps from created_at/updated_at fields. |
| CACHE-01 | Dashboard metrics are cached for 5 minutes using Redis | Laravel Cache::remember() with 300-second TTL. Redis backend already configured in Phase 4. |
| CACHE-02 | Tenant list is cached using Cache::remember() | Cache::remember('agency:tenants:list', 900, callback) pattern. Event listeners invalidate on tenant changes. |
| CACHE-03 | Cache invalidates on data updates | Model event listeners (created, updated, deleted) call Cache::forget() for affected keys. |
| OPS-01 | Server logging documentation covers Nginx access/error logs | Docker Compose logs accessible via `docker-compose logs nginx`. docs/ops/LOGGING.md documents locations. |
| OPS-02 | Server logging documentation covers Laravel logs | Laravel logs stored in storage/logs/. Documented in LOGGING.md with viewing commands. |
| OPS-03 | Server logging documentation covers Supervisor worker logs | Supervisor logs in /var/log/supervisor/. Documented with troubleshooting for queue issues. |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| league/csv | ^9.15 | CSV generation with UTF-8 support | Industry-standard CSV library for PHP. Handles proper escaping, BOM for Excel compatibility, streaming for large files. |
| phpoffice/phpspreadsheet | ^2.0 | Excel XLSX generation | Official PHPExcel successor. Supports XLSX format, styling, memory-efficient cell writing. |
| Laravel Cache | 11.x (built-in) | Redis caching interface | Laravel's unified cache API. Redis driver already configured from Phase 4. |
| Laravel Events | 11.x (built-in) | Model event listeners | Native Laravel event system for cache invalidation. No additional packages needed. |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel Queues | 11.x (built-in) | Async export jobs | Background processing already implemented in Phase 4. Reuse existing infrastructure. |
| Laravel Storage | 11.x (built-in) | Export file storage | Local disk driver for exports. Signed URLs for secure downloads. |
| Laravel Scheduler | 11.x (built-in) | Export cleanup task | Scheduled command to delete expired exports (24-hour retention). |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| league/csv | fputcsv() (native PHP) | Native function lacks UTF-8 BOM, proper escaping, streaming. League CSV handles edge cases (newlines in fields, special characters). |
| phpspreadsheet | laravel-excel (maatwebsite/excel) | Laravel-Excel is wrapper around PhpSpreadsheet. Direct PhpSpreadsheet is lighter, fewer dependencies. Laravel-Excel has better DX but overkill for simple exports. |
| Event listeners | Manual cache clearing | Manual Cache::forget() scattered in controllers is error-prone. Event listeners automatic, DRY, harder to miss. |
| Redis cache | File cache | File cache doesn't support TTL-based expiration, tag-based invalidation. Redis already in stack, use it. |

**Installation:**
```bash
composer require league/csv phpoffice/phpspreadsheet
```

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Jobs/
│   ├── ExportSyncLogs.php           # Background job for sync log export
│   └── ExportProductCatalog.php     # Background job for product export
├── Listeners/
│   ├── InvalidateTenantCache.php    # Clears tenant cache on changes
│   ├── InvalidateProductCache.php   # Clears product cache on changes
│   └── InvalidateSyncLogCache.php   # Clears sync log cache on changes
├── Services/
│   └── ExportService.php            # Common export logic (file naming, storage)
└── Providers/
    └── AppServiceProvider.php       # Register event listeners

docs/
└── ops/
    ├── README.md                    # Operations documentation index
    ├── LOGGING.md                   # Log locations and viewing commands
    ├── TROUBLESHOOTING.md           # Common issues and solutions
    └── PERFORMANCE.md               # Cache monitoring and optimization

storage/
└── app/exports/                     # Export file storage (gitignored)
```

### Pattern 1: Background Export Jobs

**What:** Async job generation of CSV/Excel files with progress tracking via JobStatus.

**When to use:** All data exports (sync logs, product catalogs, tenant lists).

**Example:**
```php
// app/Jobs/ExportSyncLogs.php
use App\Jobs\TenantAwareJob;
use App\Models\JobStatus;
use App\Models\SyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use League\Csv\Writer;

class ExportSyncLogs extends TenantAwareJob implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    private $jobStatusId;
    private $filters;
    private $format;

    public function __construct($jobStatusId, array $filters, string $format)
    {
        $this->jobStatusId = $jobStatusId;
        $this->filters = $filters;
        $this->format = $format;
    }

    public function handle(): void
    {
        $jobStatus = JobStatus::findOrFail($this->jobStatusId);
        $jobStatus->update(['status' => JobStatusEnum::RUNNING]);

        try {
            // Query with filters
            $query = SyncLog::query()->where('tenant_id', $this->tenantId);
            if (!empty($this->filters['start_date'])) {
                $query->where('created_at', '>=', $this->filters['start_date']);
            }
            // ... apply other filters

            // Estimate count
            $estimated = $query->count();
            if ($estimated > 100000) {
                throw new \Exception('Export exceeds 100K row limit');
            }

            // Generate file
            $filename = $this->generateFilename();
            $filepath = storage_path("app/exports/{$filename}");

            if ($this->format === 'csv') {
                $this->generateCsv($query, $filepath);
            } else {
                $this->generateExcel($query, $filepath);
            }

            $jobStatus->update([
                'status' => JobStatusEnum::COMPLETED,
                'result' => ['filepath' => $filepath, 'filename' => $filename]
            ]);
        } catch (\Exception $e) {
            $jobStatus->update([
                'status' => JobStatusEnum::FAILED,
                'error' => $e->getMessage()
            );
            throw $e;
        }
    }

    private function generateCsv($query, $filepath): void
    {
        $csv = Writer::createFromPath($filepath, 'w+');
        $csv->setOutputBOM(Writer::BOM_UTF8); // Excel compatibility

        // Header row
        $csv->insertOne(['Tenant', 'Status', 'Products Synced', 'Started At', 'Completed At', 'Duration']);

        // Data rows
        $query->chunk(1000, function ($logs) use ($csv) {
            foreach ($logs as $log) {
                $csv->insertOne([
                    $log->tenant->name,
                    $log->status->value,
                    $log->products_synced,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->completed_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $log->duration ?? 'N/A'
                ]);
            }
        });
    }

    private function generateFilename(): string
    {
        $tenant = Tenant::find($this->tenantId);
        $date = now()->format('Ymd');
        $ext = $this->format === 'csv' ? 'csv' : 'xlsx';
        return "synclogs_{$tenant->slug}_{$date}.{$ext}";
    }
}
```

### Pattern 2: Event-Driven Cache Invalidation

**What:** Automatic cache clearing via model event listeners.

**When to use:** Cached data that becomes stale when underlying models change.

**Example:**
```php
// app/Listeners/InvalidateTenantCache.php
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

// app/Providers/AppServiceProvider.php
use App\Models\Tenant;
use App\Models\Product;
use App\Models\SyncLog;
use App\Listeners\InvalidateTenantCache;
use App\Listeners\InvalidateProductCache;
use App\Listeners\InvalidateSyncLogCache;

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

### Pattern 3: Cached Dashboard Metrics

**What:** Cache::remember() pattern for expensive dashboard queries.

**When to use:** Dashboard metrics, tenant lists, aggregate statistics.

**Example:**
```php
// app/Http/Controllers/DashboardController.php
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->header('X-Tenant-ID');

        // Cache per-tenant metrics for 5 minutes
        $metrics = Cache::remember("agency:dashboard:metrics:{$tenantId}", 300, function () use ($tenantId) {
            return [
                'total_products' => Product::where('tenant_id', $tenantId)->count(),
                'last_sync' => SyncLog::where('tenant_id', $tenantId)
                    ->orderBy('created_at', 'desc')
                    ->first(['created_at', 'status']),
                'sync_success_rate' => $this->calculateSuccessRate($tenantId),
            ];
        });

        return response()->json(['data' => $metrics]);
    }

    public function tenants()
    {
        // Cache tenant list for 15 minutes
        $tenants = Cache::remember('agency:tenants:list', 900, function () {
            return Tenant::select(['id', 'name', 'slug', 'status'])
                ->orderBy('name')
                ->get();
        });

        return TenantResource::collection($tenants);
    }
}
```

### Pattern 4: Signed URL Downloads

**What:** Temporary signed URLs for secure export file downloads.

**When to use:** User-initiated file downloads from storage.

**Example:**
```php
// routes/api.php
use Illuminate\Support\Facades\Route;

Route::get('/exports/{uuid}', function ($uuid) {
    $jobStatus = JobStatus::where('uuid', $uuid)->firstOrFail();

    if ($jobStatus->status !== JobStatusEnum::COMPLETED) {
        return response()->json(['errors' => [
            ['message' => 'Export not ready']
        ]], 404);
    }

    $filepath = $jobStatus->result['filepath'];
    $filename = $jobStatus->result['filename'];

    // Generate signed URL valid for 24 hours
    $url = Storage::disk('exports')->temporaryUrl(
        $filename,
        now()->addHours(24)
    );

    return response()->json(['data' => [
        'download_url' => $url,
        'filename' => $filename,
        'expires_at' => now()->addHours(24)->toIso8601String()
    ]]);
})->middleware('auth:sanctum');
```

### Anti-Patterns to Avoid

- **Synchronous exports in controllers:** Blocks HTTP request, causes timeouts for large datasets. Use background jobs instead.
- **Cache::forget() in controllers:** Scatters cache logic, easy to miss. Centralize in event listeners.
- **Loading entire dataset in memory:** Causes OOM errors. Use chunk() or lazy collections.
- **Hardcoded cache keys:** Difficult to manage, prone to collisions. Use hierarchical prefix pattern (agency:type:id).
- **No cache expiration:** Stale data served indefinitely. Always use TTL-based expiration.
- **Export files in public storage:** Security risk, anyone can download. Use signed URLs from private storage.
- **Ignoring tenant context in cache keys:** Cross-tenant data leakage. Include tenant_id in cache keys.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| CSV generation | fputcsv() with manual escaping | league/csv | UTF-8 BOM, proper field escaping, newline handling, RFC 4180 compliance |
| Excel generation | Spreadsheet writer from scratch | phpspreadsheet | Cell styling, memory management, XLSX format complexity (ZIP XML) |
| Cache backend | Redis client directly | Laravel Cache facade | Unified API, driver swapping, tag support, testing fakes |
| Event system | Manual cache clearing calls | Laravel model events | Automatic, framework integration, testable |
| File storage | Direct filesystem writes | Laravel Storage | Abstraction over disk drivers, signed URLs, testable fakes |
| Queue workers | cron-based polling | Laravel Queues + Supervisor | Proper daemon behavior, memory management, auto-restart |
| Scheduled tasks | cron jobs | Laravel Scheduler | Environment-aware, one schedule() call, mutex locks |

**Key insight:** Laravel provides mature solutions for all problems in this phase. Building custom implementations introduces maintenance burden, security risks (file handling, cache collisions), and testing complexity. Use framework features.

## Common Pitfalls

### Pitfall 1: Export Timeout for Large Datasets

**What goes wrong:** Export job exceeds 5-minute timeout, JobStatus marked failed, user sees error.

**Why it happens:** Unoptimized queries (N+1), loading entire dataset in memory, inefficient CSV writing.

**How to avoid:**
- Use query chunking: `$query->chunk(1000, callback)`
- Select only needed columns: `->select(['id', 'name', 'status'])`
- Eager load relationships: `->with('tenant')`
- Estimate row count before export, warn user if > 100K
- Use streaming CSV writers (League CSV supports streams)

**Warning signs:** Export takes > 2 minutes for 10K rows, memory usage spikes, browser shows loading spinner.

### Pitfall 2: Cache Keys Collide Across Tenants

**What goes wrong:** Tenant A sees Tenant B's cached data, data leakage, security issue.

**Why it happens:** Cache keys don't include tenant_id, global keys used for tenant-specific data.

**How to avoid:**
- Always include tenant_id in per-tenant cache keys: `agency:dashboard:metrics:{tenant_id}`
- Use hierarchical key structure: `agency:{type}:{id}`
- Separate global keys from tenant-specific keys: `agency:dashboard:global` vs `agency:dashboard:metrics:123`
- Test cache isolation in multi-tenant scenarios

**Warning signs:** Dashboard shows wrong product counts, tenant dropdown shows incorrect data, tests fail intermittently.

### Pitfall 3: Stale Cache After Updates

**What goes wrong:** User updates tenant name, dashboard still shows old name, confusion about data accuracy.

**Why it happens:** Cache not invalidated after model changes, TTL too long, event listeners not registered.

**How to avoid:**
- Register event listeners in AppServiceProvider boot()
- Test cache invalidation: update model, check cache cleared
- Use appropriate TTL: 5 minutes for metrics (not 1 hour)
- Manual cache clear for debugging: `php artisan cache:clear`

**Warning signs:** Users report stale data, dashboard shows old stats, clearing cache fixes issue temporarily.

### Pitfall 4: Export Files Accumulate on Disk

**What goes wrong:** storage/exports/ directory fills with old exports, disk space exhausted.

**Why it happens:** No cleanup job, export files never deleted, retention policy not enforced.

**How to avoid:**
- Create scheduled command: `php artisan exports:cleanup`
- Run hourly via Laravel Scheduler: `$schedule->command('exports:cleanup')->hourly()`
- Delete files older than 24 hours: `Storage::disk('exports')->files()` + `now()->subDay()->gt($file->getLastModified())`
- Monitor disk usage: `df -h` alert if > 80%

**Warning signs:** Disk usage increasing steadily, old export files in storage/exports/, deployment fails due to disk full.

### Pitfall 5: Signed URL Expired Before Download

**What goes wrong:** User clicks download link after 24 hours, gets 403/404 error, poor UX.

**Why it happens:** Signed URL expiration too short, user delays download, link shared after expiration.

**How to avoid:**
- Set appropriate expiration: 24 hours for exports, 1 hour for sensitive files
- Show expiration time in UI: "Link expires in 23 hours"
- Allow regeneration: "Request new download link" button
- Store export metadata (created_at) for regeneration logic

**Warning signs:** Support tickets about broken download links, users asking for re-exports, access logs show 403s for export URLs.

## Code Examples

Verified patterns from official sources:

### Cache::remember() with TTL

```php
// Source: https://laravel.com/docs/11.x/cache#retrieving-items
use Illuminate\Support\Facades\Cache;

$metrics = Cache::remember('agency:dashboard:metrics:' . $tenantId, 300, function () {
    return [
        'total_products' => Product::count(),
        'active_tenants' => Tenant::where('status', 'active')->count(),
    ];
});
```

### Model Event Listeners

```php
// Source: https://laravel.com/docs/11.x/eloquent#events
use App\Models\Tenant;
use App\Listeners\InvalidateTenantCache;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Tenant::created(InvalidateTenantCache::class);
        Tenant::updated(InvalidateTenantCache::class);
        Tenant::deleted(InvalidateTenantCache::class);
    }
}
```

### Signed URLs for Temporary Downloads

```php
// Source: https://laravel.com/docs/11.x/filesystem#file-urls
use Illuminate\Support\Facades\Storage;

$url = Storage::disk('exports')->temporaryUrl(
    'synclogs_acme-inc_20260314.csv',
    now()->addHours(24)
);
```

### Background Job Dispatching

```php
// Source: https://laravel.com/docs/11.x/queues#dispatching-jobs
use App\Jobs\ExportSyncLogs;
use App\Models\JobStatus;

$jobStatus = JobStatus::create([
    'status' => JobStatusEnum::PENDING,
    'type' => 'export_sync_logs'
]);

ExportSyncLogs::dispatch($jobStatus->id, $filters, $format);
```

### Chunked Query Processing

```php
// Source: https://laravel.com/docs/11.x/queries#chunking-results
use App\Models\SyncLog;

SyncLog::where('tenant_id', $tenantId)
    ->chunk(1000, function ($logs) use ($csv) {
        foreach ($logs as $log) {
            $csv->insertOne([$log->name, $log->status]);
        }
    });
```

### Scheduled Commands

```php
// Source: https://laravel.com/docs/11.x/scheduling
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('exports:cleanup')->hourly();
    $schedule->command('cache:warm')->daily()->at('02:00');
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Synchronous exports | Async background jobs | Laravel 5.3 (2016) | Non-blocking UX, scalable for large datasets |
| Manual cache clearing | Event-driven invalidation | Laravel 5.4 (2017) | Automatic cache coherence, less bug-prone |
| File cache | Redis cache | Laravel 5.8 (2019) | Distributed caching, TTL support, tag invalidation |
| Public file downloads | Signed URLs | Laravel 5.6 (2018) | Secure temporary access, no public exposure |
| fputcsv() | league/csv | PHP 5.5 era | UTF-8 support, proper escaping, RFC 4180 compliance |

**Deprecated/outdated:**
- **File::put() for exports:** Use Storage facade for abstraction, testability
- **Cache::forever():** No TTL, stale data risk. Use Cache::remember() with explicit TTL
- **DB::table() for exports:** No model events, cache invalidation breaks. Use Eloquent models
- **Raw Redis calls:** Bypasses Laravel cache abstraction. Use Cache facade

## Open Questions

1. **Exact toast notification duration**
   - What we know: Alpine.js toast component from Phase 7 exists
   - What's unclear: Should export ready toast persist longer (5s vs 3s), show countdown?
   - Recommendation: Use 5-second toast with "Download now" button, auto-dismiss after action

2. **Export queue naming**
   - What we know: Supervisor monitors 2 workers on 'default' queue
   - What's unclear: Should exports use dedicated 'exports' queue to avoid blocking sync jobs?
   - Recommendation: Use 'default' queue for simplicity. If exports delay sync, add dedicated queue in Phase 10.

3. **Cache warming command details**
   - What we know: `php artisan cache:warm` should prime tenant list cache
   - What's unclear: Should it warm all tenants' dashboard metrics too? (slow for many tenants)
   - Recommendation: Warm tenant list only. Dashboard metrics warm on-demand (first user request after deploy).

4. **Export cleanup frequency**
   - What we know: 24-hour retention required
   - What's unclear: Should cleanup run hourly, daily, or every 6 hours?
   - Recommendation: Run hourly to prevent disk space issues. Hourly is negligible overhead (file stat operations).

5. **Troubleshooting examples scope**
   - What we know: docs/ops/TROUBLESHOOTING.md needs sync failures, queue issues, Elasticsearch errors
   - What's unclear: How many examples per category? Include real logs from Phase 6?
   - Recommendation: 3-5 common issues per category. Use anonymized logs from Phase 6 testing if available.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.0.1 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --parallel` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DATAFLOW-01 | Export sync logs to CSV | feature | `php artisan test --filter=ExportSyncLogsTest` | ❌ Wave 0 |
| DATAFLOW-02 | Export products to Excel | feature | `php artisan test --filter=ExportProductCatalogTest` | ❌ Wave 0 |
| DATAFLOW-03 | Export includes tenant/timestamps | feature | `php artisan test --filter=ExportDataContentTest` | ❌ Wave 0 |
| CACHE-01 | Dashboard metrics cached 5 minutes | feature | `php artisan test --filter=DashboardMetricsCacheTest` | ❌ Wave 0 |
| CACHE-02 | Tenant list cached | feature | `php artisan test --filter=TenantListCacheTest` | ❌ Wave 0 |
| CACHE-03 | Cache invalidates on updates | feature | `php artisan test --filter=CacheInvalidationTest` | ❌ Wave 0 |
| OPS-01 | Nginx logs documented | manual | N/A - documentation only | N/A |
| OPS-02 | Laravel logs documented | manual | N/A - documentation only | N/A |
| OPS-03 | Supervisor logs documented | manual | N/A - documentation only | N/A |

### Sampling Rate

- **Per task commit:** `php artisan test --filter=Feature/{ClassName}Test`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- **tests/Feature/ExportSyncLogsTest.php** — covers DATAFLOW-01, DATAFLOW-03
- **tests/Feature/ExportProductCatalogTest.php** — covers DATAFLOW-02
- **tests/Feature/DashboardMetricsCacheTest.php** — covers CACHE-01
- **tests/Feature/TenantListCacheTest.php** — covers CACHE-02
- **tests/Feature/CacheInvalidationTest.php** — covers CACHE-03
- **tests/Unit/ExportServiceTest.php** — export service logic (filename generation, filter application)
- **Framework installation:** None required (PHPUnit 11.0.1 already in composer.json)

## Sources

### Primary (HIGH confidence)

- **Laravel 11 Documentation** — Cache system, Queue system, Event system, File Storage, Signed URLs
  - https://laravel.com/docs/11.x/cache
  - https://laravel.com/docs/11.x/queues
  - https://laravel.com/docs/11.x/eloquent#events
  - https://laravel.com/docs/11.x/filesystem
  - https://laravel.com/docs/11.x/urls

- **League CSV Documentation** — CSV generation patterns, UTF-8 handling, streaming
  - https://csv.thephpleague.com/9.0/

- **PhpSpreadsheet Documentation** — XLSX generation, memory optimization
  - https://phpspreadsheet.readthedocs.io/en/latest/

### Secondary (MEDIUM confidence)

- **CONTEXT.md** — User decisions for Phase 9 implementation
- **STATE.md** — Project context, existing infrastructure (Redis, queues, JobStatus model)
- **REQUIREMENTS.md** — Phase 9 requirements (DATAFLOW-*, CACHE-*, OPS-*)

### Tertiary (LOW confidence)

- **Web search** — Rate-limited, unable to verify 2026 best practices
  - Laravel 11 Redis caching dashboard metrics 2026 (blocked by rate limit)
  - league/csv Laravel Excel export best practices 2026 (blocked by rate limit)
  - phpspreadsheet Laravel Excel XLSX export memory efficient 2026 (blocked by rate limit)
  - Laravel event listeners cache invalidation automatic 2026 (blocked by rate limit)
  - Laravel signed URLs temporary file download secure 2026 (blocked by rate limit)

**Note:** Research relies on established Laravel patterns from official documentation and existing project context. Web search rate limiting prevented verification of 2026-specific updates, but Laravel 11 is stable (released March 2024) and patterns are well-documented. Confidence is MEDIUM due to inability to verify current-year best practices via web search.

## Metadata

**Confidence breakdown:**
- Standard stack: MEDIUM - League CSV and PhpSpreadsheet are industry standards, but unable to verify 2026 updates via web search
- Architecture: HIGH - Laravel cache/queue/event patterns are well-established in official docs
- Pitfalls: MEDIUM - Based on common Laravel issues, but unable to verify 2026-specific gotchas

**Research date:** 2026-03-14
**Valid until:** 2026-04-14 (30 days - Laravel 11 is stable, patterns unlikely to change)
