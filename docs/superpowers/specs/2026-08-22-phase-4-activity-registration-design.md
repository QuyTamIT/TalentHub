# Phase 4 Activity Registration Design

**Date:** 2026-08-22
**Status:** Approved through the existing Phase 4 roadmap and the user's instruction to implement it
**Scope:** Student registration/cancellation, Teacher approval/rejection, capacity, waitlist, and cross-role compatibility

## Goals

Phase 4 makes activity registration server-authoritative. A Student can register or cancel through authenticated APIs; a Teacher can approve or reject only registrations for activities they own; capacity cannot be exceeded; a released approved seat promotes exactly one earliest waitlisted registration. Refreshing or changing devices returns the same database state.

Phase 4 does not implement QR check-in (Phase 5), application lifecycle (Phase 7), Notification Center storage (Phase 8), badges (Phase 9), or visible model AI. `TALENTHUB_AI_VISIBLE_PERCENT` remains `0`.

## Current Runtime Facts

- `activity_registrations` has 40 rows: 8 approved, 20 attended, 6 cancelled, and 6 pending.
- The unique key `uq_activity_registrations_activity_student(activityId, studentId)` is canonical and remains unchanged.
- Teacher assessments reference `(activityId, studentId)`, so registration identity and the composite key cannot be rebuilt or duplicated.
- Existing status CHECK accepts `pending`, `approved`, `rejected`, `cancelled`, and `attended`; Phase 4 adds only `waitlisted`.
- Student create/read/cancel permissions exist in the live database. `activity_registration.update_managed` exists in the approved permission vocabulary but is absent from the live database and must be added safely for Teacher only.
- Notification tables are absent by roadmap. Phase 4 records audit rows in the same transaction but does not create or fake notifications.

## Schema Design

Create forward migration `20260821000300_extend_activity_registration_lifecycle.php`.

The migration:

1. Preflights exact tables, columns, indexes, foreign keys, status counts, CHECK clauses, table engine/collation, and orphan invariants.
2. Adds `activity_registrations.cancelledAt DATETIME(6) NULL` and `cancellationReason VARCHAR(500) NULL`.
3. Backfills existing cancelled rows with `cancelledAt = updatedAt` and the deterministic reason `legacy_migration`; non-cancelled rows remain null.
4. Replaces the named status CHECK with the same five values plus `waitlisted`.
5. Adds a cancellation consistency CHECK: cancelled rows require `cancelledAt`; non-cancelled rows require it to be null.
6. Creates `activity_registration_policies` with one optional row per activity: `activityId` primary/FK, registration open/close timestamps, cancellation close timestamp, `approvalMode` (`automatic|teacher_review`), and timestamps.
7. Adds `activity_registration.update_managed` and maps it only to Teacher using deterministic permission IDs and idempotent inserts.

When no policy row exists, the service derives: registration opens when status becomes `published`, closes at `activities.startAt`, cancellation closes at `activities.startAt`, and approval mode is `automatic`.

The migration is forward-only. Recovery uses a verified backup and a new corrective migration; it never drops application tables or registration rows.

## Student Command Boundary

`ActivityCommandRepository` owns transaction and locking primitives. `ActivityRegistrationService` owns validation and transition decisions. `/app/learner/api/v1/activity-registrations.php` resolves the Student exclusively from the authenticated session and accepts no client Student ID.

### Register

The service starts one transaction, locks the activity, resolves the optional policy, verifies `published` status and the registration window, rejects an existing `(activityId, studentId)` row with `409`, locks active registrations needed for schedule-conflict detection, counts `approved|attended` under the activity lock, and writes exactly one status:

- `waitlisted` when occupied seats are at capacity;
- `pending` when a seat exists and approval mode is `teacher_review`;
- `approved` when a seat exists and approval mode is `automatic`.

Active schedule-conflict states are `pending`, `approved`, `waitlisted`, and `attended`. Any validation or SQL error rolls back. Duplicate/stale/capacity conflicts use `409`; invalid input or closed windows use `422`; invisible resources use `404`.

### Cancel

Cancellation locks the owned registration and activity. Only `pending`, `approved`, or `waitlisted` can become `cancelled`. The reason is trimmed, optional, and limited to 500 characters; the stored fallback is `student_cancelled`.

If an approved seat is released before the cancellation deadline, the repository locks the earliest waitlisted row ordered by `registeredAt,id`. It promotes that row to `approved` for automatic policy or `pending` for teacher review. The cancellation, optional promotion, and audit rows commit atomically.

The canonical unique key means a cancelled/rejected registration cannot be recreated in Phase 4. The API returns `409 REGISTRATION_EXISTS`, and the UI continues to display the terminal server state.

## Teacher Command Boundary

Add a shared route:

`PATCH /api/v1/teachers/me/activities/{activityId}/registrations/{registrationId}`

It requires Teacher role, CSRF, and `activity_registration.update_managed`. The body is exactly `{ expectedStatus, action }`, where `action` is `approve` or `reject`.

The repository locks the Teacher-owned activity and target registration. Allowed transitions are `pending -> approved` and `pending -> rejected`. Approval recounts `approved|attended` under the activity lock and returns `409 CAPACITY_CONFLICT` when full. The final update repeats the expected-status and ownership predicates so stale requests cannot mutate data. Teacher transitions write an audit row in the same transaction.

## Read Models and UI

The Student activity repository includes policy-derived windows, approval mode, occupied count, and the new cancellation fields. `learner-api.js` remains the only browser transport. In database mode, register/cancel actions call the server and replace in-memory boot state with the response; localStorage is never authoritative. Explicit mock mode retains local demo behavior.

Teacher registration lists display waitlisted state and expose approve/reject actions only for pending rows. School consumers remain read-only and receive the expanded canonical status without broader access. Enterprise code is untouched.

## Audit and Security

- Every mutation requires authenticated role, exact permission, CSRF, ownership, prepared SQL, and one transaction.
- Audit rows use the authenticated user ID, request ID, action, registration entity, and non-sensitive JSON metadata.
- No raw CSRF/session value or other secret enters logs.
- Notification persistence is deferred until the canonical Phase 8 tables exist.
- No `.env`, `.claude/`, `.qwen/`, or learner migration `001`–`004` edit is allowed.

## Verification

Verification must include:

- Static migration contract and schema readiness tests.
- Disposable MySQL rehearsal applied twice with before/after row counts and hashes.
- MySQL service integration for automatic, teacher-review, full-capacity waitlist, FIFO promotion, deadlines, duplicate, cross-Student denial, Teacher ownership, stale expected status, and rollback.
- A two-connection test: connection A locks an activity and fills its last seat; connection B blocks, resumes after commit, and writes `waitlisted`, proving no overbooking.
- Runtime endpoint tests for authentication, permission, CSRF, input allow-list, and error envelopes.
- Student UI tests proving database mode uses the API and mock mode alone uses localStorage.
- Teacher, School, grading, assessment, Talent Passport, AI-shadow, PHP, Node, lint, migration validation, protected-file, secret, and `git diff --check` regressions.

## Exit Criteria

Phase 4 is complete only when registration state survives refresh/device changes, approved plus attended never exceeds capacity, FIFO promotion is deterministic, Student/Teacher ownership is enforced, existing 40 rows are preserved through migration, Teacher and School consumers remain compatible, the primary database has zero pending/drift, and independent review has no unresolved Critical or Important issue.
