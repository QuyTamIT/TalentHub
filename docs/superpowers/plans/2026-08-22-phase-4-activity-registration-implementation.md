# Phase 4 Activity Registration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver database-backed Student registration/cancellation and Teacher approval/rejection with capacity-safe waitlist promotion and no regression across Teacher, School, Enterprise, assessment, or AI consumers.

**Architecture:** A forward-only shared migration expands the canonical registration lifecycle and adds optional per-activity policy. Student and Teacher commands use focused repositories with explicit transactions and row locks; browser state is server-authoritative outside explicit mock mode. Existing read models consume the same tables and keys.

**Tech Stack:** PHP 8.3, PDO MySQL 8.4/InnoDB, existing TalentHub router/session/RBAC/JSON contracts, vanilla JavaScript, Node test runner.

**Execution status (2026-08-22): `APPROVED_PHASE_4`.** All implementation, migration, rehearsal, concurrency, regression, cleanup, and independent-review gates passed. No commit was created, as required by the global constraints. Detailed evidence is in `docs/superpowers/readiness/2026-08-22-phase-4-activity-registration-review-report.md`.

## Global Constraints

- Work in `D:\TalentHub` on branch `feature/student`; preserve the current dirty worktree.
- Do not commit, push, merge, reset, clean, or stash during this execution.
- Do not edit `.env`, `.claude/`, `.qwen/`, or `Database/migrations/learner/001`–`004`.
- Do not edit applied migrations `20260821000100` through `20260821000210`.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- Use `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` and MySQL 8.4.3 absolute paths.
- Write each behavior test first, run it and observe the expected failure, then add minimum production code.
- Run database mutation tests only against validated disposable schema names. Back up and restore-test before applying the primary migration.
- Phase 4 creates no notification or badge tables; it writes existing `audit_logs` only.

All PowerShell command blocks that use `$php` first set:

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
```

---

### Task 1: Lock the migration contract

**Files:**
- Create: `tests/activity_registration_lifecycle_migration_test.php`
- Create: `Database/migrations/20260821000300_extend_activity_registration_lifecycle.php`
- Modify: `app/learner/data/Readiness/PhaseRequirements.php`
- Modify: `tests/learner_phase_requirements_test.php`
- Modify: `tests/student_portal_cross_role_contract_test.php`

**Interfaces:**
- Produces migration version `20260821000300`.
- Produces columns `cancelledAt`, `cancellationReason` and table `activity_registration_policies`.
- Produces canonical status `waitlisted` and Teacher permission `activity_registration.update_managed`.

- [ ] **Step 1: Write the static migration contract test**

Assert that the migration is forward-only, verifies the exact existing status CHECK and unique/FK contracts, preserves existing values, adds only `waitlisted`, backfills six legacy cancelled rows deterministically, creates the policy table, and inserts the managed Teacher permission without granting it to any other role.

- [ ] **Step 2: Run RED**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests/activity_registration_lifecycle_migration_test.php
```

Expected: FAIL because migration `20260821000300` does not exist.

- [ ] **Step 3: Implement the forward migration**

The migration contract is:

```php
preflight(): verify canonical tables/columns/indexes/FKs/CHECKs/status counts/orphans/UTC
up(): add cancellation columns; backfill cancelled rows; replace named status CHECK;
      add cancellation CHECK; create activity_registration_policies;
      insert deterministic permission and Teacher mapping
down(): no destructive action
```

Use `INSERT ... ON DUPLICATE KEY UPDATE` only for permission vocabulary/mapping. Do not delete or rewrite registration identity.

- [ ] **Step 4: Extend Phase 4 readiness**

Require the two new columns, policy table fields/index/FK, canonical unique registration key, and the new migration source while keeping Phase 5+ requirements untouched.

- [ ] **Step 5: Run GREEN and existing migration contracts**

```powershell
& $php tests/activity_registration_lifecycle_migration_test.php
& $php tests/learner_phase_requirements_test.php
& $php tests/student_portal_cross_role_contract_test.php
& $php bin/migrate.php validate
```

Expected: all pass; primary still reports one pending migration and no mutation.

---

### Task 2: Expand canonical read models

**Files:**
- Modify: `app/learner/data/Enums/Statuses.php`
- Modify: `app/learner/data/Database/DatabaseActivityRepository.php`
- Modify: `app/learner/data/ReadModel/ActivityReadModel.php`
- Modify: `app/learner/includes/activity-data.php`
- Modify: `src/Modules/Teacher/Repository/TeacherActivityRepository.php`
- Modify: `app/teacher/includes/activity-data.php`
- Test: `tests/learner_activities_data_test.php`
- Test: `tests/teacher_activity_management_test.php`

**Interfaces:**
- Registration rows expose `registered_at`, `updated_at`, `cancelled_at`, and `cancellation_reason`.
- Activity rows expose derived `registration_opens_at`, `registration_closes_at`, `cancellation_closes_at`, `approval_mode`, `participants`, and `can_register`.

- [ ] **Step 1: Write failing normalization/read tests**

Test `waitlisted` as canonical, policy-row values, no-policy defaults, occupied count limited to `approved|attended`, and Teacher list rendering of pending/waitlisted rows.

