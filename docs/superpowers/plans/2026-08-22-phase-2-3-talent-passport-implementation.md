# Phase 2–3 Talent Passport Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a truthful database-backed Student Dashboard/Talent Passport in Phase 2, then add profile ownership, certificates/projects, consent, and expiring profile sharing in Phase 3 without breaking Teacher, School, Enterprise, AI, or the shared database.

**Architecture:** Phase 2 adds one learner-owned aggregate repository with explicit optional capabilities; missing future tables produce empty collections rather than blocking readiness or enabling mock fallback. Phase 3 uses two additive shared migrations, existing shared profile routes, learner API command services, hashed share tokens, and strict ownership/consent checks. A mandatory review checkpoint separates the phases.

**Tech Stack:** PHP 8.3.30, PDO, MySQL 8.4.3, existing TalentHub migration framework, vanilla PHP views, vanilla JavaScript, Node test runner.

## Global Constraints

- Work in `D:\TalentHub` on branch `feature/student`; preserve all pre-existing uncommitted work.
- PHP: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- MySQL CLI: `D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`.
- Follow `docs/superpowers/specs/2026-08-22-phase-2-talent-passport-design.md` and the relevant Phase 3 contracts in `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`.
- Use RED-GREEN-REFACTOR for every production behavior change and preserve the failing output in the phase report.
- Phase 2 performs no migration, seed, or DML against `talenthub_local`.
- Phase 3 creates schema only through versions `20260821000100` and `20260821000200`; never create tables with ad-hoc MySQL commands.
- Do not create `badges`, `student_badges`, or `badge_rule_definitions`; they remain Phase 9.
- Do not edit learner migrations `Database/migrations/learner/001` through `004`.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT` at `0`.
- Do not edit `.env`, `.claude/`, or `.qwen/`.
- Do not commit, push, merge, reset, clean, or discard user changes.
- Stop after Phase 2 for review. Phase 3 requires explicit `APPROVED_PHASE_2` authorization.
- Phase 3 primary database apply requires a second explicit `APPROVED_PHASE_3_DCR_APPLY` authorization after disposable-clone rehearsal and backup.

---

## Phase 2 — Dashboard and Talent Passport Reads

### Task 1: Correct Phase 2 readiness semantics

**Files:**
- Modify: `app/learner/data/Readiness/PhaseRequirements.php`
- Modify: `app/learner/data/Readiness/ReadinessChecker.php`
- Modify: `tests/learner_phase_requirements_test.php`
- Create: `tests/learner_phase_2_optional_capabilities_test.php`

**Interfaces:**
- Consumes: `PhaseRequirements::forPhase(int): array`, `SchemaInspector`.
- Produces: Phase definitions with `optional_table_groups`, and readiness diagnostics that do not treat absent Phase 3/9 groups as failures.

- [ ] **Step 1: Add a failing Phase 2 contract test**

Add assertions equivalent to:

```php
$phase2 = (new PhaseRequirements())->forPhase(2);
$assert(!in_array('certificates', $phase2['tables'], true), 'certificates are not a Phase 2 hard dependency');
$assert(!in_array('projects', $phase2['tables'], true), 'projects are not a Phase 2 hard dependency');
$assert(!in_array('badges', $phase2['tables'], true), 'badges are not a Phase 2 hard dependency');
$assert($phase2['optional_table_groups'] === [
    'certificates' => ['certificates'],
    'projects' => ['projects', 'project_members'],
    'badges' => ['badges', 'student_badges'],
], 'Phase 2 optional groups are explicit');
```

The required Phase 2 tables must be exactly the current facts needed by the aggregate:

```php
[
    'users', 'student_profiles', 'classes', 'schools',
    'skills', 'student_skills',
    'activities', 'activity_registrations', 'checkins', 'experience_logs',
    'talent_tests', 'test_attempts', 'test_results',
    'assessments', 'assessment_scores', 'assessment_criteria',
]
```

Lock the required column contract to the fields actually consumed by identity resolution, ownership filters, published-result filters, and aggregate rendering:

```php
[
    'users' => ['id', 'fullName', 'email', 'status'],
    'student_profiles' => ['id', 'userId', 'classId', 'studyStatus'],
    'classes' => ['id', 'schoolId', 'name', 'gradeLevel', 'academicYear', 'status'],
    'schools' => ['id', 'name', 'status'],
    'skills' => ['id', 'code', 'name', 'category', 'status'],
    'student_skills' => ['studentId', 'skillId', 'levelScore', 'sourceType', 'verificationStatus', 'verifiedAt'],
    'activities' => ['id', 'schoolId', 'createdByTeacherId', 'title', 'category', 'startAt', 'endAt', 'status'],
    'activity_registrations' => ['id', 'activityId', 'studentId', 'status', 'registeredAt'],
    'checkins' => ['id', 'registrationId', 'qrSessionId', 'status', 'checkedInAt', 'confirmedAt'],
    'experience_logs' => ['id', 'studentId', 'activityId', 'checkinId', 'hours', 'status', 'confirmedAt'],
    'talent_tests' => ['id', 'code', 'name', 'type', 'status'],
    'test_attempts' => ['id', 'testId', 'studentId', 'status', 'startedAt', 'submittedAt'],
    'test_results' => ['attemptId', 'resultCode', 'summary', 'dimensionScoresJson', 'scoringVersion', 'createdAt'],
    'assessments' => ['id', 'teacherId', 'studentId', 'activityId', 'overallScore', 'comment', 'status', 'publishedAt', 'version'],
    'assessment_scores' => ['assessmentId', 'criteriaId', 'score'],
    'assessment_criteria' => ['id', 'code', 'name', 'minScore', 'maxScore', 'displayOrder', 'status'],
]
```

Lock only the indexes needed to prove uniqueness and efficient Student isolation. Do not invent new indexes in Phase 2:

```php
[
    'student_profiles' => ['uq_student_profiles_user', 'idx_student_profiles_class_status'],
    'classes' => ['idx_classes_school_status'],
    'skills' => ['uq_skills_code', 'idx_skills_status_category'],
    'student_skills' => ['uq_student_skills_student_skill_source', 'idx_student_skills_student_verification'],
    'activities' => ['idx_activities_teacher_status', 'idx_activities_school_start'],
    'activity_registrations' => ['uq_activity_registrations_activity_student', 'idx_activity_registrations_student_status'],
    'checkins' => ['uq_checkins_registration', 'idx_checkins_qr_session'],
    'experience_logs' => ['uq_experience_logs_checkin', 'idx_experience_logs_student_status'],
    'test_attempts' => ['idx_test_attempts_student_status'],
    'test_results' => ['uq_test_results_attempt'],
    'assessments' => ['uq_assessments_teacher_student_activity', 'idx_assessments_student_status'],
    'assessment_scores' => ['uq_assessment_scores_assessment_criteria'],
    'assessment_criteria' => ['uq_assessment_criteria_code', 'idx_assessment_criteria_status_order'],
]
```

Before copying these maps into code, compare every name against the runtime inventory from Phase 0. If a current canonical name differs, treat that as a blocker and report the exact discrepancy; do not silently relax the contract or change the database in Phase 2.

- [ ] **Step 2: Run RED**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_phase_requirements_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_phase_2_optional_capabilities_test.php
```

