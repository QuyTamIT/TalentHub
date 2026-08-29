<?php

declare(strict_types=1);

/**
 * Task 1 contract: enforce strict model execution.
 *
 * Strict mode is enforced per environment:
 *  - staging / production always default to true and cannot be turned off
 *    with a normal env override. The dedicated TALENTHUB_AI_STRICT_MODE_OVERRIDE
 *    key, when set in staging/production, is ignored and recorded in the
 *    diagnostics so operators can spot the misconfiguration.
 *  - local / test default to true as well, but may opt-out by setting
 *    TALENTHUB_AI_STRICT_MODE_OVERRIDE=false explicitly for test or mock runs.
 *
 * Under strict mode:
 *  - ModelRecommendationEngine must never call its private rule fallback
 *    and never emit a RecommendationResult(engineType='rule', fallbackReason=...)
 *    under the AI label. Provider failure, missing consent, missing
 *    migrations, and an empty snapshot must raise StrictAiUnavailable with
 *    a machine-readable reason.
 *  - ModelRoadmapEngine must surface provider failure, missing consent,
 *    missing migrations, and snapshot emptiness through StrictAiUnavailable
 *    (the existing RoadmapModelUnavailable reasons are still allowed and
 *    bubble up unchanged).
 */

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;
use TalentHub\Learner\Ai\Domain\AiExecutionState;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Model\ModelRecommendationEngine;
use TalentHub\Learner\Ai\Model\ModelRoadmapEngine;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderResponse;
use TalentHub\Learner\Ai\Provider\RoadmapProviderResponse;
use TalentHub\Learner\Ai\Provider\StrictAiUnavailable;
use TalentHub\Learner\Ai\Quality\StrictAiReadinessGate;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Rules\RuleRoadmapEngine;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function strict_mode_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

/** @return array<string,string> */
function strict_mode_baseEnvironment(string $env = 'test'): array
{
    return [
        'APP_ENV' => $env,
        'TALENTHUB_AI_ENABLED' => 'true',
        'TALENTHUB_AI_PROVIDER' => 'gemini',
        'TALENTHUB_AI_MODEL' => 'gemini-3.7-flash',
        'TALENTHUB_AI_API_URL' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent',
        'TALENTHUB_AI_API_KEY' => 'test-key-never-log',
        'TALENTHUB_AI_ALLOWED_HOSTS' => 'generativelanguage.googleapis.com',
        'TALENTHUB_AI_VISIBLE_PERCENT' => '100',
        'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
        'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'team-develop-demo-2026-08-27',
        'TALENTHUB_AI_PILOT_PAUSED' => 'false',
    ];
}

function strict_mode_input(): RecommendationInput
{
    $evidence = [[
        'source_type' => 'skill',
        'source_id' => 'skill-1',
        'observed_at' => '2026-08-20T00:00:00.000000+00:00',
        'safe_value' => ['code' => 'communication'],
    ]];
    return new RecommendationInput(
        [
            'profile' => ['study_status' => 'active'],
            'skills' => [['code' => 'communication']],
            'assessments' => [],
            'activities' => [],
            'evaluations' => [],
            'opportunities' => [],
        ],
        ['skill' => '2026-08-20T00:00:00+00:00'],
        ['allowed_scopes' => ['assessment', 'skills', 'activity', 'evaluation']],
        $evidence,
    );
}

function strict_mode_emptyInput(): RecommendationInput
{
    return new RecommendationInput(
        ['profile' => [], 'skills' => [], 'assessments' => [], 'activities' => [], 'evaluations' => [], 'opportunities' => []],
        [],
        ['allowed_scopes' => ['assessment', 'skills', 'activity', 'evaluation']],
        [],
    );
}

function strict_mode_context(string $studentId = 'student-strict'): RecommendationContext
{
    return new RecommendationContext(
        ['assessment', 'skills', 'activity', 'evaluation'],
        'request-strict',
        'idempotency-strict',
        $studentId,
    );
}

final class StrictModeFailingProvider implements RecommendationProvider
{
    public int $calls = 0;
    public function __construct(private readonly ProviderResponse $response) {}
    public function generate(ProviderRequest $request, \TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer $authorizer): ProviderResponse
    {
        $this->calls++;
        $authorizer->beforeAttempt(1);
        return $this->response;
    }
}

final class StrictModeRoadmapFailingProvider implements RoadmapProvider
{
    public int $calls = 0;
    public function __construct(private readonly RoadmapProviderResponse $response) {}
    public function generate($request, $authorizer): RoadmapProviderResponse
    {
        $this->calls++;
        $authorizer->beforeAttempt(1);
        return $this->response;
    }
}

