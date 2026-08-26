<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseActivityAttendanceReconciliationRepository;
use TalentHub\Learner\Data\Database\DatabaseCheckinRepository;
use TalentHub\Learner\Data\Service\ActivityAttendanceReconciliationService;
use TalentHub\Support\Uuid;

const PHASE9_MYSQL_SCHEMA = 'talenthub_activity_phase9_disposable';

$connect = static function (): PDO {
    $pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
    if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== PHASE9_MYSQL_SCHEMA) {
        throw new RuntimeException('Phase 9 MySQL test is not connected to the exact disposable schema.');
    }
    return $pdo;
};

if (($argv[1] ?? '') === '--worker') {
    [$action, $studentId, $userId, $target, $barrier, $workerId] = array_map(
        'strval',
        array_slice(array_pad($argv, 8, ''), 2, 6)
    );
    file_put_contents($barrier . '.' . $workerId . '.ready', 'ready');
    $deadline = microtime(true) + 15;
    while (!is_file($barrier) && microtime(true) < $deadline) usleep(10000);
    if (!is_file($barrier)) exit(3);
    try {
        $pdo = $connect();
        if ($action === 'checkin') {
            (new DatabaseCheckinRepository($pdo))->createConfirmed(
                $studentId,
                $userId,
                '01JPHASE9MYSQLRACE000001',
                hash('sha256', $target)
            );
            echo json_encode(['result' => 'attended'], JSON_THROW_ON_ERROR);
        } elseif ($action === 'reconcile') {
            $rows = (new ActivityAttendanceReconciliationService(
                new DatabaseActivityAttendanceReconciliationRepository($pdo)
            ))->run(new DateTimeImmutable('2026-08-25 12:00:00', new DateTimeZone('UTC')), 24, 100);
            echo json_encode(['result' => 'reconciled', 'count' => count($rows)], JSON_THROW_ON_ERROR);
        } else {
            throw new RuntimeException('Unknown Phase 9 race action.');
        }
    } catch (ApiException $exception) {
        echo json_encode(['error' => $exception->errorCode], JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['fatal' => basename(str_replace('\\', '/', get_class($exception)))], JSON_THROW_ON_ERROR);
    }
    exit(0);
}

if (getenv('TALENTHUB_PHASE9_MYSQL_TEST') !== '1') {
    echo "learner_activity_no_show_reconciliation_mysql_test: SKIP (opt-in disposable integration)\n";
    exit(0);
}

$pdo = $connect();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assert((int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='activity_registrations'")->fetchColumn() === 1, 'disposable fixture has the Phase 2 registration schema.');

$owner = $pdo->query(<<<'SQL'
    SELECT school.id AS schoolId, teacher.id AS teacherId
    FROM schools school
    INNER JOIN teacher_profiles teacher ON teacher.schoolId = school.id
    ORDER BY school.id, teacher.id LIMIT 1
SQL)->fetch(PDO::FETCH_ASSOC);
$studentStatement = $pdo->prepare(<<<'SQL'
    SELECT student.id AS studentId, student.userId
    FROM student_profiles student
    INNER JOIN classes classroom ON classroom.id = student.classId
    WHERE classroom.schoolId = :school_id
    ORDER BY student.id LIMIT 1
SQL);
$studentStatement->execute(['school_id' => $owner['schoolId'] ?? '']);
$student = $studentStatement->fetch(PDO::FETCH_ASSOC);
$assert(is_array($owner) && is_array($student), 'disposable fixture has a same-school owner and student.');

$createFixture = static function (string $label, bool $withQr = false) use ($pdo, $owner, $student): array {
    if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== PHASE9_MYSQL_SCHEMA) {
        throw new RuntimeException('Refusing Phase 9 fixture write on an unexpected schema.');
    }
    $activityId = Uuid::v4();
    $registrationId = Uuid::v4();
    $pdo->prepare(<<<'SQL'
        INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status)
        VALUES (?,?,?,?,'phase9-test','2026-08-20 08:00:00.000000','2026-08-20 10:00:00.000000',10,'ongoing')
    SQL)->execute([$activityId, $owner['schoolId'], $owner['teacherId'], 'Phase 9 ' . $label]);
    $pdo->prepare("INSERT INTO activity_registrations (id,activityId,studentId,status,registeredAt,updatedAt) VALUES (?,?,?,'approved','2026-08-19 08:00:00.000000','2026-08-19 08:00:00.000000')")
        ->execute([$registrationId, $activityId, $student['studentId']]);
    $token = null;
    if ($withQr) {
        $token = 'phase9-' . bin2hex(random_bytes(18));
        $pdo->prepare('INSERT INTO activity_experience_policies (activityId,confirmedHours) VALUES (?,?)')->execute([$activityId, '2.50']);
        $pdo->prepare(<<<'SQL'
            INSERT INTO activity_qr_sessions (id,activityId,createdByTeacherId,tokenHash,status,expiresAt,maxScans,usedScans)
            VALUES (?,?,?,?,'active','2099-08-25 12:00:00.000000',10,0)
        SQL)->execute([Uuid::v4(), $activityId, $owner['teacherId'], hash('sha256', $token)]);
    }
    return compact('activityId', 'registrationId', 'token');
};

