<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Evaluation\RecommendationEvaluator;
use TalentHub\Learner\Ai\Evaluation\ShadowRunService;
use TalentHub\Learner\Ai\Model\ModelRecommendationEngine;
use TalentHub\Learner\Ai\Model\PromptRegistry;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;
use TalentHub\Learner\Ai\Provider\HttpRecommendationProvider;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
use TalentHub\Learner\Ai\Service\RecommendationService;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;
use TalentHub\Learner\Api\LearnerApiContext;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/api/LearnerApiContext.php';
require_once __DIR__ . '/learner_ai_rule_cases_fixture.php';
require_once __DIR__ . '/learner_ai_test_consent.php';

function test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

echo "=== STARTING 9ROUTER SHADOW AI PROVIDER INTEGRATION TEST SUITE ===\n\n";

$baseConfigEnv = [
    'APP_ENV' => 'test',
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => '9router_gemini',
    'TALENTHUB_AI_MODEL' => 'gemini-1.5-flash-test',
    'TALENTHUB_AI_API_URL' => 'https://gateway.9router.test/v1/chat/completions',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'gateway.9router.test,ai.example.test',
    'TALENTHUB_AI_API_KEY' => 'test-shadow-key-never-in-prod',
    'TALENTHUB_AI_TIMEOUT_SECONDS' => '2',
    'TALENTHUB_AI_MAX_ATTEMPTS' => '2',
    'TALENTHUB_AI_PER_STUDENT_LIMIT' => '2',
    'TALENTHUB_AI_GLOBAL_LIMIT' => '5',
    'TALENTHUB_AI_SHADOW' => 'true',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '0',
    'TALENTHUB_AI_STRICT_MODE_OVERRIDE' => 'false',
];

$config = RecommendationConfig::fromEnvironment($baseConfigEnv);
test_assert($config->enabled() === true, 'config is enabled');
test_assert($config->visiblePercent() === 0, 'visible percent is strictly 0');
test_assert($config->shadowEnabled() === true, 'shadow is enabled');
test_assert($config->model() === 'gemini-1.5-flash-test', 'model name is configured');

// Standard input & context for testing (includes 2 skills, 1 assessment, 1 activity, 1 evaluation for standard quality gate)
$input = learner_rule_input(
    [
        learner_rule_iot_skill(),
        [
            '_source_id' => 'skill-python',
            'code' => 'python',
            'level_score' => 80,
            'verification_status' => 'verified',
            'source_updated_at' => '2026-06-16T09:00:00.000000+00:00',
        ],
    ],
    [learner_rule_holland()],
    [learner_rule_technical_activity()],
    [learner_rule_evaluation()],
);
$context = new RecommendationContext(
    ['assessment', 'skills', 'activity', 'evaluation'],
    'request-9router-test-001',
    'idempotency-9router-test-001',
    'student-shadow-001',
);
$promptRegistry = new PromptRegistry();
$request = $promptRegistry->create($input, $context);

