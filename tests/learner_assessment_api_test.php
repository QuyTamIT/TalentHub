<?php

declare(strict_types=1);

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Api\JsonResponder;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Learner\Assessment\Service\EducationBandRequired;
use TalentHub\Learner\Assessment\Service\EducationBandResolver;
use TalentHub\Rbac\Service\PermissionService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/api/JsonResponder.php';
require_once dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';

const API_STUDENT_A = '11111111-1111-4111-8111-111111111111';
const API_STUDENT_B = '22222222-2222-4222-8222-222222222222';
const API_USER_STUDENT_A = 'user-student-a';
const API_USER_STUDENT_B = 'user-student-b';
const API_USER_TEACHER = 'user-teacher';

const API_TEST_HOLLAND_ID = '33333333-3333-4333-8333-000000000001';
const API_TEST_MBTI_ID = '33333333-3333-4333-8333-000000000002';
const API_TEST_DISC_ID = '33333333-3333-4333-8333-000000000003';
const API_TEST_MI_ID = '33333333-3333-4333-8333-000000000004';

const API_VERSION_HOLLAND_ID = '44444444-4444-4444-8444-000000000001';
const API_VERSION_MBTI_ID = '44444444-4444-4444-8444-000000000002';
const API_VERSION_DISC_ID = '44444444-4444-4444-8444-000000000003';
const API_VERSION_MI_ID = '44444444-4444-4444-8444-000000000004';

function api_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function api_test_expect_band_required(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (EducationBandRequired) {
        return;
    } catch (Throwable $exception) {
        fwrite(STDERR, "Unexpected exception for {$message}: {$exception->getMessage()}\n");
        exit(1);
    }

    fwrite(STDERR, "Expected EducationBandRequired: {$message}\n");
    exit(1);
}

