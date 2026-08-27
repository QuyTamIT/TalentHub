<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=================== SCHOOLS ===================\n";
$schools = $pdo->query("SELECT id, name FROM schools")->fetchAll(PDO::FETCH_ASSOC);
foreach ($schools as $s) {
    echo "School: {$s['id']} | {$s['name']}\n";
    $stRows = $pdo->query("
        SELECT sp.id as profileId, u.id as userId, u.email, u.fullName, c.id as classId, c.name as className
        FROM student_profiles sp
        JOIN users u ON u.id = sp.userId
        JOIN classes c ON c.id = sp.classId
        WHERE c.schoolId = '{$s['id']}'
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo "  Total students: " . count($stRows) . "\n";
    foreach ($stRows as $st) {
        echo "   - {$st['fullName']} ({$st['email']}) | Class: {$st['className']}\n";
    }
}

echo "\n=================== ALL USERS WITH STUDENT ROLE ===================\n";
$allStudents = $pdo->query("
    SELECT u.id, u.email, u.fullName, sp.id as profileId, sp.classId, c.name as className, c.schoolId, s.name as schoolName
    FROM users u
    LEFT JOIN student_profiles sp ON sp.userId = u.id
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    WHERE u.email LIKE '%@student.%' OR u.email LIKE '%hs.%' OR u.email LIKE '%student%' OR sp.id IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allStudents as $st) {
    echo "User: " . str_pad($st['email'], 45) . " | Name: " . str_pad($st['fullName'], 25) . " | School: " . ($st['schoolName'] ?? 'No School') . "\n";
}
