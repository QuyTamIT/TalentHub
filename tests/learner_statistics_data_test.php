<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\StatisticsService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create SQLite schema
$pdo->exec(<<<'SQL'
CREATE TABLE experience_logs (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL,
    activityId TEXT NOT NULL,
    checkinId TEXT NOT NULL,
    hours REAL NOT NULL,
    status TEXT NOT NULL,
    confirmedAt TEXT NULL
);

CREATE TABLE activities (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    category TEXT NOT NULL
);

CREATE TABLE activity_registrations (
    id TEXT PRIMARY KEY,
    activityId TEXT NOT NULL,
    studentId TEXT NOT NULL,
    status TEXT NOT NULL
);

CREATE TABLE checkins (
    id TEXT PRIMARY KEY,
    registrationId TEXT NOT NULL,
    status TEXT NOT NULL,
    confirmedAt TEXT NULL
);

CREATE TABLE talent_tests (
    id TEXT PRIMARY KEY,
    type TEXT NOT NULL
);

CREATE TABLE test_attempts (
    id TEXT PRIMARY KEY,
    testId TEXT NOT NULL,
    studentId TEXT NOT NULL,
    status TEXT NOT NULL,
    submittedAt TEXT NULL
);

CREATE TABLE test_results (
    id TEXT PRIMARY KEY,
    attemptId TEXT NOT NULL
);

CREATE TABLE assessments (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL,
    status TEXT NOT NULL,
    publishedAt TEXT NULL
);

CREATE TABLE student_badges (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL,
    badgeId TEXT NOT NULL,
    awardedAt TEXT NOT NULL
);
SQL);

$s1 = '11111111-1111-4111-8111-111111111111';
$s2 = '22222222-2222-4222-8222-222222222222';

// Populate Student 1 data
$pdo->exec("INSERT INTO activities VALUES ('act-1', 'Workshop AI', 'technology'), ('act-2', 'Career Fair', 'career'), ('act-3', 'Soft Skills', 'personal')");

