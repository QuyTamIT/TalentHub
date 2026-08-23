# Phase 9 Badges, Levels, and Personal Statistics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Build deterministic, idempotent badge awards and real owner-scoped level/statistics views from confirmed TalentHub database facts.

**Architecture:** One statistics repository owns all fact queries; a pure rule engine evaluates a fixed threshold language; a transactional award service materializes unique awards and Phase 8 notifications. Producer integrations handle new facts, while an operator CLI handles pre-Phase-9 backfill. Learner read endpoints/pages never create awards.

**Tech Stack:** PHP 8.3.30, PDO, MySQL 8.4.3, SQLite fixtures, existing TalentHub migration framework, vanilla JavaScript, Node test runner.

> **Post-review amendment (Codex, 2026-08-23):** Anti's pending-primary handoff was independently reviewed and repaired. The disposable rehearsal, full verification, backup, primary migration, deterministic backfill, and zero-delta replay all passed. The final state is `APPROVED_PHASE_9`; the earlier pending-primary constraints below describe the implementation gate before Codex approval.

## Global Constraints

- Work only in `D:\TalentHub`, branch `feature/student`, baseline commit `8875310dbb919f04a5769c7c65f60b98bd16e399`.
- Preserve `.env`, `.claude/`, `.qwen/`, `Database/migrations/learner/001`–`004`, and every applied migration byte-for-byte.
- Preserve Phase 2–8 behavior and `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- Do not commit, push, merge, reset, clean, checkout, stash, or start Phase 10.
- Do not apply `20260821000700`, insert badge catalog/rules, backfill awards, or mutate `talenthub_local`; stop for Codex review with the migration pending.
- Disposable database names must match `^talenthub_phase9_(?:rehearsal|test)_[0-9]{14}$`, must never equal `talenthub_local`, and must be dropped with their grants revoked in `finally` cleanup.
- Database mode is fail-closed and never falls back to demo/mock data.
- Use TDD: each review unit begins with a failing behavior/contract test and ends with its focused regression passing.

---

## Locked File Map

### Create

- `Database/migrations/20260821000700_create_badges_and_award_rules.php`
- `app/learner/data/Contracts/BadgeRepository.php`
- `app/learner/data/Contracts/StatisticsRepository.php`
- `app/learner/data/Database/DatabaseBadgeRepository.php`
- `app/learner/data/Database/DatabaseStatisticsRepository.php`
- `app/learner/data/Domain/LevelProgression.php`
- `app/learner/data/Service/BadgeRuleEngine.php`
- `app/learner/data/Service/BadgeAwardService.php`
- `app/learner/data/Service/BadgeReadService.php`
- `app/learner/data/Service/StatisticsService.php`
- `app/learner/api/v1/badges.php`
- `app/learner/api/v1/statistics.php`
- `bin/run-badge-awards.php`
- `assets/js/learner-badges.js`
- `assets/js/learner-statistics.js`
- `tests/phase9_badge_migration_contract_test.php`
- `tests/learner_badge_rules_test.php`
- `tests/learner_badge_award_transaction_test.php`
- `tests/learner_badge_award_mysql_concurrency_test.php`
- `tests/learner_statistics_data_test.php`
- `tests/learner_badges_api_test.php`
- `tests/learner_statistics_api_test.php`
- `tests/learner_badges_ui_test.js`
- `tests/learner_statistics_ui_test.js`
- `tests/phase9_cross_role_contract_test.php`
- `tests/phase9_rehearsal_integrity_test.php`
- `docs/superpowers/database-change-requests/2026-08-23-phase-9-badges-levels-statistics.md`
- `docs/superpowers/readiness/2026-08-23-phase-9-rehearsal-report.md`
- `docs/superpowers/readiness/2026-08-23-phase-9-review-report.md`

### Modify only where required

- `app/learner/data/bootstrap.php`
- `app/learner/data/RepositoryFactory.php`
- `app/learner/data/Readiness/PhaseRequirements.php`
- `app/learner/data/Readiness/TalentPassportOptionalSchema.php`
- `app/learner/data/Database/DatabaseTalentPassportRepository.php`
- `app/learner/data/Database/DatabaseCheckinRepository.php`
- `app/learner/data/Database/DatabaseAssessmentWriteRepository.php`
- `src/Modules/Teacher/Repository/TeacherGradingRepository.php`
- `app/teacher/assessments/index.php` only if explicit dependency injection is needed
- `src/Modules/School/Service/SchoolDashboardService.php`
- `app/learner/includes/student-data.php`
- `app/learner/badges.php`
- `app/learner/statistics.php`
- `app/learner/index.php`
- `assets/css/learner.css`
- `tests/learner_phase_2_optional_capabilities_test.php`
- `tests/learner_talent_passport_data_test.php`
- `tests/learner_talent_passport_render_test.php`
- `tests/learner_data_foundation_test.php`
- `tests/notification_domain_producer_test.php`
- `tests/student_portal_cross_role_contract_test.php`
- `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md` only after every Phase 9 gate passes; record `GO_FOR_CODEX_REVIEW`, not `APPROVED_PHASE_9`.

---

### Task 1: Lock the Runtime Baseline and DCR

**Files:**
- Create: `docs/superpowers/database-change-requests/2026-08-23-phase-9-badges-levels-statistics.md`
- Test: read-only commands only

**Interfaces:**
- Consumes: committed Phase 8 state and migration registry.
- Produces: exact pre-change manifest, allowed delta, recovery procedure, and pinned rehearsal input.

- [x] **Step 1: Verify repository invariants**

Run:

```powershell
git branch --show-current
git rev-parse HEAD
git status --short
```

Expected: `feature/student`, commit `8875310...`; only pre-existing `.claude/` and `.qwen/` may be untracked before Phase 9 files appear.

- [x] **Step 2: Verify runtime and migration baseline**

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe bin\connect-check.php --json --quick
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe bin\migrate.php validate
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe bin\migrate.php status
```

