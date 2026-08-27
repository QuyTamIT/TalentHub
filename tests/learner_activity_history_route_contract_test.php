<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routePath = $root . '/app/learner/activity-history.php';
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(is_file($routePath), 'Minimal real activity history route exists.');
$source = is_file($routePath) ? (string) file_get_contents($routePath) : '';
$assert(str_contains($source, "require __DIR__ . '/includes/student-data.php'"), 'History reuses the learner auth, role, and app-context guard.');
$assert(str_contains($source, 'learner_activity_attendance_history(learner_current_student_id())'), 'History reads attendance records through the scoped timeline helper.');
$assert(str_contains($source, "['attended', 'no_show']"), 'History accepts only attendance-resolved statuses.');
$assert(str_contains($source, "\$activityNavigationActive = 'history'"), 'History activates the History tab.');
$assert(str_contains($source, "includes/sidebar.php") && str_contains($source, "includes/header.php"), 'History preserves the shared learner shell.');
$assert(str_contains($source, 'Chưa có lịch sử hoạt động'), 'History has a real empty state.');
$assert(!str_contains(mb_strtolower($source), 'đang phát triển'), 'History is not a coming-soon page.');
$assert(!str_contains($source, 'learner-activities-boot'), 'Minimal history does not expose a boot payload.');
$assert(str_contains($source, "activity-detail.php?id="), 'History links own-school timeline records to scoped detail.');
$assert(str_contains($source, "\$historyItem['activity_id']"), 'History detail links use the activity UUID rather than the registration UUID.');
$assert(str_contains($source, 'learner_escape'), 'History escapes server-owned values.');

$studentData = (string) file_get_contents($root . '/app/learner/includes/student-data.php');
$context = (string) file_get_contents($root . '/src/Bootstrap/StudentAppContext.php');
$assert(str_contains($studentData, "auth-guard.php") && str_contains($studentData, 'StudentAppContext'), 'Shared learner data enforces authentication and student role.');
$assert(str_contains($context, 'LearnerOnboardingGate') && str_contains($context, 'pageDestination'), 'Shared app context enforces the onboarding gate for history.');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_history_route_contract_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_history_route_contract_test: OK\n";