final class StrictModeFailingRecommendationEngine implements RecommendationEngine
{
    public function __construct(public int $calls = 0) {}
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult
    {
        $this->calls++;
        throw new \RuntimeException('rule fallback should not be invoked under strict mode');
    }
}

final class StrictModeFailingRoadmapEngine implements \TalentHub\Learner\Ai\Contracts\RoadmapEngine
{
    public int $calls = 0;
    public function generate(RecommendationInput $input, RecommendationContext $context): \TalentHub\Learner\Ai\Domain\RoadmapAnalysis
    {
        $this->calls++;
        throw new \RuntimeException('rule fallback should not be invoked under strict mode');
    }
}

function strict_mode_recommendationEngine(
    RecommendationProvider $provider,
    ?RecommendationEngine $fallback = null,
    ?RecommendationConfig $config = null,
): ModelRecommendationEngine {
    $source = new class implements \TalentHub\Learner\Ai\Sources\ConsentSource {
        public function forStudent(string $studentId): array
        {
            return [
                ['scope' => 'assessment', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-assessment'],
                ['scope' => 'skills', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-skills'],
                ['scope' => 'activity', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-activity'],
                ['scope' => 'evaluation', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-evaluation'],
            ];
        }
    };
    $gate = new ProviderConsentGate(
        new \TalentHub\Learner\Ai\Consent\ConsentPolicy(
            $source,
            static fn (): string => '2026-08-20T00:01:00+00:00',
        ),
        ['assessment', 'skills', 'activity', 'evaluation'],
    );
    return new ModelRecommendationEngine(
        $provider,
        $fallback ?? new RuleRecommendationEngine(),
        new \TalentHub\Learner\Ai\Model\PromptRegistry(),
        new RecommendationRateLimiter(10, 100, 60, static fn (): int => 1000),
        $config ?? RecommendationConfig::fromEnvironment(strict_mode_baseEnvironment('test')),
        new RecommendationResultValidator(),
        $gate,
    );
}

function strict_mode_roadmapEngine(
    RoadmapProvider $provider,
    ?\TalentHub\Learner\Ai\Contracts\RoadmapEngine $fallback = null,
    ?RecommendationConfig $config = null,
): ModelRoadmapEngine {
    $source = new class implements \TalentHub\Learner\Ai\Sources\ConsentSource {
        public function forStudent(string $studentId): array
        {
            return [
                ['scope' => 'assessment', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-assessment'],
                ['scope' => 'skills', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-skills'],
                ['scope' => 'activity', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-activity'],
                ['scope' => 'evaluation', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-evaluation'],
            ];
        }
    };
    $gate = new ProviderConsentGate(
        new \TalentHub\Learner\Ai\Consent\ConsentPolicy(
            $source,
            static fn (): string => '2026-08-20T00:01:00+00:00',
        ),
        ['assessment', 'skills', 'activity', 'evaluation'],
    );
    return new ModelRoadmapEngine(
        $provider,
        $fallback ?? new RuleRoadmapEngine(),
        new RoadmapPromptRegistry(),
        new RecommendationRateLimiter(10, 100, 60, static fn (): int => 1000),
        $config ?? RecommendationConfig::fromEnvironment(strict_mode_baseEnvironment('test')),
        $gate,
    );
}

// ---------------------------------------------------------------------
// 1. AiExecutionState contract
// ---------------------------------------------------------------------
strict_mode_assert(class_exists(AiExecutionState::class), 'AiExecutionState enum must exist for shared state contract');
$expectedStates = ['pending', 'ready_model', 'provider_unavailable', 'data_insufficient', 'consent_required', 'stale_model'];
$values = AiExecutionState::values();
sort($values, SORT_STRING);
$sortedExpected = $expectedStates;
sort($sortedExpected, SORT_STRING);
strict_mode_assert($values === $sortedExpected, 'AiExecutionState must expose the six canonical states');
foreach ($expectedStates as $state) {
    strict_mode_assert(AiExecutionState::isValid($state), "AiExecutionState::isValid accepts {$state}");
}
strict_mode_assert(!AiExecutionState::isValid('ready_rule'), 'ready_rule is a non-AI fallback state and must not be an AiExecutionState value');
strict_mode_assert(!AiExecutionState::isValid('ai_unavailable'), 'ai_unavailable is not a strict AI state; provider_unavailable is the canonical state');

// ---------------------------------------------------------------------
// 2. StrictAiUnavailable contract
// ---------------------------------------------------------------------
strict_mode_assert(class_exists(StrictAiUnavailable::class), 'StrictAiUnavailable exception must exist for strict-mode failures');
$expectedReasons = [
    'provider_unavailable',
    'consent_missing',
    'consent_required',
    'consent_changed',
    'consent_revoked',
    'data_insufficient',
    'missing_migration',
    'empty_snapshot',
    'model_disabled',
    'rate_limited',
];
$reflection = new ReflectionClass(StrictAiUnavailable::class);
$reasonsConstant = $reflection->getConstant('REASONS');
strict_mode_assert(is_array($reasonsConstant) && array_diff($expectedReasons, $reasonsConstant) === [], 'StrictAiUnavailable must allow-list every reason used by the readiness gate');
foreach ($expectedReasons as $reason) {
    $exception = new StrictAiUnavailable($reason);
    strict_mode_assert($exception->reason() === $reason, "StrictAiUnavailable::reason exposes {$reason}");
}
strict_mode_assert($exception instanceof \RuntimeException, 'StrictAiUnavailable must be a RuntimeException so it propagates through the existing try/catch');
try {
    new StrictAiUnavailable('not-an-allow-listed-reason');
    strict_mode_assert(false, 'StrictAiUnavailable must reject unknown reasons');
} catch (\InvalidArgumentException) {
    strict_mode_assert(true, 'StrictAiUnavailable rejects unknown reasons');
}

// ---------------------------------------------------------------------
// 3. StrictAiReadinessGate contract
// ---------------------------------------------------------------------
strict_mode_assert(class_exists(StrictAiReadinessGate::class), 'StrictAiReadinessGate must exist for shared readiness assertions');
$gateReflection = new ReflectionClass(StrictAiReadinessGate::class);
strict_mode_assert($gateReflection->hasMethod('assertReady'), 'StrictAiReadinessGate must expose assertReady(studentId, operation)');

// ---------------------------------------------------------------------
// 4. Strict mode is enforced per environment via RecommendationConfig
// ---------------------------------------------------------------------
$prodConfig = RecommendationConfig::fromEnvironment(strict_mode_baseEnvironment('production'));
strict_mode_assert($prodConfig->strictMode() === true, 'production always runs under strict mode');

$stagingConfig = RecommendationConfig::fromEnvironment(strict_mode_baseEnvironment('staging'));
strict_mode_assert($stagingConfig->strictMode() === true, 'staging always runs under strict mode');

$localConfig = RecommendationConfig::fromEnvironment(strict_mode_baseEnvironment('local'));
strict_mode_assert($localConfig->strictMode() === true, 'local defaults to strict mode');

$testConfig = RecommendationConfig::fromEnvironment(strict_mode_baseEnvironment('test'));
strict_mode_assert($testConfig->strictMode() === true, 'test defaults to strict mode');

$localOptOutEnv = strict_mode_baseEnvironment('local');
$localOptOutEnv['TALENTHUB_AI_STRICT_MODE_OVERRIDE'] = 'false';
$localOptOut = RecommendationConfig::fromEnvironment($localOptOutEnv);
strict_mode_assert($localOptOut->strictMode() === false, 'local may opt out of strict mode via TALENTHUB_AI_STRICT_MODE_OVERRIDE=false');

$testOptOutEnv = strict_mode_baseEnvironment('test');
$testOptOutEnv['TALENTHUB_AI_STRICT_MODE_OVERRIDE'] = 'false';
$testOptOut = RecommendationConfig::fromEnvironment($testOptOutEnv);
strict_mode_assert($testOptOut->strictMode() === false, 'test may opt out of strict mode via TALENTHUB_AI_STRICT_MODE_OVERRIDE=false');

// An override attempt in staging/production must be ignored AND surfaced in diagnostics.
$prodOverrideEnv = strict_mode_baseEnvironment('production');
$prodOverrideEnv['TALENTHUB_AI_STRICT_MODE_OVERRIDE'] = 'false';
$prodOverride = RecommendationConfig::fromEnvironment($prodOverrideEnv);
strict_mode_assert($prodOverride->strictMode() === true, 'production must ignore TALENTHUB_AI_STRICT_MODE_OVERRIDE=false');
$diagnostics = $prodOverride->diagnostics();
strict_mode_assert(($diagnostics['strict_mode'] ?? null) === true, 'production diagnostics record strict_mode=true');
strict_mode_assert(($diagnostics['strict_mode_override_rejected'] ?? null) === true, 'production diagnostics record that the override attempt was rejected');

$stagingOverrideEnv = strict_mode_baseEnvironment('staging');
$stagingOverrideEnv['TALENTHUB_AI_STRICT_MODE_OVERRIDE'] = 'false';
$stagingOverride = RecommendationConfig::fromEnvironment($stagingOverrideEnv);
strict_mode_assert($stagingOverride->strictMode() === true, 'staging must ignore TALENTHUB_AI_STRICT_MODE_OVERRIDE=false');
$stagingDiagnostics = $stagingOverride->diagnostics();
strict_mode_assert(($stagingDiagnostics['strict_mode_override_rejected'] ?? null) === true, 'staging diagnostics record that the override attempt was rejected');

// ---------------------------------------------------------------------
// 5. ModelRecommendationEngine under strict mode
// ---------------------------------------------------------------------
$strictConfig = RecommendationConfig::fromEnvironment(strict_mode_baseEnvironment('test'));
$failingRuleEngine = new StrictModeFailingRecommendationEngine();
$recommendationEngine = strict_mode_recommendationEngine(
    new StrictModeFailingProvider(ProviderResponse::failure('provider_unavailable')),
    $failingRuleEngine,
    $strictConfig,
);
$metricsBeforeProviderFailure = count(AiMetricsCollector::shared()->events());
try {
    $recommendationEngine->generate(strict_mode_input(), strict_mode_context());
    strict_mode_assert(false, 'strict mode must surface provider_unavailable as an exception, never as a rule result');
} catch (StrictAiUnavailable $exception) {
    strict_mode_assert($exception->reason() === 'provider_unavailable', 'strict-mode provider failure is normalised to the provider_unavailable reason');
}
strict_mode_assert($failingRuleEngine->calls === 0, 'strict mode must never call the rule fallback when the provider fails');
$providerFailureMetrics = array_slice(AiMetricsCollector::shared()->events(), $metricsBeforeProviderFailure);
strict_mode_assert(
    array_filter($providerFailureMetrics, static fn (array $event): bool => ($event['fallback'] ?? false) === true) === [],
    'strict-mode provider failures must not be measured as a rule fallback',
);
strict_mode_assert(
    array_filter($providerFailureMetrics, static fn (array $event): bool => ($event['provider_error'] ?? null) === 'provider_unavailable') !== [],
    'strict-mode provider failures emit a bounded provider_unavailable metric',
);

// Malformed provider payload must also raise StrictAiUnavailable, not fall back to rule.
$malformedEngine = strict_mode_recommendationEngine(
    new StrictModeFailingProvider(ProviderResponse::failure('malformed_response')),
    $failingRuleEngine,
    $strictConfig,
);
try {
    $malformedEngine->generate(strict_mode_input(), strict_mode_context());
    strict_mode_assert(false, 'strict mode must surface malformed responses as StrictAiUnavailable');
} catch (StrictAiUnavailable $exception) {
    strict_mode_assert($exception->reason() === 'provider_unavailable', 'malformed provider responses are normalised to provider_unavailable under strict mode');
}
strict_mode_assert($failingRuleEngine->calls === 0, 'strict mode must never fall back to rule on malformed responses');

// Strict mode + empty snapshot + ready provider: success path stays strict.
$validPayload = [
    [
        'item_type' => 'strength',
        'title' => 'Communication',
        'summary' => 'You communicate clearly with peers.',
        'priority' => 80,
        'confidence_band' => 'high',
        'action' => ['type' => 'develop_skill', 'skill_code' => 'communication'],
        'evidence_ref_ids' => ['evidence-001'],
    ],
];
$validResponse = ProviderResponse::success($validPayload);
$provider = new class($validResponse) implements RecommendationProvider {
    public int $calls = 0;
    public function __construct(private readonly ProviderResponse $response) {}
    public function generate(ProviderRequest $request, \TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer $authorizer): ProviderResponse
    {
        $this->calls++;
        $authorizer->beforeAttempt(1);
        $prompt = $request->payload();
        $evidence = $request->evidence('evidence-001');
        if ($evidence === null) {
            return ProviderResponse::failure('malformed_response');
        }
        return $this->response;
    }
};
$source = new class implements \TalentHub\Learner\Ai\Sources\ConsentSource {
    public function forStudent(string $studentId): array
    {
        return [
            ['scope' => 'assessment', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-assessment'],
            ['scope' => 'skills', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-skills'],
            ['scope' => 'activity', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-activity'],
            ['scope' => 'evaluation', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-20T00:00:00+00:00', 'request_id' => 'r-evaluation'],
        ];
    }
};
$consentGate = new ProviderConsentGate(
    new \TalentHub\Learner\Ai\Consent\ConsentPolicy($source, static fn (): string => '2026-08-20T00:01:00+00:00'),
    ['assessment', 'skills', 'activity', 'evaluation'],
);
$successEngine = new ModelRecommendationEngine(
    $provider,
    $failingRuleEngine,
    new \TalentHub\Learner\Ai\Model\PromptRegistry(),
    new RecommendationRateLimiter(10, 100, 60, static fn (): int => 1000),
    $strictConfig,
    new RecommendationResultValidator(),
    $consentGate,
);
$success = $successEngine->generate(strict_mode_input(), strict_mode_context());
strict_mode_assert($success->engineType() === 'model', 'strict mode success path returns engineType=model');
strict_mode_assert($success->fallbackReason() === null, 'strict mode success path has no fallback reason');
strict_mode_assert($failingRuleEngine->calls === 0, 'strict mode success path does not touch the rule engine');

// ---------------------------------------------------------------------
// 6. ModelRoadmapEngine under strict mode
// ---------------------------------------------------------------------
$roadmapEngine = strict_mode_roadmapEngine(
    new StrictModeRoadmapFailingProvider(RoadmapProviderResponse::failure('provider_unavailable')),
    new StrictModeFailingRoadmapEngine(),
    $strictConfig,
);
try {
    $roadmapEngine->generate(strict_mode_input(), strict_mode_context());
    strict_mode_assert(false, 'strict-mode roadmap provider failure must surface as an exception');
} catch (\TalentHub\Learner\Ai\Model\RoadmapModelUnavailable $exception) {
    strict_mode_assert($exception->reason() === 'provider_unavailable', 'roadmap provider failure uses the canonical provider_unavailable reason under strict mode');
}

// ---------------------------------------------------------------------
// 7. StrictAiReadinessGate::assertReady with the canonical reasons
// ---------------------------------------------------------------------
$gate = StrictAiReadinessGate::create($strictConfig);
$gateReflection = new ReflectionClass($gate);
$assertReady = $gateReflection->getMethod('assertReady');

$reasonSignals = [
    'consent_missing' => ['consent_ready' => false, 'required_scopes' => ['assessment']],
    'consent_required' => ['consent_ready' => true, 'required_scopes' => ['evaluation'], 'allowed_scopes' => []],
    'consent_revoked' => ['consent_ready' => false, 'consent_state' => 'revoked', 'required_scopes' => ['skills', 'activity']],
    'data_insufficient' => ['snapshot_present' => false],
    'missing_migration' => ['migrations_ready' => false],
    'empty_snapshot' => ['snapshot_present' => true, 'snapshot_evidence_count' => 0],
    'provider_unavailable' => ['provider_ready' => false],
];

foreach ($reasonSignals as $reason => $signals) {
    try {
        $assertReady->invoke($gate, 'student-strict', 'recommendation.generate', $signals);
        strict_mode_assert(false, "StrictAiReadinessGate::assertReady must throw for reason {$reason}");
    } catch (StrictAiUnavailable $exception) {
        strict_mode_assert($exception->reason() === $reason, "StrictAiReadinessGate::assertReady surfaces reason {$reason}");
    }
}

// model_disabled is reported when AI is disabled entirely.
$disabledConfig = RecommendationConfig::fromEnvironment(array_merge(strict_mode_baseEnvironment('test'), ['TALENTHUB_AI_ENABLED' => 'false']));
$disabledGate = StrictAiReadinessGate::create($disabledConfig);
try {
    $disabledGate->assertReady('student-strict', 'recommendation.generate', []);
    strict_mode_assert(false, 'StrictAiReadinessGate must throw model_disabled when AI is disabled');
} catch (StrictAiUnavailable $exception) {
    strict_mode_assert($exception->reason() === 'model_disabled', 'StrictAiReadinessGate surfaces model_disabled for disabled AI');
}

// The strict-mode readiness gate never returns the legacy ai_unavailable or
// ready_rule strings; it uses the canonical AiExecutionState reasons.
try {
    $assertReady->invoke($gate, 'student-strict', 'recommendation.generate', []);
    strict_mode_assert(true, 'assertReady is a no-op when no readiness signal is missing');
} catch (StrictAiUnavailable $exception) {
    strict_mode_assert(false, 'default readiness signal must not throw under strict mode');
}

echo "learner_ai_strict_mode_test: OK\n";
