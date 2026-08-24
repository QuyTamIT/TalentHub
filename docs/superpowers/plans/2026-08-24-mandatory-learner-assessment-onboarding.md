# Mandatory Learner Assessment Onboarding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gate only newly self-registered student accounts behind a resumable, server-enforced sequence of Holland, MBTI, DISC, and Multiple Intelligence assessments before normal learner-portal access.

**Architecture:** Add an empty, non-backfilled onboarding-state table and insert `pending` transactionally during new student registration. A focused repository/service computes canonical four-test progress, while a centralized page/API gate enforces `pending`, `accepted`, and `completed`; the existing assessment persistence remains the source of saved answers and immutable results.

**Tech Stack:** PHP 8.3, PDO/MySQL with SQLite test fixtures, server-rendered PHP, vanilla JavaScript, CSS, Node.js built-in test runner.

## Global Constraints

- Apply mandatory onboarding only to student accounts that receive a `learner_onboarding_states` row during self-registration after deployment.
- Do not backfill existing students; absence of an onboarding row means normal access.
- Enforce access and completion on the server; browser state is presentation-only.
- Require four distinct owned submitted result types in this order: `holland`, `mbti`, `disc`, `multiple_intelligence`.
- Normalize banded catalog codes such as `holland_high` to `holland`; never use broad `type` values such as `interest` as completion identities.
- Preserve existing assessment autosave, ownership, version binding, scoring, result immutability, and retake behavior.
- Store and compare timestamps in UTC.
- Do not expose raw assessment answers in audit logs.
- Do not apply the shared database migration until its Database Change Request receives explicit approval.
- Do not redesign or gate teacher, school, enterprise, or platform-admin portals.

---

## File Map

**Create**

- `Database/migrations/20260824000100_create_learner_onboarding_states.php` — empty, non-backfilled canonical onboarding table.
- `docs/superpowers/database-change-requests/2026-08-24-learner-onboarding-state.md` — exact schema, safety, rollback, and approval gate.
- `src/Modules/Student/Repository/LearnerOnboardingRepository.php` — onboarding row, progress query, state transition, and audit persistence.
- `src/Modules/Student/Service/LearnerOnboardingService.php` — required order, code normalization, progress read model, acceptance, sequencing, and idempotent completion.
- `src/Modules/Student/Service/LearnerOnboardingGate.php` — centralized page/API route decisions.
- `app/learner/onboarding.php` — CSRF-protected accept/decline form endpoint.
- `assets/js/learner-onboarding.js` — dialog focus containment and non-dismissible keyboard behavior.
- `tests/learner_onboarding_migration_contract_test.php` — migration/DCR/static schema contract.
- `tests/learner_onboarding_service_test.php` — state, normalization, progress, order, ownership, and reconciliation.
- `tests/learner_onboarding_registration_test.php` — transactional registration integration.
- `tests/learner_onboarding_gate_test.php` — page/API allowlist decisions.
- `tests/learner_onboarding_endpoint_test.php` — accept, decline, CSRF, audit, and session behavior.
- `tests/learner_onboarding_ui_test.js` — dialog/progress/accessibility contracts.

**Modify**

- `src/Auth/Repository/AuthRepository.php` — insert `pending` onboarding row in the existing registration transaction.
- `src/Bootstrap/StudentAppContext.php` — load onboarding, reconcile, enforce the page gate, and expose its read model.
- `app/learner/api/LearnerApiContext.php` — enforce onboarding before normal API permissions and expose onboarding services to assessment endpoints.
- `app/learner/api/v1/assessment-attempts.php` — enforce next-test order before starting/resuming.
- `app/learner/api/v1/assessment-answers.php` — allow only the current owned onboarding attempt while gated.
- `app/learner/api/v1/assessment-submit.php` — reconcile progress after submission and return the next safe destination.
- `app/learner/index.php` — render the mandatory pending dialog.
- `app/learner/discover.php` — render the accepted-state `0/4`–`4/4` progress hub and completion state.
- `app/learner/includes/student-data.php` — expose onboarding read model to learner views and filter unavailable navigation.
- `app/learner/includes/sidebar.php` — render only server-approved navigation during onboarding.
- `assets/css/learner.css` — scoped dialog, inert backdrop, progress, state, and responsive styles.
- `assets/js/learner-assessment.js` — consume the server-provided next destination after submit.
- `tests/learner_assessment_api_test.php` — assessment gate and final-submit reconciliation coverage.
- `tests/learner_assessment_ui_test.js` — next-destination behavior without client-side completion authority.
- `tests/learner_security_contract_test.php` — server-gate and safe rendering assertions.

