# Learner Hybrid AI Assessment Platform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore the learner AI baseline and deliver the database-backed assessment platform, deterministic scorers, learner-owned APIs, and API-driven UI needed by all four approved assessment types.

**Architecture:** Keep scoring deterministic and server-side behind a small scorer registry. Reuse the canonical assessment/version/attempt/result tables and the existing learner API security context; keep page JavaScript as a thin state controller over learner-owned endpoints. This plan intentionally stops before publishing the 366 reviewed, age-banded question prompts: the catalog content and its shared-database seed require a separate reviewable plan and Database Change Request.

**Tech Stack:** PHP 8.2-compatible code verified with Laragon PHP 8.3.30, PDO, Laragon MySQL Community Server 8.4.3, SQLite in-memory unit/contract tests only, vanilla JavaScript with Node's `node:test`, HTML/CSS, existing TalentHub learner data and API layers.

## Global Constraints

- Follow `docs/superpowers/specs/2026-08-17-learner-hybrid-ai-product-design.md`.
- Use Test-Driven Development: observe the targeted failure, implement the smallest change, and rerun the targeted test before broader verification.
- Rule/scoring output remains authoritative; do not call 9Router or any model in this plan.
- Keep `TALENTHUB_AI_VISIBLE_PERCENT=0` and do not change model rollout flags.
- Do not store or log provider credentials, full private profiles, or unnecessary answer payloads.
- Do not let the browser calculate authoritative scores or send a client-selected `studentId`.
- Do not apply migrations or seeds to the shared database in this plan.
- Do not add destructive SQL such as `DROP`, `DELETE`, `TRUNCATE`, or history-rewriting updates.
- Use Laragon MySQL Community Server 8.4.3 as the only application/runtime database. `config/database.php` must remain authoritative with driver `mysql` and connection values supplied through `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`.
- SQLite `sqlite::memory:` is permitted only inside isolated automated unit/contract tests. It creates an ephemeral database in RAM, never replaces, migrates, seeds, or writes to the Laragon MySQL database.
- Do not use any alternative PHP/server-stack executable, service, directory, configuration, or database instance; every local runtime command in this plan must resolve inside `D:\laragon`.
- Keep changes learner-owned except for the narrowly scoped `src/Rbac/Service/PermissionService.php` compatibility fix.
- Preserve immutable submitted attempts/results and enforce a 90-day retake window.
- Keep education-band codes exactly `middle`, `high`, and `college`.
- Keep assessment type codes exactly `holland`, `mbti`, `disc`, and `multiple_intelligence`.
- Use Laragon's PHP CLI at `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`; PHP is not currently exported through `PATH`. Use `D:\nodejs\node.exe` for Node verification.
- The local MySQL server and client are provided by Laragon at `D:\laragon\bin\mysql\mysql-8.4.3-winx64`; do not hard-code a second database connection outside the existing environment/configuration layer.

## File Structure

### New scoring domain

- `app/learner/assessment/Scoring/AssessmentScorer.php` — common scorer contract.
- `app/learner/assessment/Scoring/ScoringResult.php` — immutable normalized result value.
- `app/learner/assessment/Scoring/LikertScore.php` — shared 1–5 validation, reversal, and 0–100 normalization.
- `app/learner/assessment/Scoring/HollandScorer.php` — RIASEC scoring.
- `app/learner/assessment/Scoring/MbtiScorer.php` — four continuous preference-axis scoring.
- `app/learner/assessment/Scoring/DiscScorer.php` — DISC scoring.
- `app/learner/assessment/Scoring/MultipleIntelligenceScorer.php` — eight-dimension scoring.
- `app/learner/assessment/Scoring/ScorerRegistry.php` — approved scoring-version lookup.

### New assessment services

- `app/learner/assessment/Service/EducationBandResolver.php` — resolves or safely requests education-band confirmation.
- `app/learner/assessment/Service/AssessmentCatalogService.php` — catalog/status/question/history read orchestration.

### New learner-owned endpoints

- `app/learner/api/v1/assessments.php` — catalog, definition, questions, and history reads.
- `app/learner/api/v1/assessment-attempts.php` — start/resume and owned-attempt reads.
- `app/learner/api/v1/assessment-answers.php` — autosave one owned answer.
- `app/learner/api/v1/assessment-submit.php` — idempotent submission and result response.

### New tests

- `tests/permission_service_driver_compatibility_test.php`
- `tests/learner_assessment_scorer_contract_test.php`
- `tests/learner_holland_scorer_test.php`
- `tests/learner_mbti_scorer_test.php`
- `tests/learner_disc_scorer_test.php`
- `tests/learner_multiple_intelligence_scorer_test.php`
- `tests/learner_assessment_catalog_test.php`
- `tests/learner_assessment_api_test.php`
- `tests/learner_assessment_ui_test.js`

### Existing files to modify

- `src/Rbac/Service/PermissionService.php`
- `tests/learner_recommendation_api_test.php`
- `app/learner/data/bootstrap.php`
- `app/learner/data/Contracts/AssessmentRepository.php`
- `app/learner/data/Contracts/AssessmentWriteRepository.php`
- `app/learner/data/Database/DatabaseAssessmentRepository.php`
- `app/learner/data/Database/DatabaseAssessmentWriteRepository.php`
- `app/learner/data/Mock/MockAssessmentRepository.php`
- `app/learner/data/RepositoryFactory.php`
- `app/learner/data/Service/LearnerAssessmentService.php`
- `app/learner/api/LearnerApiContext.php`
- `app/learner/discover.php`
- `app/learner/assessment.php`
- `app/learner/assessment-result.php`
- `assets/js/learner-assessment.js`
- `assets/css/learner.css`
- `tests/learner_assessment_persistence_test.php`
- `tests/learner_holland_render_test.php`
- `docs/superpowers/readiness/learner-ai-release-checklist.md`

