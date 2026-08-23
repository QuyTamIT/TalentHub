<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$worker = ($argv[1] ?? '') === '--worker';
if ($worker) {
    $method = strtoupper((string) ($argv[2] ?? 'GET'));
    $body = (string) ($argv[3] ?? '{}');
    $userId = (string) ($argv[4] ?? '');
    $role = (string) ($argv[5] ?? 'student');
    $csrf = (string) ($argv[6] ?? 'csrf-phase8');
    $queryParams = json_decode((string) ($argv[7] ?? '{}'), true) ?: [];

    $config = require dirname(__DIR__) . '/config/database.php';
    $GLOBALS['__TALENTHUB_TEST_PDO__'] = (new Connection($config))->connect();
    $GLOBALS['__TALENTHUB_TEST_SESSION__'] = [
        'csrfToken' => 'csrf-phase8',
    ];
    if ($userId !== '') {
        $GLOBALS['__TALENTHUB_TEST_SESSION__']['user'] = [
            'id' => $userId,
            'email' => $userId . '@phase8.test',
            'fullName' => 'Phase 8 Endpoint User',
            'role' => $role,
            'status' => 'active',
        ];
    }
    $GLOBALS['__TALENTHUB_TEST_BODY__'] = $body;
    $queryString = http_build_query($queryParams);
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/app/learner/api/v1/notifications.php' . ($queryString !== '' ? '?' . $queryString : '');
    $_SERVER['QUERY_STRING'] = $queryString;
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf;
    $_SERVER['HTTP_X_REQUEST_ID'] = '01JPHASE8ENDPOINT' . substr(hash('sha256', $body . $userId), 0, 10);
    $_GET = $queryParams;


    require dirname(__DIR__) . '/app/learner/api/v1/notifications.php';
    exit(3);
}

