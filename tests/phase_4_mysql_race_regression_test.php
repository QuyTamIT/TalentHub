<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository;
use TalentHub\Learner\Data\Service\ActivityRegistrationService;
use TalentHub\Support\Uuid;

function phase4_race_safe_database(): string
{
    $name = Environment::required('DB_DATABASE');
    if (preg_match('/\Atalenthub_phase4_(?:rehearsal|test)_\d{14}\z/', $name) !== 1) {
        fwrite(STDERR, "phase_4_mysql_race_regression_test: REFUSED unsafe database name\n");
        exit(2);
    }
    return $name;
}

if (($argv[1] ?? '') === '--worker') {
    phase4_race_safe_database();
    [$action, $studentId, $userId, $targetId, $barrier, $token] = array_pad(array_slice($argv, 2, 6), 6, '');
    file_put_contents($barrier . '.' . $token . '.ready', 'ready');
    $deadline = microtime(true) + 10;
    while (!is_file($barrier) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (!is_file($barrier)) {
        fwrite(STDERR, "Race barrier timed out.\n");
        exit(3);
    }
    try {
        $pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
        $service = new ActivityRegistrationService(
            new DatabaseActivityCommandRepository($pdo),
            static fn (): DateTimeImmutable => new DateTimeImmutable('2099-08-22 00:00:00', new DateTimeZone('UTC')),
        );
        $result = $action === 'cancel'
            ? $service->cancel($studentId, $userId, '01JPHASE4RACECANCEL00001', ['registrationId' => $targetId, 'reason' => 'race_test'])
            : $service->register($studentId, $userId, '01JPHASE4RACEREGISTER001', ['activityId' => $targetId]);
        echo json_encode(['data' => $result], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    } catch (ApiException $exception) {
        echo json_encode(['error' => $exception->errorCode], JSON_THROW_ON_ERROR);
    }
    exit(0);
}

if (getenv('TALENTHUB_PHASE4_MYSQL_TEST') !== '1') {
    echo "phase_4_mysql_race_regression_test: SKIP (set TALENTHUB_PHASE4_MYSQL_TEST=1)\n";
    exit(0);
}
phase4_race_safe_database();

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$owner = $pdo->query(<<<'SQL'
    SELECT activity.schoolId, activity.createdByTeacherId teacherId
    FROM activities activity
    WHERE activity.createdByTeacherId IS NOT NULL
    LIMIT 1
SQL
)?->fetch(PDO::FETCH_ASSOC);
$students = $pdo->query(<<<'SQL'
    SELECT profile.id studentId, profile.userId
    FROM student_profiles profile
    INNER JOIN users user ON user.id = profile.userId
    WHERE user.status = 'active'
    ORDER BY profile.id
    LIMIT 3
SQL
)?->fetchAll(PDO::FETCH_ASSOC);
$assert(is_array($owner) && count($students) === 3, 'Race regression requires one activity owner and three Students.');

$overlapA = Uuid::v4();
$overlapB = Uuid::v4();
$cancelRaceActivity = Uuid::v4();

/** @param list<array{action:string,studentId:string,userId:string,targetId:string}> $commands @return list<array<string,mixed>> */
$runRace = static function (array $commands) use ($assert): array {
    $barrier = tempnam(sys_get_temp_dir(), 'talenthub-phase4-race-regression-');
    $assert(is_string($barrier) && $barrier !== '', 'Race barrier path is available.');
    unlink($barrier);
    $workers = [];
    foreach ($commands as $index => $command) {
        $token = 'worker-' . $index;
        $pipes = [];
        $process = proc_open([
            PHP_BINARY, __FILE__, '--worker', $command['action'], $command['studentId'],
            $command['userId'], $command['targetId'], $barrier, $token,
        ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        $assert(is_resource($process), 'Race worker starts.');
        $workers[] = compact('process', 'pipes', 'token');
    }
    $deadline = microtime(true) + 10;
    do {
        $ready = count(array_filter($workers, static fn (array $worker): bool => is_file($barrier . '.' . $worker['token'] . '.ready')));
        if ($ready === count($workers)) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    $assert($ready === count($workers), 'All race workers reached the barrier.');
    file_put_contents($barrier, 'go');
    $results = [];
    foreach ($workers as $worker) {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        $exit = proc_close($worker['process']);
        $assert($exit === 0, 'Race worker exits successfully: ' . $stderr);
        $results[] = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        @unlink($barrier . '.' . $worker['token'] . '.ready');
    }
    @unlink($barrier);
    return $results;
};

try {
    $insert = $pdo->prepare(<<<'SQL'
        INSERT INTO activities (id,schoolId,createdByTeacherId,title,category,startAt,endAt,capacity,status)
        VALUES (:id,:schoolId,:teacherId,:title,'phase4-race-test',:startAt,:endAt,:capacity,'published')
    SQL
    );
    foreach ([
        [$overlapA, 'Overlap A', '2099-10-04 09:00:00.000000', '2099-10-04 11:00:00.000000', 10],
        [$overlapB, 'Overlap B', '2099-10-04 10:00:00.000000', '2099-10-04 12:00:00.000000', 10],
        [$cancelRaceActivity, 'Cancel register race', '2099-10-06 09:00:00.000000', '2099-10-06 11:00:00.000000', 1],
    ] as [$id, $title, $startAt, $endAt, $capacity]) {
        $insert->execute([
            'id' => $id, 'schoolId' => $owner['schoolId'], 'teacherId' => $owner['teacherId'],
            'title' => $title, 'startAt' => $startAt, 'endAt' => $endAt, 'capacity' => $capacity,
        ]);
    }

    $sameStudentResults = $runRace([
        ['action' => 'register', 'studentId' => (string) $students[0]['studentId'], 'userId' => (string) $students[0]['userId'], 'targetId' => $overlapA],
        ['action' => 'register', 'studentId' => (string) $students[0]['studentId'], 'userId' => (string) $students[0]['userId'], 'targetId' => $overlapB],
    ]);
    $sameStudentErrors = array_values(array_filter(array_column($sameStudentResults, 'error')));
    $sameStudentSuccesses = array_values(array_filter(array_column($sameStudentResults, 'data')));
    $assert(count($sameStudentSuccesses) === 1 && $sameStudentErrors === ['SCHEDULE_CONFLICT'], 'Same-Student overlapping race persists exactly one registration.');

    $service = new ActivityRegistrationService(
        new DatabaseActivityCommandRepository($pdo),
        static fn (): DateTimeImmutable => new DateTimeImmutable('2099-08-22 00:00:00', new DateTimeZone('UTC')),
    );
    $approved = $service->register((string) $students[0]['studentId'], (string) $students[0]['userId'], '01JPHASE4RACESETUP000001', ['activityId' => $cancelRaceActivity]);
    $waitlisted = $service->register((string) $students[1]['studentId'], (string) $students[1]['userId'], '01JPHASE4RACESETUP000002', ['activityId' => $cancelRaceActivity]);
    $assert($approved['registration']['status'] === 'approved' && $waitlisted['registration']['status'] === 'waitlisted', 'Cancel/register race setup is canonical.');

    $cancelRegisterResults = $runRace([
        ['action' => 'cancel', 'studentId' => (string) $students[0]['studentId'], 'userId' => (string) $students[0]['userId'], 'targetId' => (string) $approved['registration']['id']],
        ['action' => 'register', 'studentId' => (string) $students[2]['studentId'], 'userId' => (string) $students[2]['userId'], 'targetId' => $cancelRaceActivity],
    ]);
    $assert(array_filter(array_column($cancelRegisterResults, 'error')) === [], 'Concurrent cancel/register commands both complete without state errors.');
    $counts = $pdo->query(<<<SQL
        SELECT status,COUNT(*) count FROM activity_registrations
        WHERE activityId = '{$cancelRaceActivity}' GROUP BY status
    SQL
    )?->fetchAll(PDO::FETCH_KEY_PAIR);
    $assert((int) ($counts['approved'] ?? 0) === 1, 'Concurrent cancel/register leaves exactly one approved seat.');
    $assert((int) ($counts['waitlisted'] ?? 0) === 1, 'Concurrent cancel/register leaves exactly one waitlisted Student.');
    $assert((int) ($counts['cancelled'] ?? 0) === 1, 'Concurrent cancel/register preserves cancellation history.');

    echo "phase_4_mysql_race_regression_test: OK\n";
} finally {
    $activityIds = [$overlapA, $overlapB, $cancelRaceActivity];
    $placeholders = implode(',', array_fill(0, count($activityIds), '?'));
    $registrationQuery = $pdo->prepare("SELECT id FROM activity_registrations WHERE activityId IN ({$placeholders})");
    $registrationQuery->execute($activityIds);
    $fixtureRegistrationIds = $registrationQuery->fetchAll(PDO::FETCH_COLUMN);
    foreach ($fixtureRegistrationIds as $registrationId) {
        $deleteAudit = $pdo->prepare("DELETE FROM audit_logs WHERE entityType='activity_registration' AND entityId=?");
        $deleteAudit->execute([(string) $registrationId]);
    }
    $deleteRegistrations = $pdo->prepare("DELETE FROM activity_registrations WHERE activityId IN ({$placeholders})");
    $deleteRegistrations->execute($activityIds);
    $deletePolicies = $pdo->prepare("DELETE FROM activity_registration_policies WHERE activityId IN ({$placeholders})");
    $deletePolicies->execute($activityIds);
    $deleteActivities = $pdo->prepare("DELETE FROM activities WHERE id IN ({$placeholders})");
    $deleteActivities->execute($activityIds);
}
