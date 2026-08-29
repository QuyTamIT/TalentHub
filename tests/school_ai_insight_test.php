<?php
declare(strict_types=1);

use TalentHub\Modules\School\Repository\SchoolAiAggregateRepository;
use TalentHub\Modules\School\Repository\DatabaseSchoolAiInsightRepository;
use TalentHub\Modules\School\Service\SchoolAiInsightService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/src/Modules/School/Repository/SchoolAiAggregateRepository.php';
require_once dirname(__DIR__) . '/src/Modules/School/Repository/DatabaseSchoolAiInsightRepository.php';
require_once dirname(__DIR__) . '/src/Modules/School/Service/SchoolAiInsightService.php';

function school_ai_assert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel INTEGER)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT, studyStatus TEXT DEFAULT \'active\')');
$pdo->exec('CREATE TABLE privacy_consents (id TEXT, studentId TEXT, scope TEXT, isGranted INTEGER, revokedAt TEXT)');
$pdo->exec('CREATE TABLE learner_ai_consent_events (id TEXT, studentId TEXT, scope TEXT, action TEXT, occurredAt TEXT, requestId TEXT)');
$pdo->exec('CREATE TABLE learner_ai_capability_profiles (id TEXT, student_id TEXT, status TEXT, talent_map_json TEXT, trend_signals_json TEXT, evidence_json TEXT DEFAULT \'[]\', generated_at TEXT, superseded_at TEXT)');
$pdo->exec('CREATE TABLE school_ai_insights (id TEXT PRIMARY KEY, school_id TEXT, aggregate_hash TEXT, payload_json TEXT, model_version TEXT, generated_at TEXT, stale_since TEXT, UNIQUE(school_id, aggregate_hash, model_version))');
$pdo->exec('CREATE TABLE school_ai_refresh_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, school_id TEXT, aggregate_hash TEXT, status TEXT, attempts INTEGER, lease_owner TEXT, lease_token TEXT, lease_until TEXT, error_code TEXT, next_retry_at TEXT, dead_lettered_at TEXT, created_at TEXT, updated_at TEXT)');
$pdo->exec('CREATE TABLE learner_ai_provider_health (provider_key TEXT PRIMARY KEY, state TEXT, failure_count INTEGER, opened_at TEXT, updated_at TEXT)');

$pdo->exec("INSERT INTO classes VALUES ('a1', 'school-a', '10A1', 10), ('a2', 'school-a', '10A2', 10), ('b1', 'school-b', '10B1', 10)");
for ($i = 1; $i <= 8; $i++) {
    $class = $i <= 6 ? 'a1' : 'a2';
    $student = "student-a{$i}";
    $pdo->prepare('INSERT INTO student_profiles VALUES (?,?,?)')->execute([$student, $class, 'active']);
    $talent = $i === 1 ? '[{"field":"Kỹ thuật","score":61},{"field":"Chỉ một học sinh","score":100}]' : '[{"field":"Kỹ thuật","score":' . (60 + $i) . '}]';
    $trend = $i === 1 ? '[{"label":"Tư duy logic tăng"},{"label":"Xu hướng riêng lẻ"}]' : '[{"label":"Tư duy logic tăng"}]';
    $pdo->prepare('INSERT INTO learner_ai_capability_profiles (id,student_id,status,talent_map_json,trend_signals_json,generated_at,superseded_at) VALUES (?,?,?,?,?,?,NULL)')->execute(["profile-a{$i}", $student, $i === 2 ? 'stale_model' : 'ready_model', $talent, $trend, '2026-08-27 00:00:00']);
    foreach (['assessment', 'skills', 'activity', 'evaluation'] as $scope) {
        $pdo->prepare('INSERT INTO privacy_consents VALUES (?,?,?,?,?)')->execute(["grant-{$i}-{$scope}", $student, $scope, 1, null]);
    }
}

