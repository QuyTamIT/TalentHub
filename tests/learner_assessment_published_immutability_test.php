<?php

declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/../app/learner/data/bootstrap.php';
require_once __DIR__ . '/../Database/seeds/learner/Assessment/AbstractCatalogSeeder.php';

use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Data\Database\DatabaseAssessmentRepository;
use TalentHub\Learner\Data\Database\DatabaseAssessmentWriteRepository;
use TalentHub\Learner\Seeds\Assessment\AbstractCatalogSeeder;

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expectException = static function (callable $operation, string $messagePart) use (&$assertions): void {
    $assertions++;
    try {
        $operation();
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), $messagePart)) {
            return;
        }
        throw new RuntimeException("Unexpected exception: {$e->getMessage()}", 0, $e);
    }
    throw new RuntimeException("Expected exception containing '{$messagePart}'.");
};

$createCatalogSchema = static function (): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE test_questions (id TEXT PRIMARY KEY, testId TEXT NOT NULL, code TEXT NOT NULL, content TEXT NOT NULL, optionsJson TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, UNIQUE (testId, code))');
    $pdo->exec('CREATE TABLE learner_assessment_versions (id TEXT PRIMARY KEY, testId TEXT NOT NULL, version TEXT NOT NULL, scoringVersion TEXT NOT NULL, schemaHash TEXT NOT NULL, status TEXT NOT NULL, publishedAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE (testId, version))');
    $pdo->exec('CREATE TABLE learner_assessment_question_versions (id TEXT PRIMARY KEY, versionId TEXT NOT NULL, questionId TEXT NOT NULL, position INTEGER NOT NULL, dimensionCode TEXT NOT NULL, required INTEGER NOT NULL, createdAt TEXT NOT NULL, UNIQUE (versionId, questionId), UNIQUE (versionId, position))');
    return $pdo;
};

$approvedCatalog = require __DIR__ . '/../Database/seeds/learner/Assessment/HollandCatalogMiddle.php';
$approvedCatalog['metadata']['review_state'] = 'published';
$approvedCatalog['metadata']['review_events'] = array_map(
    static fn (string $checkpoint): array => [
        'checkpoint' => $checkpoint,
        'reviewer' => 'ImmutabilityContractTest',
        'approved_at_utc' => '2026-08-20T00:00:00Z',
    ],
    [
        'content_review',
        'educational_review',
        'bias_review',
        'scoring_review',
        'product_owner_approval',
        'codex_schema_review',
    ],
);

$pdo = $createCatalogSchema();
$seeder = new AbstractCatalogSeeder($pdo);
$firstRun = $seeder->seedCatalog($approvedCatalog, 'holland_middle', 'Holland Middle', 'holland');
$assert($firstRun['status'] === 'INSERTED', 'First catalog seed must insert the published version.');

$snapshotSql = <<<'SQL'
SELECT tq.id, tq.code, tq.content, tq.optionsJson, tq.status AS question_status,
       v.version, v.scoringVersion, v.schemaHash, v.status AS version_status, v.publishedAt,
       qv.position, qv.dimensionCode, qv.required
FROM learner_assessment_versions v
INNER JOIN learner_assessment_question_versions qv ON qv.versionId = v.id
INNER JOIN test_questions tq ON tq.id = qv.questionId
ORDER BY qv.position
SQL;
$before = $pdo->query($snapshotSql)->fetchAll(PDO::FETCH_ASSOC);
$secondRun = $seeder->seedCatalog($approvedCatalog, 'holland_middle', 'Holland Middle', 'holland');
$after = $pdo->query($snapshotSql)->fetchAll(PDO::FETCH_ASSOC);

$assert($secondRun['status'] === 'NO_OP', 'Exact rerun must be an idempotent NO_OP.');
$assert($secondRun['inserted'] === 0, 'Idempotent rerun must insert zero rows.');
$assert($before === $after, 'Idempotent rerun must not change question/version/binding publication data.');
$assert(count($after) === 30, 'Published Holland version must retain all 30 immutable bindings.');