---

### Task 1: Canonical Onboarding Schema and Database Change Request

**Files:**
- Create: `tests/learner_onboarding_migration_contract_test.php`
- Create: `Database/migrations/20260824000100_create_learner_onboarding_states.php`
- Create: `docs/superpowers/database-change-requests/2026-08-24-learner-onboarding-state.md`

**Interfaces:**
- Consumes: `student_profiles.id CHAR(36)` and `MigrationContext`.
- Produces: `learner_onboarding_states(studentId,status,acceptedAt,completedAt,createdAt,updatedAt)` with no backfill.

- [ ] **Step 1: Write the failing migration contract test**

```php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$migration = (string) file_get_contents($root . '/Database/migrations/20260824000100_create_learner_onboarding_states.php');
$dcr = (string) file_get_contents($root . '/docs/superpowers/database-change-requests/2026-08-24-learner-onboarding-state.md');
$assert = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
};

$assert(str_contains($migration, 'CREATE TABLE learner_onboarding_states'), 'Creates onboarding table.');
$assert(str_contains($migration, 'PRIMARY KEY (studentId)'), 'One row per student.');
$assert(str_contains($migration, "status IN ('pending', 'accepted', 'completed')"), 'Constrains status.');
$assert(str_contains($migration, 'fk_learner_onboarding_states_student'), 'Owns student FK.');
$assert(!preg_match('/INSERT\s+INTO\s+learner_onboarding_states/i', $migration), 'Migration must not backfill existing students.');
$assert(str_contains($dcr, 'APPROVAL REQUIRED'), 'DCR retains explicit approval gate.');
$assert(str_contains($dcr, 'No existing student rows are inserted'), 'DCR documents compatibility.');
echo "learner_onboarding_migration_contract_test: OK\n";
```

- [ ] **Step 2: Run the contract test and verify RED**

Run: `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_migration_contract_test.php`

Expected: FAIL because the migration and DCR do not exist.

- [ ] **Step 3: Implement the irreversible empty-table migration**

```php
<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Create mandatory learner onboarding state'; }
    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('student_profiles');
        $context->assertTableAbsent('learner_onboarding_states');
    }
    public function up(MigrationContext $context): void
    {
        $context->execute(<<<'SQL'
CREATE TABLE learner_onboarding_states (
  studentId CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  acceptedAt DATETIME(6) NULL,
  completedAt DATETIME(6) NULL,
  createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (studentId),
  KEY idx_learner_onboarding_states_status (status, updatedAt),
  CONSTRAINT fk_learner_onboarding_states_student FOREIGN KEY (studentId) REFERENCES student_profiles(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_learner_onboarding_states_status CHECK (status IN ('pending', 'accepted', 'completed')),
  CONSTRAINT chk_learner_onboarding_states_timestamps CHECK (
    (status = 'pending' AND acceptedAt IS NULL AND completedAt IS NULL) OR
    (status = 'accepted' AND acceptedAt IS NOT NULL AND completedAt IS NULL) OR
    (status = 'completed' AND acceptedAt IS NOT NULL AND completedAt IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    public function isReversible(): bool { return false; }
    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Learner onboarding state migration is irreversible.');
    }
};
```

Write the DCR with: exact SQL contract above; row estimate `0` at migration time; no backfill; FK and check constraints; preflight queries; backup/restore procedure; verification query `SELECT status, COUNT(*) ... GROUP BY status`; rollout order migration-before-code; and a final standalone `APPROVAL REQUIRED` heading stating the migration must not be executed yet.

