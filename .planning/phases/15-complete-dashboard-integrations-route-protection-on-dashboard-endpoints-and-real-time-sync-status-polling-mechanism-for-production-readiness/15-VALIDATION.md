---
phase: 15
slug: complete-dashboard-integrations-route-protection-on-dashboard-endpoints-and-real-time-sync-status-polling-mechanism-for-production-readiness
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-15
---

# Phase 15 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x (Laravel 11) |
| **Config file** | phpunit.xml |
| **Quick run command** | `php artisan test --filter={test_name}` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter={test_name}`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 15-01-01 | 01 | 1 | AUTH-04 | feature | `php artisan test --filter=DashboardAuthTest::test_dashboard_routes_protected` | ❌ W0 | ⬜ pending |
| 15-02-01 | 02 | 1 | SYNC-06 | feature | `php artisan test --filter=SyncStatusPollingTest::test_sync_status_endpoint_returns_logs` | ❌ W0 | ⬜ pending |
| 15-03-01 | 03 | 2 | UI-06 | feature | `php artisan test --filter=TenantListPollingIntegrationTest::test_tenant_list_displays_sync_status` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/DashboardAuthTest.php` — stubs for AUTH-04
- [ ] `tests/Feature/SyncStatusPollingTest.php` — stubs for SYNC-06
- [ ] `tests/Feature/TenantListPollingIntegrationTest.php` — stubs for UI-06
- [ ] `phpunit.xml` — existing configuration

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Sync status visual updates in real-time | UI-06 | Browser DOM polling behavior | 1. Open tenant list in browser 2. Observe status column updates every 2s 3. Verify no memory leaks in console |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
