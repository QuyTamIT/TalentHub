# Phase 11 Four-Role Release Rehearsal Design

## Decision

Use one deterministic hybrid release rehearsal that combines a verified physical backup/restore with production domain services and repositories on an allow-listed disposable MySQL schema. The rehearsal proves database migration integrity, complete learner workflow continuity, and four-role ownership boundaries without changing `talenthub_local` or starting Phase 12.

## Entry state

- Branch: `feature/student`.
- Phase 10: `APPROVED_PHASE_10`.
- Runtime database: MySQL 8.4.3, 61 base tables, 29 applied migrations, 0 pending, validation clean.
- Existing primary identities: 20 Students, 11 Teachers, 3 Schools, and 1 Enterprise member.
- Phase 11 therefore creates all test actors in the disposable clone, including a second Enterprise, and never inserts rehearsal identities into the primary database.
- `TALENTHUB_AI_VISIBLE_PERCENT=0`; Rule remains learner-visible and model execution remains shadow-only.

## Scope

### Included

- Produce and SHA-256-verify a `mysqldump` backup of `talenthub_local` before rehearsal.
- Restore the backup into a schema named exactly `talenthub_phase11_rehearsal_YYYYMMDDHHMMSS`.
- Validate the restored registry, run all main migrations twice, and require the second run to be a no-op with no drift.
- Add de-identified fixtures for two Students, two Teachers, two Schools, and two Enterprises only in the disposable schema.
- Exercise positive and cross-scope negative behavior for all four roles.
- Exercise the complete learner journey: profile/share → activity/approval/waitlist → QR/check-in/confirmed experience → assessment/published Teacher evaluation → opportunity/application/Enterprise review → notification → badge/statistics.
- Compare primary state before/after and verify restored baseline hashes, row counts, foreign keys, orphan counts, ownership assignments, and cleanup.
- Create an executable release checklist, authorization matrix, recovery procedure, and final review report.

### Excluded

- No new feature, schema migration, seed, backfill, or primary data mutation.
- No enabling learner-visible model output.
- No Phase 12 shadow evaluation or pilot decision.
- No push, merge, reset, clean, checkout, stash, or edits to `.env`, `.claude/`, `.qwen/`, applied migrations, or learner migrations `001`–`004`.
- No production deployment. Phase 11 produces evidence for a later human release decision.

## Architecture

### Rehearsal orchestrator

`tests/student_portal_four_role_e2e_mysql_test.php` is the single executable release gate. It owns environment validation, backup verification, disposable schema lifecycle, migration replay, fixture creation, workflow assertions, invariant checks, and cleanup. Helper functions remain inside the test unless a reusable production-neutral testing helper is demonstrably required.

The orchestrator refuses to run unless:

- `APP_ENV=test`;
- `TALENTHUB_DISPOSABLE_TEST_DB=1`;
- the PHP, MySQL, and mysqldump executables resolve to the pinned Laragon toolchain;
- the source database is exactly `talenthub_local`;
- the generated target matches `\Atalenthub_phase11_rehearsal_\d{14}\z` and is not the source database;
- the backup is non-empty and its computed SHA-256 is pinned for restore.

### Backup and restore

The test creates a dated backup beneath the existing OS temporary backup root, records its path, byte size, and SHA-256, and restores it through the MySQL client into the disposable schema. The backup contains schema, data, triggers, routines, events, and deterministic transaction options supported by MySQL 8.4.3. Secrets are passed through the existing local configuration contract and are never written into logs or documents.

The primary snapshot records table count, migration count, and deterministic per-table count/hash facts before rehearsal. The final primary snapshot must match exactly.

### Migration replay

After restore, `MigrationRunner` validates source/registry agreement. Because the source database already has all 29 migrations applied, both migration calls must return an empty list. Registry count and checksums remain unchanged. A validation failure, pending migration, or checksum drift fails the gate.

### Synthetic actor model

The disposable fixture set contains:

- Students `student_a` and `student_b`, each linked to a different school and user.
- Teachers `teacher_a` and `teacher_b`, each owning activities only in their own school.
- Schools `school_a` and `school_b`, each represented by an authorized school member.
- Enterprises `enterprise_a` and `enterprise_b`, each represented by a separate enterprise member and owning separate internship posts.

Fixture IDs, emails, request IDs, tokens, and event keys use a unique Phase 11 run prefix. No fixture includes real personal data. Creation order follows foreign-key ownership; cleanup is achieved by dropping the disposable schema rather than deleting primary rows.

## Workflow and authorization matrix

### Profile, consent, and sharing

