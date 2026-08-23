<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

$contract = dirname(__DIR__) . '/app/learner/data/Contracts/ActivityCommandRepository.php';
$repositoryFile = dirname(__DIR__) . '/app/learner/data/Database/DatabaseActivityCommandRepository.php';
$serviceFile = dirname(__DIR__) . '/app/learner/data/Service/ActivityRegistrationService.php';
foreach ([$contract, $repositoryFile, $serviceFile] as $requiredFile) {
    if (!is_file($requiredFile)) {
        fwrite(STDERR, "Missing Phase 4 command file: {$requiredFile}\n");
        exit(1);
    }
    require_once $requiredFile;
}

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository;
use TalentHub\Learner\Data\Service\ActivityRegistrationService;

function registration_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function registration_expect_api(callable $callback, int $status, string $code, string $message): void
{
    try {
        $callback();
    } catch (ApiException $exception) {
        registration_assert($exception->status === $status, "{$message}: status {$status}");
        registration_assert($exception->errorCode === $code, "{$message}: code {$code}");
        return;
    }
    registration_assert(false, "{$message}: expected ApiException");
}

/** @return array{pdo:PDO,service:ActivityRegistrationService,ids:array<string,string>} */
function registration_fixture(): array
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT)');
    $pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT, cancellationClosesAt TEXT, approvalMode TEXT)');
    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, registeredAt TEXT, updatedAt TEXT, cancelledAt TEXT, cancellationReason TEXT, UNIQUE(activityId,studentId))');
    $pdo->exec('CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId,eventKey))');
    $pdo->exec('CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId,notificationType))');

    $ids = [
        'studentA' => '11111111-1111-4111-8111-111111111111',
        'studentB' => '22222222-2222-4222-8222-222222222222',
        'userA' => '33333333-3333-4333-8333-333333333333',
        'userB' => 'aaaaaaaa-1111-4111-8111-111111111111',
        'userC' => 'aaaaaaaa-2222-4222-8222-222222222222',
        'activityAuto' => '44444444-4444-4444-8444-444444444444',
        'activityReview' => '55555555-5555-4555-8555-555555555555',
        'activityFull' => '66666666-6666-4666-8666-666666666666',
        'activityOverlap' => '77777777-7777-4777-8777-777777777777',
    ];
    $pdo->prepare('INSERT INTO student_profiles (id,userId) VALUES (?,?),(?,?),(?,?)')->execute([
        $ids['studentA'],
        $ids['userA'],
        $ids['studentB'],
        $ids['userB'],
        'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
        $ids['userC'],
    ]);
    $insertActivity = $pdo->prepare('INSERT INTO activities VALUES (:id,:title,:startAt,:endAt,:capacity,:status)');
    foreach ([
        [$ids['activityAuto'], 'Automatic', '2026-09-10 09:00:00', '2026-09-10 11:00:00', 2, 'published'],
        [$ids['activityReview'], 'Review', '2026-09-11 09:00:00', '2026-09-11 11:00:00', 2, 'published'],
        [$ids['activityFull'], 'Full', '2026-09-12 09:00:00', '2026-09-12 11:00:00', 1, 'published'],
        [$ids['activityOverlap'], 'Overlap', '2026-09-10 10:00:00', '2026-09-10 12:00:00', 3, 'published'],
    ] as [$id, $title, $startAt, $endAt, $capacity, $status]) {
        $insertActivity->execute(compact('id', 'title', 'startAt', 'endAt', 'capacity', 'status'));
    }
    $pdo->prepare("INSERT INTO activity_registration_policies VALUES (?, '2026-08-01 00:00:00', '2026-09-10 08:00:00', '2026-09-10 08:30:00', 'teacher_review')")
        ->execute([$ids['activityReview']]);

    $repository = new DatabaseActivityCommandRepository($pdo);
    $service = new ActivityRegistrationService(
        $repository,
        static fn (): DateTimeImmutable => new DateTimeImmutable('2026-08-22 04:00:00', new DateTimeZone('UTC')),
    );
    return compact('pdo', 'service', 'ids');
}

