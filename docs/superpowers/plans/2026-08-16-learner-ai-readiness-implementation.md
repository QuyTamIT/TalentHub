# Learner AI Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the non-destructive learner data foundation, explainable rule baseline, versioned recommendation store, and safely gated model integration required to deliver production AI recommendations without modifying Teacher, School, or Enterprise code.

**Architecture:** Existing shared tables remain canonical facts. Learner-owned source adapters build a consent-filtered immutable snapshot, both rule and model engines implement the same domain contract, and structured runs/items/evidence/feedback are stored in new additive tables. Database changes use a learner-only forward migration runner and require explicit approval before the DDL file is created or executed.

**Tech Stack:** PHP 8.3, PDO MySQL 8.4/MariaDB-compatible SQL, vanilla JavaScript, Node.js test runner, existing TalentHub session/RBAC/read-model patterns.

## Global Constraints

- Modify application code only under `app/learner/**`, `assets/js/learner*.js`, learner-owned CSS, `tests/learner_*`, and `docs/superpowers/**`.
- Never modify `app/teacher/**`, `app/school/**`, or `app/enterprise/**`.
- Treat `src/**`, `api/**`, shared role assets, and shared role tests as protected; submit a separate approval request if a change becomes unavoidable.
- Every file under `Database/migrations/learner/**` and `Database/seeds/learner/**` is `APPROVAL REQUIRED` before creation and again before execution against a shared database.
- Database migrations are additive, forward-only, idempotent, backward-compatible, and must contain no `DELETE`, `DROP`, `TRUNCATE`, destructive rename, destructive type conversion, or data-rewriting backfill.
- New foreign keys use `ON DELETE RESTRICT` or `NO ACTION`.
- Existing rows are never removed, rewritten, or silently reclassified.
- Operational rollback disables feature flags and new read paths; it never removes schema or data.
- AI never verifies a skill, awards hours, publishes an evaluation, changes a registration, or overwrites a source fact.
- No browser-to-model calls; provider secrets and payloads remain server-side.
- Recommendation inputs exclude email, phone, password data, full birth date, tokens, private CV URLs, and unnecessary names.
- Use TDD for every behavior change and run protected-scope/database safety checks at every checkpoint.

## Runtime Commands

Use these exact executables:

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$node = 'C:\Users\CHI NGUYEN\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe'
```

The MySQL integration suite must run only against a disposable `APP_ENV=test` schema. Never point it at `talenthub_local`.

---

### Task 1: Enforce learner-only scope and non-destructive database policy

**Files:**
- Create: `app/learner/data/Readiness/AiScopePolicy.php`
- Create: `app/learner/tools/ai-scope-audit.php`
- Create: `tests/learner_ai_scope_policy_test.php`
- Create: `docs/superpowers/database-change-requests/README.md`

**Interfaces:**
- Produces: `AiScopePolicy::inspectPaths(array $paths,array $approvedDatabasePaths=[]): array{allowed:bool,forbidden_paths:list<string>,approval_required_paths:list<string>}`
- Produces: `AiScopePolicy::inspectMigrationText(string $sql): list<string>`
- Produces: exit `0` for a clean scope and exit `2` for a policy violation.

- [ ] **Step 1: Write the failing scope-policy test**

```php
$policy = new AiScopePolicy();
ai_assert($policy->inspectPaths(['app/learner/ai/bootstrap.php'])['allowed'], 'learner AI path is allowed');
ai_assert(!$policy->inspectPaths(['app/teacher/index.php'])['allowed'], 'teacher path is forbidden');
ai_assert(!$policy->inspectPaths(['src/Bootstrap/Application.php'])['allowed'], 'shared source path needs approval');
$migrationPath = 'Database/migrations/learner/002_create_ai_input_foundation.php';
ai_assert($policy->inspectPaths([$migrationPath])['approval_required_paths'] === [$migrationPath], 'database path requires approval');
ai_assert($policy->inspectPaths([$migrationPath], [$migrationPath])['allowed'], 'exact approved database path is allowed');
ai_assert($policy->inspectMigrationText('CREATE TABLE learner_x(id CHAR(36))') === [], 'additive DDL is accepted');
ai_assert($policy->inspectMigrationText('DROP TABLE learner_x') === ['DROP'], 'DROP is rejected');
ai_assert($policy->inspectMigrationText('DELETE FROM users') === ['DELETE'], 'DELETE is rejected');
```

- [ ] **Step 2: Run the test and verify it fails because `AiScopePolicy` does not exist**

Run: `& $php tests\learner_ai_scope_policy_test.php`
Expected: non-zero exit with class-not-found output.

- [ ] **Step 3: Implement exact allowlist and destructive-token scanner**

```php
final class AiScopePolicy
{
    private const ALLOWED_PREFIXES = ['app/learner/', 'assets/js/learner', 'tests/learner_', 'docs/superpowers/'];
    private const PROTECTED_PREFIXES = ['app/teacher/', 'app/school/', 'app/enterprise/', 'src/', 'api/'];
    private const APPROVAL_PREFIXES = ['Database/migrations/learner/', 'Database/seeds/learner/'];
    private const FORBIDDEN_SQL = ['DELETE', 'DROP', 'TRUNCATE', 'RENAME'];

    public function inspectPaths(array $paths, array $approvedDatabasePaths = []): array
    {
        $approved = array_fill_keys(array_map([$this, 'normalize'], $approvedDatabasePaths), true);
        $forbidden = [];
        $approvalRequired = [];
        foreach (array_unique(array_map([$this, 'normalize'], $paths)) as $path) {
            if ($this->startsWithAny($path, self::PROTECTED_PREFIXES)) {
                $forbidden[] = $path;
                continue;
            }
            if ($this->startsWithAny($path, self::APPROVAL_PREFIXES)) {
                if (!isset($approved[$path])) $approvalRequired[] = $path;
                continue;
            }
            if (!$this->startsWithAny($path, self::ALLOWED_PREFIXES)) $forbidden[] = $path;
        }
        sort($forbidden);
        sort($approvalRequired);
        return [
            'allowed' => $forbidden === [] && $approvalRequired === [],
            'forbidden_paths' => $forbidden,
            'approval_required_paths' => $approvalRequired,
        ];
    }

