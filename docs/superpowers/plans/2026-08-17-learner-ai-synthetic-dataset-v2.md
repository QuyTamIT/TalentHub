# Learner AI Synthetic Dataset V2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build and load an insert-only, deterministic 24-learner synthetic dataset that exercises the existing learner recommendation pipeline in the approved disposable Laragon MySQL schema.

**Architecture:** A pure PHP dataset class declares every participant, catalog item, scenario, and generated row without opening a database connection. A separate PDO seeder validates the disposable target, canonical migration checksums, V1 parents, table contracts, and reserved-row equality before performing ordered inserts in one transaction. Pure contract tests run without MySQL; a separately gated integration test writes only to `talenthub_ai_backup_verify_004_20260816` and validates the real consent, source, snapshot, quality, and rule paths.

**Tech Stack:** PHP 8.3.30 from Laragon, PDO MySQL, MySQL 8.4.3, existing learner AI source adapters/rule engine, standalone PHP test scripts, Git.

## Global Constraints

- Never write to `talenthub_local`; the only authorized write target is `talenthub_ai_backup_verify_004_20260816`.
- Never create, drop, truncate, replace, alter, update, or delete a database, table, or row.
- Preserve `LearnerAiPilotSeeder` V1 and its 61-row provenance unchanged.
- Restrict production code changes to `Database/seeds/learner/Staging`; do not edit Teacher, School, Enterprise, protected API, or shared runtime code.
- Do not add or alter `internship_posts`; opportunity data remains unavailable and the existing source must fail closed to an empty list.
- Every new email uses `.example`; names, phones, comments, question text, request IDs, QR hashes, and timestamps are deterministic synthetic values.
- Use UTC `DATETIME(6)` values. The quality test clock is fixed at `2026-08-17T00:00:00.000000+00:00`.
- The participant matrix is exactly 24 learners: four per RIASEC archetype, 18 ready, four `insufficient_data`, and two `consent_required`.
- The assessment is version `2.0.0`, scoring version `pilot-riasec-2`, with exactly 24 original synthetic questions and four questions per RIASEC dimension.
- Do not seed recommendation snapshots, runs, items, evidence, feedback, or audit records; the integration test evaluates snapshots and rule results in memory.
- Use TDD for every implementation task and commit only after the focused and regression commands are green.

## File Map

- Create `Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php`: pure deterministic participant/catalog/row declaration and invariant validation.
- Create `Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2Seeder.php`: MySQL target guard, schema/migration/V1 preflight, row comparison, transactional insert-only persistence.
- Create `tests/learner_ai_synthetic_dataset_v2_contract_test.php`: MySQL-free dataset, content, ownership, and forbidden-operation contract.
- Create `tests/learner_ai_synthetic_dataset_v2_mysql_test.php`: disposable-schema idempotency, isolation, source, quality, consent, provenance, and rule verification.
- Create `docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md`: exact target, row families, content fingerprint, risk boundary, approval and execution evidence.
- Create `.superpowers/sdd/learner-ai-synthetic-dataset-v2-execution-report.md`: local execution evidence; this remains ignored and must not be staged.
- Do not modify `Database/seeds/learner/Staging/LearnerAiPilotSeeder.php`.

---

### Task 1: Pure V2 dataset contract

**Files:**
- Create: `tests/learner_ai_synthetic_dataset_v2_contract_test.php`
- Create: `Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php`

**Interfaces:**
- Consumes: V1 IDs documented by `LearnerAiPilotSeeder::reservedPrefix()` and `LearnerAiPilotSeeder::studentIds()`.
- Produces: `LearnerAiSyntheticDatasetV2::participants(): array`, `questions(): array`, `rows(): array`, `studentIds(): array`, `expectedStates(): array`, `touchedTables(): array`, `contentHash(): string`, and `validate(): void`.
- Row shape: `array{table:string,id:string,values:array<string,scalar|null>}` with `values['id'] === id`.
- Participant shape: `array{sequence:int,student_id:string,primary:string,scenario:string,expected_state:string,expected_missing:list<string>,scores:array<string,int>}`.

- [ ] **Step 1: Write the failing pure contract test**

Create the test with explicit assertions for the approved matrix:

