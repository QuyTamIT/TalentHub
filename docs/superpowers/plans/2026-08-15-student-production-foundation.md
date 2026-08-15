# Student Production Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Chuyển Student Portal từ identity/mock runtime riêng sang shared authentication, shared database migrations, RBAC và API contract hiện có, đồng thời giữ nguyên hành vi của Teacher, School và Business/Enterprise.

**Architecture:** Shared core trong `src` là production composition root duy nhất. Student PHP pages dùng `StudentAppContext` để xác thực phiên và lấy baseline profile; browser dùng một learner API client thống nhất cho các request JSON. Readiness tooling chỉ audit shared schema/migration và không tự chạy một learner migration system riêng.

**Tech Stack:** PHP 8.3, PDO MySQL/MariaDB, PHP session, JSON API `/api/v1`, HTML/CSS/JavaScript thuần, PHP script tests, Node.js built-in test runner.

## Global Constraints

- Chỉ làm việc trên nhánh `feature/student` đã chứa `origin/develop` mới nhất.
- Không đưa các thay đổi đang dở hoặc file không thuộc task vào commit.
- Production dùng `DB_*`, `APP_ENV`, `SESSION_*`, `src/Database/Migration` và `Database/migrations/*.php` làm contract dùng chung.
- Không tạo thêm authentication, session, PDO factory, API envelope hoặc production migration runner riêng cho learner.
- `Database/migrations/*.php` là nguồn schema chuẩn; `Database/Talenthub_DB.sql` chỉ là snapshot và không được dùng để suy ra production schema khi hai nguồn khác nhau.
- Không tự động migrate database legacy khi audit chưa xác định chính xác duplicate, orphan, role mapping và migration history.
- Mọi production Student request phải lấy identity từ session; không nhận `student_id` từ browser và không fallback `student-demo-001`.
- Mock chỉ được bật rõ ràng trong test/demo; lỗi production database không được fallback sang mock.
- Không sửa giao diện hoặc nghiệp vụ riêng trong `app/teacher`, `app/school`, `app/enterprise`.
- Shared-core changes phải chạy regression cho Student, Teacher, School và Business/Enterprise.
- Dùng TDD: viết test thất bại, xác nhận đúng lý do thất bại, implement tối thiểu, chạy test xanh rồi commit nhỏ.
- Không commit `.env`, mật khẩu, DSN, cookie, dữ liệu cá nhân thật hoặc output chứa secret.
- AI recommendation vẫn bị khóa; foundation chỉ hiển thị trạng thái chưa đủ dữ liệu nếu cần.

---

## Plan Series and Dependency Order

Spec tổng thể được chia thành các implementation plan độc lập để mỗi phần có release gate rõ ràng:

1. **Plan hiện tại — Production Foundation:** shared DB readiness, session/RBAC, Student page context, API client và baseline profile.
2. **Profile and Talent Passport:** dashboard/profile aggregate, field ownership, consent và sharing.
3. **Activities and Check-in:** catalog, registration lifecycle, QR, experience và feedback.
4. **Assessment and Teacher Evaluation:** attempts, answers, scoring, published evaluation.
5. **Opportunities and Applications:** School/Enterprise opportunity, application, snapshot và history.
6. **Notifications, Badges and Statistics:** notification preferences, rules, aggregates.
7. **Frontend Hardening and Release:** accessibility, error states, security, performance và full cross-role regression.

Chỉ viết plan tiếp theo sau khi plan hiện tại đạt Definition of Done. Database audit có thể buộc tạo một plan reconciliation riêng nếu database thật đang dùng legacy snapshot thay vì migration-managed schema.

## File and Responsibility Map

### Files created by this plan

- `src/Bootstrap/StudentAppContext.php`: production page composition root cho Student; start session, refresh identity, check role/permission và trả profile baseline.
- `app/learner/includes/runtime-unavailable.php`: standalone HTTP 503 page an toàn khi shared database không khả dụng.
- `app/learner/data/Support/SharedStudentAdapter.php`: chuyển shared API/service camelCase payload thành learner view shape hiện tại.
- `assets/js/learner-api.js`: JSON client dùng chung cho Student frontend, quản lý CSRF và normalized API errors.
- `tests/learner_shared_readiness_test.php`: unit test canonical Phase 1 schema và connection-factory behavior.
- `tests/learner_student_app_context_test.php`: contract test cho context, redirect targets và không có demo fallback.
- `tests/learner_shared_student_adapter_test.php`: mapping test giữa shared Student service và learner view.
- `tests/learner_api_client_test.js`: Node tests cho API client.
- `tests/learner_foundation_mysql_test.php`: MariaDB integration test cho auth, RBAC, profile ownership và baseline dashboard.
- `bin/smoke-student-foundation.php`: safe smoke command dùng fixture test và không in secret.
- `docs/superpowers/readiness/student-production-foundation.md`: kết quả audit/migration/test của lần triển khai.

### Files modified by this plan

- `app/learner/data/Readiness/PhaseRequirements.php`: dùng canonical shared schema contract.
- `app/learner/data/Readiness/ReadinessChecker.php`: nhận shared PDO factory thay vì tự dựng DSN `TALENTHUB_*`.
- `app/learner/tools/readiness-check.php`: load root bootstrap/config và shared connection.
- `app/learner/data/bootstrap.php`: load adapter/readiness classes nhưng không trở thành production composition root.
- `app/learner/includes/student-data.php`: explicit mock mode cho test; production boot qua `StudentAppContext` và map profile thật.
- `app/learner/includes/header.php`: hiển thị tên/initials từ identity thật, thêm account/logout hooks và API boot metadata.
- `assets/js/learner.js`: khởi tạo API client và xử lý expired session/toast dùng chung.
- `src/Bootstrap/Application.php`: chỉ chỉnh nếu contract test chứng minh route Student baseline thiếu dependency/response; không refactor route của vai trò khác.
- `tests/learner_readiness_test.php`: bỏ contract `TALENTHUB_*` cũ và kiểm tra shared-core connection injection.
- Các learner render tests: bật mock mode rõ ràng trong test harness.

### Files inspected but not modified by default

- `Database/migrations/20260814000100_create_identity_catalogs.php`
- `Database/migrations/20260814000200_create_users_and_role_permissions.php`
- `Database/migrations/20260814000300_create_organizations_and_classes.php`
- `Database/migrations/20260814000400_create_profiles_and_memberships.php`
- `Database/seeds/System/RolePermissionSeeder.php`
- `Database/Talenthub_DB.sql`
- `app/teacher/**`, `app/school/**`, `app/enterprise/**`

---

### Task 1: Unify Learner Readiness with the Shared Database Contract

