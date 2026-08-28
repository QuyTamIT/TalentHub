<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== 1. USERS LIKE TAM ===\n";
$stmt = $pdo->query("SELECT u.id, u.email, u.fullName, u.roleId, r.code as roleCode, sp.id as studentProfileId, sp.classId FROM users u LEFT JOIN roles r ON r.id = u.roleId LEFT JOIN student_profiles sp ON sp.userId = u.id WHERE u.fullName LIKE '%Tam%' OR u.email LIKE '%tam%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== 2. ALL STUDENTS ===\n";
$stmt = $pdo->query("SELECT u.id, u.email, u.fullName, sp.id as studentProfileId, sp.classId, c.name as className, s.name as schoolName FROM users u JOIN student_profiles sp ON sp.userId = u.id LEFT JOIN classes c ON c.id = sp.classId LEFT JOIN schools s ON s.id = c.schoolId LIMIT 20");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== 3. TEACHERS ===\n";
$stmt = $pdo->query("SELECT u.id, u.email, u.fullName, tp.id as teacherProfileId, tp.specialization, tp.schoolId FROM users u JOIN teacher_profiles tp ON tp.userId = u.id");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== 4. EVALUATIONS / ASSESSMENTS / GRADES TABLES ===\n";
$tables = ['assessments', 'assessment_results', 'student_evaluations', 'activity_grades', 'activity_evaluation_records', 'activity_registrations', 'student_assessments', 'student_skills', 'student_badges'];
foreach ($tables as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "Table $t: $count rows\n";
    } catch (Throwable $e) {
        echo "Table $t: does not exist or error: " . $e->getMessage() . "\n";
    }
}
