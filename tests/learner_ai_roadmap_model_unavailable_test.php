<?php

declare(strict_types=1);

/**
 * Task 2 contract: when the model roadmap engine cannot serve a real Gemini
 * roadmap, it must throw `RoadmapModelUnavailable` so `RoadmapService` can
 * retain the last-known-good model or return `provider_unavailable` rather than
 * silently substituting a rule roadmap.
 */

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ProviderConsentGate;
use TalentHub\Learner\Ai\Contracts\RoadmapProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Model\ModelRoadmapEngine;
use TalentHub\Learner\Ai\Model\RoadmapModelUnavailable;
use TalentHub\Learner\Ai\Model\RoadmapPromptRegistry;
use TalentHub\Learner\Ai\Persistence\RoadmapRepository;
use TalentHub\Learner\Ai\Provider\RoadmapProviderResponse;
use TalentHub\Learner\Ai\Provider\ProviderRetryAfterException;
use TalentHub\Learner\Ai\Quality\RoadmapQualityGate;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rules\RuleRoadmapEngine;
use TalentHub\Learner\Ai\Service\RoadmapService;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/fixtures/learner_ai_roadmap_v1.php';

function model_unavailable_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

function model_unavailable_config(int $visiblePercent = 100): RecommendationConfig
{
    return RecommendationConfig::fromEnvironment([
        'APP_ENV' => 'test',
        'TALENTHUB_AI_ENABLED' => 'true',
        'TALENTHUB_AI_PROVIDER' => 'gemini',
        'TALENTHUB_AI_MODEL' => 'gemini-3.7-flash',
        'TALENTHUB_AI_API_URL' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.7-flash:generateContent',
        'TALENTHUB_AI_API_KEY' => 'test-key-never-log',
        'TALENTHUB_AI_ALLOWED_HOSTS' => 'generativelanguage.googleapis.com',
        'TALENTHUB_AI_VISIBLE_PERCENT' => (string) $visiblePercent,
        'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
        'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'team-develop-demo-2026-08-27',
        'TALENTHUB_AI_PILOT_PAUSED' => 'false',
    ]);
}

function model_unavailable_evidence(int $visiblePercent = 100): array
{
    $stage = match ($visiblePercent) {
        10 => 'pilot',
        25 => '10',
        50 => '25',
        default => '50',
    };
    return [
        'TALENTHUB_AI_ROLLOUT_STAGE' => $stage,
        'TALENTHUB_AI_VISIBLE_PERCENT' => (string) $visiblePercent,
        'TALENTHUB_AI_ERROR_BUDGET_VERIFIED' => 'true',
        'TALENTHUB_AI_FRESHNESS_SLA_VERIFIED' => 'true',
        'TALENTHUB_AI_VALIDATOR_PASS_RATE_VERIFIED' => 'true',
        'TALENTHUB_AI_PRIVACY_REVIEW_VERIFIED' => 'true',
        'TALENTHUB_AI_ROLLBACK_DRILL_VERIFIED' => 'true',
        'TALENTHUB_AI_UNIFIED_POLICY_VERIFIED' => 'true',
        'TALENTHUB_AI_LAST_KNOWN_GOOD_VERIFIED' => 'true',
        'TALENTHUB_AI_QUEUE_MONITORING_VERIFIED' => 'true',
        'TALENTHUB_AI_COMPLETED_STAGES' => 'pilot,10,25,50',
    ];
}

