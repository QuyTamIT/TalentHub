# Codex CLI Execution Prompt — Phase 3 Safe Implementation and Database Apply

You are implementing **Student Portal Phase 3: Profile Ownership, Evidence, Consent, and Sharing** in `D:\TalentHub`.

This prompt is an execution authorization, not a request to create another plan. Execute the existing approved plan task by task, fix failures in scope, rehearse the database changes, and apply the approved Phase 3 changes to `talenthub_local` only after every safety gate below passes.

## Authorization carried by this prompt

The reviewer has issued:

```text
APPROVED_PHASE_2
APPROVED_PHASE_3_DCR_APPLY
```

`APPROVED_PHASE_3_DCR_APPLY` is **conditional authorization**. It becomes effective only after the disposable-clone rehearsal, verified backup/restore, schema compatibility checks, row-count/hash comparison, full four-role regressions, and all pre-apply gates below pass. Do not pause merely to ask for the same approval again. If any gate fails, do not apply to `talenthub_local`; report `BLOCKED` with exact evidence.

## Read completely before acting

Read these files in full, in this order:

1. `docs/superpowers/readiness/2026-08-22-phase-2-talent-passport-review-report.md`
2. `docs/superpowers/specs/2026-08-22-phase-2-talent-passport-design.md`
3. `docs/superpowers/plans/2026-08-22-phase-2-3-talent-passport-implementation.md`
4. `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`
5. `docs/superpowers/readiness/2026-08-21-student-portal-runtime-audit.md`
6. `docs/superpowers/readiness/2026-08-21-phase-0-1-conditional-2-review-report.md`
7. Current `git status`, `git diff`, migration registry, migration runner, relevant repositories/services/routes/tests, and live schema inventory.

The worktree already contains approved, uncommitted Phase 0–2 work. Preserve it. Never reset, clean, checkout, stash, discard, or overwrite unrelated changes.

## Runtime and fixed baseline