---

### Task 1: Restore SQLite and canonical RBAC compatibility

**Files:**
- Create: `tests/permission_service_driver_compatibility_test.php`
- Modify: `src/Rbac/Service/PermissionService.php`
- Modify: `tests/learner_recommendation_api_test.php`

**Interfaces:**
- Consumes: `PermissionService::__construct(PDO)` and `PermissionService::require(string $userId, string $permission): void`.
- Produces: the same public interface with driver-aware legacy-column detection for MySQL and SQLite.

- [ ] **Step 1: Add a focused failing driver-compatibility test**

Create canonical and legacy SQLite fixtures in `tests/permission_service_driver_compatibility_test.php`:

```php
<?php
declare(strict_types=1);

use TalentHub\Http\ApiException;
use TalentHub\Rbac\Service\PermissionService;

require dirname(__DIR__) . '/bin/bootstrap.php';

function permission_driver_assert(bool $condition, string $message): void
{
    if (!$condition) { fwrite(STDERR, "Assertion failed: {$message}\n"); exit(1); }
}

function canonical_permission_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL)');
    $pdo->exec("INSERT INTO roles VALUES ('role-student','student')");
    $pdo->exec("INSERT INTO users VALUES ('user-student','role-student','active')");
    $pdo->exec("INSERT INTO permissions VALUES ('permission-read','student_profile.read_own')");
    $pdo->exec("INSERT INTO role_permissions VALUES ('role-student','permission-read')");
    return $pdo;
}

function legacy_permission_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roles TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec("INSERT INTO users VALUES ('user-business','business','active')");
    return $pdo;
}

(new PermissionService(canonical_permission_fixture()))->require('user-student', 'student_profile.read_own');
(new PermissionService(legacy_permission_fixture()))->require('user-business', 'business_profile.read_own');

try {
    (new PermissionService(legacy_permission_fixture()))->require('user-business', 'student_profile.read_own');
    permission_driver_assert(false, 'legacy cross-role permission must be denied');
} catch (ApiException $exception) {
    permission_driver_assert($exception->status === 403, 'legacy denial is a safe 403');
}

echo "permission_service_driver_compatibility_test: OK\n";
```

- [ ] **Step 2: Run the focused test and reproduce the current failure**

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\permission_service_driver_compatibility_test.php
```

Expected: FAIL with SQLite reporting `no such table: information_schema.columns` from `PermissionService::usesLegacyUsers()`.

- [ ] **Step 3: Make legacy-column detection driver-aware**

Replace `usesLegacyUsers()` with:

```php
private function usesLegacyUsers(): bool
{
    $driver = strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    if ($driver === 'sqlite') {
        $statement = $this->pdo->query("PRAGMA table_info('users')");
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (strtolower((string) ($column['name'] ?? '')) === 'roles') {
                return true;
            }
        }
        return false;
    }
    if ($driver !== 'mysql') {
        throw new \RuntimeException('Unsupported RBAC database driver.');
    }
    $statement = $this->pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='roles'"
    );
    return (int) $statement->fetchColumn() === 1;
}
```

- [ ] **Step 4: Align the learner API fixture with the canonical RBAC schema**

In `learner_api_fixture()` add the `roles` table, `users.status`, and role rows:

```php
$pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec("INSERT INTO roles (id, code) VALUES ('role-student', 'student'), ('role-teacher', 'teacher')");
$pdo->exec("INSERT INTO users (id, roleId, status) VALUES ('user-student', 'role-student', 'active'), ('user-teacher', 'role-teacher', 'active')");
```

- [ ] **Step 5: Run both regression tests**

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\permission_service_driver_compatibility_test.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_recommendation_api_test.php
```

Expected: both print an `OK` line and exit `0`.

- [ ] **Step 6: Commit the isolated compatibility fix**

```powershell
git add src/Rbac/Service/PermissionService.php tests/permission_service_driver_compatibility_test.php tests/learner_recommendation_api_test.php
git commit -m "fix(rbac): support SQLite permission schema detection"
```

---

### Task 2: Introduce the scorer contract and registry

**Files:**
- Create: `app/learner/assessment/Scoring/AssessmentScorer.php`
- Create: `app/learner/assessment/Scoring/ScoringResult.php`
- Create: `app/learner/assessment/Scoring/LikertScore.php`
- Create: `app/learner/assessment/Scoring/ScorerRegistry.php`
- Create: `tests/learner_assessment_scorer_contract_test.php`
- Modify: `app/learner/data/bootstrap.php`

**Interfaces:**
- Produces: `AssessmentScorer::score(array $questions, array $answers): ScoringResult`.
- Produces: `ScorerRegistry::forVersion(string $scoringVersion): AssessmentScorer`.
- Produces: `ScoringResult::toArray(): array{result_code:string,summary:string,dimension_scores:array<string,int>}`.

- [ ] **Step 1: Write the failing contract test**

```php
<?php
declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\AssessmentScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Assessment\Scoring\ScoringResult;

require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

if (!interface_exists(AssessmentScorer::class) || !class_exists(ScoringResult::class) || !class_exists(ScorerRegistry::class)) {
    fwrite(STDERR, "Assessment scoring contract is unavailable.\n");
    exit(1);
}

$result = new ScoringResult('RIA', 'Định hướng RIASEC.', ['R' => 80, 'I' => 70, 'A' => 60]);
if ($result->toArray()['result_code'] !== 'RIA') { exit(1); }

try {
    new ScoringResult('', 'Invalid', ['R' => 101]);
    exit(1);
} catch (InvalidArgumentException) {
}

echo "learner_assessment_scorer_contract_test: OK\n";
```

