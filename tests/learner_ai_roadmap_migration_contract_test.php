<?php

declare(strict_types=1);

function roadmap_migration_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

$root = dirname(__DIR__);
$dcrPath = $root . '/docs/superpowers/database-change-requests/2026-08-24-ai-roadmap-store.md';
$migrationPath = $root . '/Database/migrations/learner/005_create_ai_roadmap_store.php';
$bridgePath = $root . '/Database/migrations/20260824000300_create_learner_ai_roadmap_store.php';

roadmap_migration_assert(is_file($dcrPath), 'exact roadmap database change request exists');
$dcr = (string) file_get_contents($dcrPath);
foreach ([
    'learner_ai_roadmaps', 'learner_ai_roadmap_phases', 'learner_ai_roadmap_tasks', 'learner_ai_roadmap_task_events',
    'uq_learner_ai_roadmaps_student_version', 'uq_learner_ai_roadmaps_run',
    'uq_learner_ai_roadmap_phases_position', 'uq_learner_ai_roadmap_tasks_position',
    'owner trigger', 'append-only', 'immutable', 'backup', 'rehearsal', 'rollback-by-forward-fix',
] as $required) {
    roadmap_migration_assert(str_contains(strtolower($dcr), strtolower($required)), "DCR contains {$required}");
}

roadmap_migration_assert(is_file($migrationPath), 'approved canonical roadmap migration exists');
roadmap_migration_assert(is_file($bridgePath), 'preflighted roadmap deployment bridge exists');

$migrationText = (string) file_get_contents($migrationPath);
roadmap_migration_assert(!preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM|ALTER\s+TABLE)\b/i', $migrationText), 'migration is additive only');
foreach ([
    'UNIQUE KEY uq_learner_ai_roadmaps_student_version',
    'UNIQUE KEY uq_learner_ai_roadmaps_run',
    'UNIQUE KEY uq_learner_ai_roadmap_phases_position',
    'UNIQUE KEY uq_learner_ai_roadmap_tasks_position',
    'JSON_VALID(primaryDirectionJson)',
    'JSON_VALID(alternativeDirectionsJson)',
    'JSON_VALID(insightsJson)',
    'JSON_VALID(evidenceSummaryJson)',
    'JSON_VALID(evidenceJson)',
    'roadmap learner ownership mismatch',
    'roadmap task event learner ownership mismatch',
    'append-only roadmap task event',
    'roadmap phase is immutable',
    'roadmap task is immutable',
] as $contract) {
    roadmap_migration_assert(str_contains($migrationText, $contract), "migration protects {$contract}");
}

$bridge = (string) file_get_contents($bridgePath);
roadmap_migration_assert(str_contains($bridge, '005_create_ai_roadmap_store.php'), 'deployment bridge delegates to the canonical migration');
roadmap_migration_assert(!preg_match('/\b(DROP|TRUNCATE|DELETE\s+FROM|ALTER\s+TABLE)\b/i', $bridge), 'deployment bridge is additive only');

echo "learner_ai_roadmap_migration_contract_test: OK\n";