$eventId = 1;
foreach (range(1, 8) as $i) {
    foreach (['assessment', 'skills', 'activity', 'evaluation'] as $scope) {
        $action = $i === 6 ? 'revoked' : 'granted';
        $pdo->prepare('INSERT INTO learner_ai_consent_events VALUES (?,?,?,?,?,?)')->execute(["event-" . $eventId++, "student-a{$i}", $scope, $action, '2026-08-27 00:00:00', "request-{$i}-{$scope}"]);
    }
}

$pdo->exec("INSERT INTO student_profiles VALUES ('student-a10', 'a1', 'active')");
$pdo->exec("INSERT INTO learner_ai_capability_profiles (id,student_id,status,talent_map_json,trend_signals_json,generated_at,superseded_at) VALUES ('profile-a10','student-a10','pending','[{\"field\":\"Đang chờ\",\"score\":100}]','[{\"label\":\"Đang chờ\"}]','2026-08-27 00:00:00',NULL)");

$pdo->exec("INSERT INTO student_profiles VALUES ('student-a9', 'a1', 'active')");
$pdo->exec("INSERT INTO learner_ai_capability_profiles (id,student_id,status,talent_map_json,trend_signals_json,generated_at,superseded_at) VALUES ('profile-a9','student-a9','ready_model','[{\"field\":\"Hết hạn\",\"score\":100}]','[{\"label\":\"Xu hướng hết hạn\"}]','2024-01-01 00:00:00',NULL)");

$pdo->exec("INSERT INTO student_profiles VALUES ('student-b1', 'b1', 'active')");
$pdo->exec("INSERT INTO learner_ai_capability_profiles (id,student_id,status,talent_map_json,trend_signals_json,generated_at,superseded_at) VALUES ('profile-b1','student-b1','ready_model','[{\"field\":\"Kỹ thuật\",\"score\":99}]','[]','2026-08-27 00:00:00',NULL)");

$pdo->exec("INSERT INTO privacy_consents VALUES ('revoke', 'student-a6', 'assessment', 0, '2026-08-27')");

$repository = new SchoolAiAggregateRepository($pdo);
$readiness = $repository->readiness();
school_ai_assert($readiness['ready'] === true && $readiness['error_code'] === null, 'readiness is true on complete schema');

$minimumRejected = false;
try {
    $repository->aggregate('school-a', 4, '2026-01-01');
} catch (InvalidArgumentException) {
    $minimumRejected = true;
}
school_ai_assert($minimumRejected, 'aggregate rejects every minimum cohort below five');

$aggregate = $repository->aggregate('school-a', 5, '2026-01-01');
school_ai_assert(count($aggregate['cohorts']) === 3, 'class with five consented learners plus grade and whole-school cohorts are visible while the two-person class is suppressed');
school_ai_assert($aggregate['suppressed_cohort_count'] === 1, 'small cohort is counted only as suppressed');

$classA1 = $aggregate['cohorts'][0];
school_ai_assert(!str_contains(json_encode($classA1), 'Chỉ một học sinh') && !str_contains(json_encode($classA1), 'Xu hướng riêng lẻ'), 'individual talent and trend cells below minimum cohort are suppressed');
school_ai_assert(!str_contains(json_encode($aggregate), 'Hết hạn'), 'retention cutoff excludes expired profile evidence');

$eventId = 100;
foreach (['assessment', 'skills', 'activity', 'evaluation'] as $scope) {
    $pdo->prepare('INSERT INTO learner_ai_consent_events VALUES (?,?,?,?,?,?)')->execute(["regrant-" . $eventId++, 'student-a6', $scope, 'granted', '2026-08-27 01:00:00', "regrant-request-{$scope}"]);
}
$regranted = $repository->aggregate('school-a', 5, '2026-01-01');
$classAfterRegrant = array_values(array_filter($regranted['cohorts'], static fn(array $c): bool => $c['cohort_key'] === 'class:a1'))[0] ?? null;
school_ai_assert(($classAfterRegrant['student_count'] ?? 0) === 6, 'latest consent event supports grant after revoke without permanent exclusion');

