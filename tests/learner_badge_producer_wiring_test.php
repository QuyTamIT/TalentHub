<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Data\Database\DatabaseAssessmentWriteRepository;
use TalentHub\Learner\Data\Database\DatabaseCheckinRepository;
use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Support\Uuid;

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
CREATE TABLE users (
    id TEXT PRIMARY KEY,
    fullName TEXT NOT NULL,
    email TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active'
);

CREATE TABLE schools (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active'
);

CREATE TABLE teacher_profiles (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    schoolId TEXT NOT NULL,
    isSchoolAdmin INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE student_profiles (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    studyStatus TEXT NOT NULL DEFAULT 'studying'
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

CREATE TABLE activities (
    id TEXT PRIMARY KEY,
    title TEXT NOT NULL,
    category TEXT NOT NULL,
    startAt TEXT NOT NULL,
    endAt TEXT NOT NULL,
    capacity INTEGER NOT NULL DEFAULT 100,
    status TEXT NOT NULL,
    createdByTeacherId TEXT NOT NULL,
    createdAt TEXT NOT NULL
);

CREATE TABLE activity_qr_sessions (
    id TEXT PRIMARY KEY,
    activityId TEXT NOT NULL,
    tokenHash TEXT NOT NULL UNIQUE,
    sessionType TEXT NOT NULL DEFAULT 'checkin',
    status TEXT NOT NULL DEFAULT 'active',
    maxScans INTEGER NOT NULL DEFAULT 100,
    usedScans INTEGER NOT NULL DEFAULT 0,
    expiresAt TEXT NOT NULL,
    revokedAt TEXT NULL,
    createdByTeacherId TEXT NOT NULL,
    createdAt TEXT NOT NULL,
    updatedAt TEXT NOT NULL
);

CREATE TABLE activity_experience_policies (
    activityId TEXT PRIMARY KEY,
    confirmedHours REAL NOT NULL,
    createdAt TEXT NOT NULL,
    updatedAt TEXT NOT NULL
);

CREATE TABLE activity_registrations (
    id TEXT PRIMARY KEY,
    activityId TEXT NOT NULL,
    studentId TEXT NOT NULL,
    status TEXT NOT NULL,
    registeredAt TEXT NOT NULL,
    updatedAt TEXT NOT NULL
);

CREATE TABLE checkins (
    id TEXT PRIMARY KEY,
    registrationId TEXT NOT NULL UNIQUE,
    qrSessionId TEXT NOT NULL,
    checkedInAt TEXT NOT NULL,
    confirmedAt TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'confirmed',
    createdAt TEXT NOT NULL
);

CREATE TABLE experience_logs (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL,
    activityId TEXT NOT NULL,
    checkinId TEXT NOT NULL,
    hours REAL NOT NULL,
    status TEXT NOT NULL,
    auditReason TEXT NULL,
    confirmedAt TEXT NULL,
    createdAt TEXT NOT NULL
);

CREATE TABLE audit_logs (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    action TEXT NOT NULL,
    entityType TEXT NOT NULL,
    entityId TEXT NOT NULL,
    requestId TEXT NULL,
    ipAddress TEXT NULL,
    metadata TEXT NULL,
    createdAt TEXT NOT NULL
);

CREATE TABLE talent_tests (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    category TEXT NOT NULL
);

CREATE TABLE assessment_versions (
    id TEXT PRIMARY KEY,
    testId TEXT NOT NULL,
    version INTEGER NOT NULL,
    scoringVersion TEXT NOT NULL,
    schemaHash TEXT NOT NULL,
    status TEXT NOT NULL
);

CREATE TABLE test_attempts (
    id TEXT PRIMARY KEY,
    testId TEXT NOT NULL,
    studentId TEXT NOT NULL,
    status TEXT NOT NULL,
    versionId TEXT NOT NULL,
    score REAL NULL,
    summary TEXT NULL,
    startedAt TEXT NOT NULL,
    submittedAt TEXT NULL,
    updatedAt TEXT NOT NULL
);

CREATE TABLE learner_assessment_attempt_metadata (
    id TEXT PRIMARY KEY,
    attemptId TEXT NOT NULL UNIQUE,
    studentId TEXT NOT NULL,
    assessmentCode TEXT NOT NULL,
    educationBand TEXT NOT NULL,
    assessmentVersion INTEGER NOT NULL,
    scoringVersion TEXT NOT NULL,
    schemaHash TEXT NOT NULL,
    status TEXT NOT NULL,
    startedAt TEXT NOT NULL,
    submittedAt TEXT NULL,
    inputHash TEXT NULL,
    updatedAt TEXT NOT NULL
);

CREATE TABLE test_results (
    id TEXT PRIMARY KEY,
    attemptId TEXT NOT NULL UNIQUE,
    scores JSON NOT NULL,
    traits JSON NOT NULL,
    recommendations JSON NOT NULL,
    calculatedAt TEXT NOT NULL
);

CREATE TABLE assessments (
    id TEXT PRIMARY KEY,
    teacherId TEXT NOT NULL,
    studentId TEXT NOT NULL,
    activityId TEXT NOT NULL,
    overallScore REAL NOT NULL,
    comment TEXT NOT NULL,
    status TEXT NOT NULL,
    publishedAt TEXT NULL,
    version INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE assessment_scores (
    id TEXT PRIMARY KEY,
    assessmentId TEXT NOT NULL,
    criteriaId TEXT NOT NULL,
    score REAL NOT NULL,
    UNIQUE(assessmentId, criteriaId)
);
SQL);

$u1 = '11111111-0000-4000-8000-000000000001';
$s1 = '11111111-1111-4111-8111-111111111111';
$uTeacher = '22222222-0000-4000-8000-000000000002';
$t1 = '22222222-2222-4222-8222-222222222222';
$schoolId = '33333333-3333-4333-8333-333333333333';

$pdo->exec("INSERT INTO users VALUES ('{$u1}', 'Student One', 's1@example.com', 'active'), ('{$uTeacher}', 'Teacher One', 't1@example.com', 'active')");
$pdo->exec("INSERT INTO schools VALUES ('{$schoolId}', 'Talent High School', 'active')");
$pdo->exec("INSERT INTO teacher_profiles VALUES ('{$t1}', '{$uTeacher}', '{$schoolId}', 0)");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$s1}', '{$u1}', 'studying')");

// Seed 5 canonical badges & rules
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

$pdo->exec("INSERT INTO badges VALUES
    ('{$b1}', 'first_experience', 'Khởi đầu trải nghiệm', 'experience', '1 giờ', NULL, 1, 'active', '2026-08-01 00:00:00.000000'),
    ('{$b2}', 'experience_10h', 'Hành trình tích lũy', 'experience', '10 giờ', NULL, 2, 'active', '2026-08-01 00:00:00.000000'),
    ('{$b3}', 'active_participant', 'Thành viên năng nổ', 'activity', '3 hoạt động', NULL, 1, 'active', '2026-08-01 00:00:00.000000'),
    ('{$b4}', 'assessment_explorer', 'Khám phá năng lực', 'assessment', '2 bài test', NULL, 1, 'active', '2026-08-01 00:00:00.000000'),
    ('{$b5}', 'teacher_recognition', 'Ghi nhận từ giáo viên', 'evaluation', '1 đánh giá', NULL, 1, 'active', '2026-08-01 00:00:00.000000')");

$pdo->exec("INSERT INTO badge_rule_definitions VALUES
    ('{$r1}', '{$b1}', 'threshold', '{\"fact\":\"confirmed_experience_hours\",\"operator\":\"gte\",\"value\":1}', 1, 1, '2026-08-01 00:00:00.000000'),
    ('{$r2}', '{$b2}', 'threshold', '{\"fact\":\"confirmed_experience_hours\",\"operator\":\"gte\",\"value\":10}', 1, 1, '2026-08-01 00:00:00.000000'),
    ('{$r3}', '{$b3}', 'threshold', '{\"fact\":\"attended_activity_count\",\"operator\":\"gte\",\"value\":3}', 1, 1, '2026-08-01 00:00:00.000000'),
    ('{$r4}', '{$b4}', 'threshold', '{\"fact\":\"submitted_assessment_type_count\",\"operator\":\"gte\",\"value\":2}', 1, 1, '2026-08-01 00:00:00.000000'),
    ('{$r5}', '{$b5}', 'threshold', '{\"fact\":\"published_teacher_evaluation_count\",\"operator\":\"gte\",\"value\":1}', 1, 1, '2026-08-01 00:00:00.000000')");

// TEST 1: Checkin Producer wiring
$actId = 'c1000000-0000-4000-8000-000000000001';
$regId = 'c2000000-0000-4000-8000-000000000001';
$sessionId = 'c3000000-0000-4000-8000-000000000001';
$tokenHash = hash('sha256', 'valid-qr-token-1');

$pdo->exec("INSERT INTO activities VALUES ('{$actId}', 'AI Workshop', 'technology', '2026-08-15 08:00:00', '2026-08-15 12:00:00', 50, 'ongoing', '{$t1}', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activity_experience_policies VALUES ('{$actId}', 2.0, '2026-08-01 00:00:00', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('{$regId}', '{$actId}', '{$s1}', 'approved', '2026-08-01 00:00:00', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activity_qr_sessions VALUES ('{$sessionId}', '{$actId}', '{$tokenHash}', 'checkin', 'active', 100, 0, '2026-08-25 18:00:00', NULL, '{$t1}', '2026-08-01 00:00:00', '2026-08-01 00:00:00')");

$checkinRepo = new DatabaseCheckinRepository($pdo);
$checkinResult = $checkinRepo->createConfirmed($s1, $u1, 'req-001', $tokenHash);
$assert(is_array($checkinResult), 'Checkin created successfully');

// Assert that first_experience badge was awarded (2 hours >= 1 hour threshold)
$stmt = $pdo->prepare('SELECT COUNT(*) FROM student_badges WHERE studentId = ? AND badgeId = ?');
$stmt->execute([$s1, $b1]);
$assert((int) $stmt->fetchColumn() === 1, 'Check-in producer atomically awarded first_experience badge');

// Assert badge notification was published
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = ? AND eventKey = ?");
$stmt->execute([$u1, "badge_award:{$s1}:{$b1}:v1"]);
$assert((int) $stmt->fetchColumn() === 1, 'Badge notification published during checkin');

// TEST 2: Teacher Grading Producer wiring
// First save as draft -> should NOT award teacher_recognition
$teacherRepo = new TeacherGradingRepository($pdo);
$teacherRepo->saveAssessment($t1, $s1, $actId, null, 0, '8.5', 'Good work in draft', 'draft', null, [['criteriaId' => 'crit-1', 'score' => '8.5']]);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM student_badges WHERE studentId = ? AND badgeId = ?');
$stmt->execute([$s1, $b5]);
$assert((int) $stmt->fetchColumn() === 0, 'Draft grading does NOT award teacher_recognition');

// Now publish grading -> should award teacher_recognition
$stmt = $pdo->prepare('SELECT id FROM assessments WHERE teacherId = ? AND studentId = ? AND activityId = ?');
$stmt->execute([$t1, $s1, $actId]);
$assessmentId = (string) $stmt->fetchColumn();

$teacherRepo->saveAssessment($t1, $s1, $actId, $assessmentId, 1, '9.0', 'Excellent participation', 'published', '2026-08-16 10:00:00.000000', [['criteriaId' => 'crit-1', 'score' => '9.0']]);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM student_badges WHERE studentId = ? AND badgeId = ?');
$stmt->execute([$s1, $b5]);
$assert((int) $stmt->fetchColumn() === 1, 'Published grading atomically awarded teacher_recognition badge');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE userId = ? AND eventKey = ?");
$stmt->execute([$u1, "badge_award:{$s1}:{$b5}:v1"]);
$assert((int) $stmt->fetchColumn() === 1, 'Teacher recognition notification published');

echo "learner_badge_producer_wiring_test: OK\n";