**Files:**

- Create: `tests/learner_shared_readiness_test.php`
- Modify: `tests/learner_readiness_test.php`
- Test: `tests/learner_shared_readiness_test.php`

**Interfaces:**

- Consumes: shared schema declared by migrations `20260814000100` through `20260814000400`.
- Produces: executable assertions for Phase 1 tables, columns and indexes; later readiness code must satisfy these exact names.

- [ ] **Step 1: Write the failing canonical schema test**

Create `tests/learner_shared_readiness_test.php` with the complete contract below:

```php
<?php
declare(strict_types=1);

use TalentHub\Learner\Data\Readiness\PhaseRequirements;

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function shared_readiness_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$phase = (new PhaseRequirements())->forPhase(1);

shared_readiness_assert($phase['requires_database'] === true, 'phase 1 requires shared database');
shared_readiness_assert($phase['config_keys'] === [], 'readiness must not define a second TALENTHUB_* database vocabulary');

foreach (['roles', 'permissions', 'users', 'role_permissions', 'schools', 'classes', 'student_profiles'] as $table) {
    shared_readiness_assert(in_array($table, $phase['tables'], true), "phase 1 requires {$table}");
}

shared_readiness_assert($phase['columns']['users'] === ['id', 'roleId', 'email', 'passwordHash', 'fullName', 'status'], 'users uses canonical roleId schema');
shared_readiness_assert($phase['columns']['student_profiles'] === ['id', 'userId', 'classId', 'dateOfBirth', 'phone', 'studyStatus'], 'student profile contract matches shared migration');
shared_readiness_assert(in_array('uq_users_email', $phase['indexes']['users'], true), 'users email uniqueness is required');
shared_readiness_assert(in_array('uq_student_profiles_user', $phase['indexes']['student_profiles'], true), 'one profile per user is required');

echo "learner_shared_readiness_test: OK\n";
```

- [ ] **Step 2: Run the test and confirm the expected failure**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_shared_readiness_test.php
```

Expected: exit `1` with `phase 1 requires roles` or `users uses canonical roleId schema`, proving the old learner readiness contract still follows the legacy snapshot.

- [ ] **Step 3: Update the existing readiness test contract**

In `tests/learner_readiness_test.php`, replace the old configuration assertion:

```php
readiness_assert(
    $requirements->forPhase(1)['config_keys'] === [],
    'phase 1 reuses shared DB_* configuration instead of learner-specific variables'
);
```

Replace any Phase 1 CLI expectation that assumes `TALENTHUB_LEARNER_SOURCE` with:

```php
readiness_assert(
    in_array($blockedCliExitCode, [0, 3], true),
    'phase 1 is READY with a canonical shared database or BLOCKED when the shared database is unavailable'
);
```

- [ ] **Step 4: Run both focused tests to preserve the red baseline**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_shared_readiness_test.php
& 'D:\xampp\php\php.exe' tests\learner_readiness_test.php
```

Expected: the new canonical contract fails; unrelated readiness primitives still pass until the CLI assertion reaches the shared-connection gap.

#### Implementation after the red contract

**Files:**

- Modify: `app/learner/data/Readiness/PhaseRequirements.php`
- Modify: `app/learner/data/Readiness/ReadinessChecker.php`
- Modify: `app/learner/tools/readiness-check.php`
- Modify: `tests/learner_readiness_test.php`
- Test: `tests/learner_shared_readiness_test.php`

**Interfaces:**

- Consumes: `TalentHub\Database\Connection`, root `config/database.php`, and `callable(): PDO`.
- Produces: `ReadinessChecker::check(int $phase, string $repositoryRoot, callable $pdoFactory): ReadinessResult`.

- [ ] **Step 5: Replace Phase 1 with the canonical shared schema definition**

In `PhaseRequirements::__construct()`, define Phase 1 exactly as:

```php
1 => $this->definition(true, [], [
    'roles', 'permissions', 'users', 'role_permissions', 'schools', 'classes', 'student_profiles',
], [
    'roles' => ['id', 'code'],
    'permissions' => ['id', 'code'],
    'users' => ['id', 'roleId', 'email', 'passwordHash', 'fullName', 'status'],
    'role_permissions' => ['roleId', 'permissionId'],
    'schools' => ['id', 'name', 'status'],
    'classes' => ['id', 'schoolId', 'name', 'gradeLevel', 'academicYear', 'status'],
    'student_profiles' => ['id', 'userId', 'classId', 'dateOfBirth', 'phone', 'studyStatus'],
], [
    'roles' => ['uq_roles_code'],
    'permissions' => ['uq_permissions_code'],
    'users' => ['uq_users_email', 'idx_users_role_status'],
    'role_permissions' => ['PRIMARY'],
    'classes' => ['idx_classes_school_status'],
    'student_profiles' => ['uq_student_profiles_user', 'idx_student_profiles_class_status'],
]),
```

Keep Phases 2-11 intact for now except set their `config_keys` to `[]`; later slice plans will revise their table contracts when those domains are implemented.

- [ ] **Step 6: Inject a shared PDO factory into `ReadinessChecker`**

Replace `ReadinessChecker::check()` with this complete signature and database section:

```php
public function check(int $phase, string $repositoryRoot, callable $pdoFactory): ReadinessResult
{
    $definition = $this->requirements->forPhase($phase);
    $result = new ReadinessResult($phase);
    $scope = $this->scopeGuard->inspectWorkspace($repositoryRoot);

    if ($scope['allowed']) {
        $result->addPass('scope', 'No changes in protected role paths.');
    } else {
        foreach ($scope['forbidden_paths'] as $path) {
            $result->addFailure('scope', "Protected role path changed: {$path}");
        }
    }

    if (!$definition['requires_database']) {
        $result->addPass('phase', 'Phase 0 does not require a live database.');
        return $result;
    }

    try {
        $pdo = $pdoFactory();
        if (!$pdo instanceof \PDO) {
            throw new \RuntimeException('PDO factory did not return PDO.');
        }
    } catch (\Throwable) {
        $result->addFailure('database.connection', 'Shared database connection is unavailable.', true);
        return $result;
    }

    $result->addPass('database.connection', 'Shared database connection is available.');
    $inspector = new SchemaInspector($pdo, (string) $pdo->query('SELECT DATABASE()')->fetchColumn());

    try {
        foreach ($definition['tables'] as $table) {
            if (!$inspector->hasTable($table)) {
                $result->addFailure('schema.table', "Missing table: {$table}");
            }
        }
        foreach ($definition['columns'] as $table => $columns) {
            foreach ($columns as $column) {
                if (!$inspector->hasColumn($table, $column)) {
                    $result->addFailure('schema.column', "Missing column: {$table}.{$column}");
                }
            }
        }
        foreach ($definition['indexes'] as $table => $indexes) {
            foreach ($indexes as $index) {
                if (!$inspector->hasIndex($table, $index)) {
                    $result->addFailure('schema.index', "Missing index: {$table}.{$index}");
                }
            }
        }
    } catch (\Throwable) {
        $result->addFailure('database.schema', 'Shared database schema inspection is unavailable.', true);
    }

    return $result;
}
```

