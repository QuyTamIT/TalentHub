<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/api/JsonResponder.php';
require_once dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Data\Database\DatabaseBadgeRepository;
use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\BadgeReadService;
use TalentHub\Learner\Data\Service\BadgeRuleEngine;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

echo "Running tests/learner_badges_api_test.php..." . PHP_EOL;

// 1. Verify endpoint existence and static contracts
$endpointFile = dirname(__DIR__) . '/app/learner/api/v1/badges.php';
$assert(file_exists($endpointFile), 'app/learner/api/v1/badges.php exists');

$source = file_get_contents($endpointFile) ?: '';
$assert(str_contains($source, "METHOD_NOT_ALLOWED"), 'Endpoint enforces GET method only');
$assert(str_contains($source, "badge.read_own"), 'Endpoint requires badge.read_own permission');
$assert(!str_contains($source, "student_profile.read_own"), 'Badge endpoint does not substitute profile permission for badge ownership.');
$assert(str_contains($source, "badgeReadService"), 'Endpoint uses badgeReadService for read-only retrieval');
$assert(!str_contains($source, "evaluateAndAward"), 'Endpoint NEVER calls evaluateAndAward');

// 2. Behavioral test on SQLite
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec(<<<'SQL'
CREATE TABLE users (
    id TEXT PRIMARY KEY,
    fullName TEXT NOT NULL,
    email TEXT NOT NULL
);

CREATE TABLE student_profiles (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL
);

CREATE TABLE badges (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    description TEXT NOT NULL,
    iconUrl TEXT NULL,
    level INTEGER NOT NULL,
    status TEXT NOT NULL,
    createdAt TEXT NOT NULL
);

CREATE TABLE badge_rule_definitions (
    id TEXT PRIMARY KEY,
    badgeId TEXT NOT NULL,
    ruleType TEXT NOT NULL,
    thresholdCriteria TEXT NOT NULL,
    version INTEGER NOT NULL,
    isActive INTEGER NOT NULL,
    createdAt TEXT NOT NULL
);

CREATE TABLE student_badges (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL,
    badgeId TEXT NOT NULL,
    ruleDefinitionId TEXT NOT NULL,
    awardedAt TEXT NOT NULL,
    awardedBy TEXT NOT NULL,
    awardContext TEXT NOT NULL,
    UNIQUE(studentId, badgeId)
);

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
SQL);

$u1 = '11111111-0000-4000-8000-000000000001';
$s1 = '11111111-1111-4111-8111-111111111111';
$u2 = '22222222-0000-4000-8000-000000000002';
$s2 = '22222222-2222-4222-8222-222222222222';

$pdo->exec("INSERT INTO users VALUES ('{$u1}', 'Student 1', 's1@example.com'), ('{$u2}', 'Student 2', 's2@example.com')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$s1}', '{$u1}'), ('{$s2}', '{$u2}')");

$b1 = 'a1000000-0000-4000-8000-000000000001';
$r1 = 'b1000000-0000-4000-8000-000000000001';

$pdo->exec("INSERT INTO badges VALUES ('{$b1}', 'first_experience', 'Khởi đầu trải nghiệm', 'experience', '1 giờ', NULL, 1, 'active', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO badge_rule_definitions VALUES ('{$r1}', '{$b1}', 'threshold', '{\"fact\":\"confirmed_experience_hours\",\"operator\":\"gte\",\"value\":1}', 1, 1, '2026-08-01 00:00:00')");

// Award b1 to Student 1 only
$pdo->exec("INSERT INTO student_badges VALUES ('sb-1', '{$s1}', '{$b1}', '{$r1}', '2026-08-15 10:00:00', 'system', '{}')");

$badgeRepo = new DatabaseBadgeRepository($pdo);
$statsRepo = new DatabaseStatisticsRepository($pdo);
$readService = new BadgeReadService($badgeRepo, $statsRepo, new BadgeRuleEngine());

// Student 1 view
$res1 = $readService->forStudent($s1);
$assert(count($res1['badges']) === 1, 'Student 1 has 1 awarded badge');
$assert($res1['badges'][0]['code'] === 'first_experience', 'Badge code is first_experience');
$assert($res1['progress'][0]['status'] === 'achieved', 'Status is achieved for Student 1');

// Student 2 view (Owner isolation)
$res2 = $readService->forStudent($s2);
$assert(count($res2['badges']) === 0, 'Student 2 has 0 awarded badges');
$assert($res2['progress'][0]['status'] === 'locked', 'Status is locked for Student 2');

echo "learner_badges_api_test: OK\n";