    public function inspectMigrationText(string $sql): array
    {
        $withoutComments = preg_replace(['~/\*.*?\*/~s', '~--[^\r\n]*~'], ' ', $sql) ?? $sql;
        $matched = [];
        foreach (self::FORBIDDEN_SQL as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $withoutComments) === 1) $matched[] = $keyword;
        }
        return $matched;
    }

    private function normalize(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), './');
    }

    private function startsWithAny(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) if (str_starts_with($path, $prefix)) return true;
        return false;
    }
}
```

The implementation must strip SQL line/block comments before scanning and match whole keywords case-insensitively.

- [ ] **Step 4: Implement the CLI and Database Change Request contract**

`ai-scope-audit.php` reads `git diff --name-only --cached` plus unstaged/untracked learner plan paths, scans all `Database/migrations/learner/*.{php,sql}`, emits JSON with `allowed`, `forbidden_paths`, and `forbidden_sql`, and never changes the workspace. The README requires exact DDL, ownership matrix, compatibility analysis, backup proof, baseline counts/checksums, repeated-run evidence, post-checks, operational rollback, and a visible `APPROVAL REQUIRED` line.

- [ ] **Step 5: Run verification**

Run: `& $php tests\learner_ai_scope_policy_test.php`
Expected: `learner_ai_scope_policy_test: OK`.

Run: `& $php app\learner\tools\ai-scope-audit.php --format=json`
Expected: JSON; existing unrelated dirty files may be reported but Teacher/School/Enterprise paths must remain absent.

- [ ] **Step 6: Commit**

```powershell
git add app/learner/data/Readiness/AiScopePolicy.php app/learner/tools/ai-scope-audit.php tests/learner_ai_scope_policy_test.php docs/superpowers/database-change-requests/README.md
git commit -m "test(learner): enforce AI scope and database safety"
```

---

### Task 2: Add a learner-owned forward-only migration runner

**Files:**
- Create: `app/learner/data/Migrations/LearnerForwardMigration.php`
- Create: `app/learner/data/Migrations/ForwardMigrationDefinition.php`
- Create: `app/learner/data/Migrations/LearnerForwardMigrationRunner.php`
- Create: `tests/learner_forward_migration_test.php`
- Modify: `app/learner/data/bootstrap.php`

**Interfaces:**
- Produces: `LearnerForwardMigration::version(): string`
- Produces: `LearnerForwardMigration::description(): string`
- Produces: `LearnerForwardMigration::statements(): array`
- Produces: `LearnerForwardMigration::expectedSchema(): array`
- Produces: `LearnerForwardMigrationRunner::status(): array`
- Produces: `LearnerForwardMigrationRunner::migrateApproved(array $approvedVersions): array`

- [ ] **Step 1: Write tests for filename validation, checksum drift, approval, lock, and idempotency**

Use a temporary directory containing `002_create_sample.php` that returns a migration with one SQLite-compatible `CREATE TABLE learner_sample`. Assert:

```php
$runner = new LearnerForwardMigrationRunner($pdo, $directory, new SchemaInspector($pdo, 'main'));
forward_assert($runner->migrateApproved([]) === [], 'unapproved migration does not run');
forward_assert($runner->migrateApproved(['002_create_sample']) === ['002_create_sample'], 'approved migration runs');
forward_assert($runner->migrateApproved(['002_create_sample']) === [], 'second run is a no-op');
forward_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_forward_migrations")->fetchColumn() === 1, 'one registry row');
```

Also change the fixture checksum after applying and assert `RuntimeException('Applied learner migration drift')`.

- [ ] **Step 2: Run the failing test**

Run: `& $php tests\learner_forward_migration_test.php`
Expected: FAIL because the new migration types do not exist.

- [ ] **Step 3: Implement immutable definitions and the migration contract**

```php
interface LearnerForwardMigration
{
    public function version(): string;
    public function description(): string;
    /** @return list<string> */
    public function statements(string $driver): array;
    /** @return array<string,array{columns:list<string>,indexes:list<string>}> */
    public function expectedSchema(): array;
}
```

`ForwardMigrationDefinition` stores version, name, path, checksum, and migration instance as readonly values.

- [ ] **Step 4: Implement runner safety behavior**

The runner must:

1. accept only filenames matching `NNN_lower_snake.php`;
2. acquire MySQL advisory lock `talenthub:learner_forward_migrations` or use the current SQLite transaction;
3. create `learner_forward_migrations(version,name,checksum,description,appliedAt)` only when the first approved migration is run;
4. compare checksums of applied definitions;
5. reject any statement flagged by `AiScopePolicy`;
6. execute only versions present in `$approvedVersions`;
7. validate `expectedSchema()` after execution;
8. record the version only after schema validation;
9. expose no rollback or down method.

- [ ] **Step 5: Wire bootstrap and run tests**

Run: `& $php tests\learner_forward_migration_test.php`
Expected: `learner_forward_migration_test: OK`.

Run: `& $php tests\learner_readiness_test.php`
Expected: `learner_readiness_test: OK`.

- [ ] **Step 6: Commit**

```powershell
git add app/learner/data/Migrations app/learner/data/bootstrap.php tests/learner_forward_migration_test.php
git commit -m "feat(learner): add forward-only migration runner"
```

---

### Task 3: Prepare and approve the canonical AI-input database change

**Files:**
- Create: `docs/superpowers/database-change-requests/2026-08-16-ai-input-foundation.md`
- Create after approval: `Database/migrations/learner/002_create_ai_input_foundation.php`
- Create: `tests/learner_ai_input_schema_test.php`
- Modify: `app/learner/data/Readiness/PhaseRequirements.php`

**Interfaces:**
- Produces canonical tables: `skills`, `student_skills`, `talent_tests`, `test_questions`, `test_attempts`, `test_results`, `privacy_consents`, `activity_qr_tokens`, `checkins`, `experience_logs`.
- Consumes existing: `student_profiles`, `activities`, `activity_registrations`.

- [ ] **Step 1: Write the schema test before creating DDL**

The test uses `SchemaInspector` and asserts every table, required column, unique index, and FK-facing index. It must also scan the migration source and assert no destructive keyword.

- [ ] **Step 2: Generate the Database Change Request and stop**

The request must include current live-table inventory, exact row counts, existing query compatibility, the `learner_forward_migrations` registry contract, and the complete proposed DDL. Mark it:

```markdown
## Approval Gate

