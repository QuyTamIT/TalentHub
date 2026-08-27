<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Service\RecommendationClickService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\ApiException;
use TalentHub\Learner\Api\LearnerApiContext;
use TalentHub\Rbac\Service\PermissionService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE learner_recommendation_runs (id TEXT PRIMARY KEY, studentId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE learner_recommendation_items (id TEXT PRIMARY KEY, runId TEXT NOT NULL)');
$pdo->exec('CREATE TABLE learner_recommendation_evidence (id TEXT PRIMARY KEY, itemId TEXT NOT NULL, sourceType TEXT NOT NULL, sourceId TEXT NOT NULL)');
$pdo->exec("INSERT INTO learner_recommendation_runs (id,studentId) VALUES ('run-owned','student-owned'),('run-other','student-other')");
$pdo->exec("INSERT INTO learner_recommendation_items (id,runId) VALUES ('item-owned','run-owned'),('item-other','run-other')");
$pdo->exec("INSERT INTO learner_recommendation_evidence (id,itemId,sourceType,sourceId) VALUES ('e1','item-owned','catalog','catalog-owned'),('e2','item-other','catalog','catalog-other')");

$events = [];
$collector = new AiMetricsCollector(20, static function (array $event) use (&$events): void { $events[] = $event; });
$service = new RecommendationClickService(new DatabaseRecommendationRepository($pdo), $collector);

if (!$service->record('student-owned', 'item-owned', 'catalog-owned', 'open_catalog_item')) {
    throw new RuntimeException('Owned recommendation CTA click was not recorded.');
}
if (count($events) !== 1 || ($events[0]['recommendation_click'] ?? null) !== true || ($events[0]['recommendation_action'] ?? null) !== 'open_catalog_item') {
    throw new RuntimeException('Click telemetry event is missing its safe aggregate fields.');
}
foreach (['student_id','studentId','item_id','itemId','catalog_id','catalogId','url','title','api_key','raw_response'] as $forbidden) {
    if (array_key_exists($forbidden, $events[0])) throw new RuntimeException("Click telemetry leaked {$forbidden}.");
}

foreach ([
    ['student-other','item-owned','catalog-owned','open_catalog_item'],
    ['student-owned','item-owned','catalog-other','open_catalog_item'],
] as [$studentId,$itemId,$catalogId,$action]) {
    try { $service->record($studentId, $itemId, $catalogId, $action); throw new RuntimeException('Cross-owner or mismatched click target was accepted.'); }
    catch (DomainException) { /* Expected. */ }
}
try {
    $service->record('student-owned', 'item-owned', 'catalog-owned', 'arbitrary_action');
    throw new RuntimeException('Invalid click action was accepted.');
} catch (InvalidArgumentException) {
    // Expected.
}
if (count($events) !== 1) throw new RuntimeException('Rejected clicks must not emit telemetry.');

$session = new SessionManager([
    'name' => SessionManager::SESSION_STUDENT,
    'lifetime' => 3600,
    'secure' => false,
    'sameSite' => 'Lax',
    'savePath' => sys_get_temp_dir(),
]);
$_SESSION = ['csrfToken' => 'csrf-owned', 'csrf_token' => 'csrf-owned'];
$context = new LearnerApiContext($pdo, $session, new PermissionService($pdo), 'request-click-test');
$context->mutation('csrf-owned');
try {
    $context->mutation('csrf-wrong');
    throw new RuntimeException('Invalid CSRF token was accepted.');
} catch (ApiException $exception) {
    if ($exception->errorCode !== 'CSRF_INVALID' || $exception->status !== 403) throw $exception;
}

$endpoint = file_get_contents(dirname(__DIR__) . '/app/learner/api/v1/recommendation-click.php');
if (!is_string($endpoint)) throw new RuntimeException('Recommendation click endpoint is missing.');
foreach (["method !== 'POST'", "studentId('student_profile.update_own')", "mutation(\$request->header('x-csrf-token'))", "allowedInput(\$request->json(), ['itemId', 'catalogId', 'actionType'])"] as $marker) {
    if (!str_contains($endpoint, $marker)) throw new RuntimeException("Endpoint security marker missing: {$marker}");
}
if (!str_contains($endpoint, "array_key_exists('catalogId', \$input)")) {
    throw new RuntimeException('Endpoint must reject a supplied non-string catalog identifier.');
}
if (!str_contains($endpoint, 'catch (\DomainException)') || str_contains($endpoint, 'catch (RuntimeException)')) {
    throw new RuntimeException('Endpoint must distinguish an unavailable target from an infrastructure RuntimeException.');
}
foreach (['safeComment','title','summary','url','studentId'] as $forbiddenInput) {
    if ($forbiddenInput !== 'studentId' && str_contains($endpoint, "'{$forbiddenInput}'")) throw new RuntimeException("Endpoint accepts forbidden input: {$forbiddenInput}");
}

echo "learner_ai_recommendation_click_test: OK\n";
