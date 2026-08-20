# Complete AI Demo Dataset Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Populate `talenthub_local` with a rerunnable, relationally complete demo journey for 11 existing THPT Nguyễn Trãi learners and 8 new synthetic Đại học FPT students, then persist redacted 9Router shadow runs for one hero in each education band.

**Architecture:** Follow the existing dataset/seeder pattern: a pure deterministic manifest owns IDs and scenario facts, a coordinator validates and transactionally upserts only its UUID namespaces, and a verifier proves count, band, lifecycle, and role-isolation invariants. Keep network execution in a separate local-only AI runner so provider failure can never corrupt seeded data. No schema migration is required.

**Tech Stack:** PHP 8.3, PDO, MySQL 8.4, Laragon, existing TalentHub assessment scorers and AI recommendation services, PowerShell, Node.js, Git.

**Source design:** `docs/superpowers/specs/2026-08-20-complete-ai-demo-dataset-design.md`

## Global Constraints

- Work only on `feature/student`; do not merge or push to `develop`.
- Preserve the existing `20000000-` THPT Nguyễn Trãi identities and all unrelated school, teacher, student, enterprise, testing, `.claude/`, and `.qwen/` data.
- THPT AI-dependent rows use `21000000-`; all new synthetic Đại học FPT entities and dependent rows use `22000000-`.
- Create exactly 8 synthetic FPT students, 4 lecturers, 1 school administrator, and 4 year cohorts. Never use real student data or real `@fpt.edu.vn` addresses.
- High-school attempts use only `holland_high`, `mbti_high`, `disc_high`, and `multiple_intelligence_high`; university attempts use only the corresponding `*_college` codes.
- Reuse canonical published catalog versions and application scorers. Never insert or update `talent_tests`, `test_questions`, `learner_assessment_versions`, or `learner_assessment_question_versions`.
- Seed all deterministic database rows in one transaction after preflight. Provider calls occur only after that transaction commits.
- Never truncate or bulk-delete. Upsert only owned IDs and fail closed when a natural key belongs to a foreign ID.
- Never print or persist the 9Router API key or raw QR tokens. QR fixtures contain only `hash('sha256', 'talenthub-demo-qr-v1:' . $sessionKey)`.
- Keep `TALENTHUB_AI_SHADOW=true`, `TALENTHUB_AI_SHADOW_GATE_APPROVED=false`, and `TALENTHUB_AI_VISIBLE_PERCENT=0`.
- Back up `talenthub_local` before its first mutation. Execute and verify twice on a disposable schema before main-schema execution.
- Do not claim learner camera scanning is implemented. This plan supplies coherent QR/check-in evidence only.

## File Map

- Create `Database/seeds/Demo/CompleteAiDemoDataset.php`: pure deterministic identities, UUIDs, profiles, activity scenarios, and expected counts.
- Create `Database/seeds/Demo/CompleteAiDemoSeeder.php`: local/test guard, catalog preflight, scoring, transactional upserts, and seed summary.
- Create `Database/seeds/Demo/CompleteAiDemoVerifier.php`: read-only invariants and redacted count report.
- Create `Database/seeds/Demo/CompleteAiDemoAiRunner.php`: production-source snapshot, Rule Engine persistence, and shadow execution for two heroes.
- Modify `bin/seed.php`: add explicit `--demo-ai` dispatch without changing `--demo` semantics.
- Create `bin/verify-demo-ai.php`: read-only verifier entrypoint.
- Create `bin/run-demo-ai.php`: local-only live runner entrypoint.
- Create `tests/complete_ai_demo_dataset_contract_test.php`: pure manifest and secret-safety contract.
- Create `tests/complete_ai_demo_seed_mysql_test.php`: disposable-MySQL idempotency, band, lifecycle, and isolation integration test.
- Create `tests/complete_ai_demo_runner_test.php`: mocked-provider Rule/shadow persistence test.
- Create `docs/superpowers/database-change-requests/2026-08-20-complete-ai-demo-dataset.md`: approved local execution evidence and redacted before/after counts.

---

### Task 1: Deterministic scenario manifest

**Files:**
- Create: `Database/seeds/Demo/CompleteAiDemoDataset.php`
- Create: `tests/complete_ai_demo_dataset_contract_test.php`

**Interfaces:**
- Produces: `CompleteAiDemoDataset::uuid(string $owner, string $kind, string $key): string`
- Produces: `CompleteAiDemoDataset::learners(): array`, `fptTeachers(): array`, `activities(DateTimeImmutable $clock): array`, `assessmentPlan(): array`, `skillPlan(): array`, `registrationPlan(): array`, and `expectedMinimums(): array`
- Produces: `CompleteAiDemoDataset::heroStudentIds(): array{high:string,college:string}`

- [ ] **Step 1: Write the failing pure contract test**

Create a framework-free test with these exact identity expectations:

