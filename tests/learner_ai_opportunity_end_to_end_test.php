<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Consent\ConsentDecision;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\StructuredOpportunityScorer;
use TalentHub\Learner\Ai\Model\ModelOpportunityMatchEngine;
use TalentHub\Learner\Ai\Model\OpportunityMatchPromptRegistry;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderResponse;
use TalentHub\Learner\Ai\Service\OpportunityMatchService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function e2e_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "e2e_contract_violation={$message}\n");
        exit(1);
    }
}

function e2e_pdo(): PDO
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
    return $pdo;
}

function e2e_decision(bool $granted): ConsentDecision
{
    $events = [];
    foreach (ConsentDecision::REQUIRED_SCOPES as $scope) {
        $events[$scope] = ['action' => $granted ? 'granted' : 'revoked', 'policy_version' => 'learner-ai-consent-1.0', 'occurred_at' => '2026-08-01T00:00:00.000000+00:00', 'request_id' => 'req-e2e-consent'];
    }
    return new ConsentDecision($events, '2026-08-29T00:00:00.000000+00:00', $granted ? ConsentDecision::REQUIRED_SCOPES : []);
}

function e2e_input(): RecommendationInput
{
    $payload = [
        'education_band' => 'high',
        'profile' => ['grade_level' => 11],
        'skills' => [
            ['code' => 'python', 'score' => 82, 'verification_status' => 'verified'],
            ['code' => 'sql', 'score' => 35, 'verification_status' => 'active'],
        ],
        'assessments' => [
            ['dimension_scores' => ['logical_thinking' => 88], 'submitted_at' => '2026-08-01T00:00:00.000000+00:00'],
        ],
        'activities' => [
            ['experience_id' => 'exp-1', 'activity_category' => 'STEM', 'tags' => ['python']],
        ],
    ];

    $evidence = [
        ['source_type' => 'skill', 'source_id' => 'python-skill-1', 'observed_at' => '2026-08-01T00:00:00.000000+00:00', 'safe_value' => ['code' => 'python', 'level_score' => 82]],
    ];
    $evidence[] = ['source_type' => 'opportunity', 'source_id' => 'internship-1', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'Smart Campus IoT Internship']];
    $evidence[] = ['source_type' => 'catalog', 'source_id' => 'project-2', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'Green Analytics Data Project']];
    $evidence[] = ['source_type' => 'catalog', 'source_id' => 'catalog-3', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'Community Learning Dashboard']];
    $evidence[] = ['source_type' => 'opportunity', 'source_id' => 'internship-4', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'Mobile Automation Internship']];
    $evidence[] = ['source_type' => 'opportunity', 'source_id' => 'internship-5', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'QA Support Internship']];

    return new RecommendationInput($payload, [], ['allowed_scopes' => ConsentDecision::REQUIRED_SCOPES], $evidence);
}

function e2e_candidate(string $catalogId, string $sourceType, array $overrides = []): array
{
    $safe = array_merge([
        'catalog_id' => $catalogId,
        'item_type' => 'internship',
        'title' => "Title for {$catalogId}",
        'provider_name' => "Provider for {$catalogId}",
        'summary' => "Summary for {$catalogId}",
        'category' => 'Logical Thinking',
        'required_skills' => [['code' => 'python', 'minimum_score' => 60, 'label' => 'Python']],
        'learning_outcomes' => [['code' => 'dashboard', 'label' => 'Dashboard du lieu']],
        'education_bands' => ['high', 'college'],
        'deadline_at' => '2027-03-01T00:00:00.000000+00:00',
        'availability' => ['capacity' => 5, 'enrolled' => 3, 'remaining' => 2],
        'status' => 'active',
        'url' => '/app/learner/opportunity.php?id=' . $catalogId,
    ], $overrides);
    return ['source_type' => $sourceType, 'source_id' => $catalogId, 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => $safe];
}