Do not accept an environment array and do not instantiate raw PDO from learner-specific variables.

- [ ] **Step 7: Make the CLI boot shared configuration**

At the top of `app/learner/tools/readiness-check.php`, load root bootstrap before learner bootstrap:

```php
$repositoryRoot = dirname(__DIR__, 3);
require_once $repositoryRoot . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/data/bootstrap.php';

use TalentHub\Database\Connection;
```

Replace the checker call with:

```php
$checker = new ReadinessChecker(new PhaseRequirements(), new GitScopeGuard());
$result = $checker->check(
    $phase,
    $repositoryRoot,
    static function () use ($repositoryRoot): PDO {
        $config = require $repositoryRoot . '/config/database.php';
        return (new Connection($config))->connect();
    }
);
```

- [ ] **Step 8: Update unit tests to inject deterministic PDO factories**

For Phase 0:

```php
$phaseZero = $checker->check(0, dirname(__DIR__), static fn (): PDO => throw new RuntimeException('must not connect'));
readiness_assert($phaseZero->status() === 'READY', 'phase 0 does not invoke database factory');
```

For unavailable database:

```php
$unavailable = $checker->check(1, dirname(__DIR__), static fn (): PDO => throw new RuntimeException('offline'));
readiness_assert($unavailable->status() === 'BLOCKED', 'shared database outage is blocked');
readiness_assert($unavailable->exitCode() === 3, 'blocked database returns exit 3');
```

