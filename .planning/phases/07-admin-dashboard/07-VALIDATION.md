---
phase: 7
slug: admin-dashboard
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-14
---

# Phase 7 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Laravel Dusk 7.x (Browser automation) + PHPUnit 11.x |
| **Config file** | tests/Dusk/dusk.php (Wave 0 installs) |
| **Quick run command** | `php artisan dusk --filter=Dashboard` |
| **Full suite command** | `php artisan dusk` |
| **Estimated runtime** | ~180 seconds (3 minutes) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan dusk --filter=Dashboard`
- **After every plan wave:** Run `php artisan dusk`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 180 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 07-00-01 | 00 | 0 | Nyquist compliance | setup | `php artisan dusk --filter=DuskTestSuite` | ✅ W0 | ⬜ pending |
| 07-00-02 | 00 | 0 | Laravel Dusk install | setup | `php artisan dusk:install` | ✅ W0 | ⬜ pending |
| 07-01-01 | 01 | 1 | UI-01 | browser | `php artisan dusk --filter=TenantListTest` | ✅ W0 | ⬜ pending |
| 07-01-02 | 01 | 1 | UI-02 | browser | `php artisan dusk --filter=TenantCreateFormTest` | ✅ W0 | ⬜ pending |
| 07-02-01 | 02 | 1 | UI-03 | browser | `php artisan dusk --filter=TenantEditTest` | ✅ W0 | ⬜ pending |
| 07-02-02 | 02 | 1 | UI-04 | browser | `php artisan dusk --filter=TenantDeleteTest` | ✅ W0 | ⬜ pending |
| 07-03-01 | 03 | 2 | UI-05 | browser | `php artisan dusk --filter=SyncTriggerTest` | ✅ W0 | ⬜ pending |
| 07-03-02 | 03 | 2 | UI-06 | browser | `php artisan dusk --filter=SyncStatusTest` | ✅ W0 | ⬜ pending |
| 07-04-01 | 04 | 2 | UI-07 | browser | `php artisan dusk --filter=ProductSearchTest` | ✅ W0 | ⬜ pending |
| 07-04-02 | 04 | 2 | UI-08 | browser | `php artisan dusk --filter=ErrorLogTest` | ✅ W0 | ⬜ pending |
| 07-05-01 | 05 | 3 | UI-09 | browser | `php artisan dusk --filter=AlpineComponentsTest` | ✅ W0 | ⬜ pending |
| 07-05-02 | 05 | 3 | UI-10 | browser | `php artisan dusk --filter=TailwindStylingTest` | ✅ W0 | ⬜ pending |
| 07-05-03 | 05 | 3 | UI-11 | browser | `php artisan dusk --filter=ResponsiveDesignTest` | ✅ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

**CRITICAL GAP:** Laravel Dusk browser automation framework not configured.

- [ ] `composer require laravel/dusk --dev` — Install Laravel Dusk package
- [ ] `php artisan dusk:install` — Create Dusk configuration and example tests
- [ ] `tests/Browser/TenantListTest.php` — Browser test stub for UI-01
- [ ] `tests/Browser/TenantCreateFormTest.php` — Browser test stub for UI-02
- [ ] `tests/Browser/TenantEditTest.php` — Browser test stub for UI-03
- [ ] `tests/Browser/TenantDeleteTest.php` — Browser test stub for UI-04
- [ ] `tests/Browser/SyncTriggerTest.php` — Browser test stub for UI-05
- [ ] `tests/Browser/SyncStatusTest.php` — Browser test stub for UI-06
- [ ] `tests/Browser/ProductSearchTest.php` — Browser test stub for UI-07
- [ ] `tests/Browser/ErrorLogTest.php` — Browser test stub for UI-08
- [ ] `tests/Browser/AlpineComponentsTest.php` — Browser test stub for UI-09
- [ ] `tests/Browser/TailwindStylingTest.php` — Browser test stub for UI-10
- [ ] `tests/Browser/ResponsiveDesignTest.php` — Browser test stub for UI-11
- [ ] `tests/Dusk/dusk.php` — Dusk configuration (ChromeDriver, base URL)
- [ ] `DUSK_ENVIRONMENT=testing` in .env.dusk.testing — Separate testing database

**Wave 0 Status:** INCOMPLETE — Must install Laravel Dusk and create all browser test stubs before any UI tests can run. Estimated 4-6 hours.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Visual design quality | UI-10 | Subjective aesthetic judgment | 1. Open dashboard in browser<br>2. Review color scheme, spacing, typography<br>3. Verify professional appearance |
| Mobile UX quality | UI-11 | Requires subjective touch interaction assessment | 1. Open dashboard on mobile device or Chrome DevTools mobile emulation<br>2. Test touch targets (min 44px)<br>3. Verify no horizontal scrolling<br>4. Test stacked layouts |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 180s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
