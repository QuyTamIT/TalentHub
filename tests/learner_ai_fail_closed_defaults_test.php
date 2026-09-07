<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\CareerRoleBenchmark;
use TalentHub\Learner\Ai\Matching\CareerRoleBenchmarkRepository;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\JobMatchScorer;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Persistence\JobMatchRepository;
use TalentHub\Learner\Ai\Service\JobMatchingService;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$emptyProfile = LearnerOpportunityProfile::fromInput(new RecommendationInput([], [], [], []));
$assert($emptyProfile->skills() === [], 'An empty snapshot must not receive fabricated skills.');
$assert($emptyProfile->assessmentDimensions() === [], 'An empty snapshot must not receive fabricated assessment scores.');

$missingBenchmarkContract = new PDO('sqlite::memory:');
$benchmarkResult = (new CareerRoleBenchmarkRepository($missingBenchmarkContract))->activeRoles();
$assert(($benchmarkResult['status'] ?? null) === 'insufficient_data', 'Missing benchmark tables must fail closed.');
$assert(($benchmarkResult['roles'] ?? null) === [], 'Missing benchmark tables must not create hardcoded roles.');

$consentPdo = new PDO('sqlite::memory:');
$consentPdo->exec('CREATE TABLE learner_ai_consent_events (studentId TEXT, scope TEXT, action TEXT, policyVersion TEXT, occurredAt TEXT, requestId TEXT)');
$assert((new DatabaseConsentSource($consentPdo))->forStudent('student-without-consent') === [], 'Missing consent history must remain missing.');

$unknownActions = [];
foreach (ConsentDecision::REQUIRED_SCOPES as $scope) {
    $unknownActions[$scope] = [
        'action' => 'pending',
        'policy_version' => 'policy-1',
        'occurred_at' => '2026-09-05T00:00:00.000000+00:00',
        'request_id' => 'request-' . $scope,
    ];
}
$assert(!(new ConsentDecision($unknownActions, '2026-09-05T00:01:00.000000+00:00'))->permitsAllRequiredScopes(), 'Only an explicit granted action may allow a scope.');

$repository = new class implements JobMatchRepository {
    public int $pendingCalls = 0;
    public int $completeCalls = 0;
    public ?array $latestResult = null;

    public function latestValid(string $studentId, array $activeCatalogIds): ?array
    {
        return $this->latestResult;
    }

    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array
    {
        $this->pendingCalls++;
        return ['runId' => 'unexpected-run', 'reused' => false];
    }

    public function completeRun(string $studentId, string $runId, array $records, array $runAnalysis = []): array
    {
        $this->completeCalls++;
        return ['runId' => $runId, 'state' => 'ready_model', 'items' => []];
    }

    public function failRun(string $studentId, string $runId, string $safeCode): void
    {
    }
};

$grants = [];
foreach (ConsentDecision::REQUIRED_SCOPES as $scope) {
    $grants[$scope] = [
        'action' => 'granted',
        'policy_version' => 'policy-1',
        'occurred_at' => '2026-09-05T00:00:00.000000+00:00',
        'request_id' => 'request-' . $scope,
    ];
}
$input = new RecommendationInput(['skills' => [['code' => 'php', 'score' => 85]]], [], [], []);
$candidate = [[
    'source_type' => 'opportunity',
    'source_id' => 'job-1',
    'safe_value' => [
        'catalog_id' => 'job-1',
        'item_type' => 'internship',
        'title' => 'Backend Developer',
        'provider_name' => 'TalentHub Test',
        'status' => 'active',
        'url' => '/app/learner/ecosystem.php?tab=opportunities&focus=job-1',
        'required_skills' => [['code' => 'php', 'minimum_score' => 70, 'label' => 'PHP']],
    ],
]];
$role = new CareerRoleBenchmark('backend_developer', 'Backend Developer', 'technology', [
    ['code' => 'php', 'label' => 'PHP', 'minimum_score' => 70, 'weight' => 100.0, 'required' => true],
], []);
$service = new JobMatchingService(
    $repository,
    static fn (string $studentId): ConsentDecision => new ConsentDecision($grants, '2026-09-05T00:01:00.000000+00:00'),
    static fn (string $studentId): RecommendationInput => $input,
    static fn (string $studentId): array => $candidate,
    static fn (): array => ['status' => 'ok', 'roles' => [$role]],
    null,
    static fn (string $studentId, array $weights): array => ['status' => 'ok', 'items' => []],
    new DateTimeImmutable('2026-09-05T00:00:00+00:00'),
);
$result = $service->generate('student-1', 'request-1', 'idempotency-1');
$assert(($result['state'] ?? null) === 'provider_unavailable', 'A disabled model engine must return provider_unavailable.');
$assert($repository->pendingCalls === 0, 'A disabled model engine must not create a pending model run.');
$assert($repository->completeCalls === 0, 'A disabled model engine must not persist fabricated model analysis.');

$assessmentOnlyInput = new RecommendationInput([
    'assessments' => [[
        'test_type' => 'holland',
        'dimension_scores' => ['I' => 82, 'R' => 61],
    ]],
], [], [], []);
$assessmentOnlyService = new JobMatchingService(
    $repository,
    static fn (string $studentId): ConsentDecision => new ConsentDecision($grants, '2026-09-05T00:01:00.000000+00:00'),
    static fn (string $studentId): RecommendationInput => $assessmentOnlyInput,
    static fn (string $studentId): array => $candidate,
    static fn (): array => ['status' => 'ok', 'roles' => [$role]],
    null,
    static fn (string $studentId, array $weights): array => ['status' => 'ok', 'items' => []],
    new DateTimeImmutable('2026-09-05T00:00:00+00:00'),
);
$assessmentOnlyResult = $assessmentOnlyService->generate('student-1', 'request-2', 'idempotency-2');
$assert(($assessmentOnlyResult['state'] ?? null) === 'provider_unavailable', 'Assessment-only profiles must reach the model flow without fabricated skills.');

$candidateObject = OpportunityCandidate::fromEvidence($candidate[0]);
$currentMatchScore = (new JobMatchScorer())->score(
    LearnerOpportunityProfile::fromInput($input),
    $candidateObject,
    $role,
)->score()->totalScore();
$repository->latestResult = [
    'runId' => 'old-run',
    'state' => 'ready_model',
    'inputHash' => str_repeat('0', 64),
    'items' => [[
        'catalogId' => 'job-1',
        'matchScore' => $currentMatchScore,
        'analysis' => ['analysis' => [], 'score_breakdown' => [], 'skill_gap' => []],
    ]],
];
$changedInputResult = $service->latest('student-1');
$assert(($changedInputResult['state'] ?? null) === 'stale_model', 'A completed job match for a different input fingerprint must be retained and marked stale.');
$repository->latestResult['inputHash'] = $input->contentHash();
$repository->latestResult['generationCurrent'] = false;
$changedPromptResult = $service->latest('student-1');
$assert(($changedPromptResult['state'] ?? null) === 'stale_model', 'A completed job match from an older provider, model, or prompt version must be retained and marked stale.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_ai_fail_closed_defaults_test: OK\n";