function api_test_create_db(string $dbPath): PDO
{
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Auth & RBAC
    $pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT NOT NULL, fullName TEXT NOT NULL, roleId TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE schools (id CHAR(36) NOT NULL PRIMARY KEY, name TEXT NOT NULL, level TEXT NULL)');
    $pdo->exec('CREATE TABLE classes (id CHAR(36) NOT NULL PRIMARY KEY, schoolId CHAR(36) NOT NULL, name TEXT NOT NULL, gradeLevel INTEGER NOT NULL, academicYear TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE student_profiles (id CHAR(36) NOT NULL PRIMARY KEY, userId TEXT NOT NULL, classId CHAR(36) NULL)');
    $pdo->exec('CREATE TABLE notifications (id TEXT PRIMARY KEY, userId TEXT NOT NULL, eventKey TEXT NULL, notificationType TEXT NOT NULL, title TEXT NOT NULL, message TEXT NOT NULL, deepLink TEXT NULL, readAt TEXT NULL, createdAt TEXT NOT NULL, UNIQUE(userId,eventKey))');
    $pdo->exec('CREATE TABLE learner_notification_preferences (studentId TEXT NOT NULL, notificationType TEXT NOT NULL, inAppEnabled INTEGER NOT NULL DEFAULT 1, emailEnabled INTEGER NOT NULL DEFAULT 0, updatedAt TEXT NOT NULL, PRIMARY KEY(studentId,notificationType))');
    $pdo->exec("CREATE TABLE learner_onboarding_states (studentId TEXT PRIMARY KEY, status TEXT NOT NULL, acceptedAt TEXT NULL, completedAt TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec('CREATE TABLE audit_logs (id TEXT PRIMARY KEY, userId TEXT NULL, action TEXT NOT NULL, entityType TEXT NULL, entityId TEXT NULL, requestId TEXT NULL, ipAddress TEXT NULL, metadata TEXT NULL, createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)');

    // Seed roles, permissions, users
    $pdo->exec("INSERT INTO roles (id, code) VALUES ('role-student', 'student'), ('role-teacher', 'teacher')");
    $pdo->exec("INSERT INTO permissions (id, code) VALUES ('p-read', 'student_profile.read_own'), ('p-write', 'student_profile.update_own')");
    $pdo->exec("INSERT INTO role_permissions (roleId, permissionId) VALUES ('role-student', 'p-read'), ('role-student', 'p-write')");

    $pdo->exec("INSERT INTO users (id, email, fullName, roleId, status) VALUES ('" . API_USER_STUDENT_A . "', 'student.a@test.local', 'Student A', 'role-student', 'active'), ('" . API_USER_STUDENT_B . "', 'student.b@test.local', 'Student B', 'role-student', 'active'), ('" . API_USER_TEACHER . "', 'teacher@test.local', 'Teacher', 'role-teacher', 'active')");

    $classId = 'class-high-001';
    $schoolId = 'school-001';
    $pdo->exec("INSERT INTO schools (id, name, level) VALUES ('{$schoolId}', 'High School', 'Trung học Phổ thông')");
    $pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('{$classId}', '{$schoolId}', '11A', 11, '2026-2027')");
    $pdo->exec("INSERT INTO student_profiles (id, userId, classId) VALUES ('" . API_STUDENT_A . "', '" . API_USER_STUDENT_A . "', '{$classId}'), ('" . API_STUDENT_B . "', '" . API_USER_STUDENT_B . "', '{$classId}')");
    $pdo->exec("INSERT INTO learner_onboarding_states(studentId, status, acceptedAt) VALUES ('" . API_STUDENT_A . "', 'accepted', CURRENT_TIMESTAMP)");

    // Canonical assessment schema
    $pdo->exec('CREATE TABLE talent_tests (id CHAR(36) NOT NULL PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE test_questions (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, code TEXT NOT NULL, content TEXT NOT NULL, optionsJson TEXT NOT NULL, status TEXT NOT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, FOREIGN KEY (testId) REFERENCES talent_tests(id))');
    $pdo->exec('CREATE TABLE learner_assessment_versions (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, version TEXT NOT NULL, scoringVersion TEXT NOT NULL, schemaHash CHAR(64) NOT NULL, status TEXT NOT NULL, publishedAt TEXT NULL, createdAt TEXT NOT NULL, FOREIGN KEY (testId) REFERENCES talent_tests(id))');
    $pdo->exec('CREATE TABLE learner_assessment_question_versions (id CHAR(36) NOT NULL PRIMARY KEY, versionId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, position INTEGER NOT NULL, dimensionCode TEXT NOT NULL, required INTEGER NOT NULL, createdAt TEXT NOT NULL, FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id), FOREIGN KEY (questionId) REFERENCES test_questions(id))');
    $pdo->exec('CREATE TABLE test_attempts (id CHAR(36) NOT NULL PRIMARY KEY, testId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, status TEXT NOT NULL, startedAt TEXT NOT NULL, submittedAt TEXT NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, FOREIGN KEY (testId) REFERENCES talent_tests(id), FOREIGN KEY (studentId) REFERENCES student_profiles(id))');
    $pdo->exec('CREATE TABLE learner_assessment_attempt_metadata (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL UNIQUE, versionId CHAR(36) NOT NULL, status TEXT NOT NULL, expiresAt TEXT NULL, submittedAt TEXT NULL, inputHash CHAR(64) NULL, createdAt TEXT NOT NULL, updatedAt TEXT NOT NULL, FOREIGN KEY (attemptId) REFERENCES test_attempts(id), FOREIGN KEY (versionId) REFERENCES learner_assessment_versions(id))');
    $pdo->exec('CREATE TABLE learner_assessment_answers (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL, questionId CHAR(36) NOT NULL, answerJson TEXT NOT NULL, answeredAt TEXT NOT NULL, UNIQUE (attemptId, questionId), FOREIGN KEY (attemptId) REFERENCES learner_assessment_attempt_metadata(attemptId), FOREIGN KEY (questionId) REFERENCES test_questions(id))');
    $pdo->exec('CREATE TABLE test_results (id CHAR(36) NOT NULL PRIMARY KEY, attemptId CHAR(36) NOT NULL UNIQUE, resultCode TEXT NOT NULL, summary TEXT NOT NULL, dimensionScoresJson TEXT NOT NULL, scoringVersion TEXT NOT NULL, createdAt TEXT NOT NULL, FOREIGN KEY (attemptId) REFERENCES test_attempts(id))');
    $pdo->exec('CREATE TABLE teacher_profiles (id CHAR(36) NOT NULL PRIMARY KEY, userId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE activities (id CHAR(36) NOT NULL PRIMARY KEY, title TEXT NOT NULL, category TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE assessment_criteria (id CHAR(36) NOT NULL PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, minScore NUMERIC, maxScore NUMERIC)');
    $pdo->exec('CREATE TABLE assessments (id CHAR(36) NOT NULL PRIMARY KEY, teacherId CHAR(36) NOT NULL, studentId CHAR(36) NOT NULL, activityId CHAR(36) NOT NULL, overallScore NUMERIC, comment TEXT, status TEXT NOT NULL, publishedAt TEXT NULL)');
    $pdo->exec('CREATE TABLE assessment_scores (id CHAR(36) NOT NULL PRIMARY KEY, assessmentId CHAR(36) NOT NULL, criteriaId CHAR(36) NOT NULL, score NUMERIC)');

    // Seed 4 published tests for 'high' band
    $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . API_TEST_HOLLAND_ID . "', 'holland_high', 'Holland High', 'interest', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . API_TEST_MBTI_ID . "', 'mbti_high', 'MBTI High', 'personality', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . API_TEST_DISC_ID . "', 'disc_high', 'DISC High', 'personality', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO talent_tests (id, code, name, type, status, createdAt, updatedAt) VALUES ('" . API_TEST_MI_ID . "', 'multiple_intelligence_high', 'MI High', 'aptitude', 'published', '{$now}', '{$now}')");

    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . API_VERSION_HOLLAND_ID . "', '" . API_TEST_HOLLAND_ID . "', '1.0.0', 'holland-riasec-1.0', 'hash1', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . API_VERSION_MBTI_ID . "', '" . API_TEST_MBTI_ID . "', '1.0.0', 'mbti-education-1.0', 'hash2', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . API_VERSION_DISC_ID . "', '" . API_TEST_DISC_ID . "', '1.0.0', 'disc-education-1.0', 'hash3', 'published', '{$now}', '{$now}')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, schemaHash, status, publishedAt, createdAt) VALUES ('" . API_VERSION_MI_ID . "', '" . API_TEST_MI_ID . "', '1.0.0', 'multiple-intelligence-1.0', 'hash4', 'published', '{$now}', '{$now}')");

    // Seed Holland questions
    $optionsJson = json_encode([['value' => 1, 'label' => '1'], ['value' => 2, 'label' => '2'], ['value' => 3, 'label' => '3'], ['value' => 4, 'label' => '4'], ['value' => 5, 'label' => '5']]);
    foreach (['R', 'I', 'A', 'S', 'E', 'C'] as $idx => $dim) {
        $qId = sprintf('55555555-5555-4555-8555-%012d', $idx + 1);
        $qvId = sprintf('66666666-6666-4666-8666-%012d', $idx + 1);
        $pdo->exec("INSERT INTO test_questions (id, testId, code, content, optionsJson, status, createdAt, updatedAt) VALUES ('{$qId}', '" . API_TEST_HOLLAND_ID . "', 'Q_H_{$dim}', 'Holland Question {$dim}', '{$optionsJson}', 'published', '{$now}', '{$now}')");
        $pdo->exec("INSERT INTO learner_assessment_question_versions (id, versionId, questionId, position, dimensionCode, required, createdAt) VALUES ('{$qvId}', '" . API_VERSION_HOLLAND_ID . "', '{$qId}', " . ($idx + 1) . ", '{$dim}', 1, '{$now}')");
    }

    return $pdo;
}

