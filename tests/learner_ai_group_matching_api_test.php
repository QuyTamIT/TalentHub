<?php
declare(strict_types=1);

namespace TalentHub\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Service/EducationBandResolver.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function api_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException('FAIL: ' . $message);
    }
}

function createApiTestPdo(?string $databasePath = null): PDO
{
    $pdo = new PDO($databasePath === null ? 'sqlite::memory:' : 'sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE auth_rate_limits (bucketKey TEXT PRIMARY KEY, scope TEXT NOT NULL, failureCount INTEGER NOT NULL, windowStartedAt TEXT NOT NULL, blockedUntil TEXT NULL, updatedAt TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT, tenantId TEXT)');
    $pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel TEXT, academicYear TEXT, educationBand TEXT)');
    $pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, level TEXT)');
    $pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE student_skills (id TEXT PRIMARY KEY, studentId TEXT, skillId TEXT, levelScore REAL, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT, updatedAt TEXT)');
    $pdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT, type TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE test_attempts (id TEXT PRIMARY KEY, studentId TEXT, testId TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT, resultCode TEXT, dimensionScoresJson TEXT)');
    $pdo->exec('CREATE TABLE learner_assessment_attempt_metadata (id TEXT PRIMARY KEY, attemptId TEXT, versionId TEXT, status TEXT, submittedAt TEXT)');
    $pdo->exec('CREATE TABLE learner_assessment_versions (id TEXT PRIMARY KEY, version TEXT, scoringVersion TEXT, status TEXT, publishedAt TEXT)');
    $pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT, hours REAL, status TEXT, confirmedAt TEXT)');
    $pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT, status TEXT, confirmedAt TEXT)');
    $pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT)');
    $pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT)');
    $pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, audienceScope TEXT, filterCategory TEXT, locationName TEXT)');
    $pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT)');
    $pdo->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, overallScore REAL, status TEXT, publishedAt TEXT)');
    $pdo->exec('CREATE TABLE assessment_scores (id TEXT PRIMARY KEY, assessmentId TEXT, criteriaId TEXT, score REAL)');
    $pdo->exec('CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT)');
    $pdo->exec('CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, location TEXT, deadline TEXT, status TEXT, audience TEXT, createdAt TEXT)');
    $pdo->exec('CREATE TABLE enterprises (id TEXT PRIMARY KEY, status TEXT, verificationStatus TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_catalog_items (catalog_id TEXT PRIMARY KEY, item_type TEXT, category TEXT, title TEXT, summary TEXT, publish_status TEXT, deadline_at TEXT, eligibility_json TEXT, capacity INTEGER, enrolled_count INTEGER, url TEXT, action_json TEXT, school_id TEXT, tenant_id TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_consent_events (id TEXT PRIMARY KEY, studentId TEXT, scope TEXT, action TEXT, policyVersion TEXT, occurredAt TEXT, requestId TEXT)');
    $pdo->exec('CREATE TABLE learner_ai_roadmaps (id TEXT PRIMARY KEY, studentId TEXT, versionNumber INTEGER, roadmapId TEXT, status TEXT, modelVersion TEXT, primaryDirectionJson TEXT, alternativeDirectionsJson TEXT, overallGoal TEXT, summaryHtml TEXT, createdAt TEXT, supersededAt TEXT)');
    $pdo->exec('CREATE TABLE action_rate_limit_events (id TEXT PRIMARY KEY, actionKey TEXT NOT NULL, identifier TEXT NOT NULL, ipAddress TEXT, occurredAt TEXT NOT NULL, expireAt TEXT NOT NULL)');

    return $pdo;
}

$databasePath = sys_get_temp_dir() . '/talenthub-group-api-' . bin2hex(random_bytes(6)) . '.sqlite';
$pdo = createApiTestPdo($databasePath);
register_shutdown_function(static function () use ($databasePath): void {
    if (is_file($databasePath)) {
        @unlink($databasePath);
    }
});
$studentId = '00000000-0000-4000-8000-000000000001';
$schoolId = '00000000-0000-4000-8000-000000000002';
$classId = '00000000-0000-4000-8000-000000000003';

