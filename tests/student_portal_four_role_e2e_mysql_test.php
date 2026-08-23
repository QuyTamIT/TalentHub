<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository;
use TalentHub\Learner\Data\Database\DatabaseAssessmentRepository;
use TalentHub\Learner\Data\Database\DatabaseAssessmentWriteRepository;
use TalentHub\Learner\Data\Database\DatabaseBadgeRepository;
use TalentHub\Learner\Data\Database\DatabaseCheckinRepository;
use TalentHub\Learner\Data\Database\DatabaseInternshipApplicationCommandRepository;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\ActivityRegistrationService;
use TalentHub\Learner\Data\Service\ApplicationCommandService;
use TalentHub\Learner\Data\Service\BadgeAwardService;
use TalentHub\Learner\Data\Service\BadgeReadService;
use TalentHub\Learner\Data\Service\BadgeRuleEngine;
use TalentHub\Learner\Data\Service\LearnerAssessmentService;
use TalentHub\Learner\Data\Service\LearnerCheckinService;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Learner\Data\Service\ProfileSharingService;
use TalentHub\Learner\Data\Service\StatisticsService;
use TalentHub\Modules\Business\Repository\InternshipRepository;
use TalentHub\Modules\Business\Service\InternshipService;
use TalentHub\Modules\School\Service\SchoolCheckinAggregateService;
use TalentHub\Modules\School\Service\SchoolAuthorization;
use TalentHub\Modules\Student\Repository\StudentRepository;
use TalentHub\Modules\Student\Service\StudentProfileService;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Modules\Teacher\Repository\TeacherQrSessionRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;
use TalentHub\Modules\Teacher\Service\TeacherGradingService;
use TalentHub\Modules\Teacher\Service\TeacherQrSessionService;
use TalentHub\Rbac\Service\PermissionService;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

if (Environment::appEnvironment() !== 'test' || getenv('TALENTHUB_DISPOSABLE_TEST_DB') !== '1') {
    fwrite(STDERR, "Phase 11 requires APP_ENV=test and TALENTHUB_DISPOSABLE_TEST_DB=1\n");
    exit(2);
}

/** @return array{code:int,stdout:string,stderr:string} */
$run = static function (array $command, ?string $stdinFile = null, ?string $stdoutFile = null): array {
    $descriptors = [
        0 => $stdinFile === null ? ['pipe', 'r'] : ['file', $stdinFile, 'r'],
        1 => $stdoutFile === null ? ['pipe', 'w'] : ['file', $stdoutFile, 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Phase 11 child process.');
    }
    if ($stdinFile === null) {
        fclose($pipes[0]);
    }
    $stdout = '';
    if ($stdoutFile === null) {
        $stdout = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
    }
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
};

function phase11Id(string $seed): string
{
    $hex = substr(hash('sha256', $seed), 0, 32);
    $hex[12] = '4';
    $hex[16] = '8';

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12),
    );
}

/**
 * @return array{
 *   students:list<array{user_id:string,student_id:string,class_id:string,school_id:string}>,
 *   teachers:list<array{user_id:string,teacher_id:string,school_id:string}>,
 *   schools:list<array{user_id:string,school_id:string,class_id:string}>,
 *   enterprises:list<array{user_id:string,enterprise_id:string}>
 * }
 */