```php
<?php
declare(strict_types=1);

use TalentHub\Database\Seeds\Demo\CompleteAiDemoDataset;

require_once dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoDataset.php';

function demo_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$clock = new DateTimeImmutable('2026-08-20 00:00:00.000000', new DateTimeZone('UTC'));
$learners = CompleteAiDemoDataset::learners();
$activities = CompleteAiDemoDataset::activities($clock);
$heroes = CompleteAiDemoDataset::heroStudentIds();

demo_contract_assert(count($learners) === 19, '11 high-school plus 8 university learners');
demo_contract_assert(count(array_filter($learners, fn (array $r): bool => $r['band'] === 'high')) === 11, '11 high-school learners');
demo_contract_assert(count(array_filter($learners, fn (array $r): bool => $r['band'] === 'college')) === 8, '8 university learners');
demo_contract_assert(count(CompleteAiDemoDataset::fptTeachers()) === 4, '4 FPT lecturers');
demo_contract_assert(count($activities) === 18, '10 THPT plus 8 FPT activities');
demo_contract_assert($heroes['high'] === '20000000-0000-4000-8000-000000000060', 'existing THPT hero is stable');
demo_contract_assert(str_starts_with($heroes['college'], '22000000-'), 'FPT hero uses university namespace');

$emails = array_column(array_filter($learners, fn (array $r): bool => $r['band'] === 'college'), 'email');
demo_contract_assert(in_array('sv.fpt.an@talenthub.vn', $emails, true), 'college hero login exists');
demo_contract_assert(count(array_unique($emails)) === 8, 'university emails are unique');
foreach ($emails as $email) {
    demo_contract_assert(str_ends_with($email, '@talenthub.vn'), 'demo email uses TalentHub domain');
    demo_contract_assert(!str_ends_with($email, '@fpt.edu.vn'), 'no official FPT address is fabricated');
}

foreach (CompleteAiDemoDataset::assessmentPlan() as $studentId => $codes) {
    $band = array_values(array_filter($learners, fn (array $r): bool => $r['student_id'] === $studentId))[0]['band'];
    demo_contract_assert(count($codes) >= 2, 'every learner has at least two assessments');
    foreach ($codes as $code) {
        demo_contract_assert(str_ends_with($code, '_' . $band), 'assessment code matches learner band');
    }
}

demo_contract_assert(count(CompleteAiDemoDataset::registrationPlan()) === 40, 'exactly 40 registrations');
demo_contract_assert(CompleteAiDemoDataset::expectedMinimums() === [
    'learners' => 19,
    'activities' => 18,
    'registrations' => 40,
    'checkins' => 20,
    'experiences' => 20,
    'published_evaluations' => 20,
    'consent_events' => 76,
], 'minimum contract is exact');

$source = file_get_contents(dirname(__DIR__) . '/Database/seeds/Demo/CompleteAiDemoDataset.php');
demo_contract_assert(is_string($source), 'dataset source is readable');
foreach (['sk-', 'TALENTHUB_AI_API_KEY', 'rawToken', 'DELETE FROM', 'TRUNCATE ', 'DROP TABLE'] as $forbidden) {
    demo_contract_assert(!str_contains($source, $forbidden), 'dataset excludes secret/destructive token: ' . $forbidden);
}

echo "complete_ai_demo_dataset_contract_test: OK\n";
```

- [ ] **Step 2: Run the contract test and verify RED**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\complete_ai_demo_dataset_contract_test.php
```

Expected: FAIL because `CompleteAiDemoDataset.php` does not exist.

- [ ] **Step 3: Implement stable UUID ownership and exact people**

Use this complete identity matrix in `CompleteAiDemoDataset`:

```php
private const THPT_STUDENTS = [
    ['student_id' => '20000000-0000-4000-8000-000000000060', 'email' => 'hs.minh@talenthub.vn',  'name' => 'Nguyễn Văn Minh'],
    ['student_id' => '20000000-0000-4000-8000-000000000061', 'email' => 'hs.ha@talenthub.vn',    'name' => 'Trần Thu Hà'],
    ['student_id' => '20000000-0000-4000-8000-000000000062', 'email' => 'hs.nam@talenthub.vn',   'name' => 'Lê Hoàng Nam'],
    ['student_id' => '20000000-0000-4000-8000-000000000063', 'email' => 'hs.lan@talenthub.vn',   'name' => 'Phạm Thị Lan'],
    ['student_id' => '20000000-0000-4000-8000-000000000064', 'email' => 'hs.bao@talenthub.vn',   'name' => 'Đỗ Quốc Bảo'],
    ['student_id' => '20000000-0000-4000-8000-000000000065', 'email' => 'hs.tuyet@talenthub.vn', 'name' => 'Võ Thị Tuyết'],
    ['student_id' => '20000000-0000-4000-8000-000000000066', 'email' => 'hs.khoi@talenthub.vn',  'name' => 'Hoàng Minh Khôi'],
    ['student_id' => '20000000-0000-4000-8000-000000000067', 'email' => 'hs.truc@talenthub.vn',  'name' => 'Phan Thanh Trúc'],
    ['student_id' => '20000000-0000-4000-8000-000000000068', 'email' => 'hs.khanh@talenthub.vn', 'name' => 'Đinh Gia Khánh'],
    ['student_id' => '20000000-0000-4000-8000-000000000069', 'email' => 'hs.linh@talenthub.vn',  'name' => 'Ngô Phương Linh'],
    ['student_id' => '20000000-0000-4000-8000-00000000006a', 'email' => 'hs.duyen@talenthub.vn', 'name' => 'Trương Mỹ Duyên'],
];

private const FPT_STUDENTS = [
    ['key' => 'an',    'name' => 'Nguyễn Hoài An',   'email' => 'sv.fpt.an@talenthub.vn',    'year' => 1, 'dob' => '2007-03-14'],
    ['key' => 'bao',   'name' => 'Trần Gia Bảo',     'email' => 'sv.fpt.bao@talenthub.vn',   'year' => 1, 'dob' => '2007-08-22'],
    ['key' => 'chau',  'name' => 'Lê Minh Châu',     'email' => 'sv.fpt.chau@talenthub.vn',  'year' => 2, 'dob' => '2006-01-30'],
    ['key' => 'duy',   'name' => 'Phạm Đức Duy',     'email' => 'sv.fpt.duy@talenthub.vn',   'year' => 2, 'dob' => '2006-11-09'],
    ['key' => 'linh',  'name' => 'Võ Khánh Linh',    'email' => 'sv.fpt.linh@talenthub.vn',  'year' => 3, 'dob' => '2005-05-17'],
    ['key' => 'minh',  'name' => 'Đỗ Quang Minh',    'email' => 'sv.fpt.minh@talenthub.vn',  'year' => 3, 'dob' => '2005-09-02'],
    ['key' => 'nguyen','name' => 'Bùi Thảo Nguyên',  'email' => 'sv.fpt.nguyen@talenthub.vn','year' => 4, 'dob' => '2004-04-25'],
    ['key' => 'quang', 'name' => 'Hoàng Nhật Quang', 'email' => 'sv.fpt.quang@talenthub.vn', 'year' => 4, 'dob' => '2004-12-11'],
];