function execute_endpoint(string $endpointFile, array $server = [], array $get = [], array $post = [], array $sessionData = [], ?string $dbPath = null, ?string $rawBody = null): array
{
    $phpExe = 'D:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';

    $payload = [
        'endpoint' => $endpointFile,
        'server' => $server,
        'get' => $get,
        'post' => $post,
        'session' => $sessionData,
        'db' => $dbPath,
        'rawBody' => $rawBody,
    ];
    $jsonPayload = base64_encode((string) json_encode($payload));

    $runnerCode = <<<PHP
<?php
register_shutdown_function(function () {
    \$code = http_response_code() ?: 200;
    fwrite(STDERR, "__HTTP_STATUS_CODE:" . \$code . "__");
});

\$raw = base64_decode('{$jsonPayload}');
\$config = json_decode(\$raw, true);

if (!empty(\$config['db'])) {
    \$pdo = new PDO('sqlite:' . \$config['db']);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->exec('PRAGMA foreign_keys = ON');
    \$GLOBALS['__TALENTHUB_TEST_PDO__'] = \$pdo;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

\$uri = '/' . basename(\$config['endpoint']);
if (!empty(\$config['get'])) {
    \$uri .= '?' . http_build_query(\$config['get']);
}

\$_SERVER = array_merge([
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => \$uri,
    'HTTP_ACCEPT' => 'application/json',
], \$config['server'] ?? []);

\$_GET = \$config['get'] ?? [];
\$_POST = \$config['post'] ?? [];
\$GLOBALS['__TALENTHUB_TEST_SESSION__'] = \$config['session'] ?? [];

if (\$config['rawBody'] !== null) {
    \$GLOBALS['__TALENTHUB_TEST_BODY__'] = \$config['rawBody'];
} elseif (!empty(\$config['post'])) {
    \$GLOBALS['__TALENTHUB_TEST_BODY__'] = json_encode(\$config['post']);
}

require \$config['endpoint'];
PHP;

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($phpExe, $descriptors, $pipes);
    if (!is_resource($proc)) {
        throw new RuntimeException('Failed to start PHP child process.');
    }

    fwrite($pipes[0], $runnerCode);
    fclose($pipes[0]);

    $stdout = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    proc_close($proc);

    $httpStatus = 200;
    if (preg_match('/__HTTP_STATUS_CODE:(\d+)__/', $stderr, $matches)) {
        $httpStatus = (int) $matches[1];
    }

    $decoded = json_decode($stdout, true) ?: [];

    return [
        'status' => $httpStatus,
        'body' => $decoded,
        'raw' => $stdout,
        'stderr' => $stderr,
    ];
}

// 1. Verify endpoint files exist
$assessmentsEndpoint = dirname(__DIR__) . '/app/learner/api/v1/assessments.php';
$attemptsEndpoint = dirname(__DIR__) . '/app/learner/api/v1/assessment-attempts.php';
$answersEndpoint = dirname(__DIR__) . '/app/learner/api/v1/assessment-answers.php';
$submitEndpoint = dirname(__DIR__) . '/app/learner/api/v1/assessment-submit.php';

$endpointFiles = [$assessmentsEndpoint, $attemptsEndpoint, $answersEndpoint, $submitEndpoint];
foreach ($endpointFiles as $file) {
    api_test_assert(is_file($file), "Endpoint file exists: {$file}");
}

// 2. Setup SQLite test database
$dbPath = sys_get_temp_dir() . '/talent_test_assessment_api_' . uniqid() . '.sqlite';
$pdo = api_test_create_db($dbPath);

// University/college school level is authoritative even when the study-year grade is 1-4.
$pdo->exec("INSERT INTO schools (id, name, level) VALUES ('school-university-001', 'Đại học FPT', 'Đại học')");
$pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('class-university-year-1', 'school-university-001', 'Năm 1', 1, '2026-2027')");
$pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('class-university-year-4', 'school-university-001', 'Năm 4', 4, '2026-2027')");
$pdo->exec("UPDATE student_profiles SET classId = 'class-university-year-1' WHERE id = '" . API_STUDENT_B . "'");
$bandResolver = new EducationBandResolver($pdo);
api_test_assert($bandResolver->resolve(API_STUDENT_B, null) === 'college', 'University year 1 resolves to college without a confirmation prompt');
api_test_assert($bandResolver->resolve(API_STUDENT_B, 'high') === 'college', 'University level cannot be overridden by a stale client band');
$pdo->exec("UPDATE student_profiles SET classId = 'class-university-year-4' WHERE id = '" . API_STUDENT_B . "'");
api_test_assert($bandResolver->resolve(API_STUDENT_B, null) === 'college', 'University year 4 boundary resolves to college');

// Primary grades and unknown school levels use the latest safe inference defaults.
$pdo->exec("INSERT INTO schools (id, name, level) VALUES ('school-primary-001', 'Primary School', 'Tiểu học')");
$pdo->exec("INSERT INTO schools (id, name, level) VALUES ('school-unknown-001', 'Unknown School', NULL)");
$pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('class-primary-grade-5', 'school-primary-001', '5A', 5, '2026-2027')");
$pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear) VALUES ('class-unknown-grade-1', 'school-unknown-001', 'Year 1', 1, '2026-2027')");
$pdo->exec("UPDATE student_profiles SET classId = 'class-primary-grade-5' WHERE id = '" . API_STUDENT_B . "'");
api_test_assert($bandResolver->resolve(API_STUDENT_B, null) === 'college', 'Primary grade 5 resolves to the supported college catalog');
api_test_assert($bandResolver->resolve(API_STUDENT_B, 'middle') === 'college', 'Known primary grade takes precedence over a conflicting confirmed band');
$pdo->exec("UPDATE student_profiles SET classId = 'class-unknown-grade-1' WHERE id = '" . API_STUDENT_B . "'");
api_test_assert($bandResolver->resolve(API_STUDENT_B, null) === 'college', 'Unknown-school grade 1 resolves to the supported college catalog');
$pdo->exec("UPDATE student_profiles SET classId = 'class-high-001' WHERE id = '" . API_STUDENT_B . "'");