function phase11CreateActors(PDO $pdo, string $runId): array
{
    $roles = [];
    foreach ($pdo->query("SELECT id, code FROM roles WHERE code IN ('student','teacher','school','enterprise')")->fetchAll() as $role) {
        $roles[(string) $role['code']] = (string) $role['id'];
    }
    foreach (['student', 'teacher', 'school', 'enterprise'] as $roleCode) {
        if (!isset($roles[$roleCode])) {
            throw new RuntimeException("Missing canonical role {$roleCode}.");
        }
    }

    $now = gmdate('Y-m-d H:i:s.u');
    $passwordHash = password_hash('Phase11-disabled-' . $runId, PASSWORD_BCRYPT);
    if (!is_string($passwordHash)) {
        throw new RuntimeException('Unable to create disposable password hash.');
    }
    $insertSchool = $pdo->prepare('INSERT INTO schools (id,name,status,email,level,studentCount,teacherCount,academicYear,createdAt,updatedAt) VALUES (?,?,\'active\',?,\'THPT\',0,0,\'2026-2027\',?,?)');
    $insertEnterprise = $pdo->prepare('INSERT INTO enterprises (id,name,status,industry,email,verificationStatus,createdAt,updatedAt) VALUES (?,?,\'active\',\'Technology\',?,\'verified\',?,?)');
    $insertClass = $pdo->prepare('INSERT INTO classes (id,schoolId,name,gradeLevel,academicYear,status,createdAt,updatedAt) VALUES (?,?,?,12,\'2026-2027\',\'active\',?,?)');
    $insertUser = $pdo->prepare('INSERT INTO users (id,roleId,email,passwordHash,fullName,status,createdAt,updatedAt) VALUES (?,?,?,?,?,\'active\',?,?)');
    $insertStudent = $pdo->prepare('INSERT INTO student_profiles (id,userId,classId,dateOfBirth,phone,studyStatus,createdAt,updatedAt) VALUES (?,?,?,\'2008-01-01\',?,\'active\',?,?)');
    $insertTeacher = $pdo->prepare('INSERT INTO teacher_profiles (id,userId,schoolId,isSchoolAdmin,phone,specialization,bio,createdAt,updatedAt) VALUES (?,?,?,0,?,\'Phase 11\',\'Disposable release actor\',?,?)');
    $insertSchoolMember = $pdo->prepare('INSERT INTO school_members (id,schoolId,userId,memberRole,createdAt,updatedAt) VALUES (?,?,?,?,?,?)');
    $insertEnterpriseMember = $pdo->prepare('INSERT INTO enterprise_members (id,enterpriseId,userId,memberRole,createdAt,updatedAt) VALUES (?,?,?,\'admin\',?,?)');

    $actors = ['students' => [], 'teachers' => [], 'schools' => [], 'enterprises' => []];
    for ($index = 1; $index <= 2; $index++) {
        $suffix = (string) $index;
        $schoolId = phase11Id("{$runId}:school:{$suffix}");
        $classId = phase11Id("{$runId}:class:{$suffix}");
        $enterpriseId = phase11Id("{$runId}:enterprise:{$suffix}");
        $insertSchool->execute([$schoolId, "Phase 11 School {$suffix}", "phase11+school-org-{$runId}-{$suffix}@example.invalid", $now, $now]);
        $insertClass->execute([$classId, $schoolId, "Phase 11 Class {$suffix}", $now, $now]);
        $insertEnterprise->execute([$enterpriseId, "Phase 11 Enterprise {$suffix}", "phase11+enterprise-org-{$runId}-{$suffix}@example.invalid", $now, $now]);

        $studentUserId = phase11Id("{$runId}:student-user:{$suffix}");
        $studentId = phase11Id("{$runId}:student:{$suffix}");
        $insertUser->execute([$studentUserId, $roles['student'], "phase11+student-{$runId}-{$suffix}@example.invalid", $passwordHash, "Phase 11 Student {$suffix}", $now, $now]);
        $insertStudent->execute([$studentId, $studentUserId, $classId, "+84000001{$suffix}", $now, $now]);
        $actors['students'][] = ['user_id' => $studentUserId, 'student_id' => $studentId, 'class_id' => $classId, 'school_id' => $schoolId];

        $teacherUserId = phase11Id("{$runId}:teacher-user:{$suffix}");
        $teacherId = phase11Id("{$runId}:teacher:{$suffix}");
        $insertUser->execute([$teacherUserId, $roles['teacher'], "phase11+teacher-{$runId}-{$suffix}@example.invalid", $passwordHash, "Phase 11 Teacher {$suffix}", $now, $now]);
        $insertTeacher->execute([$teacherId, $teacherUserId, $schoolId, "+84000002{$suffix}", $now, $now]);
        $insertSchoolMember->execute([phase11Id("{$runId}:teacher-member:{$suffix}"), $schoolId, $teacherUserId, 'member', $now, $now]);
        $actors['teachers'][] = ['user_id' => $teacherUserId, 'teacher_id' => $teacherId, 'school_id' => $schoolId];

        $schoolUserId = phase11Id("{$runId}:school-user:{$suffix}");
        $insertUser->execute([$schoolUserId, $roles['school'], "phase11+school-{$runId}-{$suffix}@example.invalid", $passwordHash, "Phase 11 School Admin {$suffix}", $now, $now]);
        $insertSchoolMember->execute([phase11Id("{$runId}:school-member:{$suffix}"), $schoolId, $schoolUserId, 'admin', $now, $now]);
        $actors['schools'][] = ['user_id' => $schoolUserId, 'school_id' => $schoolId, 'class_id' => $classId];

        $enterpriseUserId = phase11Id("{$runId}:enterprise-user:{$suffix}");
        $insertUser->execute([$enterpriseUserId, $roles['enterprise'], "phase11+enterprise-{$runId}-{$suffix}@example.invalid", $passwordHash, "Phase 11 Enterprise Admin {$suffix}", $now, $now]);
        $insertEnterpriseMember->execute([phase11Id("{$runId}:enterprise-member:{$suffix}"), $enterpriseId, $enterpriseUserId, $now, $now]);
        $actors['enterprises'][] = ['user_id' => $enterpriseUserId, 'enterprise_id' => $enterpriseId];
    }

    return $actors;
}

/** @param callable(bool,string):void $assert */
function phase11VerifyAuthorization(PDO $pdo, array $actors, callable $assert): array
{
    $permissions = new PermissionService($pdo);
    $positive = [
        [$actors['students'][0]['user_id'], 'student_profile.read_own'],
        [$actors['students'][1]['user_id'], 'checkin.create_own'],
        [$actors['teachers'][0]['user_id'], 'activity.create_managed'],
        [$actors['teachers'][1]['user_id'], 'assessment.update_managed'],
        [$actors['schools'][0]['user_id'], 'school_dashboard.read_own'],
        [$actors['schools'][1]['user_id'], 'student_profile.read_own_school'],
        [$actors['enterprises'][0]['user_id'], 'internship_post.create_own_business'],
        [$actors['enterprises'][1]['user_id'], 'internship_application.review_own_business'],
    ];
    foreach ($positive as [$userId, $permission]) {
        $permissions->require($userId, $permission);
        $assert(true, "positive permission {$permission}");
    }

    $denied = [
        [$actors['students'][0]['user_id'], 'activity.create_managed'],
        [$actors['students'][1]['user_id'], 'school_dashboard.read_own'],
        [$actors['teachers'][0]['user_id'], 'internship_post.create_own_business'],
        [$actors['teachers'][1]['user_id'], 'student_profile.update_own'],
        [$actors['schools'][0]['user_id'], 'checkin.create_own'],
        [$actors['schools'][1]['user_id'], 'internship_application.review_own_business'],
        [$actors['enterprises'][0]['user_id'], 'assessment.update_managed'],
        [$actors['enterprises'][1]['user_id'], 'school_dashboard.read_own'],
    ];
    foreach ($denied as [$userId, $permission]) {
        $caught = false;
        try {
            $permissions->require($userId, $permission);
        } catch (ApiException $error) {
            $caught = $error->status === 403 && $error->errorCode === 'PERMISSION_DENIED';
        }
        $assert($caught, "forbidden permission {$permission}");
    }

    $schoolAuthorization = new SchoolAuthorization($pdo);
    $schoolAuthorization->requireWriteAccess($actors['schools'][0]['user_id'], $actors['schools'][0]['school_id']);
    $crossSchoolDenied = false;
    try {
        $schoolAuthorization->requireWriteAccess($actors['schools'][0]['user_id'], $actors['schools'][1]['school_id']);
    } catch (ApiException $error) {
        $crossSchoolDenied = $error->status === 403 && $error->errorCode === 'FORBIDDEN';
    }
    $assert($crossSchoolDenied, 'school admin cannot write another school');

    return ['positive' => count($positive) + 1, 'denied' => count($denied) + 1];
}

/** @param callable():mixed $operation */
function phase11ExpectApi(callable $operation, int $status, string $code): bool
{
    try {
        $operation();
    } catch (ApiException $error) {
        return $error->status === $status && $error->errorCode === $code;
    }

    return false;
}

/** @param callable():mixed $operation */
function phase11ExpectFailure(callable $operation): bool
{
    try {
        $operation();
    } catch (Throwable) {
        return true;
    }

    return false;
}