// Seed profile
$pdo->exec("INSERT INTO schools (id, name, level) VALUES ('{$schoolId}', 'THPT Chuyen TalentHub', 'THPT')");
$pdo->exec("INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, educationBand) VALUES ('{$classId}', '{$schoolId}', '11A1', '11', '2025-2026', 'high')");
$pdo->exec("INSERT INTO student_profiles (id, userId, classId, studyStatus, tenantId) VALUES ('{$studentId}', 'usr-1', '{$classId}', 'active', '{$schoolId}')");
$pdo->exec("INSERT INTO roles VALUES ('student-role', 'student'), ('teacher-role', 'teacher')");
$pdo->exec("INSERT INTO users VALUES ('usr-1', 'student-role', 'active'), ('usr-teacher', 'teacher-role', 'active')");
$pdo->exec("INSERT INTO permissions VALUES ('read-permission', 'student_profile.read_own'), ('write-permission', 'student_profile.update_own')");
$pdo->exec("INSERT INTO role_permissions VALUES ('student-role', 'read-permission'), ('student-role', 'write-permission')");

// Seed consents
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-1', '{$studentId}', 'activity', 'granted', '1.0', '2026-01-01 00:00:00', 'req-1')");
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-2', '{$studentId}', 'skills', 'granted', '1.0', '2026-01-01 00:00:00', 'req-2')");
$pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('c-3', '{$studentId}', 'assessment', 'granted', '1.0', '2026-01-01 00:00:00', 'req-3')");

// Seed skills
$pdo->exec("INSERT INTO skills (id, code, name, category, status) VALUES ('sk-1', 'data_analysis', 'Phân tích dữ liệu', 'tech', 'active')");
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, updatedAt) VALUES ('ss-1', '{$studentId}', 'sk-1', 85.0, 'assessment', 'verified', '2026-02-01 00:00:00', '2026-02-01 00:00:00')");

// Seed Holland Assessment
$pdo->exec("INSERT INTO talent_tests (id, code, type, status) VALUES ('t-1', 'HOLLAND', 'holland', 'published')");
$pdo->exec("INSERT INTO test_attempts (id, studentId, testId, status) VALUES ('att-1', '{$studentId}', 't-1', 'submitted')");
$pdo->exec("INSERT INTO test_results (id, attemptId, resultCode, dimensionScoresJson) VALUES ('res-1', 'att-1', 'IAS', '{\"I\":28,\"A\":24,\"S\":20,\"R\":10,\"E\":8,\"C\":6}')");
$pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, submittedAt) VALUES ('meta-1', 'att-1', 'v1', 'submitted', '2026-02-10 10:00:00')");
$pdo->exec("INSERT INTO learner_assessment_versions (id, version, scoringVersion, status, publishedAt) VALUES ('v1', '1.0', '1.0', 'published', '2026-01-01 00:00:00')");

