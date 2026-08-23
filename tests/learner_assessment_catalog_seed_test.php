<?php

declare(strict_types=1);

/**
 * Verification test suite for assessment catalog seed in the primary database (talenthub_local).
 *
 * Contract:
 * - Read-only assertions against live database. Never mutates, deletes, or truncates demo data.
 * - Verifies row counts: 12 talent_tests, 366 test_questions, 12 published versions, 366 question bindings.
 * - Verifies referential integrity of whatever attempt/metadata/answer/result rows already exist.
 *   Transactional emptiness is NOT asserted here: talenthub_local is a legitimate demo database.
 *   The "catalog seed creates no attempts/results/answers" contract lives in
 *   tests/learner_assessment_catalog_seed_isolation_test.php on a disposable schema.
 *   See plan Amendment A3 (2026-08-22).
 * - Verifies UUID and stable code global uniqueness.
 * - Verifies schemaHash matches declared canonical source hashes for all 12 catalogs.
 * - Verifies per-question bindings and content fidelity.
 */

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/Database/seeds/learner/Assessment/AbstractCatalogSeeder.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Learner\Seeds\Assessment\AbstractCatalogSeeder;
use TalentHub\Support\Uuid;

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

$config = require dirname(__DIR__) . '/config/database.php';
$connection = new Connection($config);
$pdo = $connection->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// 1. Verify database context
$currentDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$assert($currentDb !== '', 'Database name must not be empty.');
echo "Verifying assessment catalog seed on database: {$currentDb}\n";

// 2. Table row counts
$counts = [
    'talent_tests' => (int) $pdo->query('SELECT COUNT(*) FROM talent_tests')->fetchColumn(),
    'test_questions' => (int) $pdo->query('SELECT COUNT(*) FROM test_questions')->fetchColumn(),
    'learner_assessment_versions' => (int) $pdo->query('SELECT COUNT(*) FROM learner_assessment_versions')->fetchColumn(),
    'learner_assessment_question_versions' => (int) $pdo->query('SELECT COUNT(*) FROM learner_assessment_question_versions')->fetchColumn(),
    'test_attempts' => (int) $pdo->query('SELECT COUNT(*) FROM test_attempts')->fetchColumn(),
    'learner_assessment_attempt_metadata' => (int) $pdo->query('SELECT COUNT(*) FROM learner_assessment_attempt_metadata')->fetchColumn(),
    'learner_assessment_answers' => (int) $pdo->query('SELECT COUNT(*) FROM learner_assessment_answers')->fetchColumn(),
    'test_results' => (int) $pdo->query('SELECT COUNT(*) FROM test_results')->fetchColumn(),
];

$assert($counts['talent_tests'] === 12, "talent_tests count must be 12, got {$counts['talent_tests']}");
$assert($counts['test_questions'] === 366, "test_questions count must be 366, got {$counts['test_questions']}");
$assert($counts['learner_assessment_versions'] === 12, "learner_assessment_versions count must be 12, got {$counts['learner_assessment_versions']}");
$assert($counts['learner_assessment_question_versions'] === 366, "learner_assessment_question_versions count must be 366, got {$counts['learner_assessment_question_versions']}");

// 2b. Referential integrity of pre-existing transactional rows.
// Demo attempts are legitimate data. Assert they are consistent, never that they are absent.
$integrity = [
    'attempts_without_test' => 'SELECT COUNT(*) FROM test_attempts a LEFT JOIN talent_tests t ON t.id = a.testId WHERE t.id IS NULL',
    'attempts_without_student' => 'SELECT COUNT(*) FROM test_attempts a LEFT JOIN student_profiles s ON s.id = a.studentId WHERE s.id IS NULL',
    'metadata_without_attempt' => 'SELECT COUNT(*) FROM learner_assessment_attempt_metadata m LEFT JOIN test_attempts a ON a.id = m.attemptId WHERE a.id IS NULL',
    'metadata_without_version' => 'SELECT COUNT(*) FROM learner_assessment_attempt_metadata m LEFT JOIN learner_assessment_versions v ON v.id = m.versionId WHERE v.id IS NULL',
    'answers_without_attempt' => 'SELECT COUNT(*) FROM learner_assessment_answers ans LEFT JOIN test_attempts a ON a.id = ans.attemptId WHERE a.id IS NULL',
    'answers_without_question' => 'SELECT COUNT(*) FROM learner_assessment_answers ans LEFT JOIN test_questions q ON q.id = ans.questionId WHERE q.id IS NULL',
    'results_without_attempt' => 'SELECT COUNT(*) FROM test_results r LEFT JOIN test_attempts a ON a.id = r.attemptId WHERE a.id IS NULL',
    'submitted_without_metadata' => 'SELECT COUNT(*) FROM test_attempts a LEFT JOIN learner_assessment_attempt_metadata m ON m.attemptId = a.id WHERE a.status = \'submitted\' AND m.attemptId IS NULL',
    'submitted_without_result' => 'SELECT COUNT(*) FROM test_attempts a LEFT JOIN test_results r ON r.attemptId = a.id WHERE a.status = \'submitted\' AND r.attemptId IS NULL',
    'submitted_without_timestamp' => 'SELECT COUNT(*) FROM test_attempts WHERE status = \'submitted\' AND submittedAt IS NULL',
];