$encoded = json_encode($aggregate);
school_ai_assert(!str_contains($encoded, 'student-a') && !str_contains($encoded, 'student-b'), 'aggregate never exposes student identifiers');

$protectedTraits = ['age', 'gender', 'sex', 'race', 'ethnicity', 'religion', 'disability', 'health', 'nationality', 'dob', 'tuổi', 'giới tính', 'dân tộc', 'tôn giáo', 'khuyết tật', 'sức khỏe', 'ngày sinh'];
$protectedLabels = array_map(static fn(string $trait): string => "trait-{$trait}", $protectedTraits);
$protectedTalents = json_encode(array_merge([['field' => 'Kỹ thuật', 'score' => 80]], array_map(static fn(string $label): array => ['field' => $label, 'score' => 80], $protectedLabels)), JSON_UNESCAPED_UNICODE);
$protectedTrends = json_encode(array_merge([['label' => 'Tư duy logic tăng']], array_map(static fn(string $label): array => ['label' => $label], $protectedLabels)), JSON_UNESCAPED_UNICODE);
foreach (range(1, 5) as $i) {
    $pdo->prepare('UPDATE learner_ai_capability_profiles SET talent_map_json = ?, trend_signals_json = ? WHERE student_id = ?')->execute([$protectedTalents, $protectedTrends, "student-a{$i}"]);
}
$protectedAggregate = $repository->aggregate('school-a', 5, '2026-01-01');
$protectedAggregateJson = mb_strtolower((string) json_encode($protectedAggregate, JSON_UNESCAPED_UNICODE));
foreach ($protectedLabels as $label) {
    school_ai_assert(!str_contains($protectedAggregateJson, mb_strtolower($label)), "aggregate suppresses protected label {$label}");
}

$captured = [];
$service = new SchoolAiInsightService(
    $repository,
    static fn(string $user): array => ['id' => $user === 'school-user' ? 'school-a' : 'school-b'],
    static function (array $payload) use (&$captured): array {
        $captured = $payload;
        return ['summary' => 'Xu hướng kỹ thuật tích cực.', 'priorities' => ['Mở thêm dự án kỹ thuật'], 'confidence' => 'high'];
    },
    null,
    5,
    null,
    null,
    'gemini-1.5-pro'
);
$result = $service->insight('school-user');
school_ai_assert($result['state'] === 'ready_model' && $result['analysis_origin'] === 'model', 'authorized school receives model explanation over aggregates');
school_ai_assert(is_array($result['evidence'] ?? null) && count($result['evidence']) === 3, 'school insight exposes only aggregate evidence references');
school_ai_assert(isset($result['freshness_status']) && $result['freshness_status'] === 'current', 'ready response has freshness_status=current');
school_ai_assert(isset($result['cohort_version']) && strlen($result['cohort_version']) === 16, 'ready response has 16-char cohort_version');
school_ai_assert(isset($result['model_version']) && $result['model_version'] === 'gemini-1.5-pro', 'ready response has model_version');
school_ai_assert(isset($result['generated_at']), 'ready response has generated_at');

// Test strict disabled provider: must be provider_unavailable, never ready_rule
$disabled = new SchoolAiInsightService(
    $repository,
    static fn(): array => ['id' => 'school-a'],
    null,
    null,
    5,
    null,
    static fn(): bool => false,
    'gemini-test',
    false
);
$disabledResult = $disabled->insight('school-user');
school_ai_assert($disabledResult['state'] === 'provider_unavailable', 'disabled provider is explicit provider_unavailable');
school_ai_assert(($disabledResult['analysis_origin'] ?? null) === null, 'provider unavailable never reports rule origin');
school_ai_assert(!str_contains(json_encode($disabledResult), 'ready_rule'), 'strict school path has no ready_rule');
school_ai_assert(!str_contains(json_encode($disabledResult), 'rule_version'), 'strict school path has no rule_version');