- [ ] **Step 4: Run migration/static safety tests**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_migration_contract_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_forward_migration_test.php
```

Expected: both print `OK` or an existing documented safe `SKIP`; zero failures.

- [ ] **Step 5: Commit the schema contract without applying it**

```powershell
git add -f Database/migrations/20260824000100_create_learner_onboarding_states.php docs/superpowers/database-change-requests/2026-08-24-learner-onboarding-state.md tests/learner_onboarding_migration_contract_test.php
git commit -m "feat(student): define learner onboarding state schema"
```

---

### Task 2: Onboarding Repository and Domain Service

**Files:**
- Create: `tests/learner_onboarding_service_test.php`
- Create: `src/Modules/Student/Repository/LearnerOnboardingRepository.php`
- Create: `src/Modules/Student/Service/LearnerOnboardingService.php`

**Interfaces:**
- Consumes: injected `PDO`, authenticated `studentId`, `userId`, request ID, and optional IP.
- Produces:
  - `LearnerOnboardingRepository::find(string $studentId): ?array`
  - `LearnerOnboardingRepository::submittedCodes(string $studentId): array`
  - `LearnerOnboardingRepository::accept(string $studentId): void`
  - `LearnerOnboardingRepository::complete(string $studentId): void`
  - `LearnerOnboardingService::progress(string $studentId): array`
  - `LearnerOnboardingService::accept(string $studentId, string $userId, string $requestId, ?string $ip): array`
  - `LearnerOnboardingService::decline(string $studentId, string $userId, string $requestId, ?string $ip): void`
  - `LearnerOnboardingService::reconcile(string $studentId, string $userId, string $requestId, ?string $ip): array`
  - `LearnerOnboardingService::assertAssessmentAccessible(string $studentId, string $assessmentCode): void`

- [ ] **Step 1: Write failing SQLite service tests**

Create tables `learner_onboarding_states`, `talent_tests`, `test_attempts`, and `test_results`; cover:

```php
$service = new LearnerOnboardingService(new LearnerOnboardingRepository($pdo));

onboarding_assert($service->progress('legacy-student')['required'] === false, 'Missing row exempts existing student.');
onboarding_assert($service->progress('new-student')['status'] === 'pending', 'New student starts pending.');
$accepted = $service->accept('new-student', 'new-user', 'request-1', '127.0.0.1');
onboarding_assert($accepted['status'] === 'accepted' && $accepted['next_code'] === 'holland', 'Acceptance starts Holland.');

seed_submitted_result($pdo, 'new-student', 'holland_high');
seed_submitted_result($pdo, 'new-student', 'mbti_high');
seed_submitted_result($pdo, 'new-student', 'disc_high');
$three = $service->reconcile('new-student', 'new-user', 'request-2', null);
onboarding_assert($three['completed_count'] === 3 && $three['next_code'] === 'multiple_intelligence', 'Three tests remain gated.');

seed_submitted_result($pdo, 'other-student', 'multiple_intelligence_high');
onboarding_assert($service->reconcile('new-student', 'new-user', 'request-3', null)['status'] === 'accepted', 'Other learner cannot complete onboarding.');
seed_submitted_result($pdo, 'new-student', 'multiple_intelligence_high');
onboarding_assert($service->reconcile('new-student', 'new-user', 'request-4', null)['status'] === 'completed', 'Four owned types complete onboarding.');
```

Also assert `normalizeCode('multiple_intelligence_college')`, duplicate Holland attempts count once, broad `personality` type does not count, and a later test throws `ApiException` code `ONBOARDING_SEQUENCE_REQUIRED`.

- [ ] **Step 2: Run service test and verify RED**

Run: `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_service_test.php`

Expected: FAIL because repository/service classes do not exist.

- [ ] **Step 3: Implement repository queries and idempotent transitions**

Use a submitted-code query that proves ownership and immutable result existence:

```sql
SELECT DISTINCT tt.code
FROM test_attempts ta
JOIN talent_tests tt ON tt.id = ta.testId
JOIN test_results tr ON tr.attemptId = ta.id
WHERE ta.studentId = :studentId AND ta.status = 'submitted'
```

Use guarded state updates:

```sql
UPDATE learner_onboarding_states
SET status='accepted', acceptedAt=COALESCE(acceptedAt, UTC_TIMESTAMP(6))
WHERE studentId=:studentId AND status='pending'
```

```sql
UPDATE learner_onboarding_states
SET status='completed', completedAt=COALESCE(completedAt, UTC_TIMESTAMP(6))
WHERE studentId=:studentId AND status='accepted'
```

Repository audit events must be `learner.onboarding_accepted`, `learner.onboarding_declined`, and `learner.onboarding_completed`, with metadata containing only `from`, `to`, and `completedCodes`.

- [ ] **Step 4: Implement deterministic service read model**

```php
private const REQUIRED_CODES = ['holland', 'mbti', 'disc', 'multiple_intelligence'];

