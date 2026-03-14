---
phase: 10
slug: cicd-testing
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-14
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.0.1 |
| **Config file** | phpunit.xml |
| **Quick run command** | `./vendor/bin/phpunit --testsuite=Unit` |
| **Full suite command** | `./vendor/bin/phpunit` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `./vendor/bin/phpunit --testsuite=Unit`
- **After every plan wave:** Run `./vendor/bin/phpunit --coverage-text --coverage-min=70`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 10-00-01 | 00 | 0 | CICD-04, CICD-05, CICD-06 | unit | `./vendor/bin/phpunit tests/Unit/DeployScriptTest.php` | ✅ | ⬜ pending |
| 10-00-02 | 00 | 0 | CICD-07 | integration | `./vendor/bin/phpunit tests/Feature/DeployScriptTest.php` | ✅ | ⬜ pending |
| 10-01-01 | 01 | 1 | CICD-01, CICD-02, TEST-04, TEST-05 | integration | `./vendor/bin/phpunit` | ✅ | ⬜ pending |
| 10-01-02 | 01 | 1 | CICD-01, CICD-02, TEST-04, TEST-05 | integration | `./vendor/bin/phpunit` | ✅ | ⬜ pending |
| 10-01-03 | 01 | 1 | CICD-01, CICD-02, TEST-04, TEST-05 | manual | N/A (checkpoint) | N/A | ⬜ pending |
| 10-02-01 | 02 | 2 | CICD-03, CICD-04, CICD-05, CICD-06, CICD-07 | unit | `./vendor/bin/phpunit tests/Unit/DeployScriptTest.php` | ✅ | ⬜ pending |
| 10-02-02 | 02 | 2 | CICD-03, CICD-04, CICD-05, CICD-06, CICD-07 | unit | `./vendor/bin/phpunit tests/Unit/DeployScriptTest.php` | ✅ | ⬜ pending |
| 10-02-03 | 02 | 2 | CICD-03, CICD-04, CICD-05, CICD-06, CICD-07 | unit | `./vendor/bin/phpunit tests/Unit/DeployScriptTest.php` | ✅ | ⬜ pending |
| 10-02-04 | 02 | 2 | CICD-03, CICD-04, CICD-05, CICD-06, CICD-07 | manual | N/A (checkpoint) | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Unit/DeployScriptTest.php` — stubs for CICD-04, CICD-05, CICD-06
- [x] `tests/Feature/DeployScriptTest.php` — stubs for CICD-07
- [x] Existing PHPUnit infrastructure covers other requirements

*Note: Project has 88 existing test files. Wave 0 only needs test stubs for new deployment script functionality.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| GitHub Actions workflow execution | CICD-01, CICD-02 | Requires GitHub repository | Push to test branch, verify Actions tab shows workflow run |
| SSH deployment to server | CICD-03 | Requires production server | After successful CI, verify deployment completes on server |
| 70% coverage threshold enforcement | TEST-04 | Verified in CI pipeline output | Check GitHub Actions run log for coverage percentage |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 45s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
