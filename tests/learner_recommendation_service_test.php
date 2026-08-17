<?php

declare(strict_types=1);

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

foreach ([
    '/app/learner/ai/Contracts/RecommendationEngine.php',
    '/app/learner/ai/Domain/RecommendationContext.php',
    '/app/learner/ai/Domain/RecommendationEvidence.php',
    '/app/learner/ai/Domain/RecommendationInput.php',
    '/app/learner/ai/Domain/RecommendationItem.php',
    '/app/learner/ai/Domain/RecommendationResult.php',
    '/app/learner/ai/Persistence/RecommendationRepository.php',
    '/app/learner/ai/Quality/DataQualityResult.php',
    '/app/learner/ai/Validation/RecommendationResultValidator.php',
    '/app/learner/ai/Service/RecommendationResponseMapper.php',
    '/app/learner/ai/Service/RecommendationService.php',
] as $file) {
    $path = dirname(__DIR__) . $file;
    if (is_file($path)) {
        require_once $path;
    }
}

function recommendation_service_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

final class ServiceEngine implements RecommendationEngine
{
    public int $calls = 0;

    public function __construct(private readonly RecommendationResult $result, private readonly bool $throws = false)
    {
    }

    public function generate(RecommendationInput $input, RecommendationContext $context): RecommendationResult
    {
        $this->calls++;
        if ($this->throws) {
            throw new RuntimeException('raw provider timeout must not leave the service');
        }
        return $this->result;
    }
}

final class ServiceRepository implements RecommendationRepository
{
    public int $pendingCalls = 0;
    public int $completeCalls = 0;
    public int $failCalls = 0;
    public int $latestCalls = 0;
    public bool $reused = false;
    public ?array $latest = null;

    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array
    {
        $this->pendingCalls++;
        return [
            'runId' => 'run-1',
            'snapshotId' => 'snapshot-1',
            'studentId' => $studentId,
            'idempotencyKey' => $context->idempotencyKey(),
            'status' => 'pending',
            'reused' => $this->reused,
        ];
    }

    public function completeRun(string $studentId, string $runId, RecommendationResult $result): array
    {
        $this->completeCalls++;
        return [
            'runId' => $runId,
            'snapshotId' => 'snapshot-1',
            'studentId' => $studentId,
            'status' => $result->fallbackReason() === null ? 'completed' : 'fallback',
            'engineType' => $result->engineType(),
            'ruleVersion' => $result->ruleVersion(),
            'provider' => $result->provider(),
            'modelVersion' => $result->modelVersion(),
            'promptVersion' => $result->promptVersion(),
            'fallbackReason' => $result->fallbackReason(),
            'items' => array_map(static fn (RecommendationItem $item): array => [
                'itemType' => $item->itemType(),
                'title' => $item->title(),
                'summary' => $item->summary(),
                'priority' => $item->priority(),
                'confidenceBand' => $item->confidenceBand(),
                'actionJson' => $item->actionJson(),
                'evidence' => [],
            ], $result->items()),
        ];
    }

    public function failRun(string $studentId, string $runId, string $safeErrorCode): void
    {
        $this->failCalls++;
    }

    public function latestForStudent(string $studentId): ?array
    {
        $this->latestCalls++;
        return $this->latest;
    }

    public function appendFeedback(string $studentId, string $itemId, string $verdict, string $reasonCode, ?string $safeComment): array
    {
        return [];
    }
}

function recommendation_service_input(): RecommendationInput
{
    return new RecommendationInput(
        ['evaluations' => [['overall_score' => 80]], 'skills' => [], 'assessments' => [], 'activities' => [], 'opportunities' => [], 'profile' => ['study_status' => 'active']],
        [],
        ['allowed_scopes' => ['evaluation']],
        [['source_type' => 'evaluation', 'source_id' => 'evaluation-1', 'observed_at' => '2026-08-16T00:00:00.000000+00:00', 'safe_value' => ['overall_score' => 80]]],
    );
}

function recommendation_service_result(?string $fallbackReason = null, ?array $action = null, string $summary = 'Thực hành kỹ năng dựa trên bằng chứng hiện có.'): RecommendationResult
{
    return new RecommendationResult(
        'rule',
        'learner-rules-1.0.0',
        null,
        null,
        null,
        $fallbackReason,
        [new RecommendationItem(
            'roadmap',
            'Lộ trình phát triển',
            $summary,
            50,
            'medium',
            $action ?? ['type' => 'practice_presentation', 'weeks' => 4, 'steps' => ['review_feedback', 'practice', 'reflect']],
            [new RecommendationEvidence('evaluation', 'evaluation-1', '2026-08-16T00:00:00.000000+00:00', 'published_evaluation', ['overall_score' => 80])],
        )],
    );
}

/** @param callable(string):list<string> $scopes @param callable(string,array<string>):RecommendationInput $snapshot @param callable(RecommendationInput):DataQualityResult $quality @param callable(RecommendationInput):bool $fresh */
function recommendation_service(
    ServiceRepository $repository,
    ServiceEngine $engine,
    callable $scopes,
    callable $snapshot,
    callable $quality,
    callable $fresh,
): RecommendationService {
    return new RecommendationService(
        $repository,
        $engine,
        new RecommendationResultValidator(),
        new RecommendationResponseMapper(),
        static fn (string $studentId): bool => $studentId === 'student-a',
        $scopes,
        $snapshot,
        $quality,
        $fresh,
    );
}