// Seed Group Candidates
$action1 = json_encode([
    'type' => 'join_group',
    'catalog_id' => 'grp-1',
    'match_profile' => [
        'skill_codes' => ['data_analysis'],
        'assessment_directions' => ['holland' => ['I', 'A']],
        'education_bands' => ['high']
    ]
]);
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('grp-1', 'group', 'study_group', 'Nhóm Data', 'Mô tả', 'published', '2026-09-30 23:59:59', '[]', 20, 5, '/app/learner/groups.php?id=grp-1', '$action1', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Community candidate
$actionComm = json_encode([
    'type' => 'open_catalog_item',
    'catalog_id' => 'comm-1',
    'match_profile' => [
        'skill_codes' => ['data_analysis'],
        'assessment_directions' => ['holland' => ['I']],
        'education_bands' => ['high']
    ]
]);
$pdo->exec("INSERT INTO learner_ai_catalog_items VALUES ('comm-1', 'community', 'tech_community', 'Cộng đồng AI', 'Mô tả', 'published', '2026-09-30 23:59:59', '[]', 50, 10, '/app/learner/group-detail.php?id=comm-1', '$actionComm', '{$schoolId}', '{$schoolId}', '2026-08-01 00:00:00')");

// Test GET matching service resolution
$consentPolicy = new \TalentHub\Learner\Ai\Consent\ConsentPolicy(new \TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource($pdo));
$catalogSource = new \TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource($pdo);
$registry = \TalentHub\Learner\Ai\Sources\AiSourceRegistry::fromLegacySources([
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource($pdo),
]);
$registry->setTransactionPdo($pdo);
$snapshotBuilder = new \TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder($registry);
$educationBandResolver = new \TalentHub\Learner\Assessment\Service\EducationBandResolver($pdo);
$service = new \TalentHub\Learner\Ai\Service\GroupMatchingService($pdo, $catalogSource, $consentPolicy, $snapshotBuilder, $educationBandResolver);

// 1. Test Match list
$items = $service->match($studentId, 10);
api_assert(count($items) === 2, 'two matching candidates returned');
api_assert($items[0]['analysis_origin'] === 'evidence_match', 'analysis_origin is evidence_match');

// 2. Test Resolve join_group Action
$resJoin = $service->resolveAction($studentId, 'grp-1', 'join_group');
api_assert($resJoin['state'] === 'action_ready', 'join_group resolves action_ready');
api_assert($resJoin['url'] === '/app/learner/groups.php?id=grp-1', 'url is correct safe relative url');

// 3. Test Resolve open_catalog_item Action
$resOpen = $service->resolveAction($studentId, 'comm-1', 'open_catalog_item');
api_assert($resOpen['state'] === 'catalog_opened', 'open_catalog_item resolves catalog_opened');
api_assert($resOpen['url'] === '/app/learner/group-detail.php?id=comm-1', 'url is correct catalog url');

// 4. Test Resolve Action for Invalid or Unavailable Catalog ID
$resInvalid = $service->resolveAction($studentId, 'unknown-id', 'join_group');
api_assert($resInvalid['state'] === 'join_unavailable', 'unknown id resolves join_unavailable');
api_assert($resInvalid['url'] === null, 'unavailable action has null url');

/** @return array{status:int,body:array<string,mixed>,raw:string,stderr:string} */
function executeGroupMatchingEndpoint(string $endpoint, string $database, array $server, array $query, array $body, array $session): array
{
    $payload = base64_encode((string) json_encode([
        'endpoint' => $endpoint,
        'database' => $database,
        'server' => $server,
        'query' => $query,
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
$_GET = $config['query'];
$_SERVER = array_merge([
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/app/learner/api/v1/ai-group-matches.php',
    'CONTENT_TYPE' => 'application/json',
    'REMOTE_ADDR' => '127.0.0.41',
], $config['server']);
require $config['endpoint'];
PHP;
    $temporary = tempnam(sys_get_temp_dir(), 'group-api-runner-');
    if (!is_string($temporary)) {
        throw new \RuntimeException('Cannot create group API runner.');
    }
    file_put_contents($temporary, $runner);
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($temporary) . ' ' . escapeshellarg($payload);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        unlink($temporary);
        throw new \RuntimeException('Cannot start group API runner.');
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

$endpoint = dirname(__DIR__) . '/app/learner/api/v1/ai-group-matches.php';
$studentSession = ['user' => ['id' => 'usr-1', 'role' => 'student', 'status' => 'active'], 'csrfToken' => 'csrf-ok'];
$teacherSession = ['user' => ['id' => 'usr-teacher', 'role' => 'teacher', 'status' => 'active'], 'csrfToken' => 'csrf-ok'];
$apiContractFailures = [];
$collectApiFailure = static function (bool $condition, string $message) use (&$apiContractFailures): void {
    if (!$condition) {
        $apiContractFailures[] = $message;
    }
};

$anonymous = executeGroupMatchingEndpoint($endpoint, $databasePath, ['REQUEST_METHOD' => 'GET'], ['limit' => '1'], [], []);
$collectApiFailure($anonymous['status'] === 401 && ($anonymous['body']['error']['code'] ?? '') === 'AUTH_REQUIRED', 'GET requires authentication');
$wrongRole = executeGroupMatchingEndpoint($endpoint, $databasePath, ['REQUEST_METHOD' => 'GET'], ['limit' => '1'], [], $teacherSession);
$collectApiFailure($wrongRole['status'] === 403 && ($wrongRole['body']['error']['code'] ?? '') === 'PERMISSION_DENIED', 'GET is restricted to learner role and permission');

$limited = executeGroupMatchingEndpoint($endpoint, $databasePath, ['REQUEST_METHOD' => 'GET'], ['limit' => '1'], [], $studentSession);
$limitedData = $limited['body']['data'] ?? [];
$collectApiFailure($limited['status'] === 200 && count($limitedData['items'] ?? []) === 1, 'GET applies limit=1');
$collectApiFailure(($limitedData['state'] ?? '') === 'ready', 'GET returns canonical ready state');
$collectApiFailure(($limitedData['analysis_origin'] ?? '') === 'evidence_match', 'GET returns analysis_origin=evidence_match');
$collectApiFailure(($limitedData['generated_from'] ?? '') === 'database_snapshot', 'GET returns generated_from=database_snapshot');

foreach (['0', '11', 'not-a-number'] as $invalidLimit) {
    $invalidLimitResponse = executeGroupMatchingEndpoint($endpoint, $databasePath, ['REQUEST_METHOD' => 'GET'], ['limit' => $invalidLimit], [], $studentSession);
    $collectApiFailure(
        $invalidLimitResponse['status'] === 422 && ($invalidLimitResponse['body']['error']['code'] ?? '') === 'VALIDATION_FAILED',
        'GET rejects invalid limit=' . $invalidLimit,
    );
}

$validHeaders = [
    'REQUEST_METHOD' => 'POST',
    'HTTP_X_CSRF_TOKEN' => 'csrf-ok',
    'HTTP_X_IDEMPOTENCY_KEY' => 'group-action-key-0001',
];
$missingCsrf = executeGroupMatchingEndpoint(
    $endpoint,
    $databasePath,
    ['REQUEST_METHOD' => 'POST', 'HTTP_X_IDEMPOTENCY_KEY' => 'group-action-key-0000'],
    [],
    ['catalogId' => 'grp-1', 'action' => 'join_group'],
    $studentSession,
);
$collectApiFailure($missingCsrf['status'] === 403 && ($missingCsrf['body']['error']['code'] ?? '') === 'CSRF_INVALID', 'POST requires CSRF');

$extraField = executeGroupMatchingEndpoint(
    $endpoint,
    $databasePath,
    $validHeaders,
    [],
    ['catalogId' => 'grp-1', 'action' => 'join_group', 'studentId' => $studentId],
    $studentSession,
);
$collectApiFailure($extraField['status'] === 422 && ($extraField['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'POST rejects browser-owned fields');

$snakeCase = executeGroupMatchingEndpoint(
    $endpoint,
    $databasePath,
    $validHeaders,
    [],
    ['catalog_id' => 'grp-1', 'action' => 'join_group'],
    $studentSession,
);
$collectApiFailure($snakeCase['status'] === 422 && ($snakeCase['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'POST rejects legacy snake_case catalog_id');

$missingIdempotency = executeGroupMatchingEndpoint(
    $endpoint,
    $databasePath,
    ['REQUEST_METHOD' => 'POST', 'HTTP_X_CSRF_TOKEN' => 'csrf-ok'],
    [],
    ['catalogId' => 'grp-1', 'action' => 'join_group'],
    $studentSession,
);
$collectApiFailure($missingIdempotency['status'] === 422 && ($missingIdempotency['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'POST requires a valid idempotency key');

for ($attempt = 1; $attempt <= 4; $attempt++) {
    $headers = [
        'REQUEST_METHOD' => 'POST',
        'HTTP_X_CSRF_TOKEN' => 'csrf-ok',
        'HTTP_X_IDEMPOTENCY_KEY' => 'group-action-rate-' . str_pad((string) $attempt, 4, '0', STR_PAD_LEFT),
    ];
    $response = executeGroupMatchingEndpoint(
        $endpoint,
        $databasePath,
        $headers,
        [],
        ['catalogId' => 'grp-1', 'action' => 'join_group'],
        $studentSession,
    );
    if ($attempt <= 3) {
        $collectApiFailure(
            $response['status'] === 200 && ($response['body']['data']['state'] ?? '') === 'action_ready',
            'POST accepts exact camelCase catalogId contract before the rate limit',
        );
    } else {
        $collectApiFailure(
            $response['status'] === 429 && ($response['body']['error']['code'] ?? '') === 'RATE_LIMIT_EXCEEDED',
            'POST enforces learner AI action rate limit',
        );
    }
}

api_assert(
    $apiContractFailures === [],
    "real endpoint contract gaps:\n- " . implode("\n- ", $apiContractFailures),
);

echo "learner_ai_group_matching_api_test: OK\n";
