---
phase: 01
slug: foundation
status: draft
nyquist_compliant: true
wave_0_complete: true
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
| **Quick run command** | `docker compose exec app php artisan test --parallel` (requires containers) |
| **Full suite command** | `docker compose exec app php artisan test` (requires containers) |
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
| 01-03-W0 | 03 | 0 | INFRA-01-08 | test_infra | `ls -la tests/Infrastructure/DockerComposeTest.php tests/Integration/NginxProxyTest.php tests/Integration/EnvironmentConfigTest.php && grep -q 'class DockerComposeTest' tests/Infrastructure/DockerComposeTest.php && grep -q 'class NginxProxyTest' tests/Integration/NginxProxyTest.php && grep -q 'class EnvironmentConfigTest' tests/Integration/EnvironmentConfigTest.php && echo "All test stubs created with valid class structure"` | ✅ tests/Infrastructure/DockerComposeTest.php | ⬜ pending |
| 01-01-01 | 01 | 1 | INFRA-01 | container | `docker compose config > /dev/null` | ✅ tests/Infrastructure/DockerComposeTest.php | ⬜ pending |
| 01-01-02 | 01 | 1 | INFRA-02 | container | `docker compose build app && docker compose ps nginx` | ✅ tests/Infrastructure/DockerComposeTest.php | ⬜ pending |
| 01-01-03 | 01 | 1 | INFRA-03 | container | `docker compose ps mysql` | ✅ tests/Infrastructure/DockerComposeTest.php | ⬜ pending |
| 01-01-04 | 01 | 1 | INFRA-04 | integration | `docker compose exec app php -v` | ✅ tests/Infrastructure/DockerComposeTest.php | ⬜ pending |
| 01-01-05 | 01 | 1 | INFRA-05 | container | `docker compose ps elasticsearch` | ✅ tests/Infrastructure/DockerComposeTest.php | ⬜ pending |
| 01-01-06 | 01 | 1 | INFRA-06 | container | `docker compose ps redis` | ✅ tests/Infrastructure/DockerComposeTest.php | ⬜ pending |
| 01-01-07 | 01 | 1 | INFRA-07 | integration | `docker compose exec nginx curl -s http://app:9000 | head -1` | ✅ tests/Integration/NginxProxyTest.php | ⬜ pending |
| 01-02-01 | 02 | 1 | INFRA-08 | integration | `ls -la .env .env.docker 2>/dev/null` | ✅ tests/Integration/EnvironmentConfigTest.php | ⬜ pending |
| 01-02-02 | 02 | 1 | INFRA-08 | integration | `make -n up && make -n down` | ✅ tests/Integration/EnvironmentConfigTest.php | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Infrastructure/DockerComposeTest.php` — container health checks for INFRA-01 through INFRA-06
- [x] `tests/Integration/NginxProxyTest.php` — Nginx → PHP-FPM proxy test for INFRA-07
- [x] `tests/Integration/EnvironmentConfigTest.php` — .env file validation for INFRA-08
- [x] `phpunit.xml` — exists (Laravel default)
- [x] **Wave 0 verification uses file existence checks** (does not require running containers)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Browser access to application | INFRA-07 | Requires visual verification | Open http://localhost in browser after `make up`, confirm Laravel welcome page loads |
| Developer ergonomics | INFRA-01 | Subjective UX assessment | Run `make up`, `make logs`, `make down` — confirm commands work as expected |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter
- [x] Plan split into 3 focused plans (01-01, 01-02, 01-03)
- [x] **Wave 0 verification uses file existence checks** (Nyquist compliant: no circular dependency on infrastructure)

**Approval:** pending