- [ ] **Step 2: Run the contract test and verify it fails**

Run `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_assessment_scorer_contract_test.php`.

Expected: FAIL because the scoring classes do not exist.

- [ ] **Step 3: Add the scorer interface and immutable result**

`AssessmentScorer.php`:

```php
<?php
declare(strict_types=1);
namespace TalentHub\Learner\Assessment\Scoring;

interface AssessmentScorer
{
    /** @param list<array<string,mixed>> $questions @param array<string,mixed> $answers */
    public function score(array $questions, array $answers): ScoringResult;
}
```

`ScoringResult.php` validates non-empty result/summary, non-empty dimension keys, integer values from `0` to `100`, and exposes:

```php
/** @return array{result_code:string,summary:string,dimension_scores:array<string,int>} */
public function toArray(): array
{
    return [
        'result_code' => $this->resultCode,
        'summary' => $this->summary,
        'dimension_scores' => $this->dimensionScores,
    ];
}
```

- [ ] **Step 4: Add one shared Likert utility**

`LikertScore.php` must expose only these operations:

```php
public static function value(mixed $answer, bool $reversed = false): int
{
    if (!is_numeric($answer) || (int) $answer < 1 || (int) $answer > 5) {
        throw new \RuntimeException('Assessment answers must be integers from 1 to 5.');
    }
    $value = (int) $answer;
    return $reversed ? 6 - $value : $value;
}

public static function normalize(int $total, int $count): int
{
    return $count === 0 ? 0 : (int) round((($total - $count) / ($count * 4)) * 100);
}
```

- [ ] **Step 5: Add an explicit registry**

Use constructor injection so tests and later provider code do not depend on global state:

```php
/** @param array<string,AssessmentScorer> $scorers */
public function __construct(private readonly array $scorers) {}

public function forVersion(string $scoringVersion): AssessmentScorer
{
    $scorer = $this->scorers[trim($scoringVersion)] ?? null;
    if (!$scorer instanceof AssessmentScorer) {
        throw new \RuntimeException('Assessment scoring version is not approved.');
    }
    return $scorer;
}
```

- [ ] **Step 6: Register the new files in learner data bootstrap and rerun**

Add the scoring files before `DatabaseAssessmentWriteRepository.php` in `app/learner/data/bootstrap.php`.

Run `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_assessment_scorer_contract_test.php`.

Expected: `learner_assessment_scorer_contract_test: OK`.

- [ ] **Step 7: Commit the scoring boundary**

```powershell
git add app/learner/assessment/Scoring app/learner/data/bootstrap.php tests/learner_assessment_scorer_contract_test.php
git commit -m "feat(learner): add assessment scoring contract"
```

---

### Task 3: Move Holland scoring behind the contract

**Files:**
- Create: `app/learner/assessment/Scoring/HollandScorer.php`
- Create: `tests/learner_holland_scorer_test.php`
- Modify: `app/learner/data/Database/DatabaseAssessmentWriteRepository.php`
- Modify: `app/learner/data/RepositoryFactory.php`
- Modify: `tests/learner_assessment_persistence_test.php`

**Interfaces:**
- Consumes: `AssessmentScorer`, `LikertScore`, `ScoringResult`, and question dimension codes `R`, `I`, `A`, `S`, `E`, `C`, optionally suffixed with `:+` or `:-`.
- Produces: scorer version `holland-riasec-1.0`.

- [ ] **Step 1: Write deterministic Holland golden tests**

Build exactly two questions per RIASEC dimension with IDs `<dimension>-positive` and `<dimension>-reversed`, using dimension codes `<dimension>:+` and `<dimension>:-`. Use these answer pairs: `R=[5,1]`, `I=[5,2]`, `A=[4,2]`, `S=[3,3]`, `E=[2,4]`, `C=[1,5]`. They normalize to `R=100`, `I=88`, `A=75`, `S=50`, `E=25`, `C=0`, so the ranking is stably `RIA` and the reversed-item behavior is explicit.

Core assertion:

```php
$result = (new HollandScorer())->score($questions, $answers)->toArray();
holland_scorer_assert($result['result_code'] === 'RIA', 'Holland top-three code is deterministic');
holland_scorer_assert($result['dimension_scores']['R'] === 100, 'reversed Holland item is normalized');
```

- [ ] **Step 2: Run the Holland scorer test and verify it fails**

Run `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_holland_scorer_test.php`.

Expected: FAIL because `HollandScorer` does not exist.

- [ ] **Step 3: Implement Holland scoring**

Parse dimension codes with:

```php
private function dimension(string $code): array
{
    if (preg_match('/\A([RIASEC])(?::([+-]))?\z/', strtoupper(trim($code)), $match) !== 1) {
        throw new \RuntimeException('Unsupported Holland dimension code.');
    }
    return [$match[1], ($match[2] ?? '+') === '-'];
}
```

Accumulate validated Likert values, normalize each RIASEC dimension, rank by score with the stable dimension order as the tie-break, and return a `ScoringResult` with the top-three code.

- [ ] **Step 4: Inject the registry into assessment persistence**

Change the repository constructor to:

```php
public function __construct(private readonly PDO $pdo, private readonly ScorerRegistry $scorers) {}
```

Replace the embedded `score()` body with:

