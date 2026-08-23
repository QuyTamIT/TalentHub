# Phase 9 Design — Deterministic Badges, Levels, and Personal Statistics

- Date: 2026-08-23
- Status: APPROVED_FOR_IMPLEMENTATION_PLANNING
- Workspace: `D:\TalentHub`
- Baseline branch / commit: `feature/student` / `8875310dbb919f04a5769c7c65f60b98bd16e399`
- Primary schema: `talenthub_local` (MySQL 8.4.3)

## 1. Goal and Boundary

Phase 9 materializes learner badges from confirmed database facts, derives a versioned experience level, and exposes owner-scoped weekly/monthly personal statistics. It replaces database-mode empty/static badge and statistics screens without adding class ranking, school ranking, arbitrary rule execution, email delivery, model-visible AI output, or Phase 10 UI refactoring.

Primary migration is intentionally outside the Anti implementation gate. Anti must finish code, tests, DCR, disposable rehearsal, and the review report while `20260821000700` remains pending on `talenthub_local`. Codex reviews the result before any primary apply, system-catalog insertion, or badge backfill.

## 2. Chosen Architecture

Use event-driven deterministic awards plus an idempotent operator backfill:

1. `DatabaseStatisticsRepository` is the only source of the four allow-listed badge facts and the weekly/monthly personal aggregates.
2. `BadgeRuleEngine` is pure PHP. It accepts only a fixed threshold schema and never evaluates SQL, PHP, paths, callbacks, or expressions from JSON.
3. `BadgeAwardService` evaluates active rule versions, inserts each `(studentId,badgeId)` once, and publishes `badge_awarded` through the Phase 8 `NotificationService` in the same PDO transaction.
4. Confirmed check-in and submitted assessment producers call the award service before their existing transaction commits. Published Teacher evaluation uses the same service from the grading transaction. An operator CLI performs idempotent backfill for facts that existed before Phase 9.
5. Learner badge/statistics GET endpoints are read-only. Reading a page or API response never creates an award.

Rejected approaches:

- Read-time award mutation was rejected because GET requests must be safe and replayable.
- Browser-side rule evaluation was rejected because it would trust client state and duplicate business rules.
- Batch-only awards were rejected because new confirmed facts should produce timely awards; the batch command remains only for backfill/recovery.

## 3. Canonical Schema

Migration `20260821000700_create_badges_and_award_rules.php` is additive and non-reversible. If any target table already exists with a non-exact contract, preflight fails without altering or dropping it.

### `badges`

- `id CHAR(36)` primary key
- `code VARCHAR(64)` unique, stable, lowercase snake case
- `name VARCHAR(255)`, `category VARCHAR(64)`, `description TEXT`
- `iconUrl VARCHAR(500) NULL`
- `level INT NOT NULL DEFAULT 1`
- `status VARCHAR(32)` constrained to `active|inactive|deprecated`
- `createdAt DATETIME(6)`, `updatedAt DATETIME(6)` in UTC
- unique index `uq_badges_code(code)`

### `badge_rule_definitions`

- `id CHAR(36)` primary key
- `badgeId CHAR(36)` foreign key to `badges.id`, delete restricted
- `ruleType VARCHAR(64)` constrained to `threshold`
- `thresholdCriteria JSON` with exact application schema `{fact,operator,value}`
- `version INT` greater than zero
- `isActive TINYINT(1)`
- `createdAt DATETIME(6)`, `updatedAt DATETIME(6)`
- unique index `uq_badge_rules_badge_version(badgeId,version)`
- index `idx_badge_rules_active(isActive,badgeId,version)`

### `student_badges`

- `id CHAR(36)` primary key
- `studentId CHAR(36)` foreign key to `student_profiles.id`, delete restricted
- `badgeId CHAR(36)` foreign key to `badges.id`, delete restricted
- `ruleDefinitionId CHAR(36)` foreign key to `badge_rule_definitions.id`, delete restricted
- `awardedAt DATETIME(6)` in UTC
- `awardedBy VARCHAR(64)` constrained to `system|teacher|school_admin`; Phase 9 writes `system`
- `awardContext JSON` containing the rule version and exact fact snapshot used for the decision
- unique index `uq_student_badges_award(studentId,badgeId)`
- indexes for `badgeId`, `ruleDefinitionId`, and `(studentId,awardedAt)`

The migration inserts five product configuration badges and five v1 rules, but never inserts learner awards:

| Badge code | Fact | Threshold |
|---|---|---:|
| `first_experience` | `confirmed_experience_hours` | 1 |
| `experience_10h` | `confirmed_experience_hours` | 10 |
| `active_participant` | `attended_activity_count` | 3 |
| `assessment_explorer` | `submitted_assessment_type_count` | 2 |
| `teacher_recognition` | `published_teacher_evaluation_count` | 1 |