public static function normalizeCode(string $code): ?string
{
    $code = strtolower(trim($code));
    foreach (self::REQUIRED_CODES as $required) {
        if ($code === $required || str_starts_with($code, $required . '_')) { return $required; }
    }
    return null;
}
```

Return:

```php
[
    'required' => true,
    'status' => 'accepted',
    'completed_count' => 2,
    'required_count' => 4,
    'completed_codes' => ['holland', 'mbti'],
    'next_code' => 'disc',
    'next_url' => '/app/learner/assessment.php?code=disc',
    'items' => [
        ['code' => 'holland', 'state' => 'completed'],
        ['code' => 'mbti', 'state' => 'completed'],
        ['code' => 'disc', 'state' => 'next'],
        ['code' => 'multiple_intelligence', 'state' => 'locked'],
    ],
]
```

- [ ] **Step 5: Run service tests and commit**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_service_test.php
git add src/Modules/Student/Repository/LearnerOnboardingRepository.php src/Modules/Student/Service/LearnerOnboardingService.php tests/learner_onboarding_service_test.php
git commit -m "feat(student): add onboarding progress service"
```

Expected: test prints `learner_onboarding_service_test: OK`.

---

### Task 3: Transactional New-student Enrollment

**Files:**
- Create: `tests/learner_onboarding_registration_test.php`
- Modify: `src/Auth/Repository/AuthRepository.php:28-54`

**Interfaces:**
- Consumes: existing `AuthRepository::createStudent(...)` transaction.
- Produces: exactly one `pending` onboarding row for every successful new self-registration; no row for pre-existing students.

- [ ] **Step 1: Write failing registration transaction tests**

Build the minimum SQLite auth/class/profile/audit/onboarding fixture and assert:

```php
$repository = new AuthRepository($pdo);
$userId = $repository->createStudent($validData, 'registration-request', '127.0.0.1');
$studentId = (string) $pdo->query("SELECT id FROM student_profiles WHERE userId='{$userId}'")->fetchColumn();
$state = $pdo->query("SELECT status FROM learner_onboarding_states WHERE studentId='{$studentId}'")->fetchColumn();
registration_assert($state === 'pending', 'Successful registration creates pending onboarding.');
registration_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_onboarding_states')->fetchColumn() === 1, 'Exactly one onboarding row exists.');
```

Add a trigger that aborts onboarding insertion and assert users/student_profiles/audit_logs all remain empty after the exception.

- [ ] **Step 2: Run registration test and verify RED**

Run: `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_registration_test.php`

Expected: FAIL because `createStudent()` does not insert onboarding.

- [ ] **Step 3: Insert onboarding inside the existing transaction**

Immediately after creating `student_profiles` and before the registration audit:

```php
$statement = $this->pdo->prepare(
    "INSERT INTO learner_onboarding_states(studentId,status) VALUES(?,'pending')"
);
$statement->execute([$profileId]);
```

Do not catch this insert independently; the existing outer catch must roll back the whole transaction.