$runRace = static function (array $commands): array {
    $barrier = tempnam(sys_get_temp_dir(), 'talenthub-phase9-race-');
    if (!is_string($barrier)) throw new RuntimeException('Race barrier unavailable.');
    unlink($barrier);
    $workers = [];
    foreach ($commands as $index => $command) {
        $id = 'worker-' . $index;
        $process = proc_open([
            PHP_BINARY, __FILE__, '--worker', $command['action'], $command['studentId'],
            $command['userId'], $command['target'], $barrier, $id,
        ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (!is_resource($process)) throw new RuntimeException('Race worker unavailable.');
        $workers[] = compact('id', 'process', 'pipes');
    }
    $deadline = microtime(true) + 15;
    do {
        $ready = count(array_filter($workers, static fn (array $worker): bool => is_file($barrier . '.' . $worker['id'] . '.ready')));
        if ($ready === count($workers)) break;
        usleep(10000);
    } while (microtime(true) < $deadline);
    if ($ready !== count($workers)) throw new RuntimeException('Race workers did not reach the barrier.');
    file_put_contents($barrier, 'go');
    $results = [];
    foreach ($workers as $worker) {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]); fclose($worker['pipes'][2]);
        if (proc_close($worker['process']) !== 0) throw new RuntimeException('Race worker failed safely: ' . trim($stderr));
        $results[] = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        @unlink($barrier . '.' . $worker['id'] . '.ready');
    }
    @unlink($barrier);
    return $results;
};

$race = $createFixture('race', true);
$raceResults = $runRace([
    ['action' => 'checkin', 'studentId' => $student['studentId'], 'userId' => $student['userId'], 'target' => $race['token']],
    ['action' => 'reconcile', 'studentId' => $student['studentId'], 'userId' => $student['userId'], 'target' => $race['registrationId']],
]);
$assert(count(array_filter($raceResults, static fn (array $result): bool => isset($result['fatal']))) === 0, 'race has no fatal/deadlock result.');
$raceStatus = (string) $pdo->query("SELECT status FROM activity_registrations WHERE id='{$race['registrationId']}'")->fetchColumn();
$checkins = (int) $pdo->query("SELECT COUNT(*) FROM checkins WHERE registrationId='{$race['registrationId']}' AND status='confirmed' AND confirmedAt IS NOT NULL")->fetchColumn();
$noShowAudits = (int) $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE entityId='{$race['registrationId']}' AND action='activity_registration.no_show_reconciled'")->fetchColumn();
if ($raceStatus === 'attended') {
    $assert($checkins === 1 && $noShowAudits === 0, 'check-in winner has confirmed evidence and no no-show audit.');
} else {
    $assert($raceStatus === 'no_show' && $checkins === 0 && $noShowAudits === 1, 'reconciler winner has no confirmed check-in and one no-show audit.');
}

