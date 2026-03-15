---
phase: 14
slug: critical-frontend-fixes
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-15
---

# Phase 14 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest PHP 2.x / PHPUnit |
| **Config file** | phpunit.xml |
| **Quick run command** | `./vendor/bin/pest --group=frontend` |
| **Full suite command** | `./vendor/bin/pest` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/pest --group=frontend`
- **After every plan wave:** Run `./vendor/bin/pest`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 14-01-01 | 01 | 1 | SEARCH-01 | API endpoint test | `./vendor/bin/pest tests/Feature/ProductSearchEndpointTest.php` | ❌ W0 | ⬜ pending |
| 14-01-02 | 01 | 1 | SEARCH-07 | Frontend integration test | `./vendor/bin/pest tests/Feature/ProductSearchUIIntegrationTest.php` | ❌ W0 | ⬜ pending |
| 14-02-01 | 02 | 1 | SYNC-01 | API endpoint test | `./vendor/bin/pest tests/Feature/SyncDispatchEndpointTest.php` | ❌ W0 | ⬜ pending |
| 14-02-02 | 02 | 1 | UI-05 | Frontend integration test | `./vendor/bin/pest tests/Feature/SyncTriggerUIIntegrationTest.php` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/ProductSearchEndpointTest.php` — stubs for SEARCH-01, SEARCH-07
- [ ] `tests/Feature/SyncDispatchEndpointTest.php` — stubs for SYNC-01, UI-05
- [ ] `tests/Feature/ProductSearchUIIntegrationTest.php` — frontend integration tests
- [ ] `tests/Feature/SyncTriggerUIIntegrationTest.php` — frontend integration tests
- [ ] Existing Pest infrastructure covers base framework

*Note: Wave 0 creates test stubs for all 4 verification points*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Browser-based search UI interaction | UI-07 | Requires manual browser test | 1. Login as admin 2. Navigate to client catalog 3. Use search bar 4. Verify results appear |
| Browser-based sync trigger interaction | UI-05 | Requires manual browser test | 1. Login as admin 2. Navigate to dashboard 3. Click sync button 4. Verify sync starts |

*Note: Core API functionality is automated; UI smoke tests remain manual*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