Expected: at least one failure because `optional_table_groups` does not exist and Phase 2 still requires future tables.

- [ ] **Step 3: Implement explicit optional groups**

Extend the definition shape without changing phases that do not need optional capabilities:

```php
private function definition(
    bool $requiresDatabase,
    array $configKeys = [],
    array $tables = [],
    array $columns = [],
    array $indexes = [],
    array $optionalTableGroups = [],
): array {
    return [
        'requires_database' => $requiresDatabase,
        'config_keys' => $configKeys,
        'tables' => $tables,
        'columns' => $columns,
        'indexes' => $indexes,
        'optional_table_groups' => $optionalTableGroups,
    ];
}
```

`ReadinessChecker` must report optional capabilities as pass/informational diagnostics and must not add a failure when the whole group is absent. A partially present group is unavailable, never ready.

- [ ] **Step 4: Run GREEN and readiness regressions**

Run both focused tests plus:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_readiness_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_shared_readiness_test.php
```

Expected: all four scripts exit `0` and print their `OK` marker.

- [ ] **Step 5: Review checkpoint**

Record the exact required tables/columns/indexes and optional capability results. Do not commit.

### Task 2: Define the Talent Passport contract and read model

**Files:**
- Create: `app/learner/data/Contracts/TalentPassportRepository.php`
- Create: `app/learner/data/Mock/MockTalentPassportRepository.php`
- Create: `app/learner/data/ReadModel/TalentPassportReadModel.php`
- Modify: `app/learner/data/bootstrap.php`
- Create: `tests/learner_talent_passport_contract_test.php`

**Interfaces:**
- Consumes: normalized snake-case repository records.
- Produces: `TalentPassportRepository::aggregateForStudent(string $studentId): array` and `TalentPassportReadModel::fromAggregate(array $aggregate): array`.

- [ ] **Step 1: Write failing contract/read-model tests**

Test these exact stable keys and truth rules:

```php
$view = TalentPassportReadModel::fromAggregate([
    'student' => ['id' => $studentId, 'full_name' => 'Database Learner'],
    'skills' => [],
    'experience' => ['confirmed_hours' => 0, 'confirmed_entries' => []],
    'assessment_results' => [],
    'teacher_evaluations' => [],
    'activity_summary' => [],
    'certificates' => [],
    'projects' => [],
    'badges' => [],
    'source_timestamps' => [],
    'capabilities' => ['certificates' => false, 'projects' => false, 'badges' => false],
]);
$assert(array_keys($view) === [
    'student', 'skills', 'experience', 'assessment_results', 'teacher_evaluations',
    'activity_summary', 'certificates', 'projects', 'badges', 'source_timestamps', 'capabilities',
], 'Talent Passport shape is stable');
$assert($view['certificates'] === [] && $view['projects'] === [] && $view['badges'] === [], 'future facts remain empty');
$assert($view['source_timestamps'] === [], 'missing timestamps are not replaced with now');
```

Also prove the mock repository is used only when explicitly constructed as mock; do not let it masquerade as database output.

- [ ] **Step 2: Run RED**

Run the new test. Expected: missing interface/read model classes.

- [ ] **Step 3: Implement the minimal contract and read model**

Use this interface:

```php
interface TalentPassportRepository
{
    /** @return array<string,mixed> */
    public function aggregateForStudent(string $studentId): array;
}
```

The read model may normalize display fields but must not add non-zero metrics, current timestamps, verification claims, or demo collections.

- [ ] **Step 4: Run GREEN**

Run the focused test and `tests/learner_data_foundation_test.php`. Expected: both exit `0`.

- [ ] **Step 5: Review checkpoint**

Inspect the aggregate keys for naming consistency. Do not commit.

### Task 3: Implement the database aggregate with ownership isolation

**Files:**
- Create: `app/learner/data/Database/DatabaseTalentPassportRepository.php`
- Modify: `app/learner/data/Database/SchemaInspector.php` only if a reusable complete-group helper is required
- Create: `tests/learner_talent_passport_data_test.php`

**Interfaces:**
- Consumes: injected `PDO`, `SchemaInspector`, canonical Student UUID.
- Produces: repository aggregate for exactly one Student.

- [ ] **Step 1: Build a failing SQLite fixture test**

Create two students and mixed-status facts. Assert:

```php
$passport = (new DatabaseTalentPassportRepository($pdo))->aggregateForStudent($studentA);
$assert(array_column($passport['skills'], 'student_id') === [$studentA], 'skills are owner scoped');
$assert($passport['experience']['confirmed_hours'] === 2.5, 'only confirmed experience is summed');
$assert(array_column($passport['assessment_results'], 'status') === ['submitted'], 'only submitted results appear');
$assert(array_column($passport['teacher_evaluations'], 'status') === ['published'], 'only published Teacher evaluations appear');
$assert($passport['certificates'] === [] && $passport['projects'] === [] && $passport['badges'] === [], 'absent future groups are empty');
$assert(!str_contains(json_encode($passport), 'Student B Secret'), 'foreign Student facts do not leak');
```

Add a partial optional group fixture (`projects` exists but `project_members` does not) and assert capability `false` without a SQL exception.

- [ ] **Step 2: Run RED**

Expected: `DatabaseTalentPassportRepository` is missing.

- [ ] **Step 3: Implement prepared domain queries**

Use separate private methods so each query has one responsibility:

```php
private function student(string $studentId): array;
private function skills(string $studentId): array;
private function experience(string $studentId): array;
private function assessmentResults(string $studentId): array;
private function teacherEvaluations(string $studentId): array;
private function activitySummary(string $studentId): array;
private function optionalFacts(string $studentId): array;
```

Every learner-owned SQL statement must bind `:student_id`. Required-query failure throws `LearnerDataQueryException`. Optional queries execute only when the entire expected table/column group is compatible.

Use status predicates exactly:

```sql
experience_logs.status = 'confirmed'
test_attempts.status = 'submitted'
assessments.status = 'published'
```

Do not include assessment answers in the aggregate or diagnostics.

- [ ] **Step 4: Run GREEN and cross-student tests**

Run the focused test twice. Expected: deterministic output and no foreign Student data.

- [ ] **Step 5: Review SQL**

Check every query for a bound owner predicate, deterministic `ORDER BY`, and no dynamic identifier derived from user input. Do not commit.

### Task 4: Wire the repository without database-to-mock fallback

**Files:**
- Modify: `app/learner/data/RepositoryFactory.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/includes/student-data.php`
- Modify: `tests/learner_data_foundation_test.php`
- Modify: `tests/learner_database_render_test.php`

**Interfaces:**
- Consumes: `RepositoryFactory::talentPassport(array $fixture = []): TalentPassportRepository`.
- Produces: `$talentPassport`, `$dashboardKpis`, `$profileKpis`, `$skills`, `$certificates`, `$projects`, and `$learnerBadges` from the correct source.

- [ ] **Step 1: Add failing source-wiring assertions**

Prove:

```php
$databaseFactory = new RepositoryFactory('database', $pdo);
$assert($databaseFactory->talentPassport() instanceof DatabaseTalentPassportRepository, 'database source uses database passport');
$mockFactory = new RepositoryFactory('mock');
$assert($mockFactory->talentPassport($fixture) instanceof MockTalentPassportRepository, 'explicit mock uses mock passport');
```

In the render test, assert that database pages do not contain known demo values such as `Google IT Automation`, `Smart Garden IoT`, `12` demo badges, or `64h` demo experience.

- [ ] **Step 2: Run RED**

Expected: missing factory method and/or demo values still render.

- [ ] **Step 3: Implement source-safe wiring**

Add:

```php
public function talentPassport(array $fixture = []): TalentPassportRepository
{
    return $this->source === 'database'
        ? new Database\DatabaseTalentPassportRepository($this->pdo)
        : new Mock\MockTalentPassportRepository($fixture);
}
```

In `student-data.php`, retain existing arrays as explicit mock fixtures, then replace them in database mode from `TalentPassportReadModel`. Never merge a missing database collection with the fixture collection.

- [ ] **Step 4: Run GREEN**

Run foundation and database render tests. `learner_database_render_test.php` must print `learner_database_render_test: OK`; exit `0` without that marker is a failure.

- [ ] **Step 5: Review checkpoint**

Search database-mode wiring for `mock`, `localStorage`, hard-coded certificate names, project names, badge counts, and KPI values. Explain every remaining match. Do not commit.

### Task 5: Render truthful Dashboard and Profile empty states

**Files:**
- Modify: `app/learner/index.php`
- Modify: `app/learner/profile.php`
- Modify: `assets/css/learner.css` only if existing empty-state classes cannot express the design
- Create: `tests/learner_talent_passport_render_test.php`

**Interfaces:**
- Consumes: normalized variables from `student-data.php`.
- Produces: accessible database-backed Dashboard/Profile HTML.

- [ ] **Step 1: Write failing render assertions**

Assert database mode:

```php
$assert(str_contains($profileHtml, 'Chưa có chứng chỉ'), 'certificate empty state is explicit');
$assert(str_contains($profileHtml, 'Chưa có dự án'), 'project empty state is explicit');
$assert(!str_contains($profileHtml, 'Google IT Automation'), 'demo certificate is absent');
$assert(!str_contains($profileHtml, 'Smart Garden IoT'), 'demo project is absent');
$assert(!str_contains($dashboardHtml, 'vượt 28%'), 'fabricated comparison is absent');
$assert(str_contains($dashboardHtml, 'Database Learner'), 'canonical Student renders');
```

Include keyboard/heading semantics for empty-state sections.

- [ ] **Step 2: Run RED**

Expected: old static copy or values are still present.

- [ ] **Step 3: Implement minimal truthful rendering**

- Render `0` only for a computed zero over available facts.
- Render `Chưa có dữ liệu` when the capability or fact is unavailable.
- Keep automated result and Teacher evaluation source labels separate.
- Do not add Phase 3 mutation controls.

- [ ] **Step 4: Run GREEN**

Run render, database render, and existing learner UI tests. Expected: all explicit `OK` markers and Node tests pass.

- [ ] **Step 5: Review checkpoint**

Inspect HTML output for PII beyond the authenticated Student view and for any demo leakage. Do not commit.

### Task 6: Verify and report Phase 2

**Files:**
- Create: `docs/superpowers/readiness/2026-08-22-phase-2-talent-passport-review-report.md`
- Modify: `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md` only to mark evidence/status; do not rewrite scope

**Interfaces:**
- Produces: a reviewer-ready PASS/FAIL report and the `APPROVED_PHASE_2` gate.

- [ ] **Step 1: Run the focused suite**

Run all new Phase 2 tests plus existing readiness, data foundation, database render, assessment, recommendation, activity, API client, and four-role contract tests.

- [ ] **Step 2: Run static verification**

Run PHP lint over every changed PHP file, Node tests, and `git diff --check`.

- [ ] **Step 3: Run read-only database verification**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\connect-check.php --json --quick
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\migrate.php status
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\migrate.php validate
```