// Experience logs: s1 has 10h confirmed (in Aug 2026), 5h confirmed (in Jul 2026), 3h pending
$pdo->exec("INSERT INTO experience_logs VALUES
    ('exp-1', '{$s1}', 'act-1', 'chk-1', 10.0, 'confirmed', '2026-08-15 10:00:00.000000'),
    ('exp-2', '{$s1}', 'act-2', 'chk-2', 5.0, 'confirmed', '2026-07-20 10:00:00.000000'),
    ('exp-3', '{$s1}', 'act-3', 'chk-3', 3.0, 'pending', '2026-08-16 10:00:00.000000')");

// Experience logs: s2 has 8h confirmed
$pdo->exec("INSERT INTO experience_logs VALUES
    ('exp-4', '{$s2}', 'act-1', 'chk-4', 8.0, 'confirmed', '2026-08-15 10:00:00.000000')");

// Registrations & Checkins
$pdo->exec("INSERT INTO activity_registrations VALUES
    ('reg-1', 'act-1', '{$s1}', 'attended'),
    ('reg-2', 'act-2', '{$s1}', 'attended'),
    ('reg-3', 'act-3', '{$s1}', 'approved'),
    ('reg-4', 'act-1', '{$s2}', 'attended')");

$pdo->exec("INSERT INTO checkins VALUES
    ('chk-1', 'reg-1', 'confirmed', '2026-08-15 10:00:00.000000'),
    ('chk-2', 'reg-2', 'confirmed', '2026-07-20 10:00:00.000000'),
    ('chk-3', 'reg-3', 'pending', '2026-08-16 10:00:00.000000'),
    ('chk-4', 'reg-4', 'confirmed', '2026-08-15 10:00:00.000000')");

// Talent tests & attempts
$pdo->exec("INSERT INTO talent_tests VALUES
    ('test-1', 'holland'),
    ('test-2', 'mbti'),
    ('test-3', 'disc')");

$pdo->exec("INSERT INTO test_attempts VALUES
    ('att-1', 'test-1', '{$s1}', 'submitted', '2026-08-10 09:00:00.000000'),
    ('att-2', 'test-2', '{$s1}', 'submitted', '2026-08-12 09:00:00.000000'),
    ('att-3', 'test-3', '{$s1}', 'in_progress', '2026-08-14 09:00:00.000000'),
    ('att-4', 'test-1', '{$s2}', 'submitted', '2026-08-10 09:00:00.000000')");

$pdo->exec("INSERT INTO test_results VALUES
    ('res-1', 'att-1'),
    ('res-2', 'att-2'),
    ('res-4', 'att-4')");

// Assessments (Teacher evaluations)
$pdo->exec("INSERT INTO assessments VALUES
    ('eval-1', '{$s1}', 'published', '2026-08-14 14:00:00.000000'),
    ('eval-2', '{$s1}', 'draft', NULL),
    ('eval-3', '{$s2}', 'published', '2026-08-14 14:00:00.000000')");

// Student badges
$pdo->exec("INSERT INTO student_badges VALUES
    ('sb-1', '{$s1}', 'badge-1', '2026-08-15 12:00:00.000000')");

$repo = new DatabaseStatisticsRepository($pdo);

// 1. Lifetime facts for Student 1
$facts1 = $repo->lifetimeFacts($s1);
$assert($facts1['confirmed_experience_hours'] === 15.0, 'Student 1 confirmed hours = 15.0 (excludes pending 3h)');
$assert($facts1['attended_activity_count'] === 2, 'Student 1 attended activities = 2 (excludes approved without confirmed checkin)');
$assert($facts1['submitted_assessment_type_count'] === 2, 'Student 1 distinct submitted test types = 2 (holland, mbti)');
$assert($facts1['published_teacher_evaluation_count'] === 1, 'Student 1 published evaluations = 1 (excludes draft)');

// 2. Lifetime facts for Student 2 (owner isolation)
$facts2 = $repo->lifetimeFacts($s2);
$assert($facts2['confirmed_experience_hours'] === 8.0, 'Student 2 confirmed hours = 8.0');
$assert($facts2['attended_activity_count'] === 1, 'Student 2 attended activities = 1');
$assert($facts2['submitted_assessment_type_count'] === 1, 'Student 2 distinct test types = 1');
$assert($facts2['published_teacher_evaluation_count'] === 1, 'Student 2 published evaluations = 1');

// 3. Period statistics for August 2026
$fromAug = new DateTimeImmutable('2026-08-01 00:00:00', new DateTimeZone('UTC'));
$toAug = new DateTimeImmutable('2026-09-01 00:00:00', new DateTimeZone('UTC'));
$augStats = $repo->periodStatistics($s1, $fromAug, $toAug);

$assert($augStats['hours'] === 10.0, 'August confirmed hours = 10.0 (excludes July 5.0h)');
$assert($augStats['activities'] === 1, 'August attended activities = 1');
$assert($augStats['assessments'] === 2, 'August submitted assessments = 2');
$assert($augStats['evaluations'] === 1, 'August published evaluations = 1');
$assert($augStats['badges'] === 1, 'August awarded badges = 1');
$assert(count($augStats['experience_buckets']) === 31, 'August has 31 daily buckets');
$assert($augStats['category_distribution'] === [['category' => 'technology', 'hours' => 10.0]], 'Category distribution has technology 10.0h');

// 4. StatisticsService tests
$clock = new DateTimeImmutable('2026-08-16 12:00:00', new DateTimeZone('UTC'));
$service = new StatisticsService($repo, $clock);

// Valid month period
$monthResult = $service->forStudentPeriod($s1, 'month');
$assert($monthResult['period']['id'] === 'month', 'Period ID is month');
$assert($monthResult['period']['label'] === 'Tháng này', 'Period label is Tháng này');
$assert(count($monthResult['kpis']) === 4, '4 KPIs present');
$assert($monthResult['kpis'][0]['value'] === 10.0, 'Hours KPI is 10.0');
$assert($monthResult['level']['name'] === 'Innovator', 'Level is Innovator with 15h lifetime');
$assert($monthResult['level']['currentHours'] === 15.0, 'Level current hours = 15.0');

// Valid week period (2026-08-16 is Sunday, Monday of that week was 2026-08-10)
$weekResult = $service->forStudentPeriod($s1, 'week');
$assert($weekResult['period']['id'] === 'week', 'Period ID is week');
$assert($weekResult['period']['label'] === 'Tuần này', 'Period label is Tuần này');
$assert(count($weekResult['experience']['hours']) === 7, 'Week has 7 daily points');

// Invalid period throws 422
$thrown = false;
try {
    $service->forStudentPeriod($s1, 'year');
} catch (ApiException $e) {
    $assert($e->status === 422, 'Invalid period status code 422');
    $thrown = true;
}
$assert($thrown, 'Invalid period throws ApiException');

echo "learner_statistics_data_test: OK\n";
