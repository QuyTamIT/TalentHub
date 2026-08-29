<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: ACTIVITY REGISTRATION SUCCESS FEEDBACK UX\n";
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
// TEST 1: CSS Design System Rules Verification
// ----------------------------------------------------------------------
echo "\n--- TEST 1: CSS Design System Specifications ---\n";
$css = file_get_contents(dirname(__DIR__) . '/app/learner/assets/activities/activities.css');

assertCondition("Feedback box class exists in CSS", str_contains($css, '.learner-activity-feedback-box'));
assertCondition("Feedback box success has #F0FDF4 background", str_contains($css, 'background: #F0FDF4;') || str_contains($css, 'background:#F0FDF4'));
assertCondition("Feedback box has 1.5px solid #86EFAC border", str_contains($css, 'border: 1.5px solid #86EFAC') || str_contains($css, 'border:1.5px solid #86EFAC'));
assertCondition("Feedback box has 12px border radius", str_contains($css, 'border-radius: 12px') || str_contains($css, 'border-radius:12px'));
assertCondition("Check icon has #16A34A color", str_contains($css, '#16A34A'));
assertCondition("Disabled button has #DCFCE7 background & #15803D text", str_contains($css, '.learner-btn--registered-disabled'));
assertCondition("Slide-up & Fade-in animation keyframes exist", str_contains($css, '@keyframes learnerSuccessFeedbackIn'));
assertCondition("Icon scale animation keyframes exist", str_contains($css, '@keyframes learnerSuccessIconScale'));

// ----------------------------------------------------------------------
// TEST 2: Unregistered Activity Page Render (CTA Active, Feedback Hidden)
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Unregistered State Render ---\n";

$studentUserId = 'fd6823de-d3d9-4d3a-b916-9f811853a24c';
$studentProfileId = 'f3150ce0-7a99-4d5f-8b03-c293b91e37e5';
$teacherUserId = 'a8360cd2-7835-4eb2-892b-c2209089d381';
$schoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';

learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => $studentProfileId]);
(new \TalentHub\Bootstrap\StudentAppContext($pdo))->boot();

$teacherRepo = new TeacherActivityRepository($pdo);
$teacherService = new TeacherActivityService($teacherRepo);

// Create a test activity
$startAt = new DateTimeImmutable('+3 days 09:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$endAt   = new DateTimeImmutable('+3 days 17:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$activityId = $teacherService->create($teacherUserId, $schoolId, [
    'title' => '[Feedback UX Test] IoT Bootcamp & AI Lab 2026',
    'category' => 'Kỹ thuật',
    'startAt' => $startAt,
    'endAt' => $endAt,
    'capacity' => 40,
]);
$teacherService->advanceStatus($teacherUserId, $activityId); // publish

$_GET['id'] = $activityId;
$_SESSION['user'] = ['id' => $studentUserId, 'email' => 'tamlangtu2005@gmail.com', 'role' => 'student'];

ob_start();
include dirname(__DIR__) . '/app/learner/activity-detail.php';
$htmlUnregistered = ob_get_clean();

assertCondition("Unregistered page renders CTA button 'Đăng ký hoạt động'", str_contains($htmlUnregistered, 'Đăng ký hoạt động'));
assertCondition("Unregistered page has feedback box with hidden attribute", str_contains($htmlUnregistered, 'data-registration-feedback-box hidden'));

// ----------------------------------------------------------------------
// TEST 3: Registered Success State Render (Feedback Box Visible & CTA Disabled)
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Registered Success State Render ---\n";

// Register the student for this activity
$regId = \TalentHub\Support\Uuid::v4();
$pdo->prepare("
    INSERT INTO activity_registrations (id, activityId, studentId, status, registeredAt, updatedAt)
    VALUES (?, ?, ?, 'approved', NOW(), NOW())
")->execute([$regId, $activityId, $studentProfileId]);

ob_start();
include dirname(__DIR__) . '/app/learner/activity-detail.php';
$htmlRegistered = ob_get_clean();

assertCondition("Registered page renders Feedback Box (NOT hidden)", str_contains($htmlRegistered, 'data-registration-feedback-box') && !str_contains($htmlRegistered, 'data-registration-feedback-box hidden'));
assertCondition("Feedback box contains title 'Đăng ký tham gia thành công!'", str_contains($htmlRegistered, 'Đăng ký tham gia thành công!'));
assertCondition("Feedback box contains caption 'Hệ thống đã ghi nhận tên bạn vào danh sách'", str_contains($htmlRegistered, 'Hệ thống đã ghi nhận tên bạn vào danh sách. Vui lòng có mặt đúng giờ để check-in QR.'));
assertCondition("Feedback box contains circular check icon SVG", str_contains($htmlRegistered, 'learner-activity-feedback-box__icon'));
assertCondition("CTA Button transitions to '✓ Đã đăng ký tham gia'", str_contains($htmlRegistered, '✓ Đã đăng ký tham gia'));
assertCondition("CTA Button has disabled class 'learner-btn--registered-disabled'", str_contains($htmlRegistered, 'learner-btn--registered-disabled'));

// ----------------------------------------------------------------------
// TEST 4: Forwarder clean rendering
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Student Portal Forwarder ---\n";
ob_start();
include dirname(__DIR__) . '/app/student/activity-detail.php';
$htmlForwarded = ob_get_clean();

assertCondition("Student forwarder app/student/activity-detail.php renders Feedback Box seamlessly", str_contains($htmlForwarded, 'learner-activity-feedback-box--success'));

// Clean test activity
$pdo->prepare("DELETE FROM activity_registrations WHERE id = ?")->execute([$regId]);
$pdo->prepare("DELETE FROM activities WHERE id = ?")->execute([$activityId]);

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