- [ ] **Step 9: Run focused readiness tests**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_shared_readiness_test.php
& 'D:\xampp\php\php.exe' tests\learner_readiness_test.php
& 'D:\xampp\php\php.exe' app\learner\tools\readiness-check.php --phase=0 --format=text
```

Expected: both tests print `OK`; Phase 0 prints `READY`.

- [ ] **Step 10: Run Phase 1 against the configured shared database**

Run:

```powershell
& 'D:\xampp\php\php.exe' app\learner\tools\readiness-check.php --phase=1 --format=json
```

Expected when MySQL is unavailable: exit `3`, status `BLOCKED`, no DSN/password in output. Expected when connected to legacy schema: exit `2` with exact missing canonical tables/columns/indexes. Expected when canonical shared migrations are applied: exit `0`, status `READY`.

- [ ] **Step 11: Commit readiness unification**

```powershell
git add app/learner/data/Readiness/PhaseRequirements.php app/learner/data/Readiness/ReadinessChecker.php app/learner/tools/readiness-check.php tests/learner_readiness_test.php tests/learner_shared_readiness_test.php
git commit -m "refactor(student): use shared database readiness contract"
```

---

### Task 2: Audit Migration State Before Any Database Mutation

**Files:**

- Create: `docs/superpowers/readiness/student-production-foundation.md`
- Inspect: `Database/Talenthub_DB.sql`
- Inspect: `Database/migrations/*.php`
- Inspect: `Database/seeds/System/RolePermissionSeeder.php`

**Interfaces:**

- Consumes: `bin/migrate.php status|validate`, Phase 1 readiness JSON, read-only information schema output.
- Produces: one recorded decision: `CANONICAL_READY`, `CANONICAL_PENDING`, `LEGACY_RECONCILIATION_REQUIRED`, or `DATABASE_BLOCKED`.

- [ ] **Step 1: Confirm no protected role path is modified**

Run:

```powershell
git status --short --branch
git diff --name-only
git diff --cached --name-only
```

Expected: no path under `app/teacher`, `app/school`, or `app/enterprise`.

- [ ] **Step 2: Run migration status and validation without applying changes**

Run:

```powershell
& 'D:\xampp\php\php.exe' bin\migrate.php status
& 'D:\xampp\php\php.exe' bin\migrate.php validate
```

Expected canonical database: all known migrations are `applied` and validation prints `[OK] validation: OK`, or known migrations are `pending` on an empty migration-managed database. A connection failure is `DATABASE_BLOCKED`. Missing migration history with pre-existing `users`/`student_profiles` is `LEGACY_RECONCILIATION_REQUIRED`.

- [ ] **Step 3: Run read-only schema audit**

Run:

```powershell
& 'D:\xampp\php\php.exe' app\learner\tools\readiness-check.php --phase=1 --format=json
```

Record only table/column/index names. Do not copy connection strings or credentials into the readiness document.

- [ ] **Step 4: Apply the hard stop rule**

Use this exact decision table:

| State | Condition | Action |
|---|---|---|
| `CANONICAL_READY` | migrations validate and Phase 1 is READY | continue Task 3 |
| `CANONICAL_PENDING` | empty/migration-managed DB with pending files | run approved shared migrations and seeds, then re-audit |
| `LEGACY_RECONCILIATION_REQUIRED` | pre-existing legacy tables conflict with migration preflight or `users.roles` exists without `users.roleId` | stop this plan before mutation; write a separate reconciliation spec/plan |
| `DATABASE_BLOCKED` | cannot connect | stop database-dependent work and report environment blocker |

- [ ] **Step 5: For `CANONICAL_PENDING` only, apply shared migrations and system seed**

Run:

```powershell
& 'D:\xampp\php\php.exe' bin\migrate.php migrate
& 'D:\xampp\php\php.exe' bin\seed.php
& 'D:\xampp\php\php.exe' bin\migrate.php validate
& 'D:\xampp\php\php.exe' app\learner\tools\readiness-check.php --phase=1 --format=json
```

Expected: migration and seed commands exit `0`; final Phase 1 status is `READY`. Never run these commands for a legacy/conflicting database.

- [ ] **Step 6: Write the audit record**

Create `docs/superpowers/readiness/student-production-foundation.md` with actual command timestamps and sanitized results using this structure:

```markdown
# Student Production Foundation Readiness

- Audited at: 2026-08-15 Asia/Bangkok
- Branch: feature/student
- Commit: giá trị nguyên văn từ `git rev-parse --short HEAD`
- Database state: CANONICAL_READY
- Migration validation: PASS
- Phase 1 readiness: READY
- Protected role paths changed: none
- Secrets recorded: none

## Evidence

- `bin/migrate.php validate`: exit 0
- learner readiness Phase 1: exit 0
- canonical `users.roleId`: present
- legacy `users.roles`: absent in runtime database
- `uq_users_email`: present
- `uq_student_profiles_user`: present
```

Use the actual database state; do not write `CANONICAL_READY` if evidence differs.

- [ ] **Step 7: Commit the audit record only when the gate is ready**

```powershell
git add docs/superpowers/readiness/student-production-foundation.md
git commit -m "docs(student): record production foundation readiness"
```

If the state is blocked or legacy, do not claim readiness and do not continue to Task 3.

---

### Task 3: Add a Shared-Core Student Page Context

**Files:**

- Create: `src/Bootstrap/StudentAppContext.php`
- Create: `tests/learner_student_app_context_test.php`
- Test: `tests/learner_student_app_context_test.php`

**Interfaces:**

- Consumes: `SessionManager`, `AuthService`, `PermissionService`, `StudentProfileService`.
- Produces: `StudentAppContext::boot(): array{user:array,student:array,dashboard:array,csrfToken:string}`.

- [ ] **Step 1: Write the failing context contract test**

Create `tests/learner_student_app_context_test.php`:

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

function student_context_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$path = dirname(__DIR__) . '/src/Bootstrap/StudentAppContext.php';
student_context_assert(is_file($path), 'StudentAppContext exists');
$source = file_get_contents($path);
student_context_assert(is_string($source), 'StudentAppContext is readable');
student_context_assert(str_contains($source, "['role'] ?? null) !== 'student'"), 'context rejects wrong role');
student_context_assert(str_contains($source, "student_profile.read_own"), 'context checks Student permission');
student_context_assert(str_contains($source, "AuthPortalRouter::destination"), 'context reuses shared portal routing');
student_context_assert(!str_contains($source, 'student-demo-001'), 'production context has no demo identity fallback');
student_context_assert(!str_contains($source, 'TALENTHUB_DB_'), 'context has no second DB configuration');

echo "learner_student_app_context_test: OK\n";
```

- [ ] **Step 2: Run the test to verify it fails**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_student_app_context_test.php
```

Expected: exit `1` with `StudentAppContext exists`.

- [ ] **Step 3: Implement `StudentAppContext`**

Create `src/Bootstrap/StudentAppContext.php` with these public behaviors:

```php
<?php
declare(strict_types=1);

namespace TalentHub\Bootstrap;

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\StudentRepository;
use TalentHub\Modules\Student\Service\StudentProfileService;
use TalentHub\Rbac\Service\PermissionService;

final class StudentAppContext
{
    private SessionManager $session;
    private AuthService $auth;
    private PermissionService $permissions;
    private StudentProfileService $students;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);
        $pdo = (new Connection(require $root . '/config/database.php'))->connect();
        $this->session = new SessionManager(require $root . '/config/session.php');
        $this->session->start();
        $this->auth = new AuthService(new AuthRepository($pdo));
        $this->permissions = new PermissionService($pdo);
        $this->students = new StudentProfileService(new StudentRepository($pdo));
    }

    /** @return array{user:array<string,mixed>,student:array<string,mixed>,dashboard:array<string,mixed>,csrfToken:string} */
    public function boot(): array
    {
        $cached = $this->session->user();
        if ($cached === null) {
            $this->redirectToLogin();
        }
        if (($cached['role'] ?? null) !== 'student') {
            header('Location: ' . AuthPortalRouter::destination((string) ($cached['role'] ?? '')));
            exit;
        }

        try {
            $user = $this->auth->current((string) $cached['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 401) {
                $this->session->destroy();
                $this->redirectToLogin();
            }
            throw $exception;
        }
        $this->session->refreshUser($user);
        $this->permissions->require($user['id'], 'student_profile.read_own');

        try {
            $student = $this->students->get($user['id']);
            $dashboard = $this->students->dashboard($user['id']);
        } catch (ApiException $exception) {
            if ($exception->status === 404) {
                header('Location: /role-selection.php?error=student_profile_missing');
                exit;
            }
            throw $exception;
        }

        return [
            'user' => $user,
            'student' => $student,
            'dashboard' => $dashboard,
            'csrfToken' => $this->session->csrfToken(),
        ];
    }

    private function redirectToLogin(): never
    {
        $next = $_SERVER['REQUEST_URI'] ?? '/app/learner/index.php';
        header('Location: /login.php?next=' . urlencode($next));
        exit;
    }
}
```

Handle expired/deactivated sessions and missing Student profiles as redirects inside the context. Let `DatabaseConnectionException` reach the page boundary in Task 5 so it can render the standalone safe 503 state.

- [ ] **Step 4: Run context contract and PHP lint**

Run:

```powershell
& 'D:\xampp\php\php.exe' -l src\Bootstrap\StudentAppContext.php
& 'D:\xampp\php\php.exe' tests\learner_student_app_context_test.php
```

Expected: lint says no syntax errors; test prints `OK`.

- [ ] **Step 5: Commit the context**

```powershell
git add src/Bootstrap/StudentAppContext.php tests/learner_student_app_context_test.php
git commit -m "feat(student): add shared authenticated page context"
```

---

### Task 4: Map the Shared Student Payload into Existing Learner Views

**Files:**

- Create: `app/learner/data/Support/SharedStudentAdapter.php`
- Create: `tests/learner_shared_student_adapter_test.php`
- Modify: `app/learner/data/bootstrap.php`
- Test: `tests/learner_shared_student_adapter_test.php`

**Interfaces:**

- Consumes: `StudentProfileService::get()` response.
- Produces: `SharedStudentAdapter::toView(array $profile, array $dashboard): array` with existing learner keys.

- [ ] **Step 1: Write the failing adapter test**

Create a test with a fixed payload and exact output assertions:

```php
<?php
declare(strict_types=1);

use TalentHub\Learner\Data\Support\SharedStudentAdapter;

require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function shared_adapter_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$view = SharedStudentAdapter::toView([
    'id' => '11111111-1111-4111-8111-111111111111',
    'userId' => '22222222-2222-4222-8222-222222222222',
    'email' => 'student@example.test',
    'fullName' => 'Nguyễn Văn A',
    'school' => ['id' => '33333333-3333-4333-8333-333333333333', 'name' => 'TalentHub School'],
    'class' => ['id' => '44444444-4444-4444-8444-444444444444', 'name' => '12A1'],
    'dateOfBirth' => '2008-01-02',
    'phone' => '0900000000',
    'studyStatus' => 'active',
], [
    'metrics' => ['profileCompletion' => 100, 'studyStatus' => 'active'],
]);

