<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Model\ModelRoadmapEngine;
use TalentHub\Learner\Ai\Model\RoadmapModelUnavailable;
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

function roadmap_engine_input(bool $withCatalog = false): RecommendationInput
{
    $codes = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
    $records = $evidence = [];
    foreach ($codes as $index => $code) {
        $record = ['test_type' => $code, 'result_code' => strtoupper(substr($code, 0, 3)), 'dimension_scores' => ['A' => 70 + $index], 'submitted_at' => '2026-08-20T00:00:00+00:00'];
        $records[] = $record;
        $evidence[] = ['source_type' => 'assessment', 'source_id' => 'result-' . $index, 'observed_at' => '2026-08-20T00:00:00+00:00', 'safe_value' => $record];
    }
    $opportunities = [];
    $sourceUpdatedAt = ['assessment' => '2026-08-20T00:00:00+00:00'];
    if ($withCatalog) {
        $catalogId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $catalog = ['title' => 'IoT Lab', 'category' => 'technology', 'opportunity_type' => 'activity'];
        $opportunities[] = $catalog;
        $evidence[] = ['source_type' => 'opportunity', 'source_id' => $catalogId, 'observed_at' => '2026-08-21T00:00:00+00:00', 'safe_value' => $catalog];
        $sourceUpdatedAt['opportunity'] = '2026-08-21T00:00:00+00:00';
    }
    return new RecommendationInput(
        ['profile' => ['study_status' => 'active'], 'assessments' => $records, 'skills' => [], 'activities' => [], 'evaluations' => [], 'opportunities' => $opportunities],
        $sourceUpdatedAt,
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

$catalogFixture = $fixture;
$catalogFixture['potential_paths'] = [[
    'label' => 'Thử nghiệm IoT qua hoạt động thực tế',
    'catalog_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'evidence_ref_ids' => ['evidence-005'],
]];
$catalogModel = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::success($catalogFixture, null, str_repeat('e', 64))))
    ->generate(roadmap_engine_input(true), roadmap_engine_context());
roadmap_engine_assert($catalogModel->origin() === 'model', 'catalog id supplied to Gemini and allowed by the prompt is accepted by the validator');
$fabricatedCatalog = $catalogFixture;
$fabricatedCatalog['potential_paths'][0]['catalog_id'] = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
try {
    roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::success($fabricatedCatalog, null, str_repeat('f', 64))))
        ->generate(roadmap_engine_input(true), roadmap_engine_context());
    roadmap_engine_assert(false, 'a fabricated catalog id must be rejected');
} catch (RoadmapModelUnavailable $exception) {
    roadmap_engine_assert($exception->reason() === 'invalid_model_response', 'fabricated catalog ids fail with the canonical safe reason');
}

$unsafe = $fixture;
$unsafe['executive_summary'] = 'Lộ trình này đảm bảo bạn đỗ đại học 100%.';
try {
    $unsafeModel = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::success($unsafe, null, str_repeat('d', 64))))
        ->generate(roadmap_engine_input(), roadmap_engine_context());
    roadmap_engine_assert(false, 'deterministic safety violations must throw rather than fall back to a rule roadmap');
} catch (RoadmapModelUnavailable $exception) {
    roadmap_engine_assert($exception->reason() === 'invalid_model_response', 'unsafe output throws with the canonical reason');
}

$unknown = $fixture;
$unknown['unexpected_model_field'] = 'must fail';
try {
    $invalid = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::success($unknown, null, str_repeat('b', 64))))
        ->generate(roadmap_engine_input(), roadmap_engine_context());
    roadmap_engine_assert(false, 'unknown model fields must throw');
} catch (RoadmapModelUnavailable $exception) {
    roadmap_engine_assert($exception->reason() === 'invalid_model_response', 'unknown fields throw with the allow-listed reason');
}

$missingCitation = $fixture;
$missingCitation['insights'][0]['evidence_ref_ids'] = [];
try {
    $uncited = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::success($missingCitation, null, str_repeat('c', 64))))
        ->generate(roadmap_engine_input(), roadmap_engine_context());
    roadmap_engine_assert(false, 'missing citations must throw');
} catch (RoadmapModelUnavailable $exception) {
    roadmap_engine_assert($exception->reason() === 'invalid_model_response', 'missing citations throw with the canonical reason');
}

try {
    $failure = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::failure('provider_unavailable')))
        ->generate(roadmap_engine_input(), roadmap_engine_context());
    roadmap_engine_assert(false, 'provider failure must throw rather than fall back to a rule roadmap');
} catch (RoadmapModelUnavailable $exception) {
    roadmap_engine_assert($exception->reason() === 'provider_unavailable', 'provider failure throws with the allow-listed reason');
}

try {
    $unsafeReason = roadmap_engine_build(roadmap_engine_provider(RoadmapProviderResponse::failure('secret-internal-error')))
        ->generate(roadmap_engine_input(), roadmap_engine_context());
    roadmap_engine_assert(false, 'unrecognised provider errors must throw');
} catch (RoadmapModelUnavailable $exception) {
    roadmap_engine_assert($exception->reason() === 'provider_unavailable', 'unrecognised errors are normalised to the allow-listed reason');
}

$disabledProvider = roadmap_engine_provider(RoadmapProviderResponse::failure('should-not-run'));
try {
    $disabled = roadmap_engine_build($disabledProvider, false)->generate(roadmap_engine_input(), roadmap_engine_context());
    roadmap_engine_assert(false, 'disabled configuration must throw');
} catch (RoadmapModelUnavailable $exception) {
    roadmap_engine_assert($exception->reason() === 'model_disabled', 'disabled configuration throws with the canonical reason');
    roadmap_engine_assert($disabledProvider->calls === 0, 'disabled model never calls provider');
}

echo "learner_ai_roadmap_engine_test: OK\n";
