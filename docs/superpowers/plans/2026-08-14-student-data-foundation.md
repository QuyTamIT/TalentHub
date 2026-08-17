# Student Data Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add five learner repository contracts with mock and read-only PDO implementations while preserving the existing learner UI and keeping mock as the default source.

**Architecture:** Learner pages keep their existing `learner_*` compatibility functions. Those adapters obtain typed repository contracts from a learner-only factory, which chooses mock or database from explicit configuration. Repository records are normalized to snake_case, database UUIDs stay authoritative, mock legacy IDs receive marked compatibility UUIDs, and statuses use learner-local backed enums with `unknown` fallback.

**Tech Stack:** PHP 8.3, PDO, MySQL schema from `Database/Talenthub_DB.sql`, SQLite only as an in-memory test driver, existing plain PHP/Node test runners.

## Global Constraints

- Work only on branch `feature/student`.
- `Database/Talenthub_DB.sql` is the only schema reference.
- Never run or add CREATE, ALTER, DROP, migrations, seeds, INSERT, UPDATE, or DELETE in production repositories.
- Do not modify `Database/`, `app/enterprise`, `app/school`, teacher code, or another role's mock data.
- Mock is the default source. Database requires explicit configuration and injected PDO.
- Missing PDO in database mode is a configuration error; database/query errors never fall back to mock.
- Mock compatibility UUIDs are never database identifiers.
- Learner status enums always support `unknown`.
- Do not commit, push, or merge.

---

### Task 1: Contracts, normalization, configuration, and factory

**Files:**
- Create: `app/learner/data/Contracts/StudentRepository.php`
- Create: `app/learner/data/Contracts/AssessmentRepository.php`
- Create: `app/learner/data/Contracts/ActivityRepository.php`
- Create: `app/learner/data/Contracts/EcosystemRepository.php`
- Create: `app/learner/data/Contracts/ApplicationRepository.php`
- Create: `app/learner/data/Enums/Statuses.php`
- Create: `app/learner/data/Exceptions/LearnerDataConfigurationException.php`
- Create: `app/learner/data/Exceptions/LearnerDataMappingException.php`
- Create: `app/learner/data/Exceptions/LearnerDataQueryException.php`
- Create: `app/learner/data/Support/KeyMapper.php`
- Create: `app/learner/data/Support/Uuid.php`
- Create: `app/learner/data/RepositoryFactory.php`
- Create: `app/learner/data/config.php`
- Create: `app/learner/data/bootstrap.php`
- Create: `tests/learner_data_foundation_test.php`

**Interfaces:**
- `StudentRepository::findById(string $studentId): ?array`
- `AssessmentRepository::all(): array`, `findById(string $assessmentId): ?array`, `questionsFor(string $assessmentId): array`, `attemptsFor(string $studentId, string $assessmentId): array`, `evaluationsForStudent(string $studentId): array`
- `ActivityRepository::all(): array`, `findById(string $activityId): ?array`, `registrationsFor(string $studentId): array`
- `EcosystemRepository::partners(?string $type = null): array`, `opportunities(): array`, `findPartner(string $type, string $partnerId): ?array`, `findOpportunity(string $type, string $opportunityId): ?array`, `opportunitiesForPartner(string $partnerId, bool $activeOnly = false): array`
- `ApplicationRepository::forStudent(string $studentId): array`, `findByIdForStudent(string $applicationId, string $studentId): ?array`
- `RepositoryFactory` receives `source` and optional `PDO`; its five creation methods accept mock fixture arrays but return only contract types.

- [ ] **Step 1: Write a failing foundation test**

```php
require_once __DIR__ . '/../app/learner/data/bootstrap.php';

foundation_assert(KeyMapper::toSnake(['studentId' => 'x']) === ['student_id' => 'x'], 'camelCase maps to snake_case');
$uuid = Uuid::fromMockLegacy('student', 'student-demo-001');
foundation_assert(Uuid::isValid($uuid), 'mock legacy id maps to UUID');
foundation_assert($uuid === Uuid::fromMockLegacy('student', 'student-demo-001'), 'mock UUID is deterministic');
foundation_assert(ActivityStatus::normalize('team-value') === ActivityStatus::Unknown, 'unknown activity status is retained safely');
foundation_assert((new RepositoryFactory())->source() === 'mock', 'factory defaults to mock');
foundation_expect_exception(
    static fn () => new RepositoryFactory('database'),
    LearnerDataConfigurationException::class,
    'requires an injected PDO'
);
```