// Test missing AI schema: fails closed to provider_unavailable with ai_schema_unavailable
$pdoMissing = new PDO('sqlite::memory:');
$pdoMissing->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdoMissing->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel INTEGER)');
$repoMissing = new SchoolAiAggregateRepository($pdoMissing);
$serviceMissing = new SchoolAiInsightService(
    $repoMissing,
    static fn(): array => ['id' => 'school-a'],
    static fn(): array => ['summary' => 'test'],
    null,
    5
);
$schemaFailResult = $serviceMissing->insight('school-user');
school_ai_assert($schemaFailResult['state'] === 'provider_unavailable', 'missing AI schema fails closed');
school_ai_assert(($schemaFailResult['error_code'] ?? '') === 'ai_schema_unavailable', 'schema error is categorized safely');

foreach (['school_ai_insights', 'school_ai_refresh_jobs', 'learner_ai_provider_health', 'learner_ai_consent_events'] as $missingTable) {
    $schemaPdo = new PDO('sqlite::memory:');
    $schemaPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    foreach ([
        'CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel INTEGER)',
        'CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT, studyStatus TEXT DEFAULT \'active\')',
        'CREATE TABLE learner_ai_capability_profiles (id TEXT, student_id TEXT, status TEXT, talent_map_json TEXT, trend_signals_json TEXT, evidence_json TEXT, generated_at TEXT, superseded_at TEXT)',
    ] as $sql) {
        $schemaPdo->exec($sql);
    }
    $requiredAiTables = [
        'school_ai_insights' => 'CREATE TABLE school_ai_insights (id TEXT, school_id TEXT, aggregate_hash TEXT, payload_json TEXT, model_version TEXT, generated_at TEXT)',
        'school_ai_refresh_jobs' => 'CREATE TABLE school_ai_refresh_jobs (id TEXT, school_id TEXT, aggregate_hash TEXT, status TEXT, attempts INTEGER, next_retry_at TEXT)',
        'learner_ai_provider_health' => 'CREATE TABLE learner_ai_provider_health (provider_key TEXT, state TEXT, failure_count INTEGER, opened_at TEXT, updated_at TEXT)',
        'learner_ai_consent_events' => 'CREATE TABLE learner_ai_consent_events (id TEXT, studentId TEXT, scope TEXT, action TEXT, occurredAt TEXT, requestId TEXT)',
    ];
    foreach ($requiredAiTables as $table => $sql) {
        if ($table !== $missingTable) {
            $schemaPdo->exec($sql);
        }
    }
    $schemaReadiness = (new SchoolAiAggregateRepository($schemaPdo))->readiness();
    school_ai_assert($schemaReadiness['ready'] === false && $schemaReadiness['error_code'] === 'ai_schema_unavailable', "missing {$missingTable} fails school AI readiness closed");
}

// Async queueing contract
$queued = [];
$async = new SchoolAiInsightService(
    $repository,
    static fn(): array => ['id' => 'school-a'],
    static fn(): array => ['summary' => 'queued'],
    null,
    5,
    null,
    static fn(): bool => true,
    'model-v1',
    false,
    604800,
    static function (string $school, string $hash) use (&$queued): void {
        $queued = [$school, $hash];
    }
);
$asyncResult = $async->insight('school-user');
school_ai_assert($asyncResult['state'] === 'pending' && $queued[0] === 'school-a', 'school GET never invokes Gemini synchronously and enqueues a deterministic refresh');

