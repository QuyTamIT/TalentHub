<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/api/JsonResponder.php';
require_once dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\StatisticsService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

echo "Running tests/learner_statistics_api_test.php..." . PHP_EOL;

// 1. Verify endpoint existence and static contracts
$endpointFile = dirname(__DIR__) . '/app/learner/api/v1/statistics.php';
$assert(file_exists($endpointFile), 'app/learner/api/v1/statistics.php exists');

$source = file_get_contents($endpointFile) ?: '';
$assert(str_contains($source, "METHOD_NOT_ALLOWED"), 'Endpoint enforces GET method only');
$assert(str_contains($source, "student_dashboard.read_own"), 'Endpoint requires student_dashboard.read_own permission');
$assert(!str_contains($source, "student_profile.read_own"), 'Statistics endpoint does not substitute profile permission for dashboard ownership.');
$assert(str_contains($source, "statisticsService"), 'Endpoint uses statisticsService');
$assert(str_contains($source, "ALLOWED_PERIODS"), 'Endpoint validates period allow-list');
$assert(str_contains($source, "array_diff(array_keys(\$request->queryParams()), ['period'])"), 'Endpoint rejects unknown query parameters');

// 2. Behavioral tests with StatisticsService and DatabaseStatisticsRepository
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT, category TEXT);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT);
CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT, status TEXT, confirmedAt TEXT);
CREATE TABLE talent_tests (id TEXT PRIMARY KEY, type TEXT);
CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT, studentId TEXT, status TEXT, submittedAt TEXT);
CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT);
CREATE TABLE assessments (id TEXT PRIMARY KEY, studentId TEXT, status TEXT, publishedAt TEXT);
CREATE TABLE student_badges (id TEXT PRIMARY KEY, studentId TEXT, badgeId TEXT, awardedAt TEXT);
SQL);

$s1 = '11111111-1111-4111-8111-111111111111';
$s2 = '22222222-2222-4222-8222-222222222222';

$pdo->exec("INSERT INTO activities VALUES ('act-1', 'Workshop', 'technology')");
$pdo->exec("INSERT INTO experience_logs VALUES ('exp-1', '{$s1}', 'act-1', 'chk-1', 12.0, 'confirmed', '2026-08-15 10:00:00.000000')");
$pdo->exec("INSERT INTO experience_logs VALUES ('exp-2', '{$s2}', 'act-1', 'chk-2', 5.0, 'confirmed', '2026-08-15 10:00:00.000000')");

$repo = new DatabaseStatisticsRepository($pdo);
$clock = new DateTimeImmutable('2026-08-16 12:00:00', new DateTimeZone('UTC'));
$service = new StatisticsService($repo, $clock);

// Month view
$monthRes = $service->forStudentPeriod($s1, 'month');
$assert($monthRes['period']['id'] === 'month', 'Period ID is month');
$assert($monthRes['kpis'][0]['value'] === 12.0, 'Student 1 hours = 12.0');
$assert($monthRes['level']['name'] === 'Innovator', 'Student 1 is Innovator');

// Owner isolation
$monthRes2 = $service->forStudentPeriod($s2, 'month');
$assert($monthRes2['kpis'][0]['value'] === 5.0, 'Student 2 hours = 5.0');
$assert($monthRes2['level']['name'] === 'Explorer', 'Student 2 is Explorer');

// Week view
$weekRes = $service->forStudentPeriod($s1, 'week');
$assert($weekRes['period']['id'] === 'week', 'Period ID is week');
$assert(count($weekRes['experience']['hours']) === 7, '7 days in week view');

echo "learner_statistics_api_test: OK\n";
