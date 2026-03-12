---
phase: 01
slug: foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-13
---

# Phase 01 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.x (Laravel 11 default) |
| **Config file** | phpunit.xml |
| **Quick run command** | `docker compose exec app php artisan test --parallel` |
| **Full suite command** | `docker compose exec app php artisan test` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `docker compose exec app php artisan test --parallel`
- **After every plan wave:** Run `docker compose exec app php artisan test`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 01-01-01 | 01 | 1 | INFRA-01 | container | `docker compose ps` | ❌ W0 | ⬜ pending |
| 01-01-02 | 01 | 1 | INFRA-02 | container | `docker compose ps nginx` | ❌ W0 | ⬜ pending |
| 01-01-03 | 01 | 1 | INFRA-03 | container | `docker compose ps mysql` | ❌ W0 | ⬜ pending |
| 01-01-04 | 01 | 1 | INFRA-04 | integration | `docker compose exec app php -v` | ❌ W0 | ⬜ pending |
| 01-01-05 | 01 | 1 | INFRA-05 | container | `docker compose ps elasticsearch` | ❌ W0 | ⬜ pending |
| 01-01-06 | 01 | 1 | INFRA-06 | container | `docker compose ps redis` | ❌ W0 | ⬜ pending |
| 01-01-07 | 01 | 1 | INFRA-07 | integration | `docker compose exec nginx curl -s http://app:9000 | head -1` | ❌ W0 | ⬜ pending |
| 01-01-08 | 01 | 1 | INFRA-08 | integration | `ls -la .env .env.docker 2>/dev/null` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Infrastructure/DockerComposeTest.php` — container health checks for INFRA-01 through INFRA-06
- [ ] `tests/Integration/NginxProxyTest.php` — Nginx → PHP-FPM proxy test for INFRA-07
- [ ] `tests/Integration/EnvironmentConfigTest.php` — .env file validation for INFRA-08
- [ ] `phpunit.xml` — exists (Laravel default)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Browser access to application | INFRA-07 | Requires visual verification | Open http://localhost in browser after `make up`, confirm Laravel welcome page loads |
| Developer ergonomics | INFRA-01 | Subjective UX assessment | Run `make up`, `make logs`, `make down` — confirm commands work as expected |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