$currentHash = $repository->aggregateHash($repository->aggregate('school-a', 5));
$sameHashQueued = 0;
$sameHashCache = new SchoolAiInsightService(
    $repository,
    static fn(): array => ['id' => 'school-a'],
    static fn(): array => ['summary' => 'must not run'],
    static fn(): array => ['state' => 'ready_model', 'analysis_origin' => 'model', 'aggregate' => $repository->aggregate('school-a', 5), 'generated_at' => gmdate('Y-m-d H:i:s'), 'model_version' => 'model-v1'],
    5,
    null,
    static fn(): bool => true,
    'model-v1',
    false,
    604800,
    static function () use (&$sameHashQueued): void { $sameHashQueued++; }
);
$sameHashResult = $sameHashCache->insight('school-user');
school_ai_assert($sameHashResult['state'] === 'ready_model' && $sameHashResult['freshness_status'] === 'current' && $sameHashQueued === 0, 'same-hash valid model cache is current and does not enqueue');

$changedHashQueued = 0;
$changedHashCache = new SchoolAiInsightService(
    $repository,
    static fn(): array => ['id' => 'school-a'],
    static fn(): array => ['summary' => 'must not run'],
    static fn(): array => ['state' => 'ready_model', 'analysis_origin' => 'model', 'aggregate' => ['school_id' => 'school-a', 'minimum_cohort' => 5, 'cohorts' => [], 'suppressed_cohort_count' => 0, 'generated_at' => '2026-01-01T00:00:00+00:00'], 'generated_at' => gmdate('Y-m-d H:i:s'), 'model_version' => 'model-v1'],
    5,
    null,
    static fn(): bool => true,
    'model-v1',
    false,
    604800,
    static function () use (&$changedHashQueued): void { $changedHashQueued++; }
);
$changedHashResult = $changedHashCache->insight('school-user');
school_ai_assert($changedHashResult['state'] === 'stale_model' && $changedHashQueued === 1, 'changed hash with valid LKG enqueues once and returns stale model');

$noQueue = new SchoolAiInsightService(
    $repository,
    static fn(): array => ['id' => 'school-a'],
    static fn(): array => ['summary' => 'must not run'],
    null,
    5,
    null,
    static fn(): bool => true,
    'model-v1',
    false
);
$noQueueResult = $noQueue->insight('school-user');
school_ai_assert($noQueueResult['state'] === 'provider_unavailable' && $noQueueResult['error_code'] === 'ai_queue_unavailable', 'missing queue with no cache fails closed instead of pending forever');

$invalidCacheQueued = 0;
$invalidCache = new SchoolAiInsightService(
    $repository,
    static fn(): array => ['id' => 'school-a'],
    static fn(): array => ['summary' => 'must not run'],
    static fn(): array => ['state' => 'ready_model', 'analysis_origin' => 'rule', 'aggregate' => $repository->aggregate('school-a', 5), 'generated_at' => gmdate('Y-m-d H:i:s')],
    5,
    null,
    static fn(): bool => true,
    'model-v1',
    false,
    604800,
    static function () use (&$invalidCacheQueued): void { $invalidCacheQueued++; }
);
$invalidCacheResult = $invalidCache->insight('school-user');
school_ai_assert($invalidCacheResult['state'] === 'pending' && $invalidCacheQueued === 1, 'non-model or malformed cache is never presented as stale model output');

$cacheFailure = new SchoolAiInsightService(
    $repository,
    static fn(): array => ['id' => 'school-a'],
    static fn(): array => ['summary' => 'must not run'],
    static function (): array { throw new RuntimeException('database unavailable'); },
    5,
    null,
    static fn(): bool => true,
    'model-v1',
    false,
    604800,
    static function (): void {}
);
$cacheFailureResult = $cacheFailure->insight('school-user');
school_ai_assert($cacheFailureResult['state'] === 'provider_unavailable' && $cacheFailureResult['error_code'] === 'ai_cache_unavailable', 'cache read failure fails closed with a canonical unavailable state');

// Stale SLA expiration
$oldAsync = new SchoolAiInsightService(
    $repository,
    static fn(): array => ['id' => 'school-a'],
    static fn(): array => ['summary' => 'must not run'],
    static fn(): ?array => ['state' => 'ready_model', 'generated_at' => '2020-01-01 00:00:00', 'model_version' => 'old'],
    5,
    null,
    static fn(): bool => true,
    'new',
    false,
    60
);
school_ai_assert($oldAsync->insight('school-user')['state'] === 'provider_unavailable', 'async path refuses an LKG beyond the stale SLA with provider_unavailable');

