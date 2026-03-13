---
phase: 6
slug: catalog-synchronization
status: draft
nyquist_compliant: true
wave_0_complete: true
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
| 06-01-01 | 01 | 1 | SYNC-05 | unit | `./vendor/bin/pest --testsuites=Unit --filter=SyncLogModelTest` | ✅ W0 | ⬜ pending |
| 06-01-02 | 01 | 2 | SYNC-03 | unit | `./vendor/bin/pest --testsuites=Unit --filter=ProductValidatorTest` | ✅ W0 | ⬜ pending |
| 06-01-03 | 01 | 3 | SYNC-07 | unit | `./vendor/bin/pest --testsuites=Unit --filter=ShopifySyncServiceTest` | ✅ W0 | ⬜ pending |
| 06-01-04 | 01 | 3 | SYNC-08 | unit | `./vendor/bin/pest --testsuites=Unit --filter=ShopwareSyncServiceTest` | ✅ W0 | ⬜ pending |
| 06-01-05 | 01 | 4 | SYNC-07 | unit | `./vendor/bin/pest --testsuites=Unit --filter=FetchShopifyProductsJobTest` | ✅ W0 | ⬜ pending |
| 06-01-06 | 01 | 4 | SYNC-08 | unit | `./vendor/bin/pest --testsuites=Unit --filter=FetchShopwareProductsJobTest` | ✅ W0 | ⬜ pending |
| 06-01-07 | 01 | 5 | SYNC-01 | feature | `./vendor/bin/pest --testsuites=Feature --filter=ShopifySyncTriggerTest` | ✅ W0 | ⬜ pending |
| 06-02-01 | 02 | 2 | SYNC-09 | unit | `./vendor/bin/pest --testsuites=Unit --filter=ProductStorageTest` | ✅ W0 | ⬜ pending |
| 06-02-02 | 02 | 2 | SYNC-09 | unit | `./vendor/bin/pest --testsuites=Unit --filter=ProcessProductsChunkJobTest` | ✅ W0 | ⬜ pending |
| 06-02-03 | 02 | 2 | SYNC-09 | unit | `./vendor/bin/pest --testsuites=Unit --filter=IndexProductsChunkJobTest` | ✅ W0 | ⬜ pending |
| 06-02-04 | 02 | 3 | SYNC-07, SYNC-08 | unit | `./vendor/bin/pest --testsuites=Unit --filter=FetchShopifyProductsJobUpdatedTest` | ✅ W0 | ⬜ pending |
| 06-02-05 | 02 | 3 | SYNC-07, SYNC-08 | integration | `./vendor/bin/pest --testsuites=Integration --filter=ShopifyEndToEndSyncTest` | ✅ W0 | ⬜ pending |
| 06-03-01 | 03 | 3 | SYNC-06 | unit | `./vendor/bin/pest --testsuites=Unit --filter=SyncStatusTest` | ✅ W0 | ⬜ pending |
| 06-03-02 | 03 | 3 | SYNC-06 | unit | `./vendor/bin/pest --testsuites=Unit --filter=SyncLogResourceTest` | ✅ W0 | ⬜ pending |
| 06-03-03 | 03 | 3 | SYNC-06 | feature | `./vendor/bin/pest --testsuites=Feature --filter=SyncStatusEndpointsTest` | ✅ W0 | ⬜ pending |
| 06-03-04 | 03 | 3 | SYNC-06 | feature | `./vendor/bin/pest --testsuites=Feature --filter=SyncHistoryEndpointsTest` | ✅ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Feature/Sync/ShopifySyncTriggerTest.php` — stubs for SYNC-01, SYNC-05
- [x] `tests/Feature/Sync/SyncLoggingTest.php` — stubs for SYNC-06
- [x] `tests/Feature/Sync/ShopwareSyncTriggerTest.php` — stubs for SYNC-03, SYNC-07
- [x] `tests/Feature/Sync/SyncStatusTest.php` — stubs for SYNC-08
- [x] `tests/Integration/Sync/EndToEndSyncTest.php` — stubs for SYNC-09
- [x] `tests/Unit/Sync/ProductValidationTest.php` — stubs for data validation
- [x] `tests/Unit/Sync/SyncStorageTest.php` — stubs for storage logic
- [x] `tests/Unit/Sync/SyncLogModelTest.php` — stubs for SyncLog model
- [x] `tests/Unit/Sync/ProductValidatorTest.php` — stubs for ProductValidator
- [x] `tests/Unit/Sync/ShopifySyncServiceTest.php` — stubs for ShopifySyncService
- [x] `tests/Unit/Sync/ShopwareSyncServiceTest.php` — stubs for ShopwareSyncService
- [x] `tests/Unit/Sync/FetchShopifyProductsJobTest.php` — stubs for FetchShopifyProductsJob
- [x] `tests/Unit/Sync/FetchShopwareProductsJobTest.php` — stubs for FetchShopwareProductsJob
- [x] `tests/Unit/Sync/ProductStorageTest.php` — stubs for Product model
- [x] `tests/Unit/Sync/ProcessProductsChunkJobTest.php` — stubs for ProcessProductsChunkJob
- [x] `tests/Unit/Sync/IndexProductsChunkJobTest.php` — stubs for IndexProductsChunkJob
- [x] `tests/Unit/Enums/SyncStatusTest.php` — stubs for SyncStatus enum
- [x] `tests/Unit/Resources/SyncLogResourceTest.php` — stubs for SyncLogResource
- [x] `tests/Feature/Sync/SyncStatusEndpointsTest.php` — stubs for status endpoints
- [x] `tests/Feature/Sync/SyncHistoryEndpointsTest.php` — stubs for history endpoints

*All test files created in Wave 0 before implementation begins.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Shopify API authentication | SYNC-01 | Requires real Shopify credentials | 1. Create test store in Shopify 2. Add credentials to tenant 3. Trigger sync 4. Verify products import |
| Shopware API authentication | SYNC-03 | Requires real Shopware instance | 1. Set up local Shopware instance 2. Add API credentials 3. Trigger sync 4. Verify products import |
| Rate limit handling | SYNC-01, SYNC-03 | External API rate limits | 1. Mock slow API responses 2. Trigger sync of large catalog 3. Verify backoff occurs 4. No data loss |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter
- [x] `wave_0_complete: true` set in frontmatter

**Approval:** pending