- [ ] **Step 2: Run the test and verify RED**

Run: `D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tests\learner_data_foundation_test.php`

Expected: FAIL because `app/learner/data/bootstrap.php` does not exist.

- [ ] **Step 3: Implement the five interfaces, enums, mapper, UUID helper, exceptions, bootstrap, and factory validation**

Key behavior:

```php
enum ActivityStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case Unknown = 'unknown';

    public static function normalize(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Unknown;
    }
}
```

`Uuid::fromMockLegacy()` uses a fixed learner namespace and RFC 4122 version/variant bits. `Uuid::normalizeDatabase()` lowercases valid UUIDs and throws `LearnerDataMappingException` for invalid values.

- [ ] **Step 4: Run the foundation test and verify GREEN**

Expected: all Task 1 assertions pass.

### Task 2: Mock repository implementations

**Files:**
- Create: `app/learner/data/Support/MockRecordNormalizer.php`
- Create: `app/learner/data/Mock/MockStudentRepository.php`
- Create: `app/learner/data/Mock/MockAssessmentRepository.php`
- Create: `app/learner/data/Mock/MockActivityRepository.php`
- Create: `app/learner/data/Mock/MockEcosystemRepository.php`
- Create: `app/learner/data/Mock/MockApplicationRepository.php`
- Modify: `app/learner/data/RepositoryFactory.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `tests/learner_data_foundation_test.php`

**Produces:** Every mock record has canonical UUID relationship keys, `legacy_id`/`legacy_*_id` where applicable, and `id_origin = mock_compat`. Lookups accept canonical UUID or legacy ID.

- [ ] **Step 1: Add failing contract tests for all five mock repositories**

Use minimal fixtures containing `student_id`, `school_id`, `enterprise_id`, and `activity_id`. Assert deterministic UUID conversion, legacy lookup compatibility, status normalization including unknown, student scoping, and absence of cross-student applications.

- [ ] **Step 2: Run and verify RED because mock classes/factory methods are missing**

- [ ] **Step 3: Implement minimal mock repositories and factory creation methods**

Mock normalization preserves the original presentation fields but replaces internal identifiers with compatibility UUIDs and stores each original value under the matching `legacy_*` key.

- [ ] **Step 4: Run and verify GREEN**

### Task 3: Shared PDO reader plus Student and Assessment database repositories

**Files:**
- Create: `app/learner/data/Database/AbstractDatabaseRepository.php`
- Create: `app/learner/data/Database/DatabaseStudentRepository.php`
- Create: `app/learner/data/Database/DatabaseAssessmentRepository.php`
- Modify: `app/learner/data/RepositoryFactory.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `tests/learner_data_foundation_test.php`

**Database reads:**
- Student: `student_profiles`, `users`, `classes`, `schools`.
- Assessment: `talent_tests`, `test_questions`, `test_attempts`, `test_results`, `assessments`, `assessment_scores`, `assessment_criteria`.

- [ ] **Step 1: Create in-memory PDO tables in the test with only schema-reference columns and add failing read tests**

The test tables mirror the named fields from `Database/Talenthub_DB.sql`. Test setup may use SQLite DDL because it is isolated test state; production repository code must contain no DDL. Insert test fixtures only into the in-memory test database. Assert returned keys are snake_case, UUIDs are authoritative, JSON columns decode, attempt status derives from `completedAt`, and unknown values map to `unknown`.

- [ ] **Step 2: Run and verify RED because database repositories are missing**

- [ ] **Step 3: Implement `AbstractDatabaseRepository::fetchAll()`/`fetchOne()` with `prepare()` and `execute()`**

All SQL is fixed SELECT SQL. Bind identifiers using named parameters. Wrap prepare/execute/fetch failures in `LearnerDataQueryException` with operation context and original cause.

- [ ] **Step 4: Implement Student and Assessment SELECT queries using only listed schema columns**

