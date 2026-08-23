<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$worker = ($argv[1] ?? '') === '--worker';
if ($worker) {
    $method = strtoupper((string) ($argv[2] ?? 'POST'));
    $body = (string) ($argv[3] ?? '{}');
    $userId = (string) ($argv[4] ?? '');
    $role = (string) ($argv[5] ?? 'student');
    $csrf = (string) ($argv[6] ?? 'csrf-phase7');
    $config = require dirname(__DIR__) . '/config/database.php';
    $GLOBALS['__TALENTHUB_TEST_PDO__'] = (new Connection($config))->connect();
    $GLOBALS['__TALENTHUB_TEST_SESSION__'] = [
        'csrfToken' => 'csrf-phase7',
    ];
    if ($userId !== '') {
        $GLOBALS['__TALENTHUB_TEST_SESSION__']['user'] = [
            'id' => $userId,
            'email' => $userId . '@phase7.test',
            'fullName' => 'Phase 7 Endpoint User',
            'role' => $role,
            'status' => 'active',
        ];
    }
    $GLOBALS['__TALENTHUB_TEST_BODY__'] = $body;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/app/learner/api/v1/applications.php';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf;
    $_SERVER['HTTP_X_REQUEST_ID'] = '01JPHASE7ENDPOINT' . substr(hash('sha256', $body . $userId), 0, 10);
    require dirname(__DIR__) . '/app/learner/api/v1/applications.php';
    exit(3);
}