- Student A updates only Student A self-declared profile fields.
- Student A creates an expiring allow-listed profile share and resolves it by raw token.
- Student B cannot list, revoke, or resolve Student A private ownership data beyond the intentionally shared projection.
- Revocation makes the token unusable.

### Activity, registration, QR, and experience

- Teacher A creates and publishes an owned activity with capacity one.
- Student A registers and Teacher A approves the registration.
- Student B registers only after an explicit capacity/waitlist setup and receives the canonical waitlisted state.
- Teacher B cannot update Teacher A's activity, registration, or QR session.
- Teacher A creates a bounded QR session; Student A check-in is idempotent, changes attendance consistently, and creates exactly one confirmed experience.
- Student B cannot use Student A's registration or receive Student A's experience.

### Assessment and Teacher evaluation

- Student A starts a catalog assessment, saves answers, submits it, and reads only the owned immutable result/history.
- Student B cannot read or mutate Student A's attempt.
- Teacher A creates or updates an evaluation for Student A only through an owned activity, then publishes it.
- Teacher B cannot grade Teacher A's activity; Student A sees the published evaluation while Student B does not.

### Opportunity and application

- Enterprise A owns and publishes a synthetic internship post.
- Student A grants application-sharing consent, submits an application with an immutable profile snapshot, and sees its ordered history.
- Enterprise B cannot list or review Enterprise A's application.
- Enterprise A reviews the application through the canonical transition contract.
- Student B cannot read or withdraw Student A's application.

### Notification, badge, and statistics

- Domain events create owner-addressed notifications once with stable event keys.
- Student A cannot read or mark Student B's notifications and vice versa.
- Confirmed facts are evaluated by the badge engine; replay creates no duplicate `(studentId,badgeId)` awards or notifications.
- Statistics and level views are scoped to the requested current student and contain confirmed facts only.
- School reads remain limited to its own school aggregate; Enterprise code has no check-in write path.

### RBAC assertions

Each of the eight actors is checked through `PermissionService` for role-appropriate positive permissions and at least one forbidden permission. Domain ownership tests then prove permissions alone cannot cross organization/student boundaries.

## Invariants

The rehearsal fails on any of the following:

- a non-allow-listed database name;
- any primary before/after difference;
- migration registry drift, a non-no-op second migration run, or pending migration;
- a changed hash/count for a restored pre-existing table outside explicitly named rehearsal fixture rows;
- foreign-key violations or orphan rows in Phase 2–9 domain tables;
- duplicate registration, check-in, experience, application, notification event, share, or badge award uniqueness facts;
- actor reassignment between schools, enterprises, teachers, or students;
- a successful cross-owner read/write that should be denied;
- a cleanup failure leaving the disposable schema or grants behind.

## Error handling and cleanup

The test wraps all schema creation and grant work in `try/finally`. On failure it preserves the original exception, revokes grants, drops only the exact allow-listed disposable schema, verifies both schema and grant absence, then rethrows. It never issues destructive SQL against `talenthub_local`.

The release checklist records recovery steps:

1. stop application writes;
2. verify the backup SHA-256;
3. restore into a new recovery schema;
4. validate migration registry and row/hash invariants;
5. switch configuration only under separate human approval;
6. retain the failed schema for forensic review until explicitly released.

## Verification strategy

1. Run the Phase 11 E2E test with explicit disposable gates and capture its JSON evidence.
2. Run all PHP tests that are safe for the current environment plus every disposable MySQL suite required by the roadmap.
3. Run all learner Node suites.
4. Lint PHP across application, source, database, bin, and tests.
5. Run migration validate/status, `git diff --check`, protected-scope/hash checks, secret scan, and Phase 11 readiness.
6. Verify no `talenthub_phase11_*` schema or grant remains.
7. Perform an independent code/requirements review before marking the tracker.

## Documentation outputs

- `docs/superpowers/readiness/student-portal-release-checklist.md`
- `docs/superpowers/readiness/student-portal-authorization-matrix.md`
- `docs/superpowers/readiness/2026-08-23-phase-11-four-role-release-rehearsal-report.md`
- deployment/recovery documentation only after the executable gate passes.

## Exit gate

Phase 11 can be marked `APPROVED_PHASE_11` only when the backup is verified, restore and double migration replay succeed, the complete four-role workflow and negative matrix pass, all regression and security gates are green, primary state is unchanged, cleanup is proven, and a human release review approves the evidence. Otherwise the phase remains `GO_FOR_REVIEW`, `NOT_READY`, or `BLOCKED` with exact evidence. Phase 12 stays untouched.

