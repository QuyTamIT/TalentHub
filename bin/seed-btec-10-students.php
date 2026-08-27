<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SEED: NẠP DANH SÁCH 10 SINH VIÊN CHUẨN CHO CAO ĐẲNG QUỐC TẾ BTEC FPT\n";
echo "======================================================================\n\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$btecSchoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';
$roleStudentId = 'c8b7001c-6496-5dcf-ab0a-dd384c1ba673';
$pwdHash = password_hash('123456', PASSWORD_DEFAULT);

$classAiId = 'a1e2894b-2386-5404-9695-78a78f5a60d3'; // BTEC-AI-2026A
$classSeId = 'bc0be670-12fd-545b-a70f-3ebf8ce3fad7'; // BTEC-SE-2026A

// Purge any existing students under BTEC to cleanly insert the exact 10
$btecClasses = [$classAiId, $classSeId];
$classIdsStr = "'" . implode("','", $btecClasses) . "'";
$existingProfileIds = $pdo->query("SELECT id FROM student_profiles WHERE classId IN ({$classIdsStr})")->fetchAll(PDO::FETCH_COLUMN);

if (!empty($existingProfileIds)) {
    $pList = "'" . implode("','", $existingProfileIds) . "'";
    $pdo->exec("DELETE FROM student_profile_details WHERE studentId IN ({$pList})");
    $pdo->exec("DELETE FROM student_profiles WHERE id IN ({$pList})");
}

$students = [
    // Lớp BTEC-AI-2026A (5 sinh viên)
    [
        'fullName' => 'Vũ Đức Anh',
        'email' => 'vuducanh@student.btec.edu.vn',
        'classId' => $classAiId,
        'className' => 'BTEC-AI-2026A',
        'headline' => 'Trí tuệ Nhân tạo & LLM',
        'phone' => '0912345601',
        'dob' => '2004-05-15',
    ],
    [
        'fullName' => 'Trần Minh Đức',
        'email' => 'tranminhduc@student.btec.edu.vn',
        'classId' => $classAiId,
        'className' => 'BTEC-AI-2026A',
        'headline' => 'Trí tuệ Nhân tạo & LLM',
        'phone' => '0912345602',
        'dob' => '2004-03-20',
    ],
    [
        'fullName' => 'Nguyễn Hoàng Nam',
        'email' => 'hoangnam@student.btec.edu.vn',
        'classId' => $classAiId,
        'className' => 'BTEC-AI-2026A',
        'headline' => 'Trí tuệ Nhân tạo & LLM',
        'phone' => '0912345603',
        'dob' => '2004-07-11',
    ],
    [
        'fullName' => 'Lê Thị Thu Thảo',
        'email' => 'thuthao@student.btec.edu.vn',
        'classId' => $classAiId,
        'className' => 'BTEC-AI-2026A',
        'headline' => 'Trí tuệ Nhân tạo & LLM',
        'phone' => '0912345604',
        'dob' => '2004-11-28',
    ],
    [
        'fullName' => 'Phạm Gia Bảo',
        'email' => 'giabao@student.btec.edu.vn',
        'classId' => $classAiId,
        'className' => 'BTEC-AI-2026A',
        'headline' => 'Trí tuệ Nhân tạo & LLM',
        'phone' => '0912345605',
        'dob' => '2004-09-05',
    ],
    // Lớp BTEC-SE-2026A (5 sinh viên)
    [
        'fullName' => 'Đặng Ngọc Mai',
        'email' => 'ngocmai@student.btec.edu.vn',
        'classId' => $classSeId,
        'className' => 'BTEC-SE-2026A',
        'headline' => 'Kỹ thuật Phần mềm',
        'phone' => '0912345606',
        'dob' => '2004-02-14',
    ],
    [
        'fullName' => 'Bùi Quốc Tuấn',
        'email' => 'quoctuan@student.btec.edu.vn',
        'classId' => $classSeId,
        'className' => 'BTEC-SE-2026A',
        'headline' => 'Kỹ thuật Phần mềm',
        'phone' => '0912345607',
        'dob' => '2004-08-19',
    ],
    [
        'fullName' => 'Hồ Thanh Trúc',
        'email' => 'thanhtruc@student.btec.edu.vn',
        'classId' => $classSeId,
        'className' => 'BTEC-SE-2026A',
        'headline' => 'Kỹ thuật Phần mềm',
        'phone' => '0912345608',
        'dob' => '2004-10-30',
    ],
    [
        'fullName' => 'Dương Nhật Huy',
        'email' => 'nhathuy@student.btec.edu.vn',
        'classId' => $classSeId,
        'className' => 'BTEC-SE-2026A',
        'headline' => 'Kỹ thuật Phần mềm',
        'phone' => '0912345609',
        'dob' => '2004-04-12',
    ],
    [
        'fullName' => 'Lê Minh Quân',
        'email' => 'minhquan@student.btec.edu.vn',
        'classId' => $classSeId,
        'className' => 'BTEC-SE-2026A',
        'headline' => 'Kỹ thuật Phần mềm',
        'phone' => '0912345610',
        'dob' => '2004-12-01',
    ],
];

$stmtUser = $pdo->prepare("
    INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt)
    VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE roleId = VALUES(roleId), passwordHash = VALUES(passwordHash), fullName = VALUES(fullName), status = 'active', updatedAt = NOW()
");

$stmtProfile = $pdo->prepare("
    INSERT INTO student_profiles (id, userId, classId, studyStatus, talentScore, dateOfBirth, phone, createdAt, updatedAt)
    VALUES (?, ?, ?, 'active', NULL, ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE classId = VALUES(classId), studyStatus = 'active', talentScore = NULL, dateOfBirth = VALUES(dateOfBirth), phone = VALUES(phone), updatedAt = NOW()
");

$stmtDetail = $pdo->prepare("
    INSERT INTO student_profile_details (studentId, headline, bio, location, createdAt, updatedAt)
    VALUES (?, ?, ?, 'Hà Nội', NOW(), NOW())
    ON DUPLICATE KEY UPDATE headline = VALUES(headline), bio = VALUES(bio), updatedAt = NOW()
");

foreach ($students as $idx => $st) {
    // Check existing user or make new UUID
    $existingUserId = $pdo->query("SELECT id FROM users WHERE email = '{$st['email']}' LIMIT 1")->fetchColumn();
    $userId = $existingUserId ? (string) $existingUserId : Uuid::v4();
    $profileId = Uuid::v4();

    $stmtUser->execute([$userId, $roleStudentId, $st['email'], $pwdHash, $st['fullName']]);
    $stmtProfile->execute([$profileId, $userId, $st['classId'], $st['dob'], $st['phone']]);
    $stmtDetail->execute([$profileId, $st['headline'], "Sinh viên lớp {$st['className']} - Chuyên ngành {$st['headline']}."]);

    echo ($idx + 1) . ". {$st['fullName']} | {$st['email']} | Lớp: {$st['className']} | CN: {$st['headline']} | SĐT: {$st['phone']}\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// Verify count in BTEC FPT
$total = (int) $pdo->query("
    SELECT COUNT(*) 
    FROM student_profiles sp 
    JOIN classes c ON c.id = sp.classId 
    WHERE c.schoolId = '{$btecSchoolId}'
")->fetchColumn();

echo "\n======================================================================\n";
echo " SEEDING COMPLETED: ĐÃ NẠP THÀNH CÔNG {$total} SINH VIÊN CHO BTEC FPT!\n";
echo "======================================================================\n";
