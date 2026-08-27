<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== 1. CONFIGURE TEACHER ACCOUNT ===\n";
// Find or create role teacher
$roleTeacher = $pdo->query("SELECT id FROM roles WHERE code = 'teacher' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$roleId = $roleTeacher['id'];

// Check user teacher@talenthub.local
$user = $pdo->query("SELECT * FROM users WHERE email = 'teacher@talenthub.local' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$passwordHash = password_hash('123456', PASSWORD_DEFAULT);

if ($user) {
    $userId = $user['id'];
    $stmt = $pdo->prepare("
        UPDATE users 
        SET fullName = 'ThS. Nguyễn Văn Hùng', 
            passwordHash = ?, 
            roleId = ?, 
            status = 'active' 
        WHERE id = ?
    ");
    $stmt->execute([$passwordHash, $roleId, $userId]);
    echo "[OK] Updated users table for teacher@talenthub.local (ID: {$userId})\n";
} else {
    $userId = Uuid::uuid4();
    $stmt = $pdo->prepare("
        INSERT INTO users (id, email, passwordHash, fullName, roleId, status)
        VALUES (?, 'teacher@talenthub.local', ?, 'ThS. Nguyễn Văn Hùng', ?, 'active')
    ");
    $stmt->execute([$userId, $passwordHash, $roleId]);
    echo "[OK] Created user teacher@talenthub.local (ID: {$userId})\n";
}

// Find BTEC FPT school
$school = $pdo->query("SELECT id, name FROM schools WHERE name LIKE '%BTEC%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert(!empty($school), 'BTEC FPT school must exist');
$schoolId = $school['id'];
echo "[OK] Found School: {$school['name']} (ID: {$schoolId})\n";

// Ensure teacher_profiles record
$tp = $pdo->prepare("SELECT id FROM teacher_profiles WHERE userId = ? LIMIT 1");
$tp->execute([$userId]);
$existingTp = $tp->fetch(PDO::FETCH_ASSOC);

if ($existingTp) {
    $stmt = $pdo->prepare("
        UPDATE teacher_profiles 
        SET schoolId = ?, 
            specialization = 'Kỹ thuật phần mềm & Trí tuệ nhân tạo (AI)', 
            bio = 'Giảng viên phụ trách chuyên ngành Trí tuệ nhân tạo (AI) & Kỹ thuật phần mềm - BTEC FPT',
            phone = '0909123456',
            isSchoolAdmin = 0
        WHERE userId = ?
    ");
    $stmt->execute([$schoolId, $userId]);
    $teacherProfileId = $existingTp['id'];
    echo "[OK] Updated teacher_profiles (ID: {$teacherProfileId})\n";
} else {
    $teacherProfileId = Uuid::uuid4();
    $stmt = $pdo->prepare("
        INSERT INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin, phone, specialization, bio)
        VALUES (?, ?, ?, 0, '0909123456', 'Kỹ thuật phần mềm & Trí tuệ nhân tạo (AI)', 'Giảng viên phụ trách chuyên ngành Trí tuệ nhân tạo (AI) & Kỹ thuật phần mềm - BTEC FPT')
    ");
    $stmt->execute([$teacherProfileId, $userId, $schoolId]);
    echo "[OK] Inserted teacher_profiles (ID: {$teacherProfileId})\n";
}

echo "\n=== 2. CONFIGURE CLASSES & ASSIGN TEACHER ===\n";
// Check if homeroomTeacherId column exists in classes
$hasCol = $pdo->query("SHOW COLUMNS FROM classes LIKE 'homeroomTeacherId'")->fetch();
if (!$hasCol) {
    $pdo->exec("ALTER TABLE classes ADD COLUMN homeroomTeacherId CHAR(36) NULL DEFAULT NULL AFTER academicYear");
    echo "[OK] Added column homeroomTeacherId to classes table.\n";
}

// Assign BTEC-AI-2026A and BTEC-SE-2026A to this teacher
$stmtClass = $pdo->prepare("
    UPDATE classes 
    SET homeroomTeacherId = ? 
    WHERE schoolId = ? AND (name LIKE '%BTEC-AI%' OR name LIKE '%BTEC-SE%')
");
$stmtClass->execute([$userId, $schoolId]);
echo "[OK] Assigned BTEC-AI-2026A and BTEC-SE-2026A to teacher.\n";

// Move Võ Đức Anh into BTEC-AI-2026A
$aiClass = $pdo->query("SELECT id FROM classes WHERE schoolId = '{$schoolId}' AND name LIKE '%BTEC-AI%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert(!empty($aiClass), 'BTEC-AI class must exist');
$aiClassId = $aiClass['id'];

$stmtDucAnh = $pdo->prepare("
    UPDATE student_profiles sp
    JOIN users u ON u.id = sp.userId
    SET sp.classId = ?
    WHERE u.fullName LIKE '%Võ Đức Anh%'
");
$stmtDucAnh->execute([$aiClassId]);
echo "[OK] Assigned Võ Đức Anh to BTEC-AI-2026A.\n";

echo "\n=== 3. ADD talentScore TO student_profiles ===\n";
$hasScoreCol = $pdo->query("SHOW COLUMNS FROM student_profiles LIKE 'talentScore'")->fetch();
if (!$hasScoreCol) {
    $pdo->exec("ALTER TABLE student_profiles ADD COLUMN talentScore DECIMAL(5,2) NULL DEFAULT 85.00 AFTER studyStatus");
    echo "[OK] Added column talentScore to student_profiles table.\n";
}

// Default top students talentScore
$pdo->exec("
    UPDATE student_profiles sp
    JOIN users u ON u.id = sp.userId
    SET sp.talentScore = 95.00
    WHERE u.fullName LIKE '%Trần Minh Đức%'
");
$pdo->exec("
    UPDATE student_profiles sp
    JOIN users u ON u.id = sp.userId
    SET sp.talentScore = 92.00
    WHERE u.fullName LIKE '%Võ Đức Anh%'
");
echo "[OK] Set talentScore = 95.00 for Trần Minh Đức and 92.00 for Võ Đức Anh.\n";

echo "\n=== 4. UPDATE CHECK CONSTRAINT ON internship_applications ===\n";
try {
    $pdo->exec("ALTER TABLE internship_applications DROP CONSTRAINT chk_internship_applications_status");
    $pdo->exec("ALTER TABLE internship_applications ADD CONSTRAINT chk_internship_applications_status CHECK ((status in ('submitted','reviewing','interview','accepted','declined','withdrawn','invited','pending')))");
    echo "[OK] Updated chk_internship_applications_status to allow 'invited' and 'pending'.\n";
} catch (Throwable $e) {
    echo "[NOTE] Constraint update: " . $e->getMessage() . "\n";
}

echo "\n=== 5. VERIFY QUERY FOR TEACHER STUDENTS ===\n";
$stus = $pdo->prepare("
    SELECT sp.id as studentId, u.fullName, u.email, sp.phone, sp.talentScore, c.name as className, spd.headline
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    JOIN classes c ON c.id = sp.classId
    LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
    WHERE c.id = ?
    ORDER BY u.fullName ASC
");
$stus->execute([$aiClassId]);
$list = $stus->fetchAll(PDO::FETCH_ASSOC);
echo "Students in BTEC-AI-2026A (" . count($list) . " students):\n";
foreach ($list as $s) {
    echo " - {$s['fullName']} | Score: {$s['talentScore']}% | Class: {$s['className']} | Email: {$s['email']}\n";
}

echo "\n=== SETUP COMPLETED SUCCESSFULLY! ===\n";
