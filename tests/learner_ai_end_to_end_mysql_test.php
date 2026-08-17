<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Evaluation\RecommendationEvaluator;
use TalentHub\Learner\Ai\Evaluation\ShadowRunService;
use TalentHub\Learner\Ai\Model\ModelRecommendationEngine;
use TalentHub\Learner\Ai\Model\PromptRegistry;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Provider\FakeRecommendationProvider;
use TalentHub\Learner\Ai\Provider\ProviderResponse;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Service\RecommendationResponseMapper;
use TalentHub\Learner\Ai\Service\RecommendationService;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;
use TalentHub\Learner\Seeds\Staging\LearnerAiPilotSeeder;

function e2e_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function e2e_expect_exception(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException('Assertion failed: ' . $message);
}

function e2e_schema(): string
{
    $schema = (string) getenv('LEARNER_MYSQL_TEST_SCHEMA');
    e2e_assert(preg_match('/^talenthub_ai_backup_verify_[A-Za-z0-9_]+$/', $schema) === 1, 'E2E test requires an explicitly named disposable verification schema');
    return $schema;
}

function e2e_pdo(string $schema): PDO
{
    $configRoot = (string) getenv('TALENTHUB_DB_CONFIG_ROOT');
    e2e_assert($configRoot !== '' && is_file($configRoot . '/bin/bootstrap.php') && is_file($configRoot . '/config/database.php'), 'E2E test requires an external local configuration root');
    require_once $configRoot . '/bin/bootstrap.php';
    $config = require $configRoot . '/config/database.php';
    $config['database'] = $schema;
    $pdo = (new TalentHub\Database\Connection($config))->connect();
    e2e_assert((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $schema, 'E2E test is pinned to the requested disposable schema');
    return $pdo;
}

function e2e_snapshot_builder(PDO $pdo): RecommendationSnapshotBuilder
{
    return new RecommendationSnapshotBuilder(
        new DatabaseStudentProfileSource($pdo),
        new DatabaseSkillSource($pdo),
        new DatabaseAssessmentSource($pdo),
        new DatabaseActivityExperienceSource($pdo),
        new DatabasePublishedEvaluationSource($pdo),
        new DatabaseOpportunitySource($pdo),
    );
}

function e2e_service(
    string $authorizedStudentId,
    DatabaseRecommendationRepository $repository,
    RecommendationSnapshotBuilder $snapshotBuilder,
    ConsentPolicy $consent,
): RecommendationService {
    $quality = new DataQualityGate(new DateTimeImmutable('2026-08-17T00:00:00.000000+00:00', new DateTimeZone('UTC')));

    return new RecommendationService(
        $repository,
        new RuleRecommendationEngine(),
        new RecommendationResultValidator(),
        new RecommendationResponseMapper(),
        static fn (string $studentId): bool => hash_equals($authorizedStudentId, $studentId),
        static fn (string $studentId): array => $consent->allowedScopes($studentId),
        static fn (string $studentId, array $scopes) => $snapshotBuilder->build($studentId, $scopes),
        static fn ($input) => $quality->evaluate($input),
        static fn (): bool => true,
    );
}

/** @return array<string,mixed> */
function e2e_generate(RecommendationService $service, string $studentId, string $requestId, string $idempotencyKey): array
{
    return $service->generate($studentId, $requestId, $idempotencyKey);
}

/** @return array{runId:string,status:string,engineType:string,itemId:string,evidenceCount:int} */
function e2e_completed_rule_run(PDO $pdo, string $studentId, string $idempotencyKey): array
{
    $run = $pdo->prepare(
        'SELECT id, status, engineType FROM learner_recommendation_runs WHERE studentId = :studentId AND idempotencyKey = :idempotencyKey AND engineType = :engineType LIMIT 1',
    );
    $run->execute(['studentId' => $studentId, 'idempotencyKey' => $idempotencyKey, 'engineType' => 'rule']);
    $row = $run->fetch(PDO::FETCH_ASSOC);
    e2e_assert(is_array($row) && ($row['status'] ?? null) === 'completed', 'requested learner rule run is persisted and completed');

    $item = $pdo->prepare(
        'SELECT items.id, COUNT(evidence.id) AS evidenceCount FROM learner_recommendation_items AS items LEFT JOIN learner_recommendation_evidence AS evidence ON evidence.itemId = items.id WHERE items.runId = :runId GROUP BY items.id ORDER BY items.priority ASC, items.id ASC LIMIT 1',
    );
    $item->execute(['runId' => $row['id']]);
    $itemRow = $item->fetch(PDO::FETCH_ASSOC);
    e2e_assert(is_array($itemRow) && (int) ($itemRow['evidenceCount'] ?? 0) > 0, 'persisted rule item has provenance evidence');

    return [
        'runId' => (string) $row['id'],
        'status' => (string) $row['status'],
        'engineType' => (string) $row['engineType'],
        'itemId' => (string) $itemRow['id'],
        'evidenceCount' => (int) $itemRow['evidenceCount'],
    ];
}

$repositoryRoot = dirname(__DIR__);
$seedFile = $repositoryRoot . '/Database/seeds/learner/Staging/LearnerAiPilotSeeder.php';
e2e_assert(is_file($seedFile), 'Task 14 pilot seeder is available');
require_once $seedFile;
require_once $repositoryRoot . '/app/learner/ai/bootstrap.php';

e2e_assert((string) getenv('APP_ENV') === 'test', 'E2E test requires APP_ENV=test');
$schema = e2e_schema();
$pdo = e2e_pdo($schema);
$seeder = new LearnerAiPilotSeeder($pdo, $schema);
$seed = $seeder->seed();
e2e_assert($seed['declared'] === 61 && $seed['inserted'] === 0, 'E2E starts from the verified idempotent pilot fixture');

[$studentA, $studentB] = LearnerAiPilotSeeder::studentIds();
$repository = new DatabaseRecommendationRepository($pdo);
$consent = new ConsentPolicy(new DatabaseConsentSource($pdo));
$snapshotBuilder = e2e_snapshot_builder($pdo);
$serviceA = e2e_service($studentA, $repository, $snapshotBuilder, $consent);
$serviceB = e2e_service($studentB, $repository, $snapshotBuilder, $consent);

$ruleA = e2e_generate(
    $serviceA,
    $studentA,
    '00000000-0000-4000-8000-000000009001',
    'e2e-rule-a-v1',
);
$ruleB = e2e_generate(
    $serviceB,
    $studentB,
    '00000000-0000-4000-8000-000000009002',
    'e2e-rule-b-v1',
);
e2e_assert(in_array($ruleA['state'] ?? null, ['ready_rule', 'pending'], true), 'learner A rule request returns a safe owned result or idempotent pending state');
e2e_assert(in_array($ruleB['state'] ?? null, ['ready_rule', 'pending'], true), 'learner B rule request returns a safe owned result or idempotent pending state');
$storedRuleA = e2e_completed_rule_run($pdo, $studentA, 'e2e-rule-a-v1');
$storedRuleB = e2e_completed_rule_run($pdo, $studentB, 'e2e-rule-b-v1');
e2e_assert($storedRuleA['runId'] !== $storedRuleB['runId'], 'two learners receive distinct persisted rule runs');

e2e_assert($serviceA->latest($studentB) === null, 'learner A cannot read learner B latest recommendation endpoint');
e2e_assert($serviceB->latest($studentA) === null, 'learner B cannot read learner A latest recommendation endpoint');
e2e_assert(
    (e2e_generate($serviceA, $studentB, '00000000-0000-4000-8000-000000009003', 'e2e-forbidden-a-to-b')['state'] ?? null) === 'forbidden',
    'authenticated learner A cannot generate a recommendation for learner B',
);

$itemA = $storedRuleA['itemId'];
e2e_assert($itemA !== '', 'learner A rule response includes a feedback-addressable item');
$existingFeedback = $pdo->prepare(
    'SELECT id, studentId, itemId, verdict, reasonCode FROM learner_recommendation_feedback WHERE studentId = :studentId AND itemId = :itemId AND reasonCode = :reasonCode LIMIT 1',
);
$existingFeedback->execute(['studentId' => $studentA, 'itemId' => $itemA, 'reasonCode' => 'e2e_verified']);
$feedback = $existingFeedback->fetch(PDO::FETCH_ASSOC);
if (!is_array($feedback)) {
    $feedback = $repository->appendFeedback($studentA, $itemA, 'helpful', 'e2e_verified', 'Synthetic pilot confirmation.');
}
e2e_assert(($feedback['studentId'] ?? null) === $studentA && ($feedback['itemId'] ?? null) === $itemA, 'learner A can append immutable feedback to its own item');
e2e_expect_exception(
    static fn (): array => $repository->appendFeedback($studentB, $itemA, 'helpful', 'e2e_cross_learner', null),
    'learner B cannot append feedback to learner A item',
);

$inputA = $snapshotBuilder->build($studentA, $consent->allowedScopes($studentA));
$visibleRule = (new RuleRecommendationEngine())->generate(
    $inputA,
    new RecommendationContext($consent->allowedScopes($studentA), '00000000-0000-4000-8000-000000009004', 'e2e-visible-a-v1', $studentA),
);
$modelConfig = RecommendationConfig::fromEnvironment([
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => 'fake',
    'TALENTHUB_AI_MODEL' => 'e2e-shadow-model-1',
    'TALENTHUB_AI_API_URL' => 'https://ai.example.test/v1/recommendations',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'ai.example.test',
    'TALENTHUB_AI_API_KEY' => 'test-only-no-network-key',
    'TALENTHUB_AI_TIMEOUT_SECONDS' => '2',
    'TALENTHUB_AI_MAX_ATTEMPTS' => '1',
    'TALENTHUB_AI_PER_STUDENT_LIMIT' => '2',
    'TALENTHUB_AI_GLOBAL_LIMIT' => '4',
    'TALENTHUB_AI_SHADOW' => 'true',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'false',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '0',
]);
e2e_assert($modelConfig->visiblePercent() === 0 && $modelConfig->shadowEnabled(), 'E2E keeps all model output shadow-only');
$successProvider = new FakeRecommendationProvider(ProviderResponse::success([[
    'item_type' => 'strength',
    'title' => 'Phát triển nền tảng IoT',
    'summary' => 'Tiếp tục thực hành dựa trên bằng chứng kỹ năng đã xác minh.',
    'priority' => 25,
    'confidence_band' => 'medium',
    'action' => ['type' => 'develop_skill', 'skill_code' => 'iot'],
    'evidence_ref_ids' => ['evidence-001'],
]]));
$modelEngine = new ModelRecommendationEngine(
    $successProvider,
    new RuleRecommendationEngine(),
    new PromptRegistry(),
    new RecommendationRateLimiter(2, 4, 60, static fn (): int => 1_000),
    $modelConfig,
    new RecommendationResultValidator(),
);
$shadow = (new ShadowRunService($repository, $modelEngine, new RecommendationEvaluator()))->run(
    $studentA,
    $inputA,
    new RecommendationContext($consent->allowedScopes($studentA), '00000000-0000-4000-8000-000000009005', 'e2e-shadow-visible-a-v1', $studentA),
    $visibleRule,
);
e2e_assert($shadow['visible_result']->engineType() === 'rule', 'fake model shadow never replaces the rule-visible result');
e2e_assert($shadow['shadow_result']->engineType() === 'model' && $shadow['evaluation']['valid'] === true, 'fake model shadow is validated against canonical snapshot evidence');
e2e_assert(count($successProvider->requests()) === 1, 'shadow invokes only the injected fake provider');

$failureEngine = new ModelRecommendationEngine(
    new FakeRecommendationProvider(ProviderResponse::failure('provider_unavailable')),
    new RuleRecommendationEngine(),
    new PromptRegistry(),
    new RecommendationRateLimiter(2, 4, 60, static fn (): int => 1_000),
    $modelConfig,
    new RecommendationResultValidator(),
);
$fallback = $failureEngine->generate(
    $inputA,
    new RecommendationContext($consent->allowedScopes($studentA), '00000000-0000-4000-8000-000000009006', 'e2e-provider-fallback-a-v1', $studentA),
);
e2e_assert($fallback->engineType() === 'rule' && $fallback->fallbackReason() === 'provider_unavailable', 'provider failure keeps the deterministic rule fallback');

$crossEvidence = $pdo->prepare(
    'SELECT COUNT(*) FROM learner_recommendation_evidence AS evidence INNER JOIN learner_recommendation_items AS items ON items.id = evidence.itemId INNER JOIN learner_recommendation_runs AS runs ON runs.id = items.runId WHERE runs.studentId = :studentId AND evidence.sourceId IN (:otherSkill, :otherResult, :otherExperience, :otherEvaluation)',
);
$crossEvidence->execute([
    'studentId' => $studentA,
    'otherSkill' => '00000000-0000-4000-8000-000000000203',
    'otherResult' => '00000000-0000-4000-8000-000000000252',
    'otherExperience' => '00000000-0000-4000-8000-000000000152',
    'otherEvaluation' => '00000000-0000-4000-8000-000000000162',
]);
e2e_assert((int) $crossEvidence->fetchColumn() === 0, 'learner A persisted evidence never references learner B sources');

echo 'learner_ai_end_to_end_mysql_test: OK' . PHP_EOL;