APPROVAL REQUIRED: do not create or run migration 002 until the user explicitly approves this exact DDL.
```

- [ ] **Step 3: Wait for explicit user approval**

Do not create `002_create_ai_input_foundation.php`, do not run a migration command, and do not write to the shared database before approval.

- [ ] **Step 4: After approval, create the migration with these non-destructive table contracts**

The migration returns `LearnerForwardMigration` and emits `CREATE TABLE IF NOT EXISTS` statements with these exact required fields:

```text
skills(id, code UNIQUE, name, category, status, createdAt, updatedAt)
student_skills(id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt, UNIQUE(studentId,skillId,sourceType))
talent_tests(id, code UNIQUE, name, type, status, createdAt, updatedAt)
test_questions(id, testId, code, content, optionsJson, status, createdAt, updatedAt, UNIQUE(testId,code))
test_attempts(id, testId, studentId, status, startedAt, submittedAt, createdAt, updatedAt)
test_results(id, attemptId UNIQUE, resultCode, summary, dimensionScoresJson, scoringVersion, createdAt)
privacy_consents(id, studentId, scope, isGranted, policyVersion, grantedAt, revokedAt, createdAt, UNIQUE(studentId,scope,policyVersion,createdAt))
activity_qr_tokens(id, activityId, tokenHash UNIQUE, validFrom, validUntil, status, createdAt)
checkins(id, registrationId UNIQUE, qrTokenId, status, checkedInAt, confirmedAt, createdAt)
experience_logs(id, studentId, activityId, checkinId UNIQUE, hours, status, auditReason, confirmedAt, createdAt)
```

All UUIDs are `CHAR(36)`, JSON payloads are `LONGTEXT` plus `JSON_VALID` checks, time values are `DATETIME(6)`, new FKs are `ON DELETE RESTRICT ON UPDATE CASCADE`, and status columns have explicit CHECK allowlists. If any table already exists, preflight must require compatible columns and indexes instead of silently accepting a different shape.

- [ ] **Step 5: Run static and disposable-schema tests only**

Run: `& $php tests\learner_ai_scope_policy_test.php`
Expected: PASS.

Run against disposable test DB: `& $php tests\learner_ai_input_schema_test.php`
Expected: PASS and second migration run reports no applied versions.

- [ ] **Step 6: Capture approval before shared database execution**

Present the DCR, disposable-schema output, pre-migration schema hash, backup proof, and row-count baseline. Stop again. Only after approval may the exact migration version be passed to `migrateApproved()`.

- [ ] **Step 7: Verify the approved shared execution without changing existing rows**

Expected evidence:

```text
approved version: 002_create_ai_input_foundation
existing-table row counts before == after
foreign key check violations: 0
second run applied versions: []
protected role smoke checks: PASS
```

- [ ] **Step 8: Commit migration and approval evidence**

```powershell
git add docs/superpowers/database-change-requests/2026-08-16-ai-input-foundation.md Database/migrations/learner/002_create_ai_input_foundation.php tests/learner_ai_input_schema_test.php app/learner/data/Readiness/PhaseRequirements.php
git commit -m "feat(learner): add approved AI input schema"
```

---

### Task 4: Add assessment versioning, answers, evidence, and append-only consent events

**Files:**
- Create: `docs/superpowers/database-change-requests/2026-08-16-ai-input-extensions.md`
- Create after approval: `Database/migrations/learner/003_create_ai_input_extensions.php`
- Create: `tests/learner_ai_input_extensions_schema_test.php`

**Interfaces:**
- Produces: `learner_assessment_versions`
- Produces: `learner_assessment_question_versions`
- Produces: `learner_assessment_attempt_metadata`
- Produces: `learner_assessment_answers`
- Produces: `learner_skill_evidence`
- Produces: `learner_ai_consent_events`

- [ ] **Step 1: Write failing schema and immutability tests**

Assert unique `(testId,version)`, unique `(versionId,questionId)`, unique `(attemptId,questionId)`, and append-only consent event ordering. Assert all source FKs use RESTRICT/NO ACTION.

- [ ] **Step 2: Create the DCR and stop for approval**

The DCR must prove that extension tables avoid `ALTER` on existing canonical tables and that old role queries remain unchanged.

- [ ] **Step 3: After approval, implement exact table contracts**

```text
learner_assessment_versions(id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt, UNIQUE(testId,version))
learner_assessment_question_versions(id, versionId, questionId, position, dimensionCode, required, createdAt, UNIQUE(versionId,questionId), UNIQUE(versionId,position))
learner_assessment_attempt_metadata(id, attemptId UNIQUE, versionId, status, expiresAt, submittedAt, inputHash, createdAt, updatedAt)
learner_assessment_answers(id, attemptId, questionId, answerJson, answeredAt, UNIQUE(attemptId,questionId))
learner_skill_evidence(id, studentSkillId, evidenceType, evidenceRef, verificationStatus, observedAt, createdAt)
learner_ai_consent_events(id, studentId, scope, action, policyVersion, occurredAt, requestId, UNIQUE(studentId,scope,occurredAt,requestId))
```

Use CHECK constraints for assessment status `draft|published|retired`, attempt status `in_progress|submitted|expired`, consent action `granted|revoked`, and verification status `self_declared|pending|verified|rejected`.

- [ ] **Step 4: Run disposable-schema tests twice**

Run: `& $php tests\learner_ai_input_extensions_schema_test.php`
Expected: PASS, no existing row-count delta, second run no-op.

- [ ] **Step 5: Stop for shared execution approval, then verify**

Require the same backup, hash, count, FK, repeated-run, and protected-role evidence as Task 3.

- [ ] **Step 6: Commit**

```powershell
git add docs/superpowers/database-change-requests/2026-08-16-ai-input-extensions.md Database/migrations/learner/003_create_ai_input_extensions.php tests/learner_ai_input_extensions_schema_test.php
git commit -m "feat(learner): add approved versioned input extensions"
```

---

### Task 5: Implement versioned assessment persistence in learner code

**Files:**
- Create: `app/learner/data/Contracts/AssessmentWriteRepository.php`
- Create: `app/learner/data/Database/DatabaseAssessmentWriteRepository.php`
- Create: `app/learner/data/Service/LearnerAssessmentService.php`
- Modify: `app/learner/data/RepositoryFactory.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/includes/assessment-data.php`
- Modify: `assets/js/learner-assessment.js`
- Create: `tests/learner_assessment_persistence_test.php`
- Modify: `tests/learner_holland_ui_test.js`

**Interfaces:**
- Produces: `start(string $studentId,string $testId,string $version): array`
- Produces: `saveAnswer(string $studentId,string $attemptId,string $questionId,mixed $answer): array`
- Produces: `submit(string $studentId,string $attemptId): array`

- [ ] **Step 1: Write ownership, version, idempotency, and immutable-submit tests**

Assert a second learner cannot read/write the attempt, a submitted attempt cannot accept new answers, the same submit request returns the existing result, and result rows retain `scoringVersion` and input hash.

- [ ] **Step 2: Run focused tests and confirm failure**

Run: `& $php tests\learner_assessment_persistence_test.php`
Expected: FAIL because write contracts do not exist.

- [ ] **Step 3: Define the write contract and transactional repository**

```php
interface AssessmentWriteRepository
{
    public function startAttempt(string $studentId, string $testId, string $version): array;
    public function saveAnswer(string $studentId, string $attemptId, string $questionId, mixed $answer): array;
    public function submitAttempt(string $studentId, string $attemptId): array;
}
```

Every update query includes `studentId` ownership and current status in its predicate. Submission locks the attempt and answers, calculates the approved scoring version, writes one result, and marks metadata submitted in one transaction.

- [ ] **Step 4: Replace browser-only persistence with API-confirmed state**

Keep localStorage only as a recoverable draft cache. The canonical submitted result comes from the database response and includes assessment/version IDs.

- [ ] **Step 5: Verify**

Run: `& $php tests\learner_assessment_persistence_test.php`
Expected: PASS.

Run: `& $node --test tests\learner_holland_ui_test.js`
Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add app/learner/data app/learner/includes/assessment-data.php assets/js/learner-assessment.js tests/learner_assessment_persistence_test.php tests/learner_holland_ui_test.js
git commit -m "feat(learner): persist versioned assessment results"
```

