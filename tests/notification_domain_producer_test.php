<?php

declare(strict_types=1);

require_once __DIR__ . '/../bin/bootstrap.php';
require_once __DIR__ . '/../app/learner/data/bootstrap.php';

use TalentHub\Http\ApiException;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

echo "Running tests/notification_domain_producer_test.php..." . PHP_EOL;

// 1. Verify existence of required classes
$assert(class_exists('TalentHub\Learner\Data\Database\DatabaseNotificationRepository'), 'DatabaseNotificationRepository exists');
$assert(class_exists('TalentHub\Learner\Data\Service\NotificationService'), 'NotificationService exists');

// Recipient resolution must fail closed. A broken student_profiles query cannot silently
// commit the domain fact without its notification.
foreach ([
    __DIR__ . '/../app/learner/data/Database/DatabaseActivityCommandRepository.php',
    __DIR__ . '/../app/learner/data/Database/DatabaseAssessmentWriteRepository.php',
    __DIR__ . '/../src/Modules/Teacher/Repository/TeacherActivityRepository.php',
    __DIR__ . '/../src/Modules/Business/Repository/InternshipRepository.php',
] as $recipientProducerFile) {
    $producerSource = file_get_contents($recipientProducerFile) ?: '';
    $assert(
        preg_match('/catch\s*\(\\\\PDOException\)\s*\{\s*return null;\s*\}/s', $producerSource) !== 1,
        basename($recipientProducerFile) . ' does not suppress recipient-resolution database failures'
    );
}

