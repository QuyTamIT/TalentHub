<?php
declare(strict_types=1);

$provider = dirname(__DIR__) . '/app/learner/includes/activity-data.php';
if (!is_file($provider)) { fwrite(STDERR, "Missing activity provider.\n"); exit(1); }
require_once $provider;

function activity_assert(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "Assertion failed: {$message}\n"); exit(1); } }

$activities = learner_activity_catalog();
$registrations = learner_activity_registration_history('student-demo-001');
activity_assert(count($activities) >= 6, 'catalog has six activities');
activity_assert(count(array_unique(array_column($activities, 'id'))) === count($activities), 'activity ids are unique');

$modes = [];
foreach ($activities as $activity) {
    activity_assert(($activity['source_role'] ?? '') === 'teacher', 'source role is teacher');
    activity_assert(isset($activity['school_id'], $activity['created_by_teacher_id']), 'database foreign keys are represented');
    activity_assert(isset($activity['registration_opens_at'], $activity['registration_closes_at']), 'registration window exists');
    $modes[] = $activity['approval_mode'];
}
activity_assert(in_array('automatic', $modes, true), 'automatic registration exists');
activity_assert(in_array('teacher_review', $modes, true), 'teacher review exists');
activity_assert(learner_activity_find('iot-lab') !== null, 'detail lookup works');
activity_assert(learner_activity_find('missing') === null, 'unknown activity returns null');

$statuses = array_column($registrations, 'status');
foreach (['registered', 'pending', 'waitlisted', 'checked_in', 'completed', 'cancelled'] as $status) {
    activity_assert(in_array($status, $statuses, true), "mock history contains {$status}");
}

echo "learner_activities_data_test: OK\n";
