<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Config\Environment;
use TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository;
use TalentHub\Learner\Data\Service\ActivityRegistrationService;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;
use TalentHub\Support\Uuid;

$raceWorker = ($argv[1] ?? '') === '--race-worker';
if ($raceWorker) {
    $workerDatabaseName = Environment::required('DB_DATABASE');
    if (preg_match('/\Atalenthub_phase4_(?:rehearsal|test)_\d{14}\z/', $workerDatabaseName) !== 1) {
        fwrite(STDERR, "phase_4_mysql_integration_test worker: REFUSED unsafe database name\n");
        exit(2);
    }
    $activityId = (string) ($argv[2] ?? '');
    $studentId = (string) ($argv[3] ?? '');
    $userId = (string) ($argv[4] ?? '');
    $barrier = (string) ($argv[5] ?? '');
    file_put_contents($barrier . '.' . $studentId . '.ready', 'ready');
    $deadline = microtime(true) + 10;
    while (!is_file($barrier) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (!is_file($barrier)) {
        fwrite(STDERR, "Race barrier timed out.\n");
        exit(2);
    }
    $workerPdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
    $workerService = new ActivityRegistrationService(
        new DatabaseActivityCommandRepository($workerPdo),
        static fn (): DateTimeImmutable => new DateTimeImmutable('2099-08-22 00:00:00', new DateTimeZone('UTC')),
    );
    $result = $workerService->register($studentId, $userId, '01JPHASE4MYSQLRACE000001', ['activityId' => $activityId]);
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if (getenv('TALENTHUB_PHASE4_MYSQL_TEST') !== '1') {
    echo "phase_4_mysql_integration_test: SKIP (set TALENTHUB_PHASE4_MYSQL_TEST=1)\n";
    exit(0);
}

$databaseName = Environment::required('DB_DATABASE');
if (preg_match('/\Atalenthub_phase4_(?:rehearsal|test)_\d{14}\z/', $databaseName) !== 1) {
    fwrite(STDERR, "phase_4_mysql_integration_test: REFUSED unsafe database name\n");
    exit(2);
}

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$owner = $pdo->query(<<<'SQL'
    SELECT activity.schoolId, teacher.id teacherId, teacher.userId teacherUserId
    FROM activities activity
    INNER JOIN teacher_profiles teacher ON teacher.id = activity.createdByTeacherId
    WHERE teacher.userId IS NOT NULL
    LIMIT 1
SQL
)?->fetch(PDO::FETCH_ASSOC);
$students = $pdo->query(<<<'SQL'
    SELECT profile.id studentId, profile.userId
    FROM student_profiles profile
    INNER JOIN users user ON user.id = profile.userId
    WHERE user.status = 'active'
    ORDER BY profile.id
    LIMIT 2
SQL
)?->fetchAll(PDO::FETCH_ASSOC);
$assert(is_array($owner) && count($students) === 2, 'Phase 4 MySQL fixtures require one Teacher owner and two active Students.');

$automaticActivityId = Uuid::v4();
$reviewActivityId = Uuid::v4();
$raceActivityId = Uuid::v4();
$registrationIds = [];
try {
    $insertActivity = $pdo->prepare(<<<'SQL'
        INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status)
        VALUES (:id,:schoolId,:teacherId,:title,'phase4-test',:startAt,:endAt,1,'published')
    SQL
    );
    $insertActivity->execute([
        'id' => $automaticActivityId,
        'schoolId' => $owner['schoolId'],
        'teacherId' => $owner['teacherId'],
        'title' => 'Phase 4 MySQL automatic fixture',
        'startAt' => '2099-10-01 09:00:00.000000',
        'endAt' => '2099-10-01 11:00:00.000000',
    ]);
    $insertActivity->execute([
        'id' => $reviewActivityId,
        'schoolId' => $owner['schoolId'],
        'teacherId' => $owner['teacherId'],
        'title' => 'Phase 4 MySQL review fixture',
        'startAt' => '2099-10-02 09:00:00.000000',
        'endAt' => '2099-10-02 11:00:00.000000',
    ]);
    $insertActivity->execute([
        'id' => $raceActivityId,
        'schoolId' => $owner['schoolId'],
        'teacherId' => $owner['teacherId'],
        'title' => 'Phase 4 MySQL race fixture',
        'startAt' => '2099-10-03 09:00:00.000000',
        'endAt' => '2099-10-03 11:00:00.000000',
    ]);
    $policy = $pdo->prepare(<<<'SQL'
        INSERT INTO activity_registration_policies
            (activityId,registrationOpensAt,registrationClosesAt,cancellationClosesAt,approvalMode)
        VALUES (:activityId,'2099-01-01 00:00:00.000000','2099-09-30 23:59:59.000000','2099-10-01 00:00:00.000000','teacher_review')
    SQL
    );
    $policy->execute(['activityId' => $reviewActivityId]);

    $clock = static fn (): DateTimeImmutable => new DateTimeImmutable('2099-08-22 00:00:00', new DateTimeZone('UTC'));
    $studentCommands = new ActivityRegistrationService(new DatabaseActivityCommandRepository($pdo), $clock);
    $first = $studentCommands->register(
        (string) $students[0]['studentId'],
        (string) $students[0]['userId'],
        '01JPHASE4MYSQLREGISTER001',
        ['activityId' => $automaticActivityId],
    );
    $second = $studentCommands->register(
        (string) $students[1]['studentId'],
        (string) $students[1]['userId'],
        '01JPHASE4MYSQLREGISTER002',
        ['activityId' => $automaticActivityId],
    );
    $registrationIds[] = (string) $first['registration']['id'];
    $registrationIds[] = (string) $second['registration']['id'];
    $assert($first['registration']['status'] === 'approved', 'First MySQL registration is approved.');
    $assert($second['registration']['status'] === 'waitlisted', 'Full MySQL activity creates waitlist status.');

    $cancelled = $studentCommands->cancel(
        (string) $students[0]['studentId'],
        (string) $students[0]['userId'],
        '01JPHASE4MYSQLCANCEL0001',
        ['registrationId' => (string) $first['registration']['id'], 'reason' => 'integration_test'],
    );
    $assert($cancelled['registration']['status'] === 'cancelled', 'MySQL cancellation persists canonical state.');
    $assert(($cancelled['promotedRegistration']['id'] ?? null) === $second['registration']['id'], 'MySQL cancellation promotes FIFO waitlist entry.');
    $assert(($cancelled['promotedRegistration']['status'] ?? null) === 'approved', 'Automatic waitlist promotion becomes approved.');

    $pending = $studentCommands->register(
        (string) $students[0]['studentId'],
        (string) $students[0]['userId'],
        '01JPHASE4MYSQLPENDING0001',
        ['activityId' => $reviewActivityId],
    );
    $registrationIds[] = (string) $pending['registration']['id'];
    $assert($pending['registration']['status'] === 'pending', 'Teacher-review MySQL registration becomes pending.');

    $teacher = new TeacherActivityService(new TeacherActivityRepository($pdo));
    $approved = $teacher->transitionRegistration(
        (string) $owner['teacherId'],
        (string) $owner['teacherUserId'],
        '01JPHASE4MYSQLAPPROVE001',
        $reviewActivityId,
        (string) $pending['registration']['id'],
        ['expectedStatus' => 'pending', 'action' => 'approve'],
    );
    $assert($approved['status'] === 'approved', 'Teacher MySQL transition approves owned pending registration.');

    $barrier = tempnam(sys_get_temp_dir(), 'talenthub-phase4-race-');
    $assert(is_string($barrier) && $barrier !== '', 'Race barrier path is available.');
    unlink($barrier);
    $workers = [];
    foreach ($students as $student) {
        $pipes = [];
        $command = [
            PHP_BINARY,
            __FILE__,
            '--race-worker',
            $raceActivityId,
            (string) $student['studentId'],
            (string) $student['userId'],
            $barrier,
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        $assert(is_resource($process), 'MySQL race worker starts.');
        $workers[] = ['process' => $process, 'pipes' => $pipes, 'studentId' => (string) $student['studentId']];
    }
    $readyDeadline = microtime(true) + 10;
    do {
        $ready = 0;
        foreach ($workers as $worker) {
            if (is_file($barrier . '.' . $worker['studentId'] . '.ready')) {
                $ready++;
            }
        }
        if ($ready === count($workers)) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $readyDeadline);
    $assert($ready === count($workers), 'Both MySQL race workers reached the barrier.');
    file_put_contents($barrier, 'go');

    $raceStatuses = [];
    foreach ($workers as $worker) {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        $exit = proc_close($worker['process']);
        $assert($exit === 0, 'MySQL race worker succeeds: ' . $stderr);
        $decoded = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        $registrationIds[] = (string) $decoded['registration']['id'];
        $raceStatuses[] = (string) $decoded['registration']['status'];
        @unlink($barrier . '.' . $worker['studentId'] . '.ready');
    }
    @unlink($barrier);
    sort($raceStatuses);
    $assert($raceStatuses === ['approved', 'waitlisted'], 'Two-connection MySQL race cannot overbook capacity one.');

    $permissionCount = (int) $pdo->query(<<<'SQL'
        SELECT COUNT(*)
        FROM permissions permission
        INNER JOIN role_permissions mapping ON mapping.permissionId = permission.id
        INNER JOIN roles role ON role.id = mapping.roleId
        WHERE permission.code = 'activity_registration.update_managed' AND role.code = 'teacher'
    SQL
    )?->fetchColumn();
    $assert($permissionCount === 1, 'Managed transition permission is mapped to Teacher exactly once.');

    echo "phase_4_mysql_integration_test: OK\n";
} finally {
    $deleteFixtureAudit = $pdo->prepare(<<<'SQL'
        DELETE FROM audit_logs
        WHERE entityType = 'activity_registration'
          AND (
            metadata LIKE :automaticActivity
            OR metadata LIKE :reviewActivity
            OR metadata LIKE :raceActivity
            OR entityId IN (
                SELECT id FROM activity_registrations
                WHERE activityId IN (:automaticId,:reviewId,:raceId)
            )
          )
    SQL
    );
    $deleteFixtureAudit->execute([
        'automaticActivity' => '%' . $automaticActivityId . '%',
        'reviewActivity' => '%' . $reviewActivityId . '%',
        'raceActivity' => '%' . $raceActivityId . '%',
        'automaticId' => $automaticActivityId,
        'reviewId' => $reviewActivityId,
        'raceId' => $raceActivityId,
    ]);
    foreach ($registrationIds as $registrationId) {
        $deleteAudit = $pdo->prepare('DELETE FROM audit_logs WHERE entityType = :type AND entityId = :id');
        $deleteAudit->execute(['type' => 'activity_registration', 'id' => $registrationId]);
    }
    $deleteRegistrations = $pdo->prepare('DELETE FROM activity_registrations WHERE activityId IN (:automaticId,:reviewId,:raceId)');
    $deleteRegistrations->execute(['automaticId' => $automaticActivityId, 'reviewId' => $reviewActivityId, 'raceId' => $raceActivityId]);
    $deletePolicies = $pdo->prepare('DELETE FROM activity_registration_policies WHERE activityId IN (:automaticId,:reviewId,:raceId)');
    $deletePolicies->execute(['automaticId' => $automaticActivityId, 'reviewId' => $reviewActivityId, 'raceId' => $raceActivityId]);
    $deleteActivities = $pdo->prepare('DELETE FROM activities WHERE id IN (:automaticId,:reviewId,:raceId)');
    $deleteActivities->execute(['automaticId' => $automaticActivityId, 'reviewId' => $reviewActivityId, 'raceId' => $raceActivityId]);
}