- [ ] **Step 4: Run registration and auth regressions**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_registration_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\login_form_security_test.php
```

Expected: both print `OK`.

- [ ] **Step 5: Commit**

```powershell
git add src/Auth/Repository/AuthRepository.php tests/learner_onboarding_registration_test.php
git commit -m "feat(auth): enroll new students in onboarding"
```

---

### Task 4: Central Page/API Gate and Decision Endpoint

**Files:**
- Create: `tests/learner_onboarding_gate_test.php`
- Create: `tests/learner_onboarding_endpoint_test.php`
- Create: `src/Modules/Student/Service/LearnerOnboardingGate.php`
- Create: `app/learner/onboarding.php`
- Modify: `src/Bootstrap/StudentAppContext.php:16-88`
- Modify: `app/learner/api/LearnerApiContext.php:50-136`

**Interfaces:**
- Consumes: `LearnerOnboardingService::progress()` and normalized request path.
- Produces:
  - `LearnerOnboardingGate::pageDestination(array $progress, string $path): ?string`
  - `LearnerOnboardingGate::assertApiAllowed(array $progress, string $endpoint): void`
  - page context key `onboarding`.

- [ ] **Step 1: Write failing pure gate tests**

```php
$gate = new LearnerOnboardingGate();
gate_assert($gate->pageDestination(['required'=>false], '/app/learner/profile.php') === null, 'Legacy account allowed.');
gate_assert($gate->pageDestination(['required'=>true,'status'=>'pending'], '/app/learner/index.php') === null, 'Pending can reach overview.');
gate_assert($gate->pageDestination(['required'=>true,'status'=>'pending'], '/app/learner/profile.php') === '/app/learner/index.php', 'Pending is redirected.');
gate_assert($gate->pageDestination(['required'=>true,'status'=>'accepted','next_url'=>'/app/learner/assessment.php?code=disc'], '/app/learner/profile.php') === '/app/learner/assessment.php?code=disc', 'Accepted resumes next test.');
gate_assert($gate->pageDestination(['required'=>true,'status'=>'completed'], '/app/learner/profile.php') === null, 'Completed allowed.');
```

Assert that malformed external paths and `//host/path` never become destinations, and non-assessment API access throws `ApiException(403, 'ONBOARDING_REQUIRED', ...)`.

- [ ] **Step 2: Write failing endpoint tests**

Exercise `app/learner/onboarding.php` in child PHP processes:

- POST `accept` with valid session/CSRF changes `pending` to `accepted` and redirects to Holland.
- POST `decline` records audit, destroys session, and redirects to `/login.php?onboarding=declined`.
- invalid CSRF returns 403 and changes no state.
- GET or an unknown action returns 405/422 without mutation.

- [ ] **Step 3: Run gate/endpoint tests and verify RED**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_gate_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_endpoint_test.php
```

Expected: FAIL because gate and endpoint do not exist.

- [ ] **Step 4: Implement centralized page decisions**

In `StudentAppContext::boot()` after resolving the student:

```php
$onboardingService = new LearnerOnboardingService(new LearnerOnboardingRepository($this->pdo));
$onboarding = $onboardingService->reconcile($student['id'], $user['id'], RequestId::make(null), $_SERVER['REMOTE_ADDR'] ?? null);
$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/app/learner/index.php', PHP_URL_PATH) ?: '/app/learner/index.php');
$destination = (new LearnerOnboardingGate())->pageDestination($onboarding, $path);
if ($destination !== null) { header('Location: ' . app_href($destination)); exit; }
```

Return `'onboarding' => $onboarding` in the page context. Pass the canonical request ID into `boot()` or store it once so reconciliation/audit does not create unrelated IDs.

- [ ] **Step 5: Implement API enforcement before normal endpoint work**

Enforce the gate once inside `studentIdentityForPermissions()`, after permissions and student-profile ownership are resolved. The gate owns a fixed basename allowlist containing only `assessments.php`, `assessment-attempts.php`, `assessment-answers.php`, and `assessment-submit.php` for the `accepted` state; callers cannot opt themselves into that allowlist:

```php
public function studentIdentityForPermissions(array $permissions): array
{
    $user = $this->session->requireUser();
    if (($user['role'] ?? null) !== 'student') {
        throw new ApiException(403, 'PERMISSION_DENIED', 'Endpoint chỉ dành cho học viên.');
    }
    foreach (array_values(array_unique($permissions)) as $permission) {
        if (!is_string($permission) || trim($permission) === '') {
            throw new InvalidArgumentException('Student permission code must be a non-empty string.');
        }
        $this->permissions->require((string) $user['id'], $permission);
    }
    $identity = [
        'student_id' => $this->resolveStudentId((string) $user['id']),
        'user_id' => (string) $user['id'],
    ];
    $this->onboardingGate()->assertApiAllowed(
        $this->onboardingService()->reconcile($identity['student_id'], $identity['user_id'], $this->requestId, $_SERVER['REMOTE_ADDR'] ?? null),
        basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH))
    );
    return $identity;
}
```

Existing `studentId()` and `studentIdForPermissions()` callers continue to delegate to this method. Normal learner endpoints are denied while gated, while only the four fixed assessment endpoint basenames are allowed for `accepted` students.

- [ ] **Step 6: Implement CSRF-protected accept/decline endpoint**

Use the existing `SessionManager`, authenticated student resolution, `LearnerOnboardingService`, fixed redirects, and `app_href()`. On decline call `$service->decline(...)` before `$session->destroy()`; never accept a redirect destination from POST.

- [ ] **Step 7: Run tests and commit**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_gate_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_onboarding_endpoint_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_security_contract_test.php
git add src/Modules/Student/Service/LearnerOnboardingGate.php src/Bootstrap/StudentAppContext.php app/learner/api/LearnerApiContext.php app/learner/onboarding.php tests/learner_onboarding_gate_test.php tests/learner_onboarding_endpoint_test.php
git commit -m "feat(student): enforce mandatory onboarding gate"
```

