<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/learner/data/bootstrap.php';
require_once $root . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Data\Database\DatabaseActivityRepository;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, createdByTeacherId TEXT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, audienceScope TEXT NOT NULL, filterCategory TEXT NULL, displayCategory TEXT NULL, locationName TEXT NULL, coverImageUrl TEXT NULL, coverImageAlt TEXT NULL)');
$pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT NOT NULL, registrationClosesAt TEXT NOT NULL, cancellationClosesAt TEXT NOT NULL, approvalMode TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL)');

$schoolA = '10000000-0000-4000-8000-000000000001';
$schoolB = '10000000-0000-4000-8000-000000000002';
$classA = '20000000-0000-4000-8000-000000000001';
$studentA = '30000000-0000-4000-8000-000000000001';
$otherStudent = '30000000-0000-4000-8000-000000000002';
$now = new DateTimeImmutable(
    (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
    new DateTimeZone('UTC'),
);
$timestamp = static fn (DateTimeImmutable $value): string => $value->format('Y-m-d H:i:s');

$pdo->prepare('INSERT INTO schools VALUES (?,?,?), (?,?,?)')->execute([$schoolA, 'School A', 'active', $schoolB, 'School B', 'active']);
$pdo->prepare('INSERT INTO classes VALUES (?,?)')->execute([$classA, $schoolA]);
$pdo->prepare('INSERT INTO student_profiles VALUES (?,?)')->execute([$studentA, $classA]);

$ids = [
    'eligible' => '40000000-0000-4000-8000-000000000001',
    'start_exact' => '40000000-0000-4000-8000-000000000002',
    'started' => '40000000-0000-4000-8000-000000000003',
    'closed' => '40000000-0000-4000-8000-000000000004',
    'full' => '40000000-0000-4000-8000-000000000005',
    'foreign' => '40000000-0000-4000-8000-000000000006',
    'pending' => '40000000-0000-4000-8000-000000000007',
    'approved' => '40000000-0000-4000-8000-000000000008',
    'waitlisted' => '40000000-0000-4000-8000-000000000009',
    'attended' => '40000000-0000-4000-8000-000000000010',
    'cancelled' => '40000000-0000-4000-8000-000000000011',
    'rejected' => '40000000-0000-4000-8000-000000000012',
];

$insertActivity = $pdo->prepare('INSERT INTO activities VALUES (?,?,?,?,?,?,?,?,?,?)');
$insertDetails = $pdo->prepare('INSERT INTO activity_details VALUES (?,?,?,?,?,?,?)');
$insertPolicy = $pdo->prepare('INSERT INTO activity_registration_policies VALUES (?,?,?,?,?)');
$index = 0;
foreach ($ids as $name => $id) {
    $school = $name === 'foreign' ? $schoolB : $schoolA;
    $start = match ($name) {
        'start_exact' => $now,
        'started' => $now->modify('-1 hour'),
        default => $now->modify('+' . (2 + $index) . ' hours'),
    };
    $end = $start->modify('+2 hours');
    $capacity = $name === 'full' ? 1 : 5;
    $insertActivity->execute([$id, $school, null, $name, 'career_technical', $timestamp($start), $timestamp($end), $capacity, 'published', $timestamp($now->modify('-10 days'))]);
    $insertDetails->execute([$id, 'school_only', 'Kỹ thuật', 'Kỹ thuật', 'Phòng Lab', 'assets/activities/covers/talenthub-stem-robotics.webp', 'Ảnh hoạt động']);
    $registrationClose = $name === 'closed' ? $now : $now->modify('+1 hour +' . $index . ' minutes');
    $insertPolicy->execute([$id, $timestamp($now->modify('-1 day')), $timestamp($registrationClose), $timestamp($registrationClose), 'automatic']);
    $index++;
}

$insertRegistration = $pdo->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?)');
$sequence = 1;
foreach (['pending', 'approved', 'waitlisted', 'attended', 'cancelled', 'rejected'] as $status) {
    $insertRegistration->execute([
        sprintf('50000000-0000-4000-8000-%012d', $sequence++),
        $ids[$status],
        $studentA,
        $status,
    ]);
}
$insertRegistration->execute(['50000000-0000-4000-8000-000000000099', $ids['full'], $otherStudent, 'approved']);
$insertRegistration->execute(['50000000-0000-4000-8000-000000000100', $ids['eligible'], $otherStudent, 'pending']);