- [ ] **Step 2: Run RED**

```powershell
& $php tests/learner_activities_data_test.php
& $php tests/teacher_activity_management_test.php
```

Expected: failures for missing `waitlisted` normalization and policy fields.

- [ ] **Step 3: Implement read queries and normalization**

Use `LEFT JOIN activity_registration_policies` and correlated occupied counts. Default registration/cancellation close to `activities.startAt` and default approval mode to `automatic`; do not invent a policy row.

- [ ] **Step 4: Run GREEN plus School/AI consumers**

```powershell
& $php tests/learner_activities_data_test.php
& $php tests/learner_database_render_test.php
& $php tests/student_portal_cross_role_contract_test.php
& $php tests/learner_ai_sources_test.php
```

Expected: all pass.

---

### Task 3: Implement Student registration and cancellation commands

**Files:**
- Create: `app/learner/data/Contracts/ActivityCommandRepository.php`
- Create: `app/learner/data/Database/DatabaseActivityCommandRepository.php`
- Create: `app/learner/data/Service/ActivityRegistrationService.php`
- Create: `app/learner/api/v1/activity-registrations.php`
- Modify: `app/learner/api/LearnerApiContext.php`
- Test: `tests/learner_activity_registration_api_test.php`
- Test: `tests/learner_activity_registration_endpoint_runtime_test.php`

**Interfaces:**

```php
interface ActivityCommandRepository
{
    public function register(string $studentId, string $actorUserId, string $requestId, string $activityId, DateTimeImmutable $now): array;
    public function cancel(string $studentId, string $actorUserId, string $requestId, string $registrationId, ?string $reason, DateTimeImmutable $now): array;
}

final class ActivityRegistrationService
{
    public function register(string $studentId, string $actorUserId, string $requestId, array $input): array;
    public function cancel(string $studentId, string $actorUserId, string $requestId, array $input): array;
}
```

- [ ] **Step 1: Write failing service/API tests**

Cover allowed input only, UUID validation, session-derived Student, exact permissions, CSRF, closed/draft/completed activity, duplicate including cancelled/rejected, conflict, capacity, approval mode, cancellation deadline, cross-Student denial, terminal state, and safe error envelopes.

- [ ] **Step 2: Run RED**

```powershell
& $php tests/learner_activity_registration_api_test.php
& $php tests/learner_activity_registration_endpoint_runtime_test.php
```

Expected: FAIL because command classes and endpoint do not exist.

- [ ] **Step 3: Implement repository transactions**

Register lock order: activity → policy → existing own registration → overlapping active registrations → occupied count → insert registration → audit. Cancel lock order: owned registration → activity → policy → FIFO waitlist row → registration updates → audit. Always roll back on `Throwable` and map duplicate/stale state to `409`.

- [ ] **Step 4: Implement endpoint**

`POST` accepts exactly `{action, activityId}` for register or `{action, registrationId, reason}` for cancel. It requires `activity_registration.create_own` or `activity_registration.cancel_own`, CSRF, and no client Student/user/request identity fields.

- [ ] **Step 5: Run GREEN**

Run the two focused suites and existing API security suites. Expected: all pass.

---

### Task 4: Implement Teacher managed transitions

**Files:**
- Modify: `src/Modules/Teacher/Repository/TeacherActivityRepository.php`
- Modify: `src/Modules/Teacher/Service/TeacherActivityService.php`
- Modify: `src/Bootstrap/Application.php`
- Modify: `app/teacher/activities/index.php`
- Test: `tests/teacher_activity_registration_transition_test.php`
- Test: `tests/teacher_registration_route_test.php`

**Interfaces:**

```php
TeacherActivityService::transitionRegistration(
    string $teacherId,
    string $actorUserId,
    string $requestId,
    string $activityId,
    string $registrationId,
    string $expectedStatus,
    string $action
): array
```

- [ ] **Step 1: Write failing transition tests**

Cover only `pending -> approved|rejected`, exact expected status, Teacher activity ownership, capacity recount, stale requests, cross-Teacher denial, CSRF, exact permission, audit rollback, and no Student/School/Enterprise access.

- [ ] **Step 2: Run RED**

Expected: missing method/route failures.

- [ ] **Step 3: Implement transaction and shared route**

Wire repository/service dependencies in `Application::buildRouter()`. Add the exact PATCH route, input allow-list, Teacher role, CSRF, and permission checks. Final SQL repeats activity ownership, registration ID, activity ID, and expected status.

- [ ] **Step 4: Add Teacher page forms**

Pending rows receive approve/reject buttons with CSRF and expected status. The page calls the same service boundary; it must not bypass permission/ownership logic.

- [ ] **Step 5: Run GREEN and Teacher grading regressions**

Expected: transition/route/page, grading, QR, and Teacher authentication tests pass.

---

### Task 5: Convert Student UI to server-authoritative mutations

**Files:**
- Modify: `assets/js/learner-api.js`
- Modify: `assets/js/learner-activities.js`
- Modify: `app/learner/activity-detail.php`
- Modify: `app/learner/my-activities.php`
- Modify: `tests/learner_api_client_test.js`
- Modify: `tests/learner_activities_ui_test.js`
- Modify: `tests/learner_activities_data_test.php`

