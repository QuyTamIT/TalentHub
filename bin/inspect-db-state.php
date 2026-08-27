<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "\n=================== DESCRIBE USERS ===================\n";
$cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo " - {$c['Field']} ({$c['Type']})\n";
}

echo "\n=================== ALL USERS ===================\n";
$users = $pdo->query("
    SELECT u.id, u.email, u.fullName, u.status,
           (SELECT r.code FROM roles r JOIN role_permissions rp ON 1=1 WHERE 1=0) as r_test,
           (SELECT roleId FROM (SELECT userId, roleId FROM school_members UNION SELECT userId, roleId FROM enterprise_members) t WHERE t.userId = u.id LIMIT 1) as org_role
    FROM users u 
    ORDER BY u.email
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($users as $u) {
    // Check student profile or teacher profile or enterprise or school member
    $isStudent = (bool) $pdo->query("SELECT 1 FROM student_profiles WHERE userId = '{$u['id']}' LIMIT 1")->fetchColumn();
    $isTeacher = (bool) $pdo->query("SELECT 1 FROM teacher_profiles WHERE userId = '{$u['id']}' LIMIT 1")->fetchColumn();
    $isSchool = (bool) $pdo->query("SELECT 1 FROM school_members WHERE userId = '{$u['id']}' LIMIT 1")->fetchColumn();
    $isEnterprise = (bool) $pdo->query("SELECT 1 FROM enterprise_members WHERE userId = '{$u['id']}' OR userId IN (SELECT userId FROM enterprises WHERE userId = '{$u['id']}') LIMIT 1")->fetchColumn();
    
    $roleType = 'unknown';
    if ($isStudent) $roleType = 'student';
    elseif ($isTeacher) $roleType = 'teacher';
    elseif ($isSchool) $roleType = 'school';
    elseif ($isEnterprise) $roleType = 'enterprise';

    echo "ID: {$u['id']} | Type: " . str_pad($roleType, 11) . " | Email: " . str_pad($u['email'], 40) . " | Name: {$u['fullName']}\n";
}

echo "\n=================== SCHOOLS ===================\n";
$schools = $pdo->query("SELECT id, name, code, status FROM schools")->fetchAll(PDO::FETCH_ASSOC);
print_r($schools);

echo "\n=================== ENTERPRISES ===================\n";
$enterprises = $pdo->query("SELECT id, userId, name, industry, verificationStatus FROM enterprises")->fetchAll(PDO::FETCH_ASSOC);
print_r($enterprises);

echo "\n=================== CLASSES ===================\n";
$classes = $pdo->query("SELECT c.id, c.schoolId, c.name, s.name as schoolName FROM classes c LEFT JOIN schools s ON s.id = c.schoolId")->fetchAll(PDO::FETCH_ASSOC);
print_r($classes);