// Verify LearnerApiContext exposes required service methods
$session = new SessionManager(['name' => 'LEARNER_API_TEST', 'lifetime' => 3600, 'secure' => false, 'sameSite' => 'Lax']);
$context = new LearnerApiContext($pdo, $session, new PermissionService($pdo), 'req-test-001');

api_test_assert(method_exists($context, 'assessmentCatalogService'), 'LearnerApiContext has assessmentCatalogService');
api_test_assert(method_exists($context, 'assessmentService'), 'LearnerApiContext has assessmentService');
api_test_assert($context->assessmentCatalogService() instanceof \TalentHub\Learner\Assessment\Service\AssessmentCatalogService, 'assessmentCatalogService returns instance');
api_test_assert($context->assessmentService() instanceof \TalentHub\Learner\Data\Service\LearnerAssessmentService, 'assessmentService returns instance');

$userStudentA = ['id' => API_USER_STUDENT_A, 'email' => 'a@test.local', 'fullName' => 'Student A', 'role' => 'student', 'status' => 'active'];
$userStudentB = ['id' => API_USER_STUDENT_B, 'email' => 'b@test.local', 'fullName' => 'Student B', 'role' => 'student', 'status' => 'active'];
$userTeacher = ['id' => API_USER_TEACHER, 'email' => 't@test.local', 'fullName' => 'Teacher', 'role' => 'teacher', 'status' => 'active'];