/** @param callable(bool,string):void $assert */
function phase11RunProfileJourney(PDO $pdo, array $actors, callable $assert): array
{
    $studentA = $actors['students'][0];
    $studentB = $actors['students'][1];
    $profiles = new StudentProfileService(new StudentRepository($pdo));
    $updated = $profiles->update($studentA['user_id'], [
        'fullName' => 'Phase 11 Learner Owner',
        'phone' => '+84910000001',
        'location' => 'Da Nang',
        'headline' => 'Release rehearsal learner',
        'bio' => 'Deidentified Phase 11 disposable profile.',
    ]);
    $assert($updated['id'] === $studentA['student_id'], 'student updates only the profile resolved from own session user');
    $assert($profiles->get($studentB['user_id'])['fullName'] === 'Phase 11 Student 2', 'student B profile remains unchanged');

    $sharing = new ProfileSharingService($pdo);
    $share = $sharing->createShare($studentA['student_id'], ['fullName', 'headline', 'school'], 1);
    $projection = $sharing->resolveShare($share['rawToken']);
    $assert(is_array($projection) && ($projection['student']['fullName'] ?? null) === 'Phase 11 Learner Owner', 'consented profile projection resolves from opaque token');
    $assert($sharing->listShares($studentB['student_id']) === [], 'another student cannot enumerate owner shares');
    $assert(phase11ExpectApi(
        static fn () => $sharing->revokeShare($studentB['student_id'], $share['id']),
        404,
        'RESOURCE_NOT_FOUND',
    ), 'another student cannot revoke owner share');
    $sharing->revokeShare($studentA['student_id'], $share['id']);
    $assert($sharing->resolveShare($share['rawToken']) === null, 'revoked share fails closed');

    return ['updated' => true, 'shared' => true, 'revoked' => true, 'cross_owner_denied' => true];
}

/** @param callable(bool,string):void $assert */
function phase11RunActivityJourney(PDO $pdo, array $actors, string $runId, callable $assert): array
{
    $studentA = $actors['students'][0];
    $studentB = $actors['students'][1];
    $teacherA = $actors['teachers'][0];
    $teacherB = $actors['teachers'][1];
    $notifications = new NotificationService(new DatabaseNotificationRepository($pdo));
    $activityRepository = new TeacherActivityRepository($pdo, $notifications);
    $teacherActivities = new TeacherActivityService($activityRepository);
    $title = "Phase 11 release activity {$runId}";
    $teacherActivities->create($teacherA['teacher_id'], $teacherA['school_id'], [
        'title' => $title,
        'category' => 'career',
        'startAt' => new DateTimeImmutable('+1 day'),
        'endAt' => new DateTimeImmutable('+1 day +2 hours'),
        'capacity' => 1,
    ]);
    $findActivity = $pdo->prepare('SELECT id FROM activities WHERE title = :title AND createdByTeacherId = :teacherId LIMIT 1');
    $findActivity->execute(['title' => $title, 'teacherId' => $teacherA['teacher_id']]);
    $activityId = (string) $findActivity->fetchColumn();
    $assert($activityId !== '', 'teacher-created activity is persisted');
    $teacherActivities->advanceStatus($teacherA['teacher_id'], $activityId);

    $policy = $pdo->prepare(<<<'SQL'
        INSERT INTO activity_registration_policies
            (activityId, registrationOpensAt, registrationClosesAt, cancellationClosesAt, approvalMode)
        VALUES (:activityId, CURRENT_TIMESTAMP(6) - INTERVAL 1 DAY, CURRENT_TIMESTAMP(6) + INTERVAL 12 HOUR,
                CURRENT_TIMESTAMP(6) + INTERVAL 18 HOUR, 'teacher_review')
    SQL);
    $policy->execute(['activityId' => $activityId]);

    $registrationService = new ActivityRegistrationService(new DatabaseActivityCommandRepository($pdo, $notifications));
    $registeredA = $registrationService->register($studentA['student_id'], $studentA['user_id'], "p11rega{$runId}", ['activityId' => $activityId]);
    $assert(($registeredA['registration']['status'] ?? null) === 'pending', 'teacher-review registration starts pending');
    $approved = $teacherActivities->transitionRegistration(
        $teacherA['teacher_id'],
        $teacherA['user_id'],
        "p11apra{$runId}",
        $activityId,
        (string) $registeredA['registration']['id'],
        ['expectedStatus' => 'pending', 'action' => 'approve'],
    );
    $assert($approved['status'] === 'approved', 'owner teacher approves registration');
    $registeredB = $registrationService->register($studentB['student_id'], $studentB['user_id'], "p11regb{$runId}", ['activityId' => $activityId]);
    $assert(($registeredB['registration']['status'] ?? null) === 'waitlisted', 'second student is waitlisted at capacity');
    $assert(phase11ExpectApi(
        static fn () => $teacherActivities->update($teacherB['teacher_id'], $activityId, [
            'title' => 'Cross-owner mutation',
            'category' => 'career',
            'startAt' => new DateTimeImmutable('+1 day'),
            'endAt' => new DateTimeImmutable('+1 day +2 hours'),
            'capacity' => 1,
        ]),
        404,
        'RESOURCE_NOT_FOUND',
    ), 'another teacher cannot update the activity');

    $teacherActivities->advanceStatus($teacherA['teacher_id'], $activityId);
    $qr = new TeacherQrSessionService(new TeacherQrSessionRepository($pdo));
    $createdQr = $qr->create($teacherA['user_id'], $activityId, '15', '10', '1.50');
    $assert(isset($createdQr['rawToken']) && !isset($createdQr['tokenHash']), 'teacher receives raw QR token exactly once');
    $assert(phase11ExpectApi(
        static fn () => $qr->create($teacherB['user_id'], $activityId, '15', '10', '2.00'),
        422,
        'INVALID_ACTIVITY',
    ), 'another teacher cannot create QR for the activity');

    $checkins = new LearnerCheckinService(new DatabaseCheckinRepository($pdo, $notifications));
    $rawToken = (string) $createdQr['rawToken'];
    $replayToken = $rawToken;
    $confirmed = $checkins->submit($studentA['student_id'], $studentA['user_id'], "p11checka{$runId}", $rawToken);
    $assert(($confirmed['status'] ?? null) === 'confirmed', 'learner QR check-in is confirmed');
    $assert($rawToken === null, 'learner service erases raw QR token after use');
    $assert(phase11ExpectApi(
        static function () use ($checkins, $studentA, $runId, &$replayToken): void {
            $checkins->submit($studentA['student_id'], $studentA['user_id'], "p11replay{$runId}", $replayToken);
        },
        409,
        'CHECKIN_ALREADY_EXISTS',
    ), 'replayed QR check-in is rejected idempotently');

    $checkinCount = $pdo->prepare('SELECT COUNT(*) FROM checkins WHERE registrationId = :registrationId AND status = \'confirmed\'');
    $checkinCount->execute(['registrationId' => $registeredA['registration']['id']]);
    $experienceCount = $pdo->prepare('SELECT COUNT(*) FROM experience_logs WHERE studentId = :studentId AND activityId = :activityId AND status = \'confirmed\'');
    $experienceCount->execute(['studentId' => $studentA['student_id'], 'activityId' => $activityId]);
    $assert((int) $checkinCount->fetchColumn() === 1, 'QR replay creates no duplicate check-in');
    $assert((int) $experienceCount->fetchColumn() === 1, 'QR replay creates one confirmed experience fact');

    return [
        'activity_id' => $activityId,
        'registration_id' => (string) $registeredA['registration']['id'],
        'approved' => 1,
        'waitlisted' => 1,
        'confirmed_checkins' => 1,
        'confirmed_experiences' => 1,
        'replay_duplicates' => 0,
    ];
}

