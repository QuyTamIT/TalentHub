<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';

function roadmap_api_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

/** @return array{status:int,body:array<string,mixed>,raw:string,stderr:string} */
function roadmap_api_execute(string $endpoint, string $database, array $server, array $body, array $session): array
{
    $payload = base64_encode((string) json_encode([
        'endpoint' => $endpoint,
        'database' => $database,
        'server' => $server,
        'body' => $body,
        'session' => $session,
    ], JSON_THROW_ON_ERROR));
    $runner = <<<'PHP'
<?php
$config = json_decode((string) base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
register_shutdown_function(static function (): void {
    fwrite(STDERR, '__HTTP_STATUS_CODE:' . (http_response_code() ?: 200) . '__');
});
$pdo = new PDO('sqlite:' . $config['database']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['__TALENTHUB_TEST_PDO__'] = $pdo;
$GLOBALS['__TALENTHUB_TEST_SESSION__'] = $config['session'];
$GLOBALS['__TALENTHUB_TEST_BODY__'] = json_encode($config['body'], JSON_THROW_ON_ERROR);
$GLOBALS['__TALENTHUB_TEST_ENV__'] = ['APP_ENV' => 'test', 'TALENTHUB_AI_ENABLED' => 'false'];
putenv('APP_ENV=test');
$_SERVER = array_merge([
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/' . basename($config['endpoint']),
    'CONTENT_TYPE' => 'application/json',
    'REMOTE_ADDR' => '127.0.0.9',
], $config['server']);
require $config['endpoint'];
PHP;
    $temporary = tempnam(sys_get_temp_dir(), 'roadmap-api-runner-');
    if (!is_string($temporary)) {
        throw new RuntimeException('Cannot create API runner.');
    }
    file_put_contents($temporary, $runner);
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($temporary) . ' ' . escapeshellarg($payload);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        unlink($temporary);
        throw new RuntimeException('Cannot start API runner.');
    }
    $stdout = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($process);
    unlink($temporary);
    preg_match('/__HTTP_STATUS_CODE:(\d+)__/', $stderr, $matches);
    $decoded = json_decode($stdout, true);
    return [
        'status' => isset($matches[1]) ? (int) $matches[1] : 200,
        'body' => is_array($decoded) ? $decoded : [],
        'raw' => $stdout,
        'stderr' => $stderr,
    ];
}

function roadmap_api_database(string $path): PDO
{
    @unlink($path);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE learner_onboarding_states (studentId TEXT PRIMARY KEY, status TEXT NOT NULL, acceptedAt TEXT NULL, completedAt TEXT NULL)');
    $pdo->exec('CREATE TABLE auth_rate_limits (bucketKey TEXT PRIMARY KEY, scope TEXT NOT NULL, failureCount INTEGER NOT NULL, windowStartedAt TEXT NOT NULL, blockedUntil TEXT NULL, updatedAt TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE learner_recommendation_runs (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, status TEXT NOT NULL, startedAt TEXT NOT NULL, createdAt TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE learner_recommendation_audit_events (id TEXT PRIMARY KEY, runId TEXT NOT NULL, studentId TEXT NOT NULL, action TEXT NOT NULL, createdAt TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE learner_ai_roadmaps (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, runId TEXT NULL, versionNumber INTEGER NOT NULL DEFAULT 1, status TEXT NOT NULL DEFAULT \'active\')');
    $pdo->exec('CREATE TABLE learner_ai_roadmap_phases (id TEXT PRIMARY KEY, roadmapId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE learner_ai_roadmap_tasks (id TEXT PRIMARY KEY, phaseId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE learner_ai_roadmap_task_events (id TEXT PRIMARY KEY, taskId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL, requestId TEXT NOT NULL, occurredAt TEXT NOT NULL, createdAt TEXT NOT NULL)');
    $pdo->exec("INSERT INTO roles VALUES ('student-role','student'),('teacher-role','teacher')");
    $pdo->exec("INSERT INTO users VALUES ('student-user','student-role','active'),('teacher-user','teacher-role','active')");
    $pdo->exec("INSERT INTO permissions VALUES ('read-permission','student_profile.read_own'),('write-permission','student_profile.update_own')");
    $pdo->exec("INSERT INTO role_permissions VALUES ('student-role','read-permission'),('student-role','write-permission')");
    $pdo->exec("INSERT INTO student_profiles VALUES ('11111111-1111-4111-8111-111111111111','student-user')");
    $pdo->exec("INSERT INTO learner_onboarding_states VALUES ('11111111-1111-4111-8111-111111111111','completed',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    return $pdo;
}

$roadmapEndpoint = dirname(__DIR__) . '/app/learner/api/v1/ai-roadmap.php';
$taskEndpoint = dirname(__DIR__) . '/app/learner/api/v1/ai-roadmap-task.php';
roadmap_api_assert(is_file($roadmapEndpoint), 'roadmap endpoint exists');
roadmap_api_assert(is_file($taskEndpoint), 'roadmap task endpoint exists');

$database = sys_get_temp_dir() . '/talenthub-roadmap-api-' . bin2hex(random_bytes(6)) . '.sqlite';
$pdo = roadmap_api_database($database);
$student = ['id'=>'student-user','role'=>'student','status'=>'active'];
$teacher = ['id'=>'teacher-user','role'=>'teacher','status'=>'active'];

$anonymous = roadmap_api_execute($roadmapEndpoint, $database, ['REQUEST_METHOD'=>'GET'], [], []);
roadmap_api_assert($anonymous['status'] === 401 && ($anonymous['body']['error']['code'] ?? '') === 'AUTH_REQUIRED', 'anonymous read is denied: ' . json_encode($anonymous));
$wrongRole = roadmap_api_execute($roadmapEndpoint, $database, ['REQUEST_METHOD'=>'GET'], [], ['user'=>$teacher]);
roadmap_api_assert($wrongRole['status'] === 403 && ($wrongRole['body']['error']['code'] ?? '') === 'PERMISSION_DENIED', 'non-student read is denied');
$empty = roadmap_api_execute($roadmapEndpoint, $database, ['REQUEST_METHOD'=>'GET'], [], ['user'=>$student]);
roadmap_api_assert($empty['status'] === 200 && ($empty['body']['data']['state'] ?? '') === 'not_generated', 'owned GET returns not_generated');
$pdo->exec("INSERT INTO learner_recommendation_runs VALUES ('pending-roadmap-run','11111111-1111-4111-8111-111111111111','pending','2026-08-24 12:00:00','2026-08-24 12:00:00')");
$pdo->exec("INSERT INTO learner_recommendation_audit_events VALUES ('pending-roadmap-audit','pending-roadmap-run','11111111-1111-4111-8111-111111111111','roadmap_run_created','2026-08-24 12:00:00')");
$pending = roadmap_api_execute($roadmapEndpoint, $database, ['REQUEST_METHOD'=>'GET'], [], ['user'=>$student]);
roadmap_api_assert($pending['status'] === 200 && ($pending['body']['data']['state'] ?? '') === 'pending', 'owned GET returns roadmap-specific pending state');
$pdo->exec("DELETE FROM learner_recommendation_audit_events");
$pdo->exec("DELETE FROM learner_recommendation_runs");

$missingCsrf = roadmap_api_execute($roadmapEndpoint, $database, ['REQUEST_METHOD'=>'POST','HTTP_X_IDEMPOTENCY_KEY'=>'roadmap-generate-0001'], [], ['user'=>$student,'csrfToken'=>'csrf-ok']);
roadmap_api_assert($missingCsrf['status'] === 403 && ($missingCsrf['body']['error']['code'] ?? '') === 'CSRF_INVALID', 'generate requires CSRF');
$unknown = roadmap_api_execute($roadmapEndpoint, $database, ['REQUEST_METHOD'=>'POST','HTTP_X_CSRF_TOKEN'=>'csrf-ok','HTTP_X_IDEMPOTENCY_KEY'=>'roadmap-generate-0002'], ['studentId'=>'another'], ['user'=>$student,'csrfToken'=>'csrf-ok']);
roadmap_api_assert($unknown['status'] === 422 && ($unknown['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'generate rejects owner fields');
$badAction = roadmap_api_execute($roadmapEndpoint, $database, ['REQUEST_METHOD'=>'POST','HTTP_X_CSRF_TOKEN'=>'csrf-ok','HTTP_X_IDEMPOTENCY_KEY'=>'roadmap-generate-0003'], ['action'=>'delete'], ['user'=>$student,'csrfToken'=>'csrf-ok']);
roadmap_api_assert($badAction['status'] === 422 && ($badAction['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'generate accepts only generate or refresh');

$invalidTask = roadmap_api_execute($taskEndpoint, $database, ['REQUEST_METHOD'=>'POST','HTTP_X_CSRF_TOKEN'=>'csrf-ok','HTTP_X_IDEMPOTENCY_KEY'=>'roadmap-task-key-0001'], ['taskId'=>'not-a-uuid','status'=>'completed'], ['user'=>$student,'csrfToken'=>'csrf-ok']);
roadmap_api_assert($invalidTask['status'] === 422 && ($invalidTask['body']['error']['details'][0]['code'] ?? '') === 'INVALID_UUID', 'task id must be UUID');
$invalidStatus = roadmap_api_execute($taskEndpoint, $database, ['REQUEST_METHOD'=>'POST','HTTP_X_CSRF_TOKEN'=>'csrf-ok','HTTP_X_IDEMPOTENCY_KEY'=>'roadmap-task-key-0002'], ['taskId'=>'22222222-2222-4222-8222-222222222222','status'=>'not_started'], ['user'=>$student,'csrfToken'=>'csrf-ok']);
roadmap_api_assert($invalidStatus['status'] === 422 && ($invalidStatus['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'task status is allowlisted');

$foreignTask = '33333333-3333-4333-8333-333333333333';
$pdo->exec("INSERT INTO learner_ai_roadmaps(id,studentId) VALUES ('foreign-roadmap','foreign-student')");
$pdo->exec("INSERT INTO learner_ai_roadmap_phases VALUES ('foreign-phase','foreign-roadmap')");
$pdo->exec("INSERT INTO learner_ai_roadmap_tasks VALUES ('{$foreignTask}','foreign-phase')");
$crossOwner = roadmap_api_execute($taskEndpoint, $database, ['REQUEST_METHOD'=>'POST','HTTP_X_CSRF_TOKEN'=>'csrf-ok','HTTP_X_IDEMPOTENCY_KEY'=>'roadmap-task-key-0003'], ['taskId'=>$foreignTask,'status'=>'completed'], ['user'=>$student,'csrfToken'=>'csrf-ok']);
roadmap_api_assert($crossOwner['status'] === 409 && ($crossOwner['body']['error']['code'] ?? '') === 'ROADMAP_TASK_UPDATE_REJECTED', 'cross-owner task is rejected without revealing ownership');
roadmap_api_assert(!str_contains($crossOwner['raw'], 'foreign-student'), 'cross-owner response does not leak owner id');

$ownedTask = '44444444-4444-4444-8444-444444444444';
$pdo->exec("INSERT INTO learner_ai_roadmaps(id,studentId) VALUES ('owned-roadmap','11111111-1111-4111-8111-111111111111')");
$pdo->exec("INSERT INTO learner_ai_roadmap_phases VALUES ('owned-phase','owned-roadmap')");
$pdo->exec("INSERT INTO learner_ai_roadmap_tasks VALUES ('{$ownedTask}','owned-phase')");
$taskHeaders = ['REQUEST_METHOD'=>'POST','HTTP_X_CSRF_TOKEN'=>'csrf-ok','HTTP_X_IDEMPOTENCY_KEY'=>'roadmap-task-key-0004'];
$firstUpdate = roadmap_api_execute($taskEndpoint, $database, $taskHeaders, ['taskId'=>$ownedTask,'status'=>'completed'], ['user'=>$student,'csrfToken'=>'csrf-ok']);
roadmap_api_assert($firstUpdate['status'] === 200 && ($firstUpdate['body']['data']['state'] ?? '') === 'task_updated', 'owned task progress is updated');
$repeatUpdate = roadmap_api_execute($taskEndpoint, $database, $taskHeaders, ['taskId'=>$ownedTask,'status'=>'completed'], ['user'=>$student,'csrfToken'=>'csrf-ok']);
roadmap_api_assert($repeatUpdate['status'] === 200 && ($repeatUpdate['body']['data']['reused'] ?? false) === true, 'same task idempotency key reuses the event');
$eventCount = (int) $pdo->query("SELECT COUNT(*) FROM learner_ai_roadmap_task_events WHERE taskId = '{$ownedTask}'")->fetchColumn();
roadmap_api_assert($eventCount === 1, 'idempotent replay creates one task event');
$rateLimited = roadmap_api_execute($taskEndpoint, $database, ['REQUEST_METHOD'=>'POST','HTTP_X_CSRF_TOKEN'=>'csrf-ok','HTTP_X_IDEMPOTENCY_KEY'=>'roadmap-task-key-0005'], ['taskId'=>$ownedTask,'status'=>'completed'], ['user'=>$student,'csrfToken'=>'csrf-ok']);
roadmap_api_assert($rateLimited['status'] === 429 && ($rateLimited['body']['error']['code'] ?? '') === 'RATE_LIMIT_EXCEEDED', 'AI mutation rate limit is enforced');

$roadmapSource = (string) file_get_contents($roadmapEndpoint);
$taskSource = (string) file_get_contents($taskEndpoint);
foreach (['TALENTHUB_AI_API_KEY','TALENTHUB_AI_API_URL','provider_request_id','response_hash','input_hash','raw_snapshot'] as $secretField) {
    roadmap_api_assert(!str_contains($roadmapSource . $taskSource, $secretField), "endpoints never expose {$secretField}");
}

@unlink($database);
echo "learner_ai_roadmap_api_test: OK\n";