```php
<?php

declare(strict_types=1);

use TalentHub\Learner\Seeds\Staging\LearnerAiSyntheticDatasetV2;

function v2_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$root = dirname(__DIR__);
$datasetFile = $root . '/Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php';
v2_contract_assert(is_file($datasetFile), 'V2 dataset class exists');
require_once $datasetFile;

LearnerAiSyntheticDatasetV2::validate();
$participants = LearnerAiSyntheticDatasetV2::participants();
$questions = LearnerAiSyntheticDatasetV2::questions();
$rows = LearnerAiSyntheticDatasetV2::rows();

v2_contract_assert(count($participants) === 24, 'exactly 24 participants');
v2_contract_assert(count(array_unique(array_column($participants, 'student_id'))) === 24, 'participant IDs are distinct');
v2_contract_assert(array_count_values(array_column($participants, 'primary')) === ['R' => 4, 'I' => 4, 'A' => 4, 'S' => 4, 'E' => 4, 'C' => 4], 'RIASEC is balanced');
v2_contract_assert(array_count_values(array_column($participants, 'expected_state')) === ['ready' => 18, 'insufficient_data' => 4, 'consent_required' => 2], 'state matrix is exact');
v2_contract_assert(count($questions) === 24, 'exactly 24 questions');
v2_contract_assert(array_count_values(array_column($questions, 'dimension')) === ['R' => 4, 'I' => 4, 'A' => 4, 'S' => 4, 'E' => 4, 'C' => 4], 'four questions per dimension');
v2_contract_assert(count($rows) === 1116, 'V2 declares the fixed 1116-row contract');
v2_contract_assert(preg_match('/^[a-f0-9]{64}$/', LearnerAiSyntheticDatasetV2::contentHash()) === 1, 'content fingerprint is SHA-256');

$rowKeys = [];
foreach ($rows as $row) {
    $key = $row['table'] . "\0" . $row['id'];
    v2_contract_assert(!isset($rowKeys[$key]), 'table/id pairs are unique');
    $rowKeys[$key] = true;
    v2_contract_assert(($row['values']['id'] ?? null) === $row['id'], 'row id is declared in values');
    foreach ($row['values'] as $value) {
        if (is_string($value) && str_contains($value, '@')) {
            v2_contract_assert(preg_match('/@(?:[A-Za-z0-9-]+\.)*example$/', $value) === 1, 'email-like values use .example only');
        }
    }
}

$source = file_get_contents($datasetFile);
v2_contract_assert(is_string($source), 'dataset source is readable');
foreach (['UPDATE ', 'DELETE ', 'REPLACE ', 'DROP ', 'TRUNCATE ', 'ALTER '] as $forbidden) {
    v2_contract_assert(stripos($source, $forbidden) === false, 'dataset contains no destructive or mutable SQL token: ' . trim($forbidden));
}

echo 'learner_ai_synthetic_dataset_v2_contract_test: OK' . PHP_EOL;
```

- [ ] **Step 2: Run the test and verify the RED state**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_synthetic_dataset_v2_contract_test.php
```

Expected: FAIL at `V2 dataset class exists` and no database connection.

- [ ] **Step 3: Implement the participant matrix, original question bank, and score profiles**

The class is `final`, contains no PDO dependency, and starts with these exact declarations:

```php
namespace TalentHub\Learner\Seeds\Staging;

use RuntimeException;

final class LearnerAiSyntheticDatasetV2
{
    public const RESERVED_PREFIX = '00000000-0000-4000-8000-';
    public const TEST_ID = self::RESERVED_PREFIX . '000000000060';
    public const VERSION_ID = self::RESERVED_PREFIX . '000000001130';
    public const VERSION = '2.0.0';
    public const SCORING_VERSION = 'pilot-riasec-2';
    public const POLICY_VERSION = 'pilot-ai-policy-2';
    public const RECENT_SUBMITTED_AT = '2026-08-10 09:30:00.000000';
    public const STALE_SUBMITTED_AT = '2024-01-15 09:00:00.000000';

    private const PROFILES = [
        'R' => ['R' => 95, 'I' => 80, 'A' => 60, 'S' => 55, 'E' => 70, 'C' => 65],
        'I' => ['R' => 80, 'I' => 95, 'A' => 65, 'S' => 55, 'E' => 60, 'C' => 70],
        'A' => ['R' => 75, 'I' => 70, 'A' => 95, 'S' => 65, 'E' => 60, 'C' => 55],
        'S' => ['R' => 70, 'I' => 75, 'A' => 65, 'S' => 95, 'E' => 60, 'C' => 55],
        'E' => ['R' => 75, 'I' => 70, 'A' => 60, 'S' => 65, 'E' => 95, 'C' => 55],
        'C' => ['R' => 70, 'I' => 75, 'A' => 55, 'S' => 60, 'E' => 65, 'C' => 95],
    ];

