<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\OpportunityMatch;
use TalentHub\Learner\Ai\Matching\OpportunityScore;
use TalentHub\Learner\Ai\Model\ModelOpportunityMatchEngine;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderResponse;
use TalentHub\Learner\Ai\Service\OpportunityMatchService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function service_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "service_contract_violation={$message}\n");
        exit(1);
    }
}

function service_test_pdo(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
    $recommendationStore = require dirname(__DIR__) . '/Database/migrations/learner/004_create_recommendation_store.php';
    foreach ($recommendationStore->migration->statements('sqlite') as $statement) {
        $pdo->exec($statement);
    }
    $catalogStore = require dirname(__DIR__) . '/Database/migrations/learner/011_create_ai_catalog_items.php';
    foreach ($catalogStore->migration->statements('sqlite') as $statement) {
        $pdo->exec($statement);
    }
    $opportunityExtension = require dirname(__DIR__) . '/Database/migrations/learner/015_extend_learner_opportunity_matching.php';
    foreach ($opportunityExtension->migration->statements('sqlite') as $statement) {
        $pdo->exec($statement);
    }
    $analysisExtension = require dirname(__DIR__) . '/Database/migrations/learner/016_add_learner_opportunity_analysis.php';
    foreach ($analysisExtension->migration->statements('sqlite') as $statement) {
        $pdo->exec($statement);
    }
    return $pdo;
}

function service_test_decision(bool $granted): ConsentDecision
{
    $action = $granted ? 'granted' : 'revoked';
    $events = [];
    foreach (ConsentDecision::REQUIRED_SCOPES as $scope) {
        $events[$scope] = ['action' => $action, 'policy_version' => 'learner-ai-consent-1.0', 'occurred_at' => '2026-08-01T00:00:00.000000+00:00', 'request_id' => 'req-consent'];
    }
    return new ConsentDecision($events, '2026-08-29T00:00:00.000000+00:00', $granted ? ConsentDecision::REQUIRED_SCOPES : []);
}

function service_test_input(array $overrides = []): RecommendationInput
{
    $payload = array_merge([
        'education_band' => 'high',
        'profile' => ['grade_level' => 11],
        'skills' => [
            ['code' => 'python', 'score' => 82, 'verification_status' => 'verified'],
        ],
        'assessments' => [
            ['dimension_scores' => ['logical_thinking' => 88], 'submitted_at' => '2026-08-01T00:00:00.000000+00:00'],
        ],
        'activities' => [
            ['experience_id' => 'exp-1', 'activity_category' => 'STEM', 'tags' => ['python']],
        ],
    ], $overrides);

    $evidence = [
        ['source_type' => 'skill', 'source_id' => 'python-skill-1', 'observed_at' => '2026-08-01T00:00:00.000000+00:00', 'safe_value' => ['code' => 'python', 'level_score' => 82]],
    ];
    foreach (array_merge(range(1, 12), [101]) as $index) {
        $evidence[] = ['source_type' => 'opportunity', 'source_id' => "internship-{$index}", 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => "Internship {$index}"]];
    }

    return new RecommendationInput($payload, [], ['allowed_scopes' => ConsentDecision::REQUIRED_SCOPES], $evidence);
}

function service_test_candidate_evidence(string $catalogId, array $overrides = []): array
{
    $safe = array_merge([
        'catalog_id' => $catalogId,
        'item_type' => 'internship',
        'title' => "Title for {$catalogId}",
        'provider_name' => "Provider for {$catalogId}",
        'summary' => "Summary for {$catalogId}",
        'required_skills' => [['code' => 'python', 'minimum_score' => 60]],
        'learning_outcomes' => [['code' => 'dashboard', 'label' => 'Dashboard dữ liệu']],
        'education_bands' => ['high', 'college'],
        'deadline_at' => '2026-10-01T00:00:00.000000+00:00',
        'availability' => ['remaining' => 2],
        'status' => 'active',
        'url' => '/app/learner/opportunity.php?id=' . $catalogId,
    ], $overrides);
    return ['source_type' => 'opportunity', 'source_id' => $catalogId, 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => $safe];
}

function service_test_scoring_presets(): array
{
    return [
        'internship-1' => [35, 20, 15, 10, 10],
        'internship-2' => [30, 20, 10, 10, 10],
        'internship-3' => [25, 15, 10, 10, 10],
        'internship-4' => [20, 15, 10, 5, 10],
        'internship-5' => [20, 15, 10, 5, 10],
        'internship-6' => [15, 10, 5, 0, 10],
        'internship-7' => [15, 10, 5, 0, 10],
        'internship-8' => [15, 10, 5, 0, 10],
        'internship-9' => [15, 10, 5, 0, 10],
        'internship-10' => [15, 10, 5, 0, 10],
        'internship-11' => [15, 10, 5, 0, 10],
        'internship-12' => [15, 10, 5, 0, 10],
    ];
}

