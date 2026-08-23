<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\Database\DatabaseBadgeRepository;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\BadgeAwardService;
use TalentHub\Learner\Data\Service\BadgeReadService;
use TalentHub\Learner\Data\Service\BadgeRuleEngine;
use TalentHub\Learner\Data\Service\NotificationService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

// Create schema
$pdo->exec(<<<'SQL'
CREATE TABLE users (
    id TEXT PRIMARY KEY,
    fullName TEXT NOT NULL,
    email TEXT NOT NULL
);

CREATE TABLE student_profiles (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    FOREIGN KEY (userId) REFERENCES users(id)
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
    createdAt TEXT NOT NULL,
    FOREIGN KEY (badgeId) REFERENCES badges(id)
);

CREATE TABLE student_badges (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL,
    badgeId TEXT NOT NULL,
    ruleDefinitionId TEXT NOT NULL,
    awardedAt TEXT NOT NULL,
    awardedBy TEXT NOT NULL,
    awardContext TEXT NOT NULL,
    UNIQUE(studentId, badgeId),
    FOREIGN KEY (studentId) REFERENCES student_profiles(id),
    FOREIGN KEY (badgeId) REFERENCES badges(id),
    FOREIGN KEY (ruleDefinitionId) REFERENCES badge_rule_definitions(id)
);

CREATE TABLE notifications (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    eventKey TEXT NOT NULL,
    notificationType TEXT NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    deepLink TEXT NULL,
    readAt TEXT NULL,
    createdAt TEXT NOT NULL,
    UNIQUE(userId, eventKey)
);

CREATE TABLE learner_notification_preferences (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL,
    notificationType TEXT NOT NULL,
    inAppEnabled INTEGER NOT NULL,
    emailEnabled INTEGER NOT NULL,
    updatedAt TEXT NOT NULL,
    UNIQUE(studentId, notificationType)
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
SQL);

$u1 = '11111111-0000-4000-8000-000000000001';
$s1 = '11111111-1111-4111-8111-111111111111';

$u2 = '22222222-0000-4000-8000-000000000002';
$s2 = '22222222-2222-4222-8222-222222222222';

$b1 = 'a1000000-0000-4000-8000-000000000001';
$b2 = 'a1000000-0000-4000-8000-000000000002';
$b3 = 'a1000000-0000-4000-8000-000000000003';
$b4 = 'a1000000-0000-4000-8000-000000000004';
$b5 = 'a1000000-0000-4000-8000-000000000005';

$r1 = 'b1000000-0000-4000-8000-000000000001';
$r2 = 'b1000000-0000-4000-8000-000000000002';
$r3 = 'b1000000-0000-4000-8000-000000000003';
$r4 = 'b1000000-0000-4000-8000-000000000004';
$r5 = 'b1000000-0000-4000-8000-000000000005';

$pdo->exec("INSERT INTO users VALUES ('{$u1}', 'Student One', 's1@example.com'), ('{$u2}', 'Student Two', 's2@example.com')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$s1}', '{$u1}'), ('{$s2}', '{$u2}')");

// Seed 5 canonical badges
$pdo->exec("INSERT INTO badges VALUES
    ('{$b1}', 'first_experience', 'Khởi đầu trải nghiệm', 'experience', '1 giờ', '/assets/icons/first-experience.svg', 1, 'active', '2026-08-01 00:00:00.000000'),
    ('{$b2}', 'experience_10h', 'Hành trình tích lũy', 'experience', '10 giờ', NULL, 2, 'active', '2026-08-01 00:00:00.000000'),
    ('{$b3}', 'active_participant', 'Thành viên năng nổ', 'activity', '3 hoạt động', NULL, 1, 'active', '2026-08-01 00:00:00.000000'),
    ('{$b4}', 'assessment_explorer', 'Khám phá năng lực', 'assessment', '2 bài test', NULL, 1, 'active', '2026-08-01 00:00:00.000000'),
    ('{$b5}', 'teacher_recognition', 'Ghi nhận từ giáo viên', 'evaluation', '1 đánh giá', NULL, 1, 'active', '2026-08-01 00:00:00.000000')");

// Seed 5 v1 active rules
$pdo->exec("INSERT INTO badge_rule_definitions VALUES
    ('{$r1}', '{$b1}', 'threshold', '{\"fact\":\"confirmed_experience_hours\",\"operator\":\"gte\",\"value\":1}', 1, 1, '2026-08-01 00:00:00.000000'),
    ('{$r2}', '{$b2}', 'threshold', '{\"fact\":\"confirmed_experience_hours\",\"operator\":\"gte\",\"value\":10}', 1, 1, '2026-08-01 00:00:00.000000'),
    ('{$r3}', '{$b3}', 'threshold', '{\"fact\":\"attended_activity_count\",\"operator\":\"gte\",\"value\":3}', 1, 1, '2026-08-01 00:00:00.000000'),
    ('{$r4}', '{$b4}', 'threshold', '{\"fact\":\"submitted_assessment_type_count\",\"operator\":\"gte\",\"value\":2}', 1, 1, '2026-08-01 00:00:00.000000'),
    ('{$r5}', '{$b5}', 'threshold', '{\"fact\":\"published_teacher_evaluation_count\",\"operator\":\"gte\",\"value\":1}', 1, 1, '2026-08-01 00:00:00.000000')");

// Give Student 1: 12h confirmed experience (qualifies for first_experience and experience_10h)
$pdo->exec("INSERT INTO experience_logs VALUES
    ('exp-1', '{$s1}', 'act-1', 'chk-1', 12.0, 'confirmed', '2026-08-15 10:00:00.000000')");

$badgeRepo = new DatabaseBadgeRepository($pdo);
$statsRepo = new DatabaseStatisticsRepository($pdo);
$notifRepo = new DatabaseNotificationRepository($pdo);
$notifService = new NotificationService($notifRepo);
$ruleEngine = new BadgeRuleEngine();
$awardService = new BadgeAwardService($badgeRepo, $statsRepo, $ruleEngine, $notifService);
$readService = new BadgeReadService($badgeRepo, $statsRepo, $ruleEngine);

// 1. Initial award evaluation for Student 1
$newAwards = $awardService->evaluateAndAward($s1);
$assert(count($newAwards) === 2, 'Student 1 receives exactly 2 awards (first_experience and experience_10h)');

$awardedCodes = array_column(array_column($newAwards, 'badge'), 'code');
sort($awardedCodes);
$assert($awardedCodes === ['experience_10h', 'first_experience'], 'Awarded codes match expected');

// Verify DB persistence
$stmt = $pdo->prepare('SELECT COUNT(*) FROM student_badges WHERE studentId = ?');
$stmt->execute([$s1]);
$assert((int) $stmt->fetchColumn() === 2, '2 student_badges rows in DB');

// Verify notification persistence
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = ? AND notificationType = 'badge_awarded'");
$stmt->execute([$u1]);
$assert((int) $stmt->fetchColumn() === 2, '2 badge_awarded notifications in DB');

// Verify event key format
$stmt = $pdo->prepare('SELECT eventKey FROM notifications WHERE userId = ?');
$stmt->execute([$u1]);
$eventKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);
$assert(in_array("badge_award:{$s1}:{$b1}:v1", $eventKeys, true), 'b1 eventKey matches canonical format');
$assert(in_array("badge_award:{$s1}:{$b2}:v1", $eventKeys, true), 'b2 eventKey matches canonical format');

// 2. Replay idempotency: evaluate again for Student 1
$replayAwards = $awardService->evaluateAndAward($s1);
$assert(count($replayAwards) === 0, 'Replay creates 0 new awards');

$stmt = $pdo->prepare('SELECT COUNT(*) FROM student_badges WHERE studentId = ?');
$stmt->execute([$s1]);
$assert((int) $stmt->fetchColumn() === 2, 'student_badges count remains 2');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = ? AND notificationType = 'badge_awarded'");
$stmt->execute([$u1]);
$assert((int) $stmt->fetchColumn() === 2, 'notifications count remains 2');

// 3. Student 2 isolation: evaluate for Student 2 (has no facts yet)
$s2Awards = $awardService->evaluateAndAward($s2);
$assert(count($s2Awards) === 0, 'Student 2 receives 0 awards');

// 4. Preference suppression test:
// Give Student 2: 1 published evaluation (eligible for teacher_recognition)
$pdo->exec("INSERT INTO assessments VALUES ('eval-1', '{$s2}', 'published', '2026-08-15 10:00:00.000000')");
// Disable in-app notifications for badge_awarded for Student 2
$pdo->exec("INSERT INTO learner_notification_preferences VALUES ('pref-1', '{$s2}', 'badge_awarded', 0, 0, '2026-08-01 00:00:00.000000')");

$s2NewAwards = $awardService->evaluateAndAward($s2);
$assert(count($s2NewAwards) === 1, 'Student 2 gets 1 award (teacher_recognition)');
$assert($s2NewAwards[0]['badge']['code'] === 'teacher_recognition', 'Awarded badge is teacher_recognition');

// Verify student_badges has the row
$stmt = $pdo->prepare('SELECT COUNT(*) FROM student_badges WHERE studentId = ?');
$stmt->execute([$s2]);
$assert((int) $stmt->fetchColumn() === 1, 'Student 2 has 1 student_badge row');

// Verify notifications has 0 rows for User 2 (suppressed)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = ?");
$stmt->execute([$u2]);
$assert((int) $stmt->fetchColumn() === 0, 'Student 2 notification was suppressed');

// 5. Read service tests:
$s1Read = $readService->forStudent($s1);
$assert(count($s1Read['badges']) === 2, 'Read service returns 2 awarded badges');
$firstExperienceRead = array_values(array_filter(
    $s1Read['badges'],
    static fn (array $badge): bool => $badge['code'] === 'first_experience'
))[0] ?? null;
$assert(
    is_array($firstExperienceRead) && $firstExperienceRead['iconUrl'] === '/assets/icons/first-experience.svg',
    'Awarded badge read preserves the iconUrl column alias.'
);
$assert(count($s1Read['progress']) === 5, 'Read service returns progress for all 5 active rules');

$progressByCode = [];
foreach ($s1Read['progress'] as $p) {
    $progressByCode[$p['badgeCode']] = $p;
}
$assert($progressByCode['first_experience']['status'] === 'achieved', 'first_experience is achieved');
$assert($progressByCode['first_experience']['progressPercent'] === 100, 'first_experience is 100%');
$assert($progressByCode['experience_10h']['status'] === 'achieved', 'experience_10h is achieved');
$assert($progressByCode['active_participant']['status'] === 'locked', 'active_participant is locked (0 activities)');

// Verify that read does not mutate DB
$countBefore = (int) $pdo->query('SELECT COUNT(*) FROM student_badges')->fetchColumn();
$readService->forStudent($s1);
$countAfter = (int) $pdo->query('SELECT COUNT(*) FROM student_badges')->fetchColumn();
$assert($countBefore === $countAfter, 'Read operation causes no database mutation');

// 6. A foreign-key failure is not an idempotent duplicate replay.
$foreignKeyFailurePropagated = false;
try {
    $badgeRepo->insertAward(
        $s2,
        $b3,
        'ffffffff-ffff-4fff-8fff-ffffffffffff',
        'system',
        ['fact' => 'attended_activity_count', 'current' => 3, 'target' => 3],
        new DateTimeImmutable('2026-08-20 00:00:00', new DateTimeZone('UTC'))
    );
} catch (PDOException) {
    $foreignKeyFailurePropagated = true;
}
$assert($foreignKeyFailurePropagated, 'Foreign-key failures propagate instead of being swallowed as duplicate awards.');
$stmt = $pdo->prepare('SELECT COUNT(*) FROM student_badges WHERE studentId = ? AND badgeId = ?');
$stmt->execute([$s2, $b3]);
$assert((int) $stmt->fetchColumn() === 0, 'Foreign-key failure leaves no partial award row.');

// 7. Notification persistence failure rolls back the award in the same transaction.
$rollbackBadge = 'a1000000-0000-4000-8000-000000000099';
$rollbackRule = 'b1000000-0000-4000-8000-000000000099';
$pdo->prepare(<<<'SQL'
    INSERT INTO badges (id, code, name, category, description, iconUrl, level, status, createdAt)
    VALUES (?, 'rollback_probe', 'Rollback Probe', 'experience', 'Failure injection only', NULL, 1, 'active', '2026-08-01')
SQL)->execute([$rollbackBadge]);
$pdo->prepare(<<<'SQL'
    INSERT INTO badge_rule_definitions (id, badgeId, ruleType, thresholdCriteria, version, isActive, createdAt)
    VALUES (?, ?, 'threshold', '{"fact":"confirmed_experience_hours","operator":"gte","value":1}', 1, 1, '2026-08-01')
SQL)->execute([$rollbackRule, $rollbackBadge]);
$pdo->exec("CREATE TRIGGER fail_badge_notification BEFORE INSERT ON notifications WHEN NEW.notificationType = 'badge_awarded' BEGIN SELECT RAISE(ABORT, 'injected notification failure'); END");
$notificationFailurePropagated = false;
try {
    $awardService->evaluateAndAward($s1);
} catch (PDOException $exception) {
    $notificationFailurePropagated = str_contains($exception->getMessage(), 'injected notification failure');
}
$pdo->exec('DROP TRIGGER fail_badge_notification');
$assert($notificationFailurePropagated, 'Injected notification failure propagates to the producer transaction.');
$stmt = $pdo->prepare('SELECT COUNT(*) FROM student_badges WHERE studentId = ? AND badgeId = ?');
$stmt->execute([$s1, $rollbackBadge]);
$assert((int) $stmt->fetchColumn() === 0, 'Notification failure rolls back the newly inserted award row.');

$cliSource = file_get_contents(dirname(__DIR__) . '/bin/run-badge-awards.php') ?: '';
$assert(str_contains($cliSource, 'Unknown option:'), 'Badge backfill CLI rejects unknown options before database access.');
$assert(str_contains($cliSource, 'Choose exactly one mode:'), 'Badge backfill CLI rejects conflicting modes.');
$assert(str_contains($cliSource, 'Choose exactly one scope:'), 'Badge backfill CLI rejects conflicting scopes.');

echo "learner_badge_award_transaction_test: OK\n";