    private const SCENARIOS = [
        101 => ['R', 'complete', 'ready', []], 102 => ['R', 'complete', 'ready', []],
        103 => ['R', 'complete', 'ready', []], 104 => ['R', 'one_skill', 'insufficient_data', ['skills']],
        105 => ['I', 'complete', 'ready', []], 106 => ['I', 'complete', 'ready', []],
        107 => ['I', 'complete', 'ready', []], 108 => ['I', 'no_experience', 'insufficient_data', ['experience']],
        109 => ['A', 'complete', 'ready', []], 110 => ['A', 'complete', 'ready', []],
        111 => ['A', 'complete', 'ready', []], 112 => ['A', 'stale_assessment', 'insufficient_data', ['assessment']],
        113 => ['S', 'complete', 'ready', []], 114 => ['S', 'complete', 'ready', []],
        115 => ['S', 'complete', 'ready', []], 116 => ['S', 'draft_evaluation', 'insufficient_data', ['evaluations']],
        117 => ['E', 'complete', 'ready', []], 118 => ['E', 'complete', 'ready', []],
        119 => ['E', 'complete', 'ready', []], 120 => ['E', 'revoked_evaluation', 'consent_required', []],
        121 => ['C', 'complete', 'ready', []], 122 => ['C', 'complete', 'ready', []],
        123 => ['C', 'complete', 'ready', []], 124 => ['C', 'missing_activity_consent', 'consent_required', []],
    ];
}
```

Use these exact 24 question codes/texts. `R1`, `I1`, and `A1` retain their V1 IDs and content; the remaining 21 questions receive deterministic IDs `...001101` through `...001121` in dimension/code order:

```php
private const QUESTION_TEXT = [
    'R1' => 'Synthetic realistic-interest question.',
    'R2' => 'Tôi thích lắp ráp một mô hình từ các bộ phận có sẵn.',
    'R3' => 'Tôi hứng thú khi thử dụng cụ để tạo ra một sản phẩm nhỏ.',
    'R4' => 'Tôi muốn thực hành quy trình an toàn trong một xưởng mô phỏng.',
    'I1' => 'Synthetic investigative-interest question.',
    'I2' => 'Tôi thích đặt giả thuyết rồi kiểm tra bằng dữ liệu giả lập.',
    'I3' => 'Tôi muốn phân tích nguyên nhân của một kết quả bất thường.',
    'I4' => 'Tôi thấy hứng thú khi so sánh nhiều cách giải một vấn đề.',
    'A1' => 'Synthetic artistic-interest question.',
    'A2' => 'Tôi thích tạo bố cục hình ảnh cho một câu chuyện giả tưởng.',
    'A3' => 'Tôi muốn thử nhiều cách diễn đạt cho cùng một ý tưởng.',
    'A4' => 'Tôi hứng thú khi biến một chủ đề thành sản phẩm sáng tạo.',
    'S1' => 'Tôi thích hướng dẫn bạn khác hoàn thành một nhiệm vụ mới.',
    'S2' => 'Tôi muốn lắng nghe và giúp một nhóm thống nhất cách làm.',
    'S3' => 'Tôi thấy có động lực khi hỗ trợ người khác tiến bộ.',
    'S4' => 'Tôi hứng thú với vai trò điều phối một buổi học nhóm.',
    'E1' => 'Tôi thích trình bày một ý tưởng để thuyết phục nhóm thử nghiệm.',
    'E2' => 'Tôi muốn chủ động tổ chức nguồn lực cho một dự án nhỏ.',
    'E3' => 'Tôi hứng thú khi đặt mục tiêu và theo dõi tiến độ của nhóm.',
    'E4' => 'Tôi thích đề xuất một hướng đi khi nhóm cần quyết định.',
    'C1' => 'Tôi thích sắp xếp dữ liệu theo một cấu trúc rõ ràng.',
    'C2' => 'Tôi muốn kiểm tra chi tiết để phát hiện sai lệch trong bảng số liệu.',
    'C3' => 'Tôi hứng thú với việc chuẩn hóa các bước của một quy trình.',
    'C4' => 'Tôi thích hoàn thành công việc theo tiêu chí và thứ tự xác định.',
];
```

- [ ] **Step 4: Implement deterministic catalogs and row generation**

Declare the exact 12-skill catalog:

```php
private const SKILLS = [
    'R' => [['iot', 'IoT Fundamentals', 50], ['prototyping', 'Prototype Practice', 1001]],
    'I' => [['python', 'Python Fundamentals', 51], ['data_analysis', 'Synthetic Data Analysis', 1002]],
    'A' => [['visual_design', 'Visual Design Practice', 1003], ['storytelling', 'Digital Storytelling', 1004]],
    'S' => [['peer_mentoring', 'Peer Mentoring', 1005], ['facilitation', 'Group Facilitation', 1006]],
    'E' => [['pitching', 'Idea Pitching', 1007], ['initiative', 'Project Initiative', 1008]],
    'C' => [['spreadsheet', 'Spreadsheet Accuracy', 1009], ['quality_control', 'Quality Control Practice', 1010]],
];
```

Declare the 12-activity catalog, reusing V1 activity `...000030` and adding IDs `...001021` through `...001031`:

```php
private const ACTIVITIES = [
    ['R', 30, 'Synthetic Technical Workshop', 'technology'],
    ['R', 1021, 'Synthetic Prototype Lab', 'technical_lab'],
    ['I', 1022, 'Synthetic Data Investigation Lab', 'technical_lab'],
    ['I', 1023, 'Synthetic Python Data Challenge', 'technology'],
    ['A', 1024, 'Synthetic Visual Design Studio', 'creative_studio'],
    ['A', 1025, 'Synthetic Digital Storytelling Studio', 'creative_studio'],
    ['S', 1026, 'Synthetic Peer Mentoring Circle', 'community'],
    ['S', 1027, 'Synthetic Facilitation Practice', 'community'],
    ['E', 1028, 'Synthetic Student Pitch Lab', 'innovation_lab'],
    ['E', 1029, 'Synthetic Initiative Sprint', 'entrepreneurship'],
    ['C', 1030, 'Synthetic Spreadsheet Accuracy Lab', 'technical_lab'],
    ['C', 1031, 'Synthetic Quality Control Simulation', 'operations'],
];
```

Generate exactly 1116 rows in FK-safe table order with these rules:

1. Add `users` and `student_profiles` only for 103–124; 101–102 are V1 prerequisites.
2. Add the ten new `skills`; reuse V1 `iot` and `python` rows.
3. Add one new `prototyping` skill to 101 and 102; their existing V1 IoT/Python rows remain their other two skills. Learner 103 receives IoT, Prototyping, and Python. Every non-R learner receives IoT plus the two skills aligned to the primary archetype. Learner 104 receives only IoT. Add one verified `learner_skill_evidence` row for every new student-skill row.
4. Add 21 questions, one version row, and 24 immutable question-version mappings.
5. Add one submitted V2 attempt, metadata row, 24 answer rows, and result row for each learner. Convert each score into four 1–5 answers whose sum is `score / 5`; JSON key order is `R,I,A,S,E,C`. Learner 112 uses `STALE_SUBMITTED_AT` in both `test_attempts.submittedAt` and `learner_assessment_attempt_metadata.submittedAt`; all others use `RECENT_SUBMITTED_AT` plus a deterministic learner-second offset.
6. Add 11 new activities and 11 QR token hashes. Add a registration for every learner. Add confirmed check-in and confirmed experience rows for every learner except 108.
7. Add one assessment and one presentation criterion score per learner. Learner 116 has `status=draft`, `overallScore=null`, `publishedAt=null`; all others are published. Learner 101's new presentation score is `55.00`, giving it two low-presentation evaluations when combined with V1.
8. Add four granted consent events per learner except 124, who lacks activity. Add a later evaluation revoke for 120. This produces exactly 96 V2 consent rows.

The exact row-family arithmetic is:

| Table | V2 rows |
|---|---:|
| `users` | 22 |
| `student_profiles` | 22 |
| `skills` | 10 |
| `student_skills` | 66 |
| `learner_skill_evidence` | 66 |
| `test_questions` | 21 |
| `learner_assessment_versions` | 1 |
| `learner_assessment_question_versions` | 24 |
| `test_attempts` | 24 |
| `learner_assessment_attempt_metadata` | 24 |
| `learner_assessment_answers` | 576 |
| `test_results` | 24 |
| `activities` | 11 |
| `activity_qr_tokens` | 11 |
| `activity_registrations` | 24 |
| `checkins` | 23 |
| `experience_logs` | 23 |
| `assessments` | 24 |
| `assessment_scores` | 24 |
| `learner_ai_consent_events` | 96 |
| **Total** | **1116** |

Use fixed per-table numeric ID blocks so no generated row can collide with V1:

```php
private static function id(int $sequence): string
{
    return self::RESERVED_PREFIX . str_pad((string) $sequence, 12, '0', STR_PAD_LEFT);
}

