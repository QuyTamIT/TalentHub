<?php

declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/../src/Database/Migration/MigrationContext.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationContext;

$config = require __DIR__ . '/../config/database.php';

$pdo = (new Connection($config))->connect();
$assessmentTables = [
    'talent_tests',
    'test_questions',
    'learner_assessment_versions',
    'learner_assessment_question_versions',
    'test_attempts',
    'learner_assessment_attempt_metadata',
    'learner_assessment_answers',
    'test_results',
];
$placeholders = implode(',', array_fill(0, count($assessmentTables), '?'));
$statement = $pdo->prepare(
    "SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})",
);
$statement->execute($assessmentTables);
$existingTables = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
if ($existingTables === []) {
    echo "learner_assessment_schema_reconciliation_test: SKIP (no existing assessment schema to reconcile)\n";
    exit(0);
}
if (count($existingTables) !== count($assessmentTables)) {
    throw new RuntimeException('Existing assessment schema is partial; reconciliation must fail closed.');
}

$migration = require __DIR__ . '/../Database/migrations/20260818000100_create_learner_assessment_schema.php';
$migration->preflight(new MigrationContext($pdo));

echo "learner_assessment_schema_reconciliation_test: preflight accepted existing compatible assessment schema\n";