$schema = (string) ((require dirname(__DIR__) . '/config/database.php')['database'] ?? '');
if (!filter_var(getenv('TALENTHUB_DISPOSABLE_TEST_DB'), FILTER_VALIDATE_BOOL)
    || preg_match('/\Atalenthub_phase8_rehearsal_\d{14}\z/', $schema) !== 1) {
    fwrite(STDERR, "learner_notifications_endpoint_runtime_test: NOT RUN (exact disposable gate required)\n");
    exit(2);
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->exec("SET time_zone = '+00:00'");

$students = $pdo->query('SELECT sp.id AS studentId, sp.userId, u.roleId FROM student_profiles sp INNER JOIN users u ON u.id = sp.userId ORDER BY sp.id LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
$assert(count($students) >= 2, 'at least 2 student fixtures exist');
$student1 = $students[0];
$student2 = $students[1];

$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
$notificationIds = [Uuid::v4(), Uuid::v4(), Uuid::v4(), Uuid::v4()];
$insertNotification = $pdo->prepare(<<<'SQL'
    INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, readAt, createdAt)
    VALUES (:id, :userId, :eventKey, 'activity_checkin_committed', 'Phase 8 runtime', 'Runtime contract notification', '/app/learner/checkin.php', :readAt, :createdAt)
SQL);
$insertNotification->execute(['id' => $notificationIds[0], 'userId' => $student1['userId'], 'eventKey' => 'runtime:student1:unread:1', 'readAt' => null, 'createdAt' => $now]);
$insertNotification->execute(['id' => $notificationIds[1], 'userId' => $student1['userId'], 'eventKey' => 'runtime:student1:unread:2', 'readAt' => null, 'createdAt' => $now]);
$insertNotification->execute(['id' => $notificationIds[2], 'userId' => $student1['userId'], 'eventKey' => 'runtime:student1:read', 'readAt' => $now, 'createdAt' => $now]);
$insertNotification->execute(['id' => $notificationIds[3], 'userId' => $student2['userId'], 'eventKey' => 'runtime:student2:unread', 'readAt' => null, 'createdAt' => $now]);

$startWorker = static function (string $method, array $body, string $userId, string $role = 'student', string $csrf = 'csrf-phase8', array $queryParams = []): array {
    $command = [
        PHP_BINARY,
        __FILE__,
        '--worker',
        $method,
        json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $userId,
        $role,
        $csrf,
        json_encode($queryParams, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ];
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start worker process');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $decoded = json_decode((string) $stdout, true);
    return [
        'exitCode' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'json' => is_array($decoded) ? $decoded : null,
    ];
};

// 1. Unauthenticated test (401)
$res = $startWorker('GET', [], '');
$assert(($res['json']['error']['code'] ?? '') === 'AUTHENTICATION_REQUIRED', 'unauthenticated returns 401 AUTHENTICATION_REQUIRED');

// 2. Non-student role test (403)
$res = $startWorker('GET', [], $student1['userId'], 'teacher');
$assert(($res['json']['error']['code'] ?? '') === 'PERMISSION_DENIED', 'teacher role on student endpoint returns 403');

// 3. Authenticated Student missing exact permission
// Create test user with no permissions
$classId = $pdo->query('SELECT classId FROM student_profiles WHERE classId IS NOT NULL LIMIT 1')->fetchColumn();

$unpermUserId = Uuid::v4();
$unpermRoleId = Uuid::v4();
$unpermStudentId = Uuid::v4();
$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
$pdo->prepare("INSERT INTO roles (id, code, name, description, isSystem, createdAt, updatedAt) VALUES (:id, 'noperm', 'NoPerm', 'No perm', 0, :c1, :u1)")
    ->execute(['id' => $unpermRoleId, 'c1' => $now, 'u1' => $now]);
$pdo->prepare("INSERT INTO users (id, roleId, fullName, email, passwordHash, status, createdAt, updatedAt) VALUES (:id, :roleId, 'No Perm User', 'noperm@test.com', 'hash', 'active', :c2, :u2)")
    ->execute(['id' => $unpermUserId, 'roleId' => $unpermRoleId, 'c2' => $now, 'u2' => $now]);
$pdo->prepare("INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus, createdAt, updatedAt) VALUES (:id, :userId, :classId, '2005-01-01', '0901234567', 'active', :c3, :u3)")
    ->execute(['id' => $unpermStudentId, 'userId' => $unpermUserId, 'classId' => $classId ?: null, 'c3' => $now, 'u3' => $now]);


$res = $startWorker('GET', [], $unpermUserId, 'student');
$assert(($res['json']['error']['code'] ?? '') === 'PERMISSION_DENIED', 'missing exact permission returns 403');



// 4. Invalid CSRF on mutation (403)
$res = $startWorker('PATCH', ['action' => 'mark-all-read'], $student1['userId'], 'student', 'bad-csrf');
$assert(($res['json']['error']['code'] ?? '') === 'CSRF_INVALID', 'invalid CSRF returns 403 CSRF_INVALID');

// 5. Valid GET notifications
$res = $startWorker('GET', [], $student1['userId'], 'student', 'csrf-phase8', ['limit' => '10', 'offset' => '0']);
$assert(isset($res['json']['data']), 'GET notifications succeeds with data');
$assert(isset($res['json']['data']['notifications']), 'notifications field present in data');
$assert(isset($res['json']['data']['unreadCount']), 'unreadCount field present in data');
$assert(isset($res['json']['data']['preferences']), 'preferences field present in data');

// 5a. Server-side unread filter is owner-scoped and returns scoped pagination.
$res = $startWorker('GET', [], $student1['userId'], 'student', 'csrf-phase8', ['filter' => 'unread', 'limit' => '10', 'offset' => '0']);
$assert(($res['json']['data']['pagination']['total'] ?? null) === 2, 'unread filter total is scoped to Student 1 unread rows');
$assert(count($res['json']['data']['notifications'] ?? []) === 2, 'unread filter returns only unread rows');
foreach ($res['json']['data']['notifications'] ?? [] as $notification) {
    $assert(($notification['userId'] ?? '') === $student1['userId'], 'unread list never leaks another owner');
    $assert(($notification['readAt'] ?? null) === null, 'unread list excludes read notifications');
}

// 6. Update preference
$res = $startWorker('PATCH', [
    'action' => 'update-preference',
    'notificationType' => 'activity_registration_created',
    'inAppEnabled' => false,
    'emailEnabled' => true,
], $student1['userId'], 'student');
$assert(isset($res['json']['data']), 'update-preference succeeds with data');
$assert(($res['json']['data']['preference']['inAppEnabled'] ?? null) === false, 'inAppEnabled updated to false');
$assert(($res['json']['data']['preference']['emailEnabled'] ?? null) === true, 'emailEnabled updated to true');

// 6a. JSON strings are not accepted as booleans.
$res = $startWorker('PATCH', [
    'action' => 'update-preference',
    'notificationType' => 'activity_registration_created',
    'inAppEnabled' => 'false',
    'emailEnabled' => false,
], $student1['userId'], 'student');
$assert(($res['json']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'string false is rejected instead of coerced to true');

// 6b. Mark one is owner scoped and mark-all returns server-confirmed unread count.
$res = $startWorker('PATCH', [
    'action' => 'mark-read',
    'notificationId' => $notificationIds[3],
], $student1['userId'], 'student');
$assert(($res['json']['error']['code'] ?? '') === 'RESOURCE_NOT_FOUND', 'Student 1 cannot mark Student 2 notification');

$res = $startWorker('PATCH', [
    'action' => 'mark-read',
    'notificationId' => $notificationIds[0],
], $student1['userId'], 'student');
$assert(($res['json']['data']['unreadCount'] ?? null) === 1, 'mark-one returns server-confirmed unread count');

$res = $startWorker('PATCH', ['action' => 'mark-all-read'], $student1['userId'], 'student');
$assert(($res['json']['data']['unreadCount'] ?? null) === 0, 'mark-all returns zero unread count');

// 7. Pagination bounds validation
$res = $startWorker('GET', [], $student1['userId'], 'student', 'csrf-phase8', ['limit' => '200']);
$assert(($res['json']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'limit > 100 rejected with 422');


$res = $startWorker('GET', [], $student1['userId'], 'student', 'csrf-phase8', ['offset' => '-1']);
$assert(($res['json']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'negative offset rejected with 422');

$res = $startWorker('GET', [], $student1['userId'], 'student', 'csrf-phase8', ['limit' => '1.5']);
$assert(($res['json']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'fractional limit is rejected');

$res = $startWorker('GET', [], $student1['userId'], 'student', 'csrf-phase8', ['filter' => 'archived']);
$assert(($res['json']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'unknown notification filter is rejected');

$res = $startWorker('GET', [], $student1['userId'], 'student', 'csrf-phase8', ['view' => 'preferences']);
$assert(($res['json']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'unknown GET query field is rejected');

echo "learner_notifications_endpoint_runtime_test: OK ({$assertions} assertions)\n";