**Interfaces:**

```javascript
api.registerActivity(activityId)
api.cancelActivity(registrationId, reason)
createActivityController({source, apiClient, boot, renderMessage})
```

- [ ] **Step 1: Write failing UI/client tests**

Assert database mode calls the API, disables duplicate submissions, replaces boot registration state with the server response, shows normalized errors, and never writes registration truth to localStorage. Assert explicit mock mode remains local.

- [ ] **Step 2: Run RED**

```powershell
node --test tests/learner_api_client_test.js tests/learner_activities_ui_test.js
```

Expected: missing methods/controller failures.

- [ ] **Step 3: Implement client/controller and page boot**

Use `/app/learner/api/v1/activity-registrations.php`, existing CSRF boot data, text-safe rendering, `aria-live` state, and server-returned registrations. Do not enable Phase 5 check-in mutations.

- [ ] **Step 4: Run GREEN**

Expected: focused Node and PHP render tests pass.

---

### Task 6: Rehearse migration and real MySQL concurrency

**Files:**
- Create: `tests/learner_activity_registration_mysql_test.php`
- Create: `tests/helpers/phase4_registration_worker.php`
- Create: `docs/superpowers/database-change-requests/2026-08-22-phase-4-activity-registration.md`
- Create: `docs/superpowers/readiness/2026-08-22-phase-4-rehearsal-report.md`

**Interfaces:**
- Tests require `DB_DATABASE` matching `talenthub_phase4_(rehearsal|test)_[0-9]{14}`.
- Worker accepts only environment-provided validated schema and fixture IDs.

- [ ] **Step 1: Create a pre-Phase-4 backup of primary**

Use `mysqldump --single-transaction --routines --triggers --no-tablespaces --set-gtid-purged=OFF`; record size and SHA-256.

- [ ] **Step 2: Restore-test the backup**

Restore into a validated disposable schema, confirm 50 current tables and 21 migration rows, then remove the clone.

- [ ] **Step 3: Rehearse migration twice**

Apply `00300`, verify 40 existing registrations and exact statuses remain, verify six cancelled backfills, permission mapping, policy schema, zero pending/drift, and second-run idempotency.

- [ ] **Step 4: Run MySQL behavior matrix**

Cover automatic/pending/waitlisted outcomes, FIFO promotion, deadlines, duplicates, schedule overlap, rollback, ownership, Teacher transitions, and assessment FK preservation.

- [ ] **Step 5: Run two-connection capacity race**

Connection A locks the activity and commits the last approved seat. The worker on connection B must wait, resume, recount, and persist `waitlisted`; assert approved+attended equals capacity and never exceeds it.

- [ ] **Step 6: Remove every disposable schema**

Query `information_schema.schemata` and require zero `talenthub_phase4_%` schemas before proceeding.

---

### Task 7: Apply the primary migration safely

**Files:**
- Modify: `docs/superpowers/database-change-requests/2026-08-22-phase-4-activity-registration.md`

- [ ] **Step 1: Gate primary apply**

Require backup restore PASS, rehearsal PASS, concurrency PASS, exactly one pending migration `20260821000300`, protected hashes unchanged, and user authorization already granted for safe Phase 4 migration apply.

- [ ] **Step 2: Apply through canonical runner**

```powershell
& $php bin/migrate.php migrate
& $php bin/migrate.php status
& $php bin/migrate.php validate
```

Expected: `00300` applied, 22 applied, 0 pending, validation OK.

- [ ] **Step 3: Verify primary invariants**

Require 40 original rows, exact pre-existing status counts, no orphan, assessment FK valid, six cancellation backfills, Teacher-only managed permission, empty policy table unless explicitly fixture-owned, and no Phase 5+ table.

---

### Task 8: Full verification, independent review, and report

**Files:**
- Create: `docs/superpowers/readiness/2026-08-22-phase-4-activity-registration-review-report.md`
- Modify: `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`

- [ ] **Step 1: Run focused and broad PHP suites**

Include all new Phase 4 tests plus Phase 2/3, Teacher, School, QR, assessment, recommendation, AI-shadow, security, and migration tests. Report exact pass/fail counts.

- [ ] **Step 2: Run all learner Node tests and PHP lint**

Expected: zero failures.

- [ ] **Step 3: Run safety checks**

Require `git diff --check`, zero high-confidence secret hits, `.env` untracked/unchanged, protected hashes unchanged, AI visibility `0`, primary migration validation OK, and zero disposable schemas.

- [ ] **Step 4: Request independent review**

Reviewer checks transaction locking/order, capacity math, FIFO, ownership, CSRF/RBAC, migration compatibility, audit atomicity, UI server authority, and four-role regressions. Fix every Critical/Important issue with a new RED/GREEN cycle.

- [ ] **Step 5: Mark Phase 4 complete only with evidence**

Update the roadmap checkboxes and report `APPROVED_PHASE_4`; leave the commit checkbox open and do not start Phase 5.