/** @param callable(bool,string):void $assert */
function phase11RunAssessmentJourney(PDO $pdo, array $actors, array $activity, callable $assert): array
{
    $studentA = $actors['students'][0];
    $studentB = $actors['students'][1];
    $teacherA = $actors['teachers'][0];
    $teacherB = $actors['teachers'][1];
    $notifications = new NotificationService(new DatabaseNotificationRepository($pdo));
    $readRepository = new DatabaseAssessmentRepository($pdo);
    $writeRepository = new DatabaseAssessmentWriteRepository($pdo, new ScorerRegistry([
        'holland-riasec-1.0' => new HollandScorer(),
        'mbti-education-1.0' => new MbtiScorer(),
        'disc-education-1.0' => new DiscScorer(),
        'multiple-intelligence-1.0' => new MultipleIntelligenceScorer(),
    ]), $notifications);
    $assessments = new LearnerAssessmentService($readRepository, $writeRepository);
    $attempt = $assessments->startOrResume($studentA['student_id'], 'holland', 'high');
    $attemptWithQuestions = $assessments->ownedAttemptWithQuestions($studentA['student_id'], (string) $attempt['id']);
    $questions = $attemptWithQuestions['questions'] ?? [];
    $assert(is_array($questions) && count($questions) > 0, 'student starts a published catalog assessment');
    foreach ($questions as $question) {
        $assessments->saveAnswer($studentA['student_id'], (string) $attempt['id'], (string) $question['id'], 5);
    }
    $assert(phase11ExpectFailure(
        static fn () => $assessments->ownedAttempt($studentB['student_id'], (string) $attempt['id']),
    ), 'another student cannot read the owned assessment attempt');
    $submitted = $assessments->submit($studentA['student_id'], (string) $attempt['id']);
    $assert(isset($submitted['result_code']) && $submitted['result_code'] !== '', 'student submits a deterministic assessment result');
    $resultCount = $pdo->prepare('SELECT COUNT(*) FROM test_results WHERE attemptId = :attemptId');
    $resultCount->execute(['attemptId' => $attempt['id']]);
    $assert((int) $resultCount->fetchColumn() === 1, 'assessment submission persists one immutable result');

    $grading = new TeacherGradingService(new TeacherGradingRepository($pdo));
    $page = $grading->pageData($teacherA['user_id'], $activity['activity_id']);
    $criteriaInput = [];
    foreach ($page['criteria'] as $criterion) {
        $criteriaInput[(string) $criterion['id']] = number_format((float) $criterion['maxScore'], 2, '.', '');
    }
    $assert($criteriaInput !== [], 'teacher grading uses active canonical criteria');
    $gradingInput = [
        'activityId' => $activity['activity_id'],
        'studentId' => $studentA['student_id'],
        'assessmentId' => null,
        'expectedVersion' => '0',
        'overallScore' => '88.00',
        'comment' => 'Phase 11 published evaluation',
        'assessmentStatus' => 'published',
        'criteria' => $criteriaInput,
    ];
    $assert(phase11ExpectApi(
        static fn () => $grading->save($teacherB['user_id'], $gradingInput),
        404,
        'RESOURCE_NOT_FOUND',
    ), 'another teacher cannot grade the owned activity');
    $grading->save($teacherA['user_id'], $gradingInput);
    $publishedA = $readRepository->publishedEvaluationsForStudent($studentA['student_id']);
    $publishedB = $readRepository->publishedEvaluationsForStudent($studentB['student_id']);
    $assert(count($publishedA) === 1 && ($publishedA[0]['status'] ?? null) === 'published', 'student sees the published owned teacher evaluation');
    $assert($publishedB === [], 'another student sees no foreign evaluation');

    return ['submitted_results' => 1, 'published_evaluations' => 1];
}