/** @return list<array<string,mixed>> Five active, one expired, one protected-trait candidate. */
function e2e_candidate_evidence(): array
{
    return [
        e2e_candidate('internship-1', 'opportunity', [
            'title' => 'Smart Campus IoT Internship',
            'summary' => 'Xay dung he thong IoT cho campus với cảm biến và xử lý dữ liệu.',
        ]),
        e2e_candidate('project-2', 'catalog', [
            'item_type' => 'project',
            'title' => 'Green Analytics Data Project',
            'summary' => 'Du an phan tich du lieu xanh cho truong hoc.',
            'required_skills' => [
                ['code' => 'python', 'minimum_score' => 60, 'label' => 'Python'],
                ['code' => 'git', 'minimum_score' => 40, 'label' => 'Git'],
            ],
            'learning_outcomes' => [
                ['code' => 'git', 'label' => 'Lam viec voi Git'],
                ['code' => 'prototyping', 'label' => 'Prototype nhanh'],
            ],
            'deadline_at' => '2027-02-01T00:00:00.000000+00:00',
            'url' => '/app/learner/ecosystem.php?focus=project-2',
        ]),
        e2e_candidate('catalog-3', 'catalog', [
            'item_type' => 'project',
            'title' => 'Community Learning Dashboard',
            'summary' => 'Du an dashboard cong dong cho ho so nang luc.',
            'required_skills' => [
                ['code' => 'sql', 'minimum_score' => 30, 'label' => 'SQL'],
                ['code' => 'python', 'minimum_score' => 90, 'label' => 'Python nang cao'],
            ],
            'learning_outcomes' => [
                ['code' => 'python', 'label' => 'Python nang cao'],
                ['code' => 'dashboard_data', 'label' => 'Dashboard du lieu'],
            ],
            'deadline_at' => '2027-01-01T00:00:00.000000+00:00',
            'url' => '/app/learner/ecosystem.php?focus=catalog-3',
        ]),
        e2e_candidate('internship-4', 'opportunity', [
            'title' => 'Mobile Automation Internship',
            'deadline_at' => '2027-04-01T00:00:00.000000+00:00',
        ]),
        e2e_candidate('internship-5', 'opportunity', [
            'title' => 'QA Support Internship',
            'deadline_at' => '2027-05-01T00:00:00.000000+00:00',
        ]),
        e2e_candidate('internship-expired', 'opportunity', [
            'title' => 'Closed Last Year Internship',
            'deadline_at' => '2020-01-01T00:00:00.000000+00:00',
        ]),
        e2e_candidate('internship-protected', 'opportunity', [
            'title' => 'Protected Trait Internship',
            'health' => 'bao-mat-thong-tin-nhay-cam-e2e',
        ]),
    ];
}

/** Active candidates remaining after the first-ranked candidate is closed. */
function e2e_active_after_close_evidence(): array
{
    return array_values(array_filter(
        e2e_candidate_evidence(),
        static fn (array $entry): bool => !in_array($entry['source_id'], ['internship-1', 'internship-expired', 'internship-protected'], true),
    ));
}

function e2e_model_items(): array
{
    return [
        [
            'catalog_id' => 'internship-1',
            'gemini_score' => 97,
            'why_fit' => 'Du an IoT campus dung ngay ky nang Python da xac minh cua ban.',
            'matched_skill_codes' => ['python'],
            'missing_skill_codes' => [],
            'expected_outcome_codes' => ['dashboard'],
            'evidence_ref_ids' => ['opportunity:internship-1'],
        ],
        [
            'catalog_id' => 'project-2',
            'gemini_score' => 93,
            'why_fit' => 'Du an phan tich du lieu giup ban ren tu duy va lam viec nhom.',
            'matched_skill_codes' => ['python'],
            'missing_skill_codes' => ['git'],
            'expected_outcome_codes' => ['git', 'prototyping'],
            'evidence_ref_ids' => ['catalog:project-2'],
        ],
        [
            'catalog_id' => 'catalog-3',
            'gemini_score' => 90,
            'why_fit' => 'Du an dashboard cong dong giup ban bu du ky nang python nang cao.',
            'matched_skill_codes' => ['sql'],
            'missing_skill_codes' => ['python'],
            'expected_outcome_codes' => ['python', 'dashboard_data'],
            'evidence_ref_ids' => ['catalog:catalog-3'],
        ],
    ];
}

