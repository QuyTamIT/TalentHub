# Phase 11 Four-Role Release Rehearsal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce executable evidence that the complete Student Portal and its Teacher, School, and Enterprise interactions are release-ready on a verified disposable MySQL clone while leaving `talenthub_local` unchanged.

**Architecture:** One fail-closed PHP orchestrator creates and verifies a backup, restores it to an allow-listed Phase 11 schema, proves migration replay and readiness, creates de-identified four-role fixtures, drives the canonical domain services, validates positive/negative ownership, hashes invariants, and cleans up in `finally`. Readiness Phase 11 becomes the union of required Phase 1–10 schema contracts so the release gate cannot pass by checking only a migration registry.

**Tech Stack:** PHP 8.3.30, PDO MySQL, MySQL/mysqldump 8.4.3, existing TalentHub services/repositories, Node test runner, PowerShell orchestration, Git.

## Global Constraints

- Work only on `feature/student`; do not push or merge.
- Preserve `.env`, `.claude/`, `.qwen/`, all applied migrations, and learner migrations `001`–`004`.
- Do not add a migration, seed/backfill primary data, or mutate `talenthub_local`.
- Only create/drop schemas matching `\Atalenthub_phase11_rehearsal_\d{14}\z`; explicitly reject `talenthub_local`.
- Use de-identified fixtures only inside the disposable clone.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT=0`; do not begin Phase 12.
- Test-first for every production behavior change; capture the expected failing output before implementation.
- Commit each independently reviewable unit and stop for human release review after the final report.

---

### Task 1: Make Phase 11 readiness cover the complete portal schema

**Files:**
- Modify: `tests/learner_phase_requirements_test.php`
- Modify: `app/learner/data/Readiness/PhaseRequirements.php`

**Interfaces:**
- Consumes: Phase definitions 1–10 returned by `PhaseRequirements::forPhase(int): array`.
- Produces: Phase 11 definition containing the union of required tables, columns, indexes, optional groups, and foreign keys.

- [ ] **Step 1: Write the failing Phase 11 union assertions**

Replace the shared `[10, 11]` registry-only assertion with a Phase 10 exact assertion and Phase 11 release assertions:

```php
$phase10 = $requirements->forPhase(10);
phase_requirements_assert($phase10['tables'] === ['learner_forward_migrations'], 'phase 10 uses the forward-only registry');

