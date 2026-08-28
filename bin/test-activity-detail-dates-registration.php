<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/teacher/includes/dashboard-data.php';
require_once dirname(__DIR__) . '/app/teacher/includes/activity-data.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\ReadModel\ActivityReadModel;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => '9f9b3e8c-0f72-4b8d-90d9-53ca6ce0a69d']);
(new \TalentHub\Bootstrap\StudentAppContext($pdo))->boot();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: ACTIVITY TIME RANGE & REGISTRATION AVAILABILITY\n";
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

// ----------------------------------------------------------------------
// TEST 1: Forwarder /app/student/activity-detail.php exists
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Student Activity Detail Forwarder ---\n";
$forwarderPath = dirname(__DIR__) . '/app/student/activity-detail.php';
assertCondition("Forwarder app/student/activity-detail.php exists", file_exists($forwarderPath));

// ----------------------------------------------------------------------
// TEST 2: Multi-day Activity Range Formatting (e.g. 28/08 14:00 to 29/08 17:28)
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Multi-Day Event Schedule Display ---\n";

$teacherId = 'a8360cd2-7835-4eb2-892b-c2209089d381'; // ThS. Nguyễn Văn Hùng
$schoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783'; // BTEC FPT
$teacherRepo = new TeacherActivityRepository($pdo);
$teacherService = new TeacherActivityService($teacherRepo);

// Create an active 2-day activity spanning from today 14:00 to tomorrow 17:28
$startAt = new DateTimeImmutable('2026-08-28 14:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$endAt   = new DateTimeImmutable('2026-08-29 17:28:00', new DateTimeZone('Asia/Ho_Chi_Minh'));

$activityId = $teacherService->create($teacherId, $schoolId, [
    'title' => '[Test] Trải nghiệm AI & Robotics BTEC',
    'category' => 'Kỹ thuật',
    'startAt' => $startAt,
    'endAt' => $endAt,
    'capacity' => 60,
]);
// Publish the activity so it is active
$teacherService->advanceStatus($teacherId, $activityId);

$stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
$stmt->execute([$activityId]);
$dbActivity = $stmt->fetch(PDO::FETCH_ASSOC);

$dbActivity['displayCategory'] = 'Kỹ thuật';
$dbActivity['locationName'] = 'Hội trường Trụ sở BTEC';
$readModel = ActivityReadModel::activity($dbActivity);

assertCondition("Activity status is published", $readModel['status'] === 'published', $readModel['status']);
assertCondition("Activity start_at is '2026-08-28 14:00:00'", str_starts_with((string)$readModel['start_at'], '2026-08-28 14:00:00'));
assertCondition("Activity end_at is '2026-08-29 17:28:00'", str_starts_with((string)$readModel['end_at'], '2026-08-29 17:28:00'));

// Test formatting function as implemented in activity-detail.php
$formatDateTime = static function (mixed $value, string $format): string {
    if (!is_string($value) || trim($value) === '') return 'Chưa cập nhật';
    try {
        $clean = trim($value);
        $dt = new DateTimeImmutable($clean, new DateTimeZone('Asia/Ho_Chi_Minh'));
        return $dt->format($format);
    } catch (Throwable) {
        return 'Chưa cập nhật';
    }
};

$sDate = $formatDateTime($readModel['start_at'], 'd/m/Y');
$eDate = $formatDateTime($readModel['end_at'], 'd/m/Y');
$sTime = $formatDateTime($readModel['start_at'], 'H:i');
$eTime = $formatDateTime($readModel['end_at'], 'H:i');
$sFull = $formatDateTime($readModel['start_at'], 'd/m/Y H:i');
$eFull = $formatDateTime($readModel['end_at'], 'd/m/Y H:i');

$eventRange = ($sDate === $eDate) ? "{$sDate} {$sTime} đến {$eTime}" : "{$sFull} đến {$eFull}";

assertCondition("Event schedule range includes start date & time '28/08/2026 14:00'", str_contains($eventRange, '28/08/2026 14:00'), $eventRange);
assertCondition("Event schedule range includes end date & time '29/08/2026 17:28'", str_contains($eventRange, '29/08/2026 17:28'), $eventRange);
assertCondition("Complete range string is '28/08/2026 14:00 đến 29/08/2026 17:28'", $eventRange === '28/08/2026 14:00 đến 29/08/2026 17:28', $eventRange);

// ----------------------------------------------------------------------
// TEST 3: Registration Availability When Event is Ongoing / Active
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Registration Availability For Active / Ongoing Event ---\n";

// Current simulated time during the event: 28/08/2026 14:35 (after startAt 14:00, before endAt 29/08 17:28)
$simulatedNow = new DateTimeImmutable('2026-08-28 14:35:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$availability = ActivityReadModel::availabilityState($readModel, $simulatedNow);

assertCondition("Availability code is 'open' (NOT 'expired' or 'unavailable')", $availability['code'] === 'open', "Code: " . $availability['code']);
assertCondition("Availability label is 'Đang mở đăng ký'", $availability['label'] === 'Đang mở đăng ký', "Label: " . $availability['label']);
assertCondition("canRegister helper returns true", ActivityReadModel::canRegister($readModel, $simulatedNow));

// ----------------------------------------------------------------------
// TEST 4: Page Render Verification on /app/learner/activity-detail.php
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Full Page Render Verification ---\n";

$_GET['id'] = $activityId;
$_SESSION['user'] = ['id' => 'fd6823de-d3d9-4d3a-b916-9f811853a24c', 'email' => 'tamlangtu2005@gmail.com', 'role' => 'student'];

ob_start();
include dirname(__DIR__) . '/app/learner/activity-detail.php';
$html = ob_get_clean();

assertCondition("Page renders 200 OK with content", strlen($html) > 5000);
assertCondition("Page renders activity title '[Test] Trải nghiệm AI & Robotics BTEC'", str_contains($html, '[Test] Trải nghiệm AI &amp; Robotics BTEC') || str_contains($html, '[Test] Trải nghiệm AI & Robotics BTEC'));
assertCondition("Page renders range '28/08/2026 14:00 đến 29/08/2026 17:28'", str_contains($html, '28/08/2026 14:00 đến 29/08/2026 17:28'));
assertCondition("Page renders active registration button (NOT disabled)", str_contains($html, 'data-register-current') && !str_contains($html, 'data-register-current disabled'));
assertCondition("Page does NOT display 'Hoạt động đã hết hạn đăng ký'", !str_contains($html, 'Hoạt động đã hết hạn đăng ký'));

// Clean test activity
$pdo->prepare("DELETE FROM activities WHERE id = ?")->execute([$activityId]);

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