---

### Task 6: Implement learner skills, consent, check-in, experience, and published-evaluation sources

**Files:**
- Create: `app/learner/ai/Sources/StudentProfileSource.php`
- Create: `app/learner/ai/Sources/SkillSource.php`
- Create: `app/learner/ai/Sources/AssessmentSource.php`
- Create: `app/learner/ai/Sources/ActivityExperienceSource.php`
- Create: `app/learner/ai/Sources/PublishedEvaluationSource.php`
- Create: `app/learner/ai/Sources/OpportunitySource.php`
- Create: `app/learner/ai/Sources/ConsentSource.php`
- Create: `app/learner/ai/Sources/Database/DatabaseStudentProfileSource.php`
- Create: `app/learner/ai/Sources/Database/DatabaseSkillSource.php`
- Create: `app/learner/ai/Sources/Database/DatabaseAssessmentSource.php`
- Create: `app/learner/ai/Sources/Database/DatabaseActivityExperienceSource.php`
- Create: `app/learner/ai/Sources/Database/DatabasePublishedEvaluationSource.php`
- Create: `app/learner/ai/Sources/Database/DatabaseOpportunitySource.php`
- Create: `app/learner/ai/Sources/Database/DatabaseConsentSource.php`
- Create: `app/learner/ai/Consent/ConsentPolicy.php`
- Create: `app/learner/ai/bootstrap.php`
- Create: `tests/learner_ai_sources_test.php`

**Interfaces:**
- Produces each source method `forStudent(string $studentId): array`.
- Produces: `ConsentPolicy::allowedScopes(string $studentId): array`.

- [ ] **Step 1: Write source contract tests**

Fixtures include two learners, draft/published evaluations, inactive/active opportunities, self-declared/verified skills, pending/confirmed experience, and granted/revoked scopes. Assert only owned, published, active, verified-state-aware, and consented data is returned.

- [ ] **Step 2: Run and confirm failure**

Run: `& $php tests\learner_ai_sources_test.php`
Expected: FAIL because source interfaces are missing.

- [ ] **Step 3: Implement focused read adapters**

Each SQL query binds `studentId`, uses a fixed table/column list, excludes direct identifiers, and maps timestamps to RFC 3339 strings. `PublishedEvaluationSource` requires `assessments.status='published'` and non-null `publishedAt`. `ActivityExperienceSource` exposes hours only when both check-in and experience statuses are confirmed.

- [ ] **Step 4: Implement consent resolution**

Resolve the latest append-only event per `(studentId,scope)`. Return only scopes whose latest action is `granted`; do not rely on client-supplied consent.

- [ ] **Step 5: Verify and commit**

Run: `& $php tests\learner_ai_sources_test.php`
Expected: PASS.

```powershell
git add app/learner/ai tests/learner_ai_sources_test.php
git commit -m "feat(learner): add consent-aware recommendation sources"
```

---

### Task 7: Build immutable snapshots and the data-quality gate

**Files:**
- Create: `app/learner/ai/Domain/RecommendationInput.php`
- Create: `app/learner/ai/Domain/RecommendationContext.php`
- Create: `app/learner/ai/Snapshot/RecommendationSnapshotBuilder.php`
- Create: `app/learner/ai/Quality/DataQualityResult.php`
- Create: `app/learner/ai/Quality/DataQualityGate.php`
- Create: `tests/learner_ai_snapshot_test.php`

**Interfaces:**
- Produces: `RecommendationSnapshotBuilder::build(string $studentId,array $allowedScopes): RecommendationInput`
- Produces: `DataQualityGate::evaluate(RecommendationInput $input): DataQualityResult`

- [ ] **Step 1: Write deterministic hash and minimization tests**

Assert the same normalized inputs in different database row order produce the same SHA-256 hash; a source timestamp/value change produces a different hash; JSON contains no email, phone, birth date, name, token, password, or CV URL keys.

- [ ] **Step 2: Write data-quality cases**

The first rule baseline requires:

```php
[
    'assessment' => ['submitted_count' => 1, 'max_age_days' => 365],
    'skills' => ['minimum_count' => 2],
    'experience' => ['confirmed_activity_count' => 1],
    'evaluations' => ['published_count' => 1],
]
```

Return `insufficient_data` with safe completion actions for every missing category. A revoked scope returns `consent_required`, not `insufficient_data`.

- [ ] **Step 3: Implement immutable value objects and canonical JSON**

`RecommendationInput` stores schema version `1.0`, content hash, source timestamps, quality flags, evidence references, and minimized domain values. It exposes no mutator.

- [ ] **Step 4: Run and commit**

Run: `& $php tests\learner_ai_snapshot_test.php`
Expected: PASS.

```powershell
git add app/learner/ai/Domain app/learner/ai/Snapshot app/learner/ai/Quality tests/learner_ai_snapshot_test.php
git commit -m "feat(learner): build versioned recommendation snapshots"
```

---

### Task 8: Prepare and approve the recommendation-store schema