Catalog IDs and rule IDs are hard-coded valid UUIDs in the migration. Rerun is an exact no-op; a matching ID/code with different content is a preflight conflict.

## 4. Rule Contract and Facts

Allowed criterion keys are exactly `fact`, `operator`, and `value`. The only operator is `gte`. `value` must be a finite non-negative integer or decimal. Unknown keys, missing keys, booleans, strings as thresholds, negative values, non-finite values, unknown facts, and unknown operators fail closed.

Allowed facts are:

- `confirmed_experience_hours`: sum of `experience_logs.hours` where `status='confirmed'`.
- `attended_activity_count`: count of distinct learner registrations backed by a confirmed check-in/confirmed experience; pending/rejected/cancelled rows do not count.
- `submitted_assessment_type_count`: count of distinct `talent_tests.type` with a learner `test_attempts.status='submitted'` and a persisted `test_results` row.
- `published_teacher_evaluation_count`: count of `assessments` where `status='published'` and `publishedAt IS NOT NULL`.

Rules consume lifetime facts. Personal statistics consume the same confirmed sources within explicit UTC calendar periods.

## 5. Levels and Personal Statistics

Levels are derived, not persisted. `LevelProgression::CONFIG_VERSION` is `experience-hours-v1`:

| Level | Minimum confirmed hours |
|---|---:|
| Explorer | 0 |
| Innovator | 10 |
| Expert | 100 |
| Master | 200 |

The response includes current level, current hours, next level, next threshold, remaining hours, and bounded progress percent. Boundary behavior at 0, 10, 100, and 200 hours is covered by pure unit tests.

Statistics periods are allow-listed:

- `week`: current ISO week, Monday 00:00:00 UTC through the following Monday (exclusive), with seven daily buckets.
- `month`: first day of the current UTC month through first day of the following month (exclusive), with daily buckets.

The owner-scoped response contains period bounds, confirmed hours, confirmed attended activities, submitted assessment types, published evaluations, badges awarded in the period, experience buckets, experience category distribution, and lifetime level. It contains no peer average, comparison line, percentile, rank, class position, school position, or invented competency score.

## 6. API and UI

- `GET app/learner/api/v1/badges.php` requires Student role and `badge.read_own`; identity comes from `LearnerApiContext`. It returns awarded badges, active rule progress, lifetime facts, and derived level.
- `GET app/learner/api/v1/statistics.php?period=week|month` requires Student role and `student_dashboard.read_own`; unknown query keys and invalid periods return normalized `422` errors.
- Neither endpoint accepts `studentId`, writes data, or falls back to mock data in database mode.
- `app/learner/badges.php`, `app/learner/statistics.php`, and the learner Dashboard render server-truth loading/empty/error/content states. All untrusted values use escaping or DOM `textContent`; no new `innerHTML` path is allowed.
- Mock mode remains explicit demo behavior and must not contaminate database mode.

## 7. Cross-Role and AI Constraints

- Teacher/School/Enterprise cannot call learner owner endpoints. No new badge mutation permission is introduced.
- `SchoolDashboardService` is reconciled to the canonical badge columns and remains scoped by `classes.schoolId`; it does not receive learner mutation access.
- Enterprise receives no badge writer or unrestricted badge reader.
- Talent Passport reads real awarded badges after the schema becomes available; absence remains `[]` before migration. Once exact Phase 9 tables exist, query errors fail closed instead of silently masquerading as an absent capability.
- Badge evaluation is a deterministic rule feature, not an AI feature. `TALENTHUB_AI_VISIBLE_PERCENT` remains `0`; no model call, prompt, provider, or rollout decision changes.

## 8. Transaction, Idempotency, and Failure Behavior

- `BadgeAwardService` joins an existing PDO transaction when called by a producer and starts/owns a transaction only for CLI backfill.
- An inserted `student_badges` row and its `badge_awarded` notification commit or roll back together.
- The notification event key is `badge_award:<studentId>:<badgeId>:v<ruleVersion>` and remains within the Phase 8 format/length contract.
- Only exact MySQL duplicate error 1062 (or exact SQLite unique constraint) is treated as an already-awarded replay. Foreign-key failures, missing recipients/tables, malformed rules, and all other integrity errors propagate.
- Concurrent evaluation for one student/badge results in one award and one notification.

## 9. Review and Primary-Database Gate

Anti may create code, tests, docs, the pending migration, and disposable databases. Anti must not apply `00700`, seed catalog rows, run badge backfill, or otherwise mutate `talenthub_local` before Codex approval.

The implementation gate requires a pinned dump/hash, disposable restore, migration apply twice, exact schema/RBAC/data-hash verification, rule/API/UI tests, MySQL concurrency and rollback tests, regression for Phases 2–8 and all four roles, cleanup of schema/grants, and a `PHASE_9_GO_FOR_CODEX_REVIEW` report. Phase 10 is out of scope.