final class E2eFakeGeminiProvider implements RecommendationProvider
{
    /** @var list<ProviderRequest> */
    private array $requests = [];

    public function __construct(private readonly ProviderResponse $response)
    {
    }

    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse
    {
        $authorizer->beforeAttempt(1);
        $this->requests[] = $request;
        return $this->response;
    }

    /** @return list<ProviderRequest> */
    public function requests(): array
    {
        return $this->requests;
    }
}

final class E2eFailingGeminiProvider implements RecommendationProvider
{
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse
    {
        $authorizer->beforeAttempt(1);
        return ProviderResponse::failure('provider_unavailable');
    }
}

function e2e_service(PDO $pdo, RecommendationProvider $provider, ?array $candidateEvidence = null): OpportunityMatchService
{
    $decision = e2e_decision(true);
    $candidateEvidence = $candidateEvidence ?? e2e_candidate_evidence();
    $scorer = new StructuredOpportunityScorer();

    return new OpportunityMatchService(
        new \TalentHub\Learner\Ai\Persistence\DatabaseOpportunityMatchRepository(
            $pdo,
            'e2e_fake_gemini',
            'fake/gemini-e2e',
            OpportunityMatchPromptRegistry::VERSION,
        ),
        static fn (string $studentId): ConsentDecision => $decision,
        static fn (string $studentId): RecommendationInput => e2e_input(),
        static fn (string $studentId): array => $candidateEvidence,
        static fn (LearnerOpportunityProfile $profile, $candidate) => $scorer->score($profile, $candidate),
        new ModelOpportunityMatchEngine($provider, new class implements ProviderAttemptAuthorizer {
            public function beforeAttempt(int $attemptNumber): ConsentDecision
            {
                return e2e_decision(true);
            }
        }),
    );
}

function e2e_catalog_ids(array $items): array
{
    return array_map(static fn (array $item): string => (string) $item['catalog_id'], $items);
}

/** Capability-specific persistence mapping under test: catalog/project
 * evidence must round-trip through a persistable snapshot source type. */
function e2e_persistable_ref(string $reference): string
{
    $parts = explode(':', $reference, 2);
    if (count($parts) === 2 && $parts[0] === 'catalog') {
        return 'opportunity:' . $parts[1];
    }
    return $reference;
}

$studentId = 'student-e2e-1';
$pdo = e2e_pdo();
$provider = new E2eFakeGeminiProvider(ProviderResponse::success(e2e_model_items()));
$service = e2e_service($pdo, $provider);

$generated = $service->generate($studentId, 'request-e2e-0001', 'idempotency-e2e-0001');
e2e_assert(($generated['state'] ?? null) === 'ready_model', 'generate returns ready_model, got ' . var_export($generated['state'] ?? null, true));
e2e_assert(count($generated['items']) === 3, 'generation returns exactly three items');
$generatedIds = e2e_catalog_ids($generated['items']);
e2e_assert(count(array_unique($generatedIds)) === 3, 'three distinct catalog ids are generated');
e2e_assert(count(array_unique(array_column($generated['items'], 'why_fit'))) === 3, 'three distinct why_fit analyses are generated');
e2e_assert(in_array('internship-1', $generatedIds, true), 'top three include an opportunity-sourced candidate');
e2e_assert(in_array('project-2', $generatedIds, true) && in_array('catalog-3', $generatedIds, true), 'top three include catalog/project candidates');
e2e_assert($generatedIds === ['internship-1', 'project-2', 'catalog-3'], 'top three keep the deterministic final order');
e2e_assert(array_column($generated['items'], 'rank') === [1, 2, 3], 'ranks are 1..3');
e2e_assert(array_column($generated['items'], 'match_score') === [99, 81, 80], 'final scores compose 70/30 into 99/81/80');