**Files:**
- Create: `docs/superpowers/database-change-requests/2026-08-16-recommendation-store.md`
- Create after approval: `Database/migrations/learner/004_create_recommendation_store.php`
- Create: `tests/learner_recommendation_schema_test.php`

**Interfaces:**
- Produces six `learner_recommendation_*` tables consumed by Task 9.

- [ ] **Step 1: Write the failing schema test**

Assert required columns, ownership indexes, idempotency unique keys, evidence links, append-only feedback/audit, RESTRICT FKs, and zero destructive tokens.

- [ ] **Step 2: Create DCR and stop for approval**

Include exact DDL, estimated storage, index cost, compatibility proof, and operational rollback. Do not create or execute migration 004 before approval.

- [ ] **Step 3: After approval, implement exact contracts**

```text
learner_recommendation_input_snapshots(id, studentId, schemaVersion, contentHash, consentScopesJson, qualityFlagsJson, payloadJson, sourceUpdatedAt, createdAt, UNIQUE(studentId,contentHash))
learner_recommendation_runs(id, studentId, snapshotId, idempotencyKey, engineType, status, ruleVersion, provider, modelVersion, promptVersion, fallbackReason, safeErrorCode, startedAt, completedAt, createdAt, UNIQUE(studentId,idempotencyKey))
learner_recommendation_items(id, runId, itemType, title, summary, priority, confidenceBand, actionJson, lifecycleStatus, createdAt)
learner_recommendation_evidence(id, itemId, sourceType, sourceId, observedAt, contributionLabel, safeValueJson, createdAt, UNIQUE(itemId,sourceType,sourceId))
learner_recommendation_feedback(id, studentId, itemId, verdict, reasonCode, safeComment, createdAt)
learner_recommendation_audit_events(id, runId, studentId, requestId, actorType, action, engineMetadataJson, status, createdAt)
```

Allowlist values: engine `rule|model`; run status `pending|completed|failed|fallback`; item type `strength|improvement|development|activity|roadmap`; feedback verdict `helpful|not_helpful|not_relevant|unsafe`; lifecycle `active|superseded`.

- [ ] **Step 4: Run disposable schema twice, stop for shared execution approval, then verify**

Run: `& $php tests\learner_recommendation_schema_test.php`
Expected: PASS, second migration no-op, old row counts unchanged.

- [ ] **Step 5: Commit after approved execution evidence is recorded**

```powershell
git add docs/superpowers/database-change-requests/2026-08-16-recommendation-store.md Database/migrations/learner/004_create_recommendation_store.php tests/learner_recommendation_schema_test.php
git commit -m "feat(learner): add approved recommendation store"
```

---

### Task 9: Implement transactional recommendation persistence

**Files:**
- Create: `app/learner/ai/Persistence/RecommendationRepository.php`
- Create: `app/learner/ai/Persistence/DatabaseRecommendationRepository.php`
- Create: `app/learner/ai/Domain/RecommendationItem.php`
- Create: `app/learner/ai/Domain/RecommendationEvidence.php`
- Create: `app/learner/ai/Domain/RecommendationResult.php`
- Create: `tests/learner_recommendation_repository_test.php`

**Interfaces:**
- Produces: `createPendingRun(string $studentId,RecommendationInput $input,RecommendationContext $context): array`.
- Produces: `completeRun(string $studentId,string $runId,RecommendationResult $result): array`.
- Produces: `failRun(string $studentId,string $runId,string $safeErrorCode): void`.
- Produces: `latestForStudent(string $studentId): ?array`.
- Produces: `appendFeedback(string $studentId,string $itemId,string $verdict,string $reasonCode,?string $safeComment): array`.

- [ ] **Step 1: Write transaction, idempotency, ownership, and append-only tests**

Force an evidence insert failure and assert no run/item remains. Assert repeated idempotency key returns the existing run. Assert learner B cannot load or feedback on learner A's item.

- [ ] **Step 2: Run failing tests**

Run: `& $php tests\learner_recommendation_repository_test.php`
Expected: FAIL because repository types are absent.

- [ ] **Step 3: Implement the repository**

```php
interface RecommendationRepository
{
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array;
    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array;
    public function failRun(string $studentId, string $runId, string $safeErrorCode): void;
    public function latestForStudent(string $studentId): ?array;
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array;
}
```

All selects and mutations include `studentId`; completion inserts item/evidence/audit records and updates run status in one transaction.

- [ ] **Step 4: Verify and commit**

Run: `& $php tests\learner_recommendation_repository_test.php`
Expected: PASS.

```powershell
git add app/learner/ai/Persistence app/learner/ai/Domain tests/learner_recommendation_repository_test.php
git commit -m "feat(learner): persist recommendation evidence and feedback"
```

---

### Task 10: Implement versioned rule recommendation baseline

**Files:**
- Create: `app/learner/ai/Contracts/RecommendationEngine.php`
- Create: `app/learner/ai/Rules/RuleDefinition.php`
- Create: `app/learner/ai/Rules/RuleSetV1.php`
- Create: `app/learner/ai/Rules/RuleRecommendationEngine.php`
- Create: `app/learner/ai/Explanation/RecommendationExplainer.php`
- Create: `tests/learner_rule_recommendation_test.php`
- Create: `tests/learner_ai_rule_cases_fixture.php`

**Interfaces:**
- Produces: `RecommendationEngine::generate(RecommendationInput $input,RecommendationContext $context): RecommendationResult`.
- Produces rule version `learner-rules-1.0.0`.

- [ ] **Step 1: Write exact golden cases**

Include at least:

1. Holland R/I high + verified IoT + confirmed technical activity -> technical strength and eligible IoT activity.
2. Repeated low presentation scores -> communication improvement roadmap.
3. Closed/inactive activity -> never recommended.
4. Missing evaluation -> `insufficient_data`, no speculative output.
5. Revoked activity scope -> no activity evidence or recommendation.
6. Score ties -> deterministic order by rule priority then stable source ID.

- [ ] **Step 2: Run and verify failure**

Run: `& $php tests\learner_rule_recommendation_test.php`
Expected: FAIL because engine is absent.

- [ ] **Step 3: Implement the interface and immutable rule definitions**

```php
interface RecommendationEngine
{
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult;
}
```

Each `RuleDefinition` declares ID, version, required scopes, predicate, item builder, priority, and evidence mapper. Rules never query PDO and never inspect presentation arrays.

- [ ] **Step 4: Implement explanation output**

