<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

$workerMode = ($argv[1] ?? '') === '--worker';
if ($workerMode) {
    $databasePath = (string) ($argv[2] ?? '');
    $endpoint = (string) ($argv[3] ?? '');
    $method = strtoupper((string) ($argv[4] ?? 'GET'));
    $id = (string) ($argv[5] ?? '');
    $body = (string) ($argv[6] ?? '{}');
    $userId = (string) ($argv[7] ?? 'user-a');
    $csrf = (string) ($argv[8] ?? 'csrf-test-token');

    if (!in_array($endpoint, ['certificates', 'profile-shares'], true)) {
        fwrite(STDERR, "Invalid endpoint worker target\n");
        exit(2);
    }

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
    $_SERVER['REQUEST_URI'] = '/app/learner/api/v1/' . $endpoint . '.php' . ($id !== '' ? '?id=' . rawurlencode($id) : '');
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf;

    require dirname(__DIR__) . '/app/learner/api/v1/' . $endpoint . '.php';
    exit(3);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$databasePath = tempnam(sys_get_temp_dir(), 'talenthub-phase3-endpoint-');
$assert(is_string($databasePath) && $databasePath !== '', 'Disposable SQLite path is available.');

try {
    $pdo = new PDO('sqlite:' . $databasePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(<<<'SQL'
CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL);
CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL);
CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL, PRIMARY KEY (roleId, permissionId));
CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, status TEXT NOT NULL, fullName TEXT NOT NULL, email TEXT NOT NULL);
CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, phone TEXT NULL, classId TEXT NULL);
CREATE TABLE certificates (
  id TEXT PRIMARY KEY, studentId TEXT NOT NULL, title TEXT NOT NULL, issuingOrganization TEXT NOT NULL,
  issueDate TEXT NOT NULL, expiryDate TEXT NULL, credentialId TEXT NULL, credentialUrl TEXT NULL,
  verificationStatus TEXT NOT NULL DEFAULT 'unverified', verifiedBy TEXT NULL, verifiedAt TEXT NULL,
  createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL
);
CREATE TABLE privacy_consents (
  id TEXT PRIMARY KEY, studentId TEXT NOT NULL, scope TEXT NOT NULL, isGranted INTEGER NOT NULL,
  policyVersion TEXT NOT NULL, grantedAt TEXT NULL, revokedAt TEXT NULL, createdAt TEXT NOT NULL
);
CREATE TABLE student_profile_shares (
  id TEXT PRIMARY KEY, studentId TEXT NOT NULL, consentId TEXT NULL, tokenHash TEXT NOT NULL UNIQUE,
  sharedFieldsJson TEXT NOT NULL, expiresAt TEXT NOT NULL, revokedAt TEXT NULL, createdAt TEXT NOT NULL
);
INSERT INTO roles (id, code) VALUES ('role-student', 'student');
INSERT INTO permissions (id, code) VALUES
  ('permission-certificate', 'certificate.manage_own'),
  ('permission-share', 'student_profile.share_own'),
  ('permission-consent', 'privacy_consent.manage_own');
INSERT INTO role_permissions (roleId, permissionId) VALUES
  ('role-student', 'permission-certificate'),
  ('role-student', 'permission-share'),
  ('role-student', 'permission-consent');
INSERT INTO users (id, roleId, status, fullName, email) VALUES
  ('user-a', 'role-student', 'active', 'Student A', 'a@example.test'),
  ('user-b', 'role-student', 'active', 'Student B', 'b@example.test');
INSERT INTO student_profiles (id, userId) VALUES ('student-a', 'user-a'), ('student-b', 'user-b');
INSERT INTO certificates
  (id, studentId, title, issuingOrganization, issueDate, verificationStatus, createdAt, updatedAt)