/** @param callable(bool,string):void $assert */
function phase11RunApplicationJourney(PDO $pdo, array $actors, string $runId, callable $assert): array
{
    $studentA = $actors['students'][0];
    $studentB = $actors['students'][1];
    $enterpriseA = $actors['enterprises'][0];
    $enterpriseB = $actors['enterprises'][1];
    $notifications = new NotificationService(new DatabaseNotificationRepository($pdo));
    $internships = new InternshipService(new InternshipRepository($pdo, $notifications));
    $post = $internships->createPost($enterpriseA['user_id'], [
        'title' => "Phase 11 Internship {$runId}",
        'field' => 'Technology',
        'location' => 'Remote',
        'workType' => 'hybrid',
        'duration' => '3 months',
        'educationLevel' => 'THPT',
        'description' => 'Deidentified release rehearsal opportunity.',
        'benefits' => 'Mentoring',
        'skills' => ['Collaboration', 'Problem solving'],
        'requirements' => ['Active learner'],
        'slots' => 1,
        'deadline' => (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s'),
    ]);
    $postId = (string) $post['id'];
    $internships->publish($enterpriseA['user_id'], $postId, 'draft');
    $assert(phase11ExpectApi(
        static fn () => $internships->post($enterpriseB['user_id'], $postId),
        404,
        'RESOURCE_NOT_FOUND',
    ), 'another enterprise cannot read the foreign owned post');

    $applications = new ApplicationCommandService(new DatabaseInternshipApplicationCommandRepository($pdo, $notifications));
    $consent = $applications->grantConsent($studentA['student_id'], $studentA['user_id'], "p11cons{$runId}", true);
    $assert(($consent['isGranted'] ?? null) === true, 'student grants explicit application profile consent');
    $application = $applications->submit(
        $studentA['student_id'],
        $studentA['user_id'],
        "p11apply{$runId}",
        $postId,
        'Phase 11 disposable application',
    );
    $applicationId = (string) $application['id'];
    $detail = $applications->detail($studentA['student_id'], $applicationId);
    $assert(isset($detail['snapshot']) && count($detail['history'] ?? []) === 1, 'application stores immutable profile snapshot and submitted history');
    $assert(phase11ExpectApi(
        static fn () => $applications->detail($studentB['student_id'], $applicationId),
        404,
        'RESOURCE_NOT_FOUND',
    ), 'another student cannot read the owned application');
    $assert(phase11ExpectApi(
        static fn () => $internships->review($enterpriseB['user_id'], $applicationId, [
            'expectedCurrentStatus' => 'submitted',
            'targetStatus' => 'reviewing',
            'reviewerNote' => 'Forbidden cross-owner review',
        ]),
        404,
        'RESOURCE_NOT_FOUND',
    ), 'another enterprise cannot review the application');
    $reviewed = $internships->review($enterpriseA['user_id'], $applicationId, [
        'expectedCurrentStatus' => 'submitted',
        'targetStatus' => 'reviewing',
        'reviewerNote' => 'Phase 11 owner review',
    ]);
    $assert(($reviewed['status'] ?? null) === 'reviewing' && count($reviewed['history'] ?? []) === 2, 'owner enterprise reviews through canonical transition history');

    return ['applications' => 1, 'final_status' => 'reviewing'];
}

/** @param callable(bool,string):void $assert */
function phase11RunFactsJourney(PDO $pdo, array $actors, string $runId, callable $assert): array
{
    $studentA = $actors['students'][0];
    $studentB = $actors['students'][1];
    $notifications = new NotificationService(new DatabaseNotificationRepository($pdo));
    $firstEvent = $notifications->publish(
        $studentA['user_id'],
        'assessment_submitted',
        'Phase 11 idempotency event',
        'Release rehearsal event',
        '/app/learner/assessment-result.php',
        "phase11:event:{$runId}",
        $studentA['student_id'],
    );
    $replayedEvent = $notifications->publish(
        $studentA['user_id'],
        'assessment_submitted',
        'Phase 11 idempotency event',
        'Release rehearsal event',
        '/app/learner/assessment-result.php',
        "phase11:event:{$runId}",
        $studentA['student_id'],
    );
    $assert(is_array($firstEvent) && $replayedEvent === null, 'stable notification event key is idempotent');
    $ownerNotifications = $notifications->listForUser($studentA['user_id']);
    $otherNotifications = $notifications->listForUser($studentB['user_id']);
    $assert(($ownerNotifications['total'] ?? 0) > 0, 'student notification inbox contains owner events');
    $assert(count(array_filter($ownerNotifications['items'], static fn (array $item): bool => $item['userId'] !== $studentA['user_id'])) === 0, 'owner inbox contains no foreign rows');
    $assert(count(array_filter($otherNotifications['items'], static fn (array $item): bool => $item['userId'] !== $studentB['user_id'])) === 0, 'other inbox contains no owner rows');
    $assert(phase11ExpectApi(
        static fn () => $notifications->markRead($studentB['user_id'], (string) $firstEvent['id']),
        404,
        'RESOURCE_NOT_FOUND',
    ), 'another student cannot mark the owner notification');

    $badgeRepository = new DatabaseBadgeRepository($pdo);
    $statisticsRepository = new DatabaseStatisticsRepository($pdo);
    $rules = new BadgeRuleEngine();
    $awards = new BadgeAwardService($badgeRepository, $statisticsRepository, $rules, $notifications);
    $badgeRead = new BadgeReadService($badgeRepository, $statisticsRepository, $rules);
    $beforeReplay = $badgeRead->forStudent($studentA['student_id']);
    $replayAwards = $awards->evaluateAndAward($studentA['student_id']);
    $assert(count($beforeReplay['badges']) > 0 && $replayAwards === [], 'domain producers award badges once and explicit replay is empty');
    $badgesA = $badgeRead->forStudent($studentA['student_id']);
    $badgesB = $badgeRead->forStudent($studentB['student_id']);
    $statistics = new StatisticsService($statisticsRepository);
    $statsA = $statistics->forStudentPeriod($studentA['student_id'], 'month');
    $statsB = $statistics->forStudentPeriod($studentB['student_id'], 'month');
    $assert(($badgesA['facts']['confirmed_experience_hours'] ?? 0) > 0 && ($badgesB['facts']['confirmed_experience_hours'] ?? 0) === 0.0, 'badge facts are isolated to confirmed owner experience');
    $assert(($statsA['facts']['attended_activity_count'] ?? 0) === 1 && ($statsB['facts']['attended_activity_count'] ?? 0) === 0, 'statistics are owner scoped and confirmed-only');

    return [
        'notifications' => ['owner_visible' => true, 'cross_owner_visible' => false],
        'badges_statistics' => ['replay_awards' => 0, 'owner_scoped' => true],
    ];
}

/** @param callable(bool,string):void $assert */
function phase11RunJourney(PDO $pdo, array $actors, string $runId, callable $assert): array
{
    $profile = phase11RunProfileJourney($pdo, $actors, $assert);
    $activity = phase11RunActivityJourney($pdo, $actors, $runId, $assert);
    return [
        'profile_share' => $profile['updated'] && $profile['shared'] && $profile['revoked'] && $profile['cross_owner_denied'],
        'activity_registration' => ['approved' => $activity['approved'], 'waitlisted' => $activity['waitlisted']],
        'checkin_experience' => [
            'checkins' => $activity['confirmed_checkins'],
            'confirmed_experiences' => $activity['confirmed_experiences'],
            'replay_duplicates' => $activity['replay_duplicates'],
        ],
        'assessment_evaluation' => phase11RunAssessmentJourney($pdo, $actors, $activity, $assert),
        'application_review' => phase11RunApplicationJourney($pdo, $actors, $runId, $assert),
        ...phase11RunFactsJourney($pdo, $actors, $runId, $assert),
    ];
}

/**
 * @return array{tables:array<string,array{primary_keys:list<string>,count:int,hash:string,rows:array<string,array<string,mixed>>}>,table_count:int,row_count:int,digest:string}
 */
function phase11DatabaseSnapshot(PDO $pdo): array
{
    $tableNames = $pdo->query(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' ORDER BY table_name"
    )->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $tables = [];
    $totalRows = 0;
    foreach ($tableNames as $tableNameRaw) {
        $tableName = (string) $tableNameRaw;
        if (preg_match('/\A[a-zA-Z0-9_]+\z/', $tableName) !== 1) {
            throw new RuntimeException("Unsafe table name in Phase 11 snapshot: {$tableName}");
        }
        $columnsStatement = $pdo->prepare(<<<'SQL'
            SELECT column_name AS name, column_key AS key_type
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table
            ORDER BY ordinal_position
        SQL);
        $columnsStatement->execute(['table' => $tableName]);
        $columnRows = $columnsStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $columns = array_map(static fn (array $row): string => (string) $row['name'], $columnRows);
        $primaryKeys = array_values(array_map(
            static fn (array $row): string => (string) $row['name'],
            array_filter($columnRows, static fn (array $row): bool => (string) $row['key_type'] === 'PRI'),
        ));
        $orderColumns = $primaryKeys !== [] ? $primaryKeys : $columns;
        $quotedColumns = array_map(static fn (string $column): string => "`{$column}`", $columns);
        $quotedOrder = array_map(static fn (string $column): string => "`{$column}`", $orderColumns);
        $rows = $pdo->query(
            'SELECT ' . implode(',', $quotedColumns) . " FROM `{$tableName}` ORDER BY " . implode(',', $quotedOrder)
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $rowMap = [];
        foreach ($rows as $index => $row) {
            $identity = $primaryKeys === []
                ? sprintf('row:%08d:%s', $index, hash('sha256', json_encode($row, JSON_THROW_ON_ERROR)))
                : json_encode(array_map(static fn (string $column): mixed => $row[$column] ?? null, $primaryKeys), JSON_THROW_ON_ERROR);
            $rowMap[$identity] = $row;
        }
        $encodedRows = json_encode($rowMap, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tables[$tableName] = [
            'primary_keys' => $primaryKeys,
            'count' => count($rows),
            'hash' => hash('sha256', $encodedRows),
            'rows' => $rowMap,
        ];
        $totalRows += count($rows);
    }
    $digestMaterial = [];
    foreach ($tables as $table => $snapshot) {
        $digestMaterial[$table] = ['count' => $snapshot['count'], 'hash' => $snapshot['hash']];
    }

    return [
        'tables' => $tables,
        'table_count' => count($tables),
        'row_count' => $totalRows,
        'digest' => hash('sha256', json_encode($digestMaterial, JSON_THROW_ON_ERROR)),
    ];
}

/** @return array{table_count:int,row_count:int,digest:string} */
function phase11SnapshotEvidence(array $snapshot): array
{
    return [
        'table_count' => (int) $snapshot['table_count'],
        'row_count' => (int) $snapshot['row_count'],
        'digest' => (string) $snapshot['digest'],
    ];
}

/** @return array{constraints:int,orphans:int} */
function phase11VerifyForeignKeys(PDO $pdo, callable $assert): array
{
    $rows = $pdo->query(<<<'SQL'
        SELECT constraint_name AS constraint_id, table_name AS child_table, column_name AS child_column,
               referenced_table_name AS parent_table, referenced_column_name AS parent_column,
               ordinal_position AS position
        FROM information_schema.key_column_usage
        WHERE constraint_schema = DATABASE() AND referenced_table_name IS NOT NULL
        ORDER BY table_name, constraint_name, ordinal_position
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $constraints = [];
    foreach ($rows as $row) {
        $key = (string) $row['child_table'] . '|' . (string) $row['constraint_id'];
        $constraints[$key]['table'] = (string) $row['child_table'];
        $constraints[$key]['referenced_table'] = (string) $row['parent_table'];
        $constraints[$key]['columns'][] = [(string) $row['child_column'], (string) $row['parent_column']];
    }
    $orphanTotal = 0;
    foreach ($constraints as $key => $constraint) {
        foreach ([$constraint['table'], $constraint['referenced_table']] as $identifier) {
            if (preg_match('/\A[a-zA-Z0-9_]+\z/', $identifier) !== 1) {
                throw new RuntimeException("Unsafe FK table identifier: {$identifier}");
            }
        }
        $joins = [];
        $nonnull = [];
        foreach ($constraint['columns'] as [$column, $referencedColumn]) {
            if (preg_match('/\A[a-zA-Z0-9_]+\z/', $column) !== 1 || preg_match('/\A[a-zA-Z0-9_]+\z/', $referencedColumn) !== 1) {
                throw new RuntimeException('Unsafe FK column identifier.');
            }
            $joins[] = "child.`{$column}` = parent.`{$referencedColumn}`";
            $nonnull[] = "child.`{$column}` IS NOT NULL";
        }
        $firstReferencedColumn = $constraint['columns'][0][1];
        $sql = "SELECT COUNT(*) FROM `{$constraint['table']}` child LEFT JOIN `{$constraint['referenced_table']}` parent ON "
            . implode(' AND ', $joins)
            . ' WHERE ' . implode(' AND ', $nonnull)
            . " AND parent.`{$firstReferencedColumn}` IS NULL";
        $orphans = (int) $pdo->query($sql)->fetchColumn();
        $assert($orphans === 0, "foreign key {$key} has zero orphan rows");
        $orphanTotal += $orphans;
    }

    return ['constraints' => count($constraints), 'orphans' => $orphanTotal];
}

/** @param callable(bool,string):void $assert */
function phase11VerifyInvariants(PDO $pdo, array $baseline, array $actors, callable $assert): array
{
    $current = phase11DatabaseSnapshot($pdo);
    $verifiedRows = 0;
    foreach ($baseline['tables'] as $tableName => $baselineTable) {
        $currentTable = $current['tables'][$tableName] ?? null;
        $assert(is_array($currentTable), "restored baseline table {$tableName} still exists");
        foreach ($baselineTable['rows'] as $identity => $baselineRow) {
            $currentRow = $currentTable['rows'][$identity] ?? null;
            $assert(is_array($currentRow) && $currentRow === $baselineRow, "pre-existing row remains unchanged in {$tableName}");
            $verifiedRows++;
        }
    }

    $foreignKeys = phase11VerifyForeignKeys($pdo, $assert);
    $uniqueQueries = [
        'registration' => "SELECT COUNT(*) FROM (SELECT activityId,studentId FROM activity_registrations GROUP BY activityId,studentId HAVING COUNT(*)>1) duplicates",
        'checkin' => "SELECT COUNT(*) FROM (SELECT registrationId FROM checkins GROUP BY registrationId HAVING COUNT(*)>1) duplicates",
        'experience' => "SELECT COUNT(*) FROM (SELECT checkinId FROM experience_logs GROUP BY checkinId HAVING COUNT(*)>1) duplicates",
        'application' => "SELECT COUNT(*) FROM (SELECT postId,studentId FROM internship_applications GROUP BY postId,studentId HAVING COUNT(*)>1) duplicates",
        'notification_event' => "SELECT COUNT(*) FROM (SELECT userId,eventKey FROM notifications WHERE eventKey IS NOT NULL GROUP BY userId,eventKey HAVING COUNT(*)>1) duplicates",
        'profile_share_token' => "SELECT COUNT(*) FROM (SELECT tokenHash FROM student_profile_shares GROUP BY tokenHash HAVING COUNT(*)>1) duplicates",
        'badge_award' => "SELECT COUNT(*) FROM (SELECT studentId,badgeId FROM student_badges GROUP BY studentId,badgeId HAVING COUNT(*)>1) duplicates",
    ];
    foreach ($uniqueQueries as $name => $sql) {
        $assert((int) $pdo->query($sql)->fetchColumn() === 0, "{$name} uniqueness invariant holds");
    }

    foreach ($actors['students'] as $actor) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM student_profiles WHERE id=:profileId AND userId=:userId AND classId=:classId');
        $statement->execute(['profileId' => $actor['student_id'], 'userId' => $actor['user_id'], 'classId' => $actor['class_id']]);
        $assert((int) $statement->fetchColumn() === 1, 'student actor keeps one profile/class ownership');
    }
    foreach ($actors['teachers'] as $actor) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM teacher_profiles WHERE id=:profileId AND userId=:userId AND schoolId=:schoolId');
        $statement->execute(['profileId' => $actor['teacher_id'], 'userId' => $actor['user_id'], 'schoolId' => $actor['school_id']]);
        $assert((int) $statement->fetchColumn() === 1, 'teacher actor keeps one profile/school ownership');
    }
    foreach ($actors['schools'] as $actor) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM school_members WHERE userId=:userId AND schoolId=:schoolId');
        $statement->execute(['userId' => $actor['user_id'], 'schoolId' => $actor['school_id']]);
        $assert((int) $statement->fetchColumn() === 1, 'school actor keeps one organization membership');
    }
    foreach ($actors['enterprises'] as $actor) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM enterprise_members WHERE userId=:userId AND enterpriseId=:enterpriseId');
        $statement->execute(['userId' => $actor['user_id'], 'enterpriseId' => $actor['enterprise_id']]);
        $assert((int) $statement->fetchColumn() === 1, 'enterprise actor keeps one organization membership');
    }

    $schoolAggregates = new SchoolCheckinAggregateService($pdo);
    $schoolA = $schoolAggregates->confirmedForSchool($actors['schools'][0]['school_id']);
    $schoolB = $schoolAggregates->confirmedForSchool($actors['schools'][1]['school_id']);
    $assert($schoolA['confirmedCheckins'] === 1 && $schoolA['confirmedHours'] === '1.50', 'school A sees its own confirmed aggregate');
    $assert($schoolB['confirmedCheckins'] === 0 && $schoolB['confirmedHours'] === '0.00', 'school B aggregate excludes school A facts');

    return [
        'baseline_tables' => count($baseline['tables']),
        'baseline_rows_verified' => $verifiedRows,
        'baseline_digest' => $baseline['digest'],
        'foreign_keys' => $foreignKeys,
        'uniqueness_checks' => count($uniqueQueries),
        'actor_ownership_checks' => 8,
        'school_scope' => ['school_a_checkins' => 1, 'school_b_checkins' => 0],
    ];
}