foreach ($integrity as $label => $sql) {
    $orphans = (int) $pdo->query($sql)->fetchColumn();
    $assert($orphans === 0, "Referential integrity: {$label} must be 0, got {$orphans}");
}

$assert(
    $counts['test_results'] <= $counts['test_attempts'],
    "test_results ({$counts['test_results']}) cannot exceed test_attempts ({$counts['test_attempts']})"
);
$assert(
    $counts['learner_assessment_attempt_metadata'] <= $counts['test_attempts'],
    'Attempt metadata cannot exceed attempts'
);
$duplicateResults = (int) $pdo->query(
    'SELECT COUNT(*) FROM (SELECT attemptId FROM test_results GROUP BY attemptId HAVING COUNT(*) > 1) dupes'
)->fetchColumn();
$assert($duplicateResults === 0, "No attempt may carry more than one result, got {$duplicateResults}");

echo "Pre-existing transactional rows verified consistent: "
    . "{$counts['test_attempts']} attempts, {$counts['test_results']} results, "
    . "{$counts['learner_assessment_answers']} answers\n";

// 3. Expected catalog definitions
$expectedCatalogs = [
    'holland_middle' => ['type' => 'holland', 'count' => 30, 'file' => 'Database/seeds/learner/Assessment/HollandCatalogMiddle.php'],
    'holland_high' => ['type' => 'holland', 'count' => 30, 'file' => 'Database/seeds/learner/Assessment/HollandCatalogHigh.php'],
    'holland_college' => ['type' => 'holland', 'count' => 30, 'file' => 'Database/seeds/learner/Assessment/HollandCatalogCollege.php'],
    'mbti_middle' => ['type' => 'mbti', 'count' => 32, 'file' => 'Database/seeds/learner/Assessment/MbtiCatalogMiddle.php'],
    'mbti_high' => ['type' => 'mbti', 'count' => 32, 'file' => 'Database/seeds/learner/Assessment/MbtiCatalogHigh.php'],
    'mbti_college' => ['type' => 'mbti', 'count' => 32, 'file' => 'Database/seeds/learner/Assessment/MbtiCatalogCollege.php'],
    'disc_middle' => ['type' => 'disc', 'count' => 28, 'file' => 'Database/seeds/learner/Assessment/DiscCatalogMiddle.php'],
    'disc_high' => ['type' => 'disc', 'count' => 28, 'file' => 'Database/seeds/learner/Assessment/DiscCatalogHigh.php'],
    'disc_college' => ['type' => 'disc', 'count' => 28, 'file' => 'Database/seeds/learner/Assessment/DiscCatalogCollege.php'],
    'multiple_intelligence_middle' => ['type' => 'multiple_intelligence', 'count' => 32, 'file' => 'Database/seeds/learner/Assessment/MultipleIntelligenceCatalogMiddle.php'],
    'multiple_intelligence_high' => ['type' => 'multiple_intelligence', 'count' => 32, 'file' => 'Database/seeds/learner/Assessment/MultipleIntelligenceCatalogHigh.php'],
    'multiple_intelligence_college' => ['type' => 'multiple_intelligence', 'count' => 32, 'file' => 'Database/seeds/learner/Assessment/MultipleIntelligenceCatalogCollege.php'],
];

// 4. Validate talent_tests and published versions
$testsStmt = $pdo->query('SELECT id, code, name, type, status FROM talent_tests ORDER BY code');
$tests = $testsStmt->fetchAll();
$assert(count($tests) === 12, 'Must have 12 tests in talent_tests.');

$testIdsByCode = [];
foreach ($tests as $t) {
    $code = (string) $t['code'];
    $assert(isset($expectedCatalogs[$code]), "Unexpected test code: {$code}");
    $assert($t['status'] === 'published', "Test {$code} status must be published");
    $assert($t['type'] === $expectedCatalogs[$code]['type'], "Test {$code} type mismatch");
    $assert(Uuid::isValid((string) $t['id']), "Test {$code} id must be valid UUID");
    $testIdsByCode[$code] = (string) $t['id'];
}

// 5. Validate learner_assessment_versions
$versionStmt = $pdo->query('SELECT id, testId, version, scoringVersion, schemaHash, status, publishedAt FROM learner_assessment_versions ORDER BY version');
$versions = $versionStmt->fetchAll();
$assert(count($versions) === 12, 'Must have 12 versions in learner_assessment_versions.');

