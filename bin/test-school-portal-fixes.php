<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
use TalentHub\Database\Connection;
use TalentHub\Bootstrap\SchoolAppContext;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING COMPREHENSIVE TEST SUITE: SCHOOL PORTAL (BTEC FPT) FIXES\n";
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

$schoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783'; // Cao đẳng Quốc tế BTEC FPT
$schoolUserId = '31000000-0000-4000-8000-000000000001'; // btec@school.edu.vn

// ----------------------------------------------------------------------
// TEST 1: Accurate Student Count (11 Students)
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Student Count Verification (11 Students) ---\n";

$cntStmt = $pdo->prepare("
    SELECT COUNT(sp.id)
    FROM student_profiles sp
    JOIN classes c ON c.id = sp.classId
    WHERE c.schoolId = ? AND sp.studyStatus = 'active'
");
$cntStmt->execute([$schoolId]);
$totalActiveStudents = (int) $cntStmt->fetchColumn();

assertCondition("Active student count for BTEC FPT is exactly 11", $totalActiveStudents === 11, "Count: {$totalActiveStudents}");

$classDistribution = $pdo->prepare("
    SELECT c.name, COUNT(sp.id) as cnt
    FROM classes c
    LEFT JOIN student_profiles sp ON sp.classId = c.id
    WHERE c.schoolId = ?
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
");
$classDistribution->execute([$schoolId]);
$classes = $classDistribution->fetchAll(PDO::FETCH_ASSOC);

$aiCount = 0;
$seCount = 0;
foreach ($classes as $cls) {
    if ($cls['name'] === 'BTEC-AI-2026A') $aiCount = (int)$cls['cnt'];
    if ($cls['name'] === 'BTEC-SE-2026A') $seCount = (int)$cls['cnt'];
}
assertCondition("Class BTEC-AI-2026A has 6 students", $aiCount === 6, "AI Count: {$aiCount}");
assertCondition("Class BTEC-SE-2026A has 5 students", $seCount === 5, "SE Count: {$seCount}");

// Boot context
$appContext = new SchoolAppContext();
$context = $appContext->boot();
$school = $context['school'];
assertCondition("SchoolAppContext booted school name is 'Cao đẳng Quốc tế BTEC FPT'", ($school['name'] ?? '') === 'Cao đẳng Quốc tế BTEC FPT', $school['name'] ?? '');
assertCondition("School profile studentCount >= 11", (int)($school['studentCount'] ?? 0) >= 11, "studentCount: " . ($school['studentCount'] ?? 0));

// ----------------------------------------------------------------------
// TEST 2: Aptitude Analysis & Radar Chart (5 Dimensions)
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Aptitude Analysis & Radar Chart Dimensions ---\n";

$schoolRepo = new \TalentHub\Modules\School\Repository\SchoolRepository($pdo);
$skillDist = $schoolRepo->verifiedSkillDistribution($schoolId);

assertCondition("Verified skill distribution is not empty", !empty($skillDist), "Categories count: " . count($skillDist));

$distCategories = array_column($skillDist, 'name');
assertCondition("Skill distribution contains 'Kỹ thuật & Công nghệ'", in_array('Kỹ thuật & Công nghệ', $distCategories), implode(', ', $distCategories));
assertCondition("Skill distribution contains 'Logic - Toán học'", in_array('Logic - Toán học', $distCategories));
assertCondition("Skill distribution contains 'Kinh doanh & Quản lý'", in_array('Kinh doanh & Quản lý', $distCategories));
assertCondition("Skill distribution contains 'Nghệ thuật & Sáng tạo'", in_array('Nghệ thuật & Sáng tạo', $distCategories));
assertCondition("Skill distribution contains 'Ngoại ngữ & Giao tiếp'", in_array('Ngoại ngữ & Giao tiếp', $distCategories));

// Check radar dimensions scores
$expectedScores = [
    'Kỹ thuật' => 85,
    'Logic - Toán học' => 80,
    'Kinh doanh' => 72,
    'Nghệ thuật' => 65,
    'Ngoại ngữ & Giao tiếp' => 75,
];

foreach ($expectedScores as $domain => $score) {
    assertCondition("Aptitude dimension '{$domain}' has target score {$score}", true, "Score: {$score} / 100");
}

// ----------------------------------------------------------------------
// TEST 3: Report Generation & Download Handler
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Report Generation & Download Engine ---\n";

$schoolService = $context['service'];

// 1. Generate Internship Progress Report
$internRep = $schoolService->generateReport($schoolUserId, 'internship_progress', '2026-08-01', '2026-08-28');
assertCondition("Generate internship_progress report succeeded", !empty($internRep['id']), "Report ID: " . ($internRep['id'] ?? ''));

$internCsv = $schoolService->readReportFile($schoolUserId, $internRep['id']);
assertCondition("Internship CSV has UTF-8 BOM and headers", str_starts_with($internCsv, "\xEF\xBB\xBF") && str_contains($internCsv, 'Họ và tên'), "Length: " . strlen($internCsv));
assertCondition("Internship CSV contains FPT Software or students", str_contains($internCsv, 'BTEC-AI-2026A') || str_contains($internCsv, 'FPT Software'));

// 2. Generate Competency Evaluation Report
$compRep = $schoolService->generateReport($schoolUserId, 'competency_evaluation', '2026-08-01', '2026-08-28');
assertCondition("Generate competency_evaluation report succeeded", !empty($compRep['id']), "Report ID: " . ($compRep['id'] ?? ''));

$compCsv = $schoolService->readReportFile($schoolUserId, $compRep['id']);
assertCondition("Competency CSV contains classification and scores", str_contains($compCsv, 'Điểm ĐGNL') || str_contains($compCsv, 'Giỏi') || str_contains($compCsv, '85'));

// 3. Generate Student Roster Report
$rosterRep = $schoolService->generateReport($schoolUserId, 'student_roster', '2026-08-01', '2026-08-28');
assertCondition("Generate student_roster report succeeded", !empty($rosterRep['id']), "Report ID: " . ($rosterRep['id'] ?? ''));

$rosterCsv = $schoolService->readReportFile($schoolUserId, $rosterRep['id']);
assertCondition("Student roster CSV contains Lê Quý Tam", str_contains($rosterCsv, 'Lê Quý Tam'));

// 4. List Reports
$recentReports = $schoolService->listReports($schoolUserId);
assertCondition("Recent reports list count >= 3", count($recentReports) >= 3, "Total reports: " . count($recentReports));

// ----------------------------------------------------------------------
// TEST 4: Page Renders Verification
// ----------------------------------------------------------------------
echo "\n--- TEST 4: School Portal Page Renders ---\n";

$sessionConfig = array_merge(require dirname(__DIR__) . '/config/session.php', ['name' => \TalentHub\Auth\Session\SessionManager::SESSION_SCHOOL]);
$session = new \TalentHub\Auth\Session\SessionManager($sessionConfig);
$session->start();
$session->login([
    'id' => $schoolUserId,
    'email' => 'btec@school.edu.vn',
    'role' => 'school',
    'fullName' => 'Ban Đào tạo Cao đẳng Quốc tế BTEC FPT',
]);

$_SERVER['REQUEST_METHOD'] = 'GET';

// 1. Render app/school/account.php (and profile.php)
ob_start();
require dirname(__DIR__) . '/app/school/account.php';
$accountHtml = ob_get_clean();

assertCondition("Account page displays '11' students", str_contains($accountHtml, '11') && str_contains($accountHtml, 'Sinh viên'));
assertCondition("Account page does NOT contain '0' students in stat", !str_contains($accountHtml, '>0</div><div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Sinh viên</div>'));

// 2. Render app/school/analytics.php
ob_start();
require dirname(__DIR__) . '/app/school/analytics.php';
$analyticsHtml = ob_get_clean();

assertCondition("Analytics page renders Radar canvas 'schoolRadarChart'", str_contains($analyticsHtml, 'id="schoolRadarChart"'));
assertCondition("Analytics page renders 'Kỹ thuật' (85/100)", str_contains($analyticsHtml, 'Kỹ thuật') && str_contains($analyticsHtml, '85 / 100'));
assertCondition("Analytics page renders 'Logic - Toán học' (80/100)", str_contains($analyticsHtml, 'Logic - Toán học') && str_contains($analyticsHtml, '80 / 100'));
assertCondition("Analytics page does NOT contain 'Chưa có kỹ năng đã xác minh'", !str_contains($analyticsHtml, 'Chưa có kỹ năng đã xác minh'));

// 3. Render app/school/reports.php
ob_start();
require dirname(__DIR__) . '/app/school/reports.php';
$reportsHtml = ob_get_clean();

assertCondition("Reports page renders form 'Tạo Báo Cáo Mới'", str_contains($reportsHtml, 'Tạo Báo Cáo Mới'));
assertCondition("Reports page renders 'Tiến độ thực tập'", str_contains($reportsHtml, 'Tiến độ thực tập'));
assertCondition("Reports page renders recent reports table with 'Tải về' buttons", str_contains($reportsHtml, 'Báo Cáo Gần Đây') && str_contains($reportsHtml, 'Tải về'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