Expected: MySQL 8.4.3, validation OK, 28 applied, 0 pending before `00700` exists.

- [x] **Step 3: Record exact source facts**

Use read-only SQL to record table/migration counts; absence of `badges`, `student_badges`, `badge_rule_definitions`; counts for confirmed experience, attended/confirmed activity facts, submitted assessment types, published evaluations, notifications, permissions, and role mappings. Record hashes for all 58 baseline tables and applied migration files.

- [x] **Step 4: Write the DCR**

The DCR must name the three tables, five system catalog rows, five rule rows, zero planned learner awards before backfill, exact indexes/FKs/checks, disposable protocol, backup commands, allowed post-approval mutations, and restore procedure. Status remains `PENDING_CODEX_REVIEW`.

- [x] **Step 5: Review gate**

Report Task 1 facts in the work log and continue automatically unless branch/HEAD/migration facts contradict this plan. A contradiction is a blocker; ordinary dirty Phase 9 files are not.

---

### Task 2: Write RED Migration and Contract Tests

**Files:**
- Create: `tests/phase9_badge_migration_contract_test.php`
- Modify: `tests/student_portal_cross_role_contract_test.php`
- Modify: `app/learner/data/Readiness/PhaseRequirements.php`
- Modify: `app/learner/data/Readiness/TalentPassportOptionalSchema.php`

**Interfaces:**
- Consumes: canonical schema from the design.
- Produces: executable exact-contract expectations for Task 3.

- [x] **Step 1: Add a failing migration contract test**

Assert migration identity, non-reversibility, target-table preflight, exact three-table schema, five stable catalog/rule definitions, `uq_student_badges_award`, `uq_badge_rules_badge_version`, FKs/actions, JSON/check constraints, and absence of destructive `DROP/TRUNCATE/DELETE` behavior.

- [x] **Step 2: Fix the existing readiness-name contradiction in the test expectation**

Phase 9 canonical unique index is `uq_student_badges_award`. Update `PhaseRequirements` from `uq_student_badges_student_badge`; do not create two synonymous indexes.

- [x] **Step 3: Extend optional capability contract**

Badge capability requires `badges`, `student_badges`, and `badge_rule_definitions` with the design's columns/indexes. Phase 2 still treats the whole capability as optional before migration.

- [x] **Step 4: Reserve Phase 9 migration in cross-role tests**

Assert `20260821000700_create_badges_and_award_rules.php` exists and no Phase 10 migration is introduced.

