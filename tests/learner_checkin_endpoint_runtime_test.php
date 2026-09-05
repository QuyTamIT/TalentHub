<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

$workerMode = ($argv[1] ?? '') === '--worker';
if ($workerMode) {
    $databasePath = (string) ($argv[2] ?? '');
    $method = strtoupper((string) ($argv[3] ?? 'GET'));
    $body = (string) ($argv[4] ?? '{}');
    $userId = (string) ($argv[5] ?? '33333333-3333-4333-8333-333333333333');
    $csrf = (string) ($argv[6] ?? 'csrf-test-token');

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
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/app/learner/api/v1/checkins.php?limit=25&offset=0';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf;
    $_SERVER['HTTP_X_REQUEST_ID'] = '01JPHASE5ENDPOINTTEST00001';

    require dirname(__DIR__) . '/app/learner/api/v1/checkins.php';
    exit(3);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$databasePath = tempnam(sys_get_temp_dir(), 'talenthub-phase5-endpoint-');
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
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL);
CREATE TABLE activities (id TEXT PRIMARY KEY, title TEXT, category TEXT, startAt TEXT, endAt TEXT, status TEXT);
CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, updatedAt TEXT);
CREATE TABLE activity_qr_sessions (id TEXT PRIMARY KEY, activityId TEXT, createdByTeacherId TEXT, tokenHash TEXT UNIQUE, status TEXT, expiresAt TEXT, maxScans INTEGER, usedScans INTEGER, revokedAt TEXT, updatedAt TEXT);
CREATE TABLE activity_experience_policies (activityId TEXT PRIMARY KEY, confirmedHours NUMERIC NOT NULL);
CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT UNIQUE, qrSessionId TEXT, status TEXT, checkedInAt TEXT, confirmedAt TEXT, createdAt TEXT);
CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT UNIQUE, hours NUMERIC, status TEXT, auditReason TEXT, confirmedAt TEXT, createdAt TEXT);
CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT, action TEXT, entityType TEXT, entityId TEXT, requestId TEXT, ipAddress TEXT, metadata TEXT, createdAt TEXT);
CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId,eventKey));
CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId,notificationType));
CREATE TABLE auth_rate_limits (
    bucketKey TEXT PRIMARY KEY,
    scope TEXT NOT NULL CHECK (scope IN ('identity', 'ip')),
    failureCount INTEGER NOT NULL DEFAULT 0,
    windowStartedAt TEXT NOT NULL,
    blockedUntil TEXT NULL,
    updatedAt TEXT NOT NULL
);

INSERT INTO roles VALUES ('role-student','student'),('role-student-denied','student_denied');
INSERT INTO permissions VALUES ('permission-create','checkin.create_own'),('permission-read','experience_log.read_own');
INSERT INTO role_permissions VALUES ('role-student','permission-create'),('role-student','permission-read');
INSERT INTO users VALUES
 ('33333333-3333-4333-8333-333333333333','role-student','active','Student A','a@example.test'),
 ('44444444-4444-4444-8444-444444444444','role-student','active','Student B','b@example.test'),
 ('55555555-5555-4555-8555-555555555555','role-student-denied','active','Student Denied','denied@example.test');
INSERT INTO student_profiles VALUES
 ('11111111-1111-4111-8111-111111111111','33333333-3333-4333-8333-333333333333'),
 ('22222222-2222-4222-8222-222222222222','44444444-4444-4444-8444-444444444444'),
 ('66666666-6666-4666-8666-666666666666','55555555-5555-4555-8555-555555555555');