private static function studentId(int $sequence): string
{
    return self::id($sequence);
}

private static function row(string $table, array $values): array
{
    $id = (string) ($values['id'] ?? '');
    if (preg_match('/^00000000-0000-4000-8000-[0-9]{12}$/', $id) !== 1) {
        throw new RuntimeException('V2 row id is outside the reserved synthetic namespace.');
    }
    return ['table' => $table, 'id' => $id, 'values' => $values];
}
```

Use these non-overlapping blocks: identity `103–124`, skills `1001–1010`, activities `1021–1031`, QR `1041–1051`, questions `1101–1121`, version/map `1130–1154`, student-skill `200000+`, skill evidence `300000+`, attempt `400000+`, metadata `401000+`, answer `500000+`, result `600000+`, registration `700000+`, check-in `701000+`, experience `702000+`, assessment `800000+`, assessment score `801000+`, consent event `900000+`, and consent request `910000+`.

New identity rows use `pilot-learner-{sequence}@synthetic.example`, `Synthetic Learner {sequence}`, password placeholder `!synthetic-disabled-login-v2!`, phone `+0000000{sequence}`, class `...000011`, role `...000001`, `studyStatus=active`, and fixed dates of birth `2010-01-{03..24}`. Activity rows use `status=published`, registrations use `status=attended`, QR rows store only `hash('sha256', 'synthetic-ai-v2-activity-' . $activitySequence)`, and confirmed chains use positive hours from `2.50` through `6.50`.

Metadata `inputHash` is `hash('sha256', 'pilot-riasec-2:' . $studentId . ':' . $canonicalAnswerJson)`. Result `dimensionScoresJson` is encoded with keys in `R,I,A,S,E,C` order, `resultCode` is the three highest dimensions in descending score order, and summary is `Synthetic RIASEC V2 result for archetype {primary}.`.

`contentHash()` sorts rows by `(table,id)`, recursively sorts associative value keys while preserving list order, encodes with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION`, and returns the SHA-256 of that canonical JSON. `touchedTables()` returns sorted unique table names; `studentIds()` and `expectedStates()` derive only from `SCENARIOS`.