echo "Setting up in-memory sqlite test database..." . PHP_EOL;
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup schema
$pdo->exec(<<<'SQL'
    CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT, fullName TEXT, role TEXT);
    CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT);
    CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT);
    CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT, classId TEXT, phone TEXT, dateOfBirth TEXT, studyStatus TEXT);
    CREATE TABLE student_profile_details (id TEXT PRIMARY KEY, studentId TEXT, headline TEXT, location TEXT, bio TEXT, avatarUrl TEXT);
    CREATE TABLE skills (id TEXT PRIMARY KEY, name TEXT, category TEXT, status TEXT);
    CREATE TABLE student_skills (id TEXT PRIMARY KEY, studentId TEXT, skillId TEXT, levelScore REAL);
    CREATE TABLE certificates (id TEXT PRIMARY KEY, studentId TEXT, title TEXT, issuingOrganization TEXT, issueDate TEXT, credentialUrl TEXT, verificationStatus TEXT);
    CREATE TABLE projects (id TEXT PRIMARY KEY, title TEXT, category TEXT, description TEXT, projectUrl TEXT, status TEXT, createdAt TEXT);
    CREATE TABLE project_members (id TEXT PRIMARY KEY, projectId TEXT, studentId TEXT, role TEXT, status TEXT);
    CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT);
    CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT);
    CREATE TABLE enterprise_members (id TEXT PRIMARY KEY, enterpriseId TEXT, userId TEXT);


    CREATE TABLE activities (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        category TEXT NOT NULL DEFAULT 'workshop',
        capacity INTEGER NOT NULL,
        status TEXT NOT NULL,
        approvalMode TEXT NOT NULL DEFAULT 'automatic',
        createdByTeacherId TEXT NOT NULL,
        schoolId TEXT NOT NULL,
        startAt TEXT NOT NULL,
        endAt TEXT NOT NULL,
        registrationOpensAt TEXT NULL,
        registrationClosesAt TEXT NULL,
        cancellationClosesAt TEXT NULL,
        location TEXT NULL,
        description TEXT NULL,
        createdAt TEXT NOT NULL,
        updatedAt TEXT NOT NULL
    );
    CREATE TABLE activity_registrations (
        id TEXT PRIMARY KEY,
        activityId TEXT NOT NULL,
        studentId TEXT NOT NULL,
        status TEXT NOT NULL,
        registeredAt TEXT NOT NULL,
        updatedAt TEXT NOT NULL,
        cancelledAt TEXT NULL,
        cancellationReason TEXT NULL
    );
    CREATE UNIQUE INDEX uq_act_reg_stu ON activity_registrations(activityId, studentId);

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
    CREATE TABLE activity_registration_policies (
        activityId TEXT PRIMARY KEY,
        registrationOpensAt TEXT NULL,
        registrationClosesAt TEXT NULL,
        cancellationClosesAt TEXT NULL,
        approvalMode TEXT NOT NULL DEFAULT 'automatic',
        createdAt TEXT NOT NULL,
        updatedAt TEXT NOT NULL
    );
    CREATE TABLE activity_experience_policies (
        activityId TEXT PRIMARY KEY,
        confirmedHours REAL NOT NULL,
        createdAt TEXT NOT NULL,
        updatedAt TEXT NOT NULL
    );
    CREATE TABLE talent_tests (
        id TEXT PRIMARY KEY,
        code TEXT NOT NULL,
        type TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'published'
    );
    CREATE TABLE learner_assessment_versions (
        id TEXT PRIMARY KEY,
        testId TEXT NOT NULL,
        version TEXT NOT NULL,
        scoringVersion TEXT NOT NULL,
        schemaHash TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'published',
        createdAt TEXT NOT NULL
    );
    CREATE TABLE test_attempts (
        id TEXT PRIMARY KEY,
        testId TEXT NOT NULL,
        studentId TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'in_progress',
        startedAt TEXT NOT NULL,
        submittedAt TEXT NULL,
        createdAt TEXT NOT NULL,
        updatedAt TEXT NOT NULL
    );
    CREATE TABLE learner_assessment_attempt_metadata (
        id TEXT PRIMARY KEY,
        attemptId TEXT NOT NULL UNIQUE,
        versionId TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'in_progress',
        expiresAt TEXT NULL,
        submittedAt TEXT NULL,
        inputHash TEXT NULL,
        createdAt TEXT NOT NULL,
        updatedAt TEXT NOT NULL
    );
    CREATE TABLE learner_assessment_question_versions (
        id TEXT PRIMARY KEY,
        versionId TEXT NOT NULL,
        questionId TEXT NOT NULL,
        position INTEGER NOT NULL,
        dimensionCode TEXT NOT NULL,
        required INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE learner_assessment_answers (
        id TEXT PRIMARY KEY,
        attemptId TEXT NOT NULL,
        questionId TEXT NOT NULL,
        answerJson TEXT NOT NULL,
        answeredAt TEXT NOT NULL
    );
    CREATE TABLE test_results (
        id TEXT PRIMARY KEY,
        attemptId TEXT NOT NULL UNIQUE,
        resultCode TEXT NOT NULL,
        summary TEXT NOT NULL,
        dimensionScoresJson TEXT NOT NULL,
        scoringVersion TEXT NOT NULL,
        createdAt TEXT NOT NULL
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
        checkinId TEXT NOT NULL UNIQUE,
        hours REAL NOT NULL,
        status TEXT NOT NULL DEFAULT 'confirmed',
        confirmedAt TEXT NULL,
        recordedAt TEXT NULL,

        auditReason TEXT NULL,
        createdAt TEXT NOT NULL
    );



    CREATE TABLE privacy_consents (
        id TEXT PRIMARY KEY,
        studentId TEXT NOT NULL,
        scope TEXT NOT NULL,
        isGranted INTEGER NOT NULL DEFAULT 1,
        policyVersion TEXT NOT NULL,
        grantedAt TEXT NOT NULL,
        revokedAt TEXT NULL,
        createdAt TEXT NOT NULL
    );
    CREATE TABLE internship_posts (
        id TEXT PRIMARY KEY,
        enterpriseId TEXT NOT NULL,
        title TEXT NOT NULL,
        field TEXT NOT NULL,
        workType TEXT NOT NULL DEFAULT 'full_time',
        location TEXT NOT NULL,
        duration TEXT NOT NULL,
        educationLevel TEXT NOT NULL,
        slots INTEGER NOT NULL DEFAULT 1,
        skillsJson TEXT NOT NULL,
        description TEXT NOT NULL,
        requirements TEXT NOT NULL,
        benefits TEXT NOT NULL,
        deadline TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'active',
        createdAt TEXT NOT NULL,
        updatedAt TEXT NOT NULL
    );
    CREATE TABLE internship_applications (
        id TEXT PRIMARY KEY,
        postId TEXT NOT NULL,
        studentId TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'submitted',
        message TEXT NULL,
        reviewerNote TEXT NULL,
        reviewedAt TEXT NULL,
        reviewedBy TEXT NULL,
        appliedAt TEXT NOT NULL,
        createdAt TEXT NOT NULL,
        updatedAt TEXT NOT NULL
    );
    CREATE UNIQUE INDEX uq_intern_app ON internship_applications(postId, studentId);
    CREATE TABLE application_profile_snapshots (
        id TEXT PRIMARY KEY,
        applicationId TEXT NOT NULL UNIQUE,
        consentId TEXT NOT NULL,
        schemaVersion TEXT NOT NULL DEFAULT '1.0.0',
        snapshotPayload TEXT NOT NULL,
        createdAt TEXT NOT NULL
    );
    CREATE TABLE application_status_history (
        id TEXT PRIMARY KEY,
        applicationId TEXT NOT NULL,
        fromStatus TEXT NULL,
        toStatus TEXT NOT NULL,
        changedByUserId TEXT NOT NULL,
        changedByRole TEXT NOT NULL,
        note TEXT NULL,
        createdAt TEXT NOT NULL
    );

    CREATE TABLE audit_logs (
        id TEXT PRIMARY KEY,
        userId TEXT NOT NULL,
        action TEXT NOT NULL,
        entityType TEXT NOT NULL,
        entityId TEXT NOT NULL,
        requestId TEXT NOT NULL,
        ipAddress TEXT NULL,
        metadata TEXT NULL,
        createdAt TEXT NOT NULL
    );

    CREATE TABLE notifications (
        id TEXT PRIMARY KEY,
        userId TEXT NOT NULL,
        eventKey TEXT NULL,
        notificationType TEXT NOT NULL,
        title TEXT NOT NULL,
        message TEXT NOT NULL,
        deepLink TEXT NULL,
        readAt TEXT NULL,
        createdAt TEXT NOT NULL
    );
    CREATE UNIQUE INDEX uq_notif_user_event ON notifications(userId, eventKey);

    CREATE TABLE learner_notification_preferences (
        studentId TEXT NOT NULL,
        notificationType TEXT NOT NULL,
        inAppEnabled INTEGER NOT NULL DEFAULT 1,
        emailEnabled INTEGER NOT NULL DEFAULT 0,
        updatedAt TEXT NOT NULL,
        PRIMARY KEY (studentId, notificationType)
    );
SQL);

