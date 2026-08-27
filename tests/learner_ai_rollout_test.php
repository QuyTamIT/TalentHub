<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Persistence\RecommendationRepository;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
use TalentHub\Learner\Ai\Service\RecommendationService;
use TalentHub\Learner\Ai\Quality\DataQualityResult;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function rollout_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

rollout_assert(class_exists(RecommendationRolloutSelector::class), 'rollout selector exists');

$default = RecommendationConfig::fromEnvironment([]);
rollout_assert($default->visiblePercent() === 0 && $default->shadowEnabled() === false, 'model rollout defaults to hidden and shadow disabled');

$config = RecommendationConfig::fromEnvironment([
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'fake',
    'TALENTHUB_AI_MODEL' => 'learner-test-1',
    'TALENTHUB_AI_API_URL' => 'https://ai.example.test/v1/recommendations',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'ai.example.test',
    'TALENTHUB_AI_API_KEY' => 'test-key',
    'TALENTHUB_AI_TIMEOUT_SECONDS' => '2',
    'TALENTHUB_AI_MAX_ATTEMPTS' => '1',
    'TALENTHUB_AI_SHADOW' => 'true',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '37',
]);
$selector = new RecommendationRolloutSelector();
$studentId = '00000000-0000-4000-8000-000000000017';
rollout_assert($selector->isAssigned($studentId, $config) === $selector->isAssigned($studentId, $config), 'pilot assignment is stable');
rollout_assert(
    $selector->canShowModel($studentId, $config, ['assessment', 'skills', 'activity', 'evaluation'], true) === $selector->isAssigned($studentId, $config),
    'only deterministically assigned learners can see a model after all gates pass',
);
rollout_assert(!$selector->canShowModel($studentId, $config, ['assessment', 'skills'], true), 'revoked or missing consent disables model visibility');
rollout_assert(!$selector->canShowModel($studentId, $default, ['assessment', 'skills', 'activity', 'evaluation'], true), 'disabled default configuration never shows a model');

$zeroVisible = RecommendationConfig::fromEnvironment([
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'fake',
    'TALENTHUB_AI_MODEL' => 'learner-test-1',
    'TALENTHUB_AI_API_URL' => 'https://ai.example.test/v1/recommendations',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'ai.example.test',
    'TALENTHUB_AI_API_KEY' => 'test-key',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '0',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
]);
rollout_assert(!$selector->canShowModel($studentId, $zeroVisible, ['assessment', 'skills', 'activity', 'evaluation'], true), 'visible percent zero always keeps rules visible');

$fullVisible = RecommendationConfig::fromEnvironment([
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'fake',
    'TALENTHUB_AI_MODEL' => 'learner-test-1',
    'TALENTHUB_AI_API_URL' => 'https://ai.example.test/v1/recommendations',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'ai.example.test',
    'TALENTHUB_AI_API_KEY' => 'test-key',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '100',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
    'TALENTHUB_AI_PILOT_APPROVAL_REFERENCE' => 'test-approved-pilot',
    'TALENTHUB_AI_PILOT_PAUSED' => 'false',
]);
$selector = new RecommendationRolloutSelector(null, [
    'stage' => '50', 'error_budget' => true, 'freshness_sla' => true, 'validator_pass_rate' => true,
    'privacy_review' => true, 'rollback_drill' => true, 'approval_reference' => 'test-approved-pilot',
    'enabled' => true, 'shadow_gate_approved' => true, 'pilot_paused' => false,
    'completed_stages' => ['pilot', '10', '25', '50'], 'visible_percent' => 100,
    'unified_policy_verified' => true, 'last_known_good_verified' => true, 'queue_monitoring_verified' => true,
]);

$input = new RecommendationInput(
    ['skills' => [['code' => 'iot']]], [], ['allowed_scopes' => ['assessment', 'skills', 'activity', 'evaluation']],
    [['source_type' => 'skill', 'source_id' => 'skill-1', 'observed_at' => null, 'safe_value' => ['code' => 'iot']]],
);
$itemFactory = static fn (string $title): RecommendationItem => new RecommendationItem(
    'strength', $title, 'Dựa trên kỹ năng đã xác minh.', 20, 'medium', ['type' => 'develop_skill', 'skill_code' => 'iot'],
    [new RecommendationEvidence('skill', 'skill-1', null, 'source', ['code' => 'iot'])],
);
$ruleEngine = new class($itemFactory('Gợi ý quy tắc')) implements RecommendationEngine {
    public function __construct(private readonly RecommendationItem $item) {}
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult { return new RecommendationResult('rule', 'rules-1', null, null, null, null, [$this->item]); }
};
$modelEngine = new class($itemFactory('Gợi ý mô hình')) implements RecommendationEngine {
    public function __construct(private readonly RecommendationItem $item) {}
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult { return new RecommendationResult('model', null, 'fake', 'model-1', 'prompt-1', null, [$this->item]); }
};
$repository = new class implements RecommendationRepository {
    public array $completed = [];
    private int $sequence = 0;
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array { $this->sequence += 1; return ['runId' => 'run-' . $this->sequence, 'snapshotId' => 'snapshot-1', 'reused' => false]; }
    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array { $this->completed[] = $result; return ['runId' => $runId, 'snapshotId' => 'snapshot-1', 'status' => 'completed', 'engineType' => $result->engineType(), 'ruleVersion' => $result->ruleVersion(), 'provider' => $result->provider(), 'modelVersion' => $result->modelVersion(), 'promptVersion' => $result->promptVersion(), 'fallbackReason' => $result->fallbackReason(), 'items' => []]; }
    public function failRun(string $studentId, string $runId, string $safeErrorCode): void {}
    public function latestForStudent(string $studentId): ?array { return null; }
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array { return []; }
};
$service = new RecommendationService(
    $repository, $ruleEngine, new RecommendationResultValidator(), new RecommendationResponseMapper(),
    static fn (): bool => true,
    static fn (): array => ['assessment', 'skills', 'activity', 'evaluation'],
    static fn (): RecommendationInput => $input,
    static fn (): DataQualityResult => new DataQualityResult('ready'),
    static fn (): bool => true,
    $modelEngine, $fullVisible, $selector,
);
$visible = $service->generate('student-visible-1', 'request-visible-1', 'idempotency-visible-1');
rollout_assert($visible['state'] === 'ready_model' && array_map(static fn (RecommendationResult $result): string => $result->engineType(), $repository->completed) === ['model'], 'model visibility persists only the validated assigned model run');