Expected: connection OK, 15 applied, 0 pending, validation OK. If the database is unavailable, report BLOCKED; do not claim Phase 2 PASS.

- [ ] **Step 4: Prove database invariants**

Record migration status, relevant row counts, protected migration SHA-256 hashes, and absence of DML/migration/seed commands.

- [ ] **Step 5: Write report and stop**

Report files changed, RED evidence, GREEN evidence, database state, Student isolation, four-role regressions, AI/privacy invariants, risks, and PASS/FAIL. Stop. Do not begin Phase 3 without exact authorization `APPROVED_PHASE_2`.

---

## Phase 3 — Profile Ownership, Evidence, Consent, and Sharing

### Task 7: Lock Phase 3 migration contracts before creating migrations

**Files:**
- Create: `tests/student_passport_sharing_migration_test.php`
- Create: `tests/student_certificates_projects_migration_test.php`
- Modify: `app/learner/data/Readiness/PhaseRequirements.php`

**Interfaces:**
- Consumes: approved Phase 2 aggregate and migration versions reserved by Phase 1.
- Produces: failing contracts for versions `20260821000100` and `20260821000200`.

- [ ] **Step 1: Recheck migration namespace**

Fail immediately if either migration file/version or a semantic-equivalent live table exists unexpectedly. Record any collision; do not silently choose a new ID.