shared_adapter_assert($view['id'] === '11111111-1111-4111-8111-111111111111', 'student profile id is preserved');
shared_adapter_assert($view['user_id'] === '22222222-2222-4222-8222-222222222222', 'user id is mapped');
shared_adapter_assert($view['name'] === 'Nguyễn Văn A', 'fullName maps to name');
shared_adapter_assert($view['initials'] === 'A', 'initials are deterministic');
shared_adapter_assert($view['class'] === '12A1', 'class name is mapped');
shared_adapter_assert($view['school'] === 'TalentHub School', 'school name is mapped');
shared_adapter_assert($view['verified'] === false, 'foundation does not invent verification');
shared_adapter_assert($view['streak_days'] === 0, 'unknown metrics use safe zero');

echo "learner_shared_student_adapter_test: OK\n";
```

- [ ] **Step 2: Run the test and verify the class-not-found failure**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_shared_student_adapter_test.php
```

Expected: failure because `SharedStudentAdapter` is not loaded.

- [ ] **Step 3: Implement the adapter**

Create `SharedStudentAdapter.php`:

```php
<?php
declare(strict_types=1);

namespace TalentHub\Learner\Data\Support;

final class SharedStudentAdapter
{
    /** @return array<string,mixed> */
    public static function toView(array $profile, array $dashboard): array
    {
        $name = trim((string) ($profile['fullName'] ?? ''));
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $last = $parts === [] ? '?' : (string) end($parts);
        $initial = mb_strtoupper(mb_substr($last, 0, 1));

        return [
            'id' => (string) ($profile['id'] ?? ''),
            'school_id' => (string) ($profile['school']['id'] ?? ''),
            'class_id' => (string) ($profile['class']['id'] ?? ''),
            'user_id' => (string) ($profile['userId'] ?? ''),
            'study_status' => (string) ($profile['studyStatus'] ?? 'unknown'),
            'name' => $name,
            'initials' => $initial,
            'class' => (string) ($profile['class']['name'] ?? ''),
            'school' => (string) ($profile['school']['name'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
            'date_of_birth' => (string) ($profile['dateOfBirth'] ?? ''),
            'location' => '',
            'verified' => false,
            'streak_days' => 0,
            'experience_hours' => 0,
            'profile_completion' => (int) ($dashboard['metrics']['profileCompletion'] ?? 0),
        ];
    }
}
```

Add one `require_once` beside other support classes in `app/learner/data/bootstrap.php`:

```php
require_once $learnerDataRoot . '/Support/SharedStudentAdapter.php';
```

- [ ] **Step 4: Run adapter and existing foundation tests**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_shared_student_adapter_test.php
& 'D:\xampp\php\php.exe' tests\learner_data_foundation_test.php
```

Expected: both print `OK`.

- [ ] **Step 5: Commit the adapter**

```powershell
git add app/learner/data/Support/SharedStudentAdapter.php app/learner/data/bootstrap.php tests/learner_shared_student_adapter_test.php
git commit -m "feat(student): adapt shared profile to learner views"
```

---

### Task 5: Boot Every Student Page from the Authenticated Shared Context

**Files:**

- Modify: `app/learner/includes/student-data.php`
- Modify: `app/learner/includes/header.php`
- Create: `app/learner/includes/runtime-unavailable.php`
- Modify: `tests/learner_*_render_test.php`
- Test: `tests/learner_database_render_test.php`

**Interfaces:**

- Consumes: `StudentAppContext::boot()` and `SharedStudentAdapter::toView()`.
- Produces: `$student` from a real logged-in Student in local/staging/production; explicit mock only when `TALENTHUB_LEARNER_SOURCE=mock` and `APP_ENV=test`.

- [ ] **Step 1: Add failing production-bootstrap assertions**

In `tests/learner_database_render_test.php`, add source assertions:

```php
$studentDataSource = file_get_contents(dirname(__DIR__) . '/app/learner/includes/student-data.php');
database_render_assert(is_string($studentDataSource), 'student data source is readable');
database_render_assert(str_contains($studentDataSource, 'StudentAppContext'), 'production pages boot shared Student context');
database_render_assert(str_contains($studentDataSource, "\$appEnvironment === 'test'"), 'mock is restricted to test environment');
database_render_assert(!str_contains($studentDataSource, "source' => 'database'"), 'page does not create a second learner database configuration');
database_render_assert(str_contains($studentDataSource, 'DatabaseConnectionException'), 'database outage has a controlled page boundary');
database_render_assert(str_contains($studentDataSource, 'runtime-unavailable.php'), 'database outage renders the safe 503 page');
```

- [ ] **Step 2: Run the focused render test and verify it fails**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_database_render_test.php
```

Expected: failure at `production pages boot shared Student context`.

- [ ] **Step 3: Replace the top-level Student selection in `student-data.php`**

Keep existing mock arrays for deterministic test/demo rendering, but select the source with this exact boundary before `$studentRecord` is built:

```php
$repositoryRoot = dirname(__DIR__, 3);
require_once $repositoryRoot . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/data/bootstrap.php';

$appEnvironment = strtolower((string) (getenv('APP_ENV') ?: ''));
$learnerSource = strtolower((string) (getenv('TALENTHUB_LEARNER_SOURCE') ?: 'database'));
$useMock = $appEnvironment === 'test' && $learnerSource === 'mock';

if ($useMock) {
    $studentRecord = learner_repository_factory()->student([$studentMock])->findById($studentMock['id']);
    $student = \TalentHub\Learner\Data\ReadModel\StudentReadModel::fromRecord($studentRecord ?? []);
} else {
    try {
        $context = (new \TalentHub\Bootstrap\StudentAppContext())->boot();
    } catch (\TalentHub\Database\Exception\DatabaseConnectionException) {
        require __DIR__ . '/runtime-unavailable.php';
        exit;
    }
    $student = \TalentHub\Learner\Data\Support\SharedStudentAdapter::toView(
        $context['student'],
        $context['dashboard']
    );
    $GLOBALS['learner_page_context'] = $context;
}
```

Remove the old unconditional mock `$studentRecord` assignment. Do not delete the remaining mock domain arrays yet; later vertical slices will replace them.

- [ ] **Step 4: Add the safe runtime-unavailable page**

Create `app/learner/includes/runtime-unavailable.php` as a standalone response that contains no exception text:

```php
<?php
declare(strict_types=1);

http_response_code(503);
header('Retry-After: 30');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dịch vụ tạm thời gián đoạn | TalentHub</title>
    <link rel="stylesheet" href="/assets/css/home.css">
    <link rel="stylesheet" href="/assets/css/learner.css">
</head>
<body class="learner-app">
<main class="learner-content" id="main-content">
    <section class="learner-card learner-not-found" role="alert">
        <h1>Dịch vụ dữ liệu tạm thời không khả dụng</h1>
        <p>TalentHub chưa thể tải dữ liệu học viên. Vui lòng thử lại sau.</p>
        <a class="learner-btn learner-btn--primary" href="/app/learner/index.php">Thử lại</a>
    </section>
</main>
</body>
</html>
```

