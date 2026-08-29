<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== DESCRIBE INTERNSHIP_APPLICATIONS ===\n";
print_r($pdo->query("DESCRIBE internship_applications")->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== DESCRIBE INTERNSHIP_POSTS ===\n";
print_r($pdo->query("DESCRIBE internship_posts")->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== EXISTING APPLICATIONS SAMPLE ===\n";
print_r($pdo->query("SELECT id, postId, studentId, status, appliedAt FROM internship_applications LIMIT 5")->fetchAll(PDO::FETCH_ASSOC));