- Repository: `D:\TalentHub`
- Required branch: `feature/student`
- Reference HEAD: `bb167d97d2043f2ce71b78c1f62f9c9ae6d584d4`
- PHP: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`
- MySQL: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`
- MySQL dump: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe`
- Primary schema: `talenthub_local`
- Expected pre-Phase-3 migration state: 15 applied, 0 pending, validation OK
- Expected current table count: 45
- Phase 2 gate: `APPROVED_PHASE_2`
- AI visibility must remain: `TALENTHUB_AI_VISIBLE_PERCENT=0`

Use absolute executable paths. Resolve and print the actual working directory, branch, HEAD, PHP version, MySQL version, and schema name before implementation.

## Required working method

Use these skills/processes when available:

- `superpowers:executing-plans`
- `superpowers:test-driven-development`
- `superpowers:systematic-debugging` for every unexpected failure
- `superpowers:verification-before-completion` before every PASS claim

Execute **Tasks 7–15** from `docs/superpowers/plans/2026-08-22-phase-2-3-talent-passport-implementation.md`. Do not redesign the feature, skip RED runs, or broaden the roadmap.

For each task:

1. Inspect current implementation and consumers.
2. Write the smallest failing contract/security test.
3. Run it and record the expected RED evidence.
4. Implement the minimum production change.
5. Run focused GREEN tests.
6. Run affected Student/Teacher/School/Enterprise regressions.
7. Update plan checkboxes only after fresh evidence.

Do not commit, push, merge, rebase, reset, clean, or stash. Leave all changes available for reviewer inspection.

## Exact Phase 3 scope

Implement only:

1. Additive `student_profile_details` ownership fields.
2. Additive, expiring, revocable `student_profile_shares` with hashed tokens.
3. Safe expansion of `privacy_consents.scope` preserving all current AI scopes and rows.
4. Additive `certificates`, `projects`, and `project_members` schema.
5. Existing `PATCH /api/v1/students/me` extended with strict Student-owned field allow-list and one transaction.
6. Owner-scoped certificate read/create/update/delete API.
7. Consented profile-share create/revoke API and read-only shared-profile rendering.
8. Profile UI using server-confirmed persistence; no fake success or localStorage truth.
9. Readiness and full regression coverage.

Do not implement badges, levels, badge rules, notifications, applications, QR camera scanning, or another roadmap phase.

## Migration namespace and schema constraints

Create exactly these shared migrations and no substitute IDs:

```text
Database/migrations/20260821000100_create_student_passport_sharing.php
Database/migrations/20260821000200_create_student_certificates_and_projects.php
```

Before creating them, fail immediately if either version/file already exists unexpectedly or if a semantic-equivalent table exists with incompatible columns, indexes, checks, foreign keys, or ownership rules. Never silently rename a migration or adopt a legacy table.

Do not edit `Database/migrations/learner/001` through `004`. Do not create `badges`, `student_badges`, or `badge_rule_definitions`.

Use the exact Phase 3 schemas, column names, indexes, constraints, and recovery semantics from Tasks 8–9. `TalentPassportOptionalSchema` is the shared compatibility contract; migrations, readiness, and repository reads must agree with it.

Both migrations are forward-only after user rows exist. Do not use destructive automatic rollback. If post-apply recovery is required, preserve data and write an additive forward-recovery action.

## Application security requirements

Every mutation must enforce all of the following:

- authenticated Student session;
- Student role;
- exact permission;
- CSRF validation;
- strict input allow-list;
- UUID validation;
- current-Student ownership predicate in SQL;
- transaction boundary;
- normalized safe JSON error envelope;
- no PII, raw tokens, SQL, secrets, or stack traces in client errors/logs.

Student profile updates may change only:

```text
fullName, dateOfBirth, phone, location, bio, avatarUrl, headline
```

Reject email, role, account status, school/class ownership, skills verification, confirmed hours, scores, Teacher evaluations, badges, verification fields, and every unknown key with a stable validation error.

Student-created certificates start as `unverified`. Students may update/delete only their own `unverified` certificates. They can never set `verificationStatus`, `verifiedBy`, or `verifiedAt`.

Profile shares must:

- generate `bin2hex(random_bytes(32))`;
- persist only `hash('sha256', $rawToken)`;
- return the raw token/share URL once;
- never store raw tokens in DB, logs, HTML source after the immediate response, session, analytics, or localStorage;
- require a future expiry;
- support owner-scoped revoke;
- reject expired, revoked, invalid, or foreign tokens;
- render only the stored field allow-list;
- default to excluding email and phone;
- require explicit consent for any sensitive field;
- emit `Cache-Control: no-store` and escape all server-derived content.

Enterprise, School, and Teacher have no bypass to a Student passport share.

## Four-role non-regression gate

Build a consumer/ownership matrix before changing shared code:

- Student owns profile details, own unverified certificates, and own shares.
- Teacher-published assessments remain Teacher-owned and read-only to Student.
- School class/profile views remain school-scoped; Phase 3 must not widen them.
- Enterprise may see only the fields in a valid, unexpired, non-revoked share with the required consent; existing enterprise opportunity/application behavior must not change.

Before primary apply, prove:

1. No existing API route was duplicated.
2. No existing table/column was renamed or dropped.
3. Existing foreign-key delete/update behavior remains unchanged outside the new tables/constraint expansion.
4. Existing role permissions are not revoked or reassigned.
5. Teacher, School, and Enterprise read/write tests pass.
6. Four-role contract tests pass.
7. AI consent scopes and model visibility remain unchanged.
8. No Student can read/update/delete another Student’s profile, certificate, project membership, consent, or share.

### RBAC database rule

Audit the live permission catalog before implementing APIs. The current audit may show `certificate.manage_own` absent from the live DB even though it exists in the modified `RolePermissionSeeder.php`.

Do not run the broad RolePermissionSeeder blindly because it synchronizes mappings and can delete mappings not represented by its current constants.

Rehearse any RBAC change on the disposable clone and compute the exact before/after permission and role-mapping diff. A primary RBAC apply is allowed under this prompt only if all of these are true:

- zero role rows are deleted or renamed;
- zero existing permission rows are deleted or reassigned;
- zero existing role-permission mappings are removed;
- changes are idempotent and transactional;
- the only Phase 3-required additive grant is `certificate.manage_own` to `student`;
- any other planned Phase 1 deltas are reported separately and are not applied as a side effect of Phase 3.

If the existing seeder cannot satisfy that exact additive-only diff, do not run it on `talenthub_local`. Implement/rehearse the Phase 3 code and schema, report the RBAC blocker explicitly, and do not claim the certificate mutation flow is production-ready. Do not use ad-hoc manual SQL to bypass this gate.

## Database safety protocol

### Gate A — read-only primary baseline

Before any primary mutation:

1. Run connection, migration status, and migration validation.
2. Verify primary schema name is exactly `talenthub_local`.
3. Inventory all tables, columns, indexes, checks, and foreign keys relevant to Phase 3.
4. Capture row counts for all existing tables.
5. Capture stable, deterministic hashes for existing rows where possible; record the method and column ordering.
6. Capture exact role/permission mappings.
7. Capture SHA-256 for learner migrations 001–004 and all currently applied shared migration files.
8. Verify the five future tables are absent unless the current Phase 3 run already created them through the canonical runner.

Abort on drift, unexpected pending migration, schema collision, unsupported consent scope, orphan data, duplicate token hashes, invalid JSON, or an unexplained baseline mismatch.

### Gate B — verified backup and restore test

Before primary apply:

1. Create a timestamped SQL backup outside the repository under:

```text
C:\Users\CHI NGUYEN\AppData\Local\Temp\TalentHubBackups\
```

2. Include routines/triggers/events when supported and use options compatible with MySQL 8.4.
3. Verify the backup file exists, is non-empty, and compute SHA-256.
4. Restore that backup into an explicitly named disposable schema such as `talenthub_phase3_restorecheck_<timestamp>`.
5. Compare restored table counts and representative row hashes with the primary baseline.
6. Record the absolute backup path, byte size, checksum, and restore-check schema.

Never delete a schema unless its resolved name begins exactly with `talenthub_phase3_` and is not `talenthub_local`. Verify the resolved name immediately before cleanup.

### Gate C — disposable-clone rehearsal

Use an explicitly named clone such as `talenthub_phase3_rehearsal_<timestamp>`.

1. Restore the verified primary backup to the clone.
2. Point the canonical migration runner to the clone without editing `.env`.
3. Apply the two Phase 3 migrations once.
4. Run status and validation.
5. Run the migration command a second time to prove idempotency/no duplicate application.
6. Validate every new column, index, check, foreign key, status constraint, and consent scope.
7. Run all Phase 2 and Phase 3 PHP/Node/security/render/four-role tests against the clone.
8. Compare every pre-existing table’s row count and stable row hash to the pre-migration clone baseline.
9. Prove only intended schema metadata and explicitly approved additive permission rows/mappings changed.
10. Write:
   - `docs/superpowers/database-change-requests/2026-08-22-phase-3-passport-evidence-sharing.md`
   - `docs/superpowers/readiness/2026-08-22-phase-3-rehearsal-report.md`

Any unexpected data change, deleted mapping, failing test, collision, or non-idempotent result means `BLOCKED`; do not touch the primary schema.

### Gate D — immediate primary preflight

Immediately before primary apply, repeat the read-only connection/status/validation/schema-collision/consent-scope checks and compare them with Gate A. Confirm the verified backup still exists and its SHA-256 is unchanged.

Primary apply is allowed only when all gates A–D are green and the DCR explicitly says `GO_FOR_PRIMARY_APPLY`.

### Gate E — primary apply

Apply through the canonical migration runner exactly once. Do not run ad-hoc DDL and do not seed demo rows.

After apply, expected migration state is:

```text
17 applied, 0 pending, validation OK
```

If the state differs, stop. Do not drop new tables or restore over the live database automatically. Preserve evidence and use forward recovery.

### Gate F — post-apply proof

After primary apply:

1. Re-run all Phase 2/3 tests, all seven learner Node test files, permission tests, auth/security tests, Student/Teacher/School/Enterprise regressions, four-role contracts, AI/privacy tests, PHP lint, and `git diff --check`.
2. Recompute existing table row counts/hashes and role/permission mapping diff.
3. Prove old data is unchanged except explicitly documented, additive consent/RBAC metadata.
4. Verify cross-Student denial, share expiry/revoke, XSS escaping, raw-token absence, CSRF, ownership, and transaction rollback.
5. Verify Phase 2 database rendering still contains no demo leakage.
6. Verify learner migrations 001–004 hashes and `TALENTHUB_AI_VISIBLE_PERCENT=0`.

## Stop conditions

Stop and report `BLOCKED` without primary mutation if any of these occur:

- branch/HEAD/scope mismatch that cannot be explained by approved Phase 0–2 work;
- migration version or semantic schema collision;
- primary schema is not exactly `talenthub_local`;
- backup or restore verification fails;
- clone rehearsal is not fully green;
- an old row count/hash changes unexpectedly;
- an existing permission/mapping would be removed or reassigned;
- a Teacher/School/Enterprise regression fails;
- Student ownership, consent, CSRF, transaction, token, or privacy tests fail;
- AI visibility or protected migration hashes change;
- required evidence cannot be reproduced.

Do not weaken a test, constraint, permission, or ownership rule merely to pass a gate.

## Context-window handling

Do not redo completed tasks after compaction. At every atomic checkpoint update:

- plan checkboxes;
- current task and next exact step;
- files changed;
- RED/GREEN commands and exit codes;
- current DB/clone state;
- backup path/checksum if created;
- remaining gates and blockers.

If context becomes low, write the continuation state to the Phase 3 rehearsal/final report and continue from it. Do not use context pressure as a reason to skip validation or claim completion.

## Required final report

Create:

```text
docs/superpowers/readiness/2026-08-22-phase-3-profile-evidence-sharing-review-report.md
```

The terminal summary and report must include:

1. Decision: `PASS`, `FAIL`, or `BLOCKED`.
2. Tasks 7–15 completed/remaining.
3. Files created/modified.
4. RED and GREEN evidence with exact commands and exit codes.
5. Backup path, size, SHA-256, and restore verification.
6. Rehearsal schema, apply-twice/idempotency evidence, and cleanup status.
7. Primary pre/post migration state.
8. New schema/index/check/FK verification.
9. Pre-existing table row-count/hash comparison.
10. Exact RBAC before/after diff and confirmation that no mapping was removed.
11. Student ownership, CSRF, authorization, transaction, privacy, consent, expiry/revoke, token, and XSS results.
12. Teacher/School/Enterprise/four-role regression results.
13. AI visibility and protected migration hashes.
14. `git diff --check`, PHP lint, PHP test, and Node test totals.
15. Remaining risks or forward-recovery steps.
16. Whether any primary DB mutation occurred.

Do not claim Phase 3 PASS unless the fresh post-apply evidence is completely green. Do not commit, push, or merge. Stop after the report for reviewer inspection.
