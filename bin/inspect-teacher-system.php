<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== 1. USERS: teacher@talenthub.local ===\n";
$teacherUser = $pdo->query("SELECT id, email, fullName, roleId, status FROM users WHERE email LIKE '%teacher%'")->fetchAll(PDO::FETCH_ASSOC);
print_r($teacherUser);

echo "\n=== DESCRIBE assessments ===\n";
foreach ($pdo->query("DESCRIBE assessments")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}

echo "\n=== DESCRIBE student_skills ===\n";
foreach ($pdo->query("DESCRIBE student_skills")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}

echo "\n=== 3. TEACHERS TABLE / TEACHER_PROFILES ===\n";
$tables = $pdo->query("SHOW TABLES LIKE '%teacher%'")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

foreach ($tables as $t) {
    echo "--- Table: $t ---\n";
    $cols = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_ASSOC);
    print_r($cols);
    $data = $pdo->query("SELECT * FROM $t LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    print_r($data);
}

echo "\n=== 4. ALL DATABASE TABLES ===\n";
$allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($allTables);

echo "\n=== 5. STUDENTS IN BTEC-AI-2026A ===\n";
$aiClass = $pdo->query("SELECT id, name FROM classes WHERE name LIKE '%BTEC-AI%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($aiClass) {
    echo "Class ID: {$aiClass['id']} ({$aiClass['name']})\n";
    $stus = $pdo->prepare("
        SELECT sp.id as studentId, u.fullName, u.email, sp.phone, spd.headline
        FROM student_profiles sp
        JOIN users u ON u.id = sp.userId
        LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
        WHERE sp.classId = ?
    ");
    $stus->execute([$aiClass['id']]);
    print_r($stus->fetchAll(PDO::FETCH_ASSOC));
}
