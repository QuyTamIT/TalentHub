<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseCheckinRepository;
use TalentHub\Modules\School\Service\SchoolCheckinAggregateService;
use TalentHub\Modules\Teacher\Repository\TeacherQrSessionRepository;
use TalentHub\Modules\Teacher\Service\TeacherQrSessionService;
use TalentHub\Support\Uuid;

function phase5_mysql_safe_database(): string
{
    $database = Environment::required('DB_DATABASE');
    if (preg_match('/\Atalenthub_phase5_(?:rehearsal|test)_\d{14}\z/', $database) !== 1) {
        fwrite(STDERR, "learner_checkin_mysql_integration_test: REFUSED unsafe database name\n");
        exit(2);
    }
    return $database;
}

$workerMode = ($argv[1] ?? '') === '--worker';
if ($workerMode) {
    phase5_mysql_safe_database();
    [$action, $studentId, $userId, $target, $barrier, $workerToken] = array_map(
        static fn (mixed $value): string => (string) $value,
        array_slice(array_pad($argv, 8, ''), 2, 6),
    );
    file_put_contents($barrier . '.' . $workerToken . '.ready', 'ready');
    $deadline = microtime(true) + 15;
    while (!is_file($barrier) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (!is_file($barrier)) {
        fwrite(STDERR, "Phase 5 race barrier timed out.\n");
        exit(3);
    }

    try {
        $pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
        if ($action === 'scan') {
            $result = (new DatabaseCheckinRepository($pdo))->createConfirmed(
                $studentId,
                $userId,
                '01JPHASE5MYSQLWORKER0001',
                hash('sha256', $target),
            );
            echo json_encode(['data' => $result], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } elseif ($action === 'revoke') {
            $result = (new TeacherQrSessionRepository($pdo))->revokeSession($studentId, $target);
            echo json_encode(['revoked' => $result], JSON_THROW_ON_ERROR);
        } elseif ($action === 'policy') {
            $created = (new TeacherQrSessionService(new TeacherQrSessionRepository($pdo)))->create(
                $userId,
                $target,
                '15',
                '10',
                '3.75',
            );
            echo json_encode(['policy' => '3.75', 'sessionId' => $created['sessionId']], JSON_THROW_ON_ERROR);
        } else {
            throw new RuntimeException('Unknown worker action.');
        }
    } catch (ApiException $exception) {
        echo json_encode(['error' => $exception->errorCode], JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['fatal' => get_class($exception), 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR);
    }
    exit(0);
}

if (getenv('TALENTHUB_PHASE5_MYSQL_TEST') !== '1') {
    echo "learner_checkin_mysql_integration_test: SKIP (set TALENTHUB_PHASE5_MYSQL_TEST=1)\n";
    exit(0);
}

$databaseName = phase5_mysql_safe_database();
$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $databaseName) {
    throw new RuntimeException('Phase 5 MySQL connection is not pinned to the disposable schema.');
}
if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='activity_experience_policies'")->fetchColumn() !== 1) {
    throw new RuntimeException('Phase 5 migration must be applied to the disposable schema before integration tests.');
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$owner = $pdo->query(<<<'SQL'
    SELECT a.schoolId, t.id teacherId, t.userId teacherUserId
    FROM activities a
    INNER JOIN teacher_profiles t ON t.id=a.createdByTeacherId
    WHERE t.userId IS NOT NULL
    LIMIT 1
SQL)?->fetch(PDO::FETCH_ASSOC);
$students = $pdo->query(<<<'SQL'
    SELECT sp.id studentId, sp.userId
    FROM student_profiles sp
    INNER JOIN users u ON u.id=sp.userId
    WHERE u.status='active'
    ORDER BY sp.id
    LIMIT 2
SQL)?->fetchAll(PDO::FETCH_ASSOC);
$assert(is_array($owner) && count($students) === 2, 'Disposable fixture requires one Teacher and two active Students.');
$otherTeacher = $pdo->prepare('SELECT id teacherId,userId teacherUserId FROM teacher_profiles WHERE id<>? AND userId IS NOT NULL ORDER BY id LIMIT 1');
$otherTeacher->execute([$owner['teacherId']]);
$otherTeacher = $otherTeacher->fetch(PDO::FETCH_ASSOC);
$assert(is_array($otherTeacher), 'Disposable fixture requires a second Teacher for ownership denial.');

$fixtures = [];
$checkinIds = [];
$makeFixture = static function (
    string $label,
    array $registrants,
    int $maxScans,
    string $hours = '2.00',
) use ($pdo, $owner, &$fixtures): array {
    $activityId = Uuid::v4();
    $sessionId = Uuid::v4();
    $token = 'phase5-' . $label . '-' . bin2hex(random_bytes(12));
    $insertActivity = $pdo->prepare(<<<'SQL'
        INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status)
        VALUES (:id,:schoolId,:teacherId,:title,'phase5-test','2099-08-22 08:00:00.000000','2099-08-22 12:00:00.000000',100,'ongoing')
    SQL);
    $insertActivity->execute(['id' => $activityId, 'schoolId' => $owner['schoolId'], 'teacherId' => $owner['teacherId'], 'title' => 'Phase 5 ' . $label]);
    $policy = $pdo->prepare('INSERT INTO activity_experience_policies (activityId,confirmedHours) VALUES (?,?)');
    $policy->execute([$activityId, $hours]);
    $session = $pdo->prepare(<<<'SQL'
        INSERT INTO activity_qr_sessions (id,activityId,createdByTeacherId,tokenHash,status,expiresAt,maxScans,usedScans)
        VALUES (?,?,?,?,'active','2099-08-22 11:00:00.000000',?,0)
    SQL);
    $session->execute([$sessionId, $activityId, $owner['teacherId'], hash('sha256', $token), $maxScans]);
    $registrationIds = [];
    $registration = $pdo->prepare("INSERT INTO activity_registrations (id,activityId,studentId,status,registeredAt,updatedAt) VALUES (?,?,?,'approved',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
    foreach ($registrants as $student) {
        $registrationId = Uuid::v4();
        $registration->execute([$registrationId, $activityId, $student['studentId']]);
        $registrationIds[] = $registrationId;
    }
    $fixtures[] = $activityId;
    return compact('activityId', 'sessionId', 'token', 'registrationIds');
};

/** @param list<array{action:string,studentId:string,userId:string,target:string}> $commands @return list<array<string,mixed>> */
$runRace = static function (array $commands) use ($assert): array {
    $barrier = tempnam(sys_get_temp_dir(), 'talenthub-phase5-race-');
    $assert(is_string($barrier) && $barrier !== '', 'Race barrier path is available.');
    unlink($barrier);
    $workers = [];
    foreach ($commands as $index => $command) {
        $workerToken = 'worker-' . $index;
        $pipes = [];
        $process = proc_open([
            PHP_BINARY, __FILE__, '--worker', $command['action'], $command['studentId'],
            $command['userId'], $command['target'], $barrier, $workerToken,
        ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        $assert(is_resource($process), 'Phase 5 race worker starts.');
        $workers[] = compact('process', 'pipes', 'workerToken');
    }
    $deadline = microtime(true) + 15;
    do {
        $ready = count(array_filter($workers, static fn (array $worker): bool => is_file($barrier . '.' . $worker['workerToken'] . '.ready')));
        if ($ready === count($workers)) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    $assert($ready === count($workers), 'All Phase 5 race workers reached the barrier.');
    file_put_contents($barrier, 'go');
    $results = [];
    foreach ($workers as $worker) {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        $exit = proc_close($worker['process']);
        $assert($exit === 0, 'Race worker exits cleanly: ' . $stderr);
        $result = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        $assert(!isset($result['fatal']), 'Race worker has no fatal exception: ' . ($result['message'] ?? ''));
        $results[] = $result;
        @unlink($barrier . '.' . $worker['workerToken'] . '.ready');
    }
    @unlink($barrier);
    return $results;
};

try {
    $teacherActivityId = Uuid::v4();
    $fixtures[] = $teacherActivityId;
    $insertTeacherActivity = $pdo->prepare(<<<'SQL'
        INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status)
        VALUES (?,?,?,'Phase 5 Teacher policy','phase5-test','2099-08-22 08:00:00.000000','2099-08-22 12:00:00.000000',100,'ongoing')
    SQL);
    $insertTeacherActivity->execute([$teacherActivityId, $owner['schoolId'], $owner['teacherId']]);
    $teacherService = new TeacherQrSessionService(new TeacherQrSessionRepository($pdo));
    $teacherCreated = $teacherService->create((string) $owner['teacherUserId'], $teacherActivityId, '15', '5', '4.25');
    $assert(isset($teacherCreated['rawToken']) && !isset($teacherCreated['tokenHash']), 'Teacher receives the raw token only in the one-time create response.');
    $persistedTeacherQr = $pdo->prepare('SELECT tokenHash FROM activity_qr_sessions WHERE id=?');
    $persistedTeacherQr->execute([$teacherCreated['sessionId']]);
    $assert(hash_equals(hash('sha256', $teacherCreated['rawToken']), (string) $persistedTeacherQr->fetchColumn()), 'Teacher QR persists only the SHA-256 token hash.');
    $assert((string) $pdo->query("SELECT confirmedHours FROM activity_experience_policies WHERE activityId='{$teacherActivityId}'")->fetchColumn() === '4.25', 'Teacher-owned QR create persists the confirmed-hours policy.');
    try {
        $teacherService->create((string) $otherTeacher['teacherUserId'], $teacherActivityId, '15', '5', '7.00');
        $assert(false, 'Another Teacher must not create QR or update policy for an unowned activity.');
    } catch (ApiException $exception) {
        $assert($exception->errorCode === 'INVALID_ACTIVITY', 'Cross-Teacher create fails with the managed-ownership contract.');
    }
    $assert((string) $pdo->query("SELECT confirmedHours FROM activity_experience_policies WHERE activityId='{$teacherActivityId}'")->fetchColumn() === '4.25', 'Cross-Teacher attempt does not mutate the owner policy.');
    $teacherService->revoke((string) $owner['teacherUserId'], $teacherCreated['sessionId']);
    $assert((string) $pdo->query("SELECT status FROM activity_qr_sessions WHERE id='{$teacherCreated['sessionId']}'")->fetchColumn() === 'revoked', 'Teacher can revoke the owned QR session.');

    $same = $makeFixture('same-registration', [$students[0]], 10);
    $sameRace = $runRace([
        ['action' => 'scan', 'studentId' => $students[0]['studentId'], 'userId' => $students[0]['userId'], 'target' => $same['token']],
        ['action' => 'scan', 'studentId' => $students[0]['studentId'], 'userId' => $students[0]['userId'], 'target' => $same['token']],
    ]);
    $assert(count(array_filter($sameRace, static fn (array $row): bool => isset($row['data']))) === 1, 'Same-registration race has exactly one success.');
    $assert(array_values(array_filter(array_column($sameRace, 'error'))) === ['CHECKIN_ALREADY_EXISTS'], 'Same-registration race has one stable duplicate response.');
    $assert((int) $pdo->query("SELECT usedScans FROM activity_qr_sessions WHERE id='{$same['sessionId']}'")->fetchColumn() === 1, 'Same-registration race consumes one scan.');

    $last = $makeFixture('last-scan', $students, 1);
    $lastRace = $runRace([
        ['action' => 'scan', 'studentId' => $students[0]['studentId'], 'userId' => $students[0]['userId'], 'target' => $last['token']],
        ['action' => 'scan', 'studentId' => $students[1]['studentId'], 'userId' => $students[1]['userId'], 'target' => $last['token']],
    ]);
    $assert(count(array_filter($lastRace, static fn (array $row): bool => isset($row['data']))) === 1, 'Last-scan race has exactly one success.');
    $lastErrors = array_values(array_filter(array_column($lastRace, 'error')));
    $assert(count($lastErrors) === 1 && in_array($lastErrors[0], ['QR_SESSION_EXHAUSTED', 'CHECKIN_STATE_CONFLICT'], true), 'Last-scan loser receives a stable capacity conflict.');
    $assert((int) $pdo->query("SELECT usedScans FROM activity_qr_sessions WHERE id='{$last['sessionId']}'")->fetchColumn() === 1, 'Last-scan race never exceeds maxScans.');

    $revoke = $makeFixture('revoke', [$students[0]], 10);
    $revokeRace = $runRace([
        ['action' => 'scan', 'studentId' => $students[0]['studentId'], 'userId' => $students[0]['userId'], 'target' => $revoke['token']],
        ['action' => 'revoke', 'studentId' => $owner['teacherId'], 'userId' => $owner['teacherUserId'], 'target' => $revoke['sessionId']],
    ]);
    $scanResult = $revokeRace[0];
    $revokeResult = $revokeRace[1];
    $assert(
        (isset($scanResult['data']) && ($revokeResult['revoked'] ?? false) === true)
        || (($scanResult['error'] ?? null) === 'QR_SESSION_REVOKED' && ($revokeResult['revoked'] ?? false) === true),
        'Concurrent revoke serializes as completed-scan-then-revoke or revoke-wins.'
    );

    $policy = $makeFixture('policy-snapshot', [$students[0]], 10, '2.00');
    $policyRace = $runRace([
        ['action' => 'scan', 'studentId' => $students[0]['studentId'], 'userId' => $students[0]['userId'], 'target' => $policy['token']],
        ['action' => 'policy', 'studentId' => $owner['teacherId'], 'userId' => $owner['teacherUserId'], 'target' => $policy['activityId']],
    ]);
    $assert(isset($policyRace[0]['data']) && ($policyRace[1]['policy'] ?? null) === '3.75', 'Policy-update race completes both serialized operations.');
    $snapshotHours = (string) $pdo->query("SELECT hours FROM experience_logs WHERE activityId='{$policy['activityId']}'")->fetchColumn();
    $assert(in_array($snapshotHours, ['2.00', '2.000000', '3.75', '3.750000'], true), 'Experience records one complete locked policy snapshot.');

    foreach ($fixtures as $activityId) {
        $counts = $pdo->query("SELECT COUNT(*) checkins, COUNT(DISTINCT c.id) distinctCheckins, COUNT(el.id) experiences, COUNT(DISTINCT el.id) distinctExperiences FROM checkins c INNER JOIN activity_registrations ar ON ar.id=c.registrationId LEFT JOIN experience_logs el ON el.checkinId=c.id WHERE ar.activityId='{$activityId}'")?->fetch(PDO::FETCH_ASSOC);
        $assert((int) $counts['checkins'] === (int) $counts['distinctCheckins'], 'Fixture has no duplicate check-ins.');
        $assert((int) $counts['experiences'] === (int) $counts['distinctExperiences'], 'Fixture has no duplicate experiences.');
    }

    $teacherRows = (new TeacherQrSessionRepository($pdo))->listManagedCheckins((string) $owner['teacherId'], 100);
    $fixtureTeacherRows = array_filter($teacherRows, static fn (array $row): bool => in_array((string) $row['activityId'], $fixtures, true));
    $assert($fixtureTeacherRows !== [], 'Teacher sees confirmed check-ins for managed activities.');
    $schoolAggregate = (new SchoolCheckinAggregateService($pdo))->confirmedForSchool((string) $owner['schoolId']);
    $assert($schoolAggregate['confirmedCheckins'] >= count($fixtureTeacherRows), 'School sees a scoped aggregate that includes managed fixture check-ins.');

    $enterprisePermissions = $pdo->query(<<<'SQL'
        SELECT COUNT(*)
        FROM role_permissions rp
        INNER JOIN roles r ON r.id=rp.roleId
        INNER JOIN permissions p ON p.id=rp.permissionId
        WHERE r.code IN ('enterprise','business')
          AND (p.code LIKE 'checkin.%' OR p.code LIKE 'qr_session.%' OR p.code LIKE 'experience_log.%')
    SQL)?->fetchColumn();
    $assert((int) $enterprisePermissions === 0, 'Runtime Enterprise roles have no Phase 5 permissions.');

    echo "learner_checkin_mysql_integration_test: OK (same-registration,last-scan,revoke,policy-snapshot)\n";
} finally {
    if ($fixtures !== []) {
        $placeholders = implode(',', array_fill(0, count($fixtures), '?'));
        $checkins = $pdo->prepare("SELECT c.id FROM checkins c INNER JOIN activity_registrations ar ON ar.id=c.registrationId WHERE ar.activityId IN ({$placeholders})");
        $checkins->execute($fixtures);
        $checkinIds = $checkins->fetchAll(PDO::FETCH_COLUMN);
        if ($checkinIds !== []) {
            $checkinPlaceholders = implode(',', array_fill(0, count($checkinIds), '?'));
            $deleteAudit = $pdo->prepare("DELETE FROM audit_logs WHERE entityType='checkin' AND entityId IN ({$checkinPlaceholders})");
            $deleteAudit->execute($checkinIds);
        }
        foreach (['experience_logs', 'checkins'] as $table) {
            $delete = $pdo->prepare("DELETE {$table} FROM {$table} INNER JOIN activity_registrations ar ON ar.id=" . ($table === 'checkins' ? "{$table}.registrationId" : "(SELECT c.registrationId FROM checkins c WHERE c.id={$table}.checkinId)") . " WHERE ar.activityId IN ({$placeholders})");
            if ($table === 'experience_logs') {
                $delete = $pdo->prepare("DELETE el FROM experience_logs el INNER JOIN checkins c ON c.id=el.checkinId INNER JOIN activity_registrations ar ON ar.id=c.registrationId WHERE ar.activityId IN ({$placeholders})");
            }
            $delete->execute($fixtures);
        }
        foreach (['activity_qr_sessions', 'activity_experience_policies', 'activity_registrations', 'activities'] as $table) {
            $column = $table === 'activities' ? 'id' : 'activityId';
            $delete = $pdo->prepare("DELETE FROM {$table} WHERE {$column} IN ({$placeholders})");
            $delete->execute($fixtures);
        }
    }
}
