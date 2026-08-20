<?php

declare(strict_types=1);

use TalentHub\Database\Connection;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
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
use TalentHub\Learner\Seeds\Staging\LearnerCareerActivitySeeder;

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/bin/bootstrap.php';
require_once $repositoryRoot . '/app/learner/data/bootstrap.php';
require_once $repositoryRoot . '/app/learner/ai/bootstrap.php';
require_once $repositoryRoot . '/Database/seeds/learner/Staging/LearnerAiPilotSeeder.php';
require_once $repositoryRoot . '/Database/seeds/learner/Staging/LearnerCareerActivitySeeder.php';

function mysql_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function mysql_test_expect_exception(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        return;
    }
    fwrite(STDERR, "Expected exception not thrown: {$message}\n");
    exit(1);
}

$baseConfig = require $repositoryRoot . '/config/database.php';
$disposableSchema = (string) (getenv('LEARNER_MYSQL_TEST_SCHEMA') ?: 'talenthub_ai_backup_verify_004_20260816');

// Connect directly to disposable database
$disposableConfig = $baseConfig;
$disposableConfig['database'] = $disposableSchema;
$pdo = (new Connection($disposableConfig))->connect();

mysql_test_assert((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $disposableSchema, 'connected to disposable verification database');

// Run base pilot seed to ensure prerequisite parent rows exist
$pilotSeeder = new LearnerAiPilotSeeder($pdo, $disposableSchema);
$pilotResult = $pilotSeeder->seed();
mysql_test_assert($pilotResult['declared'] === 61, 'pilot seeder initialized fixture');

$schoolId = '00000000-0000-4000-8000-000000000010';
$teacherProfileId = '00000000-0000-4000-8000-000000000021';
[$studentA, $studentB] = LearnerAiPilotSeeder::studentIds();

// -------------------------------------------------------------
// 1. LearnerCareerActivitySeeder Tests on MySQL 8.4
// -------------------------------------------------------------
$activitySeeder = new LearnerCareerActivitySeeder($pdo, $disposableSchema, $schoolId, $teacherProfileId);

// Test talenthub_local rejection
mysql_test_expect_exception(static function () use ($pdo, $schoolId, $teacherProfileId): void {
    (new LearnerCareerActivitySeeder($pdo, 'talenthub_local', $schoolId, $teacherProfileId))->seed();
}, 'refuses talenthub_local on MySQL');

// First run / Idempotent run
$firstRun = $activitySeeder->seed();
mysql_test_assert($firstRun['declared'] === 8, 'declares 8 activities');
mysql_test_assert($firstRun['inserted'] + $firstRun['existing'] === 8, 'first run completed successfully');

// Second run (idempotent no-op)
$secondRun = $activitySeeder->seed();
mysql_test_assert($secondRun['inserted'] === 0 && $secondRun['existing'] === 8, 'second run is idempotent no-op');

// Conflict check: update one row title
$pdo->exec("UPDATE activities SET title = 'Altered Title For Conflict Test' WHERE id = '00000000-0000-4000-8000-000000000301'");
mysql_test_expect_exception(static function () use ($activitySeeder): void {
    $activitySeeder->seed();
}, 'content conflict throws exception and fails closed');
// Restore original title and updatedAt
$pdo->exec("UPDATE activities SET title = 'CLB Sáng tạo Robot & IoT', updatedAt = '2026-08-18 00:00:00.000000' WHERE id = '00000000-0000-4000-8000-000000000301'");
$restoredRun = $activitySeeder->seed();
mysql_test_assert($restoredRun['existing'] === 8, 'restored seeder runs smoothly');

// -------------------------------------------------------------
// 2. Full Flow: Snapshot -> Rule Engine -> Real Activity Action -> API Response
// -------------------------------------------------------------
$consent = new ConsentPolicy(new DatabaseConsentSource($pdo));
$snapshotBuilder = new RecommendationSnapshotBuilder(
    new DatabaseStudentProfileSource($pdo),
    new DatabaseSkillSource($pdo),
    new DatabaseAssessmentSource($pdo),
    new DatabaseActivityExperienceSource($pdo),
    new DatabasePublishedEvaluationSource($pdo),
    new DatabaseOpportunitySource($pdo),
);
$repository = new DatabaseRecommendationRepository($pdo);
$engine = new RuleRecommendationEngine();
$validator = new RecommendationResultValidator();
$mapper = new RecommendationResponseMapper();

// Build snapshot for student A (RIA: R=82, I=76, A=64 -> top group Technical)
$allowedScopes = $consent->allowedScopes($studentA);
$inputA = $snapshotBuilder->build($studentA, $allowedScopes);

// Verify opportunities in snapshot contain the seeded activities
$opps = $inputA->payload()['opportunities'];
mysql_test_assert(count($opps) >= 8, 'snapshot opportunities include seeded career activities');

// Generate rule result
$contextA = new RecommendationContext($allowedScopes, 'mysql-e2e-req-1', 'mysql-e2e-idemp-1', $studentA);
$ruleResult = $engine->generate($inputA, $contextA);
$validator->validate($ruleResult);

$items = $ruleResult->items();
mysql_test_assert(count($items) >= 3, 'rule result produces strength and activity items');

// Find the career group strength item and activity items
$careerGroupStrengthItem = null;
$careerGroupActivityItems = [];
foreach ($items as $item) {
    if ($item->action()['type'] === 'explore_career_group') {
        $careerGroupStrengthItem = $item;
    }
    if ($item->action()['type'] === 'register_activity') {
        $careerGroupActivityItems[] = $item;
    }
}

mysql_test_assert($careerGroupStrengthItem !== null, 'career group strength item generated');
mysql_test_assert($careerGroupStrengthItem->action()['career_group'] === 'technical', 'student A has technical top career group');

mysql_test_assert(count($careerGroupActivityItems) >= 2, 'student A receives matching technical activity recommendations');
$activityIds = array_map(static fn ($item): string => (string) $item->action()['activity_source_id'], $careerGroupActivityItems);
mysql_test_assert(in_array('00000000-0000-4000-8000-000000000301', $activityIds, true), 'action contains real robot IoT club ID');
mysql_test_assert(in_array('00000000-0000-4000-8000-000000000302', $activityIds, true), 'action contains real python workshop ID');

// -------------------------------------------------------------
// 3. Service Generate and API Mapper Response Verification
// -------------------------------------------------------------
$service = new RecommendationService(
    $repository,
    $engine,
    $validator,
    $mapper,
    static fn (string $id): bool => hash_equals($studentA, $id),
    static fn (string $id): array => $consent->allowedScopes($id),
    static fn (string $id, array $scopes) => $snapshotBuilder->build($id, $scopes),
    static fn ($input) => (new DataQualityGate())->evaluate($input),
    static fn ($input): bool => true,
);

$testRequestId = sprintf(
    '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff),
    mt_rand(0x8000, 0xbfff),
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);
$testIdempotencyKey = 'mysql-service-e2e-' . microtime(true);

$apiResponse = $service->generate($studentA, $testRequestId, $testIdempotencyKey);
mysql_test_assert($apiResponse['state'] === 'ready_rule', 'service returns ready_rule state');
mysql_test_assert(count($apiResponse['items']) >= 3, 'API response contains mapped recommendation items');

$apiActivityActions = [];
foreach ($apiResponse['items'] as $item) {
    if (($item['action']['type'] ?? null) === 'register_activity') {
        $apiActivityActions[] = $item['action'];
    }
}
mysql_test_assert(count($apiActivityActions) >= 2, 'API response exposes register_activity actions with real IDs');
$apiActivityIds = array_column($apiActivityActions, 'activity_source_id');
mysql_test_assert(in_array('00000000-0000-4000-8000-000000000301', $apiActivityIds, true), 'API response contains real club UUID for frontend registration');

echo "learner_career_groups_mysql_integration_test: OK\n";
