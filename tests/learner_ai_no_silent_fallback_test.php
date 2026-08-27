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
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array { return ['runId' => 'run-1', 'snapshotId' => 'snapshot-1', 'status' => 'pending', 'reused' => false]; }
    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array
    {
        $this->completeCalls++;
        return ['runId' => $runId, 'snapshotId' => 'snapshot-1', 'status' => 'completed', 'engineType' => $result->engineType(), 'ruleVersion' => $result->ruleVersion(), 'provider' => $result->provider(), 'modelVersion' => $result->modelVersion(), 'promptVersion' => $result->promptVersion(), 'fallbackReason' => $result->fallbackReason(), 'completedAt' => '2026-08-27T00:00:00+00:00', 'items' => []];
    }
    public function failRun(string $studentId, string $runId, string $safeErrorCode): void {}
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
$service = new RecommendationService(
    new NoSilentFallbackRepository(), new NoSilentFallbackEngine(), new RecommendationResultValidator(), new RecommendationResponseMapper(),
    static fn (string $studentId): bool => true,
    static fn (string $studentId): array => ['assessment', 'skills', 'activity', 'evaluation'],
    static fn (string $studentId, array $scopes): RecommendationInput => $input,
    static fn (RecommendationInput $value): DataQualityResult => new DataQualityResult('ready'),
    static fn (RecommendationInput $value): bool => true,
    new NoSilentFallbackEngine('provider_unavailable'), $config, new RecommendationRolloutSelector(new AiAvailabilityPolicy(), $rolloutEvidence), new AiAvailabilityPolicy(),
);
$response = $service->generate('student-a', 'request-a', 'idempotency-a');
no_silent_fallback_assert(($response['state'] ?? null) === 'ready_rule', 'rule fallback remains an explicit ready_rule state');
no_silent_fallback_assert(($response['analysis_origin'] ?? null) === 'rule', 'rule fallback is labeled with rule origin');
no_silent_fallback_assert(($response['fallback_reason'] ?? null) === 'provider_unavailable', 'model failure reason is exposed safely instead of silently falling back');

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
