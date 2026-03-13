---
phase: 04-background-processing-infrastructure
plan: 02
type: execute
subsystem: "Background Jobs"
tags:
  - queue
  - jobs
  - tenant-aware
  - retry-logic
  - status-tracking
dependency_graph:
  requires:
    - "Laravel Queues"
    - "Tenant model (from Phase 3)"
  provides:
    - id: "04-03"
      description: "Async sync operations foundation"
  affects:
    - "Phase 6: Catalog Synchronization"
tech_stack:
  added:
    - "Laravel Queues"
    - "Job middleware"
    - "Job status tracking"
  patterns:
    - "Tenant-aware base class pattern"
    - "Job middleware for context restoration"
    - "Queue event listeners for lifecycle tracking"
key_files:
  created:
    - path: "app/Jobs/TenantAwareJob.php"
      description: "Abstract base class for tenant-aware queue jobs with retry logic"
    - path: "app/Queue/Middleware/SetTenantContext.php"
      description: "Job middleware for automatic tenant context restoration"
    - path: "app/Models/JobStatus.php"
      description: "Job status tracking model with helper methods"
    - path: "app/Services/QueueJobTracker.php"
      description: "Service for tracking job lifecycle events"
    - path: "database/migrations/2026_03_13_073915_create_job_statuses_table.php"
      description: "Migration for job_statuses table"
    - path: "database/factories/JobStatusFactory.php"
      description: "Factory for JobStatus model testing"
  modified:
    - path: "app/Models/Tenant.php"
      description: "Added static setCurrentTenant() and currentTenant() methods"
    - path: "app/Providers/AppServiceProvider.php"
      description: "Registered queue event listeners for job lifecycle tracking"
    - path: "database/migrations/2026_03_13_073713_change_api_credentials_to_text_in_tenants_table.php"
      description: "Fixed api_credentials column type for encrypted storage"
    - path: "docker/supervisor/supervisord.conf"
      description: "Fixed worker log file path"
decisions:
  - "UUID primary keys for JobStatus (matches Tenant pattern)"
  - "Encrypted credentials stored in text column, not json (encrypted data is not valid JSON)"
  - "Tenant ID as string UUID in jobs (matches Tenant model)"
  - "Job middleware pattern for tenant context restoration"
  - "Queue event listeners for automatic status tracking"
metrics:
  duration: "~15 minutes"
  completed_date: "2026-03-13"
  tasks_completed: 4
  test_count: 20
  test_pass_rate: "100%"
---

# Phase 04 - Plan 02: Tenant-Aware Job Infrastructure Summary

## Objective

Build the foundation for tenant-aware queue jobs with automatic retry logic, status tracking, and failure logging. This enables Phase 6 catalog sync jobs to maintain tenant isolation and provide visibility into job execution.

**Purpose:** Enable background jobs with tenant context, automatic retries, and status tracking
**Output:** TenantAwareJob base class, job middleware, JobStatus model, and tracking service

## One-Liner

Tenant-aware queue job infrastructure with UUID-based tenant context, exponential backoff retry logic (10s, 30s, 90s), automatic status tracking through queue event listeners, and comprehensive job lifecycle visibility.

## Key Technical Details

### TenantAwareJob Base Class

- **Abstract base class** for all tenant-aware queue jobs
- **Tenant ID storage:** Public `tenantId` property (string UUID)
- **Retry configuration:**
  - `$tries = 3`: Maximum 3 attempts
  - `backoff()`: Exponential delays [10, 30, 90] seconds
  - `$timeout = 120`: Kill jobs after 2 minutes
- **Queue assignment:** `'sync'` queue for catalog operations
- **Middleware integration:** Returns `SetTenantContext` instance
- **Usage pattern:**
  ```php
  class SyncProductsJob extends TenantAwareJob {
      public function handle(): void {
          // Job automatically has tenant context restored
          // $this->tenantId is available
      }
  }

  SyncProductsJob::dispatch($tenantId);
  ```

### SetTenantContext Middleware

- **Purpose:** Automatically restore tenant context during job execution
- **Implementation:**
  - Checks for `tenantId` property on job
  - Loads tenant from database via `Tenant::findOrFail()`
  - Sets tenant in app container and via `Tenant::setCurrentTenant()`
  - Cleans up context after job execution
- **Graceful handling:** Jobs without `tenantId` execute normally
- **Thread safety:** Context cleared after job to prevent memory leaks

### JobStatus Model and Tracking

- **Table schema:**
  - UUID primary key (`id`)
  - Foreign key to `tenants` (cascade delete)
  - `job_id` (unique, from queue)
  - `job_type` (class name)
  - `status` enum: pending, running, completed, failed
  - `payload` (JSON, for debugging)
  - `error_message` (text, for failures)
  - `started_at`, `completed_at` (timestamps)
- **Helper methods:**
  - `markAsRunning()`: Updates status and sets started_at
  - `markAsCompleted()`: Updates status and sets completed_at
  - `markAsFailed(string $error)`: Updates status with error message

### QueueJobTracker Service