private const FPT_TEACHERS = [
    ['key' => 'son',  'name' => 'Nguyễn Minh Sơn', 'email' => 'gv.fpt.son@talenthub.vn',  'specialization' => 'Kỹ thuật phần mềm'],
    ['key' => 'thao', 'name' => 'Trần Thu Thảo',   'email' => 'gv.fpt.thao@talenthub.vn', 'specialization' => 'Trí tuệ nhân tạo'],
    ['key' => 'viet', 'name' => 'Lê Quốc Việt',    'email' => 'gv.fpt.viet@talenthub.vn', 'specialization' => 'Khởi nghiệp'],
    ['key' => 'yen',  'name' => 'Phạm Hải Yến',    'email' => 'gv.fpt.yen@talenthub.vn',  'specialization' => 'Thiết kế trải nghiệm'],
];
```

Implement IDs without random bytes:

```php
public static function uuid(string $owner, string $kind, string $key): string
{
    $prefix = match ($owner) {
        'thpt' => '21000000',
        'fpt' => '22000000',
        default => throw new InvalidArgumentException('Unknown demo owner.'),
    };
    $hex = substr(hash('sha256', "talenthub-complete-demo-v1\0{$owner}\0{$kind}\0{$key}"), 0, 24);
    return sprintf('%s-%s-4%s-8%s-%s',
        $prefix,
        substr($hex, 0, 4),
        substr($hex, 4, 3),
        substr($hex, 7, 3),
        substr($hex, 10, 12),
    );
}
```

Use these exact activity titles and distributions:

```php
private const THPT_ACTIVITIES = [
    ['robotics', 'Dự án Robot cứu hộ', 'career_technical', 'completed', -75, -74],
    ['stem-lab', 'Phòng thí nghiệm STEM mở', 'career_technical', 'ongoing', -1, 1],
    ['young-business', 'Thử thách Doanh nhân trẻ', 'career_business', 'published', 14, 15],
    ['finance', 'Ngày hội Tài chính học đường', 'career_business', 'completed', -60, -60],
    ['design', 'Triển lãm Thiết kế sáng tạo', 'career_arts', 'ongoing', -1, 2],
    ['music', 'Workshop Sáng tác âm nhạc', 'career_arts', 'published', 21, 21],
    ['football', 'Giải bóng đá Nguyễn Trãi', 'career_sports_academic', 'completed', -45, -43],
    ['debate', 'Câu lạc bộ Tranh biện học thuật', 'career_sports_academic', 'ongoing', -2, 2],
    ['science', 'Cuộc thi Nghiên cứu khoa học', 'career_technical', 'published', 30, 31],
    ['volunteer', 'Dự án Tình nguyện cộng đồng', 'career_sports_academic', 'completed', -30, -29],
];

private const FPT_ACTIVITIES = [
    ['hackathon', 'FPTU Hackathon vì cộng đồng', 'career_technical', 'completed', -70, -68],
    ['ai-club', 'Câu lạc bộ Trí tuệ nhân tạo', 'career_technical', 'ongoing', -2, 2],
    ['startup', 'Vườn ươm Khởi nghiệp sinh viên', 'career_business', 'published', 12, 30],
    ['marketing', 'Dự án Digital Marketing thực chiến', 'career_business', 'completed', -55, -50],
    ['ux', 'UX Design Challenge', 'career_arts', 'ongoing', -1, 1],
    ['music-studio', 'FPTU Music Studio Showcase', 'career_arts', 'published', 20, 20],
    ['vovinam', 'Giải Vovinam sinh viên', 'career_sports_academic', 'completed', -40, -39],
    ['research', 'Hội nghị Nghiên cứu sinh viên', 'career_sports_academic', 'published', 35, 36],
];
```

Generate `assessmentPlan()` deterministically: every learner gets Holland plus one secondary test selected by learner index modulo three; both heroes get all four. Generate 3-5 skill assignments per learner from `python`, `data_analysis`, `communication`, `teamwork`, `leadership`, `creative_design`, `problem_solving`, `entrepreneurship`, `research`, and `sports_discipline`. Generate exactly 20 registrations per organization with status totals `attended=10`, `approved=4`, `pending=3`, `cancelled=3`; never cross organization boundaries.

The high-school and university hero must each own two attended registrations on two distinct completed activities. All non-hero attended slots are distributed without duplicating the `(activityId, studentId)` unique key.

- [ ] **Step 4: Run contract test and verify GREEN**

Expected: `complete_ai_demo_dataset_contract_test: OK`.

- [ ] **Step 5: Commit the manifest**

```powershell
git add -- Database/seeds/Demo/CompleteAiDemoDataset.php tests/complete_ai_demo_dataset_contract_test.php
git commit -m "test(demo): define complete learner scenario"
```

### Task 2: Transactional identity, skill, and consent seeding

**Files:**
- Create: `Database/seeds/Demo/CompleteAiDemoSeeder.php`
- Create: `tests/complete_ai_demo_seed_mysql_test.php`

**Interfaces:**
- Consumes: `CompleteAiDemoDataset`
- Produces: `CompleteAiDemoSeeder::run(PDO $pdo, string $environment, string $password, DateTimeImmutable $clock): array<string,int>`
- Produces: `CompleteAiDemoSeeder::touchedTables(): array`

- [ ] **Step 1: Write the disposable-MySQL RED harness**

The test must create only a schema matching `^talenthub_complete_demo_test_[a-z0-9_]+$`, run all existing migrations and system/catalog seeds, run `SchoolDemoSeeder`, then capture baseline rows outside `21000000-%` and `22000000-%`. Wrap schema deletion in `finally` and reject `talenthub_local` explicitly.

Core assertions before the implementation exists:

```php
$first = $seeder->run($pdo, 'test', 'CompleteDemoPass!2026', $clock);
$firstCounts = demo_table_counts($pdo, $seeder->touchedTables());
$second = $seeder->run($pdo, 'test', 'CompleteDemoPass!2026', $clock);
$secondCounts = demo_table_counts($pdo, $seeder->touchedTables());

