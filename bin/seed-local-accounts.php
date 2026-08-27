<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

$hash = password_hash('123456', PASSWORD_DEFAULT);

$roles = [];
$roleStmt = $pdo->query("SELECT id, code FROM roles");
while ($r = $roleStmt->fetch(PDO::FETCH_ASSOC)) {
    $roles[$r['code']] = $r['id'];
}

$accounts = [
    [
        'email' => 'student@talenthub.local',
        'fullName' => 'Demo Student',
        'role' => 'student',
        'roleId' => $roles['student'] ?? null,
        'profileType' => 'student',
    ],
    [
        'email' => 'teacher@talenthub.local',
        'fullName' => 'Demo Teacher',
        'role' => 'teacher',
        'roleId' => $roles['teacher'] ?? null,
        'profileType' => 'teacher',
    ],
    [
        'email' => 'school@talenthub.local',
        'fullName' => 'Demo School Admin',
        'role' => 'school',
        'roleId' => $roles['school'] ?? null,
        'profileType' => 'school',
    ],
    [
        'email' => 'enterprise@talenthub.local',
        'fullName' => 'Demo Enterprise Manager',
        'role' => 'enterprise',
        'roleId' => $roles['enterprise'] ?? null,
        'profileType' => 'enterprise',
    ],
    [
        'email' => 'admin@talenthub.local',
        'fullName' => 'Demo Platform Admin',
        'role' => 'platform_admin',
        'roleId' => $roles['platform_admin'] ?? null,
        'profileType' => 'admin',
    ],
];

// Reference entities
$schoolId = '10000000-0000-4000-8000-000000000001';
$classId = '10000000-0000-4000-8000-000000000002';
$enterpriseId = '10000000-0000-4000-8000-000000000003';

foreach ($accounts as $acc) {
    $email = $acc['email'];
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $userId = (string) $existingId;
        $pdo->prepare("UPDATE users SET passwordHash = ?, fullName = ?, status = 'active' WHERE id = ?")
            ->execute([$hash, $acc['fullName'], $userId]);
        echo "[UPDATED] {$email} ({$userId})" . PHP_EOL;
    } else {
        $userId = Uuid::v4();
        $pdo->prepare("INSERT INTO users (id, roleId, email, passwordHash, fullName, status) VALUES (?, ?, ?, ?, ?, 'active')")
            ->execute([$userId, $acc['roleId'], $email, $hash, $acc['fullName']]);
        echo "[CREATED] {$email} ({$userId})" . PHP_EOL;
    }

    // Link profiles
    if ($acc['profileType'] === 'student') {
        $spStmt = $pdo->prepare("SELECT id FROM student_profiles WHERE userId = ? LIMIT 1");
        $spStmt->execute([$userId]);
        if (!$spStmt->fetchColumn()) {
            $spId = Uuid::v4();
            $pdo->prepare("INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus) VALUES (?, ?, ?, '2008-01-01', '0901234567', 'active')")
                ->execute([$spId, $userId, $classId]);
            $pdo->prepare("INSERT IGNORE INTO learner_onboarding_states (studentId, status, acceptedAt, completedAt) VALUES (?, 'completed', NOW(), NOW())")
                ->execute([$spId]);
            echo "   -> Linked student profile {$spId}" . PHP_EOL;
        }
    } elseif ($acc['profileType'] === 'teacher') {
        $tpStmt = $pdo->prepare("SELECT id FROM teacher_profiles WHERE userId = ? LIMIT 1");
        $tpStmt->execute([$userId]);
        if (!$tpStmt->fetchColumn()) {
            $tpId = Uuid::v4();
            $pdo->prepare("INSERT INTO teacher_profiles (id, userId, schoolId, specialization, phone, isSchoolAdmin) VALUES (?, ?, ?, 'Toán học', '0912345678', 0)")
                ->execute([$tpId, $userId, $schoolId]);
            echo "   -> Linked teacher profile {$tpId}" . PHP_EOL;
        }
    } elseif ($acc['profileType'] === 'school') {
        $smStmt = $pdo->prepare("SELECT id FROM school_members WHERE userId = ? LIMIT 1");
        $smStmt->execute([$userId]);
        if (!$smStmt->fetchColumn()) {
            $smId = Uuid::v4();
            $pdo->prepare("INSERT INTO school_members (id, schoolId, userId, memberRole) VALUES (?, ?, ?, 'admin')")
                ->execute([$smId, $schoolId, $userId]);
            echo "   -> Linked school member {$smId}" . PHP_EOL;
        }
    } elseif ($acc['profileType'] === 'enterprise') {
        $emStmt = $pdo->prepare("SELECT id FROM enterprise_members WHERE userId = ? LIMIT 1");
        $emStmt->execute([$userId]);
        if (!$emStmt->fetchColumn()) {
            $emId = Uuid::v4();
            $pdo->prepare("INSERT INTO enterprise_members (id, enterpriseId, userId, memberRole) VALUES (?, ?, ?, 'admin')")
                ->execute([$emId, $enterpriseId, $userId]);
            echo "   -> Linked enterprise member {$emId}" . PHP_EOL;
        }
    }
}

echo "=== SEEDING COMPLETED ===" . PHP_EOL;
