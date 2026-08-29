<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Availability\AiAvailabilityPolicy;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderConsentDenied;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;
use TalentHub\Learner\Ai\Quality\DataQualityResult;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
use TalentHub\Learner\Ai\Service\RecommendationService;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function no_silent_fallback_assert(bool $condition, string $message): void
{
    if (!$condition) { fwrite(STDERR, "Assertion failed: {$message}\n"); exit(1); }
}

final class NoSilentFallbackEngine implements RecommendationEngine
{
    public function __construct(private readonly ?string $fallbackReason = null) {}
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult
    {
        return new RecommendationResult('rule', 'learner-rules-1.0.0', null, null, null, $this->fallbackReason, [
            new RecommendationItem('development', 'Practice', 'Evidence-backed practice.', 10, 'medium', ['type' => 'develop_skill', 'skill_code' => 'communication'], [
                new RecommendationEvidence('skill', 'skill-1', '2026-08-27T00:00:00.000000+00:00', 'skill_evidence', ['code' => 'communication']),
            ]),
        ]);
    }
}

final class NoSilentFallbackRepository implements RecommendationRepository
{
    public ?array $latest = null;
    public int $completeCalls = 0;
    /** @var list<string> */
    public array $failedCodes = [];
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array { return ['runId' => 'run-1', 'snapshotId' => 'snapshot-1', 'status' => 'pending', 'reused' => false]; }
    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array
    {
        $this->completeCalls++;
        return ['runId' => $runId, 'snapshotId' => 'snapshot-1', 'status' => 'completed', 'engineType' => $result->engineType(), 'ruleVersion' => $result->ruleVersion(), 'provider' => $result->provider(), 'modelVersion' => $result->modelVersion(), 'promptVersion' => $result->promptVersion(), 'fallbackReason' => $result->fallbackReason(), 'completedAt' => '2026-08-27T00:00:00+00:00', 'items' => []];
    }
    public function failRun(string $studentId, string $runId, string $safeErrorCode): void { $this->failedCodes[] = $safeErrorCode; }
    public function latestForStudent(string $studentId): ?array { return $this->latest; }
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array { return []; }
}

final class RevokedDuringModelAttemptEngine implements RecommendationEngine
{
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult
    {
        throw new ProviderConsentDenied('consent_revoked');
    }
}

$input = new RecommendationInput(
    ['profile' => [], 'skills' => [['code' => 'communication']], 'assessments' => [], 'activities' => [], 'evaluations' => [], 'opportunities' => []],
    [],
    ['allowed_scopes' => ['assessment', 'skills', 'activity', 'evaluation']],
    [['source_type' => 'skill', 'source_id' => 'skill-1', 'observed_at' => '2026-08-27T00:00:00.000000+00:00', 'safe_value' => ['code' => 'communication']]],
);
$config = RecommendationConfig::fromEnvironment([
    'APP_ENV' => 'test', 'TALENTHUB_AI_ENABLED' => 'true', 'TALENTHUB_AI_PROVIDER' => 'gemini',
    'TALENTHUB_AI_MODEL' => 'test-model', 'TALENTHUB_AI_API_URL' => 'http://127.0.0.1:20128/generate',
    'TALENTHUB_AI_API_KEY' => 'test-only-not-a-real-key', 'TALENTHUB_AI_ALLOWED_HOSTS' => '127.0.0.1',
    'TALENTHUB_AI_SHADOW' => 'false', 'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '100', 'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'test-approval',
    'TALENTHUB_AI_PILOT_PAUSED' => 'false',
]);
$rolloutEvidence = ['stage'=>'50','error_budget'=>true,'freshness_sla'=>true,'validator_pass_rate'=>true,'privacy_review'=>true,'rollback_drill'=>true,'approval_reference'=>'test-approval','enabled'=>true,'shadow_gate_approved'=>true,'pilot_paused'=>false,'completed_stages'=>['pilot','10','25','50'],'visible_percent'=>100,'unified_policy_verified'=>true,'last_known_good_verified'=>true,'queue_monitoring_verified'=>true];
$strictRepository = new NoSilentFallbackRepository();
$strictRefreshes = [];
$service = new RecommendationService(
    $strictRepository, new NoSilentFallbackEngine(), new RecommendationResultValidator(), new RecommendationResponseMapper(),
    static fn (string $studentId): bool => true,
    static fn (string $studentId): array => ['assessment', 'skills', 'activity', 'evaluation'],
    static fn (string $studentId, array $scopes): RecommendationInput => $input,
    static fn (RecommendationInput $value): DataQualityResult => new DataQualityResult('ready'),
    static fn (RecommendationInput $value): bool => true,
    new NoSilentFallbackEngine('provider_unavailable'), $config, new RecommendationRolloutSelector(new AiAvailabilityPolicy(), $rolloutEvidence), new AiAvailabilityPolicy(),
    static function (string $studentId, RecommendationInput $snapshot) use (&$strictRefreshes): void { $strictRefreshes[] = [$studentId, $snapshot->contentHash()]; },
);
$response = $service->generate('student-a', 'request-a', 'idempotency-a');
no_silent_fallback_assert(($response['state'] ?? null) === 'provider_unavailable', 'strict mode exposes provider_unavailable instead of a rule fallback');
no_silent_fallback_assert(($response['analysis_origin'] ?? null) === null, 'strict model failure has no rule or model origin');
no_silent_fallback_assert(($response['items'] ?? null) === [], 'strict model failure returns no rule items');
no_silent_fallback_assert($strictRepository->completeCalls === 0, 'strict model failure never persists a rule result');
no_silent_fallback_assert($strictRepository->failedCodes === ['provider_unavailable'], 'strict model failure is persisted with the canonical safe error code');
no_silent_fallback_assert($strictRefreshes === [['student-a', $input->contentHash()]], 'strict provider failure enqueues a refresh using the exact snapshot hash');
$strictRepository->latest = [
    'runId' => 'run-1',
    'snapshotId' => 'snapshot-1',
    'status' => 'failed',
    'engineType' => 'rule',
    'safeErrorCode' => 'provider_unavailable',
    'items' => [],
];
$strictLatest = $service->latest('student-a');
no_silent_fallback_assert(($strictLatest['state'] ?? null) === 'provider_unavailable', 'strict failed history remains provider_unavailable on a later GET');
no_silent_fallback_assert(($strictLatest['analysis_origin'] ?? null) === null && ($strictLatest['items'] ?? null) === [], 'strict failed history never revives a rule origin or rule items');