Each item must include at least one evidence record and a safe explanation such as: `Dựa trên kết quả Holland phiên bản 1.0 ngày 15/06/2026 và kỹ năng IoT đã xác minh.` The explainer receives normalized evidence only.

- [ ] **Step 5: Verify and commit**

Run: `& $php tests\learner_rule_recommendation_test.php`
Expected: all golden cases PASS.

```powershell
git add app/learner/ai/Contracts app/learner/ai/Rules app/learner/ai/Explanation tests/learner_rule_recommendation_test.php tests/learner_ai_rule_cases_fixture.php
git commit -m "feat(learner): add explainable recommendation baseline"
```

---

### Task 11: Add orchestration, output validation, and safe fallback states

**Files:**
- Create: `app/learner/ai/Validation/RecommendationResultValidator.php`
- Create: `app/learner/ai/Service/RecommendationService.php`
- Create: `app/learner/ai/Service/RecommendationResponseMapper.php`
- Create: `tests/learner_recommendation_service_test.php`

**Interfaces:**
- Produces: `RecommendationService::latest(string $studentId): ?array`
- Produces: `RecommendationService::generate(string $studentId,string $requestId,string $idempotencyKey): array`

- [ ] **Step 1: Write service tests for all public states**

Assert `consent_required`, `insufficient_data`, `source_unavailable`, completed rule result, duplicate request reuse, engine failure, and stale snapshot. Assert no failed path silently loads mock data.

- [ ] **Step 2: Run failing tests**

Run: `& $php tests\learner_recommendation_service_test.php`
Expected: FAIL because service is absent.

- [ ] **Step 3: Implement orchestration order**

```text
authorize -> resolve consent -> build snapshot -> quality gate -> create pending run
-> engine generate -> validate -> complete transaction -> map response
```

Validator requirements: known item types, priority 1-100, confidence band `low|medium|high`, non-empty evidence, allowed action schema, maximum 12 items, maximum 3 roadmap steps, and no unsupported absolute career/admission/hiring claim.

- [ ] **Step 4: Verify and commit**

Run: `& $php tests\learner_recommendation_service_test.php`
Expected: PASS.

```powershell
git add app/learner/ai/Validation app/learner/ai/Service tests/learner_recommendation_service_test.php
git commit -m "feat(learner): orchestrate safe recommendation generation"
```

---

### Task 12: Expose learner-owned authenticated endpoints

**Files:**
- Create: `app/learner/api/LearnerApiContext.php`
- Create: `app/learner/api/JsonResponder.php`
- Create: `app/learner/api/v1/recommendations.php`
- Create: `app/learner/api/v1/recommendation-feedback.php`
- Create: `app/learner/api/v1/ai-consent.php`
- Modify: `assets/js/learner-api.js`
- Create: `tests/learner_recommendation_api_test.php`
- Modify: `tests/learner_api_client_test.js`

**Interfaces:**
- `GET recommendations.php` -> latest state.
- `POST recommendations.php` -> idempotent generation.
- `POST recommendation-feedback.php` -> append feedback.
- `GET|POST ai-consent.php` -> read or append grant/revoke event.

- [ ] **Step 1: Write authentication, CSRF, ownership, and envelope tests**

Require 401 without session, 403 wrong role, 403 missing permission, 403 with code `CSRF_INVALID` for invalid CSRF on mutations, 422 validation errors, and request ID in every response. Client-supplied `studentId` must be ignored/rejected.

- [ ] **Step 2: Write JS client test for the learner-local base**

```javascript
const client = createLearnerApiClient({ baseUrl: '/app/learner/api/v1', fetchImpl });
await client.get('/recommendations.php');
assert.equal(requestUrl, '/app/learner/api/v1/recommendations.php');
```

The client accepts only `/api/v1` or `/app/learner/api/v1`; all external/traversal bases remain rejected.

- [ ] **Step 3: Implement learner-local API context**

Reuse existing `bin/bootstrap.php`, `Connection`, `SessionManager`, `AuthService`, and `PermissionService` without modifying them. Resolve `student_profiles.id` from the authenticated user. Do not duplicate passwords, tokens, or DB configuration.

- [ ] **Step 4: Implement endpoints and safe JSON mapping**

Mutations require `X-CSRF-Token`; generation requires `X-Idempotency-Key` matching `^[A-Za-z0-9_-]{16,100}$`; feedback comments are optional and limited to 500 characters.

- [ ] **Step 5: Verify and commit**

Run: `& $php tests\learner_recommendation_api_test.php`
Expected: PASS.

Run: `& $node --test tests\learner_api_client_test.js`
Expected: PASS.

```powershell
git add app/learner/api assets/js/learner-api.js tests/learner_recommendation_api_test.php tests/learner_api_client_test.js
git commit -m "feat(learner): expose recommendation APIs"
```

---

### Task 13: Replace hard-coded AI page data with API states and evidence UI

**Files:**
- Modify: `app/learner/ai-recommendations.php`
- Modify: `app/learner/includes/student-data.php`
- Create: `assets/js/learner-recommendations.js`
- Modify: `assets/css/learner.css`
- Create: `tests/learner_ai_recommendation_render_test.php`
- Create: `tests/learner_ai_recommendation_ui_test.js`

**Interfaces:**
- Consumes the stable response mapper from Task 11.
- Produces UI states: loading, consent-required, insufficient-data, source-error, ready-rule, ready-model, fallback-rule, and feedback-saved.

- [ ] **Step 1: Write render tests proving hard-coded claims are gone**

Assert the PHP source no longer contains the fixed IoT/Drone summary or fixed three-month roadmap. Assert semantic containers and live regions exist for every state.

- [ ] **Step 2: Write JS state-machine tests**

Mock API responses and assert exact state transitions, retry behavior, idempotency key reuse during one request, evidence expansion, feedback submission, and accessible focus movement.

- [ ] **Step 3: Implement progressive rendering**

Server renders a safe empty shell and disclaimer. JS fetches latest, requests generation only on explicit action or approved first-load policy, and inserts text with `textContent`, never untrusted `innerHTML`.

- [ ] **Step 4: Implement evidence and engine labels**

Each item shows safe explanation, source date, and `Rule baseline` or approved model label. Never display raw prompt, snapshot JSON, provider payload, internal error details, or private source IDs.

- [ ] **Step 5: Verify and commit**

Run: `& $php tests\learner_ai_recommendation_render_test.php`
Expected: PASS.

Run: `& $node --test tests\learner_ai_recommendation_ui_test.js`
Expected: PASS.

