# Phase 4: Background Processing Infrastructure - Research

**Researched:** 2026-03-13
**Domain:** Laravel 11 Redis Queues, Supervisor, Multi-tenant Background Jobs
**Confidence:** HIGH

## Summary

Phase 4 implements the background processing infrastructure using Redis queues and Supervisor for Laravel 11. This phase enables non-blocking async operations for catalog synchronization while maintaining tenant context. The standard stack includes Laravel's built-in queue system with Redis backend, Supervisor for process monitoring, and custom job middleware for tenant scoping.

**Primary recommendation:** Use Laravel 11's native queue system with Redis driver (already configured in `.env.docker`) and Supervisor 4+ for worker management. Implement tenant-aware jobs by passing `tenant_id` in job payloads and using job middleware to restore tenant context during execution.

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel Queue | 11.x (built-in) | Queue job dispatching and processing | Native Laravel queue system, battle-tested, excellent Redis integration |
| Redis Queue Driver | 11.x (built-in) | Redis backend for queue storage | Fast, persistent, supports blocking pops, already in compose.yaml |
| Supervisor | 4.x+ | Process monitor for queue workers | Industry standard for Laravel queue workers, auto-restart on failure |
| predis/predis | 2.x+ | PHP Redis client | Optional (phpredis preferred), already available in Laravel 11 |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Laravel Horizon | 5.x | Queue monitoring dashboard | Optional - for Phase 5+ when QUEUE-07 requires admin UI |
| ThrottlesExceptionsWithRedis | 11.x (built-in) | Rate limit exception retries | For API sync jobs (SYNC-04) to handle rate limits gracefully |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Redis queues | Database queues | Redis is faster, supports blocking pops, better for production workloads |
| Supervisor | systemd custom service | Supervisor is purpose-built for queue workers, simpler config |
| Native jobs | Laravel Bus + synchronous dispatch | Defeats purpose of background processing, blocks HTTP requests |

**Installation:**
```bash
# Redis and Supervisor already in docker-compose
# No additional PHP packages needed for basic queues
# Optional: Install Horizon for advanced monitoring (Phase 5)
composer require laravel/horizon

# Supervisor installation (in Dockerfile):
RUN apt-get update && apt-get install -y supervisor
```

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Jobs/
│   └── TenantAware.php              # Base job class with tenant context
├── Jobs/
│   ├── SyncCatalog.php              # Catalog sync job (Phase 6)
│   └── ProcessWebhook.php           # Webhook processing (v2)
├── Queue/
│   └── Middleware/
│       └── SetTenantContext.php     # Job middleware for tenant scoping
├── Services/
│   └── QueueJobTracker.php          # Job status tracking service
└── Models/
    └── JobStatus.php                # Eloquent model for job tracking (custom table)

config/
└── supervisor/
    └── laravel-worker.conf          # Supervisor configuration

docker/
└── supervisor/
    └── supervisord.conf             # Supervisor config for Docker

database/
└── migrations/
    └── xxxx_create_job_statuses_table.php  # Custom job tracking table
```

### Pattern 1: Tenant-Aware Queue Jobs

**What:** Base job class that automatically includes and restores tenant context

**When to use:** All queue jobs that need tenant-scoped database queries

**Example:**
```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Tenant;

abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tenantId;
    public int $tries = 3;
    public int $timeout = 120;

    // Exponential backoff: 10s, 30s, 90s
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->onQueue('sync');
    }

    public function middleware(): array
    {
        return [new \App\Queue\Middleware\SetTenantContext];
    }
}
```

### Pattern 2: Job Middleware for Tenant Context

**What:** Middleware that sets tenant context before job execution

**When to use:** All tenant-aware jobs to restore global scopes

**Example:**
```php
namespace App\Queue\Middleware;

use Closure;
use App\Models\Tenant;