$phase11 = $requirements->forPhase(11);
foreach (['users', 'student_profiles', 'activities', 'activity_registrations', 'checkins',
          'experience_logs', 'test_attempts', 'assessments', 'internship_posts',
          'internship_applications', 'notifications', 'badges', 'student_badges',
          'learner_forward_migrations'] as $table) {
    phase_requirements_assert(in_array($table, $phase11['tables'], true), "phase 11 requires {$table}");
}
phase_requirements_assert(
    in_array('uq_checkins_registration', $phase11['indexes']['checkins'], true)
        && in_array('uq_student_badges_award', $phase11['indexes']['student_badges'], true),
    'phase 11 carries release-critical uniqueness contracts',
);
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_phase_requirements_test.php
```

Expected: FAIL because Phase 11 currently contains only `learner_forward_migrations`.

- [ ] **Step 3: Merge definitions deterministically**

Build definitions 0–10 first, then assign Phase 11 using a private merger that deduplicates lists while preserving phase order:

```php
$this->requirements[11] = $this->mergeDefinitions(array_slice($this->requirements, 1, 10, true));
```

`mergeDefinitions()` must union `tables`, each table's `columns` and `indexes`, optional table groups, and foreign-key tuples keyed by `from|table|to`; it returns the same documented definition shape with `requires_database=true` and no new config key.

- [ ] **Step 4: Run focused readiness tests and verify GREEN**

Run the Phase requirements, shared readiness, and Phase 11 readiness commands. Expected: all exit `0`; Phase 11 reports `READY` against the 61-table primary schema.

- [ ] **Step 5: Commit readiness contract**

```powershell
git add app/learner/data/Readiness/PhaseRequirements.php tests/learner_phase_requirements_test.php
git commit -m "feat(readiness): require full portal schema for phase 11"
```

---

### Task 2: Lock the rehearsal safety contract before building the orchestrator

**Files:**
- Create: `tests/phase11_release_rehearsal_contract_test.php`
- Create: `tests/student_portal_four_role_e2e_mysql_test.php`

**Interfaces:**
- Consumes: `config/database.php`, `MigrationRunner`, pinned Laragon executables, and environment gates.
- Produces: executable Phase 11 test that prints one JSON evidence object followed by `student_portal_four_role_e2e_mysql_test: OK; cleanup verified`.

- [ ] **Step 1: Write a failing source contract**

The contract reads the not-yet-created orchestrator and asserts all of these literal safety facts:

```php
$assert(is_file($e2e), 'Phase 11 E2E test exists');
$source = (string) file_get_contents($e2e);
$assert(str_contains($source, "TALENTHUB_DISPOSABLE_TEST_DB"), 'explicit disposable gate');
$assert(str_contains($source, "talenthub_phase11_rehearsal_"), 'allow-listed prefix');
$assert(str_contains($source, "!== 'talenthub_local'"), 'primary destructive guard');
$assert(str_contains($source, 'mysqldump'), 'physical backup');
$assert(str_contains($source, "hash_file('sha256'"), 'backup digest verification');
$assert(str_contains($source, 'finally'), 'cleanup is unconditional');
$assert(str_contains($source, 'DROP DATABASE IF EXISTS'), 'disposable cleanup');
```

- [ ] **Step 2: Run contract and verify RED**

Expected: FAIL with `Phase 11 E2E test exists`.

- [ ] **Step 3: Add the fail-closed orchestrator shell**

The file must:

```php
if (Environment::appEnvironment() !== 'test' || getenv('TALENTHUB_DISPOSABLE_TEST_DB') !== '1') {
    fwrite(STDERR, "Phase 11 requires APP_ENV=test and TALENTHUB_DISPOSABLE_TEST_DB=1\n");
    exit(2);
}
$sourceDatabase = (string) $config['database'];
$assert($sourceDatabase === 'talenthub_local', 'source must be talenthub_local');
$targetDatabase = 'talenthub_phase11_rehearsal_' . gmdate('YmdHis');
$assert(preg_match('/\Atalenthub_phase11_rehearsal_\d{14}\z/', $targetDatabase) === 1, 'safe target name');
$assert($targetDatabase !== 'talenthub_local', 'target must not be primary');
```

Resolve `php.exe`, `mysql.exe`, and `mysqldump.exe`; create a backup under `sys_get_temp_dir()/TalentHubBackups`; assert non-zero size and exact `hash_file('sha256', $path)`; create/grant/restore target; verify restored table/migration counts; run `MigrationRunner::validate()` and `migrate()` twice, requiring both arrays empty.

The `finally` block revokes target grants, drops only the regex-validated target, checks `information_schema.schemata` and `mysql.db`, and rethrows the original failure.

- [ ] **Step 4: Run contract and guarded negative invocation**

Expected: contract PASS; invoking E2E without the gates exits `2` before backup/schema creation.

- [ ] **Step 5: Commit the safety shell**

```powershell
git add tests/phase11_release_rehearsal_contract_test.php tests/student_portal_four_role_e2e_mysql_test.php
git commit -m "test(release): guard phase 11 disposable rehearsal"
```

---

### Task 3: Add deterministic fixtures and four-role authorization proof

**Files:**
- Modify: `tests/student_portal_four_role_e2e_mysql_test.php`
- Create: `docs/superpowers/readiness/student-portal-authorization-matrix.md`

**Interfaces:**
- Consumes: restored target PDO, `PermissionService`, canonical roles and permissions.
- Produces: `createActors(PDO,string): array` returning two actors per role with user/profile/organization IDs; JSON evidence `authorization.positive` and `authorization.denied` counts.

- [ ] **Step 1: Add failing fixture/RBAC assertions to the E2E test**

Assert eight distinct active users, two distinct organization IDs for School and Enterprise, correct role codes, positive role permissions, forbidden cross-role permissions, and ownership denial for cross-school/cross-enterprise resource access.

- [ ] **Step 2: Run gated E2E and verify RED**

Expected: FAIL on missing `createActors`/actor evidence after backup/restore, followed by verified disposable cleanup.

- [ ] **Step 3: Implement de-identified actor creation**

Insert deterministic UUIDv5-style values derived from the unique run prefix into canonical `schools`, `enterprises`, `users`, `student_profiles`, `teacher_profiles`, `school_members`, and `enterprise_members`. Use existing canonical role IDs and existing class patterns. Emails use `phase11+<actor>@example.invalid`; passwords use an unusable local hash because service-level rehearsal does not authenticate over HTTP.

Use `PermissionService::require()` to assert each allowed permission. Use an `expectApiException(403, 'PERMISSION_DENIED', callable)` helper for forbidden permissions. Domain repository lookups must assert `404 RESOURCE_NOT_FOUND` or `403 PERMISSION_DENIED` for foreign owner IDs.

- [ ] **Step 4: Write the exact matrix document from executable assertions**

Document actor, permission, resource scope, expected positive result, expected negative actor, expected denial code, and E2E assertion label. Do not claim a case not present in the test.

- [ ] **Step 5: Run gated E2E and focused RBAC regressions**

Expected: actor/RBAC section passes and cleanup is verified even if later workflow sections are not yet implemented.

- [ ] **Step 6: Commit actor/matrix unit**

```powershell
git add tests/student_portal_four_role_e2e_mysql_test.php docs/superpowers/readiness/student-portal-authorization-matrix.md
git commit -m "test(release): prove four-role ownership boundaries"
```

---

### Task 4: Drive the complete Student Portal workflow on the clone

**Files:**
- Modify: `tests/student_portal_four_role_e2e_mysql_test.php`

**Interfaces:**
- Consumes: actor IDs from Task 3 and production services/repositories from Phases 2–9.
- Produces: JSON evidence counters for profile/share, activity/waitlist, QR/experience, assessment/evaluation, application/review, notification, badge/statistics, replay, and negative ownership cases.

- [ ] **Step 1: Add failing journey assertions in dependency order**

For each workflow stage assert the exact canonical persisted state and one corresponding cross-owner denial. The final expected evidence map is:

```php
[
  'profile_share' => true,
  'activity_registration' => ['approved' => 1, 'waitlisted' => 1],
  'checkin_experience' => ['checkins' => 1, 'confirmed_experiences' => 1, 'replay_duplicates' => 0],
  'assessment_evaluation' => ['submitted_results' => 1, 'published_evaluations' => 1],
  'application_review' => ['applications' => 1, 'final_status' => 'reviewing'],
  'notifications' => ['owner_visible' => true, 'cross_owner_visible' => false],
  'badges_statistics' => ['replay_awards' => 0, 'owner_scoped' => true],
]
```

- [ ] **Step 2: Run gated E2E and verify RED at the first missing workflow**

Expected: first unimplemented stage fails; `finally` removes schema and grants.

- [ ] **Step 3: Implement profile/share and activity lifecycle**

Use `StudentProfileService`, `ProfileSharingService`, `TeacherActivityService`, `DatabaseActivityCommandRepository`, and `ActivityRegistrationService`. Teacher A owns an activity with capacity one and manual approval. Student A becomes approved; Student B becomes waitlisted. Teacher B's update/transition attempts must fail.

- [ ] **Step 4: Implement QR/check-in/experience**

Use `TeacherQrSessionService`, `LearnerCheckinService`, and production repositories. Submit the raw QR token once, assert registration `attended`, check-in `confirmed`, exactly one confirmed experience, then replay and assert the idempotent response creates no second check-in/experience.

- [ ] **Step 5: Implement assessment and published evaluation**

Use the existing assessment catalog/version/questions, `LearnerAssessmentService`, and `TeacherGradingService`. Answer every required question with a valid value, submit Student A's attempt, deny Student B access, save and publish Teacher A's owned evaluation, and deny Teacher B grading.

- [ ] **Step 6: Implement opportunity/application/review**

Use `InternshipService` to create/publish Enterprise A's post; `ApplicationCommandService` to grant consent and submit Student A; assert immutable snapshot/history; deny Enterprise B review and Student B detail; transition Enterprise A application from `submitted` to `reviewing`.

- [ ] **Step 7: Implement notifications, badges, and statistics**

Use `NotificationService`, `BadgeAwardService`, `BadgeReadService`, and `StatisticsService`. Assert owner isolation, idempotent event keys, deterministic award replay, confirmed-only facts, and Student A/Student B views are not interchangeable.

- [ ] **Step 8: Run E2E until GREEN and commit**

Expected: JSON evidence contains every stage, primary before/after equality is true, and cleanup line is printed.

```powershell
git add tests/student_portal_four_role_e2e_mysql_test.php
git commit -m "test(release): exercise complete four-role portal journey"
```

---

### Task 5: Add database invariants, release checklist, and recovery runbook

**Files:**
- Modify: `tests/student_portal_four_role_e2e_mysql_test.php`
- Create: `docs/superpowers/readiness/student-portal-release-checklist.md`
- Modify: `docs/superpowers/readiness/student-production-foundation.md`

**Interfaces:**
- Consumes: baseline/primary snapshots and journey fixture prefix.
- Produces: JSON evidence for table hashes/counts, orphan checks, ownership assignments, cleanup, backup/recovery, and release checklist commands.

- [ ] **Step 1: Add failing invariant assertions**

Assert all pre-existing restored rows match the backup snapshot when Phase 11 fixture rows are excluded, all foreign-key checks report zero orphans, user/profile/member ownership remains one-to-one, and primary table/migration/count/hash snapshot is unchanged.

- [ ] **Step 2: Run and verify RED on missing invariant evidence**

- [ ] **Step 3: Implement deterministic snapshot and orphan checks**

Hash each table by ordered columns/primary key. Exclude only rows whose IDs/event keys/emails carry the unique Phase 11 prefix. Run `information_schema.referential_constraints`/`key_column_usage` driven orphan checks or an explicit allow-listed FK matrix for all Phase 2–9 tables; store each query/count in evidence.

- [ ] **Step 4: Write executable release and recovery documentation**

The checklist records prerequisites, exact backup/hash/restore commands without secrets, migration validation/twice-run expectations, E2E command, regression commands, cleanup verification, log privacy audit, approval signature fields, and the six recovery steps from the design. Update production foundation documentation only with the verified recovery procedure.

- [ ] **Step 5: Run E2E and documentation contract tests, then commit**

```powershell
git add tests/student_portal_four_role_e2e_mysql_test.php docs/superpowers/readiness/student-portal-release-checklist.md docs/superpowers/readiness/student-production-foundation.md
git commit -m "docs(release): add verified portal recovery gate"
```

---

### Task 6: Full verification, independent review, and Phase 11 report

**Files:**
- Create: `docs/superpowers/readiness/2026-08-23-phase-11-four-role-release-rehearsal-report.md`
- Modify: `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`
- Modify: `docs/superpowers/plans/2026-08-14-student-portal-completion-roadmap.md`

**Interfaces:**
- Consumes: all Phase 11 commits and fresh verification output.
- Produces: `GO_FOR_REVIEW` evidence first; `APPROVED_PHASE_11` only after independent review has no unresolved Critical/Important issue and human review confirms release evidence.

- [ ] **Step 1: Run the gated Phase 11 rehearsal fresh**

Set `APP_ENV=test`, `TALENTHUB_DISPOSABLE_TEST_DB=1`, and the source DB environment values for `talenthub_local`; run the E2E test and capture JSON, backup path/hash/size, MySQL version, table/migration counts, assertion counts, and cleanup evidence.

- [ ] **Step 2: Run full regression and static gates**

Run all 13 Node suites, safe PHP matrix, applicable disposable MySQL suites, PHP lint, migration validate/status, Phase 11 readiness, `git diff --check`, changed-file secret scan, protected hashes, and query for remaining `talenthub_phase11_%` schemas/grants.

- [ ] **Step 3: Request independent code/requirements review**

Provide the reviewer the Phase 11 base SHA, head SHA, approved spec, implementation plan, E2E evidence, and exact requirement checklist. Fix every valid Critical/Important finding with a reproducing test and rerun affected/full gates.

- [ ] **Step 4: Write report and update trackers**

Record exact commands, exit codes, counts, database/tool versions, backup/recovery facts, authorization matrix totals, primary equality, cleanup, known gaps, commits, and reviewer decision. Mark Task 15 checkboxes and tracker complete only when evidence supports `APPROVED_PHASE_11`; leave Phase 12 untouched.

- [ ] **Step 5: Final verification and documentation commit**

```powershell
git add docs/superpowers/readiness/2026-08-23-phase-11-four-role-release-rehearsal-report.md docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md docs/superpowers/plans/2026-08-14-student-portal-completion-roadmap.md
git commit -m "docs(release): record phase 11 rehearsal evidence"
```

- [ ] **Step 6: Stop before Phase 12**

Report branch/HEAD, commits, tests, backup, database invariants, protected paths, and Phase 11 decision. Do not push, merge, or change AI visibility.