SQL
    );

    $fixture = static function (
        PDO $pdo,
        string $suffix,
        string $token,
        string $studentId = '11111111-1111-4111-8111-111111111111',
        string $activityStatus = 'ongoing',
        string $registrationStatus = 'approved',
        string $sessionStatus = 'active',
        string $expiresAt = '2099-08-22 12:00:00.000000',
        int $maxScans = 10,
        int $usedScans = 0,
        bool $withPolicy = true,
    ): array {
        $activityId = "activity-{$suffix}";
        $registrationId = "registration-{$suffix}";
        $sessionId = "session-{$suffix}";
        $activity = $pdo->prepare('INSERT INTO activities VALUES (?,?,?,?,?,?)');
        $activity->execute([$activityId, "Activity {$suffix}", 'phase5', '2026-08-22 08:00:00.000000', '2026-08-22 12:00:00.000000', $activityStatus]);
        $registration = $pdo->prepare('INSERT INTO activity_registrations VALUES (?,?,?,?,?)');
        $registration->execute([$registrationId, $activityId, $studentId, $registrationStatus, '2026-08-22 07:00:00.000000']);
        $session = $pdo->prepare('INSERT INTO activity_qr_sessions VALUES (?,?,?,?,?,?,?,?,?,?)');
        $session->execute([$sessionId, $activityId, 'teacher-a', hash('sha256', $token), $sessionStatus, $expiresAt, $maxScans, $usedScans, $sessionStatus === 'revoked' ? '2026-08-22 07:30:00.000000' : null, '2026-08-22 07:00:00.000000']);
        if ($withPolicy) {
            $policy = $pdo->prepare('INSERT INTO activity_experience_policies VALUES (?,?)');
            $policy->execute([$activityId, '2.50']);
        }
        return compact('activityId', 'registrationId', 'sessionId');
    };

    $success = $fixture($pdo, 'success', 'opaque-success');
    $fixture($pdo, 'expired', 'opaque-expired', expiresAt: '2000-01-01 00:00:00.000000');
    $fixture($pdo, 'revoked', 'opaque-revoked', sessionStatus: 'revoked');
    $fixture($pdo, 'exhausted', 'opaque-exhausted', maxScans: 1, usedScans: 1);
    $fixture($pdo, 'not-ongoing', 'opaque-not-ongoing', activityStatus: 'published');
    $fixture($pdo, 'pending', 'opaque-pending', registrationStatus: 'pending');
    $fixture($pdo, 'waitlisted', 'opaque-waitlisted', registrationStatus: 'waitlisted');
    $fixture($pdo, 'rejected', 'opaque-rejected', registrationStatus: 'rejected');
    $fixture($pdo, 'cancelled', 'opaque-cancelled', registrationStatus: 'cancelled');
    $fixture($pdo, 'attended', 'opaque-attended', registrationStatus: 'attended');
    $fixture($pdo, 'missing-policy', 'opaque-missing-policy', withPolicy: false);

    $run = static function (
        string $method,
        array $body = [],
        string $userId = '33333333-3333-4333-8333-333333333333',
        string $csrf = 'csrf-test-token',
    ) use ($databasePath, $assert): array {
        $command = [PHP_BINARY, __FILE__, '--worker', $databasePath, $method, json_encode($body, JSON_THROW_ON_ERROR), $userId, $csrf];
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
        // Rate-limit behavior has its own clock-controlled suite. Keep each endpoint
        // contract case isolated so accumulated buckets cannot mask its domain error.
        $rateLimitFixture = new PDO('sqlite:' . $databasePath);
        $rateLimitFixture->exec('DELETE FROM auth_rate_limits');
        unset($rateLimitFixture);
        return ['payload' => $decoded, 'raw' => (string) $stdout];
    };

    $badCsrf = $run('POST', ['token' => 'opaque-success'], csrf: 'wrong');
    $assert(($badCsrf['payload']['error']['code'] ?? null) === 'CSRF_INVALID', 'POST rejects invalid CSRF.');
    $spoof = $run('POST', ['token' => 'opaque-success', 'studentId' => '22222222-2222-4222-8222-222222222222']);
    $assert(($spoof['payload']['error']['code'] ?? null) === 'VALIDATION_FAILED', 'POST rejects client-selected Student identity.');
    $denied = $run('POST', ['token' => 'opaque-success'], '55555555-5555-4555-8555-555555555555');
    $assert(($denied['payload']['error']['code'] ?? null) === 'PERMISSION_DENIED', 'POST requires exact create-own permission.');
    $crossStudent = $run('POST', ['token' => 'opaque-success'], '44444444-4444-4444-8444-444444444444');
    $assert(($crossStudent['payload']['error']['code'] ?? null) === 'REGISTRATION_NOT_ELIGIBLE', 'Cross-Student token use cannot select another registration.');

    foreach ([
        'opaque-expired' => 'QR_SESSION_EXPIRED',
        'opaque-revoked' => 'QR_SESSION_REVOKED',
        'opaque-exhausted' => 'QR_SESSION_EXHAUSTED',
        'opaque-not-ongoing' => 'ACTIVITY_NOT_CHECKIN_ELIGIBLE',
        'opaque-pending' => 'REGISTRATION_NOT_ELIGIBLE',
        'opaque-waitlisted' => 'REGISTRATION_NOT_ELIGIBLE',
        'opaque-rejected' => 'REGISTRATION_NOT_ELIGIBLE',
        'opaque-cancelled' => 'REGISTRATION_NOT_ELIGIBLE',
        'opaque-attended' => 'REGISTRATION_NOT_ELIGIBLE',
        'opaque-missing-policy' => 'EXPERIENCE_POLICY_MISSING',
        'opaque-invalid' => 'QR_TOKEN_INVALID',
    ] as $token => $expectedCode) {
        $response = $run('POST', ['token' => $token]);
        $assert(($response['payload']['error']['code'] ?? null) === $expectedCode, "{$expectedCode} is stable.");
        $assert(!str_contains($response['raw'], $token), "{$expectedCode} response never echoes the raw token.");
    }

    $created = $run('POST', ['token' => 'opaque-success']);
    $assert(($created['payload']['data']['status'] ?? null) === 'confirmed', 'Successful POST returns a confirmed check-in.');
    $assert(($created['payload']['data']['experience']['hours'] ?? null) === '2.50', 'Successful POST returns the locked policy snapshot.');
    $assert(!str_contains($created['raw'], 'opaque-success'), 'Success response never echoes raw token.');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM checkins WHERE registrationId='registration-success'")->fetchColumn() === 1, 'Exactly one check-in is persisted.');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM experience_logs WHERE checkinId IN (SELECT id FROM checkins WHERE registrationId='registration-success')")->fetchColumn() === 1, 'Exactly one experience is persisted.');
    $assert((string) $pdo->query("SELECT status FROM activity_registrations WHERE id='registration-success'")->fetchColumn() === 'attended', 'Registration transitions to attended.');
    $assert((int) $pdo->query("SELECT usedScans FROM activity_qr_sessions WHERE id='session-success'")->fetchColumn() === 1, 'Scan count increments exactly once.');
    $auditMetadata = (string) $pdo->query("SELECT metadata FROM audit_logs WHERE action='checkin.confirmed' ORDER BY createdAt DESC LIMIT 1")->fetchColumn();
    $assert(!str_contains($auditMetadata, 'opaque-success'), 'Audit metadata never contains raw token.');

    $duplicate = $run('POST', ['token' => 'opaque-success']);
    $assert(($duplicate['payload']['error']['code'] ?? null) === 'CHECKIN_ALREADY_EXISTS', 'Replay returns stable duplicate error.');
    $assert((int) $pdo->query("SELECT usedScans FROM activity_qr_sessions WHERE id='session-success'")->fetchColumn() === 1, 'Replay does not consume another scan.');

    $pdo->exec("INSERT INTO checkins VALUES ('checkin-unconfirmed','registration-pending','session-pending','pending',NULL,NULL,'2026-08-22 07:00:00.000000')");
    $pdo->exec("INSERT INTO experience_logs VALUES ('experience-unconfirmed','11111111-1111-4111-8111-111111111111','activity-pending','checkin-unconfirmed',9.00,'pending',NULL,NULL,'2026-08-22 07:00:00.000000')");

    $history = $run('GET');
    $items = $history['payload']['data']['items'] ?? [];
    $assert(count($items) === 1 && ($items[0]['activity']['id'] ?? null) === $success['activityId'], 'GET returns only the authenticated Student history.');
    $deniedHistory = $run('GET', [], '55555555-5555-4555-8555-555555555555');
    $assert(($deniedHistory['payload']['error']['code'] ?? null) === 'PERMISSION_DENIED', 'GET requires exact read-own permission.');
} finally {
    unset($pdo);
    gc_collect_cycles();
    if (is_string($databasePath) && is_file($databasePath)) {
        unlink($databasePath);
    }
}

echo "learner_checkin_endpoint_runtime_test: OK\n";
