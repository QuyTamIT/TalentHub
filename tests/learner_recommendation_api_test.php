<?php

declare(strict_types=1);

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Rbac\Service\PermissionService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
foreach ([
    '/app/learner/api/JsonResponder.php',
    '/app/learner/api/LearnerApiContext.php',
] as $file) {
    $path = dirname(__DIR__) . $file;
    if (is_file($path)) {
        require_once $path;
    }
}

function learner_api_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function learner_api_expect(callable $operation, int $status, string $code): void
{
    try {
        $operation();
    } catch (ApiException $exception) {
        learner_api_assert($exception->status === $status && $exception->errorCode === $code, "API failure is {$status}/{$code}");
        return;
    }
    learner_api_assert(false, "expected API failure {$status}/{$code}");
}

function learner_api_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL)');
    $pdo->exec("INSERT INTO users (id, roleId) VALUES ('user-student', 'role-student'), ('user-teacher', 'role-teacher')");
    $pdo->exec("INSERT INTO student_profiles (id, userId) VALUES ('student-1', 'user-student')");
    $pdo->exec("INSERT INTO permissions (id, code) VALUES ('permission-read', 'student_profile.read_own'), ('permission-write', 'student_profile.update_own')");
    return $pdo;
}

function learner_api_context(PDO $pdo, array $user, string $requestId = 'request-api-0000001'): LearnerApiContext
{
    $_SESSION = ['user' => $user, 'csrfToken' => 'csrf-valid'];
    $session = new SessionManager(['name' => 'LEARNER_API_TEST', 'lifetime' => 3600, 'secure' => false, 'sameSite' => 'Lax']);
    return new LearnerApiContext($pdo, $session, new PermissionService($pdo), $requestId);
}

learner_api_assert(class_exists(LearnerApiContext::class), 'learner API context exists');
learner_api_assert(class_exists(JsonResponder::class), 'learner JSON responder exists');

$pdo = learner_api_fixture();
$studentUser = ['id' => 'user-student', 'email' => 'student@example.test', 'fullName' => 'Student', 'role' => 'student', 'status' => 'active'];
$teacherUser = ['id' => 'user-teacher', 'email' => 'teacher@example.test', 'fullName' => 'Teacher', 'role' => 'teacher', 'status' => 'active'];

$_SESSION = [];
$anonymous = new LearnerApiContext($pdo, new SessionManager(['name' => 'LEARNER_API_TEST', 'lifetime' => 3600, 'secure' => false, 'sameSite' => 'Lax']), new PermissionService($pdo), 'request-api-0000002');
learner_api_expect(static fn (): string => $anonymous->studentId('student_profile.read_own'), 401, 'AUTHENTICATION_REQUIRED');

$wrongRole = learner_api_context($pdo, $teacherUser);
learner_api_expect(static fn (): string => $wrongRole->studentId('student_profile.read_own'), 403, 'PERMISSION_DENIED');

$missingPermission = learner_api_context($pdo, $studentUser);
learner_api_expect(static fn (): string => $missingPermission->studentId('student_profile.read_own'), 403, 'PERMISSION_DENIED');

$pdo->exec("INSERT INTO role_permissions (roleId, permissionId) VALUES ('role-student', 'permission-read'), ('role-student', 'permission-write')");
$context = learner_api_context($pdo, $studentUser);
learner_api_assert($context->studentId('student_profile.read_own') === 'student-1', 'student id is resolved from the authenticated user profile');
learner_api_expect(static function () use ($context): void { $context->mutation('csrf-wrong'); }, 403, 'CSRF_INVALID');
learner_api_expect(static fn (): array => $context->allowedInput(['studentId' => 'student-2'], []), 422, 'VALIDATION_FAILED');
learner_api_expect(static fn (): string => $context->idempotencyKey('short'), 422, 'VALIDATION_FAILED');
learner_api_assert($context->idempotencyKey('idempotency-key-0001') === 'idempotency-key-0001', 'idempotency key accepts the approved format');

$success = JsonResponder::success(['state' => 'ready_rule'], 'request-api-0000003');
learner_api_assert($success['status'] === 200 && $success['payload']['meta']['requestId'] === 'request-api-0000003', 'every successful envelope includes request id');
$error = JsonResponder::error(new ApiException(422, 'VALIDATION_FAILED', 'Invalid'), 'request-api-0000004');
learner_api_assert($error['status'] === 422 && $error['payload']['meta']['requestId'] === 'request-api-0000004', 'every error envelope includes request id');

echo "learner_recommendation_api_test: OK\n";