---

### Task 5: Ordered Assessment Access and Completion Reconciliation

**Files:**
- Modify: `app/learner/api/v1/assessment-attempts.php:15-65`
- Modify: `app/learner/api/v1/assessment-answers.php:15-57`
- Modify: `app/learner/api/v1/assessment-submit.php:15-43`
- Modify: `tests/learner_assessment_api_test.php`

**Interfaces:**
- Consumes: `LearnerOnboardingService::assertAssessmentAccessible()`, `progress()`, and `reconcile()`.
- Produces: assessment submit payload key `onboarding` and safe `next_url`.

- [ ] **Step 1: Add failing API cases to the existing SQLite endpoint harness**

Extend the fixture with `learner_onboarding_states` and assert:

```php
$laterStart = execute_endpoint($attemptsEndpoint, $postServer, [], [
    'assessmentCode' => 'disc',
    'educationBand' => 'high',
], $acceptedSession, $dbPath);
api_test_assert($laterStart['status'] === 409, 'Cannot start DISC before Holland.');
api_test_assert(($laterStart['body']['error']['code'] ?? '') === 'ONBOARDING_SEQUENCE_REQUIRED', 'Returns sequence code.');
```

Then submit four valid owned attempts in order and assert counts `1`, `2`, `3`, `4`; only the fourth response has onboarding status `completed`. Add a second student's result and a duplicate Holland result to prove neither advances progress.

- [ ] **Step 2: Run assessment API test and verify RED**

Run: `& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_assessment_api_test.php`

Expected: new ordering/completion assertions fail.

- [ ] **Step 3: Gate assessment creation by normalized code**

In POST attempts, resolve identity through the existing centrally gated `studentIdentityForPermissions()`, validate the code, then call:

```php
$context->onboardingService()->assertAssessmentAccessible($studentId, $code);
```

For GET attempts, answers, and submit, load the owned attempt first, normalize its assessment code, and assert it is either the current required code or already submitted for result viewing. Never trust a code supplied separately from an attempt ID.

- [ ] **Step 4: Reconcile after authoritative submit**

```php
$identity = $context->studentIdentityForPermissions(['student_profile.update_own']);
$result = $context->assessmentService()->submit($identity['student_id'], $attemptId);
$onboarding = $context->onboardingService()->reconcile(
    $identity['student_id'],
    $identity['user_id'],
    $context->requestId(),
    $_SERVER['REMOTE_ADDR'] ?? null
);
$result['onboarding'] = $onboarding;
$result['next_url'] = $onboarding['status'] === 'completed'
    ? '/app/learner/discover.php?onboarding=completed'
    : $onboarding['next_url'];
```

The submit endpoint remains idempotent through the existing assessment write path; reconciliation must be repeatable.

