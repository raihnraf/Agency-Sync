---
phase: 12
slug: deep-dive-audit-logs
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-15
---

# Phase 12 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

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
| 12-01-01 | 01 | 1 | AUDIT-01 | feature | `php artisan test --filter SyncLogDetailsTest` | ✅ W0 | ⬜ pending |
| 12-01-02 | 01 | 1 | AUDIT-02 | unit | `php artisan test --filter ErrorPayloadTest` | ✅ W0 | ⬜ pending |
| 12-01-03 | 01 | 1 | AUDIT-03 | feature | `php artisan test --filter ModalDisplayTest` | ✅ W0 | ⬜ pending |
| 12-02-01 | 02 | 2 | AUDIT-04 | feature | `php artisan test --filter StackTraceCaptureTest` | ✅ W0 | ⬜ pending |
| 12-02-02 | 02 | 2 | AUDIT-05 | feature | `php artisan test --filter SuccessSyncDetailsTest` | ✅ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/SyncLogDetailsTest.php` — stubs for AUDIT-01
- [ ] `tests/Unit/ErrorPayloadTest.php` — stubs for AUDIT-02
- [ ] `tests/Feature/ModalDisplayTest.php` — stubs for AUDIT-03
- [ ] `tests/Feature/StackTraceCaptureTest.php` — stubs for AUDIT-04
- [ ] `tests/Feature/SuccessSyncDetailsTest.php` — stubs for AUDIT-05
- [ ] `tests/Pest.php` — shared fixtures

*Existing infrastructure covers framework setup. Wave 0 adds test stubs for new audit log functionality.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| JSON syntax highlighting in modal | AUDIT-03 | Visual rendering requires human verification | 1. Run sync that fails with API error<br>2. Click "View Details"<br>3. Verify JSON is highlighted with color<br>4. Check nested objects are properly indented |
| Modal accessibility (keyboard nav) | AUDIT-03 | Accessibility testing requires human interaction | 1. Open error details modal<br>2. Test Tab key navigation<br>3. Test Escape key closes modal<br>4. Verify focus trap behavior |
| Stack trace readability | AUDIT-04 | Visual judgment of formatting | 1. Trigger internal error in sync<br>2. View error details<br>3. Verify stack traces are readable<br>4. Check file paths are clickable (if implemented) |

*Automated tests verify data structure and presence. Manual verifications ensure visual quality and accessibility.*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 20s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
