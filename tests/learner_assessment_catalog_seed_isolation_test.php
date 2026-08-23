<?php

declare(strict_types=1);

/**
 * Phase 6 baseline remediation companion test (plan Amendment A3, 2026-08-22).
 *
 * The primary-database catalog test cannot assert transactional emptiness because
 * talenthub_local is a legitimate demo database holding real submitted attempts.
 * This test therefore owns the isolated contract instead:
 *
 *   "Seeding the assessment catalog inserts catalog rows only. It never creates
 *    a test attempt, attempt metadata, an answer, or a result."
 *
 * It runs the real AbstractCatalogSeeder against a disposable, freshly created
 * schema. It never opens a connection to any shared or primary database.
 */

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/Database/seeds/learner/Assessment/AbstractCatalogSeeder.php';

use TalentHub\Learner\Seeds\Assessment\AbstractCatalogSeeder;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$databasePath = tempnam(sys_get_temp_dir(), 'talenthub-catalog-isolation-');
$assert(is_string($databasePath) && $databasePath !== '', 'Disposable SQLite path is available.');

/** Tables that the seeder is allowed to write. */
const CATALOG_TABLES = [
    'talent_tests',
    'test_questions',
    'learner_assessment_versions',
    'learner_assessment_question_versions',
];

/** Tables that must remain empty no matter how many catalogs are seeded. */
const TRANSACTIONAL_TABLES = [
    'test_attempts',
    'learner_assessment_attempt_metadata',
    'learner_assessment_answers',
    'test_results',
];

try {
    $pdo = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec(<<<'SQL'
CREATE TABLE talent_tests (
    id TEXT PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, type TEXT NOT NULL,
    status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL
);
CREATE TABLE test_questions (
    id TEXT PRIMARY KEY, testId TEXT NOT NULL, code TEXT NOT NULL UNIQUE, content TEXT NOT NULL,
    optionsJson TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL
);
CREATE TABLE learner_assessment_versions (
    id TEXT PRIMARY KEY, testId TEXT NOT NULL, version TEXT NOT NULL, scoringVersion TEXT NOT NULL,
    schemaHash TEXT NOT NULL, status TEXT NOT NULL, publishedAt TEXT, createdAt TEXT NOT NULL
);
CREATE TABLE learner_assessment_question_versions (
    id TEXT PRIMARY KEY, versionId TEXT NOT NULL, questionId TEXT NOT NULL, position INTEGER NOT NULL,
    dimensionCode TEXT NOT NULL, required INTEGER NOT NULL, createdAt TEXT NOT NULL,
    UNIQUE (versionId, questionId)
);
CREATE TABLE test_attempts (
    id TEXT PRIMARY KEY, testId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL,
    startedAt TEXT, submittedAt TEXT
);
CREATE TABLE learner_assessment_attempt_metadata (
    attemptId TEXT PRIMARY KEY, versionId TEXT NOT NULL, status TEXT NOT NULL,
    expiresAt TEXT, submittedAt TEXT, inputHash TEXT
);
CREATE TABLE learner_assessment_answers (
    id TEXT PRIMARY KEY, attemptId TEXT NOT NULL, questionId TEXT NOT NULL, answerJson TEXT NOT NULL
);
CREATE TABLE test_results (
    id TEXT PRIMARY KEY, attemptId TEXT NOT NULL, resultCode TEXT, summary TEXT,
    dimensionScoresJson TEXT, scoringVersion TEXT, createdAt TEXT NOT NULL
);
SQL
    );

    $countRows = static function (PDO $pdo, string $table): int {
        return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    };

    foreach ([...CATALOG_TABLES, ...TRANSACTIONAL_TABLES] as $table) {
        $assert($countRows($pdo, $table) === 0, "Fresh schema starts with an empty {$table}.");
    }

    $seeder = new AbstractCatalogSeeder($pdo);

    // Two frameworks and two education bands prove the contract is not catalog-specific.
    $catalogs = [
        ['file' => 'HollandCatalogMiddle.php', 'code' => 'holland_middle', 'name' => 'Holland Middle', 'type' => 'holland', 'count' => 30],
        ['file' => 'MbtiCatalogHigh.php', 'code' => 'mbti_high', 'name' => 'MBTI High', 'type' => 'mbti', 'count' => 32],
    ];

    $expectedQuestions = 0;
    foreach ($catalogs as $spec) {
        $catalog = require dirname(__DIR__) . '/Database/seeds/learner/Assessment/' . $spec['file'];
        $outcome = $seeder->seedCatalog($catalog, $spec['code'], $spec['name'], $spec['type']);

        $assert($outcome['status'] === 'INSERTED', "{$spec['code']} seeds as INSERTED on a fresh schema.");
        $assert($outcome['inserted'] === $spec['count'], "{$spec['code']} inserts exactly {$spec['count']} questions.");
        $expectedQuestions += $spec['count'];

        foreach (TRANSACTIONAL_TABLES as $table) {
            $rows = $countRows($pdo, $table);
            $assert($rows === 0, "Seeding {$spec['code']} must not create rows in {$table}, got {$rows}.");
        }
    }

    $assert($countRows($pdo, 'talent_tests') === count($catalogs), 'Exactly one talent test per seeded catalog.');
    $assert($countRows($pdo, 'test_questions') === $expectedQuestions, 'Question count matches the seeded catalogs.');
    $assert($countRows($pdo, 'learner_assessment_versions') === count($catalogs), 'Exactly one version per seeded catalog.');
    $assert(
        $countRows($pdo, 'learner_assessment_question_versions') === $expectedQuestions,
        'Exactly one binding per seeded question.'
    );

    $publishedVersions = (int) $pdo->query(
        "SELECT COUNT(*) FROM learner_assessment_versions WHERE status = 'published' AND publishedAt IS NOT NULL"
    )->fetchColumn();
    $assert($publishedVersions === count($catalogs), 'Every seeded version is published with a publishedAt.');

    // Re-seeding is a verified NO-OP and still cannot manufacture transactional rows.
    foreach ($catalogs as $spec) {
        $catalog = require dirname(__DIR__) . '/Database/seeds/learner/Assessment/' . $spec['file'];
        $replay = $seeder->seedCatalog($catalog, $spec['code'], $spec['name'], $spec['type']);

        $assert($replay['status'] === 'NO_OP', "Re-seeding {$spec['code']} is a NO_OP.");
        $assert($replay['inserted'] === 0, "Re-seeding {$spec['code']} inserts nothing.");
    }

    foreach (TRANSACTIONAL_TABLES as $table) {
        $assert($countRows($pdo, $table) === 0, "Replay must not create rows in {$table}.");
    }
    $assert($countRows($pdo, 'test_questions') === $expectedQuestions, 'Replay does not duplicate questions.');
    $assert(
        $countRows($pdo, 'learner_assessment_question_versions') === $expectedQuestions,
        'Replay does not duplicate bindings.'
    );
} finally {
    unset($pdo, $seeder);
    gc_collect_cycles();
    if (is_string($databasePath) && is_file($databasePath)) {
        unlink($databasePath);
    }
}

echo "learner_assessment_catalog_seed_isolation_test: {$assertions} assertions passed\n";