$inMemoryRepo = new class implements RecommendationRepository {
    /** @var array<string,array<string,mixed>> */
    public array $runs = [];
    public ?RecommendationResult $lastCompleted = null;
    public ?string $lastFailedError = null;

    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array
    {
        $runId = 'run-' . bin2hex(random_bytes(8));
        $this->runs[$runId] = [
            'id' => $runId,
            'studentId' => $studentId,
            'status' => 'pending',
            'context' => $context,
        ];
        return ['runId' => $runId, 'reused' => false];
    }

    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array
    {
        $this->lastCompleted = $result;
        $items = array_map(static fn (RecommendationItem $item): array => [
            'itemId' => 'item-' . bin2hex(random_bytes(4)),
            'itemType' => $item->itemType(),
            'title' => $item->title(),
            'summary' => $item->summary(),
            'priority' => $item->priority(),
            'confidenceBand' => $item->confidenceBand(),
            'action' => $item->action(),
            'actionJson' => json_encode($item->action(), JSON_THROW_ON_ERROR),
            'evidence' => array_map(static fn (RecommendationEvidence $ev): array => [
                'sourceType' => $ev->sourceType(),
                'sourceId' => $ev->sourceId(),
                'observedAt' => $ev->observedAt(),
                'contributionLabel' => $ev->contributionLabel(),
                'safeValueJson' => json_encode($ev->safeValue(), JSON_THROW_ON_ERROR),
            ], $item->evidence()),
        ], $result->items());

        $run = [
            'runId' => $runId,
            'snapshotId' => 'snap-' . bin2hex(random_bytes(4)),
            'studentId' => $studentId,
            'status' => $result->fallbackReason() === null ? 'completed' : 'fallback',
            'engineType' => $result->engineType(),
            'ruleVersion' => $result->ruleVersion(),
            'provider' => $result->provider(),
            'modelVersion' => $result->modelVersion(),
            'promptVersion' => $result->promptVersion(),
            'fallbackReason' => $result->fallbackReason(),
            'completedAt' => '2026-08-20T13:00:00.000000+00:00',
            'items' => $items,
        ];
        $this->runs[$runId] = $run;
        return $run;
    }

    public function failRun(string $studentId, string $runId, string $safeErrorCode): void
    {
        $this->lastFailedError = $safeErrorCode;
        if (isset($this->runs[$runId])) {
            $this->runs[$runId]['status'] = 'failed';
            $this->runs[$runId]['error'] = $safeErrorCode;
        }
    }

    public function latestForStudent(string $studentId): ?array
    {
        foreach (array_reverse($this->runs) as $run) {
            if ($run['studentId'] === $studentId && in_array($run['status'] ?? '', ['completed', 'fallback'], true)) {
                return $run;
            }
        }
        return null;
    }

    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array
    {
        return ['feedback_id' => 'fb-1', 'student_id' => $studentId, 'verdict' => $verdict];
    }
};

$fallbackEngine = new RuleRecommendationEngine();

// =========================================================================
// SECTION 1: 9Router/Gemini HTTP Success via OpenAI & Gemini Formats
// =========================================================================
echo "--- Section 1: Provider Success via OpenAI & Gemini Response Formats ---\n";

$firstRefId = $request->evidenceReferenceIds()[0];
$expectedSourceId = $request->evidence($firstRefId)->sourceId();