`validate()` must throw on an invalid count, duplicate table/id, invalid UUID, participant imbalance, question imbalance, non-unique top score, non-multiple-of-five score, `R < 70` or `I < 70`, missing verified IoT on a ready learner, incorrect edge row presence, real-looking email, raw QR value, or unexpected row total.

- [ ] **Step 5: Run the pure contract and lint**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l Database\seeds\learner\Staging\LearnerAiSyntheticDatasetV2.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_synthetic_dataset_v2_contract_test.php
```

Expected: `No syntax errors detected` and `learner_ai_synthetic_dataset_v2_contract_test: OK`.

- [ ] **Step 6: Commit the pure dataset contract**

```powershell
git add -- Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php tests/learner_ai_synthetic_dataset_v2_contract_test.php
git commit -m "feat(learner): declare synthetic AI dataset v2"
```

---

### Task 2: Exact disposable-only Database Change Request

**Files:**
- Create: `docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md`
- Test: `tests/learner_ai_synthetic_dataset_v2_contract_test.php`

**Interfaces:**
- Consumes: `LearnerAiSyntheticDatasetV2::contentHash()` and `touchedTables()` from Task 1.
- Produces: an exact approval artifact pinned to `talenthub_ai_backup_verify_004_20260816`; Task 3 must stop unless its embedded fingerprint matches the dataset.

- [ ] **Step 1: Extend the contract test with a failing DCR fingerprint check**

Add:

```php
$dcrPath = $root . '/docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md';
v2_contract_assert(is_file($dcrPath), 'V2 DCR exists');
$dcr = file_get_contents($dcrPath);
v2_contract_assert(is_string($dcr), 'V2 DCR is readable');
v2_contract_assert(str_contains($dcr, '`talenthub_ai_backup_verify_004_20260816`'), 'DCR pins the approved disposable schema');
v2_contract_assert(str_contains($dcr, '`' . LearnerAiSyntheticDatasetV2::contentHash() . '`'), 'DCR records the exact dataset fingerprint');
v2_contract_assert(str_contains($dcr, '1116'), 'DCR records the exact V2 row count');
v2_contract_assert(!str_contains($dcr, 'talenthub_local` is approved'), 'DCR never approves the shared schema');
```

- [ ] **Step 2: Run the pure test and verify the RED state**

Run the contract test. Expected: FAIL at `V2 DCR exists`; no database connection.

- [ ] **Step 3: Write the exact DCR**

The DCR must contain:

- status `PROPOSED — DISPOSABLE SCHEMA ONLY` until the user approves the exact artifact;
- exact target `talenthub_ai_backup_verify_004_20260816` and explicit rejection of `talenthub_local`;
- current canonical migration versions/checksums resolved with `LearnerMigrationChecksum::canonical()`;
- V1 prerequisite IDs and a statement that V1 remains unchanged;
- exact participant matrix, 12-skill catalog, 12-activity catalog, 24-question bank, row-family counts totaling 1116, timestamp constants, and edge states;
- output of `LearnerAiSyntheticDatasetV2::contentHash()` in backticks;
- the exact allowed SQL form `INSERT ... SELECT ... WHERE NOT EXISTS`;
- forbidden SQL tokens and the no-cleanup/no-rollback-data policy;
- before/after non-reserved count verification;
- rollback response: stop, retain evidence, and do not delete or mutate rows; a correction requires a forward V3 dataset with new IDs;
- user approval line and execution evidence section, initially marked `NOT EXECUTED`.

Compute the fingerprint without database access:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -r "require 'Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php'; echo TalentHub\Learner\Seeds\Staging\LearnerAiSyntheticDatasetV2::contentHash(), PHP_EOL;"
```

- [ ] **Step 4: Verify and commit the proposed DCR**

Run the contract test and `git diff --check`. Expected: contract test OK and no whitespace errors.

```powershell
git add -- docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md tests/learner_ai_synthetic_dataset_v2_contract_test.php
git commit -m "docs(learner): request synthetic AI dataset v2"
```

- [ ] **Step 5: Obtain the explicit execution approval gate**

Present the committed DCR path, fingerprint, target schema, 1116-row count, and no-delete/no-update boundary. Do not implement or run the MySQL seeder until the user approves this exact DCR. After approval, change only the DCR status/approval line to record the approval date and commit:

```powershell
git add -- docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md
git commit -m "docs(learner): approve synthetic AI dataset v2"
```

---

### Task 3: Insert-only MySQL seeder

**Files:**
- Create: `tests/learner_ai_synthetic_dataset_v2_mysql_test.php`
- Create: `Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2Seeder.php`

**Interfaces:**
- Consumes: `LearnerAiSyntheticDatasetV2::rows()`, `studentIds()`, `expectedStates()`, `touchedTables()`, and `contentHash()`.
- Produces: constructor `__construct(PDO $pdo, string $expectedSchema, string $approvedContentHash)` and `seed(): array{declared:int,inserted:int,existing:int,students:int,complete:int,edge:int}`.

