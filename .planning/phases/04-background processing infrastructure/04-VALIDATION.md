---
phase: 4
slug: background-processing-infrastructure
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-13
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x (Laravel 11 default) |
| **Config file** | phpunit.xml |
| **Quick run command** `make test` or `docker compose exec app php artisan test --parallel` |
| **Full suite command** `make test-coverage` or `docker compose exec app php artisan test --coverage` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `docker compose exec app php artisan test`
- **After every plan wave:** Run `docker compose exec app php artisan test --coverage`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 04-01-01 | 01 | 1 | QUEUE-01 | unit | `docker compose exec app php artisan test --filter=RedisQueueTest` | ✅ created | ⬜ pending |
| 04-01-02 | 01 | 1 | QUEUE-02 | integration | `docker compose exec app php artisan test --filter=SupervisorConfigTest` | ✅ created | ⬜ pending |
| 04-01-03 | 01 | 2 | QUEUE-03 | unit | `docker compose exec app php artisan test --filter=TenantAwareJobTest` | ✅ created | ⬜ pending |
| 04-02-01 | 02 | 1 | QUEUE-04 | unit | `docker compose exec app php artisan test --filter=JobStatusTest` | ✅ created | ⬜ pending |
| 04-02-02 | 02 | 1 | QUEUE-05 | unit | `docker compose exec app php artisan test --filter=SetTenantContextTest` | ✅ created | ⬜ pending |
| 04-02-03 | 02 | 2 | QUEUE-06 | integration | `docker compose exec app php artisan test --filter=QueueJobTrackerTest` | ✅ created | ⬜ pending |
| 04-03-01 | 03 | 1 | SYNC-02 | feature | `docker compose exec app php artisan test --filter=SyncControllerTest` | ✅ created | ⬜ pending |
| 04-03-02 | 03 | 1 | SYNC-04 | feature | `docker compose exec app php artisan test --filter=AsyncSyncOperationTest` | ✅ created | ⬜ pending |
| 04-03-03 | 03 | 2 | TEST-03 | feature | `docker compose exec app php artisan test --filter=TenantContextJobIntegrationTest` | ✅ created | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Unit/Queue/RedisQueueTest.php` — stubs for QUEUE-01
- [x] `tests/Integration/Queue/SupervisorConfigTest.php` — stubs for QUEUE-02
- [x] `tests/Unit/Jobs/TenantAwareJobTest.php` — stubs for QUEUE-03
- [x] `tests/Unit/Models/JobStatusTest.php` — stubs for QUEUE-04
- [x] `tests/Unit/Jobs/SetTenantContextTest.php` — stubs for QUEUE-05 (retry logic)
- [x] `tests/Unit/Services/QueueJobTrackerTest.php` — stubs for QUEUE-06 (failure logging)
- [x] `tests/Unit/Jobs/ExampleSyncJobTest.php` — stubs for SYNC-02
- [x] `tests/Feature/Queue/SyncControllerTest.php` — stubs for SYNC-02 (async dispatch)
- [x] `tests/Feature/Queue/AsyncSyncOperationTest.php` — stubs for SYNC-04 (exponential backoff)
- [x] `tests/Integration/Jobs/TenantContextJobIntegrationTest.php` — stubs for TEST-03
- [x] `tests/Jobs/BaseJobTest.php` — shared job test fixtures

*Wave 0 complete - test infrastructure established before queue implementation.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Supervisor process monitoring in production | QUEUE-02 | Requires running Supervisor daemon and checking process status | 1. Start container: `make up` 2. Check Supervisor status: `docker compose exec app supervisorctl status` 3. Verify workers are running: `queue-worker-default: RUNNING` |
| Redis queue depth visualization | QUEUE-01 | Requires accessing Redis CLI and queue monitoring | 1. Access Redis: `docker compose exec redis redis-cli` 2. Check queue depth: `LLEN queues:default` 3. Verify jobs are being processed: `LRANGE queues:default 0 -1` |

*These manual verifications complement automated tests for production readiness confirmation.*

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 45s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending

---

*Phase: 04-background processing infrastructure*
*Validation strategy created: 2026-03-13*
*Wave 0 completed: 2026-03-13*
*Nyquist compliance: ✅ compliant*
