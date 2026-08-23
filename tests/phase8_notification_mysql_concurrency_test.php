<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Contracts\NotificationRepository;
use TalentHub\Learner\Data\Database\DatabaseActivityCommandRepository;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Learner\Data\Service\NotificationService;
use TalentHub\Support\Uuid;

if (($argv[1] ?? '') === '--worker') {
    $eventKey = (string) ($argv[2] ?? '');
    $userId = (string) ($argv[3] ?? '');
    $studentId = (string) ($argv[4] ?? '');
    $pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
    $service = new NotificationService(new DatabaseNotificationRepository($pdo));
    $result = $service->publish(
        $userId,
        'activity_checkin_committed',
        'Kiểm tra đồng thời',
        'Thông báo kiểm tra idempotency trên MySQL.',
        '/app/learner/checkin.php',
        $eventKey,
        $studentId,
    );
    echo $result === null ? "duplicate\n" : "inserted\n";
    exit(0);
}

$config = require dirname(__DIR__) . '/config/database.php';
$schema = (string) ($config['database'] ?? '');
if (!filter_var(getenv('TALENTHUB_DISPOSABLE_TEST_DB'), FILTER_VALIDATE_BOOL)
    || preg_match('/\Atalenthub_phase8_rehearsal_\d{14}\z/', $schema) !== 1
) {
    fwrite(STDERR, "phase8_notification_mysql_concurrency_test: NOT RUN (exact disposable gate required)\n");
    exit(2);
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

$pdo = (new Connection($config))->connect();
$pdo->exec("SET time_zone = '+00:00'");
$identity = $pdo->query(
    'SELECT sp.id AS studentId, sp.userId FROM student_profiles sp INNER JOIN users u ON u.id = sp.userId ORDER BY sp.id LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);
$assert(is_array($identity), 'disposable database has a Student identity');

$eventKey = 'phase8_concurrency:' . strtolower(str_replace('-', '', Uuid::v4()));
$commands = [];
for ($index = 0; $index < 8; $index++) {
    $commands[] = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        $eventKey,
        (string) $identity['userId'],
        (string) $identity['studentId'],
    ];
}

$processes = [];
foreach ($commands as $command) {
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Phase 8 concurrency worker.');
    }
    $processes[] = [$process, $pipes];
}

$workerResults = [];
foreach ($processes as [$process, $pipes]) {
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $assert($exitCode === 0, 'concurrency worker exits successfully: ' . trim((string) $stderr));
    $workerResults[] = trim((string) $stdout);
}
$assert(count(array_filter($workerResults, static fn (string $value): bool => $value === 'inserted')) === 1, 'exactly one concurrent publisher inserts');
$assert(count(array_filter($workerResults, static fn (string $value): bool => $value === 'duplicate')) === 7, 'seven concurrent retries resolve as idempotent duplicates');

$count = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE userId = :userId AND eventKey = :eventKey');
$count->execute(['userId' => $identity['userId'], 'eventKey' => $eventKey]);
$assert((int) $count->fetchColumn() === 1, 'unique event barrier leaves exactly one MySQL row');

$service = new NotificationService(new DatabaseNotificationRepository($pdo));
$foreignKeyFailed = false;
try {
    $service->publish(
        Uuid::v4(),
        'activity_checkin_committed',
        'Invalid recipient',
        'This write must fail.',
        '/app/learner/checkin.php',
        'phase8_invalid_recipient:' . strtolower(str_replace('-', '', Uuid::v4())),
        null,
    );
} catch (PDOException) {
    $foreignKeyFailed = true;
}
$assert($foreignKeyFailed, 'MySQL foreign-key failure is propagated, not classified as duplicate');

final class Phase8ThrowAfterInsertRepository implements NotificationRepository
{
    public function __construct(private readonly NotificationRepository $delegate) {}

    public function listForUser(string $userId, int $limit = 25, int $offset = 0, bool $unreadOnly = false): array
    {
        return $this->delegate->listForUser($userId, $limit, $offset, $unreadOnly);
    }