demo_mysql_assert($firstCounts === $secondCounts, 'second seed has zero count drift');
demo_mysql_assert(demo_outside_namespace_counts($pdo, $seeder->touchedTables()) === $baselineOutside, 'unrelated rows unchanged');
demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM schools WHERE id LIKE '22000000-%' AND name='Đại học FPT'")->fetchColumn() === 1, 'FPT school exists');
demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM student_profiles WHERE id LIKE '22000000-%'")->fetchColumn() === 8, '8 FPT students exist');
demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM teacher_profiles WHERE id LIKE '22000000-%'")->fetchColumn() === 4, '4 FPT lecturers exist');
demo_mysql_assert((int) $pdo->query("SELECT COUNT(*) FROM learner_ai_consent_events WHERE id LIKE '21000000-%' OR id LIKE '22000000-%'")->fetchColumn() === 76, 'four consent scopes for 19 learners');
```

- [ ] **Step 2: Run integration test and verify RED**

Run:

```powershell
$env:COMPLETE_AI_DEMO_TEST_SCHEMA='talenthub_complete_demo_test_seed'
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\complete_ai_demo_seed_mysql_test.php
Remove-Item Env:COMPLETE_AI_DEMO_TEST_SCHEMA
```

Expected: FAIL because `CompleteAiDemoSeeder` does not exist; the test must still drop its disposable schema.

- [ ] **Step 3: Implement guard, preflight, and one transaction**

Use this transaction boundary:

```php
public function run(PDO $pdo, string $environment, string $password, DateTimeImmutable $clock): array
{
    if (!in_array(strtolower($environment), ['local', 'test'], true)) {
        throw new RuntimeException('Complete AI demo seed is forbidden outside local/test.');
    }
    if (strlen($password) < 12) {
        throw new RuntimeException('TALENTHUB_TEST_PASSWORD must contain at least 12 characters.');
    }
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') {
        throw new RuntimeException('Complete AI demo requires a selected MySQL schema.');
    }
    $this->assertParentsAndCatalog($pdo);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash)) {
        throw new RuntimeException('Unable to hash demo password.');
    }

    $pdo->beginTransaction();
    try {
        $counts = [];
        $this->seedFptOrganization($pdo, $hash, $clock, $counts);
        $this->seedSkills($pdo, $clock, $counts);
        $this->seedStudentSkillsAndEvidence($pdo, $clock, $counts);
        $this->seedConsent($pdo, $clock, $counts);
        $pdo->commit();
        ksort($counts, SORT_STRING);
        return $counts;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
```

At the end of later tasks, the four additional seed methods are inserted inside the same transaction before `commit()`; no sub-seeder starts its own transaction.

Preflight must assert exactly one published row for each code:

```php
private const CATALOG_CODES = [
    'holland_high', 'mbti_high', 'disc_high', 'multiple_intelligence_high',
    'holland_college', 'mbti_college', 'disc_college', 'multiple_intelligence_college',
];
```

Verify the 11 existing THPT profile IDs, school `20000000-0000-4000-8000-000000000001`, six existing teacher profiles, and role codes `school`, `teacher`, `student`. Any missing or duplicate dependency throws before `beginTransaction()`.

Seed FPT parent rows with stable IDs from `CompleteAiDemoDataset::uuid()`:

- school name `Đại học FPT`, level `Đại học`, academic year `2026 - 2027`;
- administrator `fpt.admin@talenthub.vn` with role `school` and a `school_members.memberRole='admin'` row;
- four lecturer users/profiles from Task 1 with role `teacher`;
- four classes `Năm 1` through `Năm 4`, `gradeLevel=1..4`;
- eight users/profiles from Task 1 with role `student` and two students per class.

For owned UUIDs use MySQL `INSERT` with `ON DUPLICATE KEY UPDATE` only after confirming the existing row's primary ID has the expected namespace. For shared `skills.code`, resolve an existing active row by code; insert the deterministic row only when the code does not exist. Do not overwrite a foreign skill row.

Use deterministic synthetic phone numbers `0929000001` through `0929000008` for students and `0929100001` through `0929100004` for lecturers. Set the school contact email to `fpt.demo@talenthub.vn`; no seeded row may use an official FPT mailbox.

Seed one `learner_skill_evidence` record for every `student_skills` row with:

```php
[
    'evidenceType' => 'teacher_observation',
    'evidenceRef' => 'demo://verified/' . $studentSkillId,
    'verificationStatus' => 'verified',
    'observedAt' => $clock->modify('-20 days')->format('Y-m-d H:i:s.u'),
]
```

Seed one latest `granted` event per learner/scope with policy `learner-ai-consent-1.0`, deterministic IDs/request IDs, and scopes `assessment`, `skills`, `activity`, `evaluation`.

- [ ] **Step 4: Run integration test through identity/consent assertions**

Expected: FPT identity, skill, evidence, consent, idempotency, and outside-namespace assertions pass; later journey assertions remain absent until Tasks 3-4.

- [ ] **Step 5: Commit identity and consent seed**

```powershell
git add -- Database/seeds/Demo/CompleteAiDemoSeeder.php tests/complete_ai_demo_seed_mysql_test.php
git commit -m "feat(demo): seed FPT identities and learner evidence"
```

### Task 3: Canonically scored high-school and college assessments

**Files:**
- Modify: `Database/seeds/Demo/CompleteAiDemoSeeder.php`
- Modify: `tests/complete_ai_demo_seed_mysql_test.php`

**Interfaces:**
- Consumes: `ScorerRegistry`, four existing scorer classes, published catalog versions, and `CompleteAiDemoDataset::assessmentPlan()`
- Produces: deterministic submitted attempts, version metadata, answers, and scored results for all 19 learners

- [ ] **Step 1: Add failing band and scoring assertions**

Add SQL assertions that every learner has at least two submitted results, both heroes have four, and no band crossing exists:

```php
$wrongBands = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM test_attempts a
JOIN student_profiles sp ON sp.id=a.studentId
JOIN classes c ON c.id=sp.classId
JOIN talent_tests t ON t.id=a.testId
WHERE (c.schoolId='20000000-0000-4000-8000-000000000001' AND t.code NOT LIKE '%\_high')
   OR (c.schoolId LIKE '22000000-%' AND t.code NOT LIKE '%\_college')
SQL)->fetchColumn();
demo_mysql_assert($wrongBands === 0, 'assessment bands never cross');