VALUES
  ('0191316b-4000-7000-8000-000000000101', 'student-a', 'Before patch', 'Issuer', '2026-01-01', 'unverified', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('0191316b-4000-7000-8000-000000000102', 'student-a', 'Delete me', 'Issuer', '2026-01-01', 'unverified', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('0191316b-4000-7000-8000-000000000103', 'student-b', 'Student B evidence', 'Issuer', '2026-01-01', 'unverified', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
INSERT INTO privacy_consents
  (id, studentId, scope, isGranted, policyVersion, grantedAt, createdAt)
VALUES ('consent-a', 'student-a', 'profile_share', 1, 'profile-sharing-1.0', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);
INSERT INTO student_profile_shares
  (id, studentId, consentId, tokenHash, sharedFieldsJson, expiresAt, createdAt)
VALUES ('0191316b-4000-7000-8000-000000000201', 'student-a', 'consent-a', 'hash-a', '["fullName"]', '2099-01-01 00:00:00', CURRENT_TIMESTAMP);
SQL
    );

    $run = static function (
        string $endpoint,
        string $method,
        string $id,
        array $body,
        string $userId = 'user-a',
        string $csrf = 'csrf-test-token',
    ) use ($databasePath, $assert): array {
        $command = [
            PHP_BINARY,
            __FILE__,
            '--worker',
            $databasePath,
            $endpoint,
            $method,
            $id,
            json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $userId,
            $csrf,
        ];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        $assert(is_resource($process), 'Endpoint worker process starts.');
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $assert($exitCode === 0, "Endpoint worker exits cleanly: {$stderr}");
        $response = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        $assert(is_array($response), 'Endpoint returns a JSON object.');
        return $response;
    };

    $patch = $run('certificates', 'PATCH', '0191316b-4000-7000-8000-000000000101', ['title' => 'After patch']);
    $assert(
        ($patch['data']['certificate']['title'] ?? null) === 'After patch',
        'PATCH certificate executes through the real endpoint with query ID: ' . json_encode($patch, JSON_UNESCAPED_SLASHES),
    );

    $delete = $run('certificates', 'DELETE', '', ['id' => '0191316b-4000-7000-8000-000000000102']);
    $assert(($delete['data']['deleted'] ?? null) === true, 'DELETE certificate executes through the real endpoint with body ID.');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM certificates WHERE id = '0191316b-4000-7000-8000-000000000102'")->fetchColumn() === 0, 'Endpoint delete persists.');

    $csrfDenied = $run('certificates', 'DELETE', '0191316b-4000-7000-8000-000000000101', [], 'user-a', 'wrong-token');
    $assert(($csrfDenied['error']['code'] ?? null) === 'CSRF_INVALID', 'Certificate mutation rejects an invalid CSRF token.');

    $crossOwner = $run('certificates', 'DELETE', '0191316b-4000-7000-8000-000000000103', [], 'user-a');
    $assert(
        ($crossOwner['error']['code'] ?? null) === 'RESOURCE_NOT_FOUND',
        'Certificate endpoint preserves non-enumerating cross-owner denial: ' . json_encode($crossOwner, JSON_UNESCAPED_SLASHES),
    );

    $revoke = $run('profile-shares', 'DELETE', '0191316b-4000-7000-8000-000000000201', []);
    $assert(($revoke['data']['revoked'] ?? null) === true, 'DELETE profile share executes through the real endpoint with query ID.');
    $shareState = $pdo->query("SELECT revokedAt FROM student_profile_shares WHERE id = '0191316b-4000-7000-8000-000000000201'")->fetchColumn();
    $consentState = $pdo->query("SELECT isGranted FROM privacy_consents WHERE id = 'consent-a'")->fetchColumn();
    $assert(is_string($shareState) && $shareState !== '', 'Endpoint revoke persists share revocation.');
    $assert((int) $consentState === 0, 'Endpoint revoke persists linked consent revocation.');
} finally {
    unset($pdo);
    if (is_string($databasePath) && is_file($databasePath)) {
        unlink($databasePath);
    }
}

echo "learner_phase_3_endpoint_runtime_test: OK\n";
