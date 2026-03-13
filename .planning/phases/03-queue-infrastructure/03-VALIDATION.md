---
phase: 3
slug: tenant-management
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x (Laravel 11 default) |
| **Config file** | phpunit.xml |
| **Quick run command** | `docker compose exec app php artisan test --parallel` |
| **Full suite command** | `docker compose exec app vendor/bin/phpunit` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `docker compose exec app php artisan test --filter=TenantTest`
- **After every plan wave:** Run `docker compose exec app vendor/bin/phpunit`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 01 | 1 | TENANT-01 | feature | `php artisan test --filter=CreateTenantTest` | ❌ W0 | ⬜ pending |
| 03-01-02 | 01 | 1 | TENANT-02 | feature | `php artisan test --filter=ListTenantsTest` | ❌ W0 | ⬜ pending |
| 03-01-03 | 01 | 1 | TENANT-05 | unit | `php artisan test --filter=EncryptedCredentialsTest` | ❌ W0 | ⬜ pending |
| 03-02-01 | 02 | 1 | TENANT-03 | feature | `php artisan test --filter=UpdateTenantTest` | ❌ W0 | ⬜ pending |
| 03-02-02 | 02 | 1 | TENANT-04 | feature | `php artisan test --filter=DeleteTenantTest` | ❌ W0 | ⬜ pending |
| 03-03-01 | 03 | 2 | TENANT-06, TENANT-07 | unit | `php artisan test --filter=TenantScopeTest` | ❌ W0 | ⬜ pending |
| 03-03-02 | 03 | 2 | TENANT-06, TENANT-07 | unit | `php artisan test --filter=TenantMiddlewareTest` | ❌ W0 | ⬜ pending |
| 03-04-01 | 04 | 2 | TEST-01, TEST-02 | unit+feature | `php artisan test --filter=TenantIsolationTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/TenantScopeTest.php` — stubs for TENANT-06, TENANT-07
- [ ] `tests/Unit/TenantMiddlewareTest.php` — stubs for middleware tests
- [ ] `tests/Feature/Tenant/TenantCrudTest.php` — stubs for TENANT-01 through TENANT-04
- [ ] `tests/Unit/EncryptedCredentialsTest.php` — stubs for TENANT-05
- [ ] Existing PHPUnit infrastructure covers all phase requirements

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Credential validation with real Shopify/Shopware APIs | TENANT-05 | Requires live API keys, external services | Use mock responses in tests, validate manually with real keys in Phase 6 |
| Multi-user concurrent tenant access | TENANT-02, TENANT-07 | Requires multiple authenticated sessions | Test manually with two browser sessions or API clients |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending

---

*Validation strategy created: 2026-03-13*
