<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== SCHOOLS ===\n";
print_r($pdo->query("SELECT id, name, email, level, address, phone, website, academicYear, status, studentCount, teacherCount FROM schools")->fetchAll(PDO::FETCH_ASSOC));

exit;

echo "=== CLASSES ===\n";
print_r($pdo->query("SELECT c.id, c.schoolId, s.name as schoolName, c.name as className, c.gradeLevel, c.status FROM classes c LEFT JOIN schools s ON s.id = c.schoolId")->fetchAll(PDO::FETCH_ASSOC));
