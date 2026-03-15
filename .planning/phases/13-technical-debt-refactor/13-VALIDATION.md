---
phase: 13
slug: technical-debt-refactor
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-15
---

# Phase 13 — Validation Strategy

> Per-phase validation contract for feedback sampling during refactoring.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Pest 2.x / PHPUnit 10.x |
| **Config file** | phpunit.xml |
| **Quick run command** | `php artisan test --parallel` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --parallel`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 20 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 13-01-01 | 01 | 1 | REFACTOR-01 | feature | `php artisan test --filter=SanctumAuthTest` | ✅ W0 | ⬜ pending |
| 13-02-01 | 02 | 2 | REFACTOR-02 | feature | `php artisan test --filter=ResourceCollectionTest` | ✅ W0 | ⬜ pending |
| 13-03-01 | 03 | 3 | REFACTOR-03 | feature | `php artisan test --filter=FrontendIntegrationTest` | ✅ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/SanctumAuthTest.php` — stubs for Sanctum authentication tests (REFACTOR-01)
- [ ] `tests/Feature/ResourceCollectionTest.php` — stubs for API Resource Collection tests (REFACTOR-02)
- [ ] `tests/Feature/FrontendIntegrationTest.php` — stubs for frontend integration tests (REFACTOR-03)
- [ ] `tests/Pest.php` — shared fixtures for authenticated users, tenants

*Existing infrastructure covers framework setup. Wave 0 adds refactoring-specific test stubs.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| SPA login flow | REFACTOR-01 | Browser session flow requires manual verification | 1. Open http://localhost:8080/login<br>2. Login with credentials<br>3. Verify session cookie set<br>4. Access /api/v1/sync-logs<br>5. Confirm 200 (not 401) |
| JSON response structure | REFACTOR-02 | Visual verification of response format | 1. Access /api/v1/sync-logs in browser<br>2. Check DevTools Network tab<br>3. Verify `data.meta.last_page` exists<br>4. Confirm `data.data` array structure |
| Modal functionality | REFACTOR-03 | UI interaction requires human testing | 1. Open error log page<br>2. Click "View Details" button<br>3. Verify modal opens with data<br>4. Confirm no JavaScript errors |

*Automated tests verify backend structure and logic. Manual verifications ensure browser-based functionality.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 20s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