```php
$scored = $this->scorers->forVersion($attempt['scoring_version'])
    ->score($questions, $answers)
    ->toArray();
```

Delete the repository's `HOLLAND_DIMENSIONS` constant and private Holland scoring method.

- [ ] **Step 5: Build the default registry in `RepositoryFactory`**

```php
private function scorerRegistry(): ScorerRegistry
{
    return new ScorerRegistry([
        'holland-riasec-1.0' => new HollandScorer(),
    ]);
}
```

Pass this registry to `DatabaseAssessmentWriteRepository`. Update direct constructions in persistence tests to pass the same registry.

- [ ] **Step 6: Run focused and persistence tests**

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_holland_scorer_test.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_assessment_persistence_test.php
```

Expected: both print `OK`; the persistence test still produces `RIA` and one immutable result row.

- [ ] **Step 7: Commit Holland extraction**

```powershell
git add app/learner/assessment/Scoring/HollandScorer.php app/learner/data/Database/DatabaseAssessmentWriteRepository.php app/learner/data/RepositoryFactory.php tests/learner_holland_scorer_test.php tests/learner_assessment_persistence_test.php
git commit -m "refactor(learner): isolate Holland assessment scoring"
```

---

### Task 4: Add MBTI-inspired educational preference scoring

**Files:**
- Create: `app/learner/assessment/Scoring/MbtiScorer.php`
- Create: `tests/learner_mbti_scorer_test.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/data/RepositoryFactory.php`

**Interfaces:**
- Consumes dimension codes `EI:E`, `EI:I`, `SN:S`, `SN:N`, `TF:T`, `TF:F`, `JP:J`, `JP:P`.
- Produces scorer version `mbti-education-1.0`, result code such as `ENTJ`, and eight pole scores from `0` to `100`.

- [ ] **Step 1: Write a failing balanced-axis test**

Use two items per axis, one for each pole. Answer the E, N, T, and J items with `5` and opposite-pole items with `1`.

```php
$result = (new MbtiScorer())->score($questions, $answers)->toArray();
mbti_assert($result['result_code'] === 'ENTJ', 'MBTI-inspired code uses the stronger pole on each axis');
foreach (['E','I','S','N','T','F','J','P'] as $pole) {
    mbti_assert(isset($result['dimension_scores'][$pole]), "{$pole} score exists");
}
```

- [ ] **Step 2: Run the test and verify class-not-found failure**

Run `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_mbti_scorer_test.php`.

- [ ] **Step 3: Implement axis scoring without diagnostic claims**

For a question targeting one pole, add the Likert value to that pole and `6 - value` to its opposite. Normalize each pole independently. Choose the higher pole per axis; use the first pole (`E`, `S`, `T`, `J`) only as a deterministic exact-tie fallback. Use summary copy `Xu hướng học tập và làm việc theo bốn trục tham khảo.`

- [ ] **Step 4: Register and verify**

Add `'mbti-education-1.0' => new MbtiScorer()` to the factory registry and require the class in bootstrap.

Run:

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_mbti_scorer_test.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_assessment_persistence_test.php
```

Expected: both exit `0`.

- [ ] **Step 5: Commit MBTI-inspired scoring**

```powershell
git add app/learner/assessment/Scoring/MbtiScorer.php app/learner/data/bootstrap.php app/learner/data/RepositoryFactory.php tests/learner_mbti_scorer_test.php
git commit -m "feat(learner): add MBTI-inspired education scorer"
```

---

### Task 5: Add DISC educational behavior scoring

**Files:**
- Create: `app/learner/assessment/Scoring/DiscScorer.php`
- Create: `tests/learner_disc_scorer_test.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/data/RepositoryFactory.php`

**Interfaces:**
- Consumes dimension codes `D`, `I`, `S`, `C`, with optional `:+` or `:-` suffix.
- Produces scorer version `disc-education-1.0`, result code ordered from strongest to weakest, and four dimension scores.

- [ ] **Step 1: Write a failing DISC ranking and reverse-item test**

Use two items per dimension, ensure `I > S > C > D`, and include one `I:-` item whose low answer raises I after reversal.

```php
$result = (new DiscScorer())->score($questions, $answers)->toArray();
disc_assert(str_starts_with($result['result_code'], 'IS'), 'DISC code ranks strongest dimensions first');
disc_assert($result['dimension_scores']['I'] > $result['dimension_scores']['D'], 'DISC scores preserve evidence order');
```

- [ ] **Step 2: Run the failing test**

Run `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_disc_scorer_test.php`.

- [ ] **Step 3: Implement, register, and rerun**

Use the Holland accumulation pattern with DISC's stable order `D`, `I`, `S`, `C`. Return summary `Xu hướng hành vi học tập và làm việc nhóm theo DISC.` Register version `disc-education-1.0`.

Run the DISC test plus `tests\learner_assessment_persistence_test.php`; expect both to exit `0`.

- [ ] **Step 4: Commit DISC scoring**

```powershell
git add app/learner/assessment/Scoring/DiscScorer.php app/learner/data/bootstrap.php app/learner/data/RepositoryFactory.php tests/learner_disc_scorer_test.php
git commit -m "feat(learner): add DISC education scorer"
```

---

### Task 6: Add Multiple Intelligence orientation scoring