- [x] **Step 5: Run RED tests**

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\phase9_badge_migration_contract_test.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\student_portal_cross_role_contract_test.php
```

Expected: fail because `00700` and exact Phase 9 contracts are not implemented yet.

---

### Task 3: Implement Migration `00700`

**Files:**
- Create: `Database/migrations/20260821000700_create_badges_and_award_rules.php`
- Test: `tests/phase9_badge_migration_contract_test.php`

**Interfaces:**
- Consumes: existing `student_profiles` and migration framework.
- Produces: exact three-table schema and five deterministic active threshold rules.

- [x] **Step 1: Implement preflight**

Require UTC, MySQL 8+, parent tables, no conflicting migration ID, and exact compatibility for any pre-existing target table. Conflict messages must identify the table/column/index/FK/catalog row; never repair implicitly.

- [x] **Step 2: Implement additive `up()`**

Create `badges`, then `badge_rule_definitions`, then `student_badges`. Insert the five product configuration badges/rules with hard-coded UUIDs and conflict-detecting idempotency. Insert no `student_badges` and no notifications.

- [x] **Step 3: Implement migration-level verification**

After creation, verify engine/collation, column order/type/null/default/extra, index order/uniqueness, FK actions, checks, catalog content, and exactly one active v1 rule per seeded badge.

- [x] **Step 4: Run lint and contract tests**

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l Database\migrations\20260821000700_create_badges_and_award_rules.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe bin\migrate.php validate
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe bin\migrate.php status
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\phase9_badge_migration_contract_test.php
```

Expected: lint/validation/contract PASS; primary status shows `00700` pending.

---

### Task 4: Implement Facts, Levels, and Pure Rule Evaluation

**Files:**
- Create: `app/learner/data/Contracts/StatisticsRepository.php`
- Create: `app/learner/data/Database/DatabaseStatisticsRepository.php`
- Create: `app/learner/data/Domain/LevelProgression.php`
- Create: `app/learner/data/Service/StatisticsService.php`
- Create: `app/learner/data/Service/BadgeRuleEngine.php`
- Create: `tests/learner_badge_rules_test.php`
- Create: `tests/learner_statistics_data_test.php`

**Interfaces:**
- Produces: `StatisticsRepository::lifetimeFacts(string $studentId): array`, `StatisticsRepository::periodStatistics(string $studentId, DateTimeImmutable $from, DateTimeImmutable $to): array`, `LevelProgression::fromHours(float $hours): array`, and `BadgeRuleEngine::evaluate(array $criteria, array $facts): array`.

- [x] **Step 1: Write pure RED rule/level tests**

Cover exact allowed facts, only `gte`, extra/missing keys, wrong scalar types, negative/non-finite thresholds, unknown fact/operator, below/equal/above threshold, and level boundaries 0/10/100/200.

- [x] **Step 2: Implement `BadgeRuleEngine`**

Return:

```php
[
    'eligible' => bool,
    'fact' => string,
    'current' => int|float,
    'target' => int|float,
    'progressPercent' => int,
]
```

Reject invalid criteria with a domain exception; never interpolate rule values into SQL.

- [x] **Step 3: Implement `LevelProgression`**

Use `CONFIG_VERSION='experience-hours-v1'` and the four exact level boundaries. Clamp percentage to 0–100 and return `nextLevel=null`, `remainingHours=0`, `progressPercent=100` at Master.

- [x] **Step 4: Write repository RED tests**

Build two-student SQLite fixtures containing confirmed and non-confirmed facts. Assert owner isolation, confirmed/published/submitted-only rules, distinct assessment types, half-open UTC boundaries, empty periods, category totals, and no ranking/comparison keys.

- [x] **Step 5: Implement repository and service**

Use prepared parameters for `studentId`, `from`, and `to`. Weekly period is ISO Monday-to-Monday UTC; monthly is first-to-first UTC. The service accepts only `week|month` and an injectable clock for deterministic tests.

- [x] **Step 6: Run focused tests**

Run both new suites and `tests/learner_data_foundation_test.php`. Expected: PASS and no direct query concatenation with learner input.

---

### Task 5: Implement Transactional Awards and Backfill CLI

**Files:**
- Create: `app/learner/data/Contracts/BadgeRepository.php`
- Create: `app/learner/data/Database/DatabaseBadgeRepository.php`
- Create: `app/learner/data/Service/BadgeAwardService.php`
- Create: `app/learner/data/Service/BadgeReadService.php`
- Create: `bin/run-badge-awards.php`
- Create: `tests/learner_badge_award_transaction_test.php`
- Create: `tests/learner_badge_award_mysql_concurrency_test.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/data/RepositoryFactory.php`