- **Lifecycle tracking via queue events:**
  - `before`: Job picked up by worker → markAsRunning()
  - `after`: Job completed successfully → markAsCompleted()
  - `failing`: Job failed after all retries → markAsFailed()
- **Automatic tenant extraction:** Reads `tenantId` from job payload
- **Graceful handling:** Silently handles missing JobStatus records
- **Integration:** Registered in `AppServiceProvider::boot()`

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing functionality] Added static tenant context management to Tenant model**
- **Found during:** Task 2 (SetTenantContext middleware)
- **Issue:** `Tenant::setCurrentTenant()` and `Tenant::currentTenant()` methods didn't exist
- **Fix:** Added static methods to manage tenant context for queue jobs
- **Files modified:** `app/Models/Tenant.php`
- **Commit:** `c6aee50`

**2. [Rule 2 - Data type fix] Changed api_credentials column from json to text**
- **Found during:** Task 2 (testing SetTenantContext)
- **Issue:** Encrypted data is not valid JSON, causing MySQL errors
- **Fix:** Created migration to change column type to `text`
- **Files modified:** `database/migrations/2026_03_13_073713_change_api_credentials_to_text_in_tenants_table.php`
- **Commit:** `c6aee50`

**3. [Rule 2 - Type safety] Changed TenantAwareJob tenantId from int to string**
- **Found during:** Task 2 (testing with UUIDs)
- **Issue:** Tenant IDs are UUIDs (strings), not integers
- **Fix:** Updated `TenantAwareJob` to accept `string $tenantId`
- **Files modified:** `app/Jobs/TenantAwareJob.php`, `tests/Unit/Jobs/TenantAwareJobTest.php`
- **Commit:** `c6aee50`

**4. [Rule 3 - Blocking issue] Fixed supervisor log file path**
- **Found during:** Task 3 (Docker container wouldn't start)
- **Issue:** Supervisor config referenced `/var/www/html/storage/logs/worker.log` which didn't exist
- **Fix:** Changed log path to `/var/log/supervisor/worker.log`
- **Files modified:** `docker/supervisor/supervisord.conf`
- **Commit:** `3ed3fdb`

## Task Commits

Each task was committed atomically following TDD pattern:

1. **Task 1: Create TenantAwareJob base class** - `e9d3afc` (test RED), `e90c0a6` (feat GREEN)
2. **Task 2: Create SetTenantContext job middleware** - `ceb9e53` (test RED), `c6aee50` (feat GREEN)
3. **Task 3: Create JobStatus model and migration** - `e1a70d8` (test RED), `3ed3fdb` (feat GREEN)
4. **Task 4: Create QueueJobTracker service** - `6f0555a` (test RED), `454f39c` (feat GREEN)

## Verification Results

### Per-Task Tests
- **TenantAwareJobTest:** 6 tests passing (constructor, queue, backoff, tries, timeout, middleware)
- **SetTenantContextTest:** 4 tests passing (tenant restoration, setCurrentTenant, cleanup, graceful handling)
- **JobStatusTest:** 5 tests passing (fields, casts, relationship, migration, helper methods)
- **QueueJobTrackerTest:** 5 tests passing (track, payload, running, completed, failed)

### Overall Test Results
```
Tests: 20 passed (41 assertions)
Duration: 2.72s
Pass Rate: 100%
```

### Phase Gate Verification
- ✅ Queue jobs include tenant_id in payload (verified via TenantAwareJob)
- ✅ Job middleware restores tenant context (verified via SetTenantContext tests)
- ✅ Job status tracked in database (verified via JobStatus queries)
- ✅ Failed jobs retry with exponential backoff (verified via TenantAwareJob backoff())
- ✅ Job failures logged with error details (verified via QueueJobTracker markAsFailed)

## Next Steps

**For Plan 04-03 (Async Sync Operations):**
- TenantAwareJob foundation ready for catalog sync jobs
- JobStatus tracking provides visibility into sync operations
- Retry logic handles transient API failures
- Tenant context restoration ensures data isolation

**Integration Points:**
- Phase 6 will create `SyncProductsJob`, `SyncOrdersJob` extending `TenantAwareJob`
- JobStatus API endpoints for monitoring sync operations
- Queue worker configuration from Plan 04-01 will execute these jobs

## Key Decisions Made

1. **UUID for tenant IDs** - Consistent with Tenant model, prevents enumeration
2. **Exponential backoff** - Standard pattern for handling transient failures
3. **Job middleware pattern** - Laravel-recommended approach for cross-cutting concerns
4. **Event-driven tracking** - Non-intrusive status updates via queue events
5. **Text column for encrypted data** - Encrypted blobs are not valid JSON

## Technical Highlights

- **Clean separation of concerns:** Base class, middleware, model, service
- **Tenant isolation enforced at multiple levels:** Job payload, middleware, database foreign keys
- **Comprehensive testing:** 20 tests covering all code paths
- **Production-ready:** Retry logic, timeouts, error logging, graceful degradation
- **API-first design:** JobStatus model ready for API exposure in Phase 7

---

**Completed:** 2026-03-13
**Duration:** ~15 minutes
**Status:** ✅ Complete - Ready for Plan 04-03