**Files:**
- Create: `app/learner/assessment/Scoring/MultipleIntelligenceScorer.php`
- Create: `tests/learner_multiple_intelligence_scorer_test.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `app/learner/data/RepositoryFactory.php`

**Interfaces:**
- Consumes dimension codes `LING`, `LOGI`, `SPAT`, `BODY`, `MUSIC`, `INTER`, `INTRA`, `NAT`, with optional `:+` or `:-` suffix.
- Produces scorer version `multiple-intelligence-1.0`, a top-three result code joined with `-`, and eight scores.

- [ ] **Step 1: Write a failing eight-dimension golden test**

Give `LOGI`, `INTER`, and `SPAT` the three highest normalized values and assert:

```php
$result = (new MultipleIntelligenceScorer())->score($questions, $answers)->toArray();
mi_assert($result['result_code'] === 'LOGI-INTER-SPAT', 'MI top-three dimensions are deterministic');
mi_assert(count($result['dimension_scores']) === 8, 'all MI dimensions are returned');
```

- [ ] **Step 2: Run the failing test**

Run `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_multiple_intelligence_scorer_test.php`.

- [ ] **Step 3: Implement, register, and rerun**

Use shared Likert validation/reversal, the stable order listed above for ties, and summary `Định hướng đa trí thông minh phục vụ lựa chọn trải nghiệm học tập.` Register version `multiple-intelligence-1.0`.

Run the MI test and persistence test; expect both to exit `0`.

- [ ] **Step 4: Commit MI scoring**

```powershell
git add app/learner/assessment/Scoring/MultipleIntelligenceScorer.php app/learner/data/bootstrap.php app/learner/data/RepositoryFactory.php tests/learner_multiple_intelligence_scorer_test.php
git commit -m "feat(learner): add multiple intelligence scorer"
```

---

### Task 7: Align catalog reads, education bands, resume, and retake policy

**Files:**
- Create: `app/learner/assessment/Service/EducationBandResolver.php`
- Create: `app/learner/assessment/Service/AssessmentCatalogService.php`
- Create: `tests/learner_assessment_catalog_test.php`
- Modify: `app/learner/data/Contracts/AssessmentRepository.php`
- Modify: `app/learner/data/Contracts/AssessmentWriteRepository.php`
- Modify: `app/learner/data/Database/DatabaseAssessmentRepository.php`
- Modify: `app/learner/data/Database/DatabaseAssessmentWriteRepository.php`
- Modify: `app/learner/data/Mock/MockAssessmentRepository.php`
- Modify: `app/learner/data/ReadModel/AssessmentReadModel.php`
- Modify: `app/learner/data/Service/LearnerAssessmentService.php`
- Modify: `app/learner/data/bootstrap.php`

**Interfaces:**
- Produces: `EducationBandResolver::resolve(string $studentId, ?string $confirmedBand): string`.
- Produces: `AssessmentCatalogService::catalog(string $studentId, ?string $confirmedBand): array`.
- Produces: `LearnerAssessmentService::startOrResume(string $studentId, string $assessmentCode, string $band): array`.
- Produces: `LearnerAssessmentService::ownedAttempt(string $studentId, string $attemptId): array`.
- Produces: `LearnerAssessmentService::history(string $studentId, string $assessmentCode): array`.

Use these constructor boundaries so API composition and tests agree:

```php
new EducationBandResolver(PDO $pdo);
new AssessmentCatalogService(AssessmentRepository $repository, EducationBandResolver $bands);
new LearnerAssessmentService(AssessmentRepository $reads, AssessmentWriteRepository $writes);
```

- [ ] **Step 1: Build a canonical SQLite catalog fixture and failing service test**

Create four published tests for one band, published versions, questions, one in-progress attempt, and one submitted attempt 30 days ago. Assert:

```php
$catalog = $catalogService->catalog(STUDENT_ID, 'high');
catalog_assert(count($catalog['assessments']) === 4, 'all four published high-band assessments are listed');
catalog_assert($catalog['education_band'] === 'high', 'confirmed band is stable');

$resumed = $attemptService->startOrResume(STUDENT_ID, 'holland', 'high');
catalog_assert($resumed['id'] === IN_PROGRESS_ATTEMPT_ID, 'existing writable attempt is resumed');

catalog_expect_exception(
    static fn () => $attemptService->startOrResume(STUDENT_ID, 'mbti', 'high'),
    'retake inside 90 days is rejected'
);
```

- [ ] **Step 2: Run the catalog test and verify missing-service failure**

Run `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_assessment_catalog_test.php`.

- [ ] **Step 3: Implement safe education-band resolution**

`EducationBandResolver` accepts only `middle`, `high`, `college`. Query the learner's `classes.gradeLevel`; map `6..9` to `middle` and `10..12` to `high`. If the value is outside those ranges, require the explicit confirmed band. Never infer college from school name or date of birth.

- [ ] **Step 4: Rewrite assessment read SQL to the canonical schema**

Use `talent_tests.code/name/type/status`, `test_questions.optionsJson/status`, version/question-version joins, current `test_attempts.status/submittedAt`, and `test_results.dimensionScoresJson/scoringVersion`. Remove references to legacy columns `dimensions`, `options`, `completedAt`, and `dimensionScores`.

Extend `AssessmentRepository` with these explicit learner-owned reads and implement them in both database and mock repositories:

```php
public function publishedCatalog(string $studentId, string $educationBand): array;
public function publishedAssessment(string $assessmentCode, string $educationBand): ?array;
public function questionsForVersion(string $versionId): array;
public function ownedAttempt(string $studentId, string $attemptId): ?array;
public function history(string $studentId, string $assessmentCode): array;
```

Replace `AssessmentWriteRepository::startAttempt(...)` with:

```php
public function startOrResumeAttempt(
    string $studentId,
    string $assessmentCode,
    string $educationBand
): array;
```

Keep `saveAnswer(...)` and `submitAttempt(...)` unchanged. Update `LearnerAssessmentService` so `startOrResume(...)` delegates to `$writes`, while `ownedAttempt(...)` and `history(...)` delegate to `$reads` and reject missing/non-owned records without exposing existence.

Catalog rows must include:

```php
[
    'code' => 'holland',
    'education_band' => 'high',
    'version' => '1.0.0',
    'scoring_version' => 'holland-riasec-1.0',
    'question_count' => 30,
    'status' => 'published',
    'attempt_status' => 'not_started|in_progress|submitted|retake_locked',
    'progress' => 0,
    'next_retake_at' => null,
]
```

- [ ] **Step 5: Enforce start/resume and 90-day retake server-side**

Inside one transaction:

1. Return the newest owned in-progress attempt for the resolved test/version.
2. Read the latest submitted attempt for the assessment type across banded test codes.
3. Reject a new attempt when `submittedAt + 90 days` is in the future.
4. Create a new attempt only when neither condition applies.

Set `expiresAt` to 30 days after start for resumable drafts. Use UTC timestamps.

- [ ] **Step 6: Keep questions bound to the attempt version**

Expose prompt/options only through the attempt's `versionId`. Never reload the newest published version when resuming.

- [ ] **Step 7: Run catalog and persistence tests**

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_assessment_catalog_test.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_assessment_persistence_test.php
```