$selectionPdo = new PDO('sqlite::memory:');
$selectionPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$selectionPdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)');
$selectionPdo->exec('CREATE TABLE learner_assessment_versions (id TEXT PRIMARY KEY, testId TEXT NOT NULL, version TEXT NOT NULL, scoringVersion TEXT NOT NULL, schemaHash TEXT NOT NULL, status TEXT NOT NULL, publishedAt TEXT NULL, createdAt TEXT NOT NULL)');
$selectionPdo->exec('CREATE TABLE learner_assessment_question_versions (id TEXT PRIMARY KEY, versionId TEXT NOT NULL, questionId TEXT NOT NULL, position INTEGER NOT NULL, dimensionCode TEXT NOT NULL, required INTEGER NOT NULL, createdAt TEXT NOT NULL)');
$selectionPdo->exec('CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, startedAt TEXT NOT NULL, submittedAt TEXT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)');
$selectionPdo->exec('CREATE TABLE learner_assessment_attempt_metadata (id TEXT PRIMARY KEY, attemptId TEXT NOT NULL, versionId TEXT NOT NULL, status TEXT NOT NULL, expiresAt TEXT NULL, submittedAt TEXT NULL, inputHash TEXT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)');
$selectionPdo->exec("INSERT INTO talent_tests VALUES ('aaaaaaaa-aaaa-4aaa-8aaa-000000000001', 'holland_middle', 'Holland Middle', 'holland', 'published', '2026-08-20', '2026-08-20')");
$selectionPdo->exec("INSERT INTO learner_assessment_versions VALUES ('bbbbbbbb-bbbb-4bbb-8bbb-000000000001', 'aaaaaaaa-aaaa-4aaa-8aaa-000000000001', '1.0.0', 'holland-riasec-1.0', 'hash', 'archived', '2026-08-20', '2026-08-20')");

$readRepository = new DatabaseAssessmentRepository($selectionPdo);
$assert($readRepository->publishedAssessment('holland', 'middle') === null, 'Archived version must not be returned as a published assessment.');

$writeRepository = new DatabaseAssessmentWriteRepository($selectionPdo, new ScorerRegistry([]));
$expectException(
    static fn () => $writeRepository->startOrResumeAttempt(
        '11111111-1111-4111-8111-111111111111',
        'holland',
        'middle',
    ),
    'unavailable',
);

$seederSource = file_get_contents(__DIR__ . '/../Database/seeds/learner/Assessment/AbstractCatalogSeeder.php');
$masterSource = file_get_contents(__DIR__ . '/../Database/seeds/learner/AssessmentCatalogMasterSeeder.php');
$assert(is_string($seederSource) && is_string($masterSource), 'Seeder source files must be readable.');
$combinedSeederSource = $seederSource . "\n" . $masterSource;
foreach (['test_questions', 'learner_assessment_versions', 'learner_assessment_question_versions'] as $table) {
    $assert(
        preg_match('/\b(?:UPDATE|DELETE\s+FROM|TRUNCATE\s+TABLE|REPLACE\s+INTO)\s+' . preg_quote($table, '/') . '\b/i', $combinedSeederSource) !== 1,
        "Insert-only seed must never mutate published {$table} rows.",
    );
}

$migrationSource = file_get_contents(__DIR__ . '/../Database/migrations/20260818000100_create_learner_assessment_schema.php');
$assert(is_string($migrationSource), 'Assessment migration source must be readable.');
$assert(preg_match('/\bCREATE\s+TRIGGER\b/i', $migrationSource) !== 1, 'Migration intentionally has no database immutability trigger.');

echo "IMMUTABILITY_LEVEL=application_contract (MySQL does not reject UPDATE through a trigger)\n";
echo "learner_assessment_published_immutability_test: {$assertions} assertions passed\n";
