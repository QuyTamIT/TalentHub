<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

$email = 'chau.thietke@talenthub.local';
$fullName = 'Lê Minh Châu';
$password = (string) (Environment::optional('TALENTHUB_TEST_PASSWORD') ?: 'TestPassword12');
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$userId = '24000000-0000-4000-8000-00000000d001';
$studentId = '24000000-0000-4000-8000-00000000d002';
$classId = '24000000-0000-4000-8000-00000000d003';
$className = 'Thiết kế Đồ họa K50';

$roleId = (string) $pdo->query("SELECT id FROM roles WHERE code = 'student' LIMIT 1")->fetchColumn();
if ($roleId === '') {
    fwrite(STDERR, "Role student missing.\n");
    exit(1);
}

$school = $pdo->query('SELECT id, name FROM schools WHERE status = \'active\' ORDER BY createdAt ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$school) {
    fwrite(STDERR, "No active school found.\n");
    exit(1);
}
$schoolId = (string) $school['id'];
$schoolName = (string) $school['name'];

echo "======================================================================\n";
echo " SEED SINH VIÊN THIẾT KẾ ĐỒ HỌA\n";
echo "======================================================================\n\n";

$pdo->beginTransaction();
try {
    $pdo->prepare(
        "INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt)
         VALUES (?, ?, ?, 'Năm 3', '2025-2026', 'active', '2020-01-01 00:00:00', NOW())
         ON DUPLICATE KEY UPDATE name = VALUES(name), status = 'active', updatedAt = NOW()"
    )->execute([$classId, $schoolId, $className]);
    echo "[1] Class: {$className}\n";

    $existing = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $existing->execute([$email]);
    $oldUserId = $existing->fetchColumn();
    if (is_string($oldUserId) && $oldUserId !== '') {
        $oldStudentId = $pdo->query("SELECT id FROM student_profiles WHERE userId = " . $pdo->quote($oldUserId))->fetchColumn();
        if (is_string($oldStudentId) && $oldStudentId !== '') {
            $pdo->exec("DELETE FROM student_skills WHERE studentId = " . $pdo->quote($oldStudentId));
            $pdo->exec("DELETE FROM student_profile_details WHERE studentId = " . $pdo->quote($oldStudentId));
            $pdo->exec("DELETE FROM student_profiles WHERE id = " . $pdo->quote($oldStudentId));
        }
        $pdo->exec("DELETE FROM school_members WHERE userId = " . $pdo->quote($oldUserId));
        $pdo->exec("DELETE FROM users WHERE id = " . $pdo->quote($oldUserId));
    }

    $pdo->prepare(
        "INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, 'active', '2020-01-01 00:00:00', NOW())
         ON DUPLICATE KEY UPDATE roleId = VALUES(roleId), email = VALUES(email), passwordHash = VALUES(passwordHash),
           fullName = VALUES(fullName), status = 'active', createdAt = '2020-01-01 00:00:00', updatedAt = NOW()"
    )->execute([$userId, $roleId, $email, $passwordHash, $fullName]);
    echo "[2] User: {$email}\n";

    $pdo->prepare(
        "INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus, talentScore, createdAt, updatedAt)
         VALUES (?, ?, ?, '2004-06-12', '0912345678', 'active', NULL, '2020-01-01 00:00:00', NOW())
         ON DUPLICATE KEY UPDATE classId = VALUES(classId), dateOfBirth = VALUES(dateOfBirth), phone = VALUES(phone),
           studyStatus = 'active', updatedAt = NOW()"
    )->execute([$studentId, $userId, $classId]);
    echo "[3] Student profile: {$fullName}\n";

    $headline = 'Sinh viên Thiết kế Đồ họa & Truyền thông số';
    $bio = 'Sinh viên chuyên ngành Thiết kế Đồ họa tại ' . $schoolName
        . '. Đam mê nhận diện thương hiệu, UI/UX, minh họa số và storytelling trực quan. '
        . 'Thành thạo Adobe Photoshop, Illustrator, Figma; tập trung portfolio brand identity và digital campaign.';
    $pdo->prepare(
        "INSERT INTO student_profile_details (studentId, location, bio, avatarUrl, headline, createdAt, updatedAt)
         VALUES (?, 'Cần Thơ', ?, NULL, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE location = VALUES(location), bio = VALUES(bio), headline = VALUES(headline), updatedAt = NOW()"
    )->execute([$studentId, $bio, $headline]);
    echo "[4] Profile details: {$headline}\n";

    $memberId = '24000000-0000-4000-8000-00000000d004';
    $pdo->prepare(
        "INSERT INTO school_members (id, schoolId, userId, memberRole, createdAt, updatedAt)
         VALUES (?, ?, ?, 'member', NOW(), NOW())
         ON DUPLICATE KEY UPDATE schoolId = VALUES(schoolId), memberRole = 'member', updatedAt = NOW()"
    )->execute([$memberId, $schoolId, $userId]);

    $skillDefs = [
        ['code' => 'creative_design', 'name' => 'Thiết kế sáng tạo & UI/UX', 'category' => 'creative', 'level' => 78.00, 'fallbackId' => '22000000-8e10-4493-8c96-50d8c1dd776e'],
        ['code' => 'ui_ux_design', 'name' => 'Thiết kế UI/UX', 'category' => 'creative', 'level' => 72.00, 'fallbackId' => 'f351fe23-4453-5cb0-bc7f-4d0a7f051815'],
        ['code' => 'content_creator', 'name' => 'Sáng tạo nội dung', 'category' => 'creative', 'level' => 70.00, 'fallbackId' => '80000000-0000-4000-8000-000000000004'],
        ['code' => 'video_editing', 'name' => 'Video Editing', 'category' => 'creative', 'level' => 65.00, 'fallbackId' => '80000000-0000-4000-8000-000000000007'],
        ['code' => 'storytelling', 'name' => 'Digital Storytelling', 'category' => 'creative', 'level' => 68.00, 'fallbackId' => 'a67f94ac-146f-5a67-a9e2-81a2acdbdcdc'],
        ['code' => 'brand_management', 'name' => 'Quản trị thương hiệu', 'category' => 'business', 'level' => 60.00, 'fallbackId' => '80000000-0000-4000-8000-000000000039'],
        ['code' => 'communication', 'name' => 'Giao tiếp & Thuyết trình', 'category' => 'soft', 'level' => 74.00, 'fallbackId' => '22000000-8fa1-47be-8f2e-af2c412f5fac'],
        ['code' => 'teamwork', 'name' => 'Làm việc nhóm', 'category' => 'soft', 'level' => 80.00, 'fallbackId' => '22000000-6ad4-4e1b-815c-409cb8318c46'],
        ['code' => 'photoshop', 'name' => 'Adobe Photoshop', 'category' => 'creative', 'level' => 85.00, 'fallbackId' => '80000000-0000-4000-8000-00000000d101'],
        ['code' => 'illustrator', 'name' => 'Adobe Illustrator', 'category' => 'creative', 'level' => 82.00, 'fallbackId' => '80000000-0000-4000-8000-00000000d102'],
        ['code' => 'figma', 'name' => 'Figma', 'category' => 'creative', 'level' => 76.00, 'fallbackId' => '80000000-0000-4000-8000-00000000d103'],
    ];

    $findSkill = $pdo->prepare('SELECT id FROM skills WHERE code = ? LIMIT 1');
    $upsertSkill = $pdo->prepare(
        "INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt)
         VALUES (:id, :code, :name, :category, 'active', NOW(), NOW())
         ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), status = 'active', updatedAt = NOW()"
    );
    $pdo->prepare('DELETE FROM student_skills WHERE studentId = ?')->execute([$studentId]);
    $insertStudentSkill = $pdo->prepare(
        "INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, createdAt, updatedAt)
         VALUES (:id, :studentId, :skillId, :levelScore, 'self_declared', 'self_declared', NOW(), NOW())"
    );

    $skillNames = [];
    foreach ($skillDefs as $skill) {
        $findSkill->execute([$skill['code']]);
        $skillId = $findSkill->fetchColumn();
        if (!is_string($skillId) || $skillId === '') {
            $skillId = $skill['fallbackId'];
            $upsertSkill->execute([
                'id' => $skillId,
                'code' => $skill['code'],
                'name' => $skill['name'],
                'category' => $skill['category'],
            ]);
        }
        $insertStudentSkill->execute([
            'id' => Uuid::v4(),
            'studentId' => $studentId,
            'skillId' => $skillId,
            'levelScore' => $skill['level'],
        ]);
        $skillNames[] = $skill['name'];
    }
    echo "[5] Skills: " . implode(', ', $skillNames) . "\n";

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$auth = new AuthService(new AuthRepository($pdo));
$login = $auth->login(['email' => $email, 'password' => $password], 'seed-design-student');
if (($login['email'] ?? '') !== $email) {
    fwrite(STDERR, "Login verify failed.\n");
    exit(1);
}

echo "\n======================================================================\n";
echo " TÀI KHOẢN ĐĂNG NHẬP NHANH\n";
echo "======================================================================\n";
echo " Họ tên   : {$fullName}\n";
echo " Email    : {$email}\n";
echo " Mật khẩu : {$password}\n";
echo " Trường   : {$schoolName}\n";
echo " Lớp      : {$className}\n";
echo " Chuyên ngành: {$headline}\n";
echo "======================================================================\n";
