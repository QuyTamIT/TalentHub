<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function opportunity_api_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "opportunity_api_contract_violation={$message}\n");
        exit(1);
    }
}

/** @return array{status:int,body:array<string,mixed>,raw:string,stderr:string} */
function opportunity_api_request(
    string $endpoint,
    string $database,
    array $server,
    array $body,
    array $session,
    string $providerMode = 'success',
): array {
    $modelItems = [];
    $reasons = [
        1 => 'Dự án dữ liệu thứ nhất tận dụng nền tảng Python đã có trong hồ sơ. Điểm đánh giá cho thấy người học có tư duy phân tích phù hợp. Hồ sơ chưa có nhiều dashboard hoàn chỉnh để chứng minh kinh nghiệm. Cơ hội này giúp rèn cách chuyển dữ liệu thành báo cáo trực quan.',
        2 => 'Cơ hội thứ hai liên quan đến khả năng phân tích đã được ghi nhận. Kinh nghiệm hợp tác nhóm hỗ trợ quá trình nghiên cứu hành vi người dùng. Hồ sơ hiện thiếu minh chứng về một nghiên cứu thực tế. Dự án giúp rèn cách tổng hợp dữ liệu thành insight có thể hành động.',
        3 => 'Chương trình thứ ba phù hợp với kinh nghiệm làm việc nhóm của người học. Khả năng trình bày hỗ trợ việc chia sẻ kết quả nghiên cứu. Hồ sơ chưa chứng minh kỹ năng xây dựng một báo cáo hoàn chỉnh. Đây là môi trường phù hợp để rèn giao tiếp và phản biện dựa trên dữ liệu.',
    ];
    foreach (range(1, 3) as $rank) {
        $modelItems[] = [
            'catalog_id' => "project-{$rank}",
            'gemini_score' => 90 - (($rank - 1) * 5),
            'why_fit' => $reasons[$rank],
            'fit_reasons' => ['Có năng lực liên quan đã được ghi nhận.', 'Có kinh nghiệm hợp tác nhóm.'],
            'gap_reasons' => ['Chưa có sản phẩm thực tế hoàn chỉnh.'],
            'skills_to_develop' => ['Phân tích dữ liệu', 'Trình bày kết quả'],
            'matched_skill_codes' => [],
            'missing_skill_codes' => [],
            'expected_outcome_codes' => [],
            'evidence_ref_ids' => ["opportunity:project-{$rank}"],
        ];
    }
    $payload = base64_encode((string) json_encode([
        'endpoint' => $endpoint,
        'database' => $database,
        'server' => $server,
        'body' => $body,
        'session' => $session,
        'provider_mode' => $providerMode,
        'model_items' => $modelItems,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
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
$GLOBALS['__TALENTHUB_TEST_ENV__'] = [
    'APP_ENV' => 'test',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'gemini',
    'TALENTHUB_AI_MODEL' => 'gemini-api-test',
    'TALENTHUB_AI_API_URL' => 'http://127.0.0.1:20128/generate',
    'TALENTHUB_AI_API_KEY' => 'test-secret-never-persist',
    'TALENTHUB_AI_ALLOWED_HOSTS' => '127.0.0.1',
    'TALENTHUB_AI_MAX_ATTEMPTS' => '1',
];
$GLOBALS['__TALENTHUB_TEST_HTTP__'] = static function () use ($config): array {
    if ($config['provider_mode'] === 'failure') {
        return ['status' => 503, 'headers' => [], 'body' => '{"error":"unavailable"}'];
    }
    return [
        'status' => 200,
        'headers' => [],
        'body' => json_encode(['items' => $config['model_items']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ];
};
$_SERVER = array_merge([
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/app/learner/api/v1/opportunity-matches.php',
    'CONTENT_TYPE' => 'application/json',
    'REMOTE_ADDR' => '127.0.0.61',
], $config['server']);
require $config['endpoint'];
PHP;
    $temporary = tempnam(sys_get_temp_dir(), 'opportunity-api-runner-');
    if (!is_string($temporary)) {
        throw new RuntimeException('Cannot create opportunity API runner.');
    }
    file_put_contents($temporary, $runner);
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($temporary) . ' ' . escapeshellarg($payload);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        unlink($temporary);
        throw new RuntimeException('Cannot start opportunity API runner.');
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

$endpoint = dirname(__DIR__) . '/app/learner/api/v1/opportunity-matches.php';
opportunity_api_assert(is_file($endpoint), 'opportunity endpoint exists');
$source = file_get_contents($endpoint);
opportunity_api_assert(is_string($source), 'opportunity endpoint source is readable');
foreach (['student_profile.read_own', 'student_profile.update_own', 'x-csrf-token', 'x-idempotency-key', 'learner.ai'] as $needle) {
    opportunity_api_assert(str_contains($source, $needle), "endpoint contains {$needle}");
}
opportunity_api_assert(!str_contains($source, 'TALENTHUB_AI_API_KEY'), 'endpoint never reads or exposes the provider secret');

$databasePath = sys_get_temp_dir() . '/talenthub-opportunity-api-' . bin2hex(random_bytes(6)) . '.sqlite';
$pdo = new PDO('sqlite:' . $databasePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
register_shutdown_function(static function () use ($databasePath): void {
    if (is_file($databasePath)) {
        @unlink($databasePath);
    }
});

$schema = [
    'CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT NOT NULL)',
    'CREATE TABLE users (id TEXT PRIMARY KEY, roleId TEXT NOT NULL, status TEXT NOT NULL)',
    'CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT NOT NULL)',
    'CREATE TABLE role_permissions (roleId TEXT NOT NULL, permissionId TEXT NOT NULL)',
    'CREATE TABLE auth_rate_limits (bucketKey TEXT PRIMARY KEY, scope TEXT NOT NULL, failureCount INTEGER NOT NULL, windowStartedAt TEXT NOT NULL, blockedUntil TEXT NULL, updatedAt TEXT NOT NULL)',
    'CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT, tenantId TEXT)',
    'CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel TEXT, academicYear TEXT, educationBand TEXT)',
    'CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, level TEXT)',
    'CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT)',
    'CREATE TABLE student_skills (id TEXT PRIMARY KEY, studentId TEXT, skillId TEXT, levelScore REAL, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT, updatedAt TEXT)',
    'CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT, type TEXT, status TEXT)',
    'CREATE TABLE test_attempts (id TEXT PRIMARY KEY, studentId TEXT, testId TEXT, status TEXT)',
    'CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT, resultCode TEXT, dimensionScoresJson TEXT)',
    'CREATE TABLE learner_assessment_attempt_metadata (id TEXT PRIMARY KEY, attemptId TEXT, versionId TEXT, status TEXT, submittedAt TEXT)',
    'CREATE TABLE learner_assessment_versions (id TEXT PRIMARY KEY, version TEXT, scoringVersion TEXT, status TEXT, publishedAt TEXT)',
    'CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT, hours REAL, status TEXT, confirmedAt TEXT)',
    'CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT, status TEXT, confirmedAt TEXT)',
    'CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT)',
    'CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT)',
    'CREATE TABLE assessments (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, overallScore REAL, status TEXT, publishedAt TEXT)',
    'CREATE TABLE assessment_scores (id TEXT PRIMARY KEY, assessmentId TEXT, criteriaId TEXT, score REAL)',
    'CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT)',
    'CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, location TEXT, deadline TEXT, status TEXT, createdAt TEXT, description TEXT, educationLevel TEXT, skillsJson TEXT, requirementsJson TEXT, field TEXT, workType TEXT, duration TEXT, slots INTEGER)',
    'CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT, status TEXT, verificationStatus TEXT)',
    'CREATE TABLE learner_ai_consent_events (id TEXT PRIMARY KEY, studentId TEXT, scope TEXT, action TEXT, policyVersion TEXT, occurredAt TEXT, requestId TEXT)',
];
foreach ($schema as $statement) {
    $pdo->exec($statement);
}
foreach ([
    '004_create_recommendation_store.php',
    '011_create_ai_catalog_items.php',
    '015_extend_learner_opportunity_matching.php',
    '016_add_learner_opportunity_analysis.php',
] as $migrationFile) {
    $definition = require dirname(__DIR__) . '/Database/migrations/learner/' . $migrationFile;
    foreach ($definition->migration->statements('sqlite') as $statement) {
        $pdo->exec($statement);
    }
}

$studentId = '00000000-0000-4000-8000-000000000061';
$pdo->exec("INSERT INTO roles VALUES ('student-role', 'student'), ('teacher-role', 'teacher')");
$pdo->exec("INSERT INTO users VALUES ('student-user', 'student-role', 'active'), ('teacher-user', 'teacher-role', 'active')");
$pdo->exec("INSERT INTO permissions VALUES ('read-permission', 'student_profile.read_own'), ('write-permission', 'student_profile.update_own')");
$pdo->exec("INSERT INTO role_permissions VALUES ('student-role', 'read-permission'), ('student-role', 'write-permission')");
$pdo->exec("INSERT INTO schools VALUES ('school-1', 'TalentHub School', 'THPT')");
$pdo->exec("INSERT INTO classes VALUES ('class-1', 'school-1', '11A1', '11', '2026-2027', 'high')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$studentId}', 'student-user', 'class-1', 'active', 'school-1')");
$pdo->exec("INSERT INTO skills VALUES ('skill-python', 'python', 'Python', 'technical', 'active')");
$pdo->exec("INSERT INTO student_skills VALUES ('student-skill-python', '{$studentId}', 'skill-python', 88, 'assessment', 'verified', '2026-08-01 00:00:00', '2026-08-01 00:00:00')");
foreach (['activity', 'assessment', 'evaluation', 'skills'] as $index => $scope) {
    $pdo->exec("INSERT INTO learner_ai_consent_events VALUES ('consent-{$index}', '{$studentId}', '{$scope}', 'granted', 'learner-ai-consent-1.0', '2026-08-01 00:00:00', 'consent-request-{$index}')");
}
$pdo->exec("INSERT INTO enterprises VALUES ('enterprise-1', 'TalentHub Partner', 'active', 'verified')");
foreach (range(1, 5) as $index) {
    $skills = $pdo->quote('["Python"]');
    $requirements = $pdo->quote('[]');
    $pdo->exec("INSERT INTO internship_posts VALUES ('project-{$index}', 'enterprise-1', 'Dự án {$index}', 'Hà Nội', '2027-12-31 23:59:59', 'active', '2026-08-01 00:00:00', 'Mô tả riêng dự án {$index}', 'THPT', {$skills}, {$requirements}, 'AI & Data', 'hybrid', '8 tuần', 10)");
}

$skillFixture = (new \TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource($pdo))->forStudent($studentId);
opportunity_api_assert(count($skillFixture) === 1, 'skill fixture is visible to the snapshot source');
$snapshotRegistry = \TalentHub\Learner\Ai\Sources\AiSourceRegistry::fromLegacySources([
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource($pdo),
    new \TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource($pdo),
]);
$opportunityFixture = (new \TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource($pdo))->forStudent($studentId);
opportunity_api_assert(count($opportunityFixture) >= 3, 'opportunity source returns seeded fixtures: ' . json_encode($opportunityFixture));
$snapshotInput = (new \TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder($snapshotRegistry))->build(
    $studentId,
    \TalentHub\Learner\Ai\Consent\ConsentDecision::REQUIRED_SCOPES,
);
opportunity_api_assert(count($snapshotInput->payload()['skills'] ?? []) === 1, 'snapshot contains the verified skill fixture');
opportunity_api_assert(
    count($snapshotInput->payload()['opportunities'] ?? []) >= 3,
    'snapshot contains opportunity fixtures: ' . json_encode($snapshotInput->payload(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
);

$studentSession = ['user' => ['id' => 'student-user', 'role' => 'student', 'status' => 'active'], 'csrfToken' => 'csrf-ok'];
$teacherSession = ['user' => ['id' => 'teacher-user', 'role' => 'teacher', 'status' => 'active'], 'csrfToken' => 'csrf-ok'];

$get = opportunity_api_request($endpoint, $databasePath, ['REQUEST_METHOD' => 'GET'], [], $studentSession);
opportunity_api_assert(
    $get['status'] === 200 && ($get['body']['data']['state'] ?? '') === 'not_generated',
    'GET returns not_generated in the existing envelope: ' . json_encode($get, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
);

$missingCsrf = opportunity_api_request($endpoint, $databasePath, ['REQUEST_METHOD' => 'POST', 'HTTP_X_IDEMPOTENCY_KEY' => 'opportunity-api-key-0001'], [], $studentSession);
opportunity_api_assert($missingCsrf['status'] === 403 && ($missingCsrf['body']['error']['code'] ?? '') === 'CSRF_INVALID', 'POST requires CSRF');

$badIdempotency = opportunity_api_request($endpoint, $databasePath, ['REQUEST_METHOD' => 'POST', 'HTTP_X_CSRF_TOKEN' => 'csrf-ok', 'HTTP_X_IDEMPOTENCY_KEY' => 'short'], [], $studentSession);
opportunity_api_assert($badIdempotency['status'] === 422 && ($badIdempotency['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'POST validates idempotency key');

$unexpectedBody = opportunity_api_request($endpoint, $databasePath, [
    'REQUEST_METHOD' => 'POST',
    'HTTP_X_CSRF_TOKEN' => 'csrf-ok',
    'HTTP_X_IDEMPOTENCY_KEY' => 'opportunity-api-body-00001',
], ['studentId' => $studentId], $studentSession);
opportunity_api_assert($unexpectedBody['status'] === 422 && ($unexpectedBody['body']['error']['code'] ?? '') === 'VALIDATION_FAILED', 'POST accepts an empty body only');

$wrongRole = opportunity_api_request($endpoint, $databasePath, ['REQUEST_METHOD' => 'GET'], [], $teacherSession);
opportunity_api_assert($wrongRole['status'] === 403 && ($wrongRole['body']['error']['code'] ?? '') === 'PERMISSION_DENIED', 'endpoint is learner-only');

$wrongMethod = opportunity_api_request($endpoint, $databasePath, ['REQUEST_METHOD' => 'DELETE'], [], $studentSession);
opportunity_api_assert($wrongMethod['status'] === 405 && ($wrongMethod['body']['error']['code'] ?? '') === 'METHOD_NOT_ALLOWED', 'unsupported method returns 405');

$unavailable = opportunity_api_request($endpoint, $databasePath, [
    'REQUEST_METHOD' => 'POST',
    'HTTP_X_CSRF_TOKEN' => 'csrf-ok',
    'HTTP_X_IDEMPOTENCY_KEY' => 'opportunity-api-failure-0001',
], [], $studentSession, 'failure');
opportunity_api_assert($unavailable['status'] === 202, 'provider unavailable generation returns 202');
opportunity_api_assert(($unavailable['body']['data']['state'] ?? '') === 'provider_unavailable' && ($unavailable['body']['data']['items'] ?? null) === [], 'provider unavailable response is safe');

$ready = opportunity_api_request($endpoint, $databasePath, [
    'REQUEST_METHOD' => 'POST',
    'HTTP_X_CSRF_TOKEN' => 'csrf-ok',
    'HTTP_X_IDEMPOTENCY_KEY' => 'opportunity-api-ready-00001',
], [], $studentSession);
$readyRuns = $pdo->query("SELECT status, safeErrorCode FROM learner_recommendation_runs WHERE capability = 'opportunity_match' ORDER BY createdAt")->fetchAll(PDO::FETCH_ASSOC);
opportunity_api_assert(
    $ready['status'] === 202 && ($ready['body']['data']['state'] ?? '') === 'ready_model',
    'POST returns 202 ready_model: ' . json_encode(['response' => $ready, 'runs' => $readyRuns], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
);
opportunity_api_assert(count($ready['body']['data']['items'] ?? []) === 3, 'POST exposes exactly Top 3 items');
$firstReadyItem = $ready['body']['data']['items'][0] ?? [];
opportunity_api_assert(($firstReadyItem['fit_reasons'] ?? []) !== [], 'POST exposes detailed fit reasons');
opportunity_api_assert(($firstReadyItem['gap_reasons'] ?? []) !== [], 'POST exposes detailed gap reasons');
opportunity_api_assert(($firstReadyItem['skills_to_develop'] ?? []) !== [], 'POST exposes skills to develop');
opportunity_api_assert(!array_key_exists('structured_score', $firstReadyItem) && !array_key_exists('gemini_score', $firstReadyItem), 'POST hides score component calculations');

echo "learner_ai_opportunity_api_test: OK\n";
