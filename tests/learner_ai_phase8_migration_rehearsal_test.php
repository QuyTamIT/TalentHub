<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

try {
    $pdo = new PDO('sqlite::memory:');
} catch (Throwable $exception) {
    echo "learner_ai_phase8_migration_rehearsal_test: SKIPPED (SQLite PDO unavailable)\n";
    exit(0);
}
$runner = new LearnerForwardMigrationRunner($pdo, dirname(__DIR__) . '/Database/migrations/learner', new SchemaInspector($pdo, 'main'));
$versions = ['006_create_ai_refresh_jobs', '007_create_ai_data_outbox', '009_create_ai_capability_profiles', '010_create_ai_provider_health', '011_create_ai_catalog_items', '012_create_school_ai_insights', '013_create_school_ai_refresh_jobs', '014_create_enterprise_ai_match_rankings'];
$first = $runner->migrateApproved($versions);
$second = $runner->migrateApproved($versions);
if ($first !== $versions || $second !== []) throw new RuntimeException('Learner migration rehearsal is not idempotent.');
foreach (['learner_ai_refresh_jobs', 'learner_ai_data_outbox', 'learner_ai_capability_profiles', 'learner_ai_provider_health', 'learner_ai_catalog_items', 'school_ai_insights', 'school_ai_refresh_jobs', 'enterprise_ai_match_rankings'] as $table) {
    if (!(new SchemaInspector($pdo, 'main'))->hasTable($table)) throw new RuntimeException("Missing rehearsed table {$table}.");
}
echo "learner_ai_phase8_migration_rehearsal_test: OK\n";
