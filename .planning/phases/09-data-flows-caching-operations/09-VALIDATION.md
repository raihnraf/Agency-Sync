---
phase: 9
slug: data-flows-caching-operations
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-14
---

# Phase 9 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x (Laravel 11 default) |
| **Config file** | phpunit.xml (exists) |
| **Quick run command** | `php artisan test --parallel` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~60 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=Export*,Cache*,Ops*`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 09-00-01 | 00 | 0 | DATAFLOW-01, CACHE-01 | unit | `php artisan test --filter=ExportTest,CheTest` | ✅ W0 | ⬜ pending |
| 09-01-01 | 01 | 1 | DATAFLOW-01 | integration | `php artisan test --filter=SyncLogExportTest` | ✅ W0 | ⬜ pending |
| 09-01-02 | 01 | 1 | DATAFLOW-02 | integration | `php artisan test --filter=ProductExportTest` | ✅ W0 | ⬜ pending |
| 09-01-03 | 01 | 1 | DATAFLOW-03 | unit | `php artisan test --filter=ExportJobTest` | ✅ W0 | ⬜ pending |
| 09-02-01 | 02 | 2 | CACHE-01 | integration | `php artisan test --filter=DashboardCacheTest` | ✅ W0 | ⬜ pending |
| 09-02-02 | 02 | 2 | CACHE-02 | integration | `php artisan test --filter=TenantListCacheTest` | ✅ W0 | ⬜ pending |
| 09-02-03 | 02 | 2 | CACHE-03 | unit | `php artisan test --filter=CacheInvalidationTest` | ✅ W0 | ⬜ pending |
| 09-03-01 | 03 | 3 | OPS-01, OPS-02, OPS-03 | manual | See manual verification table | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/Exports/SyncLogExportTest.php` — stubs for DATAFLOW-01
- [ ] `tests/Unit/Exports/ProductExportTest.php` — stubs for DATAFLOW-02
- [ ] `tests/Unit/Exports/ExportJobTest.php` — stubs for DATAFLOW-03
- [ ] `tests/Unit/Cache/DashboardCacheTest.php` — stubs for CACHE-01
- [ ] `tests/Unit/Cache/TenantListCacheTest.php` — stubs for CACHE-02
- [ ] `tests/Unit/Cache/CacheInvalidationTest.php` — stubs for CACHE-03
- [ ] Existing phpunit.xml covers all test configuration needs

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| CSV export download | DATAFLOW-01 | Full browser interaction needed | 1. Navigate to sync logs page<br>2. Click "Export CSV"<br>3. Verify file downloads with correct content<br>4. Check tenant isolation |
| Excel export download | DATAFLOW-02 | Binary file validation | 1. Navigate to product catalog<br>2. Click "Export Excel"<br>3. Verify XLSX file opens correctly<br>4. Validate UTF-8 character handling |
| Export notification toast | DATAFLOW-03 | UI feedback | 1. Trigger export<br>2. Wait for background job<br>3. Verify toast appears with download link<br>4. Click link and verify download |
| Cache hit/miss monitoring | CACHE-01 | Redis inspection | 1. Clear Redis cache<br>2. Load dashboard<br>3. Run `redis-cli keys "agency:*"`<br>4. Verify cache keys created<br>5. Reload dashboard and verify cache hit |
| Cache invalidation on update | CACHE-03 | Event timing | 1. Load cached data<br>2. Update related model via API<br>3. Check Redis for cache deletion<br>4. Verify next request fetches fresh data |
| Documentation completeness | OPS-01, OPS-02, OPS-03 | Content review | 1. Read docs/ops/LOGGING.md<br>2. Read docs/ops/TROUBLESHOOTING.md<br>3. Verify all log locations documented<br>4. Follow troubleshooting steps<br>5. Verify docs/ops/README.md links work |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
