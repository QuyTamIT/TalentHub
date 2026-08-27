<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Evaluation\RecommendationEvaluator;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Persistence\DatabaseRoadmapRepository;
use TalentHub\Learner\Ai\Provider\HttpRoadmapProvider;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

$approval = (string) ($_ENV['TALENTHUB_AI_LIVE_CONTRACT_APPROVED'] ?? getenv('TALENTHUB_AI_LIVE_CONTRACT_APPROVED') ?: '');
$environment = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));
if ($environment === 'production') {
    fwrite(STDERR, "LIVE_CONTRACT_REFUSED: APP_ENV=production\n");
    exit(2);
}
if ($approval !== 'I_UNDERSTAND_ONE_PROVIDER_CALL') {
    echo "learner_ai_roadmap_live_contract_test: SKIPPED (one-call approval missing)\n";
    exit(0);
}

// The repository fixture creates an in-memory disposable schema; no primary database is touched.
require_once __DIR__ . '/learner_ai_roadmap_repository_test.php';

function roadmap_live_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Live contract assertion failed: ' . $message);
}

$config = RecommendationConfig::fromEnvironment($_ENV);
$studentId = 'f0000000-0000-4000-8000-000000000001';
$fixture = roadmap_repository_input('f');
$context = new RecommendationContext(['assessment'], 'live-contract-request', 'live-contract-idempotency', $studentId);
$request = (new RoadmapPromptRegistry())->create($fixture['input'], $context);
$decision = new ConsentDecision(['assessment'=>['action'=>'granted','policy_version'=>'live-test','occurred_at'=>'2026-08-24T00:00:00+00:00','request_id'=>'live-test']], '2026-08-24T00:00:01+00:00');
$authorizer = new class($decision) implements ProviderAttemptAuthorizer {
    public int $calls = 0;
    public function __construct(private ConsentDecision $decision) {}
    public function beforeAttempt(int $attemptNumber): ConsentDecision { $this->calls++; return $this->decision; }
};
$response = (new HttpRoadmapProvider($config))->generate($request, $authorizer);
roadmap_live_assert($authorizer->calls === 1, 'provider_call_count must equal one');
if (!$response->isSuccess()) {
    $safe = $response->safeMetadata();
    unset($safe['provider_request_id']);
    fwrite(STDERR, 'LIVE_CONTRACT_PROVIDER_FAILED: ' . json_encode($safe, JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}

$analysis = (new RoadmapAnalysisValidator($request->evidenceReferenceIds(), []))->fromProviderPayload($response->payload(), [
    'origin'=>'model','provider'=>(string)$config->provider(),'model_version'=>(string)$config->model(),
    'prompt_version'=>$request->promptVersion(),'confidence_band'=>'high',
    'provider_request_id'=>$response->providerRequestId(),'response_hash'=>$response->responseHash(),
]);
$evaluation = (new RecommendationEvaluator())->evaluateRoadmap($analysis, $fixture['input']);
if ($evaluation['valid'] !== true) {
    fwrite(STDERR, 'LIVE_CONTRACT_EVALUATION_FAILED: ' . json_encode([
        'violations' => $evaluation['violations'],
        'metrics' => $evaluation['metrics'],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n");
}
roadmap_live_assert($evaluation['valid'] === true, 'Vietnamese, grounding and safety evaluation must pass');

$snapshotId = 'f1000000-0000-4000-8000-000000000001';
$runId = 'f2000000-0000-4000-8000-000000000001';
roadmap_repository_seed_run($pdo, $studentId, $snapshotId, $runId, $fixture, 'model');
$pdo->prepare('UPDATE learner_recommendation_runs SET provider=?, modelVersion=?, promptVersion=? WHERE id=?')
    ->execute([(string)$config->provider(),(string)$config->model(),$request->promptVersion(),$runId]);
$repository = new DatabaseRoadmapRepository($pdo);
$saved = $repository->saveCompleted($studentId, $runId, $analysis, [
    'provider_request_id'=>$response->providerRequestId(),'response_hash'=>$response->responseHash(),'evidence_reference_map'=>$fixture['map'],
]);
roadmap_live_assert($saved['analysis_origin'] === 'model', 'persisted analysis origin must be model');
roadmap_live_assert($saved['run_id'] === $runId, 'roadmap must match the model run');
roadmap_live_assert(is_string($saved['engine']['provider'] ?? null) && is_string($saved['engine']['model_version'] ?? null) && is_string($saved['engine']['prompt_version'] ?? null), 'model provenance must be non-empty');
roadmap_live_assert(is_string($response->responseHash()) && strlen((string)$response->responseHash()) === 64, 'response hash must be non-empty');
roadmap_live_assert(count($analysis->evidenceReferenceIds()) > 0, 'all generated sections must cite snapshot evidence');

echo json_encode([
    'test'=>'learner_ai_roadmap_live_contract_test','status'=>'passed','provider_call_count'=>1,
    'analysis_origin'=>'model','provider'=>$config->provider(),'model'=>$config->model(),'prompt_version'=>$request->promptVersion(),
    'response_hash_prefix'=>substr((string)$response->responseHash(),0,12),
    'contract_validity'=>$evaluation['metrics']['roadmap_contract_validity'],
    'vietnamese_language_rate'=>$evaluation['metrics']['vietnamese_language_rate'],
    'evidence_coverage'=>$evaluation['metrics']['evidence_coverage'],
], JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES) . PHP_EOL;