$strictRepository->latest = [
    'runId' => 'legacy-rule-run',
    'snapshotId' => 'legacy-rule-snapshot',
    'status' => 'completed',
    'engineType' => 'rule',
    'ruleVersion' => 'legacy-rule-1',
    'completedAt' => '2026-08-26T00:00:00+00:00',
    'items' => [[
        'id' => 'legacy-rule-item',
        'itemType' => 'development',
        'title' => 'Legacy rule recommendation',
        'rationale' => 'Must never be revealed while strict mode is required.',
    ]],
];
$strictLegacyLatest = $service->latest('student-a');
no_silent_fallback_assert(($strictLegacyLatest['state'] ?? null) === 'provider_unavailable', 'strict GET never exposes a completed historical rule run');
no_silent_fallback_assert(($strictLegacyLatest['analysis_origin'] ?? null) === null && ($strictLegacyLatest['items'] ?? null) === [], 'strict GET never revives legacy rule items');

$revokedRepository = new NoSilentFallbackRepository();
$revokedRepository->latest = ['runId' => 'old-run', 'status' => 'completed', 'engineType' => 'model', 'provider' => 'gemini', 'modelVersion' => 'old-model', 'promptVersion' => 'old-prompt', 'completedAt' => '2026-08-26T00:00:00+00:00', 'items' => []];
$revokedService = new RecommendationService(
    $revokedRepository, new NoSilentFallbackEngine(), new RecommendationResultValidator(), new RecommendationResponseMapper(),
    static fn (string $studentId): bool => true,
    static fn (string $studentId): ConsentDecision => new ConsentDecision([
        'activity' => ['action' => 'revoked', 'policy_version' => 'v1', 'occurred_at' => '2026-08-27T00:00:00+00:00', 'request_id' => 'revoke-activity'],
        'assessment' => ['action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-26T00:00:00+00:00', 'request_id' => 'grant-assessment'],
        'evaluation' => ['action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-26T00:00:00+00:00', 'request_id' => 'grant-evaluation'],
        'skills' => ['action' => 'granted', 'policy_version' => 'v1', 'occurred_at' => '2026-08-26T00:00:00+00:00', 'request_id' => 'grant-skills'],
    ], '2026-08-27T00:00:01+00:00'),
    static fn (string $studentId, array $scopes): RecommendationInput => $input,
    static fn (RecommendationInput $value): DataQualityResult => new DataQualityResult('ready'),
    static fn (RecommendationInput $value): bool => true,
);
$revoked = $revokedService->latest('student-a');
no_silent_fallback_assert(($revoked['state'] ?? null) === 'ai_unavailable' && in_array('activity', $revoked['missing_consent_scopes'] ?? [], true), 'GET hides cached recommendation immediately after consent revoke');

$raceRepository = new NoSilentFallbackRepository();
$raceService = new RecommendationService(
    $raceRepository, new NoSilentFallbackEngine(), new RecommendationResultValidator(), new RecommendationResponseMapper(),
    static fn (string $studentId): bool => true,
    static fn (string $studentId): array => ['assessment', 'skills', 'activity', 'evaluation'],
    static fn (string $studentId, array $scopes): RecommendationInput => $input,
    static fn (RecommendationInput $value): DataQualityResult => new DataQualityResult('ready'),
    static fn (RecommendationInput $value): bool => true,
    new RevokedDuringModelAttemptEngine(), $config, new RecommendationRolloutSelector(new AiAvailabilityPolicy(), $rolloutEvidence), new AiAvailabilityPolicy(),
);
$race = $raceService->generate('student-a', 'request-race', 'idempotency-race');
no_silent_fallback_assert(($race['state'] ?? null) === 'ai_unavailable' && $raceRepository->completeCalls === 0, 'consent revoke during provider attempt cannot return or persist a rule/model result from the old snapshot');

echo "learner_ai_no_silent_fallback_test: OK\n";
