<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Database\DatabaseBadgeRepository;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(<<<'SQL'
CREATE TABLE schools (id TEXT PRIMARY KEY);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL);
CREATE TABLE badges (id TEXT PRIMARY KEY, schoolId TEXT NULL, code TEXT, name TEXT, category TEXT, description TEXT, iconUrl TEXT NULL, level INTEGER, status TEXT, createdAt TEXT);
CREATE TABLE badge_rule_definitions (id TEXT PRIMARY KEY, badgeId TEXT NOT NULL, ruleType TEXT, thresholdCriteria TEXT, version INTEGER, isActive INTEGER);
CREATE TABLE student_badges (id TEXT PRIMARY KEY, studentId TEXT, badgeId TEXT, ruleDefinitionId TEXT, awardedAt TEXT, awardedBy TEXT, awardContext TEXT, UNIQUE(studentId, badgeId));
SQL);

$schoolA = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$schoolB = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$classA = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$user = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
$student = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
$badge = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
$rule = '11111111-1111-4111-8111-111111111111';

$pdo->exec("INSERT INTO schools VALUES ('{$schoolA}'), ('{$schoolB}')");
$pdo->exec("INSERT INTO classes VALUES ('{$classA}', '{$schoolA}')");
$pdo->exec("INSERT INTO users VALUES ('{$user}', 'Student A')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$student}', '{$user}', '{$classA}')");
$pdo->exec("INSERT INTO badges VALUES ('{$badge}', '{$schoolB}', 'school_b_badge', 'Badge B', 'assessment', 'Wrong school badge', NULL, 1, 'active', '2026-08-26 00:00:00')");
$pdo->exec("INSERT INTO badge_rule_definitions VALUES ('{$rule}', '{$badge}', 'threshold', '{\"fact\":\"submitted_assessment_type_count\",\"operator\":\"gte\",\"value\":4}', 1, 1)");

$blocked = false;
try {
    (new DatabaseBadgeRepository($pdo))->insertAward(
        $student,
        $badge,
        $rule,
        'system',
        ['source' => 'scope-test'],
        new DateTimeImmutable('2026-08-26 00:00:00', new DateTimeZone('UTC'))
    );
} catch (RuntimeException) {
    $blocked = true;
}

$assert($blocked, 'cross-school badge award is rejected at repository write boundary');
$assert((int) $pdo->query('SELECT COUNT(*) FROM student_badges')->fetchColumn() === 0, 'rejected cross-school award is not persisted');
echo "learner_school_credential_award_scope_test: OK\n";