- [ ] **Step 2: Write failing migration tests**

The first test requires `student_profile_details`, `student_profile_shares`, and additive consent scopes. The second requires `certificates`, `projects`, and `project_members` with the exact keys and statuses in Tasks 8–9.

- [ ] **Step 3: Run RED**

Expected: both fail because migration files do not exist.

- [ ] **Step 4: Update Phase 3 readiness contract**

Phase 3 requires both migration table groups and the existing canonical profile/skill facts. It must not require badge tables.

- [ ] **Step 5: Review checkpoint**

Confirm no production migration was applied. Do not commit.

### Task 8: Create passport details, sharing, and consent migration

**Files:**
- Create: `Database/migrations/20260821000100_create_student_passport_sharing.php`
- Test: `tests/student_passport_sharing_migration_test.php`

**Interfaces:**
- Produces: additive profile details, hashed share storage, and consent scopes `profile_share` and `application_profile_share`.

- [ ] **Step 1: Implement strict preflight**

Preflight must assert existing `student_profiles`, `privacy_consents`, and `users`; reject incompatible semantic-equivalent tables; reject unsupported existing consent scopes; and detect duplicate/non-hex token hashes or invalid share JSON during recovery.

- [ ] **Step 2: Implement `student_profile_details`**

Use this schema contract:

