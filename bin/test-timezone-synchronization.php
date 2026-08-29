<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/teacher/includes/dashboard-data.php';
require_once dirname(__DIR__) . '/app/teacher/includes/activity-data.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: TIMEZONE SYNCHRONIZATION & ACTIVITY DATE ACCURACY\n";
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
// TEST 1: Default System Timezone
// ----------------------------------------------------------------------
echo "\n--- TEST 1: System Default Timezone ---\n";
$currentTz = date_default_timezone_get();
assertCondition("PHP default timezone is 'Asia/Ho_Chi_Minh'", $currentTz === 'Asia/Ho_Chi_Minh', "Current: {$currentTz}");

// ----------------------------------------------------------------------
// TEST 2: Teacher Activity Creation at 14:15
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Teacher Activity Creation (14:15 Asia/Ho_Chi_Minh) ---\n";

$teacherId = 'a8360cd2-7835-4eb2-892b-c2209089d381'; // ThS. Nguyễn Văn Hùng
$schoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783'; // BTEC FPT

$teacherRepo = new TeacherActivityRepository($pdo);
$teacherService = new TeacherActivityService($teacherRepo);

$inputStartAt = new DateTimeImmutable('2026-09-10 14:15:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$inputEndAt   = new DateTimeImmutable('2026-09-10 17:15:00', new DateTimeZone('Asia/Ho_Chi_Minh'));

$activityId = $teacherService->create($teacherId, $schoolId, [
    'title' => 'Workshop Trải nghiệm AI & IoT 14:15 Test',
    'category' => 'Kỹ thuật',
    'startAt' => $inputStartAt,
    'endAt' => $inputEndAt,
    'capacity' => 40,
]);

assertCondition("Activity created with ID", !empty($activityId), "ID: {$activityId}");

// Verify in DB directly
$stmt = $pdo->prepare("SELECT startAt, endAt FROM activities WHERE id = ?");
$stmt->execute([$activityId]);
$dbRow = $stmt->fetch(PDO::FETCH_ASSOC);

assertCondition("MySQL startAt is stored as '2026-09-10 14:15:00'", str_starts_with((string)$dbRow['startAt'], '2026-09-10 14:15:00'), "DB startAt: " . $dbRow['startAt']);
assertCondition("MySQL endAt is stored as '2026-09-10 17:15:00'", str_starts_with((string)$dbRow['endAt'], '2026-09-10 17:15:00'), "DB endAt: " . $dbRow['endAt']);

// ----------------------------------------------------------------------
// TEST 3: Teacher Activity Edit & Normalization (14:15)
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Teacher Activity View & Edit Normalization ---\n";

$teacherFound = teacherActivitiesFind($pdo, $teacherId, $activityId);
$normalized = teacherActivitiesNormalize($teacherFound);

assertCondition("Teacher start_label displays '10/09/2026 14:15'", $normalized['start_label'] === '10/09/2026 14:15', $normalized['start_label']);
assertCondition("Teacher start_input is '2026-09-10T14:15'", $normalized['start_input'] === '2026-09-10T14:15', $normalized['start_input']);

// ----------------------------------------------------------------------
// TEST 4: Student Side Rendering (14:15 Display)
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Student Portal Activities & Detail Display ---\n";

// 1. Student detail view formatting
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

$studentStartTime = $formatDateTime($dbRow['startAt'], 'H:i');
$studentEndTime   = $formatDateTime($dbRow['endAt'], 'H:i');
$studentStartDate = $formatDateTime($dbRow['startAt'], 'd/m/Y');

assertCondition("Student start time displays exact '14:15'", $studentStartTime === '14:15', "Actual: {$studentStartTime}");
assertCondition("Student end time displays exact '17:15'", $studentEndTime === '17:15', "Actual: {$studentEndTime}");
assertCondition("Student start date displays exact '10/09/2026'", $studentStartDate === '10/09/2026', "Actual: {$studentStartDate}");
assertCondition("Student time is NOT shifted to 12:15", $studentStartTime !== '12:15');

// 2. Student Discovery card format
$discoveryStartAt = new DateTimeImmutable((string) $dbRow['startAt'], new DateTimeZone('Asia/Ho_Chi_Minh'));
$discoveryFormatted = $discoveryStartAt->format('d/m/Y · H:i');
assertCondition("Student discovery card displays '10/09/2026 · 14:15'", $discoveryFormatted === '10/09/2026 · 14:15', $discoveryFormatted);

// ----------------------------------------------------------------------
// TEST 5: Teacher Activity Update to 15:30
// ----------------------------------------------------------------------
echo "\n--- TEST 5: Teacher Activity Update (15:30) & Student Synchronization ---\n";

$newStartAt = new DateTimeImmutable('2026-09-10 15:30:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
$newEndAt   = new DateTimeImmutable('2026-09-10 18:30:00', new DateTimeZone('Asia/Ho_Chi_Minh'));

$teacherService->update($teacherId, $activityId, [
    'title' => 'Workshop Trải nghiệm AI & IoT 15:30 Updated',
    'category' => 'Kỹ thuật',
    'startAt' => $newStartAt,
    'endAt' => $newEndAt,
    'capacity' => 50,
]);

$stmt->execute([$activityId]);
$updatedDbRow = $stmt->fetch(PDO::FETCH_ASSOC);

$updatedStudentStartTime = $formatDateTime($updatedDbRow['startAt'], 'H:i');
$updatedStudentEndTime   = $formatDateTime($updatedDbRow['endAt'], 'H:i');

assertCondition("Updated start time in MySQL is '2026-09-10 15:30:00'", str_starts_with((string)$updatedDbRow['startAt'], '2026-09-10 15:30:00'));
assertCondition("Student start time updated to exact '15:30'", $updatedStudentStartTime === '15:30', "Actual: {$updatedStudentStartTime}");
assertCondition("Student end time updated to exact '18:30'", $updatedStudentEndTime === '18:30', "Actual: {$updatedStudentEndTime}");

// Clean test activity
$pdo->prepare("DELETE FROM activities WHERE id = ?")->execute([$activityId]);

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