function service_test_scorer(): Closure
{
    $presets = service_test_scoring_presets();
    return static function (LearnerOpportunityProfile $profile, OpportunityCandidate $candidate) use ($presets): OpportunityScore {
        $breakdown = $presets[$candidate->catalogId()] ?? [15, 10, 5, 0, 10];
        return new OpportunityScore(array_combine(
            ['skill_match', 'assessment_alignment', 'experience_relevance', 'growth_potential', 'feasibility'],
            $breakdown,
        ));
    };
}

function service_test_model_items(): array
{
    return [
        [
            'catalog_id' => 'internship-1',
            'gemini_score' => 97,
            'why_fit' => 'Du an phan tich du lieu nay dung ky nang Python da xac minh cua ban.',
            'matched_skill_codes' => ['python'],
            'missing_skill_codes' => [],
            'expected_outcome_codes' => ['dashboard'],
            'evidence_ref_ids' => ['opportunity:internship-1'],
        ],
        [
            'catalog_id' => 'internship-2',
            'gemini_score' => 93,
            'why_fit' => 'AI marketing analytics giup ban ren ky nang trinh bay voi khach hang.',
            'matched_skill_codes' => ['python'],
            'missing_skill_codes' => [],
            'expected_outcome_codes' => ['dashboard'],
            'evidence_ref_ids' => ['opportunity:internship-2'],
        ],
        [
            'catalog_id' => 'internship-3',
            'gemini_score' => 90,
            'why_fit' => 'Design sprint truong hoc phat trien tu duy sang tao va lam viec nhom.',
            'matched_skill_codes' => ['python'],
            'missing_skill_codes' => [],
            'expected_outcome_codes' => ['dashboard'],
            'evidence_ref_ids' => ['opportunity:internship-3'],
        ],
    ];
}

function service_test_authorizer(): ProviderAttemptAuthorizer
{
    return new class implements ProviderAttemptAuthorizer {
        public function beforeAttempt(int $attemptNumber): ConsentDecision
        {
            return service_test_decision(true);
        }
    };
}

final class ServiceTestSequenceProvider implements RecommendationProvider
{
    /** @var list<ProviderResponse> */
    private readonly array $responses;

    /** @var list<ProviderRequest> */
    private array $requests = [];

    /** @param list<ProviderResponse> $responses */
    public function __construct(array $responses)
    {
        $this->responses = array_values($responses);
    }

    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse
    {
        $authorizer->beforeAttempt(1);
        $this->requests[] = $request;
        $index = min(count($this->requests) - 1, count($this->responses) - 1);
        return $this->responses[$index];
    }

    /** @return list<ProviderRequest> */
    public function requests(): array
    {
        return $this->requests;
    }
}

final class ServiceEngineFixture
{
    public function __construct(
        public readonly ModelOpportunityMatchEngine $engine,
        public readonly ServiceTestSequenceProvider $provider,
    ) {
    }

    /** @return list<ProviderRequest> */
    public function requests(): array
    {
        return $this->provider->requests();
    }

    public function request(int $index): ProviderRequest
    {
        $requests = $this->provider->requests();
        if (!isset($requests[$index])) {
            throw new RuntimeException("Service engine fixture request index {$index} is out of range.");
        }
        return $requests[$index];
    }
}

function service_test_engine(ProviderResponse $response, ProviderResponse ...$more): ServiceEngineFixture
{
    $provider = new ServiceTestSequenceProvider([$response, ...$more]);
    return new ServiceEngineFixture(
        new ModelOpportunityMatchEngine($provider, service_test_authorizer()),
        $provider,
    );
}

function service_make_scenario(
    PDO $pdo,
    ?ServiceEngineFixture $engine,
    ?ConsentDecision $decision = null,
    ?RecommendationInput $input = null,
    ?array $candidateEvidence = null,
    ?DateTimeImmutable $clock = null,
    ?callable $scorer = null,
): OpportunityMatchService {
    $decision = $decision ?? service_test_decision(true);
    $input = $input ?? service_test_input();
    $candidateEvidence = $candidateEvidence ?? array_map(
        static fn (int $index): array => service_test_candidate_evidence("internship-{$index}"),
        range(1, 5),
    );

    return new OpportunityMatchService(
        new \TalentHub\Learner\Ai\Persistence\DatabaseOpportunityMatchRepository(
            $pdo,
            '9router_gemini',
            'ag/gemini-3.7-flash-high',
            \TalentHub\Learner\Ai\Model\OpportunityMatchPromptRegistry::VERSION,
            static fn (): string => '2026-08-29T00:00:00.000000+00:00',
        ),
        static fn (string $studentId): ConsentDecision => $decision,
        static fn (string $studentId): RecommendationInput => $input,
        static fn (string $studentId): array => $candidateEvidence,
        $scorer ?? service_test_scorer(),
        $engine === null ? null : $engine->engine,
        $clock ?? new DateTimeImmutable('2026-08-29T00:00:00Z', new DateTimeZone('UTC')),
    );
}