$providerRequests = $provider->requests();
e2e_assert(count($providerRequests) === 1, 'successful generation calls the fake Gemini exactly once');
$allowList = $providerRequests[0]->payload()['input']['candidate_allow_list'];
$allowIds = array_column($allowList, 'catalog_id');
e2e_assert(count($allowList) === 5, 'allow-list holds exactly the five active candidates');
e2e_assert($allowIds === ['internship-1', 'internship-4', 'internship-5', 'catalog-3', 'project-2'], 'allow-list keeps the deterministic structured order');
e2e_assert(!in_array('internship-expired', $allowIds, true), 'expired candidate is not sent to Gemini');
e2e_assert(!in_array('internship-protected', $allowIds, true), 'protected-trait candidate is not sent to Gemini');
$projectEntry = null;
foreach ($allowList as $entry) {
    if (($entry['catalog_id'] ?? '') === 'project-2') {
        $projectEntry = $entry;
    }
}
e2e_assert(is_array($projectEntry) && ($projectEntry['catalog_type'] ?? '') === 'project', 'catalog candidate keeps its canonical project type in the prompt');
e2e_assert(str_contains(json_encode($providerRequests[0]->payload(), JSON_THROW_ON_ERROR), 'catalog:project-2'), 'prompt evidence allow-list carries the catalog candidate evidence ref');

foreach ($generated['items'] as $item) {
    e2e_assert(is_int($item['match_score']) && $item['match_score'] >= 0 && $item['match_score'] <= 100, 'match score stays within 0..100');
    e2e_assert($item['matched_skills'] !== [], 'every item carries matched skills');
    e2e_assert($item['expected_outcomes'] !== [], 'every item carries expected outcomes');
    e2e_assert($item['evidence'] !== [], 'every item carries evidence references');
}

$byCatalogId = [];
foreach ($generated['items'] as $item) {
    $byCatalogId[$item['catalog_id']] = $item;
}
e2e_assert($byCatalogId['project-2']['title'] === 'Green Analytics Data Project', 'catalog candidate keeps the canonical database title');
e2e_assert($byCatalogId['project-2']['canonical_url'] === '/app/learner/ecosystem.php?focus=project-2', 'catalog candidate keeps the canonical url');
e2e_assert($byCatalogId['project-2']['evidence'] === ['catalog:project-2'], 'catalog candidate cites its own catalog evidence ref');
e2e_assert($byCatalogId['catalog-3']['evidence'] === ['catalog:catalog-3'], 'second catalog candidate cites its own catalog evidence ref');

$items = $pdo->query("SELECT items.itemType, items.catalogId, items.rankPosition, items.structuredScore, items.geminiScore, items.matchScore, items.title, items.summary, items.actionJson, items.analysisJson FROM learner_recommendation_items AS items INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE runs.capability = 'opportunity_match' ORDER BY items.rankPosition ASC")->fetchAll(PDO::FETCH_ASSOC);
e2e_assert(count($items) === 3, 'exactly three opportunity match items are persisted');
e2e_assert(array_column($items, 'itemType') === ['activity', 'activity', 'activity'], 'persisted items keep itemType activity');
e2e_assert(array_column($items, 'catalogId') === ['internship-1', 'project-2', 'catalog-3'], 'persisted items keep canonical catalog ids');
e2e_assert(array_column($items, 'rankPosition') === [1, 2, 3], 'persisted items keep rank positions 1..3');
e2e_assert(array_column($items, 'matchScore') === [99, 81, 80], 'persisted items keep composed match scores');
e2e_assert(array_column($items, 'structuredScore') === [100, 76, 76], 'persisted items keep structured component scores');
e2e_assert(array_column($items, 'geminiScore') === [97, 93, 90], 'persisted items keep gemini component scores');
$titles = array_column($items, 'title');
e2e_assert(str_contains((string) $titles[1], 'Green Analytics Data Project'), 'catalog project title persists canonically');
$action = json_decode((string) $items[1]['actionJson'], true);
e2e_assert(($action['catalog_id'] ?? '') === 'project-2' && ($action['url'] ?? '') === '/app/learner/ecosystem.php?focus=project-2', 'catalog project action keeps canonical id and url');

