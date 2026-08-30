<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Student\Service\StudentActivityService;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: TEACHER <-> STUDENT ACTIVITY LIFECYCLE & SYNC\n";
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

$studentService = new StudentActivityService($pdo);
$teacherRepo = new TeacherActivityRepository($pdo);
$teacherService = new TeacherActivityService($teacherRepo);

$studentEmail = 'tamlangtu2005@gmail.com';
$studentUserId = 'fd6823de-d3d9-4d3a-b916-9f811853a24c';
$studentProfileId = '9f9b3e8c-0f72-4b8d-90d9-53ca6ce0a69d';
$teacherUserId = 'a8360cd2-7835-4eb2-892b-c2209089d381'; // ThS. Nguyễn Văn Hùng
$btecSchoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783'; // Cao đẳng Quốc tế BTEC FPT

learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => $studentProfileId]);
(new \TalentHub\Bootstrap\StudentAppContext($pdo))->boot();

// ----------------------------------------------------------------------
// TEST 1: Student Scope Resolution
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Student Scope Resolution ---\n";
$scope = $studentService->resolveStudentScope($studentProfileId);
assertCondition("Scope resolves correct studentId", $scope['studentId'] === $studentProfileId, $scope['studentId']);
assertCondition("Scope resolves correct schoolId (BTEC FPT)", $scope['schoolId'] === $btecSchoolId, (string)$scope['schoolId']);

// ----------------------------------------------------------------------
// TEST 2: Active & Future Filtering in Student Discovery
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Student Discovery Filter Conditions ---\n";
$activities = $studentService->discover($studentProfileId);
echo "  Discovered " . count($activities) . " activities for BTEC student.\n";

foreach ($activities as $act) {
    $statusOk = in_array($act['status'], ['published', 'open', 'ongoing'], true);
    assertCondition("Activity '{$act['title']}' has valid status", $statusOk, "Status: " . $act['status']);

    $schoolOk = $act['schoolId'] === $btecSchoolId || $act['schoolId'] === null;
    assertCondition("Activity '{$act['title']}' belongs to BTEC or is global", $schoolOk, "School: " . ($act['schoolName'] ?? 'Global'));

    $endTime = new DateTimeImmutable((string)($act['endAt'] ?? $act['startAt']), new DateTimeZone('Asia/Ho_Chi_Minh'));
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    assertCondition("Activity '{$act['title']}' has not ended", $endTime >= $now, "End: " . $endTime->format('Y-m-d H:i'));
}

// ----------------------------------------------------------------------
// TEST 3: Strict Exclusion of Draft, Archived, and Completed Activities
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Exclusion of Inactive Activities ---\n";
$titles = array_map(static fn($a) => $a['title'], $activities);

// Draft check
assertCondition("Draft activity 'Workshop Trải nghiệm AI & IoT 14:15 Test' is NOT shown", !in_array('Workshop Trải nghiệm AI & IoT 14:15 Test', $titles, true));

// Archived check
assertCondition("Archived activity 'Workshop Trí tuệ Nhân tạo & LLM 2026' is NOT shown", !in_array('Workshop Trí tuệ Nhân tạo & LLM 2026', $titles, true));

// Completed check
assertCondition("Completed activity 'Đồ án Thực hành Phát triển Phần mềm & AI 2026' is NOT shown", !in_array('Đồ án Thực hành Phát triển Phần mềm & AI 2026', $titles, true));

// Other school check (e.g. THPT Nguyễn Trãi or Đại học Cần Thơ)
assertCondition("Other school activity 'Hùng biện Ý tưởng Khởi nghiệp Trẻ' is NOT shown", !in_array('Hùng biện Ý tưởng Khởi nghiệp Trẻ', $titles, true));
assertCondition("Other school activity 'Cuộc thi Phân tích Dữ liệu Kinh doanh & Tài chính số CTU 2026' is NOT shown", !in_array('Cuộc thi Phân tích Dữ liệu Kinh doanh & Tài chính số CTU 2026', $titles, true));

// ----------------------------------------------------------------------
// TEST 4: Dynamic Teacher Lifecycle Transition (Draft -> Published -> Ongoing -> Completed)
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Dynamic Teacher Lifecycle Sync ---\n";

$startFuture = new DateTimeImmutable('+5 days 09:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$endFuture   = new DateTimeImmutable('+5 days 17:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));

// 1. Teacher creates draft
$newActivityId = $teacherService->create($teacherUserId, $btecSchoolId, [
    'title' => 'Cuộc thi Sáng tạo AI Hackathon BTEC 2026',
    'category' => 'Kỹ thuật',
    'startAt' => $startFuture,
    'endAt' => $endFuture,
    'capacity' => 50,
]);

$discoveredDraft = $studentService->discover($studentProfileId);
$titlesDraft = array_map(static fn($a) => $a['title'], $discoveredDraft);
assertCondition("Newly created DRAFT is hidden from student", !in_array('Cuộc thi Sáng tạo AI Hackathon BTEC 2026', $titlesDraft, true));

// 2. Teacher publishes activity (Draft -> Published)
$teacherService->advanceStatus($teacherUserId, $newActivityId);
$discoveredPublished = $studentService->discover($studentProfileId);
$titlesPublished = array_map(static fn($a) => $a['title'], $discoveredPublished);
assertCondition("Published activity appears on student discovery list", in_array('Cuộc thi Sáng tạo AI Hackathon BTEC 2026', $titlesPublished, true));

// 3. Teacher advances to Ongoing (Published -> Ongoing)
$teacherService->advanceStatus($teacherUserId, $newActivityId);
$discoveredOngoing = $studentService->discover($studentProfileId);
$titlesOngoing = array_map(static fn($a) => $a['title'], $discoveredOngoing);
assertCondition("Ongoing activity remains visible on student discovery list", in_array('Cuộc thi Sáng tạo AI Hackathon BTEC 2026', $titlesOngoing, true));

// 4. Teacher advances to Completed (Ongoing -> Completed)
$teacherService->advanceStatus($teacherUserId, $newActivityId);
$discoveredCompleted = $studentService->discover($studentProfileId);
$titlesCompleted = array_map(static fn($a) => $a['title'], $discoveredCompleted);
assertCondition("Completed activity is automatically hidden from student discovery", !in_array('Cuộc thi Sáng tạo AI Hackathon BTEC 2026', $titlesCompleted, true));

// Clean up test activity
$pdo->prepare("DELETE FROM activities WHERE id = ?")->execute([$newActivityId]);

// ----------------------------------------------------------------------
// TEST 5: Student Discovery Page Render
// ----------------------------------------------------------------------
echo "\n--- TEST 5: Student Activities Page Render ---\n";
$_SESSION['user'] = ['id' => $studentUserId, 'email' => $studentEmail, 'role' => 'student'];

ob_start();
include dirname(__DIR__) . '/app/learner/activities.php';
$html = ob_get_clean();

assertCondition("Page renders 200 OK", strlen($html) > 5000);
assertCondition("Page does not show completed or draft activities", !str_contains($html, 'Đồ án Thực hành Phát triển Phần mềm & AI 2026'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
