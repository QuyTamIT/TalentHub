<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

/** @var list<string> $failures */
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, audienceScope TEXT NOT NULL, filterCategory TEXT NOT NULL, locationName TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT NOT NULL, registrationClosesAt TEXT NOT NULL)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL)');
$pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');
$pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, activityId TEXT NOT NULL, checkinId TEXT NOT NULL, hours REAL NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');

$pdo->exec("INSERT INTO schools VALUES ('school-a', 'Trường A'), ('school-b', 'Trường B')");
$pdo->exec("INSERT INTO classes VALUES ('class-a', 'school-a'), ('class-b', 'school-b')");
$pdo->exec("INSERT INTO student_profiles VALUES ('student-a', 'class-a'), ('student-b', 'class-b')");

$activity = $pdo->prepare('INSERT INTO activities VALUES (?,?,?,?,?,?,?,?)');
$detail = $pdo->prepare('INSERT INTO activity_details VALUES (?,?,?,?)');
$policy = $pdo->prepare('INSERT INTO activity_registration_policies VALUES (?,?,?)');
$rows = [
    ['eligible', 'school-a', 'Eligible', 'career_technical', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'school_only', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
    ['pending-does-not-fill', 'school-a', 'Pending does not fill', 'career_business', '2026-08-27 10:00:00', '2026-08-27 12:00:00', 1, 'published', 'school_only', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
    ['cancelled-own', 'school-a', 'Cancelled own registration', 'career_arts', '2026-08-27 11:00:00', '2026-08-27 13:00:00', 2, 'published', 'school_only', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
    ['foreign', 'school-b', 'Foreign secret title', 'career_arts', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'school_only', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
    ['ongoing', 'school-a', 'Ongoing', 'career_technical', '2026-08-24 09:00:00', '2026-08-27 11:00:00', 2, 'ongoing', 'school_only', '2026-08-20 00:00:00', '2026-08-26 09:00:00'],
    ['legacy-active', 'school-a', 'Legacy active', 'career_technical', '2026-08-24 09:00:00', '2026-08-27 11:00:00', 2, 'active', 'school_only', '2026-08-20 00:00:00', '2026-08-26 09:00:00'],
    ['completed', 'school-a', 'Completed', 'career_technical', '2026-08-20 09:00:00', '2026-08-20 11:00:00', 2, 'completed', 'school_only', '2026-08-10 00:00:00', '2026-08-19 09:00:00'],
    ['closed', 'school-a', 'Closed registration', 'career_technical', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'school_only', '2026-08-20 00:00:00', '2026-08-25 12:00:00'],
    ['not-open', 'school-a', 'Not open', 'career_technical', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'school_only', '2026-08-25 12:00:01', '2026-08-26 09:00:00'],
    ['started', 'school-a', 'Already started', 'career_technical', '2026-08-25 11:59:59', '2026-08-25 14:00:00', 2, 'published', 'school_only', '2026-08-20 00:00:00', '2026-08-25 13:00:00'],
    ['public-scope', 'school-a', 'Wrong audience', 'career_technical', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'public', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
    ['full', 'school-a', 'Full', 'career_technical', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'school_only', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
    ['own-pending', 'school-a', 'Own pending', 'career_technical', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'school_only', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
    ['own-approved', 'school-a', 'Own approved', 'career_technical', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'school_only', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
    ['own-waitlisted', 'school-a', 'Own waitlisted', 'career_technical', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'school_only', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
    ['own-attended', 'school-a', 'Own attended', 'career_technical', '2026-08-27 09:00:00', '2026-08-27 11:00:00', 2, 'published', 'school_only', '2026-08-24 00:00:00', '2026-08-26 09:00:00'],
];
foreach ($rows as [$id, $schoolId, $title, $category, $start, $end, $capacity, $status, $scope, $opens, $closes]) {
    $activity->execute([$id, $schoolId, $title, $category, $start, $end, $capacity, $status]);
    $detail->execute([$id, $scope, 'Nhóm nghề', 'Phòng trải nghiệm']);
    $policy->execute([$id, $opens, $closes]);
}

$registration = $pdo->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?)');
foreach ([
    ['reg-pending-capacity', 'pending-does-not-fill', 'student-b', 'pending'],
    ['reg-cancelled-own', 'cancelled-own', 'student-a', 'cancelled'],
    ['reg-full-approved', 'full', 'student-b', 'approved'],
    ['reg-full-attended', 'full', 'student-c', 'attended'],
    ['reg-own-pending', 'own-pending', 'student-a', 'pending'],
    ['reg-own-approved', 'own-approved', 'student-a', 'approved'],
    ['reg-own-waitlisted', 'own-waitlisted', 'student-a', 'waitlisted'],
    ['reg-own-attended', 'own-attended', 'student-a', 'attended'],
] as $row) {
    $registration->execute($row);
}

$clock = new DateTimeImmutable('2026-08-25 12:00:00', new DateTimeZone('UTC'));
$opportunities = (new DatabaseOpportunitySource($pdo, $clock))->forStudent('student-a');
$activities = array_values(array_filter($opportunities, static fn (array $item): bool => ($item['opportunity_type'] ?? '') === 'activity'));
$assert(array_column($activities, 'opportunity_id') === ['cancelled-own', 'eligible', 'pending-does-not-fill'], 'AI discovery includes only eligible same-school activities; pending capacity and cancelled own registrations do not block');
$assert(!in_array('Foreign secret title', array_column($activities, 'title'), true), 'AI discovery never leaks foreign-school titles');
$assert(($activities[0]['location'] ?? null) === 'Phòng trải nghiệm', 'AI activity uses the scoped activity detail location');
$assert(($activities[0]['deadline_at'] ?? null) === '2026-08-26T09:00:00.000000+00:00', 'AI activity deadline is the registration close time');

$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-evidence-ok', 'eligible', 'student-a', 'attended')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-evidence-approved', 'pending-does-not-fill', 'student-a', 'approved')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-evidence-active', 'ongoing', 'student-a', 'attended')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-evidence-legacy', 'legacy-active', 'student-a', 'attended')");
$pdo->exec("INSERT INTO checkins VALUES ('checkin-ok', 'reg-evidence-ok', 'confirmed', '2026-08-28 10:00:00')");
$pdo->exec("INSERT INTO checkins VALUES ('checkin-approved', 'reg-evidence-approved', 'confirmed', '2026-08-28 10:00:00')");
$pdo->exec("INSERT INTO checkins VALUES ('checkin-active', 'reg-evidence-active', 'confirmed', '2026-08-28 10:00:00')");
$pdo->exec("INSERT INTO checkins VALUES ('checkin-legacy', 'reg-evidence-legacy', 'confirmed', '2026-08-28 10:00:00')");
$pdo->exec("INSERT INTO experience_logs VALUES ('experience-ok', 'student-a', 'eligible', 'checkin-ok', 3, 'confirmed', '2026-08-28 11:00:00')");
$pdo->exec("INSERT INTO experience_logs VALUES ('experience-approved', 'student-a', 'pending-does-not-fill', 'checkin-approved', 4, 'confirmed', '2026-08-28 11:00:00')");
$pdo->exec("INSERT INTO experience_logs VALUES ('experience-active', 'student-a', 'ongoing', 'checkin-active', 5, 'confirmed', '2026-08-28 11:00:00')");
$pdo->exec("INSERT INTO experience_logs VALUES ('experience-legacy', 'student-a', 'legacy-active', 'checkin-legacy', 6, 'confirmed', '2026-08-28 11:00:00')");
$evidence = (new DatabaseActivityExperienceSource($pdo))->forStudent('student-a');
$evidenceIds = array_column($evidence, 'experience_id');
sort($evidenceIds);
$assert($evidenceIds === ['experience-active', 'experience-ok'], 'AI evidence requires attended registration, confirmed aligned check-in/log, and a canonical activity status');

$incomplete = new PDO('sqlite::memory:');
$incomplete->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT NOT NULL)');
$incomplete->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)');
$incomplete->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
$incomplete->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, title TEXT NOT NULL, category TEXT NOT NULL, startAt TEXT NOT NULL, endAt TEXT NULL, capacity INTEGER NOT NULL, status TEXT NOT NULL)');
$assert((new DatabaseOpportunitySource($incomplete, $clock))->forStudent('student-a') === [], 'AI activity discovery fails closed when details, policy, or registration contracts are unavailable');

if ($failures !== []) {
    fwrite(STDERR, "learner_ai_activity_eligibility_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_ai_activity_eligibility_test: OK\n";