$fixture = registration_fixture();
$service = $fixture['service'];
$pdo = $fixture['pdo'];
$ids = $fixture['ids'];
$registered = $service->register($ids['studentA'], $ids['userA'], '01K39PHASE4REGISTER0000001', ['activityId' => $ids['activityAuto']]);
registration_assert(($registered['registration']['status'] ?? null) === 'approved', 'automatic registration is approved');
registration_assert((int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='activity_registration.registered'")->fetchColumn() === 1, 'registration audit is atomic');
registration_expect_api(
    fn () => $service->register($ids['studentA'], $ids['userA'], '01K39PHASE4REGISTER0000002', ['activityId' => $ids['activityAuto']]),
    409,
    'REGISTRATION_EXISTS',
    'duplicate registration is rejected',
);

$fixture = registration_fixture();
$fixture['pdo']->prepare("INSERT INTO activity_registrations VALUES ('88888888-8888-4888-8888-888888888888', ?, ?, 'approved', '2026-08-01 00:00:00', '2026-08-01 00:00:00', NULL, NULL)")
    ->execute([$fixture['ids']['activityFull'], $fixture['ids']['studentB']]);
$waitlisted = $fixture['service']->register($fixture['ids']['studentA'], $fixture['ids']['userA'], '01K39PHASE4WAITLIST00000001', ['activityId' => $fixture['ids']['activityFull']]);
registration_assert(($waitlisted['registration']['status'] ?? null) === 'waitlisted', 'full activity creates waitlisted registration');

$fixture = registration_fixture();
$pending = $fixture['service']->register($fixture['ids']['studentA'], $fixture['ids']['userA'], '01K39PHASE4PENDING000000001', ['activityId' => $fixture['ids']['activityReview']]);
registration_assert(($pending['registration']['status'] ?? null) === 'pending', 'teacher-review policy creates pending registration');

$fixture = registration_fixture();
$fixture['pdo']->prepare("INSERT INTO activity_registrations VALUES ('99999999-9999-4999-8999-999999999999', ?, ?, 'approved', '2026-08-01 00:00:00', '2026-08-01 00:00:00', NULL, NULL)")
    ->execute([$fixture['ids']['activityAuto'], $fixture['ids']['studentA']]);
registration_expect_api(
    fn () => $fixture['service']->register($fixture['ids']['studentA'], $fixture['ids']['userA'], '01K39PHASE4CONFLICT00000001', ['activityId' => $fixture['ids']['activityOverlap']]),
    409,
    'SCHEDULE_CONFLICT',
    'overlapping active registration is rejected',
);

$fixture = registration_fixture();
$approvedId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$firstWaitId = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$secondWaitId = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$insert = $fixture['pdo']->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?,?,?,NULL,NULL)');
$insert->execute([$approvedId, $fixture['ids']['activityFull'], $fixture['ids']['studentA'], 'approved', '2026-08-01 08:00:00', '2026-08-01 08:00:00']);
$insert->execute([$firstWaitId, $fixture['ids']['activityFull'], $fixture['ids']['studentB'], 'waitlisted', '2026-08-01 09:00:00', '2026-08-01 09:00:00']);
$insert->execute([$secondWaitId, $fixture['ids']['activityFull'], 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'waitlisted', '2026-08-01 10:00:00', '2026-08-01 10:00:00']);
$cancelled = $fixture['service']->cancel($fixture['ids']['studentA'], $fixture['ids']['userA'], '01K39PHASE4CANCEL000000001', [
    'registrationId' => $approvedId,
    'reason' => 'Không thể tham gia',
]);
registration_assert(($cancelled['registration']['status'] ?? null) === 'cancelled', 'owned approved registration is cancelled');
registration_assert(($cancelled['promotedRegistration']['id'] ?? null) === $firstWaitId, 'earliest waitlisted registration is promoted');
registration_assert(($cancelled['promotedRegistration']['status'] ?? null) === 'approved', 'automatic-policy waitlist promotion becomes approved');
registration_assert((string) $fixture['pdo']->query("SELECT status FROM activity_registrations WHERE id='{$secondWaitId}'")->fetchColumn() === 'waitlisted', 'only one waitlisted registration is promoted');

$fixture = registration_fixture();
$cancelledAfterCapacityReduction = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
$remainingApproved = 'ffffffff-ffff-4fff-8fff-ffffffffffff';
$blockedWaitlist = 'abababab-abab-4bab-8bab-abababababab';
$insert = $fixture['pdo']->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?,?,?,NULL,NULL)');
$insert->execute([$cancelledAfterCapacityReduction, $fixture['ids']['activityFull'], $fixture['ids']['studentA'], 'approved', '2026-08-01 08:00:00', '2026-08-01 08:00:00']);
$insert->execute([$remainingApproved, $fixture['ids']['activityFull'], $fixture['ids']['studentB'], 'approved', '2026-08-01 08:30:00', '2026-08-01 08:30:00']);
$insert->execute([$blockedWaitlist, $fixture['ids']['activityFull'], 'dddddddd-dddd-4ddd-8ddd-dddddddddddd', 'waitlisted', '2026-08-01 09:00:00', '2026-08-01 09:00:00']);
$capacitySafeCancellation = $fixture['service']->cancel($fixture['ids']['studentA'], $fixture['ids']['userA'], '01K39PHASE4CAPACITYREDUCE1', [
    'registrationId' => $cancelledAfterCapacityReduction,
]);
registration_assert($capacitySafeCancellation['promotedRegistration'] === null, 'capacity reduction prevents unsafe waitlist promotion');
registration_assert((string) $fixture['pdo']->query("SELECT status FROM activity_registrations WHERE id='{$blockedWaitlist}'")->fetchColumn() === 'waitlisted', 'waitlist remains when occupied count still meets capacity');

registration_expect_api(
    fn () => $fixture['service']->cancel($fixture['ids']['studentB'], $fixture['ids']['userA'], '01K39PHASE4CROSSSTUDENT001', ['registrationId' => $approvedId]),
    404,
    'RESOURCE_NOT_FOUND',
    'cross-Student cancellation is non-enumerating',
);

$fixture = registration_fixture();
$fixture['pdo']->exec("CREATE TRIGGER fail_registration_audit BEFORE INSERT ON audit_logs WHEN NEW.action='activity_registration.registered' BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
try {
    $fixture['service']->register($fixture['ids']['studentA'], $fixture['ids']['userA'], '01K39PHASE4ROLLBACK0000001', ['activityId' => $fixture['ids']['activityAuto']]);
    registration_assert(false, 'audit failure must abort registration');
} catch (PDOException) {
    registration_assert((int) $fixture['pdo']->query('SELECT COUNT(*) FROM activity_registrations')->fetchColumn() === 0, 'audit failure rolls back registration');
}

registration_expect_api(
    fn () => $service->register($ids['studentA'], $ids['userA'], '01K39PHASE4BADUUID00000001', ['activityId' => 'not-a-uuid']),
    422,
    'VALIDATION_FAILED',
    'invalid activity UUID is rejected',
);
registration_expect_api(
    fn () => $service->cancel($ids['studentA'], $ids['userA'], '01K39PHASE4LONGREASON00001', ['registrationId' => $registered['registration']['id'], 'reason' => str_repeat('x', 501)]),
    422,
    'VALIDATION_FAILED',
    'cancellation reason length is bounded',
);

echo "learner_activity_registration_api_test: OK\n";
