<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Model\ModelRoadmapEngine;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Provider\RoadmapProviderResponse;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rules\RuleRoadmapEngine;
use TalentHub\Learner\Ai\Sources\ConsentSource;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/fixtures/learner_ai_roadmap_v1.php';

function roadmap_engine_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

function roadmap_engine_config(bool $enabled = true): RecommendationConfig
{
    if (!$enabled) return RecommendationConfig::fromEnvironment(['TALENTHUB_AI_ENABLED' => 'false']);
    return RecommendationConfig::fromEnvironment([
        'APP_ENV' => 'test', 'TALENTHUB_AI_ENABLED' => 'true', 'TALENTHUB_AI_PROVIDER' => '9router_gemini',
        'TALENTHUB_AI_MODEL' => 'ag/gemini-3.7-flash-high', 'TALENTHUB_AI_API_URL' => 'http://127.0.0.1:20128/v1/chat/completions',
        'TALENTHUB_AI_API_KEY' => 'test-key', 'TALENTHUB_AI_ALLOWED_HOSTS' => '127.0.0.1',
    ]);
}

function roadmap_engine_input(): RecommendationInput
{
    $codes = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
    $records = $evidence = [];
    foreach ($codes as $index => $code) {
        $record = ['test_type' => $code, 'result_code' => strtoupper(substr($code, 0, 3)), 'dimension_scores' => ['A' => 70 + $index], 'submitted_at' => '2026-08-20T00:00:00+00:00'];
        $records[] = $record;
        $evidence[] = ['source_type' => 'assessment', 'source_id' => 'result-' . $index, 'observed_at' => '2026-08-20T00:00:00+00:00', 'safe_value' => $record];
    }
    return new RecommendationInput(
        ['profile' => ['study_status' => 'active'], 'assessments' => $records, 'skills' => [], 'activities' => [], 'evaluations' => [], 'opportunities' => []],
        ['assessment' => '2026-08-20T00:00:00+00:00'],
        ['allowed_scopes' => ['assessment'], 'missing_consent_scopes' => ['activity', 'evaluation', 'skills']],
        $evidence,
    );
}

function roadmap_engine_consent_gate(): ProviderConsentGate
{
    $source = new class implements ConsentSource {
        public function forStudent(string $studentId): array
        {
            return [['scope' => 'assessment', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-24T00:00:00+00:00', 'request_id' => 'consent-request']];
        }
    };
    return new ProviderConsentGate(new ConsentPolicy($source, static fn (): string => '2026-08-24T00:01:00+00:00'), ['assessment']);
}

function roadmap_engine_context(): RecommendationContext
{
    $gate = roadmap_engine_consent_gate();
    $policy = new ReflectionProperty($gate, 'policy');
    $decision = $policy->getValue($gate)->decision('student-roadmap');
    return new RecommendationContext(['assessment'], 'request', 'idem', 'student-roadmap', $decision->decisionHash(), $decision->policyVersion());
}

function roadmap_engine_provider(RoadmapProviderResponse $response): RoadmapProvider
{
    return new class($response) implements RoadmapProvider {
        public int $calls = 0;
        public function __construct(private readonly RoadmapProviderResponse $response) {}
        public function generate($request, $authorizer): RoadmapProviderResponse
        {
            $this->calls++;
            $authorizer->beforeAttempt(1);
            return $this->response;
        }
    };
}

function roadmap_engine_build(RoadmapProvider $provider, bool $enabled = true): ModelRoadmapEngine
{
    return new ModelRoadmapEngine(
        $provider,
        new RuleRoadmapEngine(),
        new RoadmapPromptRegistry(),
        new RecommendationRateLimiter(10, 100, 60, static fn (): int => 1000),
        roadmap_engine_config($enabled),
        roadmap_engine_consent_gate(),
    );
}

roadmap_engine_assert(class_exists(ModelRoadmapEngine::class), 'model roadmap engine is loaded');
$fixture = learner_ai_roadmap_provider_fixture();
$successProvider = roadmap_engine_provider(RoadmapProviderResponse::success($fixture, 'router_req_model', str_repeat('a', 64)));
$model = roadmap_engine_build($successProvider)->generate(roadmap_engine_input(), roadmap_engine_context());
roadmap_engine_assert($model->origin() === 'model', 'valid provider output is labelled model');
roadmap_engine_assert(($model->engineMetadata()['provider'] ?? null) === '9router_gemini', 'provider provenance is retained');
roadmap_engine_assert(($model->engineMetadata()['model_version'] ?? null) === 'ag/gemini-3.7-flash-high', 'model provenance is retained');
roadmap_engine_assert($model->confidenceBand() === 'high', 'four required assessment sources produce high source coverage');
roadmap_engine_assert($model->providerRequestId() === 'router_req_model', 'safe provider request id is retained for audit');
roadmap_engine_assert($model->responseHash() === str_repeat('a', 64), 'response hash is retained for audit');
roadmap_engine_assert($model->fallbackReason() === null, 'model result has no fallback reason');

$unsafe = $fixture;
$unsafe['executive_summary'] = 'Lộ trình này đảm bảo bạn đỗ đại học 100%.';
$unsafeModel = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::success($unsafe, null, str_repeat('d', 64))))
    ->generate(roadmap_engine_input(), roadmap_engine_context());
roadmap_engine_assert(
    $unsafeModel->origin() === 'rule_fallback' && $unsafeModel->fallbackReason() === 'invalid_model_response',
    'deterministic safety violations are rejected before a model roadmap can be persisted',
);

$unknown = $fixture;
$unknown['unexpected_model_field'] = 'must fail';
$invalid = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::success($unknown, null, str_repeat('b', 64))))
    ->generate(roadmap_engine_input(), roadmap_engine_context());
roadmap_engine_assert($invalid->origin() === 'rule_fallback', 'unknown model fields trigger fallback');
roadmap_engine_assert($invalid->fallbackReason() === 'invalid_model_response', 'invalid output has an allow-listed reason');

$missingCitation = $fixture;
$missingCitation['insights'][0]['evidence_ref_ids'] = [];
$uncited = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::success($missingCitation, null, str_repeat('c', 64))))
    ->generate(roadmap_engine_input(), roadmap_engine_context());
roadmap_engine_assert($uncited->origin() === 'rule_fallback' && $uncited->fallbackReason() === 'invalid_model_response', 'missing citations trigger fallback');

$failure = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::failure('provider_unavailable')))
    ->generate(roadmap_engine_input(), roadmap_engine_context());
roadmap_engine_assert($failure->origin() === 'rule_fallback' && $failure->fallbackReason() === 'provider_unavailable', 'provider failure is visibly fallback');
roadmap_engine_assert(count($failure->phases()) === 3 && count($failure->phases()[0]->tasks()) === 3, 'fallback remains actionable');

$unsafeReason = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::failure('secret-internal-error')))
    ->generate(roadmap_engine_input(), roadmap_engine_context());
roadmap_engine_assert($unsafeReason->fallbackReason() === 'provider_unavailable', 'unrecognized errors are normalized');

$disabledProvider = roadmap_engine_provider(RoadmapProviderResponse::failure('should-not-run'));
$disabled = roadmap_engine_build($disabledProvider, false)->generate(roadmap_engine_input(), roadmap_engine_context());
roadmap_engine_assert($disabled->origin() === 'rule_fallback' && $disabled->fallbackReason() === 'model_disabled', 'disabled model is visibly fallback');
roadmap_engine_assert($disabledProvider->calls === 0, 'disabled model never calls provider');

echo "learner_ai_roadmap_engine_test: OK\n";
