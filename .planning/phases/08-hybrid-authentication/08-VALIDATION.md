---
phase: 08
slug: hybrid-authentication
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-14
---

# Phase 08 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x (Laravel 11 default) |
| **Config file** | phpunit.xml (existing) |
| **Quick run command** | `php artisan test --parallel` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --testsuite=Feature`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 08-01-01 | 01 | 1 | AUTH-WEB-01 | feature | `php artisan test --testsuite=Feature --filter=BreezeInstallTest` | ✅ W0 | ⬜ pending |
| 08-01-02 | 01 | 1 | AUTH-WEB-01 | feature | `php artisan test --testsuite=Feature --filter=AuthRoutesTest` | ✅ W0 | ⬜ pending |
| 08-02-01 | 02 | 1 | AUTH-WEB-05 | feature | `php artisan test --testsuite=Feature --filter=LoginTest` | ✅ W0 | ⬜ pending |
| 08-02-02 | 02 | 1 | AUTH-WEB-04 | feature | `php artisan test --testsuite=Feature --filter=LoginRedirectTest` | ✅ W0 | ⬜ pending |
| 08-03-01 | 03 | 1 | AUTH-WEB-02 | feature | `php artisan test --testsuite=Feature --filter=SessionAuthTest` | ✅ W0 | ⬜ pending |
| 08-04-01 | 04 | 1 | AUTH-WEB-03 | feature | `php artisan test --testsuite=Feature --filter=ApiSanctumTest` | ✅ W0 | ⬜ pending |
| 08-05-01 | 05 | 1 | AUTH-WEB-04 | feature | `php artisan test --testsuite=Feature --filter=CustomAdminCommandTest` | ✅ W0 | ⬜ pending |
| 08-06-01 | 06 | 1 | AUTH-WEB-04 | feature | `php artisan test --testsuite=Feature --filter=BladeCustomizationTest` | ✅ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Auth/BreezeInstallTest.php` — verify Laravel Breeze installation, auth routes exist
- [ ] `tests/Feature/Auth/LoginTest.php` — verify login functionality, session creation
- [ ] `tests/Feature/Auth/SessionAuthTest.php` — verify session middleware on web routes
- [ ] `tests/Feature/Auth/ApiSanctumTest.php` — verify API routes still use Sanctum tokens
- [ ] `tests/Feature/Auth/CustomAdminCommandTest.php` — verify artisan command creates admin users
- [ ] `tests/Feature/Auth/BladeCustomizationTest.php` — verify login page customization

*Existing infrastructure: PHPUnit 10.x configured, Laravel test helpers available.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Login page visual appearance | AUTH-WEB-04 | Visual design validation (branding, TailwindCSS indigo theme) | 1. Visit /login in browser. 2. Verify AgencySync logo displays. 3. Verify indigo color scheme matches dashboard. 4. Verify custom footer text. |
| Remember me functionality | AUTH-WEB-05 | Requires browser cookie inspection across sessions | 1. Login with "Remember me" checked. 2. Close browser. 3. Reopen and visit /dashboard. 4. Verify still authenticated (no redirect to /login). |
| Logout redirect behavior | AUTH-WEB-05 | User flow verification | 1. Login at /login. 2. Click logout in dashboard sidebar. 3. Verify redirect to /home (public landing page). |
| Registration route absence | AUTH-WEB-04 | Security verification (no self-registration) | 1. Attempt to access /register. 2. Verify 404 response. 3. Verify no registration link in login page. |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