$prompt = json_encode($captured);
school_ai_assert(!str_contains($prompt, 'student-a') && !str_contains($prompt, 'student-b') && !str_contains($prompt, '@'), 'school prompt contains aggregate evidence only and no PII values');
foreach ($protectedLabels as $label) {
    school_ai_assert(!str_contains(mb_strtolower((string) $prompt), mb_strtolower($label)), "provider payload suppresses protected label {$label}");
}

$other = $service->insight('other-school-user');
school_ai_assert(($other['state'] ?? null) === 'insufficient_data', 'tenant resolver cannot read another school aggregate');

$store = new DatabaseSchoolAiInsightRepository($pdo);
$store->save('school-a', $result, 'gemini-test');

$stale = new SchoolAiInsightService(
    $repository,
    static fn(): array => ['id' => 'school-a'],
    static function (): array {
        throw new RuntimeException('google outage');
    },
    static fn(string $schoolId): ?array => $store->latest($schoolId),
    5
);
$staleResult = $stale->insight('school-user');
school_ai_assert($staleResult['state'] === 'stale_model' && $staleResult['last_known_good'] === true, 'provider outage serves explicitly stale last-known-good insight');
school_ai_assert(isset($staleResult['freshness_status']) && $staleResult['freshness_status'] === 'stale', 'stale response has freshness_status=stale');

$application = (string) file_get_contents(dirname(__DIR__) . '/src/Bootstrap/Application.php');
$matrix = (string) file_get_contents(dirname(__DIR__) . '/src/Rbac/EndpointPermissionMatrix.php');
$view = (string) file_get_contents(dirname(__DIR__) . '/app/school/analytics.php');
$client = (string) file_get_contents(dirname(__DIR__) . '/assets/js/school-ai-insights.js');
$migration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/learner/012_create_school_ai_insights.php');
$worker = (string) file_get_contents(dirname(__DIR__) . '/bin/worker-school-ai-refresh.php');

school_ai_assert(str_contains($application, "/api/v1/schools/me/ai-insights") && str_contains($application, "school_analytics.read_own"), 'school AI endpoint requires authenticated school analytics permission');
school_ai_assert(str_contains($matrix, "GET /api/v1/schools/me/ai-insights") && str_contains($view, 'data-school-ai-insight') && str_contains($view, 'data-school-ai-cohorts') && (str_contains($client, "credentials:'same-origin'") || str_contains($client, "credentials: 'same-origin'")) && str_contains($client, 'data-school-ai-cohorts'), 'RBAC matrix, aggregate view and same-origin endpoint client are wired');
school_ai_assert(str_contains($migration, 'school_ai_insights') && str_contains($migration, 'stale_since') && str_contains($migration, 'aggregate_hash'), 'Phase 6 insight persistence migration declares last-known-good fields');
school_ai_assert(str_contains($worker, 'SchoolAiRefreshWorker') && str_contains($worker, 'runOnce'), 'school insight Gemini work is executed by a queue worker, not the browser request');
school_ai_assert(!str_contains($client, 'generativelanguage.googleapis') && !str_contains($client, 'x-goog-api-key'), 'school browser never calls Gemini or contains provider credentials');
school_ai_assert(str_contains($application, '$schoolCircuit=null;') && str_contains($application, '$enterpriseCircuit=null;') && str_contains($application, 'if($schoolCircuit!==null&&$enterpriseCircuit!==null)') && !str_contains($application, 'new CircuitBreaker();'), 'unavailable provider health keeps both AI adapters null');
school_ai_assert(str_contains($application, '$enqueueSchoolRefresh=$schoolRefreshQueue===null?null:'), 'unavailable school refresh queue is passed as a null callback');

echo "school_ai_insight_test: OK\n";