- [ ] **Step 5: Run assessment suites and commit**

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_assessment_api_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_assessment_persistence_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_assessment_history_test.php
git add app/learner/api/LearnerApiContext.php app/learner/api/v1/assessment-attempts.php app/learner/api/v1/assessment-answers.php app/learner/api/v1/assessment-submit.php tests/learner_assessment_api_test.php
git commit -m "feat(student): reconcile ordered onboarding assessments"
```

---

### Task 6: Blocking Overview Dialog and Progress Hub

**Files:**
- Create: `assets/js/learner-onboarding.js`
- Create: `tests/learner_onboarding_ui_test.js`
- Modify: `app/learner/index.php:20-159`
- Modify: `app/learner/discover.php:20-154`
- Modify: `app/learner/includes/student-data.php:35-90`
- Modify: `app/learner/includes/sidebar.php:7-40`
- Modify: `assets/css/learner.css`
- Modify: `assets/js/learner-assessment.js`
- Modify: `tests/learner_assessment_ui_test.js`
- Modify: `tests/learner_security_contract_test.php`

**Interfaces:**
- Consumes: `$GLOBALS['learner_page_context']['onboarding']` and submit response `next_url`.
- Produces: server-rendered dialog/progress states and client redirect only to server-provided local learner URLs.

- [ ] **Step 1: Write failing UI/static tests**

```js
test('pending dialog is mandatory and posts only fixed decisions', () => {
  const source = fs.readFileSync(path.join(root, 'app', 'learner', 'index.php'), 'utf8');
  assert.match(source, /data-onboarding-dialog/);
  assert.match(source, /name="action" value="accept"/);
  assert.match(source, /name="action" value="decline"/);
  assert.doesNotMatch(source, /data-close-modal/);
  assert.match(source, /Hoàn thành đánh giá ban đầu/);
});

test('progress hub renders four server-owned states', () => {
  const source = fs.readFileSync(path.join(root, 'app', 'learner', 'discover.php'), 'utf8');
  assert.match(source, /data-onboarding-progress/);
  assert.match(source, /completed_count/);
  assert.match(source, /required_count/);
  assert.match(source, /Đăng xuất và tiếp tục sau/);
});
```

Add unit tests for `containDialogFocus(dialog, event)`, Escape suppression, and `safeOnboardingDestination(value)` accepting only `/app/learner/` paths. Add a learner-assessment controller test that submit redirects to a valid `next_url` and ignores `https://evil.example`.

- [ ] **Step 2: Run UI tests and verify RED**

```powershell
node --test tests\learner_onboarding_ui_test.js
node --test tests\learner_assessment_ui_test.js
```

Expected: new dialog/progress/redirect assertions fail.

- [ ] **Step 3: Render pending dialog only from server state**

```php
<?php $onboarding = $GLOBALS['learner_page_context']['onboarding'] ?? ['required' => false]; ?>
<?php if (($onboarding['required'] ?? false) && ($onboarding['status'] ?? '') === 'pending'): ?>
<div class="learner-onboarding" data-onboarding-dialog>
  <div class="learner-onboarding__backdrop" aria-hidden="true"></div>
  <section class="learner-onboarding__dialog" role="dialog" aria-modal="true" aria-labelledby="onboarding-title" aria-describedby="onboarding-description" tabindex="-1">
    <h2 id="onboarding-title">Hoàn thành đánh giá ban đầu</h2>
    <p id="onboarding-description">Hoàn thành bốn bài đánh giá để TalentHub hiểu sở thích, năng khiếu và cá nhân hóa lộ trình phát triển của bạn.</p>
    <ul><li>Holland</li><li>MBTI</li><li>DISC</li><li>Đa trí thông minh</li></ul>
    <p>Tiến độ được tự động lưu để bạn tiếp tục trong lần đăng nhập sau.</p>
    <div class="learner-onboarding__actions">
      <form method="post" action="<?= learner_escape(app_href('/app/learner/onboarding.php')); ?>">
        <input type="hidden" name="csrfToken" value="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ''); ?>">
        <button class="learner-btn learner-btn--primary" name="action" value="accept">Đồng ý và bắt đầu</button>
      </form>
      <form method="post" action="<?= learner_escape(app_href('/app/learner/onboarding.php')); ?>">
        <input type="hidden" name="csrfToken" value="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ''); ?>">
        <button class="learner-btn learner-btn--danger" name="action" value="decline">Từ chối và đăng xuất</button>
      </form>
    </div>
  </section>
</div>
<?php endif; ?>
```

Load `learner-onboarding.js` only when the dialog exists.

- [ ] **Step 4: Render accepted/completed progress from the server read model**

Render the four `items` using escaped text and fixed code-to-label maps. Use real links only for `completed`, `in_progress`, or `next`; render `locked` as non-interactive. On `?onboarding=completed` with server status `completed`, render `4/4`, a success message, and a fixed link to learner overview.