$evidenceRows = $pdo->query("SELECT evidence.sourceType, evidence.sourceId, items.catalogId FROM learner_recommendation_evidence AS evidence INNER JOIN learner_recommendation_items AS items ON items.id = evidence.itemId INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE runs.capability = 'opportunity_match' ORDER BY items.rankPosition ASC")->fetchAll(PDO::FETCH_ASSOC);
e2e_assert(count($evidenceRows) === 3, 'one evidence row is persisted per match');
e2e_assert(array_column($evidenceRows, 'sourceType') === ['opportunity', 'opportunity', 'opportunity'], 'catalog evidence is normalized into the persistable opportunity source type');
e2e_assert(!in_array('catalog', array_column($evidenceRows, 'sourceType'), true), 'no unpersistable catalog source type is stored');
e2e_assert(array_column($evidenceRows, 'sourceId') === ['internship-1', 'project-2', 'catalog-3'], 'normalized evidence keeps the canonical source ids');

$snapshotId = (string) $pdo->query("SELECT snapshotId FROM learner_recommendation_runs WHERE capability = 'opportunity_match' AND studentId = '{$studentId}' LIMIT 1")->fetchColumn();
$snapshotEvidence = $pdo->prepare('SELECT sourceType, sourceId FROM learner_recommendation_snapshot_evidence WHERE snapshotId = :snapshotId');
$snapshotEvidence->execute(['snapshotId' => $snapshotId]);
$snapshotRefs = array_map(static fn (array $row): string => $row['sourceType'] . ':' . $row['sourceId'], $snapshotEvidence->fetchAll(PDO::FETCH_ASSOC));
foreach (['skill:python-skill-1', 'opportunity:internship-1', 'opportunity:internship-4', 'opportunity:internship-5'] as $requiredRef) {
    e2e_assert(in_array($requiredRef, $snapshotRefs, true), "snapshot evidence keeps {$requiredRef}");
}
e2e_assert(in_array('opportunity:project-2', $snapshotRefs, true), 'catalog project evidence is normalized inside the run snapshot');
e2e_assert(in_array('opportunity:catalog-3', $snapshotRefs, true), 'second catalog project evidence is normalized inside the run snapshot');
e2e_assert(!in_array('catalog:project-2', $snapshotRefs, true) && !in_array('catalog:catalog-3', $snapshotRefs, true), 'no unpersistable catalog source type is stored in the snapshot');
foreach ($generated['items'] as $item) {
    foreach ($item['evidence'] as $reference) {
        e2e_assert(in_array(e2e_persistable_ref((string) $reference), $snapshotRefs, true), "output evidence ref {$reference} resolves inside the run snapshot evidence");
    }
}

$audit = json_decode((string) $pdo->query("SELECT engineMetadataJson FROM learner_recommendation_audit_events WHERE action = 'opportunity_match_completed' LIMIT 1")->fetchColumn(), true);
e2e_assert(is_array($audit), 'completion writes one audit event');
e2e_assert(($audit['provider'] ?? '') === 'e2e_fake_gemini' && ($audit['model_version'] ?? '') === 'fake/gemini-e2e', 'audit carries provider and model versions');
e2e_assert(($audit['prompt_version'] ?? '') === OpportunityMatchPromptRegistry::VERSION, 'audit carries the prompt version');
e2e_assert(strlen((string) ($audit['response_hash'] ?? '')) === 64, 'audit carries a 64 character response hash');