$versionIdsByTestId = [];
foreach ($versions as $v) {
    $testId = (string) $v['testId'];
    $assert(in_array($testId, $testIdsByCode, true), "Version refers to valid testId: {$testId}");
    $assert($v['version'] === '1.0.0', "Version must be 1.0.0, got {$v['version']}");
    $assert($v['status'] === 'published', "Version status must be published");
    $assert($v['publishedAt'] !== null && trim((string) $v['publishedAt']) !== '', "Version publishedAt must not be null");
    $assert(Uuid::isValid((string) $v['id']), "Version id must be valid UUID");
    $versionIdsByTestId[$testId] = $v;
}

// 6. Deep verification per catalog against source files
$allSeenUuids = [];
$allSeenCodes = [];

foreach ($expectedCatalogs as $testCode => $spec) {
    $sourceFile = dirname(__DIR__) . '/' . $spec['file'];
    $assert(is_file($sourceFile), "Catalog file must exist: {$spec['file']}");
    $catalog = require $sourceFile;

    $metadata = $catalog['metadata'];
    $sourceQuestions = $catalog['questions'];
    $expectedCount = $spec['count'];

    $assert(count($sourceQuestions) === $expectedCount, "Catalog {$testCode} source question count mismatch");

    $testId = $testIdsByCode[$testCode];
    $versionRow = $versionIdsByTestId[$testId];
    $versionId = (string) $versionRow['id'];

    // Verify schemaHash
    $expectedHash = strtolower((string) $metadata['schema_hash']);
    $dbHash = strtolower((string) $versionRow['schemaHash']);
    $computedHash = AbstractCatalogSeeder::computeCanonicalSchemaHash($sourceQuestions);

    $assert(hash_equals($expectedHash, $dbHash), "Database schemaHash mismatch for {$testCode}");
    $assert(hash_equals($expectedHash, $computedHash), "Computed canonical hash mismatch for {$testCode}");

    // Verify scoringVersion
    $assert((string) $metadata['scoring_version'] === (string) $versionRow['scoringVersion'], "Scoring version mismatch for {$testCode}");

    // Fetch questions and bindings from DB
    $qStmt = $pdo->prepare(<<<'SQL'
SELECT tq.id AS question_id, tq.code AS code, tq.content AS content, tq.optionsJson AS options_json,
       tq.status AS question_status, qv.position AS position, qv.dimensionCode AS dimension_code,
       qv.required AS required, qv.id AS binding_id
FROM learner_assessment_question_versions qv
INNER JOIN test_questions tq ON tq.id = qv.questionId
WHERE qv.versionId = :version_id
ORDER BY qv.position ASC
SQL);
    $qStmt->execute(['version_id' => $versionId]);
    $dbQuestions = $qStmt->fetchAll();

    $assert(count($dbQuestions) === $expectedCount, "DB question binding count mismatch for {$testCode}");

    $sourceQuestionsByPos = [];
    foreach ($sourceQuestions as $sq) {
        $sourceQuestionsByPos[(int) $sq['position']] = $sq;
    }

    $seenPositions = [];
    foreach ($dbQuestions as $dbQ) {
        $pos = (int) $dbQ['position'];
        $seenPositions[] = $pos;
        $assert(isset($sourceQuestionsByPos[$pos]), "Position {$pos} must exist in source for {$testCode}");
        $srcQ = $sourceQuestionsByPos[$pos];

        $uuid = strtolower((string) $dbQ['question_id']);
        $code = (string) $dbQ['code'];

        $assert(Uuid::isValid($uuid), "Question UUID {$uuid} must be valid");
        $assert(!isset($allSeenUuids[$uuid]), "Duplicate question UUID globally: {$uuid}");
        $allSeenUuids[$uuid] = $code;

        $assert(!isset($allSeenCodes[$code]), "Duplicate question code globally: {$code}");
        $allSeenCodes[$code] = $uuid;

        $assert(hash_equals($uuid, strtolower((string) $srcQ['id'])), "UUID mismatch at {$testCode} pos {$pos}");
        $assert($code === (string) $srcQ['code'], "Code mismatch at {$testCode} pos {$pos}");
        $assert($dbQ['content'] === (string) $srcQ['content'], "Content mismatch at {$testCode} pos {$pos}");
        $assert($dbQ['dimension_code'] === (string) $srcQ['dimension_code'], "Dimension code mismatch at {$testCode} pos {$pos}");
        $assert((int) $dbQ['required'] === 1, "Required must be 1 at {$testCode} pos {$pos}");
        $assert($dbQ['question_status'] === 'published', "Question status must be published at {$testCode} pos {$pos}");

        $dbOptions = json_decode((string) $dbQ['options_json'], true);
        $assert($dbOptions == $srcQ['options'], "Options mismatch at {$testCode} pos {$pos}");
    }

    $expectedPositions = range(1, $expectedCount);
    $assert($seenPositions === $expectedPositions, "Positions must be contiguous 1..{$expectedCount} for {$testCode}");
}

$assert(count($allSeenUuids) === 366, "Must have exactly 366 unique question UUIDs, got " . count($allSeenUuids));
$assert(count($allSeenCodes) === 366, "Must have exactly 366 unique question codes, got " . count($allSeenCodes));

echo "learner_assessment_catalog_seed_test: {$assertions} assertions passed\n";
