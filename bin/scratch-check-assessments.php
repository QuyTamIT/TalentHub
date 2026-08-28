<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== ASSESSMENTS TABLE ===\n";
$stmt = $pdo->query("SELECT * FROM assessments");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== ASSESSMENT CRITERIA ===\n";
$stmt = $pdo->query("SELECT * FROM assessment_criteria");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== ASSESSMENT SCORES ===\n";
$stmt = $pdo->query("SELECT * FROM assessment_scores");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== TEST ATTEMPTS & RESULTS ===\n";
$stmt = $pdo->query("SELECT * FROM test_attempts");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $pdo->query("SELECT * FROM test_results");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