```sql
studentId CHAR(36) PRIMARY KEY,
location VARCHAR(255) NULL,
bio TEXT NULL,
avatarUrl VARCHAR(500) NULL,
headline VARCHAR(255) NULL,
createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE
```

- [ ] **Step 3: Implement `student_profile_shares`**

Use:

```sql
id CHAR(36) PRIMARY KEY,
studentId CHAR(36) NOT NULL,
tokenHash CHAR(64) NOT NULL,
sharedFieldsJson JSON NOT NULL,
expiresAt DATETIME(6) NOT NULL,
revokedAt DATETIME(6) NULL,
createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
UNIQUE KEY uq_student_profile_shares_token_hash (tokenHash),
KEY idx_student_profile_shares_student_active (studentId, revokedAt, expiresAt),
FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
CHECK (JSON_VALID(sharedFieldsJson)),
CHECK (expiresAt > createdAt)
```

- [ ] **Step 4: Expand privacy consent safely**

Preserve `assessment`, `skills`, `activity`, and `evaluation`; add exactly `profile_share` and `application_profile_share`. Do not delete or rewrite consent rows. Mark the migration non-reversible because new-scope rows cannot be safely rolled back.

- [ ] **Step 5: Run GREEN on migration contracts**

Run the contract test and `bin/migrate.php validate`. Do not apply to `talenthub_local`.