- [ ] **Step 1: Write the integration test bootstrap and safe RED gate**

The test must require `APP_ENV=test`, require a `LEARNER_MYSQL_TEST_SCHEMA` matching `^talenthub_ai_backup_verify_[A-Za-z0-9_]+$`, additionally require the exact value `talenthub_ai_backup_verify_004_20260816`, load DB credentials only through `TALENTHUB_DB_CONFIG_ROOT`, override only `$config['database']`, and assert `SELECT DATABASE()` equals the target before constructing a seeder.

Before connecting, require these files in this order:

```php
$root = dirname(__DIR__);
require_once $root . '/app/learner/data/bootstrap.php';
require_once $root . '/Database/seeds/learner/Staging/LearnerAiPilotSeeder.php';
require_once $root . '/Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php';
$seederFile = $root . '/Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2Seeder.php';
if (!is_file($seederFile)) {
    throw new RuntimeException('Assertion failed: V2 seeder exists');
}
require_once $seederFile;
require_once $root . '/app/learner/ai/bootstrap.php';
```

Run without the environment variables. Expected: FAIL at the explicit disposable-schema/environment guard and no connection.

- [ ] **Step 2: Implement connection, schema, migration, DCR, and V1 preflight**

The seeder must perform these checks in order before reading V2 target IDs:

```php
private const SCHEMA_PATTERN = '/^talenthub_ai_backup_verify_[A-Za-z0-9_]+$/';
private const APPROVED_SCHEMA = 'talenthub_ai_backup_verify_004_20260816';

private function assertTarget(): void
{
    if ($this->expectedSchema !== self::APPROVED_SCHEMA
        || preg_match(self::SCHEMA_PATTERN, $this->expectedSchema) !== 1
        || $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        throw new RuntimeException('V2 seed requires the approved disposable MySQL schema.');
    }
    $actual = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($actual) || !hash_equals($this->expectedSchema, $actual)) {
        throw new RuntimeException('V2 seed connection is not pinned to the approved disposable schema.');
    }
    $timeZone = $this->pdo->query('SELECT @@session.time_zone')->fetchColumn();
    if ($timeZone !== '+00:00') {
        throw new RuntimeException('V2 seed requires MySQL session time zone +00:00.');
    }
}
```

Then verify:

- `approvedContentHash` equals both `LearnerAiSyntheticDatasetV2::contentHash()` and the approved DCR fingerprint;
- registry rows for 002, 003, and 004 match canonical source checksums via `LearnerMigrationChecksum::canonical()`;
- every touched table exists and exposes exactly the columns used by declared rows, using `information_schema.columns` constrained by `table_schema = :schema`;
- V1 parent rows used by V2 match the declared columns: learner/teacher roles, school, class, teacher user/profile, learner users/profiles 101/102, Holland test, R1/I1/A1, IoT/Python, V1 activity/QR, and presentation criterion;
- no declared V2 table/id has a conflicting stored value.

Identifier interpolation is permitted only after `/^[A-Za-z_][A-Za-z0-9_]*$/` validation. All values remain bound parameters.

- [ ] **Step 3: Implement full preflight comparison and insert-only transaction**

Use the same normalized comparison semantics as V1: `null` remains `null`; booleans become `0/1`; numeric strings compare through canonical decimal text; JSON fields are decoded and recursively key-sorted before comparison; timestamp strings retain microseconds.

Perform every conflict check before `beginTransaction()`. Then insert missing rows in dataset order:

```php
$startsTransaction = !$this->pdo->inTransaction();
if (!$startsTransaction) {
    throw new RuntimeException('V2 seed refuses an externally owned transaction.');
}
$this->pdo->beginTransaction();
try {
    $inserted = 0;
    foreach ($missing as $row) {
        if ($this->insertIfMissing($row)) {
            $inserted++;
            continue;
        }
        $actual = $this->findById($row['table'], $row['id']);
        $this->assertSameRow($row, $actual);
        $existing++;
    }
    $this->pdo->commit();
} catch (Throwable $exception) {
    if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
    }
    throw $exception;
}

return [
    'declared' => count($rows),
    'inserted' => $inserted,
    'existing' => $existing,
    'students' => 24,
    'complete' => 18,
    'edge' => 6,
];
```

The only write statement is built in this form:

```sql
INSERT INTO validated_table (validated_columns)
SELECT :bound_value_0, :bound_value_1
WHERE NOT EXISTS (
    SELECT 1 FROM validated_table WHERE id = :present_id
)
```

- [ ] **Step 4: Add integration assertions for idempotency and non-reserved isolation**

Before the first seed, capture `COUNT(*) WHERE id NOT LIKE :reserved_prefix` for every touched table. Call V1 first and require `inserted=0`; then call V2 twice:

