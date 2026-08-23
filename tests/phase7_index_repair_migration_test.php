<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Migration\Migration;

$root = dirname(__DIR__);
$protected = [
    '20260821000500_create_internships_and_application_lifecycle.php' => '28ed78fc9e46068518bf2a87377a337761cc45337b6de6918778a20781353fc7',
    '20260821000510_reconcile_phase7_exact_metadata.php' => 'a0535b5e639658eb4edb85ee1dfe126574bbf5cf5b7965ddb41e1f310f1238ea',
];
foreach ($protected as $file => $hash) {
    if (!is_string($hash) || hash_file('sha256', $root . '/Database/migrations/' . $file) !== $hash) {
        throw new RuntimeException("Applied migration changed: {$file}");
    }
}

$file = $root . '/Database/migrations/20260821000520_reconcile_phase7_exact_indexes.php';
if (!is_file($file)) {
    throw new RuntimeException('Phase 7 exact-index forward repair migration is missing.');
}
$source = file_get_contents($file);
$migration = require $file;
if (!$migration instanceof Migration || $migration->isReversible()) {
    throw new RuntimeException('Phase 7 exact-index repair must be a forward-only Migration.');
}
foreach ([
    'idx_internship_posts_enterprise (enterpriseId)',
    'idx_internship_posts_status_deadline (status, deadline)',
    'idx_internship_applications_student (studentId)',
    'idx_internship_applications_post_status (postId, status)',
    'idx_application_status_history_changed_by (changedByUserId)',
] as $contract) {
    if (!str_contains((string) $source, $contract)) {
        throw new RuntimeException("Missing exact index contract: {$contract}");
    }
}

echo "phase7_index_repair_migration_test: OK\n";
