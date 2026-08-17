# Student Data Foundation Final Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the remaining Holland database-mode, learner visibility, registration lifecycle, and contract-test gaps without changing shared schema or other roles.

**Architecture:** Holland readiness is validated at the PHP read-model and JavaScript domain boundaries, while canonical assessment UUIDs remain the only persistence identifiers. Database visibility is enforced in prepared SQL for both collection and detail reads; activity registration eligibility is exposed as `can_register` and rechecked by JavaScript before local state is written.

**Tech Stack:** PHP 8.3, PDO prepared statements, browser-neutral JavaScript loaded by Node's built-in test runner, in-memory SQLite integration fixtures.

## Global Constraints

- Work only on `feature/student`.
- Modify only `app/learner`, learner-owned JavaScript assets, learner tests, and related documentation.
- Do not modify `Database/Talenthub_DB.sql`, schema, shared mock data, or code owned by enterprise, school, teacher, or another role.
- Do not create a project database.
- Do not commit, push, or merge.

---

### Task 1: Holland readiness, result lifecycle, and canonical identifiers

**Files:**
- Modify: `app/learner/data/ReadModel/AssessmentReadModel.php`
- Modify: `app/learner/includes/assessment-data.php`
- Modify: `app/learner/assessment.php`
- Modify: `app/learner/assessment-result.php`
- Modify: `app/learner/discover.php`
- Modify: `assets/js/learner-assessment.js`
- Test: `tests/learner_holland_ui_test.js`
- Test: `tests/learner_data_foundation_test.php`
- Test: `tests/learner_database_render_test.php`

**Interfaces:**
- Produce: `AssessmentReadModel::question()` with `options: list<{value: int|float|string, label: string}>`.
- Produce: `AssessmentReadModel::isHollandReady(array $questions): bool` requiring exactly 24 valid questions.
- Produce: `AssessmentReadModel::completedAttempts(array $records): array` accepting only `submitted`/`completed` attempts with a valid RIASEC result.
- Produce: JavaScript `normalizeQuestionOptions`, `validateHollandAssessment`, and filtered `mergeAssessmentHistory` helpers.

- [x] Add PHP and JavaScript assertions for numeric/object option normalization, missing dimensions, fewer than 24 questions, `result=null`, canonical UUID boot data, and exclusion of in-progress attempts.
- [x] Run focused PHP/Node tests and confirm failures identify the missing contracts.
- [x] Normalize options without inventing dimension data, validate prompt/options/RIASEC dimension/count, and expose unavailable state as `Bài test chưa sẵn sàng`.
- [x] Keep canonical UUIDs in boot/localStorage filters while retaining `holland` only in route URLs.
- [x] Run focused tests to green.

### Task 2: Ecosystem visibility and activity registration lifecycle

**Files:**
- Modify: `app/learner/data/Database/DatabaseEcosystemRepository.php`
- Modify: `app/learner/data/ReadModel/ActivityReadModel.php`
- Modify: `app/learner/activity-detail.php`
- Modify: `assets/js/learner-activities.js`
- Test: `tests/learner_data_foundation_test.php`
- Test: `tests/learner_database_render_test.php`
- Test: `tests/learner_activities_ui_test.js`

**Interfaces:**
- Produce: repository collection/detail queries restricted to active schools, active and verified/approved enterprises, active opportunities, and active parent enterprises.
- Produce: `ActivityReadModel::canRegister(array $activity, ?DateTimeImmutable $now = null): bool` and `can_register` on every activity view.
- Produce: JavaScript `canRegisterActivity(activity, now)`; `createRegistration()` returns `null` when registration is not allowed.

- [x] Add database fixtures/assertions for inactive/unapproved partners, hidden opportunities, and inaccessible detail UUIDs.
- [x] Add PHP/JavaScript assertions for draft, cancelled, closed, completed, before-window, and expired activity registration.
- [x] Run focused tests and confirm the expected failures.
- [x] Add identical prepared visibility predicates to list/detail SQL and calculate registration eligibility from status plus registration window.
- [x] Disable the PHP CTA and enforce the same guard in JavaScript before conflict checks or localStorage writes.
- [x] Run focused tests to green.

### Task 3: Identifier/source normalization

**Files:**
- Modify: `app/learner/data/Mock/MockStudentRepository.php`
- Modify: `app/learner/data/bootstrap.php`
- Test: `tests/learner_data_foundation_test.php`

**Interfaces:**
- `studentId` and `student_id` both become the deterministic mock student UUID required by `StudentRepository`.
- `learner_current_student_id()` trims and lowercases `source` exactly as `RepositoryFactory` does.

- [x] Add failing camelCase/source-whitespace regression assertions.
- [x] Normalize keys before choosing the student primary identifier and normalize source before database-mode validation.
- [x] Run the foundation test to green.

### Task 4: Verification and audits

- [x] PHP-lint every `app/learner/**/*.php` and `tests/learner_*_test.php` file.
- [x] Run all learner PHP tests and all learner Node tests.
- [x] Run database-mode integration tests with the in-memory schema-compatible fixture.
- [x] Run `git diff --check`.
- [x] Audit learner database repositories for DDL, DML, and direct `PDO::query()` use.
- [x] Audit changed paths against the allowed learner/test/docs/assets scope and leave all changes uncommitted.