foreach (CompleteAiDemoDataset::heroStudentIds() as $studentId) {
    $statement = $pdo->prepare('SELECT COUNT(*) FROM test_attempts WHERE studentId=? AND status=\'submitted\'');
    $statement->execute([$studentId]);
    demo_mysql_assert((int) $statement->fetchColumn() === 4, 'hero has four submitted attempts');
}
```

Also assert each result JSON decodes to integer scores in `[0,100]`, `inputHash` is 64 lowercase hex, and answer count equals required published question count.

- [ ] **Step 2: Run test and verify RED**

Expected: FAIL because there are zero attempts/results.

- [ ] **Step 3: Seed answers and call the real scorers**

Construct the same approved registry as `RepositoryFactory`:

```php
private function scorers(): ScorerRegistry
{
    return new ScorerRegistry([
        'holland-riasec-1.0' => new HollandScorer(),
        'mbti-education-1.0' => new MbtiScorer(),
        'disc-education-1.0' => new DiscScorer(),
        'multiple-intelligence-1.0' => new MultipleIntelligenceScorer(),
    ]);
}
```

For each planned code:

1. Resolve exactly one published `talent_tests` + `learner_assessment_versions` row.
2. Load `question_id`, `position`, `dimension_code`, and `required` ordered by position.
3. Generate a deterministic Likert integer 1-5. Use learner index to select a primary dimension and assign `5` to its positive questions, `2` to other dimensions, and invert values for `:-` questions. MBTI uses one preferred pole per axis. This produces varied but reproducible results across R/I/A/S/E/C.
4. Call `$registry->forVersion($scoringVersion)->score($questions, $answers)->toArray()`.
5. Upsert `test_attempts`, `learner_assessment_attempt_metadata`, `learner_assessment_answers`, and `test_results` using deterministic owned IDs.
6. Compute the canonical input hash with sorted answers:

```php
ksort($answers, SORT_STRING);
$inputHash = hash('sha256', json_encode([
    'assessment_version' => $version,
    'scoring_version' => $scoringVersion,
    'schema_hash' => $schemaHash,
    'answers' => $answers,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
```

Use `submittedAt = $clock - (10 + learnerIndex) days`, `startedAt = submittedAt - 30 minutes`, `status='submitted'` in both lifecycle tables, and preserve `scoringVersion` from the catalog.

- [ ] **Step 4: Run integration and existing scorer tests**

Run:

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests\complete_ai_demo_seed_mysql_test.php
& $php tests\learner_assessment_scorer_contract_test.php
& $php tests\learner_catalog_scorer_integration_test.php
& $php tests\learner_catalog_cross_consistency_test.php
```

Expected: all exit `0` and report `OK`.

- [ ] **Step 5: Commit canonical assessment data**

```powershell
git add -- Database/seeds/Demo/CompleteAiDemoSeeder.php tests/complete_ai_demo_seed_mysql_test.php
git commit -m "feat(demo): seed band-correct scored assessments"
```

### Task 4: Activities, QR evidence, experiences, and teacher evaluations

**Files:**
- Modify: `Database/seeds/Demo/CompleteAiDemoSeeder.php`
- Modify: `tests/complete_ai_demo_seed_mysql_test.php`

**Interfaces:**
- Consumes: activity and registration plans from Task 1
- Produces: 18 activities, 40 registrations, 8 QR sessions, 20 confirmed check-ins, 20 confirmed experiences, 20 published assessments, 3 criteria, and 60 criterion scores

- [ ] **Step 1: Add failing lifecycle assertions**

Add exact checks:

```php
demo_mysql_assert(demo_owned_count($pdo, 'activities') === 18, '18 owned activities');
demo_mysql_assert(demo_owned_count($pdo, 'activity_registrations') === 40, '40 owned registrations');
demo_mysql_assert(demo_owned_count($pdo, 'checkins') === 20, '20 confirmed check-ins');
demo_mysql_assert(demo_owned_count($pdo, 'experience_logs') === 20, '20 confirmed experiences');
demo_mysql_assert(demo_owned_count($pdo, 'assessments') === 20, '20 published evaluations');
demo_mysql_assert(demo_owned_count($pdo, 'assessment_scores') === 60, 'three scores per evaluation');

$invalid = (int) $pdo->query(<<<'SQL'
SELECT COUNT(*)
FROM checkins c
JOIN activity_registrations r ON r.id=c.registrationId
JOIN activity_qr_sessions q ON q.id=c.qrSessionId
WHERE c.status<>'confirmed'
   OR r.status<>'attended'
   OR r.activityId<>q.activityId
   OR c.checkedInAt IS NULL
   OR c.confirmedAt IS NULL
SQL)->fetchColumn();
demo_mysql_assert($invalid === 0, 'check-in lifecycle is coherent');
```

Assert cancelled registrations have no check-in/experience/evaluation, each assessment's student/activity pair has a matching registration, each experience's student/activity pair matches its check-in registration, and no activity links a teacher from another organization.

- [ ] **Step 2: Run test and verify RED**

Expected: FAIL with missing journey rows.

- [ ] **Step 3: Seed exact activity and QR lifecycle**

Add these methods inside the Task 2 transaction before commit:

```php
$this->seedActivities($pdo, $clock, $counts);
$this->seedRegistrations($pdo, $clock, $counts);
$this->seedQrAndExperiences($pdo, $clock, $counts);
$this->seedTeacherEvaluations($pdo, $clock, $counts);
```

For each organization seed exactly four QR sessions:

- active: linked to the first ongoing activity, `expiresAt=clock+2 hours`, `revokedAt=NULL`;
- expired A: linked to the first completed activity used by five attended registrations, expiring one hour after that activity ends;
- expired B: linked to the second completed activity used by five attended registrations, expiring one hour after that activity ends;
- revoked: linked to the second ongoing activity, `expiresAt=clock+1 hour`, `revokedAt=clock-1 hour`.

Hash only a synthetic derivation key:

```php
$tokenHash = hash('sha256', 'talenthub-demo-qr-v1:' . $owner . ':' . $sessionKey);
```

Set `usedScans` to the count of linked check-ins and `maxScans=100`. The 10 attended registrations in each organization are split five/five across the two completed activities and their matching historical sessions. All receive confirmed check-ins between activity start and the session expiry. The database stores no raw token.

Create three criteria with deterministic IDs: `teamwork` (0-10), `initiative` (0-10), and `execution` (0-10). Each attended learner receives one published assessment with `overallScore` between `7.20` and `9.40`, a Vietnamese constructive comment, and all three criterion scores. Teacher/lecturer and activity must belong to the same organization.

- [ ] **Step 4: Verify lifecycle and teacher QR regressions**

Run:

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests\complete_ai_demo_seed_mysql_test.php
& $php tests\qr_session_migration_contract_test.php
& $php tests\learner_ai_sources_test.php
& $php tests\learner_career_activity_source_test.php
```

Expected: all exit `0`.

- [ ] **Step 5: Commit the complete journey fixtures**

```powershell
git add -- Database/seeds/Demo/CompleteAiDemoSeeder.php tests/complete_ai_demo_seed_mysql_test.php
git commit -m "feat(demo): seed QR experiences and evaluations"
```

### Task 5: Read-only verifier and seed CLI integration

**Files:**
- Create: `Database/seeds/Demo/CompleteAiDemoVerifier.php`
- Create: `bin/verify-demo-ai.php`
- Modify: `bin/seed.php`
- Modify: `tests/complete_ai_demo_seed_mysql_test.php`

**Interfaces:**
- Produces: `CompleteAiDemoVerifier::verify(PDO $pdo): array{ok:bool,counts:array<string,int>,violations:list<string>,heroes:array<string,array<string,mixed>>}`
- Consumes: `CompleteAiDemoSeeder::run(PDO $pdo, string $environment, string $password, DateTimeImmutable $clock): array<string,int>`
- Produces CLI: `php bin/seed.php --demo-ai`
- Produces CLI: `php bin/verify-demo-ai.php`

- [ ] **Step 1: Add failing verifier and CLI contract assertions**

Assert verifier output has `ok=true`, no violations, correct expected minimums, and these hero source counts: at least 5 skills, 4 assessments, 2 experiences, 2 evaluations, 4 consent scopes, and at least 1 open matching opportunity.

Add source-level assertions that `bin/seed.php` recognizes `--demo-ai`, requires `CompleteAiDemoSeeder.php`, and does not call `run-demo-ai.php` or any HTTP provider.

- [ ] **Step 2: Run test and verify RED**

Expected: FAIL because verifier and CLI option do not exist.

- [ ] **Step 3: Implement verifier with read-only SQL**

The verifier must return violations rather than mutate data. Include checks for:

- exact organization/user/profile counts;
- minimum journey counts from Task 1;
- four consent scopes per learner;
- result band matching by school;
- answer/result/attempt closure;
- registration/check-in/experience/evaluation closure;
- QR hash format `^[a-f0-9]{64}$`, unique hashes, and no raw-token column;
- two hero quality gates evaluated through `ConsentPolicy`, `RecommendationSnapshotBuilder`, and `DataQualityGate`;
- no recommendation model run with `engineType='model'` is treated as visible by the CLI.

CLI output must contain only counts, state names, engine type, provider/model diagnostics allowed by `RecommendationConfig::diagnostics()`, and violation codes. It must never output payload JSON, answers, comments, headers, or secrets.

- [ ] **Step 4: Add `--demo-ai` without changing `--demo`**

In `bin/seed.php`:

```php
require dirname(__DIR__).'/Database/seeds/Demo/CompleteAiDemoDataset.php';
require dirname(__DIR__).'/Database/seeds/Demo/CompleteAiDemoSeeder.php';

$demoAi = in_array('--demo-ai', $argv, true);
if ($demoAi && !in_array($env, ['local', 'test'], true)) {
    throw new RuntimeException('Complete AI demo seed is allowed only in local/test.');
}

if ($demoAi) {
    $password = Environment::required(SchoolDemoSeeder::PASSWORD_ENV);
    (new SchoolDemoSeeder())->run($pdo, $env, $password);
    (new CompleteAiDemoSeeder())->run(
        $pdo,
        $env,
        $password,
        new DateTimeImmutable('today', new DateTimeZone('UTC')),
    );
}
```

Reuse the existing `talenthub:system_seeds` lock. Print only `[OK] complete AI demo seed` after success.

- [ ] **Step 5: Run seed integration twice and verifier**

Expected: both seed runs exit `0`; second-run table counts equal first-run counts; verifier returns `ok=true`.

- [ ] **Step 6: Commit CLI and verifier**

```powershell
git add -- Database/seeds/Demo/CompleteAiDemoVerifier.php bin/verify-demo-ai.php bin/seed.php tests/complete_ai_demo_seed_mysql_test.php
git commit -m "feat(demo): add safe seed and verification commands"
```

### Task 6: Rule-visible and 9Router-shadow runner

**Files:**
- Create: `Database/seeds/Demo/CompleteAiDemoAiRunner.php`
- Create: `bin/run-demo-ai.php`
- Create: `tests/complete_ai_demo_runner_test.php`

**Interfaces:**
- Produces: `CompleteAiDemoAiRunner::run(PDO $pdo, RecommendationEngine $modelEngine, array $studentIds): array<string,array<string,mixed>>`
- Consumes: database sources, `DatabaseRecommendationRepository`, `RuleRecommendationEngine`, `RecommendationService`, `ShadowRunService`, and injected model engine
- CLI constructs the real configured model engine only in local/test with enabled shadow and visible percentage zero

- [ ] **Step 1: Write mocked-provider RED test**

On the disposable seeded database, inject a `ModelRecommendationEngine` backed by a fake successful provider that returns one valid item using a supplied evidence reference. Run both hero IDs and assert:

```php
foreach ($report as $studentId => $row) {
    runner_assert($row['quality_state'] === 'ready', 'hero passes quality gate');
    runner_assert($row['visible_engine'] === 'rule', 'visible output remains rule');
    runner_assert($row['shadow_engine'] === 'model', 'shadow model executed');
    runner_assert($row['shadow_valid'] === true, 'shadow evaluation valid');
}

$visibleRuns = (int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_runs WHERE engineType='rule' AND status='completed'")->fetchColumn();
$shadowRuns = (int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_runs WHERE engineType='model' AND status='completed'")->fetchColumn();
runner_assert($visibleRuns === 2, 'two persisted rule runs');
runner_assert($shadowRuns === 2, 'two persisted shadow model runs');
```

Run twice with stable idempotency keys and assert the second run creates no additional recommendation runs.

- [ ] **Step 2: Run test and verify RED**

Expected: FAIL because runner does not exist.

- [ ] **Step 3: Implement runner with production database sources**

For each hero:

```php
$owner = str_starts_with($studentId, '20000000-') ? 'thpt' : 'fpt';
$scopes = (new ConsentPolicy(new DatabaseConsentSource($pdo)))->allowedScopes($studentId);
$input = $snapshotBuilder->build($studentId, $scopes);
$quality = (new DataQualityGate())->evaluate($input);
if ($quality->state() !== 'ready') {
    throw new RuntimeException('Demo hero failed quality gate: ' . $quality->state());
}

$context = new RecommendationContext(
    $scopes,
    CompleteAiDemoDataset::uuid($owner, 'ai-request', $input->contentHash()),
    'demo-rule-' . substr(hash('sha256', $studentId . ':' . $input->contentHash()), 0, 64),
    $studentId,
);
$visibleResult = (new RuleRecommendationEngine())->generate($input, $context);
```

Persist visible output through `RecommendationService` using the same sources and repository. Then call `ShadowRunService::run($studentId, $input, $context, $visibleResult)` with the injected model engine. If a run is already complete for the stable key, load/report it rather than treating reuse as an error.

Return only:

```php
[
    'quality_state' => 'ready',
    'visible_engine' => 'rule',
    'visible_item_count' => count($visibleResult->items()),
    'shadow_engine' => $shadowResult->engineType(),
    'shadow_valid' => $evaluation['valid'],
    'shadow_violation_codes' => $evaluation['violations'],
]
```

- [ ] **Step 4: Implement local-only real CLI guard**

`bin/run-demo-ai.php` must:

1. load `bin/bootstrap.php`, learner data bootstrap, and AI bootstrap;
2. reject any environment outside `local|test`;
3. require `RecommendationConfig::enabled()`, `shadowEnabled()`, and `visiblePercent() === 0`;
4. construct `HttpRecommendationProvider`, `ModelRecommendationEngine`, and rate limiter from existing config;
5. invoke exactly the two IDs from `heroStudentIds()`;
6. print the redacted report only;
7. exit nonzero if either hero is not ready, visible engine is not rule, shadow falls back, or evaluation is invalid.

Do not print exception traces for provider errors; map them to `provider_unavailable`, `provider_fallback`, or `shadow_invalid`.

- [ ] **Step 5: Run mocked tests and existing shadow regressions**

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests\complete_ai_demo_runner_test.php
& $php tests\learner_ai_9router_shadow_integration_test.php
& $php tests\learner_recommendation_service_test.php
& $php tests\learner_recommendation_repository_test.php
```

Expected: all exit `0`; no network request occurs in tests.

- [ ] **Step 6: Commit the runner**

```powershell
git add -- Database/seeds/Demo/CompleteAiDemoAiRunner.php bin/run-demo-ai.php tests/complete_ai_demo_runner_test.php
git commit -m "feat(ai): run complete demo in shadow mode"
```

### Task 7: Full disposable verification and role-isolation gate

**Files:**
- Modify: `tests/complete_ai_demo_seed_mysql_test.php`
- Modify: `tests/complete_ai_demo_runner_test.php`

**Interfaces:**
- Consumes all prior tasks
- Produces one repeatable, self-cleaning pre-main-database acceptance gate

- [ ] **Step 1: Add final isolation snapshots**

Before seed, snapshot exact rows for:

- `roles` and `permissions`;
- users/profiles/memberships outside `21000000-%` and `22000000-%`;
- enterprises and enterprise members;
- canonical assessment catalog tables;
- `student@test.talenthub.local` and `teacher@test.talenthub.local`.

After two seeds compare sorted canonical JSON and SHA-256 hashes. The only permitted changes outside owned IDs are shared skill rows that were absent and school counters on the two demo schools. Catalog hashes must be byte-identical.

- [ ] **Step 2: Assert all eight customer stages**

For each hero prove nonempty rows for:

1. learner profile;
2. four assessment results;
3. ready AI snapshot with evidence;
4. career-group recommendation action;
5. open activity recommendation action;
6. confirmed check-in and experience;
7. published teacher/lecturer evaluation;
8. persisted historical recommendation run/items/evidence.

Also assert teacher QR session management can list at least one ongoing activity and all three QR states for a THPT teacher and an FPT lecturer through `TeacherQrSessionService::pageData()`.

- [ ] **Step 3: Run the complete disposable gate**

```powershell
$env:COMPLETE_AI_DEMO_TEST_SCHEMA='talenthub_complete_demo_test_acceptance'
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests\complete_ai_demo_dataset_contract_test.php
& $php tests\complete_ai_demo_seed_mysql_test.php
& $php tests\complete_ai_demo_runner_test.php
Remove-Item Env:COMPLETE_AI_DEMO_TEST_SCHEMA
```

Expected: three `OK` reports, zero leaked schema after `finally`, and no changes to `talenthub_local`.

- [ ] **Step 4: Run multi-role and UI regressions**

Use a separate database whose name contains `test` for the school suite:

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php bin\migrate.php validate
& $php tests\learner_assessment_api_test.php
& $php tests\learner_ai_recommendation_render_test.php
& $php tests\learner_career_rules_test.php
& $php tests\learner_career_quality_gate_test.php
& $php tests\student_repository_schema_compatibility_test.php
node tests\learner_activities_ui_test.js
node tests\learner_ai_recommendation_ui_test.js
node tests\learner_api_client_test.js
node tests\learner_assessment_ui_test.js
node tests\learner_ecosystem_ui_test.js
node tests\learner_holland_ui_test.js
node tests\learner_sidebar_banner_ui_test.js
```

Temporarily point `DB_DATABASE` at a disposable test database before running `php bin/test-school-suite.php`, then restore local configuration and delete only that verified test schema. Expected: all commands exit `0`.

- [ ] **Step 5: Commit acceptance gates**

```powershell
git add -- tests/complete_ai_demo_seed_mysql_test.php tests/complete_ai_demo_runner_test.php
git commit -m "test(demo): verify journey and role isolation"
```

### Task 8: Back up, populate `talenthub_local`, run live 9Router, and report

**Files:**
- Create: `docs/superpowers/database-change-requests/2026-08-20-complete-ai-demo-dataset.md`
- Verify only: ignored `.env`, `talenthub_local`, Git status/history

**Interfaces:**
- Consumes: `php bin/seed.php --demo-ai`, `php bin/verify-demo-ai.php`, `php bin/run-demo-ai.php`
- Produces: verified local demo data and an auditable redacted execution report

- [ ] **Step 1: Confirm branch, clean scope, migration state, and secret hygiene**

```powershell
git branch --show-current
git status --short
git check-ignore -v .env
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\migrate.php validate
```

Expected: branch `feature/student`; only known unrelated `.claude/` and `.qwen/` may be untracked; `.env` is ignored; migration validation is OK.

Search tracked files and Git history for the configured key value without displaying matches. Record only match count; expected `0`.

- [ ] **Step 2: Create and validate a timestamped SQL backup**

```powershell
$dump = Get-ChildItem 'D:\laragon\bin\mysql' -Filter mysqldump.exe -Recurse | Select-Object -First 1 -ExpandProperty FullName
$backupDir = Join-Path $env:LOCALAPPDATA 'Temp\TalentHubBackups'
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
$backupPath = Join-Path $backupDir ("talenthub_local_pre_complete_ai_demo_{0}.sql" -f (Get-Date -Format 'yyyyMMdd_HHmmss'))
& $dump -u root --single-transaction --routines --triggers --result-file=$backupPath talenthub_local
if ($LASTEXITCODE -ne 0 -or !(Test-Path $backupPath) -or (Get-Item $backupPath).Length -lt 1024) { throw 'Database backup failed validation.' }
Get-Item $backupPath | Select-Object FullName,Length,LastWriteTime
```

Expected: nonempty backup outside the repository. Record its absolute path in the DCR report.

- [ ] **Step 3: Record before-state and seed twice**

Run a read-only count query for users by role plus all touched journey tables and save only counts in the DCR. Then:

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php bin\seed.php --demo-ai
& $php bin\verify-demo-ai.php
& $php bin\seed.php --demo-ai
& $php bin\verify-demo-ai.php
```

Expected: both seeds succeed; both verifier reports are identical and `ok=true`; second seed has no count drift.

- [ ] **Step 4: Execute live 9Router shadow runs**

Confirm the local 9Router endpoint is running, then:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' bin\run-demo-ai.php
```

Expected redacted result for both `hs.minh@talenthub.vn` and `sv.fpt.an@talenthub.vn`:

- quality state `ready`;
- visible engine `rule`;
- shadow engine `model`;
- shadow valid `true`;
- no violation codes;
- at least one recommendation item.

If the provider is unavailable, stop and report the safe code. Do not change visible rollout, disable consent, invent a model row, or reseed destructively.

- [ ] **Step 5: Verify persisted model/rule separation and role isolation**

Run `bin/verify-demo-ai.php` again and read-only SQL that groups recommendation runs by student and `engineType`. Expected: each hero has a completed Rule run and a completed Model shadow run; learner-visible verification still reports Rule Engine. Compare pre-existing role/user/profile hashes captured by the integration gate; no pre-existing row was removed or reassigned.

- [ ] **Step 6: Run final regression and syntax checks**

```powershell
$php='D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
Get-ChildItem Database\seeds\Demo,bin,tests -Filter '*.php' -Recurse | ForEach-Object { & $php -l $_.FullName; if ($LASTEXITCODE -ne 0) { throw "PHP lint failed: $($_.FullName)" } }
& $php tests\complete_ai_demo_dataset_contract_test.php
$env:COMPLETE_AI_DEMO_TEST_SCHEMA='talenthub_complete_demo_test_final'
& $php tests\complete_ai_demo_seed_mysql_test.php
& $php tests\complete_ai_demo_runner_test.php
Remove-Item Env:COMPLETE_AI_DEMO_TEST_SCHEMA
& $php tests\learner_ai_9router_shadow_integration_test.php
& $php tests\learner_assessment_scorer_contract_test.php
& $php tests\learner_catalog_scorer_integration_test.php
& $php tests\qr_session_migration_contract_test.php
git diff --check
git status --short
```

Expected: every command exits `0`; no `.env` or backup is staged; only intended tracked files plus unrelated `.claude/`/`.qwen/` status remain.

- [ ] **Step 7: Complete DCR evidence and commit implementation**

The DCR must include:

- approval reference to this user-approved design/plan;
- target `talenthub_local`, environment `local`, execution timestamp UTC;
- backup path and size;
- before/after redacted table counts;
- first/second seed idempotency comparison;
- high/college catalog mapping counts;
- role-isolation result;
- live AI redacted result for both heroes;
- explicit QR Phase 2 exclusion;
- secret scan count `0`;
- all verification command exit statuses.

Then:

```powershell
git add -- Database/seeds/Demo/CompleteAiDemoDataset.php Database/seeds/Demo/CompleteAiDemoSeeder.php Database/seeds/Demo/CompleteAiDemoVerifier.php Database/seeds/Demo/CompleteAiDemoAiRunner.php bin/seed.php bin/verify-demo-ai.php bin/run-demo-ai.php tests/complete_ai_demo_dataset_contract_test.php tests/complete_ai_demo_seed_mysql_test.php tests/complete_ai_demo_runner_test.php docs/superpowers/database-change-requests/2026-08-20-complete-ai-demo-dataset.md
git diff --cached --check
git commit -m "feat(demo): populate complete learner AI journeys"
```

Do not push or merge. Report the final commit hash, test totals, database counts, backup path, redacted model status, and remaining QR Phase 2 gap to the user.

## Claude Handoff Notes

- Begin from commit `3dc942e` or later on `feature/student`.
- Read the source design before Task 1.
- Use `superpowers:subagent-driven-development` if available; each task above is a review boundary.
- Preserve unrelated `.claude/` and `.qwen/` files.
- The current local key is already configured in ignored `.env`; never echo it or copy it into a command, test, plan, DCR, or commit.
- The existing staging `LearnerAiSyntheticDatasetV2` is restricted to its disposable schema. Do not relax that guard and do not import it into `talenthub_local`.
- The task is complete only after two idempotent main-schema seeds, two valid live shadow runs, role-isolation verification, and the final redacted DCR report.
