<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING COMPREHENSIVE TEST SUITE: TEACHER PORTAL FIXES\n";
echo "======================================================================\n\n";

$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : '') . "\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " ({$detail})" : '') . "\n";
        $failed++;
    }
}

$teacherEmail = 'teacher1@talenthub.local';
$stTeacher = $pdo->prepare("SELECT u.id as userId, u.fullName, tp.id as teacherId FROM users u JOIN teacher_profiles tp ON tp.userId = u.id WHERE u.email = ?");
$stTeacher->execute([$teacherEmail]);
$teacher = $stTeacher->fetch(PDO::FETCH_ASSOC);
assertCondition("Teacher profile found ({$teacherEmail})", (bool)$teacher, $teacher['fullName'] ?? '');

$teacherId = (string)($teacher['teacherId'] ?? '');
$teacherUserId = (string)($teacher['userId'] ?? '');

// ----------------------------------------------------------------------
// Test 1: Class Filter & Student Listing in BTEC-AI-2026A vs BTEC-SE-2026A
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Class Filtering & Student Listing ---\n";

$aiClassId = 'a1e2894b-2386-5404-9695-78a78f5a60d3';
$seClassId = 'bc0be670-12fd-545b-a70f-3ebf8ce3fad7';

$stAi = $pdo->prepare("
    SELECT u.fullName, u.email, sp.talentScore
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    WHERE sp.classId = ? AND sp.studyStatus = 'active'
    ORDER BY (u.fullName LIKE '%Vũ Đức Anh%') DESC, (u.fullName LIKE '%Lê Quý Tam%') DESC, u.fullName ASC
");
$stAi->execute([$aiClassId]);
$aiStudents = $stAi->fetchAll(PDO::FETCH_ASSOC);
$aiNames = array_column($aiStudents, 'fullName');

assertCondition("BTEC-AI-2026A student count is exactly 6", count($aiStudents) === 6, "Found: " . count($aiStudents) . " students: " . implode(', ', $aiNames));

$expectedAi = ['Vũ Đức Anh', 'Lê Quý Tam', 'Lê Thị Thu Thảo', 'Nguyễn Hoàng Nam', 'Phạm Gia Bảo', 'Trần Minh Đức'];
$allFound = true;
foreach ($expectedAi as $exp) {
    $found = false;
    foreach ($aiNames as $act) {
        if (str_contains($act, $exp)) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $allFound = false;
    }
}
assertCondition("BTEC-AI-2026A contains all 6 required students", $allFound, implode(', ', $expectedAi));

// Verify SE class
$stSe = $pdo->prepare("
    SELECT u.fullName, u.email
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    WHERE sp.classId = ? AND sp.studyStatus = 'active'
");
$stSe->execute([$seClassId]);
$seStudents = $stSe->fetchAll(PDO::FETCH_ASSOC);
$seNames = array_column($seStudents, 'fullName');

assertCondition("BTEC-SE-2026A student count is 5", count($seStudents) === 5, "Found: " . count($seStudents) . " students: " . implode(', ', $seNames));
assertCondition("Lê Quý Tam is NOT in BTEC-SE-2026A", !in_array('Lê Quý Tam', $seNames));

// ----------------------------------------------------------------------
// Test 2: Standardized Activity Data ("Fix yourself" -> "IoT Lab...")
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Standardized Activity & Workshop Data ---\n";

$activityRepo = new \TalentHub\Modules\Teacher\Repository\TeacherActivityRepository($pdo);
$actList = $activityRepo->list($teacherId);

$iotAct = null;
foreach ($actList as $a) {
    if (str_contains((string)$a['title'], 'IoT Lab')) {
        $iotAct = $a;
        break;
    }
}

assertCondition("Activity 'IoT Lab - Cảm biến thông minh & AI Nhúng' exists", $iotAct !== null, $iotAct['title'] ?? 'NOT FOUND');

if ($iotAct) {
    assertCondition("Location is 'Phòng Thực hành B305 - BTEC Cần Thơ'", str_contains((string)$iotAct['locationName'], 'B305'), $iotAct['locationName']);
    assertCondition("Title does NOT contain 'Fix yourself'", !str_contains((string)$iotAct['title'], 'Fix yourself'));
}

$zeroFixYourself = $pdo->query("SELECT COUNT(*) FROM activities WHERE title LIKE '%Fix yourself%'")->fetchColumn();
assertCondition("Zero 'Fix yourself' activities in database", $zeroFixYourself == 0, "Found: {$zeroFixYourself}");

// ----------------------------------------------------------------------
// Test 3: Clean UI (No dev/debug text)
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Clean UI Verification (No debug notes/badges) ---\n";

$sessionConfig = array_merge(require dirname(__DIR__) . '/config/session.php', ['name' => \TalentHub\Auth\Session\SessionManager::SESSION_TEACHER]);
$session = new \TalentHub\Auth\Session\SessionManager($sessionConfig);
$session->start();
$session->login([
    'id' => $teacherUserId,
    'email' => $teacherEmail,
    'role' => 'teacher',
    'fullName' => $teacher['fullName'],
]);

$_SERVER['REQUEST_METHOD'] = 'GET';

// Render app/teacher/index.php
ob_start();
require dirname(__DIR__) . '/app/teacher/index.php';
$dashHtml = ob_get_clean();

assertCondition("Dashboard does NOT contain 'Đã kết nối MySQL và chỉ sử dụng SELECT'", !str_contains($dashHtml, 'Đã kết nối MySQL và chỉ sử dụng SELECT'));
assertCondition("Dashboard does NOT contain 'Đọc từ database'", !str_contains($dashHtml, 'Đọc từ database'));

// Render app/teacher/checkins/index.php
ob_start();
require dirname(__DIR__) . '/app/teacher/checkins/index.php';
$qrHtml = ob_get_clean();

assertCondition("QR page does NOT contain 'triển khai ở giai đoạn tiếp theo'", !str_contains($qrHtml, 'triển khai ở giai đoạn tiếp theo'));

// Render app/teacher/grading.php for AI class
$_GET['class'] = 'BTEC-AI-2026A';
ob_start();
require dirname(__DIR__) . '/app/teacher/grading.php';
$gradingHtml = ob_get_clean();

assertCondition("Grading page renders dropdown selector with BTEC-AI-2026A", str_contains($gradingHtml, 'id="classFilterSelect"') && str_contains($gradingHtml, 'BTEC-AI-2026A'));
assertCondition("Grading page displays exactly 6 students for AI class", str_contains($gradingHtml, 'Sĩ số: 6 sinh viên'));
assertCondition("Grading page lists Lê Quý Tam", str_contains($gradingHtml, 'Lê Quý Tam'));
assertCondition("Grading page lists Vũ Đức Anh", str_contains($gradingHtml, 'Vũ Đức Anh'));

// Render app/teacher/grading.php for SE class
$_GET['class'] = 'BTEC-SE-2026A';
ob_start();
require dirname(__DIR__) . '/app/teacher/grading.php';
$gradingSeHtml = ob_get_clean();

assertCondition("Grading page displays 5 students for SE class", str_contains($gradingSeHtml, 'Sĩ số: 5 sinh viên'));
assertCondition("Grading SE page does NOT list Lê Quý Tam", !str_contains($gradingSeHtml, 'tamlangtu2005@gmail.com'));

// Render app/teacher/activities/index.php
$_GET = [];
ob_start();
require dirname(__DIR__) . '/app/teacher/activities/index.php';
$actHtml = ob_get_clean();

assertCondition("Activities page displays 'IoT Lab - Cảm biến thông minh & AI Nhúng'", str_contains($actHtml, 'IoT Lab - Cảm biến thông minh'));
assertCondition("Activities page displays 'Phòng Thực hành B305 - BTEC Cần Thơ'", str_contains($actHtml, 'Phòng Thực hành B305 - BTEC Cần Thơ'));

// Render app/teacher/attendance-qr.php
ob_start();
require dirname(__DIR__) . '/app/teacher/attendance-qr.php';
$attHtml = ob_get_clean();
assertCondition("attendance-qr.php forwards cleanly", str_contains($attHtml, 'Điểm danh QR'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