```powershell
git add app/learner/ai-recommendations.php app/learner/includes/student-data.php assets/js/learner-recommendations.js assets/css/learner.css tests/learner_ai_recommendation_render_test.php tests/learner_ai_recommendation_ui_test.js
git commit -m "feat(learner): render evidence-backed recommendations"
```

---

### Task 14: Load an approved, de-identified staging pilot dataset

**Files:**
- Create: `docs/superpowers/database-change-requests/2026-08-16-ai-pilot-data.md`
- Create after approval: `Database/seeds/learner/Staging/LearnerAiPilotSeeder.php`
- Create: `tests/learner_ai_pilot_seed_test.php`

**Interfaces:**
- Produces idempotent inserts only; no update/delete cleanup function.

- [ ] **Step 1: Define minimum pilot records in the DCR**

For at least two synthetic staging learners, require: two skills, one submitted assessment version with answers/result, one confirmed activity/check-in/experience record, one published teacher evaluation, and explicit scopes for assessment/skills/activity/evaluation personalization. Use reserved `00000000-0000-4000-8000-*` test UUIDs and `.example` emails only.

- [ ] **Step 2: Stop for approval before seed creation or execution**

The DCR lists every inserted row and proves no production personal data is included.

- [ ] **Step 3: Implement insert-only idempotent seeding**

Each insert uses `INSERT ... SELECT ... WHERE NOT EXISTS`; encountering an existing reserved ID with different content fails loudly. Do not use `REPLACE`, `ON DUPLICATE KEY UPDATE`, `DELETE`, or cleanup SQL.

- [ ] **Step 4: Run only on disposable/staging DB after approval**

Run the seed twice. Expected: first run inserts the declared records; second run inserts zero; existing row counts outside reserved pilot IDs are unchanged.

- [ ] **Step 5: Run the end-to-end rule flow and commit**

Expected: each pilot learner produces a deterministic rule run with evidence and no cross-learner access.

After synthetic fixtures pass, onboard real pilot participants through the learner UI/API workflows only. Require explicit pilot consent, staging accounts, and verified Teacher/School-owned source records. Do not bulk-insert real personal data or copy production records into staging. Record only aggregate onboarding counts in the DCR evidence.

```powershell
git add docs/superpowers/database-change-requests/2026-08-16-ai-pilot-data.md Database/seeds/learner/Staging/LearnerAiPilotSeeder.php tests/learner_ai_pilot_seed_test.php
git commit -m "test(learner): add approved AI pilot fixtures"
```

---

### Task 15: Add provider-independent model infrastructure

**Files:**
- Create: `app/learner/ai/Contracts/RecommendationProvider.php`
- Create: `app/learner/ai/Provider/ProviderRequest.php`
- Create: `app/learner/ai/Provider/ProviderResponse.php`
- Create: `app/learner/ai/Provider/FakeRecommendationProvider.php`
- Create: `app/learner/ai/Provider/HttpRecommendationProvider.php`
- Create: `app/learner/ai/Model/PromptRegistry.php`
- Create: `app/learner/ai/Model/ModelRecommendationEngine.php`
- Create: `app/learner/ai/RateLimit/RecommendationRateLimiter.php`
- Create: `app/learner/ai/Config/RecommendationConfig.php`
- Create: `docs/superpowers/readiness/learner-ai-provider-config.md`
- Create: `tests/learner_ai_provider_test.php`

**Interfaces:**
- Produces: `RecommendationProvider::generate(ProviderRequest $request): ProviderResponse`.
- Consumes the same `RecommendationEngine` contract as rules.

- [ ] **Step 1: Write fake-provider contract tests**

Cover success, 2-second configured timeout, one bounded retry for transient 502/503 only, 429 retry-after without busy-loop, malformed JSON, unsafe content, and provider outage. Assert every failure returns a typed internal error and the service selects the rule fallback.

- [ ] **Step 2: Write secret/config tests**

Require `TALENTHUB_AI_ENABLED=false` by default. When enabled, require provider, model, API URL allowlist, API key, timeout 1-10 seconds, and maximum attempts 1-2. Diagnostics expose only enabled/provider/model/timeout, never key or prompt.

- [ ] **Step 3: Implement provider contract and prompt registry**

```php
interface RecommendationProvider
{
    public function generate(ProviderRequest $request): ProviderResponse;
}
```

Prompt version `learner-recommendation-1.0.0` requires JSON-only structured items with evidence reference IDs drawn exclusively from the supplied snapshot. The model is instructed not to infer diagnoses, protected traits, admissions outcomes, or hiring outcomes.

- [ ] **Step 4: Implement server-side HTTP and rate limiting**

Use an injected HTTP callable in tests. Validate provider URL against configured HTTPS host. Apply per-student and global buckets. Never log authorization headers, API keys, raw minimized snapshots, or full provider response bodies.

- [ ] **Step 5: Verify with fake provider only**

Run: `& $php tests\learner_ai_provider_test.php`
Expected: PASS with no network calls.

- [ ] **Step 6: Commit**

```powershell
git add app/learner/ai docs/superpowers/readiness/learner-ai-provider-config.md tests/learner_ai_provider_test.php
git commit -m "feat(learner): add gated recommendation provider"
```

---

### Task 16: Implement shadow mode and evaluation metrics

**Files:**
- Create: `app/learner/ai/Evaluation/RecommendationEvaluator.php`
- Create: `app/learner/ai/Evaluation/ShadowRunService.php`
- Create: `app/learner/tools/ai-evaluate.php`
- Create: `tests/learner_ai_evaluation_test.php`
- Create: `docs/superpowers/readiness/learner-ai-evaluation-gate.md`

**Interfaces:**
- Produces metrics: schema validity, evidence coverage, unsupported-claim rate, rule disagreement, unsafe-output rate, latency p50/p95, fallback rate, and estimated cost.

- [ ] **Step 1: Write evaluation tests**

Use fixed de-identified fixtures. Assert invalid evidence reference, absolute career claim, hidden source use, and unsafe advice each fail the evaluation. Assert groups smaller than the configured minimum sample size are reported `insufficient_sample` rather than scored for bias.

- [ ] **Step 2: Implement shadow execution**

Shadow mode receives the same snapshot as the visible rule run, persists model runs with `engineType=model`, never selects them for learner UI, and records safe comparison metrics.

- [ ] **Step 3: Implement release thresholds**

```text
schema validity = 100%
evidence coverage = 100%
unsupported claim rate = 0%
unsafe output rate = 0%
provider fallback behavior = 100% of simulated failures
p95 latency <= approved product threshold
cost per run <= approved budget
```