Expected: both exit `0`; start/resume, ownership, idempotency, and immutability remain intact.

- [ ] **Step 8: Commit catalog lifecycle behavior**

```powershell
git add app/learner/assessment/Service app/learner/data tests/learner_assessment_catalog_test.php tests/learner_assessment_persistence_test.php
git commit -m "feat(learner): add assessment catalog and lifecycle policy"
```

---

### Task 8: Add learner-owned assessment APIs

**Files:**
- Create: `app/learner/api/v1/assessments.php`
- Create: `app/learner/api/v1/assessment-attempts.php`
- Create: `app/learner/api/v1/assessment-answers.php`
- Create: `app/learner/api/v1/assessment-submit.php`
- Create: `tests/learner_assessment_api_test.php`
- Modify: `app/learner/api/LearnerApiContext.php`

**Interfaces:**
- `GET /assessments.php?band=high` returns catalog.
- `GET /assessments.php?code=holland&band=high` returns definition/questions/history.
- `POST /assessment-attempts.php` body `{assessmentCode, educationBand}` starts/resumes.
- `GET /assessment-attempts.php?attemptId=<uuid>` returns one owned attempt.
- `PATCH /assessment-answers.php` body `{attemptId, questionId, answer}` autosaves.
- `POST /assessment-submit.php` body `{attemptId}` plus `X-Idempotency-Key` submits.

- [ ] **Step 1: Write an API contract test using SQLite and authenticated sessions**

Exercise every endpoint through the existing isolated endpoint runner and assert these exact status/error contracts:

- anonymous request: HTTP `401`, `error.code=AUTH_REQUIRED`;
- authenticated teacher: HTTP `403`, `error.code=PERMISSION_DENIED`;
- student A reading student B's attempt: HTTP `404`, `error.code=ASSESSMENT_ATTEMPT_NOT_FOUND`;
- any undocumented JSON field: HTTP `422`, `error.code=VALIDATION_FAILED`;
- `educationBand=invalid`: HTTP `422`, `error.code=VALIDATION_FAILED`;
- mutation without CSRF: HTTP `403`, `error.code=CSRF_INVALID`;
- two submits using `assessment-submit-key-0001`: HTTP `200` both times and identical `data.result.id`;
- forced repository failure: HTTP `500`, `error.code=SOURCE_FAILURE`, a non-empty `request_id`, and serialized JSON containing neither `SELECT` nor `SQLSTATE`.

- [ ] **Step 2: Run the API test and verify endpoint/context failure**

Run `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_assessment_api_test.php`.

- [ ] **Step 3: Expose assessment services from `LearnerApiContext`**

Add:

```php
public function assessmentCatalogService(): AssessmentCatalogService
{
    $factory = new RepositoryFactory('database', $this->pdo);
    return new AssessmentCatalogService(
        $factory->assessment(),
        new EducationBandResolver($this->pdo)
    );
}

public function assessmentService(): LearnerAssessmentService
{
    $factory = new RepositoryFactory('database', $this->pdo);
    return new LearnerAssessmentService(
        $factory->assessment(),
        $factory->assessmentWrite()
    );
}
```

Keep PDO private and expose no generic query escape hatch.

- [ ] **Step 4: Implement strict endpoint method/input contracts**

Each endpoint must:

1. build `Request` and `LearnerApiContext`;
2. resolve the authenticated learner with `student_profile.read_own` or `student_profile.update_own`;
3. validate CSRF for mutations;
4. allow only the documented fields;
5. validate UUIDs, assessment codes, and band values;
6. call one service method;
7. return through `JsonResponder`;
8. map known validation/lifecycle errors to `ApiException` without exposing raw SQL.

- [ ] **Step 5: Run API, recommendation API, and API client tests**

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_assessment_api_test.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_recommendation_api_test.php
D:\nodejs\node.exe --test tests\learner_api_client_test.js
```

Expected: all tests pass with zero failures.

- [ ] **Step 6: Commit API vertical slice**

```powershell
git add app/learner/api app/learner/assessment app/learner/data tests/learner_assessment_api_test.php
git commit -m "feat(learner): add assessment API endpoints"
```

---

### Task 9: Replace browser-local scoring with an API state controller

**Files:**
- Create: `tests/learner_assessment_ui_test.js`
- Modify: `assets/js/learner-assessment.js`

**Interfaces:**
- Consumes existing `createLearnerApiClient`.
- Produces `createAssessmentController({api, view, createIdempotencyKey})` for catalog, start/resume, autosave, submit, result, and retry states.
- Produces presentation states `loading`, `ready`, `saving`, `save-error`, `submitting`, `validation-error`, `expired`, `source-error`, `complete`.

- [ ] **Step 1: Write controller tests before editing browser code**

Test these exact behaviors:

```js
test('autosave coalesces repeated changes for one question', async () => {
  const first = controller.saveAnswer('question-1', 4);
  const second = controller.saveAnswer('question-1', 5);
  assert.strictEqual(first, second);
  assert.equal(calls.length, 1);
});

