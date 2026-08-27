<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "====================================================================\n";
echo "   SEEDING & STANDARDIZING COLLEGES / UNIVERSITIES & STUDENTS\n";
echo "====================================================================\n\n";

// 1. Get School Role ID
$schoolRoleIdStmt = $pdo->query("SELECT id FROM roles WHERE code = 'school' LIMIT 1");
$schoolRoleId = (string) ($schoolRoleIdStmt->fetchColumn() ?: '63ff7548-6700-52e0-973d-c9feafeeee29');

$passwordHash = password_hash('123456', PASSWORD_BCRYPT);

// 2. Ensure Schools Exist
// School 1: Cao đẳng Quốc tế BTEC FPT
$btecSchoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';
$stmt = $pdo->prepare("SELECT id FROM schools WHERE id = ?");
$stmt->execute([$btecSchoolId]);
if ($stmt->fetch()) {
    $pdo->prepare("
        UPDATE schools SET 
            name = 'Cao đẳng Quốc tế BTEC FPT',
            email = 'btec@talenthub.local',
            level = 'Cao đẳng',
            address = 'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô, Nam Từ Liêm, Hà Nội',
            phone = '0981 090 513',
            website = 'https://btec.fpt.edu.vn',
            academicYear = '2025 - 2026',
            status = 'active'
        WHERE id = ?
    ")->execute([$btecSchoolId]);
} else {
    $pdo->prepare("
        INSERT INTO schools (id, name, email, level, address, phone, website, academicYear, status, createdAt, updatedAt)
        VALUES (?, 'Cao đẳng Quốc tế BTEC FPT', 'btec@talenthub.local', 'Cao đẳng', 'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô, Nam Từ Liêm, Hà Nội', '0981 090 513', 'https://btec.fpt.edu.vn', '2025 - 2026', 'active', NOW(), NOW())
    ")->execute([$btecSchoolId]);
}
echo "[OK] Updated School: Cao đẳng Quốc tế BTEC FPT ($btecSchoolId)\n";

// School 2: Đại học Cần Thơ
$ctuSchoolId = '23000000-0000-4000-8000-000000000001';
$stmt = $pdo->prepare("SELECT id FROM schools WHERE id = ?");
$stmt->execute([$ctuSchoolId]);
if ($stmt->fetch()) {
    $pdo->prepare("
        UPDATE schools SET 
            name = 'Đại học Cần Thơ',
            email = 'ctu@talenthub.local',
            level = 'Đại học',
            address = 'Khu II, Đường 3/2, P. Xuân Khánh, Q. Ninh Kiều, TP. Cần Thơ',
            phone = '0292 3832 663',
            website = 'https://ctu.edu.vn',
            academicYear = '2025 - 2026',
            status = 'active'
        WHERE id = ?
    ")->execute([$ctuSchoolId]);
} else {
    $pdo->prepare("
        INSERT INTO schools (id, name, email, level, address, phone, website, academicYear, status, createdAt, updatedAt)
        VALUES (?, 'Đại học Cần Thơ', 'ctu@talenthub.local', 'Đại học', 'Khu II, Đường 3/2, P. Xuân Khánh, Q. Ninh Kiều, TP. Cần Thơ', '0292 3832 663', 'https://ctu.edu.vn', '2025 - 2026', 'active', NOW(), NOW())
    ")->execute([$ctuSchoolId]);
}
echo "[OK] Updated School: Đại học Cần Thơ ($ctuSchoolId)\n";

// 3. Ensure School Admin Users Exist & Have Passwords 123456
function ensureSchoolUser(PDO $pdo, string $userId, string $email, string $fullName, string $schoolRoleId, string $passwordHash, string $schoolId): void {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $actualUserId = (string) $existing['id'];
        $pdo->prepare("
            UPDATE users SET 
                fullName = ?,
                passwordHash = ?,
                roleId = ?,
                status = 'active'
            WHERE id = ?
        ")->execute([$fullName, $passwordHash, $schoolRoleId, $actualUserId]);
    } else {
        $actualUserId = $userId;
        $pdo->prepare("
            INSERT INTO users (id, email, fullName, passwordHash, roleId, status, createdAt, updatedAt)
            VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ")->execute([$actualUserId, $email, $fullName, $passwordHash, $schoolRoleId]);
    }

    // Ensure school_members link
    $pdo->prepare("DELETE FROM school_members WHERE userId = ? OR (schoolId = ? AND memberRole = 'admin')")->execute([$actualUserId, $schoolId]);
    $memberId = Uuid::v4();
    $pdo->prepare("
        INSERT INTO school_members (id, schoolId, userId, memberRole, createdAt)
        VALUES (?, ?, ?, 'admin', NOW())
    ")->execute([$memberId, $schoolId, $actualUserId]);
    echo "[OK] Linked School Admin: $email ($fullName) -> School ID: $schoolId\n";
}

// BTEC FPT Admin
ensureSchoolUser($pdo, '31000000-0000-4000-8000-000000000002', 'btec@talenthub.local', 'Ban Đào tạo BTEC FPT', $schoolRoleId, $passwordHash, $btecSchoolId);
// Also link school@talenthub.local to BTEC FPT for compatibility
ensureSchoolUser($pdo, 'cf711da3-ef58-429b-b52f-d3bff8b60e05', 'school@talenthub.local', 'Ban Đào tạo Cao đẳng Quốc tế BTEC FPT', $schoolRoleId, $passwordHash, $btecSchoolId);

// CTU Admin
ensureSchoolUser($pdo, '31000000-0000-4000-8000-000000000003', 'ctu@talenthub.local', 'Ban Giám hiệu Đại học Cần Thơ', $schoolRoleId, $passwordHash, $ctuSchoolId);

// 4. Ensure Classes Exist
// BTEC FPT Classes
$btecClasses = [
    [
        'id' => 'a1e2894b-2386-5404-9695-78a78f5a60d3',
        'schoolId' => $btecSchoolId,
        'name' => 'BTEC-AI-2026A',
        'gradeLevel' => 1,
        'academicYear' => '2025-2026',
    ],
    [
        'id' => 'bc0be670-12fd-545b-a70f-3ebf8ce3fad7',
        'schoolId' => $btecSchoolId,
        'name' => 'BTEC-SE-2026A',
        'gradeLevel' => 1,
        'academicYear' => '2025-2026',
    ],
];

foreach ($btecClasses as $c) {
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ?");
    $stmt->execute([$c['id']]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE classes SET name = ?, schoolId = ?, gradeLevel = ?, academicYear = ?, status = 'active' WHERE id = ?")
            ->execute([$c['name'], $c['schoolId'], $c['gradeLevel'], $c['academicYear'], $c['id']]);
    } else {
        $pdo->prepare("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())")
            ->execute([$c['id'], $c['schoolId'], $c['name'], $c['gradeLevel'], $c['academicYear']]);
    }
    echo "[OK] BTEC Class: {$c['name']}\n";
}

// CTU Classes
$ctuClasses = [
    [
        'id' => '23000000-0000-4000-8000-000000000004',
        'schoolId' => $ctuSchoolId,
        'name' => 'K47 Quản trị Kinh doanh',
        'gradeLevel' => 4,
        'academicYear' => '2025-2026',
    ],
    [
        'id' => '23000000-0000-4000-8000-000000000003',
        'schoolId' => $ctuSchoolId,
        'name' => 'K47 Kinh doanh Quốc tế',
        'gradeLevel' => 4,
        'academicYear' => '2025-2026',
    ],
    [
        'id' => '23000000-0000-4000-8000-000000000002',
        'schoolId' => $ctuSchoolId,
        'name' => 'K47 CNTT (Năm 4)',
        'gradeLevel' => 4,
        'academicYear' => '2025-2026',
    ],
];

foreach ($ctuClasses as $c) {
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ?");
    $stmt->execute([$c['id']]);
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE classes SET name = ?, schoolId = ?, gradeLevel = ?, academicYear = ?, status = 'active' WHERE id = ?")
            ->execute([$c['name'], $c['schoolId'], $c['gradeLevel'], $c['academicYear'], $c['id']]);
    } else {
        $pdo->prepare("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())")
            ->execute([$c['id'], $c['schoolId'], $c['name'], $c['gradeLevel'], $c['academicYear']]);
    }
    echo "[OK] CTU Class: {$c['name']}\n";
}

// 5. Assign Students to Classes & Schools
// BTEC-AI-2026A
$btecAiClassId = 'a1e2894b-2386-5404-9695-78a78f5a60d3';
$btecAiEmails = [
    'tran.minh.duc@student.btec.fpt.edu.vn',
    'ho-thanh-truc@student.btec.talenthub.local',
    'nguyen-khanh-linh@student.btec.talenthub.local',
    'dang-ngoc-mai@student.btec.talenthub.local',
    'mai-phuong-anh@student.btec.talenthub.local',
    'pham-thao-vy@student.btec.talenthub.local',
    'nguyen.minh.quang.qa.20260826b@example.test',
];

foreach ($btecAiEmails as $email) {
    $pdo->prepare("
        UPDATE student_profiles sp
        JOIN users u ON u.id = sp.userId
        SET sp.classId = ?, sp.studyStatus = 'active'
        WHERE u.email = ?
    ")->execute([$btecAiClassId, $email]);
}

// BTEC-SE-2026A
$btecSeClassId = 'bc0be670-12fd-545b-a70f-3ebf8ce3fad7';
$btecSeEmails = [
    'vo.duc.anh@student.btec.fpt.edu.vn',
    'duong-nhat-nam@student.btec.talenthub.local',
    'le-minh-quan@student.btec.talenthub.local',
    'bui-quoc-bao@student.btec.talenthub.local',
    'tran-gia-huy@student.btec.talenthub.local',
    'tamlangtu2005@gmail.com',
];

foreach ($btecSeEmails as $email) {
    $pdo->prepare("
        UPDATE student_profiles sp
        JOIN users u ON u.id = sp.userId
        SET sp.classId = ?, sp.studyStatus = 'active'
        WHERE u.email = ?
    ")->execute([$btecSeClassId, $email]);
}

// CTU Students
// K47 Quản trị Kinh doanh
$ctuQtkdClassId = '23000000-0000-4000-8000-000000000004';
$pdo->prepare("
    UPDATE student_profiles sp
    JOIN users u ON u.id = sp.userId
    SET sp.classId = ?, sp.studyStatus = 'active'
    WHERE u.email = 'lehoangyennhi@student.ctu.edu.vn'
")->execute([$ctuQtkdClassId]);

// K47 Kinh doanh Quốc tế
$ctuKdqkClassId = '23000000-0000-4000-8000-000000000003';
$pdo->prepare("
    UPDATE student_profiles sp
    JOIN users u ON u.id = sp.userId
    SET sp.classId = ?, sp.studyStatus = 'active'
    WHERE u.email = 'hoang.mai.linh@student.ctu.edu.vn'
")->execute([$ctuKdqkClassId]);

// K47 CNTT
$ctuCnttClassId = '23000000-0000-4000-8000-000000000002';
$pdo->prepare("
    UPDATE student_profiles sp
    JOIN users u ON u.id = sp.userId
    SET sp.classId = ?, sp.studyStatus = 'active'
    WHERE u.email = 'le.hoang.nam@student.ctu.edu.vn'
")->execute([$ctuCnttClassId]);

echo "\n[OK] All Students mapped to BTEC FPT and CTU classes successfully!\n";

// 6. Recalculate School studentCount & teacherCount
$pdo->query("
    UPDATE schools s SET 
        studentCount = (
            SELECT COUNT(*) 
            FROM student_profiles sp 
            JOIN classes c ON c.id = sp.classId 
            WHERE c.schoolId = s.id AND sp.studyStatus = 'active'
        ),
        teacherCount = COALESCE((
            SELECT COUNT(*) 
            FROM school_members sm 
            WHERE sm.schoolId = s.id AND sm.memberRole = 'teacher'
        ), 0)
");
echo "[OK] Recalculated studentCount and teacherCount on all schools!\n";