    public function unreadCount(string $userId): int { return $this->delegate->unreadCount($userId); }
    public function markRead(string $userId, string $notificationId): ?array { return $this->delegate->markRead($userId, $notificationId); }
    public function markAllRead(string $userId): int { return $this->delegate->markAllRead($userId); }
    public function preferencesForStudent(string $studentId): array { return $this->delegate->preferencesForStudent($studentId); }
    public function updatePreference(string $studentId, string $notificationType, bool $inAppEnabled, bool $emailEnabled): array
    {
        return $this->delegate->updatePreference($studentId, $notificationType, $inAppEnabled, $emailEnabled);
    }

    public function insertNotification(
        string $id,
        string $userId,
        ?string $eventKey,
        string $notificationType,
        string $title,
        string $message,
        ?string $deepLink,
        string $createdAt,
    ): bool {
        $inserted = $this->delegate->insertNotification(
            $id,
            $userId,
            $eventKey,
            $notificationType,
            $title,
            $message,
            $deepLink,
            $createdAt,
        );
        if ($inserted) {
            throw new RuntimeException('Injected failure after notification insert.');
        }
        return false;
    }
}

// Prove that a real producer rolls back its domain row, audit row and notification
// when notification persistence fails before commit.
$teacher = $pdo->query('SELECT id FROM teacher_profiles ORDER BY id LIMIT 1')->fetchColumn();
$school = $pdo->query('SELECT id FROM schools ORDER BY id LIMIT 1')->fetchColumn();
$assert(is_string($teacher) && $teacher !== '' && is_string($school) && $school !== '', 'activity rollback fixture parents exist');
$activityId = Uuid::v4();
$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
$pdo->prepare(<<<'SQL'
    INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status, createdAt, updatedAt)
    VALUES (:id, :schoolId, :teacherId, 'Phase 8 rollback fixture', 'workshop', '2036-08-23 09:00:00.000000', '2036-08-23 11:00:00.000000', 10, 'published', :createdAt, :updatedAt)
SQL)->execute([
    'id' => $activityId,
    'schoolId' => $school,
    'teacherId' => $teacher,
    'createdAt' => $now,
    'updatedAt' => $now,
]);

// Endpoint runtime coverage intentionally disables this preference earlier in the
// rehearsal. Re-enable it so this unit reaches the notification insert boundary.
$service->updatePreference(
    (string) $identity['studentId'],
    'activity_registration_created',
    true,
    false,
);

$failingService = new NotificationService(
    new Phase8ThrowAfterInsertRepository(new DatabaseNotificationRepository($pdo))
);
$activityRepository = new DatabaseActivityCommandRepository($pdo, $failingService);
$rolledBack = false;
$rollbackFailure = 'no exception';
try {
    $activityRepository->register(
        (string) $identity['studentId'],
        (string) $identity['userId'],
        '01KPHASE8MYSQLROLLBACK001',
        $activityId,
        new DateTimeImmutable('2036-08-01 00:00:00', new DateTimeZone('UTC')),
    );
} catch (Throwable $exception) {
    $rollbackFailure = $exception::class . ': ' . $exception->getMessage();
    $rolledBack = $exception->getMessage() === 'Injected failure after notification insert.';
}
$assert($rolledBack, 'injected notification failure escapes the activity producer; actual=' . $rollbackFailure);

$registrationCount = $pdo->prepare('SELECT COUNT(*) FROM activity_registrations WHERE activityId = :activityId');
$registrationCount->execute(['activityId' => $activityId]);
$assert((int) $registrationCount->fetchColumn() === 0, 'notification failure rolls back activity registration');
$notificationCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE eventKey LIKE 'activity_registration:%'");
$notificationCount->execute();
$assert((int) $notificationCount->fetchColumn() === 0, 'notification inserted by failed producer is rolled back');
$auditCount = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE entityId = :activityId AND action = 'activity_registration.registered'");
$auditCount->execute(['activityId' => $activityId]);
$assert((int) $auditCount->fetchColumn() === 0, 'notification failure rolls back producer audit row');

echo "phase8_notification_mysql_concurrency_test: OK ({$assertions} assertions)\n";
