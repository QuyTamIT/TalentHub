<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SEEDING DIVERSE TEACHERS & DEDUPLICATING TEACHER PROFILES\n";
echo "======================================================================\n\n";

// 1. Find school ID for BTEC FPT
$btecSchoolId = $pdo->query("SELECT id FROM schools WHERE name LIKE '%BTEC%' LIMIT 1")->fetchColumn();
if (!$btecSchoolId) {
    $btecSchoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';
}
echo "Target School (BTEC FPT): {$btecSchoolId}\n";

// 2. Define the 5 standard diverse teachers
$teachersData = [
    [
        'email' => 'teacher1@talenthub.local',
        'fullName' => 'ThS. Nguyễn Văn Hùng',
        'phone' => '0901234501',
        'specialization' => 'Kỹ thuật phần mềm & Trí tuệ nhân tạo (AI)',
        'bio' => 'Thạc sĩ Khoa học Máy tính, 10 năm kinh nghiệm nghiên cứu AI và phát triển phần mềm ứng dụng.',
    ],
    [
        'email' => 'namth@talenthub.local',
        'fullName' => 'TS. Trần Hoàng Nam',
        'phone' => '0901234502',
        'specialization' => 'Khoa học dữ liệu & Học máy (Machine Learning)',
        'bio' => 'Tiến sĩ Khoa học Dữ liệu, chuyên gia Machine Learning và phân tích dữ liệu lớn (Big Data).',
    ],
    [
        'email' => 'lanltm@talenthub.local',
        'fullName' => 'ThS. Lê Thị Mai Lan',
        'phone' => '0901234503',
        'specialization' => 'Phát triển Ứng dụng Web & Điện toán đám mây',
        'bio' => 'Thạc sĩ Kỹ thuật Phần mềm, chuyên sâu kiến trúc đám mây Cloud Computing và Fullstack Web.',
    ],
    [
        'email' => 'baopq@talenthub.local',
        'fullName' => 'TS. Phạm Quốc Bảo',
        'phone' => '0901234504',
        'specialization' => 'An toàn thông tin & Mạng máy tính',
        'bio' => 'Tiến sĩ An ninh mạng, chuyên gia đánh giá bảo mật hệ thống và an toàn mạng doanh nghiệp.',
    ],
    [
        'email' => 'thaodp@talenthub.local',
        'fullName' => 'ThS. Đỗ Phương Thảo',
        'phone' => '0901234505',
        'specialization' => 'Thiết kế trải nghiệm người dùng (UI/UX) & Đồ họa',
        'bio' => 'Thạc sĩ Thiết kế Tương tác, giảng viên Design System và trải nghiệm người dùng UI/UX.',
    ],
];

// 3. Keep main ThS. Nguyễn Văn Hùng profile (a8360cd2-7835-4eb2-892b-c2209089d381), consolidate any duplicate profiles
$primaryHungUserId = $pdo->query("SELECT id FROM users WHERE email = 'teacher1@talenthub.local' LIMIT 1")->fetchColumn();
$primaryHungProfileId = 'a8360cd2-7835-4eb2-892b-c2209089d381';

// Check if primaryHungProfile exists
$chk = $pdo->prepare("SELECT id FROM teacher_profiles WHERE id = ?");
$chk->execute([$primaryHungProfileId]);
if (!$chk->fetchColumn()) {
    $primaryHungProfileId = $pdo->query("SELECT id FROM teacher_profiles WHERE userId = '{$primaryHungUserId}' LIMIT 1")->fetchColumn();
}