test('submit reuses one idempotency key while in flight', async () => {
  const first = controller.submit();
  const second = controller.submit();
  assert.strictEqual(first, second);
  assert.equal(calls[0].options.idempotencyKey, 'assessment-submit-key-0001');
});

test('authoritative scores are never calculated in the browser', () => {
  const source = fs.readFileSync(modulePath, 'utf8');
  assert.equal(source.includes('scoreHolland'), false);
  assert.equal(source.includes('localStorage'), false);
});
```

- [ ] **Step 2: Run the UI test and verify current local-scoring failure**

Run `D:\nodejs\node.exe --test tests\learner_assessment_ui_test.js`.

Expected: FAIL because the current file exports no API controller and contains `scoreHolland` and `localStorage`.

- [ ] **Step 3: Implement the API controller as a CommonJS/browser-compatible module**

Follow the export pattern in `learner-recommendations.js`. Keep an in-flight map by question ID, debounce only presentation updates, and send every authoritative answer through `/assessment-answers.php`. Keep one in-flight submit promise and one idempotency key until it settles.

- [ ] **Step 4: Render all untrusted strings with `textContent`**

Do not use `innerHTML` for API response content. Add a static test asserting `.textContent` is present and `.innerHTML` is absent from the new controller/render path.

- [ ] **Step 5: Run UI and shared API client tests**

```powershell
D:\nodejs\node.exe --test tests\learner_assessment_ui_test.js
D:\nodejs\node.exe --test tests\learner_api_client_test.js
```

Expected: both suites report zero failures.

- [ ] **Step 6: Commit the browser controller**

```powershell
git add assets/js/learner-assessment.js tests/learner_assessment_ui_test.js
git commit -m "feat(learner): connect assessments to learner API"
```

---

### Task 10: Update assessment catalog, runner, and result pages

**Files:**
- Modify: `app/learner/discover.php`
- Modify: `app/learner/assessment.php`
- Modify: `app/learner/assessment-result.php`
- Modify: `assets/css/learner.css`
- Modify: `tests/learner_holland_render_test.php`

**Interfaces:**
- Consumes the Task 8 APIs and Task 9 controller.
- Produces four database-driven assessment cards, education-band confirmation when required, resumable runner states, and generic multi-framework result rendering.

- [ ] **Step 1: Rewrite render expectations before page code**

Update the render test to assert:

- four assessment card markers: `holland`, `mbti`, `disc`, `multiple_intelligence`;
- no disabled `Sắp triển khai` cards from the old Holland-only screen;
- runner has `data-assessment-code` rather than Holland-only markup;
- band confirmation has options `middle`, `high`, `college`;
- result page has generic `data-result-dimension-list` and advisory disclaimer;
- boot payload contains endpoint paths, never a client-supplied `student_id`;
- page has loading, save-error, expired, and validation-error states.

- [ ] **Step 2: Run the render test and verify the old Holland-only UI fails**

Run `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_holland_render_test.php`.

- [ ] **Step 3: Make discovery database-driven and safe for an empty catalog**

Render cards from the catalog read model. When a reviewed question catalog has not yet been published, show `Chưa có phiên bản được duyệt` and disable start; never fall back to mock questions in database mode.

- [ ] **Step 4: Generalize the runner**

Keep the existing accessible structure but replace RIASEC-specific labels with API-provided assessment name, progress, prompt, and options. Show the band confirmation only before attempt creation when the server reports `education_band_required`.

- [ ] **Step 5: Generalize result rendering**

Render the server's result code, summary, and dimension list without assuming six RIASEC rows. Keep framework-specific educational disclaimers and display the next retake date.

- [ ] **Step 6: Add only the styles required by new generic states**

Reuse current assessment layout tokens. Add selectors for band confirmation, dynamic dimension grids, save-error recovery, and disabled/unpublished catalog cards. Do not duplicate the existing assessment stylesheet block.

- [ ] **Step 7: Run render, UI, and syntax tests**

```powershell
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_holland_render_test.php
D:\nodejs\node.exe --test tests\learner_assessment_ui_test.js
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l app\learner\discover.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l app\learner\assessment.php
D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l app\learner\assessment-result.php
```

Expected: every command exits `0`.

- [ ] **Step 8: Commit the generic assessment experience**

```powershell
git add app/learner/discover.php app/learner/assessment.php app/learner/assessment-result.php assets/css/learner.css tests/learner_holland_render_test.php
git commit -m "feat(learner): generalize four-assessment experience"
```

---

### Task 11: Run the assessment-platform release gate and record evidence

**Files:**
- Modify: `docs/superpowers/readiness/learner-ai-release-checklist.md`

**Interfaces:**
- Consumes all prior tasks.
- Produces a documented local verification record and an explicit statement that shared question-bank seeding remains unexecuted.

- [ ] **Step 1: Verify the configured runtime database is Laragon MySQL 8.4.3**

Start Laragon's MySQL service, keep the project `.env` pointed at the intended local TalentHub database, and run this read-only connection gate:

```powershell
$talentHubLaragonPhp = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
$databaseReport = & $talentHubLaragonPhp bin\connect-check.php --quick --json | ConvertFrom-Json
if ($LASTEXITCODE -ne 0) { throw 'Laragon MySQL connection check failed.' }
if ($databaseReport.db.driver -ne 'mysql') { throw 'TalentHub runtime database driver must be mysql.' }
if ($databaseReport.connection -ne 'OK') { throw 'TalentHub runtime database is unavailable.' }
if ($databaseReport.server_version -notmatch '^8\.4\.3(?:[.-]|$)') { throw "Expected Laragon MySQL 8.4.3, received $($databaseReport.server_version)." }
```

Expected: command exits `0`; `db.driver` is `mysql`, `connection` is `OK`, and `server_version` begins with `8.4.3`. This step is read-only and must not run migration or seed commands.

- [ ] **Step 2: Lint every changed PHP file**

Run:

```powershell
$files = git diff --name-only 5a8303e..HEAD -- '*.php'
foreach ($file in $files) { D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe -l $file; if ($LASTEXITCODE -ne 0) { exit 1 } }
```

Expected: every file reports `No syntax errors detected`.

- [ ] **Step 3: Run focused assessment and RBAC suites**

```powershell
$tests = @(
  'tests\permission_service_driver_compatibility_test.php',
  'tests\learner_recommendation_api_test.php',
  'tests\learner_assessment_scorer_contract_test.php',
  'tests\learner_holland_scorer_test.php',
  'tests\learner_mbti_scorer_test.php',
  'tests\learner_disc_scorer_test.php',
  'tests\learner_multiple_intelligence_scorer_test.php',
  'tests\learner_assessment_catalog_test.php',
  'tests\learner_assessment_persistence_test.php',
  'tests\learner_assessment_api_test.php',
  'tests\learner_holland_render_test.php'
)
foreach ($test in $tests) { D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe $test; if ($LASTEXITCODE -ne 0) { exit 1 } }
D:\nodejs\node.exe --test tests\learner_assessment_ui_test.js
D:\nodejs\node.exe --test tests\learner_api_client_test.js
```

Expected: all PHP tests print `OK`; both Node suites report zero failures.

- [ ] **Step 4: Run the existing non-MySQL learner AI regression suite**

```powershell
$regressions = @(
  'tests\learner_ai_evaluation_test.php',
  'tests\learner_ai_input_extensions_schema_test.php',
  'tests\learner_ai_input_schema_test.php',
  'tests\learner_ai_provider_test.php',
  'tests\learner_ai_recommendation_render_test.php',
  'tests\learner_ai_rollout_test.php',
  'tests\learner_ai_scope_audit_test.php',
  'tests\learner_ai_scope_policy_test.php',
  'tests\learner_ai_snapshot_test.php',
  'tests\learner_ai_sources_test.php',
  'tests\learner_ai_synthetic_dataset_v2_contract_test.php',
  'tests\learner_readiness_test.php',
  'tests\learner_recommendation_api_test.php',
  'tests\learner_recommendation_repository_test.php',
  'tests\learner_recommendation_schema_test.php',
  'tests\learner_recommendation_service_test.php',
  'tests\learner_rule_recommendation_test.php',
  'tests\learner_shared_readiness_test.php'
)
foreach ($test in $regressions) { D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe $test; if ($LASTEXITCODE -ne 0) { exit 1 } }
D:\nodejs\node.exe --test tests\learner_ai_recommendation_ui_test.js
```

Expected: all 18 PHP tests exit `0`; the Node suite reports `6 passed, 0 failed`.

- [ ] **Step 5: Verify repository scope and whitespace**

```powershell
git diff --check 5a8303e..HEAD
git status --short
git diff --name-only 5a8303e..HEAD
```

Expected: no whitespace errors; only the files enumerated by this plan plus generated readiness documentation are changed.

- [ ] **Step 6: Record the gate without claiming catalog publication**

Append a dated section to `learner-ai-release-checklist.md` containing:

```markdown
## Assessment platform verification 2026-08-17