Latency and cost thresholds remain disabled until the user records exact approved values in the gate document; model-visible output must stay false until then. This is a release gate, not an implementation placeholder.

- [ ] **Step 4: Run evaluation with fake/shadow provider**

Run: `& $php app\learner\tools\ai-evaluate.php --fixture=tests/learner_ai_rule_cases_fixture.php --format=json`
Expected: JSON metrics and `eligible_for_visible_rollout=false` until all approvals exist.

- [ ] **Step 5: Commit**

```powershell
git add app/learner/ai/Evaluation app/learner/tools/ai-evaluate.php tests/learner_ai_evaluation_test.php docs/superpowers/readiness/learner-ai-evaluation-gate.md
git commit -m "test(learner): add AI shadow evaluation gate"
```

---

### Task 17: Controlled model rollout with feature flags and rule fallback

**Files:**
- Modify: `app/learner/ai/Config/RecommendationConfig.php`
- Modify: `app/learner/ai/Service/RecommendationService.php`
- Modify: `app/learner/ai-recommendations.php`
- Modify: `assets/js/learner-recommendations.js`
- Create: `tests/learner_ai_rollout_test.php`
- Create: `docs/superpowers/readiness/learner-ai-release-checklist.md`

**Interfaces:**
- Consumes flags: `TALENTHUB_AI_ENABLED`, `TALENTHUB_AI_SHADOW`, `TALENTHUB_AI_VISIBLE_PERCENT`, `TALENTHUB_AI_PROVIDER`.
- Produces deterministic pilot assignment based on student UUID hash.

- [ ] **Step 1: Write rollout tests**

Assert default visible percent is `0`, non-pilot learners always see rules, pilot assignment is stable, provider failure returns the completed rule run, revoked consent disables both shadow and visible model runs, and turning flags off requires no database change.

- [ ] **Step 2: Implement rollout selection**

Visible model selection requires all of: AI enabled, shadow gate approved, visible percent > 0, learner in deterministic pilot, valid consent, current snapshot, validated model output, and completed rule fallback.

- [ ] **Step 3: Add transparent learner label**

Show model/rule label, generated time, evidence, disclaimer, and a report-unsafe action. Never present confidence as probability of career success.

- [ ] **Step 4: Run focused and full learner tests**

Run: `& $php tests\learner_ai_rollout_test.php`
Expected: PASS.

Run every `tests\learner_*_test.php` except MySQL integration under local mode and every `tests\learner_*_test.js`. Expected: PASS.

- [ ] **Step 5: Stop for explicit model-visible approval**

Present shadow metrics, safety/bias review, cost/latency thresholds, fallback evidence, and release checklist. Do not set visible percent above `0` without approval.

- [ ] **Step 6: Commit code with visibility still disabled by default**

```powershell
git add app/learner/ai app/learner/ai-recommendations.php assets/js/learner-recommendations.js tests/learner_ai_rollout_test.php docs/superpowers/readiness/learner-ai-release-checklist.md
git commit -m "feat(learner): gate AI recommendation rollout"
```

---

### Task 18: Final cross-role and database release gate

**Files:**
- Create: `tests/learner_ai_end_to_end_mysql_test.php`
- Modify: `docs/superpowers/readiness/learner-ai-release-checklist.md`

**Interfaces:**
- Produces the final `READY` or `NOT_READY` release decision; never mutates production data.

- [ ] **Step 1: Write end-to-end disposable-DB test**

Cover authenticated learner -> consent -> snapshot -> rule generation -> evidence -> feedback -> fake model shadow -> provider failure fallback. Use two learners and assert isolation at every endpoint.

- [ ] **Step 2: Run full syntax and unit verification**

```powershell
Get-ChildItem app\learner,tests -Recurse -File -Filter *.php |
  Where-Object { $_.FullName -like '*\app\learner\*' -or $_.Name -like 'learner_*_test.php' } |
  ForEach-Object { & $php -l $_.FullName; if ($LASTEXITCODE -ne 0) { exit 1 } }

Get-ChildItem tests\learner_*_test.php | Sort-Object Name |
  Where-Object { $_.Name -ne 'learner_ai_end_to_end_mysql_test.php' -and $_.Name -ne 'learner_foundation_mysql_test.php' } |
  ForEach-Object { & $php $_.FullName; if ($LASTEXITCODE -ne 0) { exit 1 } }

Get-ChildItem tests\learner_*_test.js | Sort-Object Name |
  ForEach-Object { & $node --test $_.FullName; if ($LASTEXITCODE -ne 0) { exit 1 } }
```

Expected: all lint and unit tests PASS.

- [ ] **Step 3: Run disposable MySQL integration verification**

Set `APP_ENV=test` and a database name dedicated to this test run. Execute both existing learner MySQL foundation tests and `learner_ai_end_to_end_mysql_test.php`. Expected: PASS; the test schema may be disposed only by the existing test-environment owner, never by learner production migrations.

- [ ] **Step 4: Run non-mutating cross-role regression checks**

Run existing Teacher, School, and Enterprise smoke/read-only checks. Expected: their code remains unchanged and queries continue to pass against the additive schema.

- [ ] **Step 5: Run final safety evidence**

```powershell
& $php app\learner\tools\ai-scope-audit.php --format=json
git diff --check
git status --short
```

Also compare shared table row counts/checksums to the approved baseline, run FK integrity checks, verify second-run migration no-ops, verify secrets are absent from logs, and verify AI visible percent remains `0` until separately approved.

- [ ] **Step 6: Record release decision and commit**

Mark the checklist `READY` only when every gate has evidence and no approval is missing.

```powershell
git add tests/learner_ai_end_to_end_mysql_test.php docs/superpowers/readiness/learner-ai-release-checklist.md
git commit -m "test(learner): verify AI readiness release gate"
```

## Execution Checkpoints

Stop and report to the user at these mandatory checkpoints:

1. Before creating or running migration 002.
2. Before creating or running migration 003.
3. Before creating or running migration 004.
4. Before creating or running the staging pilot seed.
5. If any task requires a file under `src/**`, `api/**`, Teacher, School, or Enterprise.
6. If schema inspection finds an existing table with a conflicting shape.
7. Before running any approved migration against the shared non-test database.
8. Before configuring a real provider key or making the first real model call.
9. Before setting model-visible rollout above zero.

At every checkpoint, report exact files, SQL, tables, expected row impact, compatibility evidence, backup/restore evidence, test output, and operational rollback.