**Interfaces:**
- Produces: `BadgeAwardService::evaluateAndAward(string $studentId): array`, `BadgeReadService::forStudent(string $studentId): array`, and CLI modes `--dry-run` (default), `--apply --student-id=<uuid>`, or `--apply --all`.

- [x] **Step 1: Write RED transaction/idempotency tests**

Assert first run inserts eligible awards/notifications, second run inserts zero, different learners remain isolated, disabled notification preference suppresses only notification as Phase 8 defines, malformed rule rolls back, missing recipient/FK rolls back, and award context records rule ID/version/fact/current/target.

- [x] **Step 2: Implement repository reads and inserts**

Read active badge/rule catalog, facts from `StatisticsRepository`, awarded rows, and user recipient. Insert an award with exact duplicate handling only. `withTransaction()` owns commit/rollback only when PDO was not already in a transaction.

- [x] **Step 3: Implement award service**

For each active rule, evaluate with `BadgeRuleEngine`; insert eligible unseen awards; publish `badge_awarded` to `/app/learner/badges.php` with event key `badge_award:<studentId>:<badgeId>:v<version>`. Notification and award share the same transaction.

- [x] **Step 4: Implement read service**

Return awarded catalog rows, active-rule progress states (`achieved|in_progress|locked`), lifetime facts, and derived level. Reading never invokes `evaluateAndAward()`.

- [x] **Step 5: Implement operator CLI**

Default is read-only dry run. `--apply` is refused on `talenthub_local` unless `TALENTHUB_PHASE9_PRIMARY_APPLY_APPROVED=1`; Anti must never set that variable. The CLI refuses unknown options and invalid UUIDs and prints per-student/aggregate counts without exposing PII.

- [x] **Step 6: Implement MySQL concurrency test**

On an exact disposable schema, start at least eight processes evaluating the same eligible badge. Assert one `student_badges` row, one `badge_awarded` notification, seven harmless replays, and zero partial rows after injected notification failure.

- [x] **Step 7: Run focused suites**

Run transaction tests, CLI dry-run contract, notification producer regressions, and disposable MySQL concurrency. Expected: all PASS; `talenthub_local` remains unchanged.

---

### Task 6: Wire Real Domain Producers

**Files:**
- Modify: `app/learner/data/Database/DatabaseCheckinRepository.php`
- Modify: `app/learner/data/Database/DatabaseAssessmentWriteRepository.php`
- Modify: `src/Modules/Teacher/Repository/TeacherGradingRepository.php`
- Modify: `app/teacher/assessments/index.php` only if construction requires it
- Modify: `tests/notification_domain_producer_test.php`
- Test: `tests/learner_badge_award_transaction_test.php`

**Interfaces:**
- Consumes: `BadgeAwardService::evaluateAndAward()`.
- Produces: automatic badge evaluation after confirmed check-in, submitted assessment, and newly published Teacher evaluation.

- [x] **Step 1: Write RED producer tests**

Assert check-in can unlock experience/activity badges, assessment submission can unlock assessment badge, a draft evaluation cannot unlock a badge, publication can unlock it, and any award/notification error rolls back the originating domain transaction.

- [x] **Step 2: Add optional dependency injection without breaking constructors**

Add `?BadgeAwardService $badgeAwards = null` after existing optional dependencies. Lazy construction must use the same PDO connection. Existing tests/constructors remain valid.

- [x] **Step 3: Evaluate before producer commit**

Invoke only after the confirmed/submitted/published fact is persisted and before commit. Exceptions propagate; do not swallow missing-table errors after Phase 9 schema is required.

- [x] **Step 4: Run Phase 4–8 producer regressions**

Run activity registration, check-in, assessment persistence/immutability, Teacher grading, Phase 8 notification, and rollback suites. Expected: PASS with no duplicate upstream notification.

---

### Task 7: Implement Owner-Scoped Read APIs

**Files:**
- Create: `app/learner/api/v1/badges.php`
- Create: `app/learner/api/v1/statistics.php`
- Create: `tests/learner_badges_api_test.php`
- Create: `tests/learner_statistics_api_test.php`

