<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$exists = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'internship_mentor_assignments'")->fetchColumn();
echo "Table internship_mentor_assignments: " . ($exists ? "EXISTS" : "NOT FOUND") . "\n";
if ($exists) {
    $cols = $pdo->query("DESCRIBE internship_mentor_assignments")->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
}