$fallbackModel = new class($itemFactory('Gợi ý dự phòng')) implements RecommendationEngine {
    public function __construct(private readonly RecommendationItem $item) {}
    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult { return new RecommendationResult('rule', 'rules-1', null, null, null, 'provider_unavailable', [$this->item]); }
};
$fallbackRepository = new class implements RecommendationRepository {
    public array $completed = [];
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array { return ['runId' => 'run-rule', 'snapshotId' => 'snapshot-1', 'reused' => false]; }
    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array { $this->completed[] = $result; return ['runId' => $runId, 'snapshotId' => 'snapshot-1', 'status' => 'completed', 'engineType' => $result->engineType(), 'ruleVersion' => $result->ruleVersion(), 'provider' => null, 'modelVersion' => null, 'promptVersion' => null, 'fallbackReason' => null, 'items' => []]; }
    public function failRun(string $studentId, string $runId, string $safeErrorCode): void {}
    public function latestForStudent(string $studentId): ?array { return null; }
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array { return []; }
};
$fallbackService = new RecommendationService(
    $fallbackRepository, $ruleEngine, new RecommendationResultValidator(), new RecommendationResponseMapper(),
    static fn (): bool => true, static fn (): array => ['assessment', 'skills', 'activity', 'evaluation'],
    static fn (): RecommendationInput => $input, static fn (): DataQualityResult => new DataQualityResult('ready'), static fn (): bool => true,
    $fallbackModel, $fullVisible, $selector,
);
$providerFailure = $fallbackService->generate('student-visible-2', 'request-visible-2', 'idempotency-visible-2');
rollout_assert($providerFailure['state'] === 'ready_rule' && count($fallbackRepository->completed) === 1, 'provider failure retains the completed visible rule run');

$activeModelRun = [
    'runId'=>'active-model-run', 'snapshotId'=>'active-model-snapshot', 'status'=>'completed', 'engineType'=>'model',
    'ruleVersion'=>null, 'provider'=>'fake', 'modelVersion'=>'model-previous', 'promptVersion'=>'prompt-previous',
    'fallbackReason'=>null, 'completedAt'=>'2026-08-25T00:00:00+00:00',
    'items'=>[['itemId'=>'active-item','itemType'=>'strength','title'=>'Active model','summary'=>'Last known good','priority'=>1,'confidenceBand'=>'medium','actionJson'=>'{}','evidence'=>[['source_type'=>'skill','source_id'=>'skill-1']]]],
];
$retainingRepository = new class($activeModelRun) implements RecommendationRepository {
    public int $completeCalls = 0;
    public function __construct(private readonly array $active) {}
    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array { return ['runId'=>'replacement-run','snapshotId'=>'replacement-snapshot','reused'=>false]; }
    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array { $this->completeCalls++; return $this->active; }
    public function failRun(string $studentId, string $runId, string $safeErrorCode): void {}
    public function latestForStudent(string $studentId): ?array { return $this->active; }
    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array { return []; }
};
$retainingService = new RecommendationService(
    $retainingRepository, $ruleEngine, new RecommendationResultValidator(), new RecommendationResponseMapper(),
    static fn (): bool => true, static fn (): array => ['assessment', 'skills', 'activity', 'evaluation'],
    static fn (): RecommendationInput => $input, static fn (): DataQualityResult => new DataQualityResult('ready'), static fn (): bool => true,
    $fallbackModel, $fullVisible, $selector,
);
$retainedModel = $retainingService->generate('student-visible-3', 'request-visible-3', 'idempotency-visible-3');
rollout_assert(($retainedModel['state'] ?? null) === 'stale_model' && ($retainedModel['last_known_good'] ?? false) === true, 'provider fallback serves the existing model as stale');
rollout_assert($retainingRepository->completeCalls === 0, 'provider fallback never persists a rule over an active model');

echo "learner_ai_rollout_test: OK\n";
