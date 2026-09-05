<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, category TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');
$pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, activityId TEXT NOT NULL, checkinId TEXT NOT NULL, hours REAL NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');
$pdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, type TEXT NOT NULL)');
$pdo->exec('CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, submittedAt TEXT NULL)');
$pdo->exec('CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, status TEXT NOT NULL, publishedAt TEXT NULL)');

$student = '11111111-1111-4111-8111-111111111111';
$pdo->exec("INSERT INTO activities VALUES ('activity-a', 'career_technical')");
foreach ([
    ['reg-pending', 'pending'],
    ['reg-approved', 'approved'],
    ['reg-waitlisted', 'waitlisted'],
    ['reg-attended', 'attended'],
    ['reg-attended-unconfirmed', 'attended'],
    ['reg-no-show', 'no_show'],
    ['reg-cancelled', 'cancelled'],
    ['reg-rejected', 'rejected'],
] as [$id, $status]) {
    $statement = $pdo->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?)');
    $statement->execute([$id, 'activity-a', $student, $status]);
}
foreach ([
    ['checkin-attended', 'reg-attended', 'confirmed', '2026-08-25 10:00:00'],
    ['checkin-attended-duplicate', 'reg-attended', 'confirmed', '2026-08-25 10:05:00'],
    ['checkin-approved', 'reg-approved', 'confirmed', '2026-08-25 10:00:00'],
    ['checkin-no-show', 'reg-no-show', 'confirmed', '2026-08-25 10:00:00'],
    ['checkin-unconfirmed', 'reg-attended-unconfirmed', 'confirmed', null],
] as $row) {
    $statement = $pdo->prepare('INSERT INTO checkins VALUES (?,?,?,?)');
    $statement->execute($row);
}
foreach ([
    ['experience-attended', 'checkin-attended', 3.0],
    ['experience-approved', 'checkin-approved', 4.0],
    ['experience-no-show', 'checkin-no-show', 5.0],
    ['experience-unconfirmed', 'checkin-unconfirmed', 6.0],
] as [$id, $checkinId, $hours]) {
    $statement = $pdo->prepare('INSERT INTO experience_logs VALUES (?,?,?,?,?,?,?)');
    $statement->execute([$id, $student, 'activity-a', $checkinId, $hours, 'confirmed', '2026-08-25 11:00:00']);
}

$statistics = new DatabaseStatisticsRepository($pdo);
$lifetime = $statistics->lifetimeFacts($student);
$assert(($lifetime['confirmed_experience_hours'] ?? null) === 3.0, 'Statistics hours require attended registration, confirmed check-in, and confirmed aligned experience');
$assert(($lifetime['attended_activity_count'] ?? null) === 1, 'Statistics count one attended registration once despite duplicate confirmed check-ins');

$period = $statistics->periodStatistics(
    $student,
    new DateTimeImmutable('2026-08-25 00:00:00', new DateTimeZone('UTC')),
    new DateTimeImmutable('2026-08-26 00:00:00', new DateTimeZone('UTC')),
);
$assert(($period['hours'] ?? null) === 3.0, 'Period statistics use the same verified evidence contract');
$assert(($period['activities'] ?? null) === 1, 'Period attended count excludes no_show and unconfirmed check-ins');
$assert(($period['experience_buckets'][0]['hours'] ?? null) === 3.0, 'Daily buckets use verified evidence only');
$assert(($period['category_distribution'][0]['hours'] ?? null) === 3.0, 'Category distribution uses verified evidence only');

$activityData = (string) file_get_contents(dirname(__DIR__) . '/app/learner/includes/activity-data.php');
$assert(str_contains($activityData, "['pending', 'approved', 'waitlisted']"), 'Registered view contains only active registration states');
$assert(str_contains($activityData, "['attended', 'no_show']"), 'History contains only attendance-resolved states');
$assert(str_contains($activityData, "if ((\$registration['status'] ?? '') === 'no_show')"), 'History forces no_show evidence to zero hours');

$notificationService = (string) file_get_contents(dirname(__DIR__) . '/app/learner/data/Service/NotificationService.php');
foreach (['activity_registration_created', 'activity_registration_cancelled', 'activity_checkin_committed', 'activity_attendance_no_show'] as $type) {
    $assert(str_contains($notificationService, "'{$type}'"), "Learner notification contract allows {$type}");
}
$assert(str_contains($notificationService, "'/app/learner/activity-history.php'"), 'no_show notification may deep-link only to learner history');

$checkinRepository = (string) file_get_contents(dirname(__DIR__) . '/app/learner/data/Database/DatabaseCheckinRepository.php');
$assert(str_contains($checkinRepository, 'evaluateAndAward'), 'Badge award evaluation is wired to confirmed check-in transaction');
$assert(!str_contains((string) file_get_contents(dirname(__DIR__) . '/app/learner/data/Database/DatabaseActivityAttendanceReconciliationRepository.php'), 'evaluateAndAward'), 'no_show reconciliation never awards badges');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_cross_module_consistency_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_cross_module_consistency_test: OK\n";