Filter `$learnerNav` during `pending` to overview only and during `accepted` to overview/discovery only; this is presentation behavior, not the security boundary.

- [ ] **Step 5: Implement focus containment and safe submit routing**

```js
function safeOnboardingDestination(value) {
  return typeof value === 'string' && value.startsWith('/app/learner/') && !value.startsWith('//')
    ? value
    : null;
}
```

On dialog initialization, focus the dialog, trap Tab/Shift+Tab among its buttons, and call `preventDefault()` for Escape. Do not add a close/backdrop handler. After assessment submit, navigate only when `safeOnboardingDestination(payload.next_url)` returns a value; otherwise retain the existing result flow.

- [ ] **Step 6: Add scoped responsive/accessibility styles**

Add `.learner-onboarding*` and `.learner-onboarding-progress*` styles only under `.learner-app`; include fixed full-screen backdrop, visible focus rings, reduced-motion compatibility, mobile stacked actions, state labels independent of color, and `overflow` handling for short viewports.

- [ ] **Step 7: Run UI, render, and security tests**

```powershell
node --test tests\learner_onboarding_ui_test.js
node --test tests\learner_assessment_ui_test.js
node --test tests\learner_discover_render_test.js
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_accessibility_render_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_security_contract_test.php
```

Expected: all tests pass with zero failures.

- [ ] **Step 8: Commit**

```powershell
git add app/learner/index.php app/learner/discover.php app/learner/includes/student-data.php app/learner/includes/sidebar.php assets/css/learner.css assets/js/learner-onboarding.js assets/js/learner-assessment.js tests/learner_onboarding_ui_test.js tests/learner_assessment_ui_test.js tests/learner_security_contract_test.php
git commit -m "feat(student): add mandatory onboarding experience"
```

---

### Task 7: Full Verification and Migration Handoff

**Files:**
- Modify only if verification finds a scoped defect in files already listed above.

**Interfaces:**
- Consumes: all Task 1–6 deliverables.
- Produces: verified feature branch plus an unapplied, approval-gated migration handoff.

- [ ] **Step 1: Run PHP syntax checks for every changed PHP file**

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
git diff --name-only HEAD~6..HEAD -- '*.php' | ForEach-Object { & $php -l $_; if ($LASTEXITCODE -ne 0) { throw "PHP lint failed: $_" } }
```

Expected: every file reports `No syntax errors detected`.

- [ ] **Step 2: Run the focused onboarding suite**

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests\learner_onboarding_migration_contract_test.php
& $php tests\learner_onboarding_service_test.php
& $php tests\learner_onboarding_registration_test.php
& $php tests\learner_onboarding_gate_test.php
& $php tests\learner_onboarding_endpoint_test.php
& $php tests\learner_assessment_api_test.php
node --test tests\learner_onboarding_ui_test.js tests\learner_assessment_ui_test.js
```

Expected: all focused tests pass.

- [ ] **Step 3: Run learner/auth regression suites**

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests\login_form_security_test.php
& $php tests\learner_assessment_persistence_test.php
& $php tests\learner_assessment_history_test.php
& $php tests\learner_assessment_catalog_test.php
& $php tests\learner_security_contract_test.php
& $php tests\student_portal_cross_role_contract_test.php
& $php tests\student_profile_ownership_api_test.php
node --test tests\learner_discover_render_test.js tests\learner_discover_style_test.js
```

Expected: all tests pass or retain their pre-existing documented environment-only skip.

- [ ] **Step 4: Verify scope and whitespace**

```powershell
git diff --check HEAD~6..HEAD
git status --short
git diff --name-only HEAD~6..HEAD
```

Expected: no whitespace errors; only planned onboarding, learner assessment/UI, migration, test, DCR, spec, and plan files appear. Unrelated pre-existing untracked files remain untouched.

- [ ] **Step 5: Stop at the database approval gate**

Do not run the migration against the primary/shared database. Present `docs/superpowers/database-change-requests/2026-08-24-learner-onboarding-state.md`, exact verification evidence, and request explicit approval to apply it. Until approval, report implementation as code-complete but database deployment pending.

If Step 1–4 exposes a defect, return to the owning task, add a failing regression assertion there, make the minimal scoped correction, rerun that task's commands, and use that task's explicit file list for the correction commit. If no defect is found, create no empty commit.
