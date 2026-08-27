<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE learner_ai_roadmap_task_events");
    $pdo->exec("TRUNCATE TABLE learner_ai_roadmap_tasks");
    $pdo->exec("TRUNCATE TABLE learner_ai_roadmap_phases");
    $pdo->exec("TRUNCATE TABLE learner_ai_roadmaps");
    $pdo->exec("TRUNCATE TABLE learner_recommendation_snapshot_evidence");
    $pdo->exec("TRUNCATE TABLE learner_recommendation_evidence");
    $pdo->exec("TRUNCATE TABLE learner_recommendation_items");
    $pdo->exec("TRUNCATE TABLE learner_recommendation_runs");
    $pdo->exec("TRUNCATE TABLE learner_recommendation_input_snapshots");
    $pdo->exec("TRUNCATE TABLE learner_recommendation_audit_events");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "SUCCESS: Truncate tables bypassed triggers cleanly!\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