$notificationRepo = new \TalentHub\Learner\Data\Database\DatabaseNotificationRepository($pdo);
$notificationService = new \TalentHub\Learner\Data\Service\NotificationService($notificationRepo);

// Seed base users
$stuUser1 = '11111111-1111-4111-8111-111111111111';
$stuUser2 = '22222222-2222-4222-8222-222222222222';
$teaUser1 = '33333333-3333-4333-8333-333333333333';
$entUser1 = '44444444-4444-4444-8444-444444444444';
$pdo->exec("INSERT INTO users VALUES ('{$stuUser1}', 'stu1@test.com', 'Học viên 1', 'student')");
$pdo->exec("INSERT INTO users VALUES ('{$stuUser2}', 'stu2@test.com', 'Học viên 2', 'student')");
$pdo->exec("INSERT INTO users VALUES ('{$teaUser1}', 'tea1@test.com', 'Giáo viên 1', 'teacher')");
$pdo->exec("INSERT INTO users VALUES ('{$entUser1}', 'ent1@test.com', 'Doanh nghiệp 1', 'enterprise')");

$stu1 = '55555555-5555-4555-8555-555555555555';
$stu2 = '66666666-6666-4666-8666-666666666666';
$tea1 = '77777777-7777-4777-8777-777777777777';
$ent1 = '88888888-8888-4888-8888-888888888888';
$entMem1 = '99999999-9999-4999-8999-999999999999';
$pdo->exec("INSERT INTO student_profiles VALUES ('{$stu1}', '{$stuUser1}', 'sch-1', 'cls-1', '0901234567', '2005-01-01', 'studying')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$stu2}', '{$stuUser2}', 'sch-1', 'cls-1', '0901234568', '2005-01-02', 'studying')");
$pdo->exec("INSERT INTO teacher_profiles VALUES ('{$tea1}', '{$teaUser1}', 'sch-1')");

