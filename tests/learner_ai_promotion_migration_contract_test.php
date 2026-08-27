<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function promotion_migration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$evidenceMigration = (string) file_get_contents($root . '/Database/migrations/20260827000100_extend_learner_ai_evidence_source_types.php');
promotion_migration_assert(str_contains($evidenceMigration, "'catalog'"), 'persisted evidence accepts the catalog source emitted by the catalog snapshot adapter');

$bridges = [
    '20260827000200_bridge_learner_ai_refresh_jobs.php' => '006_create_ai_refresh_jobs',
    '20260827000300_bridge_learner_ai_data_outbox.php' => '007_create_ai_data_outbox',
    '20260827000400_bridge_learner_ai_freshness_state.php' => '008_add_ai_freshness_and_refresh_state',
    '20260827000500_bridge_learner_ai_capability_profiles.php' => '009_create_ai_capability_profiles',
    '20260827000600_bridge_learner_ai_provider_health.php' => '010_create_ai_provider_health',
    '20260827000700_bridge_learner_ai_catalog.php' => '011_create_ai_catalog_items',
    '20260827000800_bridge_school_ai_insights.php' => '012_create_school_ai_insights',
    '20260827000900_bridge_school_ai_refresh_jobs.php' => '013_create_school_ai_refresh_jobs',
    '20260827001000_bridge_enterprise_ai_match_rankings.php' => '014_create_enterprise_ai_match_rankings',
];

foreach ($bridges as $filename => $version) {
    $path = $root . '/Database/migrations/' . $filename;
    promotion_migration_assert(is_file($path), "deployment migration exists: {$filename}");
    $source = (string) file_get_contents($path);
    promotion_migration_assert(str_contains($source, 'LearnerMigrationBridge::migrate'), "{$filename} invokes the learner migration bridge");
    promotion_migration_assert(str_contains($source, "'{$version}'"), "{$filename} applies {$version}");
}

echo "learner_ai_promotion_migration_contract_test: OK\n";
