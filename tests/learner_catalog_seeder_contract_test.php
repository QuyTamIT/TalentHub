<?php

declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/../Database/seeds/learner/Assessment/AbstractCatalogSeeder.php';

use TalentHub\Learner\Seeds\Assessment\AbstractCatalogSeeder;

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assertThrows = static function (callable $operation, string $expectedMessage) use (&$assertions): void {
    $assertions++;
    try {
        $operation();
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), $expectedMessage)) {
            return;
        }
        throw new RuntimeException("Unexpected exception: {$e->getMessage()}", 0, $e);
    }
    throw new RuntimeException("Expected exception containing '{$expectedMessage}'.");
};

$catalog = require __DIR__ . '/../Database/seeds/learner/Assessment/HollandCatalogMiddle.php';
$draftCatalog = $catalog;
$draftCatalog['metadata']['review_state'] = 'draft';
$draftCatalog['metadata']['review_events'] = [];
$assertThrows(
    static fn () => AbstractCatalogSeeder::validateCatalogInMemory(
        $draftCatalog,
        'holland_middle',
        'holland',
        AbstractCatalogSeeder::CATALOG_VERSION,
    ),
    'review_state must be published',
);

$catalog['metadata']['review_state'] = 'published';
$catalog['metadata']['review_events'] = array_map(
    static fn (string $checkpoint): array => [
        'checkpoint' => $checkpoint,
        'reviewer' => 'ContractTest',
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

$validated = AbstractCatalogSeeder::validateCatalogInMemory(
    $catalog,
    'holland_middle',
    'holland',
    AbstractCatalogSeeder::CATALOG_VERSION,
);
$assert(count($validated['questions']) === 30, 'Full in-memory validation should return all Holland questions.');

$invalid = $catalog;
$invalid['questions'][0]['required'] = false;
$assertThrows(
    static fn () => AbstractCatalogSeeder::validateCatalogInMemory(
        $invalid,
        'holland_middle',
        'holland',
        AbstractCatalogSeeder::CATALOG_VERSION,
    ),
    'required',
);

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT UNIQUE, name TEXT, type TEXT, status TEXT, createdAt TEXT, updatedAt TEXT)');
$pdo->exec('CREATE TABLE test_questions (id TEXT PRIMARY KEY, testId TEXT, code TEXT, content TEXT, optionsJson TEXT, status TEXT, createdAt TEXT, updatedAt TEXT)');
$pdo->exec('CREATE TABLE learner_assessment_versions (id TEXT PRIMARY KEY, testId TEXT, version TEXT, scoringVersion TEXT, schemaHash TEXT, status TEXT, publishedAt TEXT, createdAt TEXT)');
$pdo->exec('CREATE TABLE learner_assessment_question_versions (id TEXT PRIMARY KEY, versionId TEXT, questionId TEXT, position INTEGER, dimensionCode TEXT, required INTEGER, createdAt TEXT)');
$pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('test-1', 'holland_middle', 'Holland Middle', 'holland', 'retired', '2026-08-20', '2026-08-20')");

$assertThrows(
    static fn () => (new AbstractCatalogSeeder($pdo))->seedCatalog(
        $catalog,
        'holland_middle',
        'Holland Middle',
        'holland',
    ),
    'published',
);

$cliOutput = [];
$cliExitCode = 0;
$cliScript = realpath(__DIR__ . '/../Database/seeds/learner/AssessmentCatalogMasterSeeder.php');
$cliCommand = '"' . PHP_BINARY . '" "' . $cliScript . '" --database=disposable_review_only 2>&1';
exec($cliCommand, $cliOutput, $cliExitCode);
$cliText = implode("\n", $cliOutput);
$assert($cliExitCode !== 0, 'CLI must fail closed when the disposable database is unavailable.');
$assert(!str_contains($cliText, 'Database/bin/bootstrap.php'), 'CLI must resolve bootstrap from the repository root.');
$assert(str_contains($cliText, 'Database connection failed'), 'CLI should reach the database connection stage after bootstrap.');

echo "learner_catalog_seeder_contract_test: {$assertions} assertions passed\n";
