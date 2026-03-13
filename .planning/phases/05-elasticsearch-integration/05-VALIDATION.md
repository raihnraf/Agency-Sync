---
phase: 5
slug: elasticsearch-integration
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-13
---

# Phase 5 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 2.x (PHP testing) |
| **Config file** | phpunit.xml (Pest compatible) |
| **Quick run command** | `./vendor/bin/pest --group=elasticsearch` |
| **Full suite command** | `./vendor/bin/pest --group=elasticsearch,search` |
| **Estimated runtime** | ~45 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/pest --group=elasticsearch`
- **After every plan wave:** Run `./vendor/bin/pest --group=elasticsearch,search`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 05-00-01 | 00 | 0 | Nyquist compliance | setup | `./vendor/bin/pest --testsuites=Unit --group=elasticsearch-engine` | ✅ W0 | ⬜ pending |
| 05-00-02 | 00 | 0 | Nyquist compliance | setup | `./vendor/bin/pest --testsuites=Feature --group=search-api` | ✅ W0 | ⬜ pending |
| 05-00-03 | 00 | 0 | Nyquist compliance | setup | `./vendor/bin/pest --testsuites=Integration --group=fuzzy-search` | ✅ W0 | ⬜ pending |
| 05-00-04 | 00 | 0 | Nyquist compliance | setup | `./vendor/bin/pest --testsuites=Integration --group=tenant-scoping` | ✅ W0 | ⬜ pending |
| 05-00-05 | 00 | 0 | Nyquist compliance | setup | `./vendor/bin/pest --testsuites=Feature --group=async-indexing` | ✅ W0 | ⬜ pending |
| 05-00-06 | 00 | 0 | Nyquist compliance | setup | `php -r "require 'tests/bootstrap.php';"` | ✅ W0 | ⬜ pending |
| 05-01-01 | 01 | 1 | SEARCH-05, SEARCH-07 | unit | `./vendor/bin/pest --testsuites=Unit --group=elasticsearch-engine` | ✅ W0 | ⬜ pending |
| 05-01-02 | 01 | 1 | SEARCH-05, SEARCH-06 | feature | `./vendor/bin/pest --testsuites=Feature --group=elasticsearch-index` | ✅ W0 | ⬜ pending |
| 05-01-03 | 01 | 1 | SEARCH-07 | integration | `./vendor/bin/pest --testsuites=Integration --group=elasticsearch-client` | ✅ W0 | ⬜ pending |
| 05-02-01 | 02 | 2 | SEARCH-01, SEARCH-02 | feature | `./vendor/bin/pest --testsuites=Feature --group=search-api` | ✅ W0 | ⬜ pending |
| 05-02-02 | 02 | 2 | SEARCH-03, SEARCH-04 | integration | `./vendor/bin/pest --testsuites=Integration --group=fuzzy-search` | ✅ W0 | ⬜ pending |
| 05-02-03 | 02 | 2 | SEARCH-06 | unit | `./vendor/bin/pest --testsuites=Unit --group=tenant-scoping` | ✅ W0 | ⬜ pending |
| 05-03-01 | 03 | 3 | QUEUE-07, SEARCH-05 | feature | `./vendor/bin/pest --testsuites=Feature --group=async-indexing` | ✅ W0 | ⬜ pending |
| 05-03-02 | 03 | 3 | SEARCH-05 | integration | `./vendor/bin/pest --testsuites=Integration --group=index-operations` | ✅ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Unit/Search/ElasticsearchEngineTest.php` — stubs for SEARCH-05, SEARCH-07
- [x] `tests/Feature/Search/SearchApiTest.php` — stubs for SEARCH-01, SEARCH-02
- [x] `tests/Integration/Search/FuzzySearchTest.php` — stubs for SEARCH-03, SEARCH-04
- [x] `tests/Integration/Search/TenantScopingTest.php` — stubs for SEARCH-06
- [x] `tests/Feature/Search/AsyncIndexingTest.php` — stubs for QUEUE-07
- [x] `tests/bootstrap.php` — Elasticsearch client factory for test isolation
- [x] Existing Pest infrastructure covers framework setup

**Wave 0 Status:** COMPLETE — Plan 05-00 creates all test stubs and infrastructure.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Search performance < 500ms with 100K products | SEARCH-02 | Requires realistic data volume and timing measurement | 1. Seed 100K products per tenant<br>2. Run search queries with fuzzy matching<br>3. Measure response time<br>4. Verify < 500ms threshold |
| Sub-second fuzzy matching with typos | SEARCH-03 | Requires subjective typo tolerance validation | 1. Search with common typos (e.g., "iphne" for "iphone")<br>2. Verify relevant results returned<br>3. Check fuzzy behavior works as expected |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