**Interfaces:**
- Consumes: `BadgeReadService::forStudent()` and `StatisticsService::forStudentPeriod()`.
- Produces: normalized learner API envelopes.

- [x] **Step 1: Write RED source/runtime API tests**

Cover unauthenticated 401, wrong role/permission 403, owner identity derived from session, rejection of `studentId`, unknown query/action keys, invalid period 422, correct empty/content responses, cross-owner isolation, and proof that GET changes no award/notification rows.

- [x] **Step 2: Implement badges GET**

Use `LearnerApiContext`, Student role, and `badge.read_own`. Accept no query keys. Return `badges`, `progress`, `facts`, and `level` from the authenticated student only.

- [x] **Step 3: Implement statistics GET**

Use Student role and `student_dashboard.read_own`. Allow exactly `period`; default `month`; return period, KPIs, buckets, fields, and level. Do not implement POST/PATCH/DELETE.

- [x] **Step 4: Run endpoint tests**

Use SQLite runtime fixtures and the exact disposable MySQL gate where SQL differences matter. Expected: all API tests PASS and row-count/hash snapshots unchanged by GET.

---

### Task 8: Replace Database-Mode Empty/Static UI

**Files:**
- Modify: `app/learner/includes/student-data.php`
- Modify: `app/learner/badges.php`
- Modify: `app/learner/statistics.php`
- Modify: `app/learner/index.php`
- Create: `assets/js/learner-badges.js`
- Create: `assets/js/learner-statistics.js`
- Modify: `assets/css/learner.css`
- Create: `tests/learner_badges_ui_test.js`
- Create: `tests/learner_statistics_ui_test.js`
- Modify: `tests/learner_talent_passport_render_test.php`

**Interfaces:**
- Consumes: owner-scoped GET APIs and server-rendered initial state.
- Produces: accessible server-truth badge, level, statistics, loading, empty, and error views.

- [x] **Step 1: Write RED render/JS tests**

Assert database mode contains no demo sentinels, rank, peer comparison, fake change percentages, static badge counts, or fake competency score. Assert safe DOM APIs, keyboard-accessible filters, period request race cancellation, error/retry, empty state, and API-derived content.

- [x] **Step 2: Render real badge state**

Show current/next level from `experience-hours-v1`, awarded badges, progress for active rules, and exact empty/error states. Badge filters operate only over server-provided cards.

- [x] **Step 3: Render real statistics**

Replace the demo comparison line and rank/competency concepts with owner-only KPIs, confirmed experience buckets, category distribution, and fact counts. Period changes fetch `week|month`; stale responses cannot overwrite newer selection.

- [x] **Step 4: Update Dashboard**

Use real awarded badge count, confirmed hours, and level. Do not show class rank or invented change deltas in database mode.

- [x] **Step 5: Run UI/render suites**

Run both new Node suites plus existing learner UI, API-client, Talent Passport render/data, sidebar/banner, and database-render suites. Expected: PASS and no untrusted `innerHTML`.

---

### Task 9: Reconcile Talent Passport, School Consumer, and Cross-Role Denials

**Files:**
- Modify: `app/learner/data/Database/DatabaseTalentPassportRepository.php`
- Modify: `src/Modules/School/Service/SchoolDashboardService.php`
- Create: `tests/phase9_cross_role_contract_test.php`
- Modify: `tests/learner_talent_passport_data_test.php`
- Modify: `tests/student_portal_cross_role_contract_test.php`

**Interfaces:**
- Consumes: canonical Phase 9 tables.
- Produces: compatible Student Passport and school-scoped distribution/export; explicit Teacher/Enterprise learner-endpoint denial.

- [x] **Step 1: Write RED compatibility/negative tests**

Assert real awarded badges appear only for their student; School aggregation is restricted to its own classes; another School sees zero; Teacher and Enterprise cannot use learner APIs; Enterprise receives no badge writer; AI outward mode remains rule with visible percent zero.

- [x] **Step 2: Reconcile Passport capability**

Require all three exact tables. Query explicit columns instead of `b.*`. Remove broad catch-and-empty behavior after exact capability is available; malformed present schema/query fails closed.

- [x] **Step 3: Reconcile School query**

Replace the legacy `student_badges.sourceEvent` dependency with canonical badge code/name, awardedBy, and awardedAt through a join to `badges`. Preserve `classes.schoolId` scope.

