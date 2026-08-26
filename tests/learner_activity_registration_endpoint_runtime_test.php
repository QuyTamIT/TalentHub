<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

$workerMode = ($argv[1] ?? '') === '--worker';
if ($workerMode) {
    $databasePath = (string) ($argv[2] ?? '');
    $body = (string) ($argv[3] ?? '{}');
    $userId = (string) ($argv[4] ?? '33333333-3333-4333-8333-333333333333');
    $csrf = (string) ($argv[5] ?? 'csrf-test-token');

    $GLOBALS['__TALENTHUB_TEST_PDO__'] = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $GLOBALS['__TALENTHUB_TEST_SESSION__'] = [
        'user' => [
            'id' => $userId,
            'email' => $userId . '@example.test',
            'fullName' => $userId,
            'role' => 'student',
            'status' => 'active',
        ],
        'csrfToken' => 'csrf-test-token',
    ];
    $GLOBALS['__TALENTHUB_TEST_BODY__'] = $body;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/app/learner/api/v1/activity-registrations.php';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf;

    require dirname(__DIR__) . '/app/learner/api/v1/activity-registrations.php';
    exit(3);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$endpoint = dirname(__DIR__) . '/app/learner/api/v1/activity-registrations.php';
$assert(is_file($endpoint), 'Activity registration endpoint exists.');
$source = file_get_contents($endpoint) ?: '';
$assert(str_contains($source, 'activity_registration.create_own'), 'Register action requires exact Student create permission.');
$assert(str_contains($source, 'activity_registration.cancel_own'), 'Cancel action requires exact Student cancel permission.');
$assert(str_contains($source, "['action', 'activityId', 'registrationId', 'reason']"), 'Endpoint has an exact field allow-list.');
$assert(!str_contains($source, "'studentId'"), 'Endpoint never accepts a client Student ID.');

$databasePath = tempnam(sys_get_temp_dir(), 'talenthub-phase4-endpoint-');
$assert(is_string($databasePath) && $databasePath !== '', 'Disposable SQLite path is available.');

try {
    $pdo = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(<<<'SQL'
CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL);
CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL);
CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL, PRIMARY KEY(roleId,permissionId));
CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, status TEXT NOT NULL, fullName TEXT NOT NULL, email TEXT NOT NULL);
CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT NOT NULL);
CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL, title TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT);
CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT, cancellationClosesAt TEXT, approvalMode TEXT);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, registeredAt TEXT, updatedAt TEXT, cancelledAt TEXT, cancellationReason TEXT, UNIQUE(activityId,studentId));
CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT);
CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId,eventKey));
CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId,notificationType));
INSERT INTO roles VALUES ('role-student','student');
INSERT INTO permissions VALUES ('permission-create','activity_registration.create_own'),('permission-cancel','activity_registration.cancel_own');
INSERT INTO role_permissions VALUES ('role-student','permission-create'),('role-student','permission-cancel');
INSERT INTO schools VALUES
  ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','School A'),
  ('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','School B');
