<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->exec('SET NAMES utf8mb4');

$schoolId = '23000000-0000-4000-8000-00000000f001';
$userId = '31000000-0000-4000-8000-00000000f001';
$memberId = Uuid::v4();
$roleId = '63ff7548-6700-52e0-973d-c9feafeeee29';
$hash = password_hash('123456', PASSWORD_BCRYPT);
$now = date('Y-m-d H:i:s');
$email = 'fschool.cantho@talenthub.local';
$name = 'FSchool Cần Thơ';

$check = $pdo->prepare('SELECT id, name FROM schools WHERE id = ? OR email = ? OR name = ?');
$check->execute([$schoolId, $email, $name]);
$existingSchool = $check->fetch(PDO::FETCH_ASSOC);
if (is_array($existingSchool)) {
    echo "[SKIP] School already exists: {$existingSchool['name']} ({$existingSchool['id']})\n";
    exit(0);
}

$pdo->prepare(
    'INSERT INTO schools (id, name, status, verificationStatus, address, phone, email, website, level, studentCount, teacherCount, academicYear, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?)'
)->execute([
    $schoolId,
    $name,
    'active',
    'verified',
    'Đường 3/2, Ninh Kiều, Cần Thơ',
    '0292 7300 888',
    $email,
    'https://fschool.edu.vn',
    'Liên cấp',
    '2025-2026',
    $now,
    $now,
]);

$userCheck = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$userCheck->execute([$email]);
$existingUserId = $userCheck->fetchColumn();
if ($existingUserId) {
    $userId = (string) $existingUserId;
    $pdo->prepare(
        "UPDATE users SET fullName = ?, passwordHash = ?, roleId = ?, status = 'active', updatedAt = ? WHERE id = ?"
    )->execute(['Ban Giám hiệu FSchool Cần Thơ', $hash, $roleId, $now, $userId]);
} else {
    $pdo->prepare(
        'INSERT INTO users (id, email, fullName, passwordHash, roleId, status, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $userId,
        $email,
        'Ban Giám hiệu FSchool Cần Thơ',
        $hash,
        $roleId,
        'active',
        $now,
        $now,
    ]);
}

$pdo->prepare('DELETE FROM school_members WHERE userId = ?')->execute([$userId]);
$pdo->prepare(
    'INSERT INTO school_members (id, schoolId, userId, memberRole, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([$memberId, $schoolId, $userId, 'admin', $now, $now]);

echo "[OK] School: {$name} ({$schoolId})\n";
echo "[OK] Admin: {$email} / 123456 ({$userId})\n";