$reloaded = $service->latest($studentId);
e2e_assert(($reloaded['state'] ?? null) === 'ready_model', 'latest reloads the persisted run as ready_model');
e2e_assert(e2e_catalog_ids($reloaded['items']) === $generatedIds, 'latest keeps the persisted catalog id order');
foreach ($generated['items'] as $index => $item) {
    $restored = $reloaded['items'][$index];
    e2e_assert($restored['why_fit'] === $item['why_fit'], 'why_fit round-trips through persistence');
    e2e_assert($restored['matched_skills'] === $item['matched_skills'], 'matched skills round-trip through persistence');
    e2e_assert($restored['missing_skills'] === $item['missing_skills'], 'missing skills round-trip through persistence');
    e2e_assert($restored['expected_outcomes'] === $item['expected_outcomes'], 'expected outcomes round-trip through persistence');
    e2e_assert($restored['evidence'] === $item['evidence'], 'evidence refs round-trip through persistence');
    e2e_assert($restored['title'] === $item['title'] && $restored['canonical_url'] === $item['canonical_url'], 'canonical title and url round-trip through persistence');
    e2e_assert($restored['match_score'] === $item['match_score'], 'match score round-trips through persistence');
}

$replayed = $service->generate($studentId, 'request-e2e-0002', 'idempotency-e2e-0001');
e2e_assert(($replayed['state'] ?? null) === 'ready_model', 'idempotent replay returns the persisted run');
e2e_assert(e2e_catalog_ids($replayed['items']) === $generatedIds, 'idempotent replay keeps the persisted catalog ids');
e2e_assert(count($provider->requests()) === 1, 'idempotent replay never calls the provider again');

$closedService = e2e_service($pdo, new E2eFailingGeminiProvider(), e2e_active_after_close_evidence());
$closedGenerate = $closedService->generate($studentId, 'request-e2e-0003', 'idempotency-e2e-0003');
e2e_assert(($closedGenerate['state'] ?? null) === 'provider_unavailable', 'closing the first-ranked candidate refuses the stale fallback');
$closedLatest = $closedService->latest($studentId);
e2e_assert(($closedLatest['state'] ?? null) === 'not_generated', 'closing the first-ranked candidate removes the invalid run from latest');
$staleLeak = json_encode($closedLatest, JSON_THROW_ON_ERROR);
e2e_assert(!str_contains($staleLeak, 'internship-1'), 'latest never serves the closed opportunity');

$dumped = '';
foreach (['learner_recommendation_runs', 'learner_recommendation_items', 'learner_recommendation_evidence', 'learner_recommendation_audit_events', 'learner_recommendation_input_snapshots', 'learner_recommendation_snapshot_evidence'] as $table) {
    foreach ($pdo->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dumped .= json_encode($row, JSON_THROW_ON_ERROR);
    }
}
e2e_assert(!str_contains($dumped, 'internship-protected'), 'protected-trait candidate never reaches the database');
e2e_assert(!str_contains($dumped, 'bao-mat-thong-tin-nhay-cam-e2e'), 'protected-trait value never reaches the database');
e2e_assert(!str_contains($dumped, 'internship-expired'), 'expired candidate never reaches the database');
e2e_assert(!str_contains($dumped, 'candidate_allow_list'), 'raw prompt payload is never persisted');
e2e_assert(!str_contains($dumped, 'Return exactly three distinct catalog IDs'), 'prompt instructions are never persisted');
e2e_assert(!str_contains($dumped, 'TALENTHUB_AI_API_KEY'), 'no api key is persisted');
e2e_assert(!str_contains($dumped, 'sk-'), 'no secret token is persisted');
e2e_assert(!str_contains($dumped, 'Authorization'), 'no authorization header is persisted');
e2e_assert(!str_contains(json_encode($providerRequests[0]->payload(), JSON_THROW_ON_ERROR), 'internship-protected'), 'protected-trait candidate never enters the prompt');

echo "learner_ai_opportunity_end_to_end_test: OK\n";