- [ ] **Step 5: Make render tests opt into mock explicitly**

At the start of every learner PHP render test process, set:

```php
putenv('APP_ENV=test');
putenv('TALENTHUB_LEARNER_SOURCE=mock');
$_ENV['APP_ENV'] = 'test';
$_ENV['TALENTHUB_LEARNER_SOURCE'] = 'mock';
$_SERVER['APP_ENV'] = 'test';
$_SERVER['TALENTHUB_LEARNER_SOURCE'] = 'mock';
```

Do not set DB credentials in render tests.

- [ ] **Step 6: Update header account semantics**

Change the avatar label to use the current name:

```php
<button class="learner-avatar" type="button" aria-label="Mở tài khoản <?= learner_escape($student['name']); ?>" data-learner-account>
    <?= learner_escape($student['initials']); ?>
</button>
```

For production context, emit safe boot metadata without user email or DB details:

```php
<?php if (isset($GLOBALS['learner_page_context'])): ?>
<script id="learner-session-boot" type="application/json"><?= json_encode([
    'csrfToken' => $GLOBALS['learner_page_context']['csrfToken'],
    'apiBase' => '/api/v1',
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<?php endif; ?>
```

- [ ] **Step 7: Run all learner render/data tests**

Run:

```powershell
$php='D:\xampp\php\php.exe'
Get-ChildItem tests\learner_*_test.php | Sort-Object Name | ForEach-Object { & $php $_.FullName; if($LASTEXITCODE -ne 0){exit 1} }
```

Expected: every learner PHP test exits `0`; test mode remains deterministic.

- [ ] **Step 8: Commit page bootstrap changes**

```powershell
git add app/learner/includes/student-data.php app/learner/includes/header.php app/learner/includes/runtime-unavailable.php tests/learner_*_render_test.php tests/learner_database_render_test.php
git commit -m "feat(student): require shared authenticated runtime"
```

---

### Task 6: Add the Shared Learner JSON API Client

**Files:**

- Create: `assets/js/learner-api.js`
- Create: `tests/learner_api_client_test.js`
- Modify: `assets/js/learner.js`
- Modify: learner page script includes as needed
- Test: `tests/learner_api_client_test.js`

**Interfaces:**

- Produces: `createLearnerApiClient({baseUrl, csrfToken, fetchImpl, onUnauthorized})`.
- Methods: `get(path): Promise<unknown>`, `send(method, path, body): Promise<unknown>`, `setCsrfToken(token): void`.
- Error: `LearnerApiError` with `status`, `code`, `details`, `requestId`.

- [ ] **Step 1: Write failing Node tests**

Create `tests/learner_api_client_test.js` with cases for success, CSRF, normalized validation and expired session:

```js
const test = require('node:test');
const assert = require('node:assert/strict');
const { createLearnerApiClient, LearnerApiError } = require('../assets/js/learner-api.js');

test('GET returns response data and sends same-origin credentials', async () => {
  const calls = [];
  const client = createLearnerApiClient({
    baseUrl: '/api/v1',
    csrfToken: 'csrf-test',
    fetchImpl: async (url, options) => {
      calls.push({ url, options });
      return { ok: true, status: 200, json: async () => ({ data: { id: 'student-1' }, meta: { requestId: 'req-1' } }) };
    },
  });
  assert.deepEqual(await client.get('/students/me'), { id: 'student-1' });
  assert.equal(calls[0].options.credentials, 'same-origin');
  assert.equal(calls[0].options.headers.Accept, 'application/json');
});

test('PATCH sends JSON and CSRF token', async () => {
  let request;
  const client = createLearnerApiClient({
    baseUrl: '/api/v1', csrfToken: 'csrf-test',
    fetchImpl: async (url, options) => {
      request = { url, options };
      return { ok: true, status: 200, json: async () => ({ data: { updated: true }, meta: { requestId: 'req-2' } }) };
    },
  });
  await client.send('PATCH', '/students/me', { fullName: 'Nguyễn Văn A' });
  assert.equal(request.options.headers['Content-Type'], 'application/json');
  assert.equal(request.options.headers['X-CSRF-Token'], 'csrf-test');
  assert.equal(request.options.body, JSON.stringify({ fullName: 'Nguyễn Văn A' }));
});

test('API errors preserve safe contract and notify on 401', async () => {
  let unauthorized = 0;
  const client = createLearnerApiClient({
    baseUrl: '/api/v1', csrfToken: 'csrf-test', onUnauthorized: () => { unauthorized += 1; },
    fetchImpl: async () => ({
      ok: false, status: 401,
      json: async () => ({ error: { code: 'SESSION_EXPIRED', message: 'Phiên đăng nhập đã hết hạn.' }, meta: { requestId: 'req-3' } }),
    }),
  });
  await assert.rejects(client.get('/students/me'), error => {
    assert.ok(error instanceof LearnerApiError);
    assert.equal(error.status, 401);
    assert.equal(error.code, 'SESSION_EXPIRED');
    assert.equal(error.requestId, 'req-3');
    return true;
  });
  assert.equal(unauthorized, 1);
});
```

- [ ] **Step 2: Run the test and confirm module-not-found**

Run:

```powershell
node --test tests\learner_api_client_test.js
```

Expected: failure because `assets/js/learner-api.js` does not exist.

- [ ] **Step 3: Implement the API client**

Create `assets/js/learner-api.js` as a browser/CommonJS module:

