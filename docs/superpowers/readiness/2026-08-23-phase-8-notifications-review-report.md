# Phase 8 Final Review Report — Notification Center and Preferences

- Date: 2026-08-23
- Workspace: `D:\TalentHub`
- Branch / baseline HEAD: `feature/student` / `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- Decision: **APPROVED_PHASE_8**
- Next eligible phase: Phase 9 (not started)

## Delivered Production Scope

- Canonical database-backed notification repository and service with owner-scoped list/unread/read-one/read-all and per-student preferences.
- Strict notification type and event-key validation, exact internal deep-link allow-list, server-side unread filtering, deterministic `(userId,eventKey)` idempotency, and fail-closed schema/recipient handling.
- Learner endpoint with authentication, exact permissions, CSRF on mutations, exact input/query allow-lists, strict JSON booleans, pagination, ownership isolation, and normalized envelopes.
- Transactional producers for learner activity registration/cancellation/promotion, Teacher registration decisions, learner check-in, assessment submission, learner application submission/withdrawal, and Enterprise review. Each producer uses the domain transaction's PDO connection.
- Global learner header unread badge and Notification Center with server-truth pagination/filtering, safe DOM APIs, keyboard/focus handling, retry/error states, mark-one/read-all, and preference rollback. Email preference is stored only; Phase 8 does not send email.

## Database and Migration Review

- `20260821000600_create_notifications_and_preferences` was already applied before final review and was not edited.
- `20260821000610_validate_phase8_notification_contracts` was added as a forward-only, validation-only migration. It checks exact columns/defaults/order, engine/collation, indexes, foreign keys/actions, permission metadata, and all four role mappings.
- Fresh pre-apply backup: `C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\talenthub_local_pre_phase8_00610_20260823_091851.sql`
  - Size: 816,185 bytes
  - SHA-256: `da6a559853a043235a7222365fac6480499c9958d66178677b297e94124978a7`
- Final primary state: 58 base tables, 28 applied migrations, 0 pending, validation OK.
- No fake rows were inserted: `notifications=0`, `learner_notification_preferences=0`.
- `00610` changed only the migration registry. Permissions stayed at 103 rows and role mappings at 124 rows; their pre/post SHA-256 values were unchanged. The Phase 8 permission has exactly four canonical role mappings.

## Verification Evidence

- Disposable rehearsal: PASS using an explicitly pinned dump and SHA-256.
  - 74 integrity assertions
  - 12 forward-migration static assertions
  - 27 runtime endpoint assertions
  - 18 MySQL concurrency/FK/rollback assertions
  - Apply twice/no-op, conflict rejection, four-role contract, grant revocation, and schema cleanup all passed.
- Final focused PHP regression: 30/30 suites passed.
- JavaScript: all 11 files under `tests/*.js` passed.
- PHP lint: 507/507 PHP files passed.
- `git diff --check`: no whitespace errors (line-ending conversion warnings only).
- Migration connect/validate/status: MySQL 8.4.3, connection OK, validation OK, 28 applied, 0 pending.

During the broad regression pass, older assessment/activity/check-in fixtures were updated to include the now-required fail-closed notification contract. The assessment immutability test also exposed and now locks a production correction: new attempts may select only a published test and published version with a non-null publication timestamp.

Two specialized runtime suites (`learner_application_endpoint_runtime_test.php` and `learner_notifications_endpoint_runtime_test.php`) intentionally refuse to run without their exact disposable gate; the Phase 8 rehearsal ran the notification runtime suite on its validated disposable schema and passed all 27 assertions.

A supplemental attempt to run the legacy Holland career AI E2E on a fresh current clone stopped before any Phase 8 path because `LearnerAiPilotSeeder` still targets the removed Phase 5-era `checkins.qrTokenId` column. This is pre-existing staging-seeder compatibility debt, not a notification regression and not a Phase 8 exit dependency. Its disposable schema and grants were removed; `talenthub_app` was verified to retain only its normal `talenthub_local` grants.

## Security and Cross-Role Invariants

- Student identity and notification recipient are derived from the authenticated/domain owner, never accepted from client JSON.
- Cross-owner read/write is non-enumerating and denied.
- Missing notification tables, bad foreign keys, missing recipients, and unexpected integrity errors propagate and roll back the domain transaction.
- Only MySQL duplicate error 1062 (or the exact SQLite unique constraint) is treated as an idempotent duplicate.
- Teacher and Enterprise may produce notifications only through their owned domain transitions; they cannot mutate a learner inbox directly.
- School and Enterprise do not receive learner inbox write access through learner endpoints.
- `TALENTHUB_AI_VISIBLE_PERCENT=0` remains unchanged; Phase 8 does not alter AI visibility.
- No `.env`, applied migration, `.claude/`, or `.qwen/` content was intentionally modified by Phase 8 review.
- No commit, push, merge, reset, clean, checkout, or stash was performed. Phase 9 was not started.

## Final Decision

Phase 8 meets its exit criteria: notification ownership is enforced, counts and preferences survive refresh from server truth, producers are atomic/idempotent, no fake upstream notification exists, and the primary schema is validated with a recoverable backup. Status: **APPROVED_PHASE_8**.