$analysisPdo = service_test_pdo();
$analysisRepository = new \TalentHub\Learner\Ai\Persistence\DatabaseOpportunityMatchRepository(
    $analysisPdo,
    '9router_gemini',
    'ag/gemini-3.7-flash-high',
    \TalentHub\Learner\Ai\Model\OpportunityMatchPromptRegistry::VERSION,
    static fn (): string => '2026-08-29T00:00:00.000000+00:00',
);
$analysisDecision = service_test_decision(true);
$analysisContext = new RecommendationContext(
    $analysisDecision->allowedScopes(),
    'request-no-fit-0001',
    'idempotency-no-fit-000001',
    'student-1',
    $analysisDecision->decisionHash(),
    $analysisDecision->policyVersion(),
);
$analysisPending = $analysisRepository->createPendingRun('student-1', service_test_input(), $analysisContext);
$analysisCompleted = $analysisRepository->completeRun(
    'student-1',
    (string) $analysisPending['runId'],
    [],
    ['headline' => 'Chưa có cơ hội đủ phù hợp', 'evidence' => ['skill:python-skill-1']],
    'no_fit_model',
);
service_assert(($analysisCompleted['status'] ?? '') === 'completed', 'zero-item explanation run completes');
service_assert(($analysisCompleted['state'] ?? '') === 'no_fit_model', 'zero-item explanation run preserves state');
service_assert(($analysisCompleted['analysis']['headline'] ?? '') === 'Chưa có cơ hội đủ phù hợp', 'run-level analysis round-trips');

function service_test_catalog_ids(array $items): array
{
    return array_map(static fn (array $item): string => (string) $item['catalog_id'], $items);
}

$clock = new DateTimeImmutable('2026-08-29T00:00:00Z', new DateTimeZone('UTC'));

$emptyPdo = service_test_pdo();
$emptyService = service_make_scenario($emptyPdo, null);
service_assert($emptyService->latest('student-1')['state'] === 'not_generated', 'latest without data returns not_generated');