$hungProfiles = $pdo->prepare("
    SELECT tp.id, tp.userId
    FROM teacher_profiles tp
    JOIN users u ON u.id = tp.userId
    WHERE tp.schoolId = ? AND u.fullName LIKE '%Nguyễn Văn Hùng%' AND tp.id != ?
");
$hungProfiles->execute([$btecSchoolId, $primaryHungProfileId]);
$duplicateHungRows = $hungProfiles->fetchAll(PDO::FETCH_ASSOC);

// Re-link foreign keys for any duplicate profiles
$fkRows = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_NAME = 'teacher_profiles'
      AND REFERENCED_COLUMN_NAME = 'id'
      AND TABLE_SCHEMA = DATABASE()
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($duplicateHungRows as $dup) {
    $dupProfileId = $dup['id'];
    $dupUserId = $dup['userId'];
    echo "Consolidating duplicate profile {$dupProfileId} -> {$primaryHungProfileId}...\n";

    foreach ($fkRows as $fk) {
        $tbl = $fk['TABLE_NAME'];
        $col = $fk['COLUMN_NAME'];
        $pdo->prepare("UPDATE `{$tbl}` SET `{$col}` = ? WHERE `{$col}` = ?")->execute([$primaryHungProfileId, $dupProfileId]);
    }

    $pdo->prepare("DELETE FROM teacher_profiles WHERE id = ?")->execute([$dupProfileId]);
    if ($dupUserId !== $primaryHungUserId) {
        // Re-link users table references if any before deleting
        $userFkRows = $pdo->query("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_NAME = 'users'
              AND REFERENCED_COLUMN_NAME = 'id'
              AND TABLE_SCHEMA = DATABASE()
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($userFkRows as $ufk) {
            $tbl = $ufk['TABLE_NAME'];
            $col = $ufk['COLUMN_NAME'];
            $pdo->prepare("UPDATE `{$tbl}` SET `{$col}` = ? WHERE `{$col}` = ?")->execute([$primaryHungUserId, $dupUserId]);
        }
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$dupUserId]);
    }
}

// 4. Resolve teacher roleId
$teacherRoleId = $pdo->query("SELECT roleId FROM users WHERE email = 'teacher1@talenthub.local' LIMIT 1")->fetchColumn();
if (!$teacherRoleId) {
    $teacherRoleId = $pdo->query("SELECT id FROM roles WHERE code = 'teacher' OR name LIKE '%Teacher%' OR name LIKE '%Giáo viên%' LIMIT 1")->fetchColumn();
}
if (!$teacherRoleId) {
    $teacherRoleId = '20000000-0000-4000-8000-000000000002';
}

// 5. Seed / Update each of the 5 teachers
foreach ($teachersData as $t) {
    // Check user by email or fullName
    $uStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR fullName = ? LIMIT 1");
    $uStmt->execute([$t['email'], $t['fullName']]);
    $userId = $uStmt->fetchColumn();

    if (!$userId) {
        $userId = Uuid::v4();
        $insUser = $pdo->prepare("
            INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $insUser->execute([
            $userId,
            $teacherRoleId,
            $t['email'],
            password_hash('Password123!', PASSWORD_BCRYPT),
            $t['fullName']
        ]);
        echo "Created user: {$t['fullName']} ({$t['email']})\n";
    } else {
        $pdo->prepare("UPDATE users SET fullName = ?, status = 'active' WHERE id = ?")->execute([$t['fullName'], $userId]);
    }

    // Check teacher profile
    $tpStmt = $pdo->prepare("SELECT id FROM teacher_profiles WHERE userId = ? AND schoolId = ? LIMIT 1");
    $tpStmt->execute([$userId, $btecSchoolId]);
    $profileId = $tpStmt->fetchColumn();

    if (!$profileId) {
        $profileId = Uuid::v4();
        $insTp = $pdo->prepare("
            INSERT INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin, specialization, phone, bio, createdAt, updatedAt)
            VALUES (?, ?, ?, 0, ?, ?, ?, NOW(), NOW())
        ");
        $insTp->execute([
            $profileId,
            $userId,
            $btecSchoolId,
            $t['specialization'],
            $t['phone'],
            $t['bio']
        ]);
        echo "Created teacher profile: {$t['fullName']} - {$t['specialization']}\n";
    } else {
        $updTp = $pdo->prepare("
            UPDATE teacher_profiles
            SET specialization = ?, phone = ?, bio = ?, updatedAt = NOW()
            WHERE id = ?
        ");
        $updTp->execute([
            $t['specialization'],
            $t['phone'],
            $t['bio'],
            $profileId
        ]);
        echo "Updated teacher profile: {$t['fullName']} - {$t['specialization']}\n";
    }
}

// 6. Ensure School Admin account for BTEC FPT
$schoolAdminUser = $pdo->query("SELECT id FROM users WHERE email LIKE '%school%' OR fullName LIKE '%Giám hiệu%' LIMIT 1")->fetchColumn();
if ($schoolAdminUser) {
    $pdo->prepare("
        INSERT INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin, specialization, createdAt, updatedAt)
        VALUES (?, ?, ?, 1, 'Ban Giám hiệu', NOW(), NOW())
        ON DUPLICATE KEY UPDATE schoolId = VALUES(schoolId), isSchoolAdmin = 1
    ")->execute([Uuid::v4(), $schoolAdminUser, $btecSchoolId]);
}

echo "\nDone seeding diverse teachers.\n";