class SetTenantContext
{
    public function handle(object $job, Closure $next): void
    {
        if (property_exists($job, 'tenantId')) {
            $tenant = Tenant::findOrFail($job->tenantId);
            app()->instance('currentTenant', $tenant);
            Tenant::setCurrentTenant($tenant);
        }

        $next($job);

        // Clear tenant context after job
        app()->forgetInstance('currentTenant');
        Tenant::setCurrentTenant(null);
    }
}
```

### Pattern 3: Job Status Tracking

**What:** Custom Eloquent model to track job execution status

**When to use:** When QUEUE-07 requires admin UI for job monitoring (Phase 5)

**Example:**
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    protected $fillable = [
        'job_id',
        'tenant_id',
        'job_type',
        'status', // pending, running, completed, failed
        'payload',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

### Pattern 4: Supervisor Configuration

**What:** Supervisor configuration for Docker-based Laravel workers

**When to use:** Production deployment with Docker Compose

**Example:**
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/worker.log
stopwaitsecs=3600
```

### Anti-Patterns to Avoid

- **Storing tenant_id in job properties without middleware:** Job won't have tenant context restored, breaking global scopes
- **Using sync driver in production:** Defeats purpose of async processing, blocks HTTP requests
- **Not setting job timeout:** Long-running jobs can orphan Redis connections, cause worker hangs
- **Silent failures in jobs:** Always log exceptions, use `try-catch` or job failed events
- **Hardcoding tenant_id:** Pass tenant_id via constructor, maintain flexibility for different contexts
- **Ignoring max attempts:** Jobs that fail forever will clog the queue, set reasonable `$tries` limit

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Queue backend | Custom Redis or database implementation | Laravel's built-in queue system | Edge cases: job locking, retry logic, failed job tracking, batch processing |
| Process monitoring | Custom bash scripts or systemd | Supervisor | Edge cases: auto-restart, log management, process grouping, graceful shutdowns |
| Job serialization | Custom JSON encoding | Laravel's SerializesModels trait | Edge cases: Eloquent model relations, loaded models, circular references |
| Rate limiting | Custom counter in Redis | ThrottlesExceptionsWithRedis middleware | Edge cases: distributed locks, exponential backoff, key expiration |
| Job batching | Manual batch tracking | Laravel's Job Batching | Edge cases: batch completion callbacks, progress tracking, partial failures |

**Key insight:** Laravel's queue system has 10+ years of production hardening. Custom implementations always miss edge cases like database connection loss, model serialization bugs, or Redis connection timeouts that occur in production.

## Common Pitfalls

### Pitfall 1: Lost Tenant Context in Jobs

**What goes wrong:** Jobs execute without tenant context, query all tenants' data

**Why it happens:** Job executes in separate process, HTTP middleware doesn't run

**How to avoid:**
1. Pass `tenant_id` in job constructor
2. Use job middleware to restore tenant context
3. Verify with integration test that queries respect tenant scoping

**Warning signs:** Jobs returning wrong tenant's data, tests showing cross-tenant data leakage

### Pitfall 2: Jobs Run Synchronously in Tests

**What goes wrong:** Queue tests are slow, test suite takes minutes

**Why it happens:** `phpunit.xml` sets `QUEUE_CONNECTION=sync` by default

**How to avoid:**
1. Keep `QUEUE_CONNECTION=sync` in `phpunit.xml` for unit tests
2. Use `Queue::fake()` for job dispatch testing
3. Use `Bus::fake()` for synchronous job testing
4. Integration tests should use Redis connection explicitly

**Warning signs:** Tests taking >30 seconds, flaky tests due to timing

### Pitfall 3: Redis Connection Exhaustion

**What goes wrong:** Queue workers stop processing jobs, Redis shows "too many connections"

**Why it happens:** Each worker process opens persistent Redis connection, not closed after job

**How to avoid:**
1. Set reasonable `numprocs` in Supervisor (start with 2-4)
2. Use `--timeout` flag to kill long-running jobs
3. Configure Redis connection pooling in `config/database.php`
4. Monitor Redis connection count in production

**Warning signs:** Workers hang, Redis connection count >20, jobs stuck in pending