- [x] **Step 4: Run cross-role/AI regressions**

Run learner Passport, School dashboard, permission compatibility, Student app context, AI source/snapshot/rule/rollout, notification, and four-role contract suites. Expected: PASS and `TALENTHUB_AI_VISIBLE_PERCENT=0` unchanged.

---

### Task 10: Disposable Rehearsal and Failure Injection

**Files:**
- Create: `tests/phase9_rehearsal_integrity_test.php`
- Create: `docs/superpowers/readiness/2026-08-23-phase-9-rehearsal-report.md`

**Interfaces:**
- Consumes: an explicit dump path and 64-character pinned SHA-256.
- Produces: disposable evidence without primary mutation.

- [x] **Step 1: Make the rehearsal self-orchestrating**

Require `TALENTHUB_PHASE9_BASELINE_DUMP` and `TALENTHUB_PHASE9_BASELINE_SHA256`. Refuse missing/mismatched values, unsafe schemas, and `talenthub_local`. Create a unique schema/grant and guarantee revoke/drop in `finally`.

- [x] **Step 2: Prove migration preservation**

Restore the pinned dump, apply pending migrations including `00700`, run migration a second time, and require no changes. Before any award evaluation, prove all 58 pre-existing table counts/hashes unchanged except `schema_migrations`; prove exactly 5 badges, 5 active v1 rules, and 0 student awards.

- [x] **Step 3: Prove deterministic backfill and replay**

Run CLI dry-run, then apply on disposable. Allow changes only to `student_badges` and `notifications`. Run apply again and require zero new rows. Verify every award is justified by the saved fact snapshot and every award notification has one matching event key.

- [x] **Step 4: Run concurrency/failure gates**

Run eight-process duplicate evaluation, notification rollback injection, malformed-rule failure, owner API runtime, UI/source tests, and Phase 2–8 regressions.

- [x] **Step 5: Prove cleanup**

After all checks, assert the rehearsal schema count and matching `mysql.db` grant count are zero.

- [x] **Step 6: Write rehearsal report**

Record dump path/size/SHA, schema name, migrations applied, assertion/test counts, permitted row deltas, replay result, cleanup result, and exact failures encountered/fixed. Do not claim primary apply.

---

### Task 11: Full Verification and Codex Review Handoff

**Files:**
- Create: `docs/superpowers/readiness/2026-08-23-phase-9-review-report.md`
- Modify: `docs/superpowers/plans/2026-08-21-student-portal-four-role-completion-revised.md`

**Interfaces:**
- Produces: `PHASE_9_GO_FOR_CODEX_REVIEW` or an honest blocking report.

- [x] **Step 1: Run full syntax and formatting gates**

Run PHP lint over every PHP file, all `tests/*.js`, `git diff --check`, migration validate/status, tracked-secret scan, and protected-file diff. Expected: 0 failures; `00700` remains pending.

- [x] **Step 2: Run required regression matrix**

At minimum run Phase 2 Talent Passport, Phase 3 privacy/sharing, Phase 4 activity, Phase 5 check-in, Phase 6 assessment/evaluation, Phase 7 application, Phase 8 notification, School/Teacher/Enterprise ownership, AI rule/shadow sources, all new Phase 9 suites, and the final disposable rehearsal.

- [x] **Step 3: Verify primary invariants**

Primary remains 58 tables, 28 applied migrations, `00700` pending, no badge tables/catalog/awards, unchanged notification/preference counts, unchanged protected migration hashes, and AI visible percent zero.

- [x] **Step 4: Update tracker truthfully**

Mark Phase 9 `GO_FOR_CODEX_REVIEW — PRIMARY_APPLY_PENDING`; do not write `APPROVED_PHASE_9`. Leave Phase 10 untouched.

- [x] **Step 5: Write final report and stop**

The report must include branch/HEAD, changed files, implemented flows, exact tests and counts, disposable DB evidence, primary non-mutation evidence, migration pending state, known risks, proposed post-review primary apply/backfill commands, and confirmation of no commit/push/merge/reset/clean/checkout/stash.

Final console token:

```text
PHASE_9_GO_FOR_CODEX_REVIEW
```

If any required gate remains red, continue fixing within scope. Emit `PHASE_9_NOT_READY` only for a genuine external blocker that cannot be resolved from repository/runtime evidence, and do not apply primary as a workaround.