$pdo->exec("INSERT INTO enterprises VALUES ('{$ent1}', 'Công ty Tech')");
$pdo->exec("INSERT INTO enterprise_members VALUES ('{$entMem1}', '{$ent1}', '{$entUser1}')");

// 1. Test Activity Registration Producer
echo "Testing Activity Registration Producer..." . PHP_EOL;
$actRepo = new \TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository($pdo, $notificationService);
$now = new DateTimeImmutable('2026-08-23 10:00:00', new DateTimeZone('UTC'));

$act1 = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$pdo->exec("INSERT INTO activities (id, title, capacity, status, approvalMode, createdByTeacherId, schoolId, startAt, endAt, createdAt, updatedAt)
    VALUES ('{$act1}', 'Hội thảo AI', 1, 'published', 'automatic', '{$tea1}', 'sch-1', '2026-08-25 09:00:00', '2026-08-25 11:00:00', '2026-08-23 00:00:00', '2026-08-23 00:00:00')");

$pdo->exec("INSERT INTO activity_registration_policies (activityId, approvalMode, createdAt, updatedAt)
    VALUES ('{$act1}', 'automatic', '2026-08-23 00:00:00', '2026-08-23 00:00:00')");

$res1 = $actRepo->register($stu1, $stuUser1, 'req-1', $act1, $now);
$assert($res1['registration']['status'] === 'approved', 'Student 1 registered as approved');

// Check notification created for student 1
$notifs1 = $notificationService->listForUser($stuUser1);
$assert(count($notifs1['items']) === 1, 'Student 1 received 1 notification for registration');
$assert($notifs1['items'][0]['notificationType'] === 'activity_registration_created', 'Notification type is activity_registration_created');

// Student 2 registers -> waitlisted
$res2 = $actRepo->register($stu2, $stuUser2, 'req-2', $act1, $now);
$assert($res2['registration']['status'] === 'waitlisted', 'Student 2 registered as waitlisted');
$notifs2 = $notificationService->listForUser($stuUser2);
$assert(count($notifs2['items']) === 1, 'Student 2 received 1 notification');

// Student 1 cancels -> Student 2 promoted
$cancelRes = $actRepo->cancel($stu1, $stuUser1, 'req-3', $res1['registration']['id'], 'Bận việc', $now);
$notifs1After = $notificationService->listForUser($stuUser1);
$assert(count($notifs1After['items']) === 2, 'Student 1 received cancellation notification');
$assert($notifs1After['items'][0]['notificationType'] === 'activity_registration_cancelled', 'Type is cancelled');

$notifs2After = $notificationService->listForUser($stuUser2);
$assert(count($notifs2After['items']) === 2, 'Student 2 received promotion notification');
$assert($notifs2After['items'][0]['notificationType'] === 'activity_registration_promoted', 'Type is promoted');

// 2. Test Teacher Review Producer
echo "Testing Teacher Review Producer..." . PHP_EOL;
$act2 = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$pdo->exec("INSERT INTO activities (id, title, capacity, status, approvalMode, createdByTeacherId, schoolId, startAt, endAt, createdAt, updatedAt)
    VALUES ('{$act2}', 'Hội thảo Robotics', 10, 'published', 'teacher_review', '{$tea1}', 'sch-1', '2026-08-26 09:00:00', '2026-08-26 11:00:00', '2026-08-23 00:00:00', '2026-08-23 00:00:00')");
$pdo->exec("INSERT INTO activity_registration_policies (activityId, approvalMode, createdAt, updatedAt)
    VALUES ('{$act2}', 'teacher_review', '2026-08-23 00:00:00', '2026-08-23 00:00:00')");

$res3 = $actRepo->register($stu1, $stuUser1, 'req-4', $act2, $now);
$assert($res3['registration']['status'] === 'pending', 'Student 1 is pending teacher review');

$teaRepo = new \TalentHub\Modules\Teacher\Repository\TeacherActivityRepository($pdo, $notificationService);
$teaRepo->transitionRegistration($tea1, $teaUser1, 'req-5', $act2, $res3['registration']['id'], 'pending', 'approved');

$notifs1Tea = $notificationService->listForUser($stuUser1);
$assert($notifs1Tea['items'][0]['notificationType'] === 'activity_registration_approved', 'Student 1 received approval notification');

// 3. Test Checkin Producer
echo "Testing Checkin Producer..." . PHP_EOL;
$tokenHash = hash('sha256', 'valid-qr-token');
$pdo->exec("UPDATE activities SET status = 'ongoing' WHERE id = '{$act1}'");
$pdo->exec("INSERT INTO activity_qr_sessions (id, activityId, tokenHash, status, expiresAt, createdByTeacherId, createdAt, updatedAt)
    VALUES ('qr-1', '{$act1}', '{$tokenHash}', 'active', '2026-08-25 12:00:00', '{$tea1}', '2026-08-23 00:00:00', '2026-08-23 00:00:00')");
$pdo->exec("INSERT INTO activity_experience_policies (activityId, confirmedHours, createdAt, updatedAt)
    VALUES ('{$act1}', 2.0, '2026-08-23 00:00:00', '2026-08-23 00:00:00')");

$checkinRepo = new \TalentHub\Learner\Data\Database\DatabaseCheckinRepository($pdo, $notificationService);
// Student 2 was promoted to approved for act1
$checkinRes = $checkinRepo->createConfirmed($stu2, $stuUser2, 'req-chk', $tokenHash);
$assert($checkinRes['status'] === 'confirmed', 'Checkin confirmed');

$notifs2Chk = $notificationService->listForUser($stuUser2);
$assert($notifs2Chk['items'][0]['notificationType'] === 'activity_checkin_committed', 'Student 2 received checkin committed notification');

// 4. Test Application & Enterprise Review Producer
echo "Testing Application & Enterprise Review Producer..." . PHP_EOL;
$post1 = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$pdo->exec("INSERT INTO internship_posts (id, enterpriseId, title, field, location, duration, educationLevel, slots, skillsJson, description, requirements, benefits, deadline, status, createdAt, updatedAt)
    VALUES ('{$post1}', '{$ent1}', 'Lập trình viên PHP', 'IT', 'Hà Nội', '3 tháng', 'Đại học', 2, '[\"PHP\"]', 'Mô tả', 'Yêu cầu', 'Quyền lợi', '2026-09-01 00:00:00', 'active', '2026-08-23 00:00:00', '2026-08-23 00:00:00')");
$pdo->exec("INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, createdAt)
    VALUES ('consent-1', '{$stu1}', 'application_profile_share', 1, 'v1', '2026-08-23 00:00:00', '2026-08-23 00:00:00')");

$appRepo = new \TalentHub\Learner\Data\Database\DatabaseApplicationCommandRepository($pdo, $notificationService);
$appRes = $appRepo->submit($stu1, $stuUser1, 'req-app-1', $post1, 'Em xin ứng tuyển');
$notifs1App = $notificationService->listForUser($stuUser1);
$assert($notifs1App['items'][0]['notificationType'] === 'internship_application_submitted', 'Student 1 received application submitted notification');

$entRepo = new \TalentHub\Modules\Business\Repository\InternshipRepository($pdo, $notificationService);
$entRepo->review($ent1, $entUser1, $appRes['id'], 'submitted', 'reviewing', 'Hồ sơ đạt yêu cầu sơ loại');

$notifs1Rev = $notificationService->listForUser($stuUser1);
$assert($notifs1Rev['items'][0]['notificationType'] === 'internship_application_status_changed', 'Student 1 received status changed notification');

// Test Application Withdraw
$withdrawn = $appRepo->withdraw($stu1, $stuUser1, 'req-app-w', $appRes['id'], 'Đổi nguyện vọng');
$assert($withdrawn['status'] === 'withdrawn', 'Application withdrawn');
$notifs1With = $notificationService->listForUser($stuUser1);
$assert($notifs1With['items'][0]['notificationType'] === 'internship_application_withdrawn', 'Student 1 received withdrawal notification');

// 5. Test Assessment Submission Producer
echo "Testing Assessment Submission Producer..." . PHP_EOL;
$testId = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
$versionId = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
$attemptId = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
$questionId = '12121212-1212-4212-8212-121212121212';
$pdo->exec("INSERT INTO talent_tests VALUES ('{$testId}', 'holland_high', 'holland', 'published')");
$pdo->exec("INSERT INTO learner_assessment_versions VALUES ('{$versionId}', '{$testId}', '1.0', 'holland-riasec-1.0', 'hash1', 'published', '2026-08-23 00:00:00')");
$pdo->exec("INSERT INTO test_attempts VALUES ('{$attemptId}', '{$testId}', '{$stu1}', 'in_progress', '2026-08-23 00:00:00', NULL, '2026-08-23 00:00:00', '2026-08-23 00:00:00')");
$pdo->exec("INSERT INTO learner_assessment_attempt_metadata VALUES ('meta-1', '{$attemptId}', '{$versionId}', 'in_progress', '2026-09-23 00:00:00', NULL, NULL, '2026-08-23 00:00:00', '2026-08-23 00:00:00')");
$pdo->exec("INSERT INTO learner_assessment_question_versions VALUES ('qver-1', '{$versionId}', '{$questionId}', 1, 'R', 1)");
$pdo->exec("INSERT INTO learner_assessment_answers VALUES ('ans-1', '{$attemptId}', '{$questionId}', '5', '2026-08-23 00:00:00')");

$scorerRegistry = new \TalentHub\Learner\Assessment\Scoring\ScorerRegistry([
    'holland-riasec-1.0' => new \TalentHub\Learner\Assessment\Scoring\HollandScorer(),
]);
$assessRepo = new \TalentHub\Learner\Data\Database\DatabaseAssessmentWriteRepository($pdo, $scorerRegistry, $notificationService);
$submitResult = $assessRepo->submitAttempt($stu1, $attemptId);
$assert(($submitResult['result_code'] ?? null) !== null, 'Assessment scored and submitted');

$notifs1Assess = $notificationService->listForUser($stuUser1);
$assert($notifs1Assess['items'][0]['notificationType'] === 'assessment_submitted', 'Student 1 received assessment submitted notification');

// 6. Test Rollback Atomicity
echo "Testing Transaction Rollback Atomicity..." . PHP_EOL;
$u3 = '12345678-1234-4234-8234-123456789abc';
$s3 = '87654321-4321-4321-8321-cba987654321';
$pdo->exec("INSERT INTO users VALUES ('{$u3}', 'stu3@test.com', 'Học viên 3', 'student')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$s3}', '{$u3}', 'sch-1', 'cls-1', '0901234569', '2005-01-03', 'studying')");


$pdo->beginTransaction();
try {
    $notificationService->publish($u3, 'activity_registration_created', 'Test Rollback', 'Msg', '/app/learner/my-activities.php', 'rb-1', $s3);
    // Simulate error
    throw new RuntimeException('Simulated domain failure before commit');
} catch (Throwable $e) {
    $pdo->rollBack();
}

$assert($notificationService->unreadCount($u3) === 0, 'Unread count is 0 after rollback');
$u3List = $notificationService->listForUser($u3);
$assert(count($u3List['items']) === 0, 'Zero notifications exist after rollback');

echo "All tests in notification_domain_producer_test.php PASSED." . PHP_EOL;
