---
phase: 6
slug: catalog-synchronization
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2025-03-13
---

# Phase 6 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PEST 2.x / PHPUnit 10.x |
| **Config file** | phpunit.xml (existing) |
| **Quick run command** | `./vendor/bin/pest --testsuites=Unit --compact` |
| **Full suite command** | `./vendor/bin/pest --parallel` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/pest --testsuites=Unit,Feature --compact`
- **After every plan wave:** Run `./vendor/bin/pest`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 06-01-01 | 01 | 1 | SYNC-01 | feature | `./vendor/bin/pest --testsuites=Feature --group=shopify-sync` | ❌ W0 | ⬜ pending |
| 06-01-02 | 01 | 2 | SYNC-05 | unit | `./vendor/bin/pest --testsuites=Unit --group=product-validation` | ❌ W0 | ⬜ pending |
| 06-01-03 | 01 | 3 | SYNC-06 | feature | `./vendor/bin/pest --testsuites=Feature --group=sync-logging` | ❌ W0 | ⬜ pending |
| 06-02-01 | 02 | 1 | SYNC-03 | feature | `./vendor/bin/pest --testsuites=Feature --group=shopware-sync` | ❌ W0 | ⬜ pending |
| 06-02-02 | 02 | 2 | SYNC-07 | unit | `./vendor/bin/pest --testsuites=Unit --group=sync-storage` | ❌ W0 | ⬜ pending |
| 06-02-03 | 02 | 3 | SYNC-08 | feature | `./vendor/bin/pest --testsuites=Feature --group=sync-status` | ❌ W0 | ⬜ pending |
| 06-03-01 | 03 | 1 | SYNC-09 | integration | `./vendor/bin/pest --testsuites=Integration --group=end-to-end-sync` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Sync/ShopifySyncTest.php` — stubs for SYNC-01, SYNC-05
- [ ] `tests/Feature/Sync/SyncLoggingTest.php` — stubs for SYNC-06
- [ ] `tests/Feature/Sync/ShopwareSyncTest.php` — stubs for SYNC-03, SYNC-07
- [ ] `tests/Feature/Sync/SyncStatusTest.php` — stubs for SYNC-08
- [ ] `tests/Integration/Sync/EndToEndSyncTest.php` — stubs for SYNC-09
- [ ] `tests/Unit/Sync/ProductValidationTest.php` — stubs for data validation
- [ ] `tests/Unit/Sync/SyncStorageTest.php` — stubs for storage logic

*All test files must be created in Wave 0 before implementation begins.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Shopify API authentication | SYNC-01 | Requires real Shopify credentials | 1. Create test store in Shopify 2. Add credentials to tenant 3. Trigger sync 4. Verify products import |
| Shopware API authentication | SYNC-03 | Requires real Shopware instance | 1. Set up local Shopware instance 2. Add API credentials 3. Trigger sync 4. Verify products import |
| Rate limit handling | SYNC-01, SYNC-03 | External API rate limits | 1. Mock slow API responses 2. Trigger sync of large catalog 3. Verify backoff occurs 4. No data loss |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