function model_unavailable_input(): RecommendationInput
{
    $records = $evidence = [];
    foreach (['holland', 'mbti', 'disc', 'multiple_intelligence'] as $index => $code) {
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

function model_unavailable_context(): RecommendationContext
{
    return new RecommendationContext(['assessment'], 'request', 'idem', 'student-roadmap', null, null);
}

function model_unavailable_provider(RoadmapProviderResponse $response): RoadmapProvider
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

function model_unavailable_engine(RoadmapProvider $provider): ModelRoadmapEngine
{
    $source = new class implements \TalentHub\Learner\Ai\Sources\ConsentSource {
        public function forStudent(string $studentId): array
        {
            return [['scope' => 'assessment', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-24T00:00:00+00:00', 'request_id' => 'consent']];
        }
    };
    $gate = new ProviderConsentGate(
        new \TalentHub\Learner\Ai\Consent\ConsentPolicy($source, static fn (): string => '2026-08-24T00:01:00+00:00'),
        ['assessment'],
    );
    return new ModelRoadmapEngine(
        $provider,
        new RuleRoadmapEngine(),
        new RoadmapPromptRegistry(),
        new RecommendationRateLimiter(10, 100, 60, static fn (): int => 1000),
        model_unavailable_config(),
        $gate,
    );
}

model_unavailable_assert(class_exists(RoadmapModelUnavailable::class), 'RoadmapModelUnavailable is the canonical failure signal');

// 1. Provider failure must throw (no silent rule_fallback).
$provider = model_unavailable_provider(RoadmapProviderResponse::failure('provider_unavailable'));
$engine = model_unavailable_engine($provider);
try {
    $engine->generate(model_unavailable_input(), model_unavailable_context());
    model_unavailable_assert(false, 'provider_unavailable must throw RoadmapModelUnavailable');
} catch (RoadmapModelUnavailable $exception) {
    model_unavailable_assert($exception->reason() === 'provider_unavailable', 'reason is the allow-listed provider code');
}

// 2. Rate-limited failures must throw with the rate_limited reason.
$rateLimited = model_unavailable_provider(RoadmapProviderResponse::failure('rate_limited', 5));
try {
    model_unavailable_engine($rateLimited)->generate(model_unavailable_input(), model_unavailable_context());
    model_unavailable_assert(false, 'rate_limited must throw');
} catch (RoadmapModelUnavailable $exception) {
    model_unavailable_assert($exception->reason() === 'rate_limited', 'rate_limited reason is allow-listed');
}

// 3. Disabled config must throw without calling the provider.
$disabledConfig = RecommendationConfig::fromEnvironment(['TALENTHUB_AI_ENABLED' => 'false']);
$unusedProvider = model_unavailable_provider(RoadmapProviderResponse::failure('should-not-run'));
$disabledEngine = new ModelRoadmapEngine(
    $unusedProvider,
    new RuleRoadmapEngine(),
    new RoadmapPromptRegistry(),
    new RecommendationRateLimiter(10, 100, 60, static fn (): int => 1000),
    $disabledConfig,
    new ProviderConsentGate(
        new \TalentHub\Learner\Ai\Consent\ConsentPolicy(new class implements \TalentHub\Learner\Ai\Sources\ConsentSource {
            public function forStudent(string $studentId): array { return []; }
        }, static fn (): string => '2026-08-24T00:01:00+00:00'),
        ['assessment'],
    ),
);
try {
    $disabledEngine->generate(model_unavailable_input(), model_unavailable_context());
    model_unavailable_assert(false, 'disabled config must throw');
} catch (RoadmapModelUnavailable $exception) {
    model_unavailable_assert($exception->reason() === 'model_disabled', 'disabled reason is allow-listed');
    model_unavailable_assert($unusedProvider->calls === 0, 'disabled config must not call the provider');
}

// 4. Malformed/invalid provider output must throw with the invalid_model_response reason.
$fixture = learner_ai_roadmap_provider_fixture();
$invalid = $fixture;
$invalid['executive_summary'] = 'Lộ trình này đảm bảo bạn đỗ đại học 100%.';
$invalidProvider = model_unavailable_provider(RoadmapProviderResponse::success($invalid, null, str_repeat('d', 64)));
try {
    model_unavailable_engine($invalidProvider)->generate(model_unavailable_input(), model_unavailable_context());
    model_unavailable_assert(false, 'unsafe output must throw');
} catch (RoadmapModelUnavailable $exception) {
    model_unavailable_assert($exception->reason() === 'invalid_model_response', 'unsafe output is normalised to the canonical reason');
}

// 5. RoadmapService must retain a prior model roadmap and mark it stale_model when the engine throws.
$retainedRepository = new class([
    'roadmap_id' => 'roadmap-prior',
    'version' => 1,
    'status' => 'active',
    'analysis_origin' => 'model',
    'executive_summary' => 'Lộ trình trước của bạn.',
    'confidence_band' => 'high',
    'engine' => ['provider' => 'gemini', 'model_version' => 'gemini-3.7-flash', 'prompt_version' => 'learner-roadmap-prompt-1.2.0'],
    'phases' => [],
    'progress' => ['completed_tasks' => 0, 'total_tasks' => 9],
    'generated_at' => '2026-08-24T00:00:00+00:00',
    'freshness_status' => 'fresh',
] + []) implements RoadmapRepository {
    public int $saveCalls = 0;
    private ?array $latestPending = null;
    public function __construct(private array $latest) {}
    public function replaceLatest(array $latest): void { $this->latest = $latest; }
    public function replacePending(?array $pending): void { $this->latestPending = $pending; }
    public function latestForStudent(string $studentId): ?array { return $this->latest; }
    public function latestPendingForStudent(string $studentId): ?array { return $this->latestPending; }
    public function historyForStudent(string $studentId): array { return []; }
    public function versionForStudent(string $studentId, int $version): ?array { return ($this->latest['version'] ?? null) === $version ? $this->latest : null; }
    public function saveCompleted(string $studentId, string $runId, RoadmapAnalysis $analysis, array $providerAudit): array { $this->saveCalls++; return $this->latest; }
    public function appendTaskEvent(string $studentId, string $taskId, string $status, string $requestId): array { return ['event_id' => 'e', 'task_id' => $taskId, 'student_id' => $studentId, 'status' => $status, 'request_id' => $requestId, 'reused' => false]; }
    public function appendRoadmapFeedback(string $studentId, string $roadmapId, string $verdict, string $reasonCode, string $requestId): array { return ['state' => 'feedback_saved']; }
    public function feedbackSignalsForStudent(string $studentId): array { return []; }
};
$source = new class implements \TalentHub\Learner\Ai\Sources\ConsentSource {
    public function forStudent(string $studentId): array
    {
        return [['scope' => 'assessment', 'action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-24T00:00:00+00:00', 'request_id' => 'consent']];
    }
};
$consent = new \TalentHub\Learner\Ai\Consent\ConsentDecision(['assessment' => ['action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-24T00:00:00+00:00', 'request_id' => 'consent']], '2026-08-24T00:01:00+00:00');
$failingEngine = model_unavailable_engine(model_unavailable_provider(RoadmapProviderResponse::failure('provider_unavailable')));
$service = new RoadmapService(
    $retainedRepository,
    $failingEngine,
    static fn (string $studentId): bool => true,
    static fn (string $studentId): \TalentHub\Learner\Ai\Consent\ConsentDecision => $consent,
    static fn (string $studentId, array $scopes): RecommendationInput => model_unavailable_input(),
    static fn (RecommendationInput $value) => (new RoadmapQualityGate(new DateTimeImmutable('2026-08-24T00:00:00+00:00')))->evaluate($value),
    static fn (string $studentId, RecommendationInput $value, RecommendationContext $context): array => ['runId' => 'run-stale', 'snapshotId' => 'snapshot-stale', 'status' => 'pending', 'reused' => false],
    static fn (string $studentId, string $runId, RoadmapAnalysis $analysis): array => ['runId' => $runId, 'status' => 'failed'],
    static function (string $studentId, string $runId, string $code): void {},
    $failingEngine,
    model_unavailable_config(),
    new \TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy(),
    \TalentHub\Learner\Ai\Rollout\RolloutEvidenceFactory::fromEnvironment(
        model_unavailable_config(),
        model_unavailable_evidence(),
    ),
);
$result = $service->generate('student-stale', 'request-stale', 'idempotency-stale', true);
model_unavailable_assert(($result['state'] ?? null) === 'stale_model', 'a prior model roadmap is served as stale_model when the engine throws');
model_unavailable_assert(($result['analysis_origin'] ?? null) === 'model', 'stale_model preserves the model origin');
model_unavailable_assert(($result['last_known_good'] ?? null) === true, 'stale_model is explicitly labelled as last-known-good');

// Consent changes at the provider-attempt boundary must never expose retained
// model content, even if a last-known-good roadmap exists.
$consentFailureEngine = new class implements \TalentHub\Learner\Ai\Contracts\RoadmapEngine {
    public function generate(RecommendationInput $input, RecommendationContext $context): RoadmapAnalysis
    {
        throw new RoadmapModelUnavailable('consent_revoked');
    }
};
$consentFailureService = new RoadmapService(
    $retainedRepository,
    $consentFailureEngine,
    static fn (string $studentId): bool => true,
    static fn (string $studentId): \TalentHub\Learner\Ai\Consent\ConsentDecision => $consent,
    static fn (string $studentId, array $scopes): RecommendationInput => model_unavailable_input(),
    static fn (RecommendationInput $value) => (new RoadmapQualityGate(new DateTimeImmutable('2026-08-24T00:00:00+00:00')))->evaluate($value),
    static fn (string $studentId, RecommendationInput $value, RecommendationContext $context): array => ['runId' => 'run-consent', 'snapshotId' => 'snapshot-consent', 'status' => 'pending', 'reused' => false],
    static fn (string $studentId, string $runId, RoadmapAnalysis $analysis): array => ['runId' => $runId, 'status' => 'failed'],
    static function (string $studentId, string $runId, string $code): void {},
    $consentFailureEngine,
    model_unavailable_config(),
    new \TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy(),
    \TalentHub\Learner\Ai\Rollout\RolloutEvidenceFactory::fromEnvironment(model_unavailable_config(), model_unavailable_evidence()),
);
$consentFailure = $consentFailureService->generate('student-stale', 'request-consent', 'idempotency-consent', true);
model_unavailable_assert(($consentFailure['state'] ?? null) === 'consent_required', 'provider-attempt consent revocation returns the canonical consent state');
model_unavailable_assert(($consentFailure['analysis_origin'] ?? null) === null, 'provider-attempt consent revocation suppresses last-known-good model content');

// A saved rule roadmap from the pre-100% rollout must not remain visible or
// be retained when a selected Gemini refresh fails.
$retainedRepository->replaceLatest([
    'roadmap_id' => 'roadmap-old-rule',
    'version' => 1,
    'status' => 'active',
    'analysis_origin' => 'rule',
    'executive_summary' => 'Lộ trình quy tắc cũ.',
    'confidence_band' => 'high',
    'engine' => ['rule_version' => 'learner-roadmap-rules-1', 'fallback_reason' => 'rule_only'],
    'phases' => [],
    'progress' => ['completed_tasks' => 0, 'total_tasks' => 9],
    'generated_at' => '2026-08-24T00:00:00+00:00',
    'freshness_status' => 'fresh',
]);
model_unavailable_assert(
    $service->latest('student-stale') === null,
    'a legacy rule roadmap is hidden when the account is eligible for the 100% Gemini rollout',
);
$retainedRepository->replacePending(['runId' => 'run-pending-model', 'snapshotId' => 'snapshot-pending-model', 'reused' => true]);
model_unavailable_assert(
    ($service->latest('student-stale')['state'] ?? null) === 'pending',
    'a pending Gemini refresh remains visible while the legacy rule roadmap is hidden',
);
$retainedRepository->replacePending(null);
$failedRuleRefresh = $service->generate('student-stale', 'request-old-rule', 'idempotency-old-rule', true);
model_unavailable_assert(
    ($failedRuleRefresh['state'] ?? null) === 'provider_unavailable'
        && ($failedRuleRefresh['analysis_origin'] ?? null) === null,
    'a failed Gemini refresh never retains a legacy rule roadmap',
);

// The same no-rule-retention rule applies to a learner assigned to Gemini in
// a staged rollout below 100%; unassigned learners may still use the rule engine.
$stagedConfig = model_unavailable_config(50);
$stagedPolicy = new \TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy();
$assignedStudentId = '';
for ($candidate = 1; $candidate <= 200; $candidate++) {
    $student = 'staged-student-' . $candidate;
    if ($stagedPolicy->isAssigned($student, $stagedConfig)) { $assignedStudentId = $student; break; }
}
model_unavailable_assert($assignedStudentId !== '', 'test fixture finds a learner assigned to the 50% model bucket');
$stagedService = new RoadmapService(
    $retainedRepository,
    $failingEngine,
    static fn (string $studentId): bool => true,
    static fn (string $studentId): \TalentHub\Learner\Ai\Consent\ConsentDecision => $consent,
    static fn (string $studentId, array $scopes): RecommendationInput => model_unavailable_input(),
    static fn (RecommendationInput $value) => (new RoadmapQualityGate(new DateTimeImmutable('2026-08-24T00:00:00+00:00')))->evaluate($value),
    static fn (string $studentId, RecommendationInput $value, RecommendationContext $context): array => ['runId' => 'run-staged', 'snapshotId' => 'snapshot-staged', 'status' => 'pending', 'reused' => false],
    static fn (string $studentId, string $runId, RoadmapAnalysis $analysis): array => ['runId' => $runId, 'status' => 'failed'],
    static function (string $studentId, string $runId, string $code): void {},
    $failingEngine,
    $stagedConfig,
    $stagedPolicy,
    \TalentHub\Learner\Ai\Rollout\RolloutEvidenceFactory::fromEnvironment($stagedConfig, model_unavailable_evidence(50)),
);
$stagedFailure = $stagedService->generate($assignedStudentId, 'request-staged', 'idempotency-staged', true);
model_unavailable_assert(($stagedFailure['state'] ?? null) === 'provider_unavailable', 'an assigned staged-rollout learner never receives a retained rule after Gemini fails');

// 6. When no prior roadmap exists, RoadmapService must return provider_unavailable (no rule substitution).
$emptyRepository = new class implements RoadmapRepository {
    public int $saveCalls = 0;
    public function latestForStudent(string $studentId): ?array { return null; }
    public function latestPendingForStudent(string $studentId): ?array { return null; }
    public function historyForStudent(string $studentId): array { return []; }
    public function versionForStudent(string $studentId, int $version): ?array { return null; }
    public function saveCompleted(string $studentId, string $runId, RoadmapAnalysis $analysis, array $providerAudit): array { $this->saveCalls++; return []; }
    public function appendTaskEvent(string $studentId, string $taskId, string $status, string $requestId): array { return ['event_id' => 'e', 'task_id' => $taskId, 'student_id' => $studentId, 'status' => $status, 'request_id' => $requestId, 'reused' => false]; }
    public function appendRoadmapFeedback(string $studentId, string $roadmapId, string $verdict, string $reasonCode, string $requestId): array { return ['state' => 'feedback_saved']; }
    public function feedbackSignalsForStudent(string $studentId): array { return []; }
};
$emptyService = new RoadmapService(
    $emptyRepository,
    $failingEngine,
    static fn (string $studentId): bool => true,
    static fn (string $studentId): \TalentHub\Learner\Ai\Consent\ConsentDecision => $consent,
    static fn (string $studentId, array $scopes): RecommendationInput => model_unavailable_input(),
    static fn (RecommendationInput $value) => (new RoadmapQualityGate(new DateTimeImmutable('2026-08-24T00:00:00+00:00')))->evaluate($value),
    static fn (string $studentId, RecommendationInput $value, RecommendationContext $context): array => ['runId' => 'run-empty', 'snapshotId' => 'snapshot-empty', 'status' => 'pending', 'reused' => false],
    static fn (string $studentId, string $runId, RoadmapAnalysis $analysis): array => ['runId' => $runId, 'status' => 'failed'],
    static function (string $studentId, string $runId, string $code): void {},
    $failingEngine,
    model_unavailable_config(),
    new \TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy(),
    \TalentHub\Learner\Ai\Rollout\RolloutEvidenceFactory::fromEnvironment(
        model_unavailable_config(),
        model_unavailable_evidence(),
    ),
);
$emptyResult = $emptyService->generate('student-empty', 'request-empty', 'idempotency-empty', true);
model_unavailable_assert(($emptyResult['state'] ?? null) === 'provider_unavailable', 'no prior roadmap + failing engine returns provider_unavailable');
model_unavailable_assert(($emptyResult['analysis_origin'] ?? null) === null, 'provider_unavailable never claims a generated origin');
model_unavailable_assert(($emptyResult['rule_version'] ?? null) === null, 'provider_unavailable never exposes a rule version');
model_unavailable_assert($emptyRepository->saveCalls === 0, 'provider_unavailable does not persist a new rule roadmap');

// Internal limiter failures in worker mode need an integer retry delay and
// must remain typed as rate_limited instead of escaping as TypeError.
$rateLimitedEngine = new class implements \TalentHub\Learner\Ai\Contracts\RoadmapEngine {
    public function generate(RecommendationInput $input, RecommendationContext $context): RoadmapAnalysis
    {
        throw new RoadmapModelUnavailable('rate_limited');
    }
};
$rateLimitedService = new RoadmapService(
    $emptyRepository,
    $rateLimitedEngine,
    static fn (string $studentId): bool => true,
    static fn (string $studentId): \TalentHub\Learner\Ai\Consent\ConsentDecision => $consent,
    static fn (string $studentId, array $scopes): RecommendationInput => model_unavailable_input(),
    static fn (RecommendationInput $value) => (new RoadmapQualityGate(new DateTimeImmutable('2026-08-24T00:00:00+00:00')))->evaluate($value),
    static fn (string $studentId, RecommendationInput $value, RecommendationContext $context): array => ['runId' => 'run-rate', 'snapshotId' => 'snapshot-rate', 'status' => 'pending', 'reused' => false],
    static fn (string $studentId, string $runId, RoadmapAnalysis $analysis): array => ['runId' => $runId, 'status' => 'failed'],
    static function (string $studentId, string $runId, string $code): void {},
    $rateLimitedEngine,
    model_unavailable_config(),
    new \TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy(),
    \TalentHub\Learner\Ai\Rollout\RolloutEvidenceFactory::fromEnvironment(
        model_unavailable_config(),
        model_unavailable_evidence(),
    ),
);
try {
    $rateLimitedService->generate('student-rate', 'request-rate', 'idempotency-rate', true, null, true);
    model_unavailable_assert(false, 'worker-mode rate limit must propagate ProviderRetryAfterException');
} catch (ProviderRetryAfterException $exception) {
    model_unavailable_assert($exception->safeCategory() === 'rate_limited', 'worker preserves the rate_limited category');
    model_unavailable_assert($exception->retryAfterSeconds() > 0, 'worker receives a positive integer retry delay');
} catch (Throwable $exception) {
    model_unavailable_assert(false, 'worker-mode rate limit escaped as ' . $exception::class);
}

echo "learner_ai_roadmap_model_unavailable_test: OK\n";