$genericPdo = service_test_pdo();
$genericPdo->exec("INSERT INTO learner_recommendation_runs (id, studentId, snapshotId, idempotencyKey, engineType, status, ruleVersion, capability, startedAt, completedAt, createdAt) VALUES ('run-generic', 'student-1', 'snapshot-generic', 'generic-key-00000001', 'rule', 'completed', 'rule-1.0', 'recommendation', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
$genericPdo->exec("INSERT INTO learner_recommendation_items (id, runId, itemType, title, summary, priority, confidenceBand, actionJson) VALUES ('item-generic', 'run-generic', 'strength', 'Generic', 'Generic', 1, 'high', '{}')");
$genericService = service_make_scenario($genericPdo, null);
service_assert($genericService->latest('student-1')['state'] === 'not_generated', 'generic recommendation run never leaks into opportunity matches');

$consentPdo = service_test_pdo();
$consentProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$consentService = service_make_scenario($consentPdo, $consentProvider, service_test_decision(false));
$consentResult = $consentService->generate('student-1', 'request-1', 'idempotency-consent-0001');
service_assert($consentResult['state'] === 'consent_required', 'consent denied returns consent_required');
service_assert($consentProvider->requests() === [], 'consent denied never calls the provider');

$thinPdo = service_test_pdo();
$thinService = service_make_scenario(
    $thinPdo,
    service_test_engine(ProviderResponse::success(service_test_model_items())),
    input: service_test_input(['skills' => [], 'assessments' => []]),
);
service_assert($thinService->generate('student-1', 'request-2', 'idempotency-thin-00002')['state'] === 'insufficient_data', 'empty profile returns insufficient_data');

$twoCandidateEvidence = [
    service_test_candidate_evidence('internship-1'),
    service_test_candidate_evidence('internship-2'),
    service_test_candidate_evidence('internship-101', ['deadline_at' => '2026-08-01T00:00:00.000000+00:00']),
];
$twoPdo = service_test_pdo();
$twoProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$twoService = service_make_scenario($twoPdo, $twoProvider, candidateEvidence: $twoCandidateEvidence);
service_assert($twoService->generate('student-1', 'request-3', 'idempotency-two-000003')['state'] === 'catalog_insufficient', 'two valid candidates return catalog_insufficient');
service_assert($twoProvider->requests() === [], 'catalog_insufficient never calls the provider');

$readyPdo = service_test_pdo();
$readyProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$readyService = service_make_scenario($readyPdo, $readyProvider);
$ready = $readyService->generate('student-1', 'request-4', 'idempotency-ready-000004');
service_assert($ready['state'] === 'ready_model', 'generation returns ready_model');
service_assert(array_column($ready['items'], 'rank') === [1, 2, 3], 'ranks are 1..3');
service_assert(array_column($ready['items'], 'match_score') === [92, 84, 76], 'final scores compose 70/30 into 92/84/76');
service_assert(service_test_catalog_ids($ready['items']) === ['internship-1', 'internship-2', 'internship-3'], 'top three candidates win');
service_assert(count($readyProvider->requests()) === 1, 'successful generation calls the provider exactly once');
service_assert(count($readyProvider->requests()[0]->payload()['input']['candidate_allow_list']) === 5, 'allow-list contains every sliced candidate');

$replayed = $readyService->generate('student-1', 'request-4b', 'idempotency-ready-000004');
service_assert($replayed['state'] === 'ready_model', 'idempotent replay returns ready_model');
service_assert(service_test_catalog_ids($replayed['items']) === ['internship-1', 'internship-2', 'internship-3'], 'idempotent replay returns the persisted items');
service_assert(count($readyProvider->requests()) === 1, 'idempotent replay does not call the provider again');

$persistedItems = $readyPdo->query("SELECT items.itemType, items.catalogId, items.rankPosition, items.structuredScore, items.geminiScore, items.matchScore, items.analysisJson, items.title, items.summary, items.actionJson FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE runs.capability = 'opportunity_match' ORDER BY items.rankPosition ASC")->fetchAll(PDO::FETCH_ASSOC);
service_assert(count($persistedItems) === 3, 'three opportunity match items persisted');
service_assert(array_column($persistedItems, 'itemType') === ['activity', 'activity', 'activity'], 'items persist with itemType activity');
service_assert(array_column($persistedItems, 'catalogId') === ['internship-1', 'internship-2', 'internship-3'], 'items persist canonical catalog ids');
service_assert(array_column($persistedItems, 'rankPosition') === [1, 2, 3], 'items persist rank positions');
service_assert(array_column($persistedItems, 'structuredScore') === [90, 80, 70], 'items persist structured scores');
service_assert(array_column($persistedItems, 'geminiScore') === [97, 93, 90], 'items persist gemini scores');
service_assert(array_column($persistedItems, 'matchScore') === [92, 84, 76], 'items persist final match scores');
foreach ($persistedItems as $persisted) {
    $analysis = json_decode((string) $persisted['analysisJson'], true);
    service_assert(is_array($analysis), 'analysisJson is valid JSON');
    foreach (['why_fit', 'matched_skill_codes', 'missing_skill_codes', 'expected_outcome_codes', 'breakdown', 'evidence_ref_ids'] as $key) {
        service_assert(array_key_exists($key, $analysis), "analysisJson carries {$key}");
    }
    service_assert($analysis['breakdown']['skill_match'] === ((int) $persisted['matchScore'] === 92 ? 35 : ((int) $persisted['matchScore'] === 84 ? 30 : 25)), 'analysisJson breakdown round-trips');
    $action = json_decode((string) $persisted['actionJson'], true);
    service_assert(($action['catalog_id'] ?? '') === $persisted['catalogId'], 'action json carries canonical catalog id');
    service_assert(str_contains((string) $persisted['title'], 'Title for internship'), 'items persist canonical database title');
}
$evidenceRows = $readyPdo->query("SELECT evidence.sourceType, evidence.sourceId FROM learner_recommendation_evidence AS evidence INNER JOIN learner_recommendation_items AS items ON items.id = evidence.itemId INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE runs.capability = 'opportunity_match'")->fetchAll(PDO::FETCH_ASSOC);
service_assert(count($evidenceRows) === 3, 'one evidence row per persisted match');
service_assert(array_column($evidenceRows, 'sourceType') === ['opportunity', 'opportunity', 'opportunity'], 'evidence rows reference opportunity snapshot evidence');
$auditRows = $readyPdo->query("SELECT engineMetadataJson FROM learner_recommendation_audit_events WHERE action = 'opportunity_match_completed'")->fetchAll(PDO::FETCH_ASSOC);
service_assert(count($auditRows) === 1, 'completion writes one audit event');
$audit = json_decode((string) $auditRows[0]['engineMetadataJson'], true);
service_assert(($audit['provider'] ?? '') === '9router_gemini', 'audit carries provider version');
service_assert(($audit['model_version'] ?? '') === 'ag/gemini-3.7-flash-high', 'audit carries model version');
service_assert(($audit['prompt_version'] ?? '') === 'learner-opportunity-match-1.0.0', 'audit carries prompt version');
service_assert(strlen((string) ($audit['response_hash'] ?? '')) === 64, 'audit carries response hash');

$dumped = '';
foreach (['learner_recommendation_runs', 'learner_recommendation_items', 'learner_recommendation_evidence', 'learner_recommendation_audit_events', 'learner_recommendation_input_snapshots', 'learner_recommendation_snapshot_evidence'] as $table) {
    foreach ($readyPdo->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dumped .= json_encode($row, JSON_THROW_ON_ERROR);
    }
}
service_assert(!str_contains($dumped, 'TALENTHUB_AI_API_KEY'), 'no api key persisted');
service_assert(!str_contains($dumped, 'sk-'), 'no secret token persisted');
service_assert(!str_contains($dumped, 'Authorization'), 'no authorization header persisted');
service_assert(!str_contains($dumped, 'candidate_allow_list'), 'no raw prompt persisted');
service_assert(!str_contains($dumped, 'Return exactly three distinct catalog IDs'), 'no prompt instructions persisted');

$failingProvider = service_test_engine(ProviderResponse::failure('provider_unavailable'));
$noCachePdo = service_test_pdo();
$noCacheService = service_make_scenario($noCachePdo, $failingProvider);
service_assert($noCacheService->generate('student-1', 'request-5', 'idempotency-fail-00005')['state'] === 'provider_unavailable', 'provider failure without cache returns provider_unavailable');
service_assert(count($failingProvider->requests()) === 1, 'provider failure is never retried by the service');
$failedReplay = $noCacheService->generate('student-1', 'request-5b', 'idempotency-fail-00005');
service_assert($failedReplay['state'] === 'provider_unavailable', 'failed idempotent replay preserves provider_unavailable');
service_assert(count($failingProvider->requests()) === 1, 'failed idempotent replay does not call the provider again');

$stalePdo = service_test_pdo();
$goodProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$staleService = service_make_scenario($stalePdo, $goodProvider);
service_assert($staleService->generate('student-1', 'request-6', 'idempotency-stale-00006')['state'] === 'ready_model', 'initial generation succeeds');
$brokenProvider = service_test_engine(ProviderResponse::failure('provider_unavailable'));
$staleServiceAfter = service_make_scenario($stalePdo, $brokenProvider);
$stale = $staleServiceAfter->generate('student-1', 'request-7', 'idempotency-stale-00007');
service_assert($stale['state'] === 'stale_model', 'provider failure with canonical cache returns stale_model');
service_assert(service_test_catalog_ids($stale['items']) === ['internship-1', 'internship-2', 'internship-3'], 'stale model keeps the canonical top three');
service_assert(array_column($stale['items'], 'match_score') === [92, 84, 76], 'stale model keeps persisted match scores');
service_assert(count($brokenProvider->requests()) === 1, 'stale fallback never retries the provider');
$staleReplay = $staleServiceAfter->generate('student-1', 'request-7b', 'idempotency-stale-00007');
service_assert($staleReplay['state'] === 'stale_model', 'stale idempotent replay preserves stale_model');
service_assert(count($brokenProvider->requests()) === 1, 'stale idempotent replay does not call the provider again');

$validatorRejectingProvider = service_test_engine(ProviderResponse::success([
    ['catalog_id' => 'invented-9999', 'gemini_score' => 50, 'why_fit' => 'Invented id attempt one for the validator.', 'matched_skill_codes' => ['python'], 'missing_skill_codes' => [], 'expected_outcome_codes' => ['dashboard'], 'evidence_ref_ids' => ['opportunity:internship-1']],
    ['catalog_id' => 'internship-2', 'gemini_score' => 50, 'why_fit' => 'Second invented id attempt for the validator.', 'matched_skill_codes' => ['python'], 'missing_skill_codes' => [], 'expected_outcome_codes' => ['dashboard'], 'evidence_ref_ids' => ['opportunity:internship-2']],
    ['catalog_id' => 'internship-3', 'gemini_score' => 50, 'why_fit' => 'Third invented id attempt for the validator.', 'matched_skill_codes' => ['python'], 'missing_skill_codes' => [], 'expected_outcome_codes' => ['dashboard'], 'evidence_ref_ids' => ['opportunity:internship-3']],
]));
$deterministicPdo = service_test_pdo();
$deterministicService = service_make_scenario($deterministicPdo, $validatorRejectingProvider);
service_assert($deterministicService->generate('student-1', 'request-8', 'idempotency-det-00008')['state'] === 'provider_unavailable', 'deterministic validation failure returns safe state');
service_assert(count($validatorRejectingProvider->requests()) === 1, 'deterministic validation failures are never retried');

$crossSkillItems = service_test_model_items();
$crossSkillItems[0]['matched_skill_codes'] = ['sql'];
$crossSkillProvider = service_test_engine(ProviderResponse::success($crossSkillItems));
$crossSkillPdo = service_test_pdo();
$crossSkillService = service_make_scenario($crossSkillPdo, $crossSkillProvider);
service_assert($crossSkillService->generate('student-1', 'request-cross-skill-0001', 'idempotency-cross-skill-00001')['state'] === 'provider_unavailable', 'cross-candidate skill code returns safe state');
service_assert(count($crossSkillProvider->requests()) === 1, 'cross-candidate skill failure is never retried');

$crossOutcomeItems = service_test_model_items();
$crossOutcomeItems[1]['expected_outcome_codes'] = ['unrelated_outcome'];
$crossOutcomeProvider = service_test_engine(ProviderResponse::success($crossOutcomeItems));
$crossOutcomePdo = service_test_pdo();
$crossOutcomeService = service_make_scenario($crossOutcomePdo, $crossOutcomeProvider);
service_assert($crossOutcomeService->generate('student-1', 'request-cross-outcome-0001', 'idempotency-cross-outcome-00001')['state'] === 'provider_unavailable', 'cross-candidate outcome code returns safe state');
service_assert(count($crossOutcomeProvider->requests()) === 1, 'cross-candidate outcome failure is never retried');

$crossEvidenceItems = service_test_model_items();
$crossEvidenceItems[2]['evidence_ref_ids'] = ['opportunity:internship-1'];
$crossEvidenceProvider = service_test_engine(ProviderResponse::success($crossEvidenceItems));
$crossEvidencePdo = service_test_pdo();
$crossEvidenceService = service_make_scenario($crossEvidencePdo, $crossEvidenceProvider);
service_assert($crossEvidenceService->generate('student-1', 'request-cross-evidence-0001', 'idempotency-cross-evidence-00001')['state'] === 'provider_unavailable', 'cross-candidate evidence reference returns safe state');
service_assert(count($crossEvidenceProvider->requests()) === 1, 'cross-candidate evidence failure is never retried');

$unsafeItems = service_test_model_items();
$unsafeItems[0]['why_fit'] = 'Completing this project guaranteed admission to the advanced track next term.';
$unsafeProvider = service_test_engine(ProviderResponse::success($unsafeItems));
$unsafePdo = service_test_pdo();
$unsafeService = service_make_scenario($unsafePdo, $unsafeProvider);
service_assert($unsafeService->generate('student-1', 'request-unsafe-0001', 'idempotency-unsafe-00001')['state'] === 'provider_unavailable', 'unsafe claim returns safe state');
service_assert(count($unsafeProvider->requests()) === 1, 'unsafe claim is never retried');

$malformedProvider = service_test_engine(
    ProviderResponse::success(array_slice(service_test_model_items(), 0, 2)),
    ProviderResponse::success(service_test_model_items()),
);
$malformedPdo = service_test_pdo();
$malformedService = service_make_scenario($malformedPdo, $malformedProvider);
$malformedResult = $malformedService->generate('student-1', 'request-malformed-0001', 'idempotency-malformed-00001');
service_assert($malformedResult['state'] === 'ready_model', 'malformed output is retried exactly once and then succeeds');
service_assert(count($malformedProvider->requests()) === 2, 'malformed output retries the provider exactly once');
$firstPayload = $malformedProvider->request(0)->payload();
$secondPayload = $malformedProvider->request(1)->payload();
service_assert(($firstPayload['input']['context']['request_id'] ?? '') === ($secondPayload['input']['context']['request_id'] ?? ''), 'malformed retry reuses the request id');
service_assert(($firstPayload['input']['context']['idempotency_key'] ?? '') === ($secondPayload['input']['context']['idempotency_key'] ?? ''), 'malformed retry reuses the idempotency key');
service_assert(($firstPayload['input']['candidate_allow_list'] ?? []) === ($secondPayload['input']['candidate_allow_list'] ?? []), 'malformed retry reuses the candidate allow list');
service_assert(json_encode($firstPayload['input']['student_profile'] ?? [], JSON_THROW_ON_ERROR) === json_encode($secondPayload['input']['student_profile'] ?? [], JSON_THROW_ON_ERROR), 'malformed retry reuses the same snapshot');
service_assert(service_test_catalog_ids($malformedResult['items']) === ['internship-1', 'internship-2', 'internship-3'], 'malformed retry persists the canonical top three');
service_assert(array_column($malformedResult['items'], 'match_score') === [92, 84, 76], 'malformed retry final scores compose 70/30');

$missingEvidenceItems = service_test_model_items();
$missingEvidenceItems[0]['evidence_ref_ids'] = [];
$missingEvidenceProvider = service_test_engine(
    ProviderResponse::success($missingEvidenceItems),
    ProviderResponse::success(service_test_model_items()),
);
$missingEvidenceService = service_make_scenario(service_test_pdo(), $missingEvidenceProvider);
service_assert($missingEvidenceService->generate('student-1', 'request-missing-evidence-0001', 'idempotency-missing-evidence-01')['state'] === 'ready_model', 'missing evidence is classified as malformed and retried');
service_assert(count($missingEvidenceProvider->requests()) === 2, 'missing evidence retries the provider exactly once');

$hardGateProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$hardGateScorer = static function (LearnerOpportunityProfile $profile, OpportunityCandidate $candidate): OpportunityScore {
    if ($candidate->catalogId() === 'internship-5') {
        throw new DomainException('candidate_ineligible');
    }
    return service_test_scorer()($profile, $candidate);
};
$hardGateService = service_make_scenario(service_test_pdo(), $hardGateProvider, scorer: $hardGateScorer);
service_assert($hardGateService->generate('student-1', 'request-hard-gate-0001', 'idempotency-hard-gate-00001')['state'] === 'ready_model', 'scorer hard-gate filters one candidate without escaping the service');
service_assert(!in_array('internship-5', array_column($hardGateProvider->requests()[0]->payload()['input']['candidate_allow_list'], 'catalog_id'), true), 'hard-gated candidate is not sent to the provider');

$nullEnginePdo = service_test_pdo();
$nullEngineService = service_make_scenario($nullEnginePdo, null);
service_assert($nullEngineService->generate('student-1', 'request-null-engine-0001', 'idempotency-null-engine-01')['state'] === 'provider_unavailable', 'missing engine returns provider_unavailable');
$nullEnginePending = $nullEnginePdo->query("SELECT COUNT(1) FROM learner_recommendation_runs WHERE capability = 'opportunity_match' AND status = 'pending'")->fetchColumn();
service_assert((int) $nullEnginePending === 0, 'missing engine does not leave a pending run');

$inactiveEvidence = array_map(
    static fn (int $index): array => service_test_candidate_evidence("internship-{$index}"),
    range(2, 5),
);
$inactivePdo = service_test_pdo();
$inactiveGood = service_test_engine(ProviderResponse::success(service_test_model_items()));
$inactiveService = service_make_scenario($inactivePdo, $inactiveGood, candidateEvidence: $inactiveEvidence);
service_assert($inactiveService->generate('student-1', 'request-9', 'idempotency-inact-00009')['state'] === 'provider_unavailable', 'run with inactive candidate cannot complete stale path');

$closePdo = service_test_pdo();
$closeGood = service_test_engine(ProviderResponse::success(service_test_model_items()));
$closeService = service_make_scenario($closePdo, $closeGood);
service_assert($closeService->generate('student-1', 'request-10', 'idempotency-close-00010')['state'] === 'ready_model', 'cache scenario generates successfully');
$closedEvidence = [
    service_test_candidate_evidence('internship-2'),
    service_test_candidate_evidence('internship-3'),
    service_test_candidate_evidence('internship-4'),
];
$closedService = service_make_scenario($closePdo, service_test_engine(ProviderResponse::failure('provider_unavailable')), candidateEvidence: $closedEvidence);
service_assert($closedService->generate('student-1', 'request-11', 'idempotency-close-00011')['state'] === 'provider_unavailable', 'cache containing a closed candidate is not used as stale fallback');
service_assert($closedService->latest('student-1')['state'] === 'not_generated', 'closed candidate removes the cached run from latest');

$txPdo = service_test_pdo();
$txGood = service_test_engine(ProviderResponse::success(service_test_model_items()));
$txService = service_make_scenario($txPdo, $txGood);
service_assert($txService->generate('student-1', 'request-tx-0001', 'idempotency-tx-00001')['state'] === 'ready_model', 'first run persists canonically before the transaction failure');
$txOldLatest = $txService->latest('student-1');
service_assert(service_test_catalog_ids($txOldLatest['items']) === ['internship-1', 'internship-2', 'internship-3'], 'old run is canonical latest before the transaction failure');
$txPdo->exec("CREATE TRIGGER trg_opportunity_match_force_evidence_failure BEFORE INSERT ON learner_recommendation_evidence FOR EACH ROW BEGIN SELECT RAISE(ABORT, 'simulated evidence persistence failure'); END");
$txProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$txService2 = service_make_scenario($txPdo, $txProvider);
service_assert($txService2->generate('student-1', 'request-tx-0002', 'idempotency-tx-00002')['state'] === 'provider_unavailable', 'persistence failure inside the repository surfaces safe state');
service_assert(count($txProvider->requests()) === 1, 'persistence failure is not retried by the service');
$txAfter = $txService2->latest('student-1');
service_assert(service_test_catalog_ids($txAfter['items']) === ['internship-1', 'internship-2', 'internship-3'], 'transaction rollback does not supersede the old canonical run');
service_assert(array_column($txAfter['items'], 'match_score') === [92, 84, 76], 'old canonical scores survive the failed transaction');
$txFailed = $txPdo->query("SELECT COUNT(1) FROM learner_recommendation_runs WHERE capability = 'opportunity_match' AND status = 'failed' AND safeErrorCode = 'engine_failure'")->fetchColumn();
service_assert((int) $txFailed === 1, 'the failed run is persisted with a safe code');
$txActive = $txPdo->query("SELECT COUNT(1) FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE runs.capability = 'opportunity_match' AND items.lifecycleStatus = 'active'")->fetchColumn();
service_assert((int) $txActive === 3, 'only the canonical three items remain active after the rollback');
$txRuns = $txPdo->query("SELECT COUNT(1) FROM learner_recommendation_runs WHERE capability = 'opportunity_match' AND status = 'failed'")->fetchColumn();
service_assert((int) $txRuns === 1, 'no partially persisted run survives the transaction failure');

$isolationPdo = service_test_pdo();
$isolationProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$isolationService = service_make_scenario($isolationPdo, $isolationProvider);
service_assert($isolationService->generate('student-1', 'request-14', 'idempotency-iso-00014')['state'] === 'ready_model', 'student-1 generates');
service_assert($isolationService->latest('student-2')['state'] === 'not_generated', 'student-2 cannot read student-1 matches');
$crossRun = $isolationPdo->query("SELECT COUNT(1) FROM learner_recommendation_runs WHERE studentId = 'student-2'")->fetchColumn();
service_assert((int) $crossRun === 0, 'student-2 has no runs created by student-1 generation');

$tiePdo = service_test_pdo();
$tieEvidence = [
    service_test_candidate_evidence('internship-1'),
    service_test_candidate_evidence('internship-2'),
    service_test_candidate_evidence('internship-3'),
    service_test_candidate_evidence('internship-4', ['deadline_at' => '2026-09-15T00:00:00.000000+00:00']),
    service_test_candidate_evidence('internship-5', ['deadline_at' => '2026-09-01T00:00:00.000000+00:00']),
];
$tieProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$tieService = service_make_scenario($tiePdo, $tieProvider, candidateEvidence: $tieEvidence);
service_assert($tieService->generate('student-1', 'request-15', 'idempotency-tie-00015')['state'] === 'ready_model', 'tie scenario generates');
$allowList = $tieProvider->requests()[0]->payload()['input']['candidate_allow_list'];
$allowIds = array_column($allowList, 'catalog_id');
service_assert(count($allowList) === 5, 'tie allow-list holds every candidate');
service_assert(array_search('internship-5', $allowIds, true) < array_search('internship-4', $allowIds, true), 'equal structured scores tie-break by deadline ascending');

$boundaryPdo = service_test_pdo();
$boundaryEvidence = array_map(
    static fn (int $index): array => service_test_candidate_evidence("internship-{$index}"),
    range(1, 12),
);
$boundaryProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$boundaryService = service_make_scenario($boundaryPdo, $boundaryProvider, candidateEvidence: $boundaryEvidence);
service_assert($boundaryService->generate('student-1', 'request-16', 'idempotency-bound-00016')['state'] === 'ready_model', 'twelve candidates still generate top three');
$boundaryAllowList = $boundaryProvider->requests()[0]->payload()['input']['candidate_allow_list'];
service_assert(count($boundaryAllowList) === 10, 'at most ten candidates are sent to Gemini');
service_assert(array_column($boundaryAllowList, 'catalog_id') === [
    'internship-1', 'internship-2', 'internship-3', 'internship-4', 'internship-5',
    'internship-6', 'internship-7', 'internship-8', 'internship-9', 'internship-10',
], 'top ten slice keeps deterministic order');

$partialPdo = service_test_pdo();
$partialProvider = service_test_engine(ProviderResponse::success(service_test_model_items()));
$partialService = service_make_scenario($partialPdo, $partialProvider);
service_assert($partialService->generate('student-1', 'request-partial-0001', 'idempotency-partial-00001')['state'] === 'ready_model', 'partial cache fixture starts with a complete run');
$partialPdo->exec("DELETE FROM learner_recommendation_items WHERE rankPosition = 3");
service_assert($partialService->latest('student-1')['state'] === 'not_generated', 'latest rejects a completed run that no longer has exactly three items');

$repositorySource = file_get_contents(dirname(__DIR__) . '/app/learner/ai/Persistence/DatabaseOpportunityMatchRepository.php');
service_assert(is_string($repositorySource), 'repository source is readable for SQL portability guards');
service_assert(!str_contains($repositorySource, 'UPDATE learner_recommendation_items AS items'), 'supersede SQL does not use a MySQL self-referencing target alias');
service_assert(str_contains($repositorySource, 'runs.capability = :capability AND evidence.itemId = :itemId'), 'evidence read is capability scoped');

echo "learner_ai_opportunity_service_test: OK\n";