// A. Anonymous request returns 401 / AUTH_REQUIRED
$anonRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['band' => 'high'], [], [], $dbPath);
api_test_assert($anonRes['status'] === 401, 'Anonymous request returns 401');
api_test_assert(($anonRes['body']['error']['code'] ?? '') === 'AUTH_REQUIRED', 'Anonymous error code is AUTH_REQUIRED');
api_test_assert(!empty($anonRes['body']['meta']['requestId']), 'Error envelope includes requestId');

// B. Teacher request returns 403 / PERMISSION_DENIED
$teacherRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['band' => 'high'], [], ['user' => $userTeacher], $dbPath);
api_test_assert($teacherRes['status'] === 403, 'Teacher request returns 403');
api_test_assert(($teacherRes['body']['error']['code'] ?? '') === 'PERMISSION_DENIED', 'Teacher error code is PERMISSION_DENIED');

// C. Catalog GET returns 200 and published assessments
$catalogRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['band' => 'high'], [], ['user' => $userStudentA], $dbPath);
api_test_assert($catalogRes['status'] === 200, 'Catalog request returns 200: ' . json_encode($catalogRes));
api_test_assert(count($catalogRes['body']['data']['assessments'] ?? []) === 4, 'Catalog returns 4 published assessments');

$explicitCatalogRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['view' => 'catalog', 'band' => 'high'], [], ['user' => $userStudentA], $dbPath);
api_test_assert($explicitCatalogRes['status'] === 200, 'Explicit catalog view returns 200: ' . json_encode($explicitCatalogRes));
api_test_assert(count($explicitCatalogRes['body']['data']['assessments'] ?? []) === 4, 'Explicit catalog view returns the published catalog');

// C2. A classless learner receives a successful safe-default catalog contract.
$pdo->exec("UPDATE student_profiles SET classId = NULL WHERE id = '" . API_STUDENT_B . "'");
$bandRequiredCatalogRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], [], [], ['user' => $userStudentB], $dbPath);
api_test_assert($bandRequiredCatalogRes['status'] === 200, 'Classless catalog request returns a successful safe-default contract: ' . json_encode($bandRequiredCatalogRes));
api_test_assert(($bandRequiredCatalogRes['body']['data']['education_band'] ?? '') === 'high', 'Classless catalog uses the safe high band default');
api_test_assert(count($bandRequiredCatalogRes['body']['data']['assessments'] ?? []) === 4, 'Classless catalog returns the published high-band assessments');
api_test_assert(!isset($bandRequiredCatalogRes['body']['error']), 'Safe-default catalog is never SOURCE_FAILURE');

$bandRequiredDetailRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['code' => 'holland'], [], ['user' => $userStudentB], $dbPath);
api_test_assert($bandRequiredDetailRes['status'] === 200, 'Classless detail request returns a successful safe-default contract: ' . json_encode($bandRequiredDetailRes));
api_test_assert(($bandRequiredDetailRes['body']['data']['assessment']['education_band'] ?? '') === 'high', 'Classless detail uses the safe high band default');
api_test_assert(($bandRequiredDetailRes['body']['data']['assessment']['code'] ?? '') === 'holland', 'Classless detail preserves the requested assessment code');
api_test_assert(count($bandRequiredDetailRes['body']['data']['questions'] ?? []) === 6, 'Classless detail returns the published high-band questions');
api_test_assert(is_array($bandRequiredDetailRes['body']['data']['history'] ?? null), 'Classless detail returns history');
api_test_assert(!isset($bandRequiredDetailRes['body']['error']), 'Safe-default detail is never SOURCE_FAILURE');
$pdo->exec("UPDATE student_profiles SET classId = 'class-high-001' WHERE id = '" . API_STUDENT_B . "'");

