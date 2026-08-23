<?php
declare(strict_types=1);

$provider = dirname(__DIR__) . '/app/learner/includes/activity-data.php';
if (!is_file($provider)) { fwrite(STDERR, "Missing activity provider.\n"); exit(1); }
require_once $provider;

function activity_assert(bool $condition, string $message): void { if (!$condition) { fwrite(STDERR, "Assertion failed: {$message}\n"); exit(1); } }

$myActivitiesSource = file_get_contents(dirname(__DIR__) . '/app/learner/my-activities.php');
$activityDetailSource = file_get_contents(dirname(__DIR__) . '/app/learner/activity-detail.php');
$activityJavascript = file_get_contents(dirname(__DIR__) . '/assets/js/learner-activities.js');
activity_assert(
    is_string($myActivitiesSource) && is_string($activityDetailSource) && is_string($activityJavascript),
    'activity page and JavaScript sources are readable'
);
foreach ([$myActivitiesSource, $activityDetailSource] as $pageSource) {
    activity_assert(str_contains($pageSource, "'source'"), 'activity boot declares its data source');
    activity_assert(str_contains($pageSource, "'registrations'"), 'activity boot exposes authoritative registrations');
    activity_assert(!str_contains($pageSource, "'mock_registrations'"), 'activity boot does not label repository facts as mock');
}
activity_assert(
    str_contains($activityJavascript, 'const store=localMutationsEnabled?createActivityStorage(global.localStorage):null;'),
    'browser storage is initialized only when explicit mock mutations are enabled'
);
activity_assert(
    str_contains($activityJavascript, 'if((localMutationsEnabled||serverMutationsEnabled)&&isEligibleCancel){')
        && str_contains($activityJavascript, 'cancelButton.dataset.cancelRegistration='),
    'database mode renders cancellation only through the authenticated server gateway'
);
activity_assert(
    str_contains($activityJavascript, 'if(localMutationsEnabled&&isEligibleFeedback){')
        && str_contains($activityJavascript, 'feedbackButton.dataset.feedbackRegistration='),
    'database mode does not render local feedback actions'
);

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
foreach (['approved', 'pending', 'attended', 'cancelled'] as $status) {
    activity_assert(in_array($status, $statuses, true), "mock history contains {$status}");
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, registeredAt TEXT, updatedAt TEXT, cancelledAt TEXT, cancellationReason TEXT)');
$pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT, cancellationClosesAt TEXT, approvalMode TEXT)');
$pdo->exec("INSERT INTO activities VALUES
    ('11111111-1111-4111-8111-111111111111','22222222-2222-4222-8222-222222222222','33333333-3333-4333-8333-333333333333','Policy Activity','Technology','2026-09-10 09:00:00','2026-09-10 11:00:00',2,'published'),
    ('44444444-4444-4444-8444-444444444444','22222222-2222-4222-8222-222222222222','33333333-3333-4333-8333-333333333333','Default Activity','Science','2026-09-11 09:00:00','2026-09-11 11:00:00',5,'published')");
$pdo->exec("INSERT INTO activity_registration_policies VALUES ('11111111-1111-4111-8111-111111111111','2026-08-01 00:00:00','2026-09-09 09:00:00','2026-09-09 12:00:00','teacher_review')");
$pdo->exec("INSERT INTO activity_registrations VALUES
    ('55555555-5555-4555-8555-555555555555','11111111-1111-4111-8111-111111111111','66666666-6666-4666-8666-666666666666','approved','2026-08-20 00:00:00','2026-08-20 00:00:00',NULL,NULL),
    ('77777777-7777-4777-8777-777777777777','11111111-1111-4111-8111-111111111111','88888888-8888-4888-8888-888888888888','attended','2026-08-21 00:00:00','2026-08-21 00:00:00',NULL,NULL),
    ('99999999-9999-4999-8999-999999999999','44444444-4444-4444-8444-444444444444','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','cancelled','2026-08-22 00:00:00','2026-08-23 00:00:00','2026-08-23 00:00:00','student_cancelled')");

$databaseRepository = new \TalentHub\Learner\Data\Database\DatabaseActivityRepository($pdo);
$databaseActivities = $databaseRepository->all();
$policyActivity = array_values(array_filter($databaseActivities, static fn (array $row): bool => $row['id'] === '11111111-1111-4111-8111-111111111111'))[0] ?? null;
$defaultActivity = array_values(array_filter($databaseActivities, static fn (array $row): bool => $row['id'] === '44444444-4444-4444-8444-444444444444'))[0] ?? null;
activity_assert(is_array($policyActivity), 'database policy activity is returned');
activity_assert(($policyActivity['participants'] ?? null) === 2, 'occupied count includes approved and attended only');
activity_assert(($policyActivity['approval_mode'] ?? null) === 'teacher_review', 'database policy approval mode is authoritative');
activity_assert(($policyActivity['registration_closes_at'] ?? null) === '2026-09-09 09:00:00', 'database policy registration close is authoritative');
activity_assert(is_array($defaultActivity), 'database default-policy activity is returned');
activity_assert(($defaultActivity['approval_mode'] ?? null) === 'automatic', 'missing policy defaults to automatic approval');
activity_assert(($defaultActivity['registration_closes_at'] ?? null) === '2026-09-11 09:00:00', 'missing policy defaults registration close to activity start');

$databaseRegistrations = $databaseRepository->registrationsFor('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
activity_assert(($databaseRegistrations[0]['cancelled_at'] ?? null) === '2026-08-23 00:00:00', 'registration read exposes cancellation timestamp');
activity_assert(($databaseRegistrations[0]['cancellation_reason'] ?? null) === 'student_cancelled', 'registration read exposes cancellation reason');

echo "learner_activities_data_test: OK\n";
