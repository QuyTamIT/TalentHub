<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/includes/activity-data.php';
require_once dirname(__DIR__) . '/app/teacher/includes/dashboard-data.php';
require_once dirname(__DIR__) . '/app/teacher/includes/activity-data.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\ReadModel\ActivityReadModel;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

$fixture = $pdo->query("
    SELECT tp.id AS teacherId, tp.schoolId, sp.id AS studentProfileId,
           su.id AS studentUserId, su.email AS studentEmail
    FROM teacher_profiles tp
    INNER JOIN student_profiles sp
    INNER JOIN users su ON su.id = sp.userId
    INNER JOIN classes c ON c.id = sp.classId AND c.schoolId = tp.schoolId
    WHERE tp.schoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783'
    ORDER BY tp.id, sp.id
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);
if (!is_array($fixture)) {
    throw new RuntimeException('Fixture requires a teacher and student in the same BTEC school.');
}
learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => (string) $fixture['studentProfileId']]);
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = \TalentHub\Auth\Session\SessionManager::SESSION_STUDENT;
$testSession = new \TalentHub\Auth\Session\SessionManager($sessionConfig);
$testSession->start();
$testSession->login(['id' => (string) $fixture['studentUserId'], 'email' => (string) $fixture['studentEmail'], 'fullName' => (string) $fixture['studentEmail'], 'role' => 'student', 'status' => 'active']);
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

$teacherId = (string) $fixture['teacherId'];
$schoolId = (string) $fixture['schoolId'];
$teacherRepo = new TeacherActivityRepository($pdo);
$teacherService = new TeacherActivityService($teacherRepo);

// Create an active 2-day activity spanning from today 14:00 to tomorrow 17:28
$startAt = (new DateTimeImmutable('tomorrow 14:00:00', new DateTimeZone('Asia/Ho_Chi_Minh')));
$endAt   = $startAt->modify('+1 day')->setTime(17, 28);

$activityId = $teacherService->create($teacherId, $schoolId, [
    'title' => '[Test] Trải nghiệm AI & Robotics BTEC',
    'category' => 'Kỹ thuật',
    'startAt' => $startAt,
    'endAt' => $endAt,
    'capacity' => 60,
    'summary' => 'Hoạt động kiểm thử hiển thị lịch.',
    'locationName' => 'Hội trường BTEC',
]);
// Use a published fixture so this test does not model the Teacher approval workflow.
$pdo->prepare("UPDATE activities SET status = 'published', approvalStatus = 'approved' WHERE id = ?")->execute([$activityId]);

$stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
$stmt->execute([$activityId]);
$dbActivity = $stmt->fetch(PDO::FETCH_ASSOC);

$dbActivity['displayCategory'] = 'Kỹ thuật';
$dbActivity['locationName'] = 'Hội trường Trụ sở BTEC';
$readModel = ActivityReadModel::activity($dbActivity);

assertCondition("Activity status is published", $readModel['status'] === 'published', $readModel['status']);
assertCondition("Activity start_at matches the fixture", str_starts_with((string)$readModel['start_at'], $startAt->format('Y-m-d H:i:s')));
assertCondition("Activity end_at matches the fixture", str_starts_with((string)$readModel['end_at'], $endAt->format('Y-m-d H:i:s')));

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

assertCondition("Event schedule range includes the fixture start", str_contains($eventRange, $startAt->format('d/m/Y H:i')), $eventRange);
assertCondition("Event schedule range includes the fixture end", str_contains($eventRange, $endAt->format('d/m/Y H:i')), $eventRange);
assertCondition("Complete range string matches the fixture", $eventRange === $startAt->format('d/m/Y H:i') . ' đến ' . $endAt->format('d/m/Y H:i'), $eventRange);

// ----------------------------------------------------------------------
// TEST 3: Registration Availability When Event is Ongoing / Active
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Registration Availability For Active / Ongoing Event ---\n";

// Current simulated time during the event: 28/08/2026 14:35 (after startAt 14:00, before endAt 29/08 17:28)
$simulatedNow = $startAt->modify('+35 minutes');
$availability = ActivityReadModel::availabilityState($readModel, $simulatedNow);

assertCondition("Availability code is 'open' (NOT 'expired' or 'unavailable')", $availability['code'] === 'open', "Code: " . $availability['code']);
assertCondition("Availability label is 'Đang mở đăng ký'", $availability['label'] === 'Đang mở đăng ký', "Label: " . $availability['label']);
assertCondition("canRegister helper returns true", ActivityReadModel::canRegister($readModel, $simulatedNow));

// ----------------------------------------------------------------------
// TEST 4: Page Render Verification on /app/learner/activity-detail.php
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Full Page Render Verification ---\n";

$_GET['id'] = $activityId;
$_SESSION['user'] = ['id' => (string) $fixture['studentUserId'], 'email' => (string) $fixture['studentEmail'], 'role' => 'student'];
assertCondition("Repository resolves activity for the fixture student", learner_activity_find($activityId) !== null);

ob_start();
include dirname(__DIR__) . '/app/learner/activity-detail.php';
$html = ob_get_clean();

assertCondition("Page context keeps the fixture student", learner_current_student_id() === (string) $fixture['studentProfileId']);
assertCondition("Page repository still resolves the activity", learner_activity_find($activityId) !== null);
assertCondition("Page renders 200 OK with content", strlen($html) > 5000);
assertCondition("Page renders activity title '[Test] Trải nghiệm AI & Robotics BTEC'", str_contains($html, '[Test] Trải nghiệm AI &amp; Robotics BTEC') || str_contains($html, '[Test] Trải nghiệm AI & Robotics BTEC'));
assertCondition("Page renders the fixture schedule range", str_contains($html, $startAt->format('d/m/Y H:i') . ' đến ' . $endAt->format('d/m/Y H:i')));
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