- Runtime connection through `config/database.php` reaches Laragon MySQL Community Server 8.4.3.
- SQLite RBAC and learner recommendation API regression suites pass using ephemeral in-memory databases only.
- Four deterministic scorer contracts pass against synthetic fixtures.
- Assessment lifecycle, API ownership, idempotency, retake, render, and UI controller tests pass.
- No shared migration or question-bank seed was executed.
- The model-visible percentage remains fixed at `0`.
- Publishing the 12 age-banded assessment catalogs requires the separate reviewed catalog plan and Database Change Request.
```

- [ ] **Step 7: Commit the verified readiness record**

```powershell
git add docs/superpowers/readiness/learner-ai-release-checklist.md
git commit -m "docs(learner): record assessment platform verification"
```

## Follow-on Plans

After this plan passes review and execution, write these plans against the resulting code state, in order:

1. `2026-08-17-learner-assessment-catalog-content.md` — author, review, validate, and safely seed the 12 age-banded question catalogs (366 prompts total) with a Database Change Request.
2. `2026-08-17-learner-competency-profile.md` — combine the latest four results with verified learner evidence and expose coverage/contradictions.
3. `2026-08-17-learner-recommendation-roadmap.md` — group/activity retrieval, deterministic matching, evidence, and rolling three-month roadmap.
4. `2026-08-17-learner-gemini-9router-shadow.md` — minimized provider payload, Gemini adapter configuration, strict validation, evaluation, and rule fallback with visible percentage fixed at zero.