```js
(function (global) {
  'use strict';

  class LearnerApiError extends Error {
    constructor(status, code, message, details = [], requestId = '') {
      super(message);
      this.name = 'LearnerApiError';
      this.status = status;
      this.code = code;
      this.details = Array.isArray(details) ? details : [];
      this.requestId = requestId;
    }
  }

  function createLearnerApiClient({ baseUrl = '/api/v1', csrfToken = '', fetchImpl = global.fetch, onUnauthorized = () => {} } = {}) {
    if (typeof fetchImpl !== 'function') throw new TypeError('fetchImpl must be a function');
    let csrf = csrfToken;

    async function request(method, path, body) {
      const headers = { Accept: 'application/json' };
      const options = { method, headers, credentials: 'same-origin' };
      if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-Token'] = csrf;
        options.body = JSON.stringify(body);
      }

      const response = await fetchImpl(`${baseUrl}${path}`, options);
      let payload;
      try { payload = await response.json(); }
      catch { throw new LearnerApiError(response.status, 'INVALID_RESPONSE', 'Phản hồi máy chủ không hợp lệ.'); }

      if (!response.ok) {
        const error = payload && payload.error ? payload.error : {};
        const normalized = new LearnerApiError(
          response.status,
          String(error.code || 'REQUEST_FAILED'),
          String(error.message || 'Không thể hoàn tất yêu cầu.'),
          error.details,
          String(payload?.meta?.requestId || '')
        );
        if (response.status === 401) onUnauthorized(normalized);
        throw normalized;
      }
      return payload.data;
    }

    return {
      get: path => request('GET', path),
      send: (method, path, body) => request(String(method).toUpperCase(), path, body),
      setCsrfToken: token => { csrf = String(token || ''); },
    };
  }

  const api = { createLearnerApiClient, LearnerApiError };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.TalentHubLearnerApi = api;
})(typeof window !== 'undefined' ? window : globalThis);
```

- [ ] **Step 4: Initialize the client in `learner.js`**

Add one boot function without changing domain interactions:

```js
function createPageApiClient() {
  const node = document.getElementById('learner-session-boot');
  if (!node || !global.TalentHubLearnerApi) return null;
  let boot;
  try { boot = JSON.parse(node.textContent || '{}'); }
  catch { return null; }
  return global.TalentHubLearnerApi.createLearnerApiClient({
    baseUrl: boot.apiBase || '/api/v1',
    csrfToken: boot.csrfToken || '',
    onUnauthorized: () => {
      global.location.assign(`/login.php?next=${encodeURIComponent(global.location.pathname + global.location.search)}`);
    },
  });
}
```

Expose the initialized client as `global.TalentHubLearnerClient`; do not submit profile/activity data in this task.

- [ ] **Step 5: Load scripts in dependency order**

Before every `learner.js` include, add:

```html
<script src="../../assets/js/learner-api.js"></script>
<script src="../../assets/js/learner.js"></script>
```

Keep page-specific scripts after both shared scripts.

- [ ] **Step 6: Run Node tests and JS syntax checks**

Run:

```powershell
node --test tests\learner_api_client_test.js
node --check assets\js\learner-api.js
node --check assets\js\learner.js
```

Expected: tests pass and both syntax checks exit `0`.

- [ ] **Step 7: Commit the frontend foundation**

```powershell
git add assets/js/learner-api.js assets/js/learner.js app/learner/*.php tests/learner_api_client_test.js
git commit -m "feat(student): add authenticated learner API client"
```

Before committing, inspect `git diff --cached --name-only` and unstage any learner PHP page that changed for reasons other than adding the script include.

---

### Task 7: Verify Baseline Student API, RBAC and Cross-Role Safety

**Files:**

- Create: `tests/learner_foundation_mysql_test.php`
- Create: `bin/smoke-student-foundation.php`
- Modify: `src/Bootstrap/Application.php` only if a failing test proves a baseline Student route defect
- Test: `tests/learner_foundation_mysql_test.php`

**Interfaces:**

- Consumes: `/api/v1/auth/me`, `/api/v1/students/me`, `/api/v1/students/me/dashboard`, shared permissions and minimal auth fixture.
- Produces: repeatable MariaDB integration evidence for current Student user and negative role/ownership cases.

- [ ] **Step 1: Write the MySQL integration test with an explicit environment gate**

Create `tests/learner_foundation_mysql_test.php` that begins with:

```php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Repository\StudentRepository;
use TalentHub\Modules\Student\Service\StudentProfileService;
use TalentHub\Rbac\Service\PermissionService;

if (Environment::appEnvironment() !== 'test') {
    fwrite(STDERR, "learner_foundation_mysql_test requires APP_ENV=test\n");
    exit(2);
}

function mysql_foundation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$studentEmail = 'student@test.talenthub.local';
$studentRow = (new AuthRepository($pdo))->findByEmail($studentEmail);
mysql_foundation_assert(is_array($studentRow), 'minimal Student fixture exists');
mysql_foundation_assert($studentRow['role'] === 'student', 'fixture resolves canonical student role');

$user = (new AuthService(new AuthRepository($pdo)))->current((string) $studentRow['id']);
(new PermissionService($pdo))->require($user['id'], 'student_profile.read_own');
$service = new StudentProfileService(new StudentRepository($pdo));
$profile = $service->get($user['id']);
$dashboard = $service->dashboard($user['id']);

mysql_foundation_assert($profile['userId'] === $user['id'], 'profile is scoped to current user');
mysql_foundation_assert($profile['email'] === $studentEmail, 'profile and auth identity agree');
mysql_foundation_assert($dashboard['student']['id'] === $profile['id'], 'dashboard uses same Student profile');

$business = (new AuthRepository($pdo))->findByEmail('business@test.talenthub.local');
mysql_foundation_assert(is_array($business), 'business fixture exists for negative authorization');
try {
    (new PermissionService($pdo))->require((string) $business['id'], 'student_profile.read_own');
    mysql_foundation_assert(false, 'business cannot read own Student profile permission');
} catch (ApiException $exception) {
    mysql_foundation_assert($exception->status === 403, 'wrong role receives 403');
}

echo "learner_foundation_mysql_test: OK\n";
```

- [ ] **Step 2: Run the test and confirm the correct environment/fixture failure**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_foundation_mysql_test.php
```

Expected before test DB setup: exit `2` for wrong `APP_ENV` or a sanitized connection/fixture failure. Do not weaken the gate to make the test pass without MariaDB.

- [ ] **Step 3: Seed only the testing database**

With `APP_ENV=test` and a dedicated test DB configured:

```powershell
& 'D:\xampp\php\php.exe' bin\migrate.php migrate
& 'D:\xampp\php\php.exe' bin\seed.php --testing
```

Expected: both commands exit `0`. Confirm `DB_DATABASE` points to a disposable test database and `TALENTHUB_TEST_PASSWORD` contains a test-only value of at least 12 characters before running the seed command.

- [ ] **Step 4: Run the MySQL integration test**

Run:

```powershell
& 'D:\xampp\php\php.exe' tests\learner_foundation_mysql_test.php
```

Expected: `learner_foundation_mysql_test: OK`.

- [ ] **Step 5: Create the smoke wrapper**

Create `bin/smoke-student-foundation.php`:

```php
<?php
declare(strict_types=1);