```php
$first = $seeder->seed();
v2_mysql_assert($first['declared'] === 1116, 'V2 declares the approved row count');
v2_mysql_assert($first['inserted'] + $first['existing'] === 1116, 'first call inserts or verifies every V2 row');
v2_mysql_assert(in_array($first['existing'], [0, 1116], true), 'transactional V2 state is either absent or complete');
v2_mysql_assert($first['students'] === 24 && $first['complete'] === 18 && $first['edge'] === 6, 'participant totals are exact');

$second = $seeder->seed();
v2_mysql_assert($second === [
    'declared' => 1116,
    'inserted' => 0,
    'existing' => 1116,
    'students' => 24,
    'complete' => 18,
    'edge' => 6,
], 'second V2 seed is an idempotent no-op');
```

The approved first execution must report `inserted=1116, existing=0`; later regression executions report `inserted=0, existing=1116`. A partial identical state is rejected because the first transaction is atomic. Always require the second call to be exactly `inserted=0, existing=1116`. In both cases, non-reserved counts must equal their baseline.

Also instantiate a seeder with expected schema `talenthub_local` against the disposable PDO and assert it throws before any write.

- [ ] **Step 5: Run lint and source-policy checks before database execution**

Run:

```powershell
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l Database\seeds\learner\Staging\LearnerAiSyntheticDatasetV2Seeder.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' -l tests\learner_ai_synthetic_dataset_v2_mysql_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_synthetic_dataset_v2_contract_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_scope_policy_test.php
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_scope_audit_test.php
git diff --check
```

Expected: both lint commands and all four tests pass; diff check is silent. Do not run the MySQL test until Task 2 approval is recorded.

- [ ] **Step 6: Commit the seeder before its controlled execution**

```powershell
git add -- Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2Seeder.php tests/learner_ai_synthetic_dataset_v2_mysql_test.php
git commit -m "feat(learner): add insert-only synthetic AI seeder v2"
```

---

### Task 4: Real pipeline verification against the 24 learners

**Files:**
- Modify: `tests/learner_ai_synthetic_dataset_v2_mysql_test.php`

**Interfaces:**
- Consumes: real disposable PDO, `ConsentPolicy`, database source adapters, `RecommendationSnapshotBuilder`, `DataQualityGate`, `RuleRecommendationEngine`, and Task 1 scenario metadata.
- Produces: verified state totals `ready=18`, `insufficient_data=4`, `consent_required=2`, deterministic evidence-backed rule items for all ready learners, and no persisted recommendation output.

- [ ] **Step 1: Add the real pipeline factory**

```php
function v2_snapshot_builder(PDO $pdo): RecommendationSnapshotBuilder
{
    return new RecommendationSnapshotBuilder(
        new DatabaseStudentProfileSource($pdo),
        new DatabaseSkillSource($pdo),
        new DatabaseAssessmentSource($pdo),
        new DatabaseActivityExperienceSource($pdo),
        new DatabasePublishedEvaluationSource($pdo),
        new DatabaseOpportunitySource($pdo),
    );
}

$consent = new ConsentPolicy(new DatabaseConsentSource($pdo));
$builder = v2_snapshot_builder($pdo);
$quality = new DataQualityGate(new DateTimeImmutable('2026-08-17T00:00:00.000000+00:00', new DateTimeZone('UTC')));
$engine = new RuleRecommendationEngine();
```

- [ ] **Step 2: Assert every declared quality state and reason**

For each participant, resolve allowed scopes, build the snapshot, and evaluate quality. Assert:

- 104: state `insufficient_data`, missing exactly `['skills']`;
- 108: state `insufficient_data`, missing exactly `['experience']`;
- 112: state `insufficient_data`, missing exactly `['assessment']`;
- 116: state `insufficient_data`, missing exactly `['evaluations']`;
- 120: state `consent_required`, missing consent exactly `['evaluation']`;
- 124: state `consent_required`, missing consent exactly `['activity']`;
- all other learners: state `ready` and no missing categories/scopes.

Accumulate totals and require exactly `[ready => 18, insufficient_data => 4, consent_required => 2]`.

- [ ] **Step 3: Assert deterministic rule items and provenance for ready learners**

For each ready learner, create a `RecommendationContext` with deterministic request/idempotency strings, generate twice from the same input, and compare a canonical signature containing engine type, fallback reason, item type/title/priority, action JSON, and ordered evidence `(sourceType, sourceId)`.

Require:

```php
v2_mysql_assert($result->items() !== [], 'ready learner has at least one rule item');
v2_mysql_assert($result->fallbackReason() === null, 'ready learner has no fallback reason');
foreach ($result->items() as $item) {
    v2_mysql_assert($item->evidence() !== [], 'every rule item has evidence');
    foreach ($item->evidence() as $evidence) {
        v2_mysql_assert(isset($snapshotEvidenceIds[$evidence->sourceType() . "\0" . $evidence->sourceId()]), 'item evidence belongs to the learner snapshot');
    }
}
```

At least one learner must produce each baseline item type currently supported by the fixture: `strength`, `activity`, and `roadmap`. Learner 101 must produce the roadmap because V1 plus V2 provide two published presentation scores below 60.

- [ ] **Step 4: Assert source ownership and absence of opportunity data**