$config = require dirname(__DIR__) . '/config/database.php';
$sourceDatabase = (string) ($config['database'] ?? '');
$assert($sourceDatabase === 'talenthub_local', 'source must be talenthub_local');
$timestamp = gmdate('YmdHis');
$targetDatabase = 'talenthub_phase11_rehearsal_' . $timestamp;
$assert(preg_match('/\Atalenthub_phase11_rehearsal_\d{14}\z/', $targetDatabase) === 1, 'safe target name');
$assert($targetDatabase !== 'talenthub_local', 'target must not be primary');

$phpBin = (string) (getenv('TALENTHUB_PHP_EXE') ?: 'D:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe');
$mysqlBin = (string) (getenv('TALENTHUB_MYSQL_EXE') ?: 'D:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe');
$mysqldumpBin = (string) (getenv('TALENTHUB_MYSQLDUMP_EXE') ?: dirname($mysqlBin) . '\\mysqldump.exe');
$assert(is_file($phpBin), 'pinned PHP executable exists');
$assert(is_file($mysqlBin), 'pinned MySQL executable exists');
$assert(is_file($mysqldumpBin), 'pinned mysqldump executable exists');

$rootPdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']),
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);
$primaryPdo = (new Connection($config))->connect();
$primaryDataBefore = phase11DatabaseSnapshot($primaryPdo);
$primaryBefore = [
    'tables' => (int) $rootPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
    'migrations' => (int) $rootPdo->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
];
$assert($primaryBefore === ['tables' => 61, 'migrations' => 29], 'pinned Phase 11 primary baseline matches');

$backupDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'TalentHubBackups';
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('Unable to create the Phase 11 backup directory.');
}
$backupPath = $backupDirectory . DIRECTORY_SEPARATOR . "talenthub_local_pre_phase11_{$timestamp}.sql";
$dump = $run([
    $mysqldumpBin,
    '--host=' . $config['host'],
    '--port=' . (string) $config['port'],
    '--user=root',
    '--single-transaction',
    '--routines',
    '--events',
    '--triggers',
    '--hex-blob',
    '--default-character-set=utf8mb4',
    '--set-gtid-purged=OFF',
    $sourceDatabase,
], null, $backupPath);
$assert($dump['code'] === 0, 'mysqldump completed: ' . $dump['stderr']);
$assert(is_file($backupPath) && filesize($backupPath) > 0, 'backup is non-empty');
$backupSha256 = (string) hash_file('sha256', $backupPath);
$assert(preg_match('/\A[a-f0-9]{64}\z/', $backupSha256) === 1, 'backup SHA-256 is valid');
$assert(hash_equals($backupSha256, (string) hash_file('sha256', $backupPath)), 'backup SHA-256 re-verifies');

$failure = null;
$evidence = [];
try {
    $rootPdo->exec("CREATE DATABASE `{$targetDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach (['127.0.0.1', 'localhost'] as $host) {
        $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$targetDatabase}`.* TO '{$config['username']}'@'{$host}'");
    }

    $restore = $run([
        $mysqlBin,
        '--host=' . $config['host'],
        '--port=' . (string) $config['port'],
        '--user=root',
        '--database=' . $targetDatabase,
    ], $backupPath);
    $assert($restore['code'] === 0, 'backup restore completed: ' . $restore['stderr']);

    $targetConfig = $config;
    $targetConfig['database'] = $targetDatabase;
    $targetPdo = (new Connection($targetConfig))->connect();
    $restored = [
        'tables' => (int) $targetPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn(),
        'migrations' => (int) $targetPdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn(),
    ];
    $assert($restored === $primaryBefore, 'restored table and migration counts match primary');

    $runner = new MigrationRunner($targetPdo, dirname(__DIR__) . '/Database/migrations');
    $runner->validate();
    $firstReplay = $runner->migrate();
    $secondReplay = $runner->migrate();
    $assert($firstReplay === [], 'first migration replay is a no-op');
    $assert($secondReplay === [], 'second migration replay is a no-op');
    $runner->validate();
    $restoredSnapshot = phase11DatabaseSnapshot($targetPdo);

    $actors = phase11CreateActors($targetPdo, $timestamp);
    $assert(count($actors['students']) === 2, 'two disposable students exist');
    $assert(count($actors['teachers']) === 2, 'two disposable teachers exist');
    $assert(count($actors['schools']) === 2, 'two disposable schools exist');
    $assert(count($actors['enterprises']) === 2, 'two disposable enterprises exist');
    $actorUserIds = array_column([
        ...$actors['students'],
        ...$actors['teachers'],
        ...$actors['schools'],
        ...$actors['enterprises'],
    ], 'user_id');
    $assert(count(array_unique($actorUserIds)) === 8, 'all Phase 11 actor users are distinct');
    $authorization = phase11VerifyAuthorization($targetPdo, $actors, $assert);
    $journey = phase11RunJourney($targetPdo, $actors, $timestamp, $assert);
    $invariants = phase11VerifyInvariants($targetPdo, $restoredSnapshot, $actors, $assert);

    $primaryAfter = [
        'tables' => (int) $rootPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
        'migrations' => (int) $rootPdo->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
    ];
    $assert($primaryAfter === $primaryBefore, 'disposable restore/replay did not mutate primary');
    $primaryDataAfter = phase11DatabaseSnapshot($primaryPdo);
    $assert(
        phase11SnapshotEvidence($primaryDataAfter) === phase11SnapshotEvidence($primaryDataBefore),
        'primary row counts and deterministic table hashes remain unchanged',
    );

    $evidence = [
        'result' => 'PASS',
        'database' => $targetDatabase,
        'mysql_version' => (string) $targetPdo->query('SELECT VERSION()')->fetchColumn(),
        'backup' => ['path' => $backupPath, 'sha256' => $backupSha256, 'size' => filesize($backupPath)],
        'restored' => $restored,
        'migration_replay' => ['first' => $firstReplay, 'second' => $secondReplay, 'drift' => false],
        'actors' => ['student' => 2, 'teacher' => 2, 'school' => 2, 'enterprise' => 2],
        'authorization' => $authorization,
        'journey' => $journey,
        'invariants' => $invariants,
        'primary_snapshot' => [
            'before' => phase11SnapshotEvidence($primaryDataBefore),
            'after' => phase11SnapshotEvidence($primaryDataAfter),
        ],
        'primary_before_after_equal' => true,
        'assertions' => $assertions,
    ];
} catch (Throwable $error) {
    $failure = $error;
} finally {
    if (preg_match('/\Atalenthub_phase11_rehearsal_\d{14}\z/', $targetDatabase) !== 1 || $targetDatabase === 'talenthub_local') {
        throw new RuntimeException('Refusing unsafe Phase 11 cleanup.', previous: $failure);
    }
    foreach (['127.0.0.1', 'localhost'] as $host) {
        try {
            $rootPdo->exec("REVOKE ALL PRIVILEGES ON `{$targetDatabase}`.* FROM '{$config['username']}'@'{$host}'");
        } catch (Throwable) {
        }
    }
    try {
        $rootPdo->exec("DROP DATABASE IF EXISTS `{$targetDatabase}`");
    } catch (Throwable $cleanupError) {
        $failure ??= $cleanupError;
    }
}

$schemaCheck = $rootPdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = :schema');
$schemaCheck->execute(['schema' => $targetDatabase]);
$grantCheck = $rootPdo->prepare("SELECT COUNT(*) FROM mysql.db WHERE Db = :schema AND User = :user AND Host IN ('127.0.0.1', 'localhost')");
$grantCheck->execute(['schema' => $targetDatabase, 'user' => $config['username']]);
$assert((int) $schemaCheck->fetchColumn() === 0, 'disposable schema cleanup verified');
$assert((int) $grantCheck->fetchColumn() === 0, 'disposable grants cleanup verified');
if ($failure !== null) {
    throw $failure;
}

echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "student_portal_four_role_e2e_mysql_test: OK; cleanup verified\n";