$checkinFirst = $createFixture('checkin-first', true);
(new DatabaseCheckinRepository($pdo))->createConfirmed($student['studentId'], $student['userId'], '01JPHASE9CHECKINFIRST0001', hash('sha256', $checkinFirst['token']));
$afterCheckin = (new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($pdo)))
    ->run(new DateTimeImmutable('2026-08-25 12:00:00', new DateTimeZone('UTC')), 24, 100);
$assert(!in_array($checkinFirst['registrationId'], array_column($afterCheckin, 'registration_id'), true), 'committed check-in is skipped by reconciliation.');

$reconcileFirst = $createFixture('reconcile-first', true);
(new ActivityAttendanceReconciliationService(new DatabaseActivityAttendanceReconciliationRepository($pdo)))
    ->run(new DateTimeImmutable('2026-08-25 12:00:00', new DateTimeZone('UTC')), 24, 100);
$denied = false;
try {
    (new DatabaseCheckinRepository($pdo))->createConfirmed($student['studentId'], $student['userId'], '01JPHASE9RECONCILEFIRST1', hash('sha256', $reconcileFirst['token']));
} catch (ApiException $exception) {
    $denied = $exception->errorCode === 'REGISTRATION_NOT_ELIGIBLE';
}
$assert($denied, 'committed no_show makes a later check-in ineligible.');
$assert((int) $pdo->query("SELECT COUNT(*) FROM checkins WHERE registrationId='{$reconcileFirst['registrationId']}'")->fetchColumn() === 0, 'reconciler-first path creates no partial check-in.');

$runCli = static function (array $arguments): array {
    $job = dirname(__DIR__) . '/app/learner/jobs/reconcile-activity-attendance.php';
    $process = proc_open([PHP_BINARY, $job, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('Unable to start disposable reconciliation CLI.');
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return ['exit' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
};

$cli = $createFixture('cli');
$auditBeforeDryRun = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$notificationBeforeDryRun = (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
$dryRun = $runCli(['--schema=' . PHASE9_MYSQL_SCHEMA, '--grace-hours=24', '--limit=100', '--dry-run']);
$assert($dryRun['exit'] === 0, 'disposable CLI dry-run succeeds without secret output.');
$dryPayload = json_decode($dryRun['stdout'], true, 512, JSON_THROW_ON_ERROR);
$assert((int) ($dryPayload['candidate_count'] ?? 0) >= 1, 'disposable dry-run finds the due fixture.');
$assert((string) $pdo->query("SELECT status FROM activity_registrations WHERE id='{$cli['registrationId']}'")->fetchColumn() === 'approved', 'dry-run does not update registration.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn() === $auditBeforeDryRun, 'dry-run writes no audit.');
$assert((int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn() === $notificationBeforeDryRun, 'dry-run writes no notification.');

$apply = $runCli(['--schema=' . PHASE9_MYSQL_SCHEMA, '--grace-hours=24', '--limit=100']);
$assert($apply['exit'] === 0, 'disposable CLI apply succeeds only on the exact schema.');
$applyPayload = json_decode($apply['stdout'], true, 512, JSON_THROW_ON_ERROR);
$assert((int) ($applyPayload['reconciled_count'] ?? 0) >= 1, 'disposable CLI applies the due fixture.');
$assert((string) $pdo->query("SELECT status FROM activity_registrations WHERE id='{$cli['registrationId']}'")->fetchColumn() === 'no_show', 'disposable CLI persists no_show.');
$replay = $runCli(['--schema=' . PHASE9_MYSQL_SCHEMA, '--grace-hours=24', '--limit=100']);
$replayPayload = json_decode($replay['stdout'], true, 512, JSON_THROW_ON_ERROR);
$assert($replay['exit'] === 0 && (int) ($replayPayload['reconciled_count'] ?? -1) === 0, 'disposable CLI replay is idempotent.');

echo "learner_activity_no_show_reconciliation_mysql_test: OK (race,checkin-first,reconcile-first,cli-dry-run/apply/replay)\n";