INSERT INTO classes VALUES
  ('cccccccc-cccc-4ccc-8ccc-cccccccccccc','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
  ('dddddddd-dddd-4ddd-8ddd-dddddddddddd','bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
INSERT INTO users VALUES
  ('33333333-3333-4333-8333-333333333333','role-student','active','Student A','a@example.test'),
  ('44444444-4444-4444-8444-444444444444','role-student','active','Student B','b@example.test');
INSERT INTO student_profiles VALUES
  ('11111111-1111-4111-8111-111111111111','33333333-3333-4333-8333-333333333333','cccccccc-cccc-4ccc-8ccc-cccccccccccc'),
  ('22222222-2222-4222-8222-222222222222','44444444-4444-4444-8444-444444444444','dddddddd-dddd-4ddd-8ddd-dddddddddddd');
INSERT INTO activities VALUES
  ('55555555-5555-4555-8555-555555555555','aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','Future activity','2099-09-10 09:00:00','2099-09-10 11:00:00',2,'published'),
  ('66666666-6666-4666-8666-666666666666','bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb','Other activity','2099-09-11 09:00:00','2099-09-11 11:00:00',2,'published');
INSERT INTO activity_registrations VALUES
  ('77777777-7777-4777-8777-777777777777','66666666-6666-4666-8666-666666666666','22222222-2222-4222-8222-222222222222','approved','2026-08-01 00:00:00','2026-08-01 00:00:00',NULL,NULL);
SQL
    );

    $run = static function (array $body, string $userId = '33333333-3333-4333-8333-333333333333', string $csrf = 'csrf-test-token') use ($databasePath, $assert): array {
        $command = [PHP_BINARY, __FILE__, '--worker', $databasePath, json_encode($body, JSON_THROW_ON_ERROR), $userId, $csrf];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        $assert(is_resource($process), 'Endpoint worker starts.');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $assert($exit === 0, "Endpoint worker exits cleanly: {$stderr}");
        $decoded = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        $assert(is_array($decoded), 'Endpoint returns JSON.');
        return $decoded;
    };

    $register = $run(['action' => 'register', 'activityId' => '55555555-5555-4555-8555-555555555555']);
    $registrationId = (string) ($register['data']['registration']['id'] ?? '');
    $assert($registrationId !== '', 'Real endpoint creates a registration.');
    $assert(($register['data']['registration']['status'] ?? null) === 'approved', 'Endpoint returns authoritative status.');
    $assert(($register['data']['capacity'] ?? null) === ['participants' => 1, 'capacity' => 2, 'remaining' => 1], 'Endpoint returns authoritative capacity after registration.');

    $beforeForeignScope = [
        'registrations' => (int) $pdo->query('SELECT COUNT(*) FROM activity_registrations')->fetchColumn(),
        'audits' => (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
        'notifications' => (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(),
    ];
    $foreignScope = $run(['action' => 'register', 'activityId' => '66666666-6666-4666-8666-666666666666']);
    $assert(($foreignScope['error']['code'] ?? null) === 'ACTIVITY_SCHOOL_SCOPE_DENIED', 'Endpoint rejects an otherwise valid direct registration to another school.');
    $assert(($foreignScope['error']['message'] ?? null) === 'Bạn chỉ được đăng ký hoạt động của trường mình.', 'Endpoint preserves the school-scope denial message.');
    $afterForeignScope = [
        'registrations' => (int) $pdo->query('SELECT COUNT(*) FROM activity_registrations')->fetchColumn(),
        'audits' => (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn(),
        'notifications' => (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn(),
    ];
    $assert($afterForeignScope === $beforeForeignScope, 'Endpoint foreign-school denial performs no registration, audit, or notification write.');

    $badCsrf = $run(['action' => 'cancel', 'registrationId' => $registrationId], '33333333-3333-4333-8333-333333333333', 'wrong');
    $assert(($badCsrf['error']['code'] ?? null) === 'CSRF_INVALID', 'Endpoint rejects invalid CSRF.');

    $crossOwner = $run(['action' => 'cancel', 'registrationId' => '77777777-7777-4777-8777-777777777777']);
    $assert(($crossOwner['error']['code'] ?? null) === 'RESOURCE_NOT_FOUND', 'Cross-Student cancel is non-enumerating.');

    $forbidden = $run(['action' => 'register', 'activityId' => '66666666-6666-4666-8666-666666666666', 'studentId' => '22222222-2222-4222-8222-222222222222']);
    $assert(($forbidden['error']['code'] ?? null) === 'VALIDATION_FAILED', 'Client Student selector is rejected.');

    $cancel = $run(['action' => 'cancel', 'registrationId' => $registrationId, 'reason' => 'Đổi lịch học']);
    $assert(($cancel['data']['registration']['status'] ?? null) === 'cancelled', 'Real endpoint persists cancellation.');
    $persisted = $pdo->prepare('SELECT status,cancellationReason FROM activity_registrations WHERE id=?');
    $persisted->execute([$registrationId]);
    $row = $persisted->fetch(PDO::FETCH_ASSOC);
    $assert(($row['status'] ?? null) === 'cancelled' && ($row['cancellationReason'] ?? null) === 'Đổi lịch học', 'Cancellation persists in database.');
} finally {
    unset($row, $persisted, $pdo);
    gc_collect_cycles();
    if (is_string($databasePath) && is_file($databasePath)) {
        unlink($databasePath);
    }
}

echo "learner_activity_registration_endpoint_runtime_test: OK\n";
