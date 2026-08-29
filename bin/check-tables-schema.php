<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== TABLES ===\n";
$tables = ['internship_applications', 'job_applications', 'internship_invitations', 'application_status_history', 'notifications', 'student_profiles', 'student_profile_details'];
foreach ($tables as $t) {
    $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$t}'")->fetchColumn();
    echo "Table '{$t}': " . ($exists ? 'EXISTS' : 'NOT FOUND') . "\n";
    if ($exists) {
        $cols = $pdo->query("DESCRIBE {$t}")->fetchAll(PDO::FETCH_ASSOC);
        echo "  Columns: " . implode(', ', array_column($cols, 'Field')) . "\n";
    }
}