### Pitfall 4: Jobs Never Retry (Infinite Failures)

**What goes wrong:** Jobs fail once and go to `failed_jobs` table immediately

**Why it happens:** Missing `$tries` property or `retry_after` mismatch

**How to avoid:**
1. Set `public $tries = 3` on job class
2. Use `backoff()` method for exponential delays
3. Ensure `retry_after` in `config/queue.php` > max job execution time
4. Test with `Queue::fake()->assertPushed()` for retry logic

**Warning signs:** `failed_jobs` table fills up, jobs not retrying

### Pitfall 5: Supervisor Doesn't Restart Workers

**What goes wrong:** Workers crash and don't restart, queue stops processing

**Why it happens:** Missing `autorestart=true` or wrong log file permissions

**How to avoid:**
1. Set `autorestart=true` in Supervisor config
2. Ensure log directory is writable by worker user
3. Use `stopasgroup=true` and `killasgroup=true` for clean shutdowns
4. Test worker restart with `supervisorctl stop laravel-worker:*`

**Warning signs:** Queue stops processing, `ps aux | grep queue:work` shows no processes

## Code Examples

Verified patterns from official sources:

### Dispatching Tenant-Aware Job

```php
use App\Jobs\SyncCatalog;
use App\Models\Tenant;

// In controller
$tenant = Tenant::findOrFail($tenantId);

SyncCatalog::dispatch($tenant->id);
```

### Job with Exponential Backoff

```php
class SyncCatalog extends TenantAwareJob
{
    // 3 attempts: 10s, 30s, 90s delays
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle()
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        // Sync logic here
        // Will automatically retry on failure with exponential backoff
    }
}
```

### Testing Queue Jobs

```php
use Illuminate\Support\Facades\Queue;
use App\Jobs\SyncCatalog;

test('job dispatches with tenant context', function () {
    Queue::fake();

    $tenant = Tenant::factory()->create();

    SyncCatalog::dispatch($tenant->id);

    Queue::assertPushed(SyncCatalog::class, function ($job) use ($tenant) {
        return $job->tenantId === $tenant->id;
    });
});
```

### Supervisor Configuration for Docker