### Task 9: Create certificates and projects migration

**Files:**
- Create: `Database/migrations/20260821000200_create_student_certificates_and_projects.php`
- Test: `tests/student_certificates_projects_migration_test.php`

**Interfaces:**
- Produces: canonical certificate/project evidence tables; no badge tables.

- [ ] **Step 1: Implement strict preflight**

Require `student_profiles`, `users`, `teacher_profiles`, and `schools`. Reject existing legacy tables with columns such as `certificates.name/issuer`, `projects.targetAmount/raisedAmount`, or missing ownership/status constraints rather than treating them as compatible.

- [ ] **Step 2: Create `certificates`**

Use the approved columns and constraints:

```sql
id CHAR(36) PRIMARY KEY,
studentId CHAR(36) NOT NULL,
title VARCHAR(255) NOT NULL,
issuingOrganization VARCHAR(255) NOT NULL,
issueDate DATE NOT NULL,
expiryDate DATE NULL,
credentialId VARCHAR(255) NULL,
credentialUrl VARCHAR(500) NULL,
verificationStatus VARCHAR(32) NOT NULL DEFAULT 'unverified',
verifiedBy CHAR(36) NULL,
verifiedAt DATETIME(6) NULL,
createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
KEY idx_certificates_student_status (studentId, verificationStatus),
FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
FOREIGN KEY (verifiedBy) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
CHECK (verificationStatus IN ('unverified','verified','rejected')),
CHECK (expiryDate IS NULL OR expiryDate >= issueDate)
```

- [ ] **Step 3: Create `projects` and `project_members`**

Use approved columns, FKs, `uq_project_members_student (projectId, studentId)`, and checks:

```sql
projects.status IN ('draft','in_progress','completed','archived')
project_members.status IN ('active','left','removed')
projects.endAt IS NULL OR projects.startAt IS NULL OR projects.endAt >= projects.startAt
```

`projects.mentorTeacherId` and `projects.schoolId` use `ON DELETE SET NULL`; membership uses project cascade and Student restrict.

- [ ] **Step 4: Mark recovery semantics**

The migration is non-reversible after evidence rows exist. No automatic destructive `down()`.

- [ ] **Step 5: Run GREEN**

Run both migration contract tests and migration validation. Do not apply to `talenthub_local`.

### Task 10: Extend owner-controlled profile updates