// 1.1 OpenAI/9Router chat completion envelope format
$sentHeaders = [];
$sentBody = '';
$openAiMockHttp = static function (string $url, array $headers, string $body, int $timeout) use (&$sentHeaders, &$sentBody, $firstRefId): array {
    $sentHeaders = $headers;
    $sentBody = $body;
    $content = json_encode([
        'items' => [
            [
                'item_type' => 'strength',
                'title' => 'Phát triển kỹ năng đã xác minh',
                'summary' => 'Học viên có thế mạnh kỹ năng từ thực tế dự án.',
                'priority' => 10,
                'confidence_band' => 'high',
                'action' => ['type' => 'develop_skill', 'skill_code' => 'iot'],
                'evidence_ref_ids' => [$firstRefId],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    return [
        'status' => 200,
        'headers' => ['content-type' => 'application/json'],
        'body' => json_encode([
            'id' => 'chatcmpl-9router-test-01',
            'object' => 'chat.completion',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $content,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ];
};

$provider = new HttpRecommendationProvider($config, $openAiMockHttp);
$response = $provider->generate($request, learner_ai_test_provider_authorizer($input, $context));
test_assert($response->isSuccess() === true, '9Router chat completion envelope parsed successfully');
test_assert(count($response->items()) === 1, 'items extracted from chat completion choice');
test_assert(($sentHeaders['Authorization'] ?? '') === 'Bearer test-shadow-key-never-in-prod', 'Bearer token passed correctly');
test_assert(($sentHeaders['X-Model-Name'] ?? '') === 'gemini-1.5-flash-test', 'X-Model-Name header passed');
$decodedBody = json_decode($sentBody, true, 512, JSON_THROW_ON_ERROR);
test_assert(($decodedBody['model'] ?? '') === 'gemini-1.5-flash-test', 'model specified in JSON payload');
test_assert(!isset($decodedBody['input']) && !isset($decodedBody['evidence']), '9Router transport does not send custom fields at the top level');
test_assert(($decodedBody['messages'][0]['role'] ?? null) === 'system', '9Router transport includes a system message');
test_assert(($decodedBody['messages'][1]['role'] ?? null) === 'user', '9Router transport includes a user message');
$systemPrompt = (string) ($decodedBody['messages'][0]['content'] ?? '');
test_assert(str_contains($systemPrompt, '"strength"') && str_contains($systemPrompt, '"roadmap"'), '9Router system message constrains item type enums');
test_assert(str_contains($systemPrompt, '"register_activity"') && str_contains($systemPrompt, 'integer from 1 to 100'), '9Router system message constrains actions and priority types');
$transportContent = (string) ($decodedBody['messages'][1]['content'] ?? '');
$expectedTransportContent = json_encode($request->payload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
test_assert($transportContent === $expectedTransportContent, '9Router user message preserves the exact serialized provider payload');
$transportPrompt = json_decode($transportContent, true, 512, JSON_THROW_ON_ERROR);
test_assert(($transportPrompt['prompt_version'] ?? null) === PromptRegistry::VERSION, '9Router user message preserves the versioned provider payload');
test_assert(($transportPrompt['evidence'][0]['reference_id'] ?? null) === $firstRefId, '9Router user message preserves opaque evidence references');

$modelEngine = new ModelRecommendationEngine(
    $provider,
    $fallbackEngine,
    $promptRegistry,
    new RecommendationRateLimiter(5, 10, 60, static fn (): int => 1000),
    $config,
    new RecommendationResultValidator(),
    learner_ai_test_consent_gate(),
);
$modelResult = $modelEngine->generate($input, $context);
test_assert($modelResult->engineType() === 'model', 'ModelRecommendationEngine succeeds with model output');
test_assert($modelResult->items()[0]->evidence()[0]->sourceId() === $expectedSourceId, 'evidence ref resolved to source ID');

// 1.2 Gemini native envelope format with markdown code fences
$geminiMockHttp = static function (string $url, array $headers, string $body, int $timeout) use ($firstRefId): array {
    $rawText = "```json\n" . json_encode([
        'items' => [
            [
                'item_type' => 'activity',
                'title' => 'Tiếp tục tham gia CLB Kỹ thuật',
                'summary' => 'Hoạt động gắn liền với trải nghiệm thực tế đã xác nhận.',
                'priority' => 15,
                'confidence_band' => 'high',
                'action' => ['type' => 'continue_technical_activity', 'activity_source_id' => 'act-exp-001'],
                'evidence_ref_ids' => [$firstRefId],
            ],
        ],
    ], JSON_THROW_ON_ERROR) . "\n```";

    return [
        'status' => 200,
        'headers' => ['content-type' => 'application/json'],
        'body' => json_encode([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $rawText],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ];
};

$geminiProvider = new HttpRecommendationProvider($config, $geminiMockHttp);
$geminiResponse = $geminiProvider->generate($request, learner_ai_test_provider_authorizer($input, $context));
test_assert($geminiResponse->isSuccess() === true, 'Gemini candidate envelope with markdown fences parsed successfully');
test_assert($geminiResponse->items()[0]['item_type'] === 'activity', 'correct item type extracted');

// 1.3 Direct JSON format
$directMockHttp = static function (string $url, array $headers, string $body, int $timeout): array {
    return [
        'status' => 200,
        'headers' => ['content-type' => 'application/json'],
        'body' => json_encode([
            'items' => [
                [
                    'item_type' => 'development',
                    'title' => 'Lộ trình phát triển năng lực',
                    'summary' => 'Rèn luyện kỹ năng thuyết trình trong 4 tuần.',
                    'priority' => 25,
                    'confidence_band' => 'medium',
                    'action' => ['type' => 'practice_presentation', 'weeks' => 4, 'steps' => ['Tuần 1-2', 'Tuần 3-4']],
                    'evidence_ref_ids' => ['evidence-004'],
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ];
};
$directProvider = new HttpRecommendationProvider($config, $directMockHttp);
$directResponse = $directProvider->generate($request, learner_ai_test_provider_authorizer($input, $context));
test_assert($directResponse->isSuccess() === true, 'Direct JSON schema parsed successfully');

echo "Section 1 PASS\n\n";

// =========================================================================
// SECTION 2: Provider Timeout & Safe Rule Engine Fallback
// =========================================================================
echo "--- Section 2: Provider Timeout & Safe Fallback ---\n";

$timeoutHttp = static function (string $url, array $headers, string $body, int $timeout): array {
    throw new RuntimeException('Connection timed out after ' . $timeout . ' seconds.');
};

$timeoutProvider = new HttpRecommendationProvider($config, $timeoutHttp);
$timeoutResponse = $timeoutProvider->generate($request, learner_ai_test_provider_authorizer($input, $context));
test_assert($timeoutResponse->isSuccess() === false, 'timeout produces failed response');
test_assert($timeoutResponse->errorCode() === 'provider_unavailable', 'error code is provider_unavailable');

$timeoutEngine = new ModelRecommendationEngine(
    $timeoutProvider,
    $fallbackEngine,
    $promptRegistry,
    new RecommendationRateLimiter(5, 10, 60, static fn (): int => 1000),
    $config,
    new RecommendationResultValidator(),
    learner_ai_test_consent_gate(),
);
$timeoutResult = $timeoutEngine->generate($input, $context);
test_assert($timeoutResult->engineType() === 'rule', 'timeout gracefully falls back to rule engine');
test_assert($timeoutResult->fallbackReason() === 'provider_unavailable', 'fallback reason records provider_unavailable');
test_assert(count($timeoutResult->items()) > 0, 'fallback rule items returned to caller');

echo "Section 2 PASS\n\n";

// =========================================================================
// SECTION 3: HTTP 4xx / 5xx Errors & Retries
// =========================================================================
echo "--- Section 3: HTTP 4xx / 5xx Errors & Bounded Retries ---\n";

// 3.1 HTTP 400 Bad Request
$http400 = new HttpRecommendationProvider($config, static fn (): array => ['status' => 400, 'headers' => [], 'body' => 'Bad Request']);
test_assert($http400->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'provider_rejected', '400 produces provider_rejected');

// 3.2 HTTP 401 Unauthorized
$http401 = new HttpRecommendationProvider($config, static fn (): array => ['status' => 401, 'headers' => [], 'body' => 'Unauthorized']);
test_assert($http401->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'provider_rejected', '401 produces provider_rejected');

// 3.3 HTTP 403 Forbidden
$http403 = new HttpRecommendationProvider($config, static fn (): array => ['status' => 403, 'headers' => [], 'body' => 'Forbidden']);
test_assert($http403->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'provider_rejected', '403 produces provider_rejected');

// 3.4 HTTP 500 Internal Server Error
$http500 = new HttpRecommendationProvider($config, static fn (): array => ['status' => 500, 'headers' => [], 'body' => 'Internal Server Error']);
test_assert($http500->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'provider_unavailable', '500 produces provider_unavailable');

// 3.5 HTTP 502 with Retry up to maxAttempts
$attemptsCount = 0;
$http502 = new HttpRecommendationProvider($config, static function () use (&$attemptsCount): array {
    $attemptsCount++;
    return ['status' => 502, 'headers' => [], 'body' => 'Bad Gateway'];
});
$res502 = $http502->generate($request, learner_ai_test_provider_authorizer($input, $context));
test_assert($res502->errorCode() === 'provider_unavailable', '502 produces provider_unavailable');
test_assert($attemptsCount === 2, '502 retried exactly maxAttempts (2) times');

echo "Section 3 PASS\n\n";

// =========================================================================
// SECTION 4: Malformed Responses
// =========================================================================
echo "--- Section 4: Malformed Responses ---\n";

// 4.1 Non-JSON string
$malformedText = new HttpRecommendationProvider($config, static fn (): array => ['status' => 200, 'headers' => [], 'body' => '<html>Server Error</html>']);
test_assert($malformedText->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'malformed_response', 'non-JSON body is malformed_response');

// 4.2 Incomplete / Broken JSON
$brokenJson = new HttpRecommendationProvider($config, static fn (): array => ['status' => 200, 'headers' => [], 'body' => '{"items": [{"title": "incomp']);
test_assert($brokenJson->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'malformed_response', 'broken JSON is malformed_response');

// 4.3 Missing items key
$missingItems = new HttpRecommendationProvider($config, static fn (): array => ['status' => 200, 'headers' => [], 'body' => '{"status": "ok"}']);
test_assert($missingItems->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'malformed_response', 'missing items key is malformed_response');

// 4.4 Non-array items
$scalarItems = new HttpRecommendationProvider($config, static fn (): array => ['status' => 200, 'headers' => [], 'body' => '{"items": "string-not-array"}']);
test_assert($scalarItems->generate($request, learner_ai_test_provider_authorizer($input, $context))->errorCode() === 'malformed_response', 'non-array items is malformed_response');

// 4.5 Model Engine fallback on malformed response
$malformedEngine = new ModelRecommendationEngine(
    $brokenJson,
    $fallbackEngine,
    $promptRegistry,
    new RecommendationRateLimiter(5, 10, 60, static fn (): int => 1000),
    $config,
    new RecommendationResultValidator(),
    learner_ai_test_consent_gate(),
);
$malformedResult = $malformedEngine->generate($input, $context);
test_assert($malformedResult->engineType() === 'rule', 'malformed response triggers fallback to rule engine');
test_assert($malformedResult->fallbackReason() === 'malformed_response', 'fallback reason records malformed_response');

echo "Section 4 PASS\n\n";

// =========================================================================
// SECTION 5: Quality Gate Rejection (Production Standard Quality Gate)
// =========================================================================
echo "--- Section 5: Quality Gate Rejection ---\n";

// 5.1 Empty input fails quality gate
$defaultQualityGate = new DataQualityGate();
$emptyInput = new RecommendationInput([], [], ['allowed_scopes' => []], []);
$emptyQualityResult = $defaultQualityGate->evaluate($emptyInput);
test_assert($emptyQualityResult->state() !== 'ready', 'empty input fails default quality gate');

$serviceWithEmptyGate = new RecommendationService(
    $inMemoryRepo,
    $fallbackEngine,
    new RecommendationResultValidator(),
    new RecommendationResponseMapper(),
    static fn (string $id): bool => true,
    static fn (string $id): array => ['assessment', 'skills', 'activity', 'evaluation'],
    static fn (string $id, array $scopes): RecommendationInput => $emptyInput,
    static fn (RecommendationInput $inp) => $defaultQualityGate->evaluate($inp),
    static fn ($inp): bool => true,
);

$qualityResponse = $serviceWithEmptyGate->generate('student-001', 'req-q-01', 'idemp-0000000000000001');
test_assert(($qualityResponse['state'] ?? '') === 'ai_unavailable' && ($qualityResponse['quality_state'] ?? '') === 'insufficient_data', 'quality gate failure returns canonical unavailable state without invoking provider');
test_assert(in_array('assessment', $qualityResponse['missing_categories'] ?? [], true), 'missing assessment identified');

// 5.2 P1 Regression: Assessment-only input MUST fail standard production quality gate (allowAssessmentOnly is strictly FALSE)
$assessmentOnlyInput = learner_rule_input([], [learner_rule_holland()], [], []);
$assessmentOnlyQualityResult = $defaultQualityGate->evaluate($assessmentOnlyInput);
test_assert($assessmentOnlyQualityResult->state() === 'insufficient_data', 'assessment-only input returns insufficient_data on default quality gate');
test_assert(in_array('skills', $assessmentOnlyQualityResult->missingCategories(), true), 'missing skills identified');
test_assert(in_array('experience', $assessmentOnlyQualityResult->missingCategories(), true), 'missing experience identified');
test_assert(in_array('evaluations', $assessmentOnlyQualityResult->missingCategories(), true), 'missing evaluations identified');

$providerCallCount = 0;
$spyProvider = new HttpRecommendationProvider($config, static function () use (&$providerCallCount): array {
    $providerCallCount++;
    return ['status' => 200, 'headers' => [], 'body' => '{"items":[]}'];
});
$spyModelEngine = new ModelRecommendationEngine(
    $spyProvider,
    $fallbackEngine,
    $promptRegistry,
    new RecommendationRateLimiter(5, 10, 60, static fn (): int => 1000),
    $config,
    new RecommendationResultValidator(),
    learner_ai_test_consent_gate(),
);

$serviceWithAssessmentOnly = new RecommendationService(
    $inMemoryRepo,
    $fallbackEngine,
    new RecommendationResultValidator(),
    new RecommendationResponseMapper(),
    static fn (string $id): bool => true,
    static fn (string $id): array => ['assessment', 'skills', 'activity', 'evaluation'],
    static fn (string $id, array $scopes): RecommendationInput => $assessmentOnlyInput,
    static fn (RecommendationInput $inp) => $defaultQualityGate->evaluate($inp),
    static fn ($inp): bool => true,
    $spyModelEngine,
    $config,
    new RecommendationRolloutSelector(),
);

$assessmentOnlyResponse = $serviceWithAssessmentOnly->generate('student-001', 'req-q-02', 'idemp-0000000000000002');
test_assert(($assessmentOnlyResponse['state'] ?? '') === 'ai_unavailable' && ($assessmentOnlyResponse['quality_state'] ?? '') === 'insufficient_data', 'service preserves insufficient-data detail inside canonical availability');
test_assert($providerCallCount === 0, 'HTTP AI provider is NEVER invoked when quality gate fails');

// 5.3 Full input satisfies standard quality gate
$fullQualityResult = $defaultQualityGate->evaluate($input);
test_assert($fullQualityResult->state() === 'ready', 'full input with 2 skills, assessment, activity, evaluation passes standard quality gate');

echo "Section 5 PASS\n\n";

// =========================================================================
// SECTION 6: Consent Missing / Revoked
// =========================================================================
echo "--- Section 6: Consent Missing / Revoked ---\n";

$rollout = new RecommendationRolloutSelector();
// When required consent is incomplete (e.g. only 'skills')
$partialScopes = ['skills'];
test_assert(
    $rollout->canShowModel('student-001', $config, $partialScopes, true) === false,
    'missing consent scopes strictly prevent model visibility',
);

// Empty consent scopes
test_assert(
    $rollout->canShowModel('student-001', $config, [], true) === false,
    'empty consent strictly prevents model visibility',
);

echo "Section 6 PASS\n\n";

// =========================================================================
// SECTION 7: Rate Limiting & 429 Retry-After
// =========================================================================
echo "--- Section 7: Rate Limiting & 429 Retry-After ---\n";

// 7.1 Per-student rate limit (burst=2)
$limiter = new RecommendationRateLimiter(2, 100, 60, static fn (): int => 1000);
$limitedEngine = new ModelRecommendationEngine(
    $provider,
    $fallbackEngine,
    $promptRegistry,
    $limiter,
    $config,
    new RecommendationResultValidator(),
    learner_ai_test_consent_gate(),
);

$r1 = $limitedEngine->generate($input, $context);
test_assert($r1->engineType() === 'model', 'first call within per-student limit succeeds');

$r2 = $limitedEngine->generate($input, $context);
test_assert($r2->engineType() === 'model', 'second call within per-student limit succeeds');

$r3 = $limitedEngine->generate($input, $context);
test_assert($r3->engineType() === 'rule', 'third call exceeds per-student limit, falls back to rule engine');
test_assert($r3->fallbackReason() === 'rate_limited', 'fallback reason is rate_limited');

// 7.2 Global rate limit (burst=1)
$globalLimiter = new RecommendationRateLimiter(10, 1, 60, static fn (): int => 1000);
$globalLimitedEngine = new ModelRecommendationEngine(
    $provider,
    $fallbackEngine,
    $promptRegistry,
    $globalLimiter,
    $config,
    new RecommendationResultValidator(),
    learner_ai_test_consent_gate(),
);

$contextStudent2 = new RecommendationContext(
    ['assessment', 'skills', 'activity', 'evaluation'],
    'req-g-01',
    'idemp-g-01',
    'student-shadow-002',
);
$gr1 = $globalLimitedEngine->generate($input, $context);
test_assert($gr1->engineType() === 'model', 'first global call succeeds');

$gr2 = $globalLimitedEngine->generate($input, $contextStudent2);
test_assert($gr2->engineType() === 'rule', 'second global call from different student hits global limit');
test_assert($gr2->fallbackReason() === 'rate_limited', 'fallback reason is rate_limited for global burst exceeded');

// 7.3 HTTP 429 with Retry-After from gateway
$http429Provider = new HttpRecommendationProvider($config, static fn (): array => [
    'status' => 429,
    'headers' => ['retry-after' => '30', 'content-type' => 'application/json'],
    'body' => json_encode(['error' => 'Rate limit exceeded']),
]);
$engine429 = new ModelRecommendationEngine(
    $http429Provider,
    $fallbackEngine,
    $promptRegistry,
    new RecommendationRateLimiter(5, 10, 60, static fn (): int => 1000),
    $config,
    new RecommendationResultValidator(),
    learner_ai_test_consent_gate(),
);
$res429 = $engine429->generate($input, $context);
test_assert($res429->engineType() === 'rule', 'HTTP 429 safely falls back to rule engine');
test_assert($res429->fallbackReason() === 'rate_limited', 'HTTP 429 sets fallback_reason to rate_limited');

echo "Section 7 PASS\n\n";

// =========================================================================
// SECTION 8: Learner Isolation
// =========================================================================
echo "--- Section 8: Learner Isolation ---\n";

$contextA = new RecommendationContext(['skills'], 'req-iso-A', 'idemp-000000000000000A', 'student-iso-A');
$contextB = new RecommendationContext(['skills'], 'req-iso-B', 'idemp-000000000000000B', 'student-iso-B');

$requestA = $promptRegistry->create($input, $contextA);
$requestB = $promptRegistry->create($input, $contextB);

test_assert($contextA->studentId() !== $contextB->studentId(), 'contexts have distinct student IDs');
test_assert($requestA->promptVersion() === $requestB->promptVersion(), 'prompts use identical versioned schema');

echo "Section 8 PASS\n\n";

// =========================================================================
// SECTION 9: TALENTHUB_AI_VISIBLE_PERCENT=0 Invariant
// =========================================================================
echo "--- Section 9: TALENTHUB_AI_VISIBLE_PERCENT=0 Invariant ---\n";

// Create RecommendationService with active modelEngine, modelConfig, rolloutSelector, and default DataQualityGate
$recService = new RecommendationService(
    $inMemoryRepo,
    $fallbackEngine,
    new RecommendationResultValidator(),
    new RecommendationResponseMapper(),
    static fn (string $candidate): bool => true,
    static fn (string $candidate): array => ['assessment', 'skills', 'activity', 'evaluation'],
    static fn (string $candidate, array $scopes): RecommendationInput => $input,
    static fn (RecommendationInput $inp) => (new DataQualityGate())->evaluate($inp),
    static fn ($inp): bool => true,
    $modelEngine,
    $config,
    $rollout,
);

$generateOutput = $recService->generate('student-shadow-001', 'req-vis-01', 'idemp-0000000000000001');
test_assert(($generateOutput['state'] ?? '') === 'ready_rule', 'recommendation state is ready_rule');
test_assert(($generateOutput['status'] ?? '') === 'completed', 'recommendation status is completed');
test_assert(($generateOutput['engine_type'] ?? '') === 'rule', 'engine_type returned to API/UI is STRICTLY rule');
test_assert(isset($generateOutput['items']) && count($generateOutput['items']) > 0, 'rule items returned');

// Verify latest() also returns rule engine
$latestOutput = $recService->latest('student-shadow-001');
test_assert($latestOutput !== null, 'latest run found');
test_assert(($latestOutput['state'] ?? '') === 'ready_rule', 'latest run state is ready_rule');
test_assert(($latestOutput['engine_type'] ?? '') === 'rule', 'latest run engine_type is STRICTLY rule');

echo "Section 9 PASS\n\n";

// =========================================================================
// SECTION 10: Shadow Run Service Execution & Persistence
// =========================================================================
echo "--- Section 10: Shadow Run Execution & Evaluation ---\n";

$evaluator = new RecommendationEvaluator();
$shadowService = new ShadowRunService($inMemoryRepo, $modelEngine, $evaluator);

$shadowExecution = $shadowService->run(
    'student-shadow-001',
    $input,
    $context,
    $fallbackEngine->generate($input, $context),
);

test_assert($shadowExecution['visible_result']->engineType() === 'rule', 'visible result remains rule engine');
test_assert($shadowExecution['shadow_result']->engineType() === 'model', 'shadow result executes model engine');
test_assert($shadowExecution['evaluation']['valid'] === true, 'shadow evaluation passes schema and evidence coverage');

echo "Section 10 PASS\n\n";

// =========================================================================
// SECTION 11: P2 Regression - Shadow Service Respects Shadow Flag
// =========================================================================
echo "--- Section 11: Shadow Service Respects Shadow Flag ---\n";

// Use LearnerApiContext with mock PDO to test shadowRunService flag behavior
$mockPdo = new class extends PDO {
    public function __construct()
    {
    }
};

$session = new TalentHub\Auth\Session\SessionManager(['name' => 'SHADOW_TEST', 'lifetime' => 3600, 'secure' => false, 'sameSite' => 'Lax']);
$permissions = new TalentHub\Rbac\Service\PermissionService($mockPdo);
$learnerApiContext = new LearnerApiContext($mockPdo, $session, $permissions, 'req-shadow-test-01');

// Case 1: TALENTHUB_AI_SHADOW = false -> must return null and never create provider
$GLOBALS['__TALENTHUB_TEST_ENV__'] = array_merge($baseConfigEnv, [
    'TALENTHUB_AI_SHADOW' => 'false',
]);
$shadowWhenDisabled = $learnerApiContext->shadowRunService();
test_assert($shadowWhenDisabled === null, 'shadowRunService returns null when TALENTHUB_AI_SHADOW is false');

// Case 2: TALENTHUB_AI_ENABLED = false -> must return null
$GLOBALS['__TALENTHUB_TEST_ENV__'] = array_merge($baseConfigEnv, [
    'TALENTHUB_AI_ENABLED' => 'false',
    'TALENTHUB_AI_SHADOW' => 'true',
]);
$shadowWhenAiDisabled = $learnerApiContext->shadowRunService();
test_assert($shadowWhenAiDisabled === null, 'shadowRunService returns null when TALENTHUB_AI_ENABLED is false');

// Case 3: TALENTHUB_AI_ENABLED = true AND TALENTHUB_AI_SHADOW = true -> returns ShadowRunService instance
$GLOBALS['__TALENTHUB_TEST_ENV__'] = $baseConfigEnv;
$shadowWhenEnabled = $learnerApiContext->shadowRunService();
test_assert($shadowWhenEnabled instanceof ShadowRunService, 'shadowRunService returns instance when AI and shadow are enabled');

// Clean up test global env
unset($GLOBALS['__TALENTHUB_TEST_ENV__']);

echo "Section 11 PASS\n\n";

echo "=== ALL CHECKS PASSED: 9ROUTER SHADOW AI PROVIDER INTEGRATION TEST COMPLETED SUCCESSFULLY ===\n";
echo "learner_ai_9router_shadow_integration_test: OK\n";
