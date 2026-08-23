# Phase 8 Notifications and Preferences Implementation Plan

- **Date:** 2026-08-23
- **Author:** Antigravity
- **Target Schema:** `talenthub_local`
- **Status:** COMPLETE — APPROVED_PHASE_8

---

## 1. Review Units Order & Deliverables

### Review Unit A — Audit, Design, DCR, Failing Tests
1. Audit runtime facts: branch, HEAD, dirty worktree, 56 tables, 26 applied migrations, permissions.
2. Produce design, implementation plan, and DCR documents.
3. Write failing unit/integration/rehearsal tests:
   - `tests/learner_notifications_api_test.php`
   - `tests/notification_domain_producer_test.php`
   - `tests/phase8_rehearsal_integrity_test.php`
   - `tests/learner_notifications_ui_test.js`
4. Verify tests fail for the right reasons (RED).

### Review Unit B — Migration, Repository & Service Layer
1. Create migration `Database/migrations/20260821000600_create_notifications_and_preferences.php` and, because it was applied before final review, preserve its checksum and add validation-only migration `Database/migrations/20260821000610_validate_phase8_notification_contracts.php`.
2. Create:
   - `app/learner/data/Contracts/NotificationRepository.php`
   - `app/learner/data/Database/DatabaseNotificationRepository.php`
   - `app/learner/data/Service/NotificationService.php`
3. Update `app/learner/data/bootstrap.php` and `RepositoryFactory.php`.
4. Validate migrations with `bin/migrate.php validate`.

### Review Unit C — Learner Notifications API
1. Create `app/learner/api/v1/notifications.php`.
2. Implement RBAC, CSRF, owner isolation, pagination, mark read, mark all read, preference updates.
3. Verify all API tests pass.

### Review Unit D — Domain Event Producers & Atomicity
1. Wire notification writer into:
   - `DatabaseActivityCommandRepository` (register, cancel, promote)
   - `TeacherActivityRepository` (transitionRegistration: approve, reject)
   - `DatabaseCheckinRepository` (createConfirmed)
   - `DatabaseAssessmentWriteRepository` (submitAttempt)
   - `DatabaseApplicationCommandRepository` (submit, withdraw)
   - `InternshipRepository` (review)
2. Ensure all notification writes use caller's PDO connection and roll back on error.
3. Verify producer tests pass.

### Review Unit E — Learner UI & Server-Truth
1. Update `app/learner/includes/header.php` to fetch/display unread count and link to Notification Center.
2. Create `app/learner/notifications.php` (Notification Center page).
3. Create `assets/js/learner-notifications.js` (client-side state management, no `innerHTML`).
4. Update `app/learner/includes/sidebar.php` if required.
5. Run JS and UI render tests.

### Review Unit F — Disposable Rehearsal
1. Create fresh primary backup before rehearsal.
2. Run self-orchestrating `tests/phase8_rehearsal_integrity_test.php` from an explicitly pinned dump and SHA-256.
3. Verify apply-twice for `00600` and `00610`, table preservation hashes, exact RBAC delta, runtime auth/CSRF/rollback, duplicate-event concurrency, and a deliberately broken contract rejected by `00610` on the disposable schema.
4. Clean up rehearsal schema and database grants.
5. Write `docs/superpowers/readiness/2026-08-23-phase-8-rehearsal-report.md`.

### Review Unit G — Conditional Primary Apply
1. Create fresh primary backup #2.
2. Apply validation-only migration `20260821000610` to `talenthub_local` after the already-applied `00600` contract passes all gates.
3. Verify 28 applied migrations, 0 pending, 58 tables, 0 fake notification/preference rows, and unchanged permission/role-mapping hashes.
4. Re-run regression and final rehearsal.

### Review Unit H — Final Verification & Report
1. Run full PHP lint and JS test suites.
2. Run cross-role regressions.
3. Update Program Tracker.
4. Write `docs/superpowers/readiness/2026-08-23-phase-8-notifications-review-report.md`.
5. Emit `PHASE_8_GO_FOR_CODEX_REVIEW`.