$repository = new DatabaseActivityRepository($pdo);
$repositoryIds = array_column($repository->discoverForStudent($studentA, $now), 'id');
$expected = [$ids['eligible'], $ids['cancelled'], $ids['rejected']];
$assert($repositoryIds === $expected, 'Discovery returns only future eligible activities in startAt/id order.');

$aiIds = array_values(array_map(
    static fn (array $row): string => (string) $row['opportunity_id'],
    array_filter(
        (new DatabaseOpportunitySource($pdo, $now))->forStudent($studentA),
        static fn (array $row): bool => ($row['opportunity_type'] ?? null) === 'activity',
    ),
));
$assert($aiIds === $expected, 'AI activity IDs match discovery eligibility: ' . json_encode($aiIds));

learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => $studentA]);
require_once $root . '/app/learner/includes/activity-data.php';
require_once $root . '/app/learner/includes/ecosystem-data.php';
$catalogIds = array_column(learner_activity_catalog(), 'id');
$ecosystemIds = array_column(learner_ecosystem_school_activities($schoolA), 'id');
$dashboardIds = array_slice($catalogIds, 0, 3);
$assert($catalogIds === $expected, 'Learner discovery catalog matches the repository set.');
$assert($dashboardIds === $expected, 'Dashboard upcoming uses the same eligible IDs.');
$assert($ecosystemIds === $expected, 'Ecosystem uses the same eligible IDs.');
$assert(learner_ecosystem_school_activities($schoolB) === [], 'Ecosystem foreign-school filter remains empty.');
$assert($repository->findForStudent($studentA, $ids['approved']) !== null, 'Same-school detail remains resolvable after eligibility exclusion.');

$occupied = (int) $pdo->query("SELECT COUNT(*) FROM activity_registrations WHERE activityId='{$ids['eligible']}' AND status IN ('approved','attended')")->fetchColumn();
$assert($occupied === 0, 'Pending registrations still do not consume capacity.');

$insertRegistration->execute(['50000000-0000-4000-8000-000000000101', $ids['eligible'], $studentA, 'approved']);
$pdo->exec("UPDATE activity_registrations SET status='pending' WHERE activityId='{$ids['cancelled']}' AND studentId='{$studentA}'");
$expectedAfterRegistration = [$ids['rejected']];
$repositoryAfter = array_column($repository->discoverForStudent($studentA, $now), 'id');
$aiAfter = array_values(array_map(
    static fn (array $row): string => (string) $row['opportunity_id'],
    array_filter(
        (new DatabaseOpportunitySource($pdo, $now))->forStudent($studentA),
        static fn (array $row): bool => ($row['opportunity_type'] ?? null) === 'activity',
    ),
));
$catalogAfter = array_column(learner_activity_catalog(), 'id');
$ecosystemAfter = array_column(learner_ecosystem_school_activities($schoolA), 'id');
$assert($repositoryAfter === $expectedAfterRegistration, 'Approved and pending registrations disappear from discovery.');
$assert($aiAfter === $expectedAfterRegistration, 'Approved and pending registrations disappear from AI.');
$assert($catalogAfter === $expectedAfterRegistration, 'Approved and pending registrations disappear from the learner catalog and Dashboard upcoming.');
$assert($ecosystemAfter === $expectedAfterRegistration, 'Approved and pending registrations disappear from Ecosystem.');
$assert($repository->findForStudent($studentA, $ids['eligible']) !== null, 'Approved activity detail remains accessible after discovery exclusion.');
$registrationStatuses = array_column($repository->registrationsFor($studentA), 'status', 'activity_id');
$assert(($registrationStatuses[$ids['eligible']] ?? null) === 'approved', 'Approved activity is managed by the registration timeline.');
$assert(($registrationStatuses[$ids['cancelled']] ?? null) === 'pending', 'Teacher-review-style pending activity is managed by the registration timeline.');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_eligibility_parity_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_eligibility_parity_test: OK\n";