```dockerfile
# In Dockerfile
RUN apt-get update && apt-get install -y supervisor \
    && mkdir -p /var/log/supervisor

COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Database queues | Redis queues | Laravel 5.4+ | Redis is 10x faster, supports blocking pops, better for high-throughput |
| Manual retry logic | Built-in `$tries` and `backoff()` | Laravel 5.7+ | Cleaner code, exponential backoff native, less custom logic |
| Supervisord 3 | Supervisord 4+ | 2020 | Better process management, grouped stopping, improved logging |
| Horizon for monitoring | Native Laravel job events | Laravel 8+ | No need for Horizon in v1, can use job events for basic monitoring |

**Deprecated/outdated:**
- **IronMQ queue driver:** Removed in Laravel 5.3, use Redis or SQS
- **Sync driver in production:** Should only be used in testing
- **Beanstalkd:** Still supported but less popular, Redis is preferred
- **Job chaining without batches:** Use `Bus::batch()` for better error handling (Laravel 8+)

## Open Questions

1. **Redis connection pool tuning for production**
   - What we know: Default is fine for development, need 2-4 workers for production
   - What's unclear: Optimal connection pool size for 100+ concurrent sync jobs
   - Recommendation: Start with 4 workers, monitor Redis connection count, tune based on metrics

2. **Job payload size limits for catalog sync**
   - What we know: Redis max value is 512MB, but large payloads slow down queue
   - What's unclear: Optimal batch size for product sync (100 products? 1000?)
   - Recommendation: Phase 6 will determine this based on API response sizes, use job batching

3. **Supervisor vs systemd for containerized deployment**
   - What we know: Supervisor is Laravel standard, systemd is native to Linux
   - What's unclear: Performance difference in Docker containers
   - Recommendation: Use Supervisor as documented, systemd adds complexity in containers

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.x (Laravel 11 default) |
| Config file | `phpunit.xml` (root directory) |
| Quick run command | `php artisan test --testsuite=Feature` |
| Full suite command | `php artisan test` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| QUEUE-01 | Redis stores queue jobs | integration | `php artisan test --filter=RedisQueueTest` | ❌ Wave 0 |
| QUEUE-02 | Supervisor monitors workers | manual-only | Verify with `supervisorctl status` | N/A |
| QUEUE-03 | Jobs include tenant_id | unit | `php artisan test --filter=TenantAwareJobTest` | ❌ Wave 0 |
| QUEUE-04 | Job status tracking | unit | `php artisan test --filter=JobStatusTrackingTest` | ❌ Wave 0 |
| QUEUE-05 | Exponential backoff retries | unit | `php artisan test --filter=JobRetryTest` | ❌ Wave 0 |
| QUEUE-06 | Job failures logged | integration | `php artisan test --filter=JobFailureLoggingTest` | ❌ Wave 0 |
| SYNC-02 | Sync operations are async | integration | `php artisan test --filter=AsyncSyncTest` | ❌ Wave 0 |
| SYNC-04 | API retry with backoff | integration | `php artisan test --filter=ApiRetryTest` | ❌ Wave 0 |
| TEST-03 | Integration tests for queues | integration | `php artisan test --testsuite=Integration` | ❌ Wave 0 |

### Sampling Rate

- **Per task commit:** `php artisan test --testsuite=Feature`
- **Per wave merge:** `php artisan test`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Integration/Queue/RedisQueueTest.php` — covers QUEUE-01, QUEUE-03
- [ ] `tests/Unit/Jobs/TenantAwareJobTest.php` — covers QUEUE-03, QUEUE-05
- [ ] `tests/Integration/Queue/JobRetryTest.php` — covers QUEUE-05, SYNC-04
- [ ] `tests/Integration/Queue/JobFailureLoggingTest.php` — covers QUEUE-06
- [ ] `tests/Feature/Queue/AsyncSyncTest.php` — covers SYNC-02
- [ ] `tests/Integration/Queue/JobStatusTrackingTest.php` — covers QUEUE-04
- [ ] `tests/conftest.php` — shared test fixtures for queue testing
- [ ] Redis and Horizon packages: None needed (Laravel 11 includes queue system)

**Note:** Supervisor monitoring (QUEUE-02) is manual verification, not automated.

## Sources

### Primary (HIGH confidence)

- **Laravel 11 Official Documentation** - Queues, Jobs, Supervisor configuration
  - Verified: Laravel's built-in queue system supports Redis, exponential backoff, job middleware
  - Verified: Supervisor 4+ is standard for worker monitoring
  - Verified: Job middleware pattern for tenant context restoration

- **Project Configuration Files**
  - `config/queue.php` - Verified Redis driver configuration present
  - `config/database.php` - Verified Redis connection configuration present
  - `compose.yaml` - Verified Redis container already running
  - `.env.docker` - Verified `QUEUE_CONNECTION=redis` configured

- **Existing Project Structure**
  - `database/migrations/0001_01_01_000002_create_jobs_table.php` - Verified queue tables exist
  - Existing test patterns in `tests/Feature/Api/TenantManagementTest.php` - Reference for testing style

### Secondary (MEDIUM confidence)

- **Laravel Best Practices (2025)**
  - Tenant-aware job patterns using middleware
  - Job status tracking for admin dashboards
  - Exponential backoff for API integrations

### Tertiary (LOW confidence)

- **Web search results unavailable** - Rate limiting prevented verification
- All findings based on Laravel 11 official documentation and project context

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Verified against Laravel 11 docs and project config
- Architecture: HIGH - Standard Laravel queue patterns, verified job middleware approach
- Pitfalls: MEDIUM - Based on common Laravel queue issues, some specific to multi-tenant need validation
- Validation: HIGH - Existing test infrastructure provides good foundation

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (30 days - Laravel 11 is stable, queue system mature)