**Files:**
- Modify: `src/Modules/Student/Repository/StudentRepository.php`
- Modify: `src/Modules/Student/Service/StudentProfileService.php`
- Modify: `src/Bootstrap/Application.php` only to reuse the existing `PATCH /api/v1/students/me`
- Create: `tests/student_profile_ownership_api_test.php`

**Interfaces:**
- Consumes: authenticated user ID and allow-listed input.
- Produces: atomic updates for `fullName`, `dateOfBirth`, `phone`, `location`, `bio`, `avatarUrl`, and `headline`.

- [ ] **Step 1: Write failing ownership tests**

Allowed fields must succeed with CSRF and `student_profile.update_own`. Reject `email`, `role`, `status`, `classId`, `schoolId`, verified skills, hours, assessment scores, Teacher evaluations, badges, and any unknown field with `422 FIELD_NOT_ALLOWED`.

- [ ] **Step 2: Run RED**

Expected: detail fields are rejected or not persisted.

- [ ] **Step 3: Implement one transaction**

Update `users`, `student_profiles`, and upsert `student_profile_details` atomically. Preserve the existing route, session refresh, CSRF, permission, and response envelope. Do not add a second profile endpoint.

- [ ] **Step 4: Run GREEN and role regressions**

Run the new test plus existing auth, permission, Teacher, School, and Enterprise tests.

- [ ] **Step 5: Review checkpoint**

Verify the service cannot accept ownership/verification fields even if the UI submits them. Do not commit.

### Task 11: Implement owner-scoped certificate commands

**Files:**
- Create: `app/learner/data/Database/DatabaseCertificateCommandRepository.php`
- Create: `app/learner/data/Service/CertificateCommandService.php`
- Create: `app/learner/api/v1/certificates.php`
- Modify: `app/learner/api/LearnerApiContext.php`
- Create: `tests/learner_certificate_api_test.php`

**Interfaces:**
- Produces: list, create, update, and delete operations scoped to current `studentId`.

- [ ] **Step 1: Write failing API tests**

Cover session role, `certificate.read_own`, `certificate.manage_own`, CSRF, input allow-list, UUID ownership, cross-student denial, expiry/date validation, and immutable `verified`/`rejected` rows.

- [ ] **Step 2: Run RED**

Expected: endpoint/service missing.

- [ ] **Step 3: Implement command rules**

Student may create `unverified` rows and update/delete only their own `unverified` rows. Client input must never set `verificationStatus`, `verifiedBy`, or `verifiedAt`. Use transactions and return the persisted row.

- [ ] **Step 4: Implement endpoint contract**

Use the existing `Request`, `LearnerApiContext`, `JsonResponder`, normalized error envelope, and method/action routing. Mutations call `$context->mutation(...)`; GET does not require CSRF.

- [ ] **Step 5: Run GREEN**

Run certificate, API-client, permission, and four-role tests. Do not commit.

### Task 12: Implement consented, expiring profile shares

**Files:**
- Create: `app/learner/data/Service/ProfileSharingService.php`
- Create: `app/learner/api/v1/profile-shares.php`
- Create: `app/learner/shared-profile.php`
- Modify: `app/learner/api/LearnerApiContext.php`
- Create: `tests/learner_profile_privacy_api_test.php`
- Create: `tests/learner_shared_profile_render_test.php`

**Interfaces:**
- Produces: create/revoke owner endpoints and a token-based read-only shared view.

- [ ] **Step 1: Write failing security tests**

Cover field allow-list, default exclusion of email/phone, explicit sensitive-field consent, expiry, revoke, cross-student revoke denial, raw-token absence in DB/logs, invalid token, XSS escaping, and `Cache-Control: no-store`.

- [ ] **Step 2: Run RED**

Expected: service/routes missing.

- [ ] **Step 3: Implement token lifecycle**

Generate `bin2hex(random_bytes(32))`, return raw token once, persist only `hash('sha256', $rawToken)`, use `hash_equals` where comparison occurs outside indexed lookup, require future expiry, and scope revoke by current Student.

- [ ] **Step 4: Implement consent and field selection**

Require `student_profile.share_own` and `privacy_consent.manage_own`. Store a consent event using `scope = 'profile_share'` and the current policy version. Render only allow-listed fields stored in `sharedFieldsJson`; Enterprise has no bypass route.