// D. GET with invalid band returns 422 / VALIDATION_FAILED
$invalidBandRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['band' => 'invalid_band'], [], ['user' => $userStudentA], $dbPath);
api_test_assert($invalidBandRes['status'] === 422, 'Invalid band returns 422');
api_test_assert(($invalidBandRes['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'Invalid band code is VALIDATION_FAILED');

// E. GET assessment detail returns definition, questions, history
$detailRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['code' => 'holland', 'band' => 'high'], [], ['user' => $userStudentA], $dbPath);
api_test_assert($detailRes['status'] === 200, 'Detail request returns 200');
api_test_assert(isset($detailRes['body']['data']['assessment']), 'Detail has assessment definition');
api_test_assert(($detailRes['body']['data']['assessment']['code'] ?? '') === 'holland', 'Detail exposes the public assessment code');
api_test_assert(count($detailRes['body']['data']['questions'] ?? []) === 6, 'Detail has 6 questions for Holland version');
api_test_assert(is_array($detailRes['body']['data']['history']), 'Detail has history array');

// E2. History mode is contract-locked to view only.
$historyRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['view' => 'history'], [], ['user' => $userStudentA], $dbPath);
api_test_assert($historyRes['status'] === 200, 'History request returns 200');
api_test_assert(($historyRes['body']['data']['assessment_history']['source'] ?? '') === 'assessment_engine', 'History uses assessment_engine source');
api_test_assert(($historyRes['body']['data']['teacher_evaluations']['source'] ?? '') === 'teacher_published_evaluation', 'Teacher evaluations use their own source');

$historyCodeRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['view' => 'history', 'code' => 'garbage'], [], ['user' => $userStudentA], $dbPath);
api_test_assert($historyCodeRes['status'] === 422, 'History rejects code parameter');
api_test_assert(($historyCodeRes['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'History code rejection is VALIDATION_FAILED');
api_test_assert(($historyCodeRes['body']['error']['details'][0]['code'] ?? '') === 'FIELD_NOT_ALLOWED', 'History code rejection uses FIELD_NOT_ALLOWED');

$historyBandRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['view' => 'history', 'band' => 'high'], [], ['user' => $userStudentA], $dbPath);
api_test_assert($historyBandRes['status'] === 422, 'History rejects band parameter');

$historyStudentRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['view' => 'history', 'studentId' => API_STUDENT_B], [], ['user' => $userStudentA], $dbPath);
api_test_assert($historyStudentRes['status'] === 422, 'History rejects studentId parameter');

$historyUnknownView = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['view' => 'bogus'], [], ['user' => $userStudentA], $dbPath);
api_test_assert($historyUnknownView['status'] === 422, 'Unknown view returns 422');
api_test_assert(($historyUnknownView['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'Unknown view returns VALIDATION_FAILED');

$historyPost = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'POST'], ['view' => 'history'], [], ['user' => $userStudentA], $dbPath);
api_test_assert($historyPost['status'] === 405, 'POST history returns 405');

// F. POST start/resume without CSRF returns 403 / CSRF_INVALID
$noCsrfRes = execute_endpoint($attemptsEndpoint, [
    'REQUEST_METHOD' => 'POST',
    'CONTENT_TYPE' => 'application/json',
], [], ['assessmentCode' => 'holland', 'educationBand' => 'high'], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($noCsrfRes['status'] === 403, 'Missing CSRF returns 403');
api_test_assert(($noCsrfRes['body']['error']['code'] ?? '') === 'CSRF_INVALID', 'Missing CSRF code is CSRF_INVALID');

// G. POST start with invalid educationBand returns 422 / VALIDATION_FAILED
$invalidBandPost = execute_endpoint($attemptsEndpoint, [
    'REQUEST_METHOD' => 'POST',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
], [], ['assessmentCode' => 'holland', 'educationBand' => 'invalid_band'], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($invalidBandPost['status'] === 422, 'Invalid band in POST returns 422');
api_test_assert(($invalidBandPost['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'Invalid band in POST code is VALIDATION_FAILED');

// G2. Mandatory onboarding enforces the canonical assessment sequence.
$laterStart = execute_endpoint($attemptsEndpoint, [
    'REQUEST_METHOD' => 'POST',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
], [], ['assessmentCode' => 'disc', 'educationBand' => 'high'], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($laterStart['status'] === 409, 'Cannot start DISC before Holland.');
api_test_assert(($laterStart['body']['error']['code'] ?? '') === 'ONBOARDING_SEQUENCE_REQUIRED', 'Sequence violation returns the onboarding code.');

// H. POST start resolves the authoritative grade band instead of trusting a conflicting client band.
$startRes = execute_endpoint($attemptsEndpoint, [
    'REQUEST_METHOD' => 'POST',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
], [], ['assessmentCode' => 'holland', 'educationBand' => 'college'], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($startRes['status'] === 200, 'Start attempt returns 200');
$attemptId = $startRes['body']['data']['id'] ?? '';
api_test_assert($attemptId !== '', 'Attempt ID is generated');
api_test_assert(($startRes['body']['data']['student_id'] ?? '') === API_STUDENT_A, 'Attempt is owned by Student A');
$startedTestId = $attemptId !== ''
    ? (string) $pdo->query("SELECT testId FROM test_attempts WHERE id = '" . $attemptId . "'")->fetchColumn()
    : '';
api_test_assert($startedTestId === API_TEST_HOLLAND_ID, 'Grade 11 forces the high assessment despite a conflicting college client band');

// I. GET attempt by Student A returns attempt with version bound questions
$getAttemptRes = execute_endpoint($attemptsEndpoint, ['REQUEST_METHOD' => 'GET'], ['attemptId' => $attemptId], [], ['user' => $userStudentA], $dbPath);
api_test_assert($getAttemptRes['status'] === 200, 'Owned attempt returns 200');
api_test_assert(($getAttemptRes['body']['data']['id'] ?? '') === $attemptId, 'Returned attempt has matching ID');
api_test_assert(count($getAttemptRes['body']['data']['questions'] ?? []) === 6, 'Attempt includes version bound questions');

// J. Student B reading attempt of Student A returns 404 / ASSESSMENT_ATTEMPT_NOT_FOUND (no leak of record existence)
$crossReadRes = execute_endpoint($attemptsEndpoint, ['REQUEST_METHOD' => 'GET'], ['attemptId' => $attemptId], [], ['user' => $userStudentB], $dbPath);
api_test_assert($crossReadRes['status'] === 404, 'Cross-student read returns 404');
api_test_assert(($crossReadRes['body']['error']['code'] ?? '') === 'ASSESSMENT_ATTEMPT_NOT_FOUND', 'Cross-student error code is ASSESSMENT_ATTEMPT_NOT_FOUND');

// K. PATCH answer without CSRF returns 403 / CSRF_INVALID
$firstQuestionId = '55555555-5555-4555-8555-000000000001';
$patchNoCsrf = execute_endpoint($answersEndpoint, [
    'REQUEST_METHOD' => 'PATCH',
    'CONTENT_TYPE' => 'application/json',
], [], ['attemptId' => $attemptId, 'questionId' => $firstQuestionId, 'answer' => 5], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($patchNoCsrf['status'] === 403, 'PATCH without CSRF returns 403');
api_test_assert(($patchNoCsrf['body']['error']['code'] ?? '') === 'CSRF_INVALID', 'PATCH without CSRF code is CSRF_INVALID');

// L. PATCH body with unknown field returns 422 / VALIDATION_FAILED
$patchExtraField = execute_endpoint($answersEndpoint, [
    'REQUEST_METHOD' => 'PATCH',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
], [], ['attemptId' => $attemptId, 'questionId' => $firstQuestionId, 'answer' => 5, 'extraField' => 'hack'], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($patchExtraField['status'] === 422, 'Extra field in PATCH returns 422');
api_test_assert(($patchExtraField['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'Extra field code is VALIDATION_FAILED');

// M. PATCH answer into another student's attempt returns 404 / ASSESSMENT_ATTEMPT_NOT_FOUND
$patchCrossStudent = execute_endpoint($answersEndpoint, [
    'REQUEST_METHOD' => 'PATCH',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
], [], ['attemptId' => $attemptId, 'questionId' => $firstQuestionId, 'answer' => 5], ['user' => $userStudentB, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($patchCrossStudent['status'] === 404, 'Cross-student answer returns 404');
api_test_assert(($patchCrossStudent['body']['error']['code'] ?? '') === 'ASSESSMENT_ATTEMPT_NOT_FOUND', 'Cross-student answer code is ASSESSMENT_ATTEMPT_NOT_FOUND');

// N. PATCH answers for all 6 questions for Student A
foreach (['R', 'I', 'A', 'S', 'E', 'C'] as $idx => $dim) {
    $qId = sprintf('55555555-5555-4555-8555-%012d', $idx + 1);
    $ansRes = execute_endpoint($answersEndpoint, [
        'REQUEST_METHOD' => 'PATCH',
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
    ], [], ['attemptId' => $attemptId, 'questionId' => $qId, 'answer' => 5], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
    api_test_assert($ansRes['status'] === 200, "Answer {$dim} saved successfully");
}

// O. POST submit with Idempotency Key
$idempotencyKey = 'assessment-submit-key-0001';
$submit1 = execute_endpoint($submitEndpoint, [
    'REQUEST_METHOD' => 'POST',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
    'HTTP_X_IDEMPOTENCY_KEY' => $idempotencyKey,
], [], ['attemptId' => $attemptId], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($submit1['status'] === 200, 'First submit returns 200');
$resultId1 = $submit1['body']['data']['result_id'] ?? $submit1['body']['data']['result']['id'] ?? $submit1['body']['data']['id'] ?? '';
api_test_assert($resultId1 !== '', 'Result ID is returned');
api_test_assert(($submit1['body']['data']['onboarding']['completed_count'] ?? null) === 1, 'First authoritative submit advances onboarding to 1/4.');
api_test_assert(($submit1['body']['data']['onboarding']['next_code'] ?? null) === 'mbti', 'Holland submit selects MBTI next.');
api_test_assert(($submit1['body']['data']['next_url'] ?? null) === '/app/learner/assessment.php?code=mbti', 'Submit returns a fixed next assessment URL.');

// Second submit with same key returns 200 and same result ID
$submit2 = execute_endpoint($submitEndpoint, [
    'REQUEST_METHOD' => 'POST',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
    'HTTP_X_IDEMPOTENCY_KEY' => $idempotencyKey,
], [], ['attemptId' => $attemptId], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($submit2['status'] === 200, 'Second submit returns 200');
$resultId2 = $submit2['body']['data']['result_id'] ?? $submit2['body']['data']['result']['id'] ?? $submit2['body']['data']['id'] ?? '';
api_test_assert($resultId1 === $resultId2, 'Same result ID returned on idempotent resubmit');

// Exactly 1 result row in database
$resultCount = (int) $pdo->query("SELECT COUNT(*) FROM test_results WHERE attemptId = '{$attemptId}'")->fetchColumn();
api_test_assert($resultCount === 1, 'Exactly one result row exists for the attempt');

// P. Retake locked after submit (within 90 days)
$retakeRes = execute_endpoint($attemptsEndpoint, [
    'REQUEST_METHOD' => 'POST',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
], [], ['assessmentCode' => 'holland', 'educationBand' => 'high'], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath);
api_test_assert($retakeRes['status'] === 422, 'Retake within 90 days is rejected with 422');

// Q. Unsupported HTTP method returns 405 / METHOD_NOT_ALLOWED
$methodNotAllowedRes = execute_endpoint($submitEndpoint, [
    'REQUEST_METHOD' => 'GET',
], [], [], ['user' => $userStudentA], $dbPath);
api_test_assert($methodNotAllowedRes['status'] === 405, 'GET on submit endpoint returns 405');
api_test_assert(($methodNotAllowedRes['body']['error']['code'] ?? '') === 'METHOD_NOT_ALLOWED', 'Error code is METHOD_NOT_ALLOWED');

// R. Malformed JSON / wrong Content-Type
$malformedRes = execute_endpoint($submitEndpoint, [
    'REQUEST_METHOD' => 'POST',
    'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => 'valid-csrf',
    'HTTP_X_IDEMPOTENCY_KEY' => 'assessment-submit-key-0002',
], [], [], ['user' => $userStudentA, 'csrfToken' => 'valid-csrf'], $dbPath, '{not-valid-json}');
api_test_assert($malformedRes['status'] === 400, 'Malformed JSON returns 400');
api_test_assert(($malformedRes['body']['error']['code'] ?? '') === 'INVALID_JSON', 'Malformed JSON error code is INVALID_JSON');

// S. Forced database failure returns 500 SOURCE_FAILURE without SQL leaks
// We query an invalid db where table was dropped
$brokenDbPath = sys_get_temp_dir() . '/talent_broken_db_' . uniqid() . '.sqlite';
$brokenPdo = new PDO('sqlite:' . $brokenDbPath);
$brokenRes = execute_endpoint($assessmentsEndpoint, ['REQUEST_METHOD' => 'GET'], ['band' => 'high'], [], ['user' => $userStudentA], $brokenDbPath);
api_test_assert($brokenRes['status'] === 500, 'Forced failure returns 500');
api_test_assert(($brokenRes['body']['error']['code'] ?? '') === 'SOURCE_FAILURE', 'Forced failure code is SOURCE_FAILURE');
api_test_assert(!empty($brokenRes['body']['meta']['requestId']), 'RequestId is present on 500 error');
api_test_assert(!str_contains($brokenRes['raw'], 'SELECT'), 'Response body does not contain SELECT');
api_test_assert(!str_contains($brokenRes['raw'], 'SQLSTATE'), 'Response body does not contain SQLSTATE');

// Cleanup temp DB files
unset($pdo, $brokenPdo);
if (file_exists($dbPath)) {
    @unlink($dbPath);
}
if (file_exists($brokenDbPath)) {
    @unlink($brokenDbPath);
}

echo "learner_assessment_api_test: OK\n";