$schema = (string) ((require dirname(__DIR__) . '/config/database.php')['database'] ?? '');
if (!filter_var(getenv('TALENTHUB_DISPOSABLE_TEST_DB'), FILTER_VALIDATE_BOOL)
    || preg_match('/\Atalenthub_phase7_rehearsal_\d{14}\z/', $schema) !== 1) {
    fwrite(STDERR, "learner_application_endpoint_runtime_test: NOT RUN (exact disposable gate required)\n");
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

$student = $pdo->query('SELECT sp.id AS studentId, sp.userId, u.roleId FROM student_profiles sp INNER JOIN users u ON u.id = sp.userId ORDER BY sp.id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$enterpriseMember = $pdo->query('SELECT userId, enterpriseId FROM enterprise_members ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$assert(is_array($student) && is_array($enterpriseMember), 'student and enterprise fixtures exist');
foreach (['internship_application.create_own', 'internship_application.read_own', 'internship_application.withdraw_own', 'privacy_consent.manage_own'] as $permission) {
    $statement = $pdo->prepare('INSERT IGNORE INTO role_permissions (roleId, permissionId) SELECT :roleId, id FROM permissions WHERE code = :permission');
    $statement->execute(['roleId' => $student['roleId'], 'permission' => $permission]);
}

$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
$deadline = (new DateTimeImmutable('+30 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
$post = static function (PDO $pdo, string $enterpriseId, string $title, string $now, string $deadline): string {
    $id = Uuid::v4();
    $statement = $pdo->prepare("INSERT INTO internship_posts (id,enterpriseId,title,field,status,location,workType,duration,educationLevel,description,skillsJson,slots,deadline,createdAt,updatedAt) VALUES (:id,:enterpriseId,:title,'IT','active','Remote','hybrid','3 months','university','Endpoint runtime','[]',1,:deadline,:createdAt,:updatedAt)");
    $statement->execute(['id' => $id, 'enterpriseId' => $enterpriseId, 'title' => $title, 'deadline' => $deadline, 'createdAt' => $now, 'updatedAt' => $now]);
    return $id;
};
$consent = $pdo->prepare("SELECT id FROM privacy_consents WHERE studentId=:studentId AND scope='application_profile_share' AND isGranted=1 AND grantedAt IS NOT NULL AND revokedAt IS NULL ORDER BY grantedAt DESC LIMIT 1");
$consent->execute(['studentId' => $student['studentId']]);
if ($consent->fetchColumn() === false) {
    $pdo->prepare("INSERT INTO privacy_consents (id,studentId,scope,isGranted,policyVersion,grantedAt,createdAt) VALUES (:id,:studentId,'application_profile_share',1,'phase7-v1',:grantedAt,:createdAt)")->execute(['id' => Uuid::v4(), 'studentId' => $student['studentId'], 'grantedAt' => $now, 'createdAt' => $now]);
}

$startWorker = static function (array $body, string $userId, string $role = 'student', string $csrf = 'csrf-phase7') use ($assert): array {
    $command = [PHP_BINARY, __FILE__, '--worker', 'POST', json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $userId, $role, $csrf];
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    $assert(is_resource($process), 'endpoint worker starts');
    return [$process, $pipes];
};
$finishWorker = static function (array $running) use ($assert): array {
    [$process, $pipes] = $running;
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    $assert($exit === 0, "endpoint worker exits cleanly: {$stderr}");
    $payload = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
    $assert(is_array($payload), 'endpoint returns JSON');
    return $payload;
};
$run = static fn (array $body, string $userId, string $role = 'student', string $csrf = 'csrf-phase7'): array => $finishWorker($startWorker($body, $userId, $role, $csrf));

$postId = $post($pdo, (string) $enterpriseMember['enterpriseId'], 'Endpoint authorization', $now, $deadline);
$unauthenticated = $run(['action' => 'submit', 'postId' => $postId], '');
$assert(($unauthenticated['error']['code'] ?? null) === 'AUTHENTICATION_REQUIRED', 'runtime endpoint requires authentication');
$badCsrf = $run(['action' => 'submit', 'postId' => $postId], (string) $student['userId'], csrf: 'wrong');
$assert(($badCsrf['error']['code'] ?? null) === 'CSRF_INVALID', 'runtime endpoint rejects invalid CSRF');
$wrongRole = $run(['action' => 'submit', 'postId' => $postId], (string) $enterpriseMember['userId'], 'enterprise');
$assert(($wrongRole['error']['code'] ?? null) === 'PERMISSION_DENIED', 'runtime endpoint rejects non-student role');
$createPermission = $pdo->prepare("SELECT id FROM permissions WHERE code = 'internship_application.create_own' LIMIT 1");
$createPermission->execute();
$createPermissionId = (string) $createPermission->fetchColumn();
$assert($createPermissionId !== '', 'create-own permission fixture exists');
$revokeCreate = $pdo->prepare('DELETE FROM role_permissions WHERE roleId = :roleId AND permissionId = :permissionId');
$restoreCreate = $pdo->prepare('INSERT INTO role_permissions (roleId, permissionId) VALUES (:roleId, :permissionId)');
$revokeCreate->execute(['roleId' => $student['roleId'], 'permissionId' => $createPermissionId]);
try {
    $missingPermission = $run(['action' => 'submit', 'postId' => $postId], (string) $student['userId']);
    $assert(($missingPermission['error']['code'] ?? null) === 'PERMISSION_DENIED', 'authenticated student without create-own permission is denied');
} finally {
    $restoreCreate->execute(['roleId' => $student['roleId'], 'permissionId' => $createPermissionId]);
}

$concurrentPost = $post($pdo, (string) $enterpriseMember['enterpriseId'], 'Concurrent endpoint', $now, $deadline);
$first = $startWorker(['action' => 'submit', 'postId' => $concurrentPost, 'message' => 'first'], (string) $student['userId']);
$second = $startWorker(['action' => 'submit', 'postId' => $concurrentPost, 'message' => 'second'], (string) $student['userId']);
$responses = [$finishWorker($first), $finishWorker($second)];
$successes = array_filter($responses, static fn (array $payload): bool => ($payload['data']['application']['status'] ?? null) === 'submitted');
$duplicates = array_filter($responses, static fn (array $payload): bool => ($payload['error']['code'] ?? null) === 'DUPLICATE_APPLICATION');
$assert(count($successes) === 1 && count($duplicates) === 1, 'two concurrent HTTP submissions produce one success and one stable duplicate');
$count = $pdo->prepare('SELECT COUNT(*) FROM internship_applications WHERE postId=:postId AND studentId=:studentId');
$count->execute(['postId' => $concurrentPost, 'studentId' => $student['studentId']]);
$assert((int) $count->fetchColumn() === 1, 'concurrent endpoint submissions persist exactly one application');

$rollbackPost = $post($pdo, (string) $enterpriseMember['enterpriseId'], 'Endpoint rollback', $now, $deadline);
$previousName = $pdo->prepare('SELECT fullName FROM users WHERE id = :userId');
$previousName->execute(['userId' => $student['userId']]);
$nameBefore = (string) $previousName->fetchColumn();
$pdo->prepare("UPDATE users SET fullName = 'FORCE_ENDPOINT_ROLLBACK' WHERE id = :userId")->execute(['userId' => $student['userId']]);
$pdo->exec("ALTER TABLE application_profile_snapshots ADD CONSTRAINT chk_phase7_endpoint_snapshot_failure CHECK (JSON_UNQUOTE(JSON_EXTRACT(snapshotPayload, '$.student.fullName')) <> 'FORCE_ENDPOINT_ROLLBACK')");
try {
    $rolledBack = $run(['action' => 'submit', 'postId' => $rollbackPost], (string) $student['userId']);
    $assert(($rolledBack['error']['code'] ?? null) === 'SERVICE_UNAVAILABLE', 'endpoint returns stable failure after injected snapshot error: ' . json_encode($rolledBack));
} finally {
    $pdo->exec('ALTER TABLE application_profile_snapshots DROP CHECK chk_phase7_endpoint_snapshot_failure');
    $pdo->prepare('UPDATE users SET fullName = :fullName WHERE id = :userId')->execute(['fullName' => $nameBefore, 'userId' => $student['userId']]);
}
$count->execute(['postId' => $rollbackPost, 'studentId' => $student['studentId']]);
$assert((int) $count->fetchColumn() === 0, 'endpoint failure rolls back application');
$snapshotCount = $pdo->prepare('SELECT COUNT(*) FROM application_profile_snapshots aps INNER JOIN internship_applications ia ON ia.id=aps.applicationId WHERE ia.postId=:postId');
$snapshotCount->execute(['postId' => $rollbackPost]);
$assert((int) $snapshotCount->fetchColumn() === 0, 'endpoint failure rolls back snapshot');

echo "learner_application_endpoint_runtime_test: OK ({$assertions} assertions)\n";