- [ ] **Step 5: Run and verify GREEN, then test a missing-table failure is reported and does not fall back**

### Task 4: Activity, Ecosystem, and Application database repositories

**Files:**
- Create: `app/learner/data/Database/DatabaseActivityRepository.php`
- Create: `app/learner/data/Database/DatabaseEcosystemRepository.php`
- Create: `app/learner/data/Database/DatabaseApplicationRepository.php`
- Modify: `app/learner/data/RepositoryFactory.php`
- Modify: `app/learner/data/bootstrap.php`
- Modify: `tests/learner_data_foundation_test.php`

**Database reads:**
- Activity: `activities`, `activity_registrations`.
- Ecosystem: `schools`, `enterprises`, `internship_posts`.
- Application: `internship_applications`, `internship_posts`, `enterprises`.

- [ ] **Step 1: Add failing in-memory PDO tests for each contract**

Assert activity/student filters use bound values, shared keys are snake_case, partner/opportunity/application joins expose only schema-backed fields, unknown statuses survive as `unknown`, and application reads remain scoped to `student_id`.

- [ ] **Step 2: Run and verify RED**

- [ ] **Step 3: Implement the three repositories with fixed prepared SELECT statements**

- [ ] **Step 4: Add a static SQL guard to reject production DDL/write keywords and verify GREEN**

The guard scans only `app/learner/data/Database/*.php` for SQL tokens `CREATE`, `ALTER`, `DROP`, `INSERT`, `UPDATE`, and `DELETE` outside explanatory text.

### Task 5: Compatibility adapters and learner-page integration

**Files:**
- Create: `app/learner/data/Support/LearnerViewAdapter.php`
- Modify: `app/learner/includes/student-data.php`
- Modify: `app/learner/includes/assessment-data.php`
- Modify: `app/learner/includes/activity-data.php`
- Modify: `app/learner/includes/ecosystem-data.php`
- Modify learner pages containing hard-coded `student-demo-001` only where needed to call `learner_current_student_id()`.
- Modify existing learner data/render tests as necessary without weakening their current assertions.

**Produces:** Existing variables and functions keep their names and page-facing fields. Mock remains pixel/behavior compatible. Database mode is selected only from learner config before bootstrap and never exposes PDO to a page.

- [ ] **Step 1: Add failing compatibility assertions**

Assert existing calls still work:

```php
learner_activity_find('iot-lab');
learner_assessment_definition('holland');
learner_ecosystem_opportunity('internship', 1);
learner_ecosystem_applications();
```

Also assert default source is mock and explicit database configuration without PDO raises the configuration exception during repository resolution.

- [ ] **Step 2: Run existing learner tests and record the expected RED failures caused by adapter expectations**

- [ ] **Step 3: Route learner-owned raw fixtures through factory-created repositories and restore legacy view IDs with `LearnerViewAdapter`**

Do not change the imported enterprise mock provider. Pass its read-only output into `MockEcosystemRepository` through the learner adapter.

- [ ] **Step 4: Replace learner-only hard-coded current student IDs with `learner_current_student_id()`**

- [ ] **Step 5: Run all affected learner PHP and Node tests and verify GREEN**

### Task 6: Final verification and scope audit

**Files:** No new production behavior.

- [ ] **Step 1: Run PHP syntax checks over every learner PHP file and learner PHP test**

Run a PowerShell loop invoking `php.exe -l` for each file. Expected: no syntax errors.

- [ ] **Step 2: Run every `tests/learner_*_test.php` file**

Expected: exit 0 for every test.

- [ ] **Step 3: Run every `tests/learner_*_test.js` file with `node --test`**

Expected: zero failed tests.

- [ ] **Step 4: Re-run the database SQL safety guard and inspect repository table/column coverage against `Database/Talenthub_DB.sql`**

- [ ] **Step 5: Inspect `git diff --name-only`, `git diff --check`, and `git status --short --branch`**

Expected: branch is `feature/student`; no modified path under `Database/`, `app/enterprise`, `app/school`, or another role module; no commit/push/merge was performed.

- [ ] **Step 6: Report architecture, changed files, test evidence, table coverage, schema/mock mismatches, and database work still required**