Build ownership maps with student-scoped joins:

- skill: `student_skills.id -> studentId`;
- assessment: `test_results.id -> test_attempts.studentId`;
- activity: `experience_logs.id -> studentId`;
- evaluation: `assessments.id -> studentId`.

Every snapshot evidence reference must map to the current learner. Assert `source_counts.opportunities === 0` for all learners and confirm `SHOW TABLES LIKE 'internship_posts'` returns no row. This is read-only evidence that no shared opportunity table was created.

- [ ] **Step 5: Run the approved disposable MySQL integration test**

Load the existing local environment without printing secrets, set only the test controls, and execute:

```powershell
$env:APP_ENV = 'test'
$env:LEARNER_MYSQL_TEST_SCHEMA = 'talenthub_ai_backup_verify_004_20260816'
$env:TALENTHUB_DB_CONFIG_ROOT = 'D:\TalentHub'
& 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' tests\learner_ai_synthetic_dataset_v2_mysql_test.php
```

If the repository's database config root is not `D:\TalentHub`, resolve the already-used local config root from the existing V1/E2E execution environment without echoing credentials and set `TALENTHUB_DB_CONFIG_ROOT` to that exact directory.

Expected: `learner_ai_synthetic_dataset_v2_mysql_test: OK`. On any error, stop. Do not retry after changing rows, do not clean the database, and record the failing preflight/constraint in the execution report.

- [ ] **Step 6: Commit the completed pipeline assertions**

```powershell
git add -- tests/learner_ai_synthetic_dataset_v2_mysql_test.php
git commit -m "test(learner): verify synthetic AI dataset v2"
```

---

### Task 5: Regression, scope audit, and execution report

**Files:**
- Modify: `docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md`
- Create: `.superpowers/sdd/learner-ai-synthetic-dataset-v2-execution-report.md` (ignored, do not stage)

**Interfaces:**
- Consumes: Task 4 test output and Git diff.
- Produces: auditable before/after results and a clean learner-only commit range.

- [ ] **Step 1: Run focused regressions**

```powershell
$php = 'D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
& $php tests\learner_ai_pilot_seed_test.php
& $php tests\learner_ai_sources_test.php
& $php tests\learner_ai_snapshot_test.php
& $php tests\learner_rule_recommendation_test.php
& $php tests\learner_recommendation_repository_test.php
& $php tests\learner_recommendation_service_test.php
& $php tests\learner_ai_end_to_end_mysql_test.php
& $php tests\learner_ai_synthetic_dataset_v2_contract_test.php
& $php tests\learner_ai_synthetic_dataset_v2_mysql_test.php
& $php tests\learner_ai_scope_policy_test.php
& $php tests\learner_ai_scope_audit_test.php
```

Expected: each command ends in `OK`. Both MySQL tests remain pinned to the disposable schema.

- [ ] **Step 2: Audit repository scope and forbidden operations**

```powershell
git diff --name-only e728da8..HEAD
git diff --check e728da8..HEAD
Select-String -Path 'Database\seeds\learner\Staging\LearnerAiSyntheticDatasetV2*.php' -Pattern '\b(UPDATE|DELETE|REPLACE|DROP|TRUNCATE|ALTER)\b' -CaseSensitive:$false
git status --short
```

Expected changed production paths are limited to the two V2 files under `Database/seeds/learner/Staging`; test and docs paths are learner-owned. `Select-String` returns no executable forbidden SQL. Diff check is silent. The ignored report is absent from `git status`.

- [ ] **Step 3: Record execution evidence without secrets**

Write the report with:

- branch and commit hashes;
- PHP and MySQL binary versions;
- target schema `talenthub_ai_backup_verify_004_20260816`;
- canonical migration status/checksums;
- dataset fingerprint;
- baseline and final non-reserved counts by touched table;
- first and second seed result arrays;
- state totals `18/4/2`;
- ready item counts and evidence counts;
- `internship_posts` absence;
- every verification command and exit result;
- explicit statements that no database was created/deleted, no row was updated/deleted, `talenthub_local` was not written, and no shared-role code changed.

Do not include host usernames, passwords, DSNs containing credentials, API keys, or raw QR values.

- [ ] **Step 4: Update and commit DCR execution evidence**

Change the DCR execution section from `NOT EXECUTED` to the verified timestamp, seed results, state totals, and test names. Do not change the approved fingerprint or row contract.

```powershell
git add -- docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md
git commit -m "docs(learner): record synthetic AI dataset v2 execution"
```

- [ ] **Step 5: Final verification before completion claim**

```powershell
git status --short
git log -6 --oneline
git diff --check e728da8..HEAD
```

Expected: clean tracked worktree, the expected task commits are visible, and diff check is silent. Report completion with the exact schema, row/state counts, dataset fingerprint, commits, and any remaining AI rollout gates. Do not claim that model visibility or real-provider rollout is approved; this dataset enables development/evaluation, while the existing shadow-quality, consent-revoke, security/bias, latency/cost, and product approval gates still apply.