- [ ] **Step 5: Run GREEN**

Run both new tests plus XSS, auth, permission, and Enterprise regression tests. Do not commit.

### Task 13: Replace Phase 3 UI placeholders with server-confirmed actions

**Files:**
- Modify: `app/learner/profile.php`
- Modify: `assets/js/learner.js`
- Modify: `assets/js/learner-api.js` only if an existing generic method cannot express these requests
- Modify: `tests/learner_api_client_test.js`
- Create: `tests/learner_profile_ui_test.js`

**Interfaces:**
- Consumes: shared profile PATCH, certificate API, profile-share API.
- Produces: accessible forms whose success state appears only after a successful server envelope.

- [ ] **Step 1: Write failing UI/client tests**

Assert CSRF on mutations, same-origin canonical API base, disabled submit while pending, server validation rendering, no optimistic certificate/share success, and no raw token persistence in localStorage.

- [ ] **Step 2: Run RED**

Expected: controls or server-confirmation behavior missing.

- [ ] **Step 3: Implement minimal UI**

Use existing UI classes and API client. Keep raw share URL only in the immediate response view; do not store it in browser storage. Escape all server-derived text.

- [ ] **Step 4: Run GREEN**

Run Node tests and PHP render tests. Expected: zero failures.

- [ ] **Step 5: Review checkpoint**

Manually inspect keyboard flow, labels, error focus, and sensitive-field defaults. Do not commit.

### Task 14: Rehearse Phase 3 on a disposable clone and stop for DCR

**Files:**
- Create: `docs/superpowers/database-change-requests/2026-08-22-phase-3-passport-evidence-sharing.md`
- Create: `docs/superpowers/readiness/2026-08-22-phase-3-rehearsal-report.md`

**Interfaces:**
- Produces: lossless rehearsal evidence and the `APPROVED_PHASE_3_DCR_APPLY` gate.

- [ ] **Step 1: Create a disposable database clone**

Use an explicitly named temporary schema, never `talenthub_local`, and verify the resolved schema name before any destructive cleanup.

- [ ] **Step 2: Capture pre-migration hashes/counts**

Record every existing table row count and stable row hash outside test-owned fixtures.

- [ ] **Step 3: Apply both migrations to the clone**

Run migration once, validate constraints and indexes, then run the migration command again to prove no duplicate application or drift.

- [ ] **Step 4: Run Phase 2 and Phase 3 suites against the clone**

Expected: all focused, role, security, UI, and render suites pass.

- [ ] **Step 5: Compare invariants and write DCR**

Prove old rows and hashes are unchanged. Document forward-recovery because both migrations are non-reversible after user data exists. Stop and request exact authorization `APPROVED_PHASE_3_DCR_APPLY`.

### Task 15: Apply approved Phase 3 migrations and produce final report

**Files:**
- Create: `docs/superpowers/readiness/2026-08-22-phase-3-profile-evidence-sharing-review-report.md`

**Interfaces:**
- Consumes: explicit `APPROVED_PHASE_3_DCR_APPLY` and successful Task 14 evidence.
- Produces: primary database migration evidence and Phase 3 PASS/FAIL.

- [ ] **Step 1: Verify approval and backup**

Without the exact approval token, skip this task. With approval, create and verify a restorable backup of `talenthub_local`; record its absolute path and checksum.

- [ ] **Step 2: Re-run preflight immediately before apply**

Abort on changed migration status, schema collision, unsupported consent scopes, or backup failure.

- [ ] **Step 3: Apply migrations once**

Run the canonical migration command. Do not seed demo rows in the new tables.

- [ ] **Step 4: Run full fresh verification**

Run migration status/validation, all Phase 2/3 tests, four-role tests, AI/privacy tests, PHP lint, Node tests, and `git diff --check`. Confirm migration state is 17 applied, 0 pending only if exactly two migrations were applied to the prior 15-migration baseline.

- [ ] **Step 5: Write final report and stop**

Include files, RED/GREEN evidence, backup path/checksum, migrations, row-count/hash comparison, permissions, CSRF/authorization, token security, role regressions, AI visibility, remaining risks, and PASS/FAIL. Do not commit, push, or merge.
