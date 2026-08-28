<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== ROLES TABLE ===\n";
foreach ($pdo->query("SELECT id, name, code FROM roles") as $r) {
    echo "ID: {$r['id']} | Name: {$r['name']} | Code: {$r['code']}\n";
}

echo "\n=== USERS (School/BTEC) ===\n";
foreach ($pdo->query("SELECT u.id, u.email, u.fullName, u.status, u.roleId, r.code as roleCode FROM users u LEFT JOIN roles r ON r.id = u.roleId WHERE r.code = 'school' OR u.email LIKE '%btec%' OR u.email LIKE '%school%'") as $u) {
    echo "ID: {$u['id']} | Email: {$u['email']} | Name: {$u['fullName']} | Role: {$u['roleCode']} | RoleId: {$u['roleId']}\n";
}

echo "\n=== SCHOOLS ===\n";
foreach ($pdo->query("SELECT id, name, email FROM schools") as $s) {
    echo "ID: {$s['id']} | Name: {$s['name']} | Email: {$s['email']}\n";
}

echo "\n=== SCHOOL MEMBERS ===\n";
foreach ($pdo->query("SELECT sm.id, sm.schoolId, sm.userId, sm.memberRole, s.name as schoolName, u.email as userEmail FROM school_members sm LEFT JOIN schools s ON s.id = sm.schoolId LEFT JOIN users u ON u.id = sm.userId") as $m) {
    echo "ID: {$m['id']} | School: {$m['schoolName']} ({$m['schoolId']}) | User: {$m['userEmail']} ({$m['userId']}) | Role: {$m['memberRole']}\n";
}
