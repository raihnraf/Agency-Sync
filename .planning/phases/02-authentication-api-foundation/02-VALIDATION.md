---
phase: 02
slug: authentication-api-foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 02 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.0.1 |
| **Config file** | phpunit.xml (exists) |
| **Quick run command** | `./vendor/bin/phpunit --testsuite=Feature --filter=Auth` |
| **Full suite command** | `./vendor/bin/phpunit` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/phpunit --testsuite=Feature --filter=Auth`
- **After every plan wave:** Run `./vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | AUTH-01 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=Register` | ❌ W0 | ⬜ pending |
| 02-01-02 | 01 | 1 | AUTH-02 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=Login` | ❌ W0 | ⬜ pending |
| 02-01-03 | 01 | 1 | AUTH-03 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=ProtectedEndpoint` | ❌ W0 | ⬜ pending |
| 02-01-04 | 01 | 1 | AUTH-04 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=Logout` | ❌ W0 | ⬜ pending |
| 02-02-01 | 02 | 1 | API-01 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=JsonResponse` | ❌ W0 | ⬜ pending |
| 02-02-02 | 02 | 1 | API-02 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=Validation` | ❌ W0 | ⬜ pending |
| 02-03-01 | 03 | 2 | API-03 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=ApiVersioning` | ❌ W0 | ⬜ pending |
| 02-03-02 | 03 | 2 | API-04 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=HttpStatusCodes` | ❌ W0 | ⬜ pending |
| 02-04-01 | 04 | 2 | API-05 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=RateLimiting` | ❌ W0 | ⬜ pending |
| 02-04-02 | 04 | 2 | API-06 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=Pagination` | ❌ W0 | ⬜ pending |
| 02-05-01 | 05 | 2 | API-07 | feature | `./vendor/bin/phpunit --testsuite=Feature --filter=TokenExpiration` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Auth/RegisterTest.php` — stubs for AUTH-01 (registration endpoint)
- [ ] `tests/Feature/Auth/LoginTest.php` — stubs for AUTH-02, AUTH-03 (login and protected access)
- [ ] `tests/Feature/Auth/LogoutTest.php` — stubs for AUTH-04 (logout and token invalidation)
- [ ] `tests/Feature/Api/JsonResponseTest.php` — stubs for API-01 (consistent JSON structure)
- [ ] `tests/Feature/Api/ValidationTest.php` — stubs for API-02 (request validation)
- [ ] `tests/Feature/Api/ApiVersioningTest.php` — stubs for API-03 (versioned endpoints)
- [ ] `tests/Feature/Api/HttpStatusCodesTest.php` — stubs for API-04 (proper status codes)
- [ ] `tests/Feature/Api/RateLimitingTest.php` — stubs for API-05 (rate limiting)
- [ ] `tests/Feature/Api/PaginationTest.php` — stubs for API-06 (pagination metadata)
- [ ] `tests/Feature/Api/TokenExpirationTest.php` — stubs for API-07 (4-hour inactivity)

---

## Manual-Only Verifications

All phase behaviors have automated verification through PHPUnit feature tests.

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