recommendation_service_assert(class_exists(RecommendationService::class), 'recommendation service exists');

$input = recommendation_service_input();
$readyQuality = static fn (RecommendationInput $value): DataQualityResult => new DataQualityResult('ready');
$allScopes = static fn (string $studentId): array => ['evaluation'];
$snapshot = static fn (string $studentId, array $scopes): RecommendationInput => $input;
$fresh = static fn (RecommendationInput $value): bool => true;

$consentRepository = new ServiceRepository();
$consentService = recommendation_service(
    $consentRepository,
    new ServiceEngine(recommendation_service_result()),
    static fn (string $studentId): array => [],
    static fn (string $studentId, array $scopes): RecommendationInput => new RecommendationInput([], [], ['allowed_scopes' => [], 'missing_consent_scopes' => ['evaluation']], []),
    static fn (RecommendationInput $value): DataQualityResult => new DataQualityResult('consent_required', ['evaluation']),
    $fresh,
);
$consent = $consentService->generate('student-a', 'request-1', 'idempotency-1');
recommendation_service_assert($consent['state'] === 'consent_required' && $consentRepository->pendingCalls === 0, 'consent requirement returns before persistence');

$insufficientRepository = new ServiceRepository();
$insufficient = recommendation_service(
    $insufficientRepository,
    new ServiceEngine(recommendation_service_result()),
    $allScopes,
    $snapshot,
    static fn (RecommendationInput $value): DataQualityResult => new DataQualityResult('insufficient_data', [], ['assessment']),
    $fresh,
)->generate('student-a', 'request-2', 'idempotency-2');
recommendation_service_assert($insufficient['state'] === 'insufficient_data' && $insufficientRepository->pendingCalls === 0, 'insufficient data returns completion actions before persistence');

$sourceRepository = new ServiceRepository();
$source = recommendation_service(
    $sourceRepository,
    new ServiceEngine(recommendation_service_result()),
    $allScopes,
    static function (string $studentId, array $scopes): RecommendationInput { throw new RuntimeException('database unavailable'); },
    $readyQuality,
    $fresh,
)->generate('student-a', 'request-3', 'idempotency-3');
recommendation_service_assert($source['state'] === 'source_unavailable' && $sourceRepository->pendingCalls === 0 && $sourceRepository->latestCalls === 0, 'source failure does not silently load mock or stale data');

$staleRepository = new ServiceRepository();
$stale = recommendation_service($staleRepository, new ServiceEngine(recommendation_service_result()), $allScopes, $snapshot, $readyQuality, static fn (RecommendationInput $value): bool => false)
    ->generate('student-a', 'request-4', 'idempotency-4');
recommendation_service_assert($stale['state'] === 'stale_snapshot' && $staleRepository->pendingCalls === 0, 'stale snapshot returns a safe refresh state before persistence');

$readyRepository = new ServiceRepository();
$ready = recommendation_service($readyRepository, new ServiceEngine(recommendation_service_result()), $allScopes, $snapshot, $readyQuality, $fresh)
    ->generate('student-a', 'request-5', 'idempotency-5');
recommendation_service_assert($ready['state'] === 'ready_rule' && $readyRepository->pendingCalls === 1 && $readyRepository->completeCalls === 1, 'ready rule result persists and maps safely');

$reusedRepository = new ServiceRepository();
$reusedRepository->reused = true;
$reusedEngine = new ServiceEngine(recommendation_service_result());
$reused = recommendation_service($reusedRepository, $reusedEngine, $allScopes, $snapshot, $readyQuality, $fresh)
    ->generate('student-a', 'request-6', 'idempotency-6');
recommendation_service_assert($reused['state'] === 'pending' && $reused['reused'] === true && $reusedEngine->calls === 0, 'duplicate idempotency request reuses pending work without a second engine call');

$failureRepository = new ServiceRepository();
$failure = recommendation_service($failureRepository, new ServiceEngine(recommendation_service_result(), true), $allScopes, $snapshot, $readyQuality, $fresh)
    ->generate('student-a', 'request-7', 'idempotency-7');
recommendation_service_assert($failure['state'] === 'engine_failure' && $failureRepository->failCalls === 1 && !isset($failure['error']), 'engine failure records only a safe state');

$invalidRepository = new ServiceRepository();
$invalid = recommendation_service(
    $invalidRepository,
    new ServiceEngine(recommendation_service_result(null, ['type' => 'unsupported_action'])),
    $allScopes,
    $snapshot,
    $readyQuality,
    $fresh,
)->generate('student-a', 'request-8', 'idempotency-8');
recommendation_service_assert($invalid['state'] === 'engine_failure' && $invalidRepository->failCalls === 1, 'invalid engine output is never persisted');

$latestRepository = new ServiceRepository();
$latestRepository->latest = ['runId' => 'run-latest', 'status' => 'completed', 'engineType' => 'rule', 'ruleVersion' => 'learner-rules-1.0.0', 'fallbackReason' => null, 'items' => []];
$latest = recommendation_service($latestRepository, new ServiceEngine(recommendation_service_result()), $allScopes, $snapshot, $readyQuality, $fresh)->latest('student-a');
recommendation_service_assert($latest !== null && $latest['state'] === 'ready_rule' && $latestRepository->latestCalls === 1, 'latest maps a persisted learner-owned run');

echo "learner_recommendation_service_test: OK\n";