$test = dirname(__DIR__) . '/tests/learner_foundation_mysql_test.php';
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($test);
passthru($command, $exitCode);
exit($exitCode);
```

The wrapper must not print environment variables or catch and expose raw PDO exceptions.

- [ ] **Step 6: Verify existing Student routes in `Application.php`**

Confirm these exact routes still exist:

```text
GET   /api/v1/auth/me
GET   /api/v1/students/me
PATCH /api/v1/students/me
GET   /api/v1/students/me/dashboard
```

Do not edit `Application.php` if they already use session, role `student`, permission checks and CSRF for PATCH. If a focused test exposes a defect, make the smallest Student-only route correction and rerun all shared smokes.

- [ ] **Step 7: Run cross-role smoke tests**

Run against the test database:

```powershell
& 'D:\xampp\php\php.exe' bin\smoke-student-foundation.php
& 'D:\xampp\php\php.exe' bin\smoke-teacher-auth.php
& 'D:\xampp\php\php.exe' bin\smoke-school-api.php
& 'D:\xampp\php\php.exe' bin\smoke-role-profiles.php
```

Expected: all exit `0`. Any failure in Teacher, School or Business/Enterprise blocks the commit.

- [ ] **Step 8: Commit integration coverage**

```powershell
git add tests/learner_foundation_mysql_test.php bin/smoke-student-foundation.php
git add src/Bootstrap/Application.php
git diff --cached --name-only
git commit -m "test(student): verify shared foundation integration"
```

Only stage `Application.php` if it actually contains a required Student-only correction.

---

### Task 8: Full Foundation Verification and Handoff to the Profile Plan

**Files:**

- Modify: `docs/superpowers/readiness/student-production-foundation.md`
- Verify: all files changed by Tasks 1-8

**Interfaces:**

- Consumes: all prior task outputs.
- Produces: evidence-backed `READY_FOR_PROFILE_PLAN` or an explicit blocker; no partial success claim.

- [ ] **Step 1: Verify branch ancestry and scope**

Run:

```powershell
git branch --show-current
git merge-base --is-ancestor origin/develop HEAD
git status --short --branch
git diff --name-only origin/develop...HEAD
```

Expected: branch `feature/student`; ancestry command exit `0`; no protected role UI paths changed by this plan.

- [ ] **Step 2: Run PHP lint across application code**

Run:

```powershell
$php='D:\xampp\php\php.exe'
$fail=0
Get-ChildItem app,src,api,bin,config,Database\migrations,Database\seeds,tests -Recurse -File -Filter '*.php' | ForEach-Object { & $php -l $_.FullName; if($LASTEXITCODE -ne 0){$fail++} }
if($fail -ne 0){exit 1}
```

Expected: exit `0`, no syntax errors.

- [ ] **Step 3: Run all learner tests**

Run:

```powershell
$php='D:\xampp\php\php.exe'
Get-ChildItem tests\learner_*_test.php | Sort-Object Name | ForEach-Object { & $php $_.FullName; if($LASTEXITCODE -ne 0){exit 1} }
Get-ChildItem tests\learner_*_test.js | Sort-Object Name | ForEach-Object { node --test $_.FullName; if($LASTEXITCODE -ne 0){exit 1} }
```

Expected: every PHP/Node learner test passes, including MySQL integration when `APP_ENV=test` is configured.

- [ ] **Step 4: Run shared migration and cross-role gates**

Run:

```powershell
& $php bin\migrate.php validate
& $php app\learner\tools\readiness-check.php --phase=1 --format=text
& $php bin\smoke-student-foundation.php
& $php bin\smoke-teacher-auth.php
& $php bin\smoke-school-api.php
& $php bin\smoke-role-profiles.php
```

Expected: every command exits `0`; learner Phase 1 is `READY`.

- [ ] **Step 5: Run whitespace and JavaScript syntax checks**

Run:

```powershell
git diff --check
Get-ChildItem assets\js -File -Filter '*.js' | ForEach-Object { node --check $_.FullName; if($LASTEXITCODE -ne 0){exit 1} }
```

Expected: both checks exit `0`.

- [ ] **Step 6: Perform manual Student session smoke**

Using the test Student account:

1. Open `/login.php?next=/app/learner/index.php`.
2. Log in and confirm redirect to Student dashboard.
3. Confirm the header shows the database-backed Student name/initial.
4. Open a Student URL directly in a logged-out session and confirm redirect to login with `next` preserved.
5. Log in as Teacher, School and Business accounts and confirm direct Student URL access redirects to each account's correct portal.
6. Confirm browser network calls use `/api/v1`, same-origin cookies and no `student_id` request parameter.
7. Stop the database and confirm the page/API shows a safe unavailable state with no mock data and no connection detail.

- [ ] **Step 7: Update the readiness record with actual evidence**

Append:

```markdown
## Foundation verification

- PHP lint: PASS; ghi số file nguyên văn từ output kiểm tra
- Learner PHP tests: PASS; ghi số test nguyên văn từ output kiểm tra
- Learner Node tests: PASS; ghi số test nguyên văn từ output kiểm tra
- MariaDB Student foundation integration: PASS
- Shared migration validation: PASS
- Teacher smoke: PASS
- School smoke: PASS
- Business/Enterprise profile smoke: PASS
- Manual Student login/redirect/session smoke: PASS
- Final status: READY_FOR_PROFILE_PLAN
```

Use actual counts and statuses only.

- [ ] **Step 8: Commit the verified readiness evidence**

```powershell
git add docs/superpowers/readiness/student-production-foundation.md
git commit -m "docs(student): complete production foundation gate"
```

- [ ] **Step 9: Stop before implementing Profile/Talent Passport**

Report the foundation result and request approval to create/execute the next focused plan. Do not begin Profile, Activities, Assessment, Applications, Notifications, Badges, Statistics or AI in this plan.

---

## Definition of Done

This plan is complete only when all conditions are true:

- Shared migration validation succeeds on a canonical, non-legacy database.
- Phase 1 readiness returns `READY` using shared `DB_*` configuration.
- Student pages require an authenticated `student` session and never use demo identity in production.
- Baseline Student profile/dashboard data comes from `src/Modules/Student` and the shared database.
- Existing learner render tests opt into mock explicitly and remain green.
- Learner frontend has one tested JSON API client with CSRF and session-expiry handling.
- Baseline Student API enforces role, permission, ownership and CSRF.
- Teacher, School and Business/Enterprise smoke tests remain green.
- No secret is committed or printed in documentation.
- No protected role UI/business file is modified.
- The readiness record contains fresh commands, actual counts and actual pass/fail evidence.

If the live database matches the legacy `Database/Talenthub_DB.sql` shape (`users.roles`) instead of shared migrations (`users.roleId` plus RBAC tables), the correct result is `LEGACY_RECONCILIATION_REQUIRED`, not a partially completed foundation.
