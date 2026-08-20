<?php

declare(strict_types=1);

/**
 * Full End-to-End Integration Test:
 * Holland Assessment -> Persist Result -> Career Group Classification -> Activity Recommendation -> Activity Registration.
 *
 * Requirements:
 * - Runs exclusively on disposable MySQL 8.4.3.
 * - talenthub_local is protected and read-only.
 * - Covers all 4 career groups (technical, business, arts, sports_academic) deterministically.
 * - Verifies cross-learner isolation and TALENTHUB_AI_VISIBLE_PERCENT=0 invariant.
 */

use TalentHub\Database\Connection;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Model\ModelRecommendationEngine;
use TalentHub\Learner\Ai\Model\PromptRegistry;
use TalentHub\Learner\Ai\Persistence\DatabaseRecommendationRepository;
use TalentHub\Learner\Ai\Provider\HttpRecommendationProvider;
use TalentHub\Learner\Ai\Quality\DataQualityGate;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rollout\RecommendationRolloutSelector;
use TalentHub\Learner\Ai\Rules\CareerGroupClassifier;
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
use TalentHub\Learner\Assessment\Service\AssessmentCatalogService;
use TalentHub\Learner\Assessment\Service\EducationBandResolver;
use TalentHub\Learner\Data\Database\DatabaseActivityRepository;
use TalentHub\Learner\Data\RepositoryFactory;
use TalentHub\Learner\Data\Service\LearnerAssessmentService;
use TalentHub\Learner\Data\Support\Uuid;
use TalentHub\Learner\Seeds\AssessmentCatalogMasterSeeder;
use TalentHub\Learner\Seeds\Staging\LearnerAiPilotSeeder;
use TalentHub\Learner\Seeds\Staging\LearnerCareerActivitySeeder;

$repositoryRoot = dirname(__DIR__);
require_once $repositoryRoot . '/bin/bootstrap.php';
require_once $repositoryRoot . '/app/learner/data/bootstrap.php';
require_once $repositoryRoot . '/app/learner/ai/bootstrap.php';
require_once $repositoryRoot . '/Database/seeds/learner/AssessmentCatalogMasterSeeder.php';
require_once $repositoryRoot . '/Database/seeds/learner/Staging/LearnerAiPilotSeeder.php';
require_once $repositoryRoot . '/Database/seeds/learner/Staging/LearnerCareerActivitySeeder.php';

function e2e_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function e2e_expect_exception(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        return;
    }
    fwrite(STDERR, "Expected exception not thrown: {$message}\n");
    exit(1);
}

function cleanupStudentData(PDO $pdo, string $studentId): void
{
    $stmt = $pdo->prepare('SELECT id FROM test_attempts WHERE studentId = :studentId');
    $stmt->execute(['studentId' => $studentId]);
    $attempts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($attempts !== []) {
        $placeholders = implode(',', array_fill(0, count($attempts), '?'));
        $pdo->prepare("DELETE FROM learner_assessment_answers WHERE attemptId IN ({$placeholders})")->execute($attempts);
        $pdo->prepare("DELETE FROM test_results WHERE attemptId IN ({$placeholders})")->execute($attempts);
        $pdo->prepare("DELETE FROM learner_assessment_attempt_metadata WHERE attemptId IN ({$placeholders})")->execute($attempts);
        $pdo->prepare("DELETE FROM test_attempts WHERE id IN ({$placeholders})")->execute($attempts);
    }
    $pdo->prepare("DELETE FROM activity_registrations WHERE studentId = :studentId AND id NOT IN (SELECT registrationId FROM checkins WHERE registrationId IS NOT NULL)")->execute(['studentId' => $studentId]);
}

echo "=== STARTING HOLLAND CAREER E2E INTEGRATION SUITE ===\n\n";

// -------------------------------------------------------------
// 0. Connect to Disposable MySQL Database & Verify Schema Guard
// -------------------------------------------------------------
$baseConfig = require $repositoryRoot . '/config/database.php';
$disposableSchema = (string) (getenv('LEARNER_MYSQL_TEST_SCHEMA') ?: 'talenthub_ai_backup_verify_004_20260816');

e2e_assert($disposableSchema !== 'talenthub_local', 'E2E test refuses to run writes on talenthub_local');
e2e_assert(preg_match('/^talenthub_ai_backup_verify_[A-Za-z0-9_]+$/', $disposableSchema) === 1, 'Schema matches approved disposable naming pattern');

$disposableConfig = $baseConfig;
$disposableConfig['database'] = $disposableSchema;
$pdo = (new Connection($disposableConfig))->connect();

$currentDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
e2e_assert($currentDb === $disposableSchema, "Connected to pinned disposable database: {$disposableSchema}");

// Ensure prerequisite seeds are present in disposable database
$pilotSeeder = new LearnerAiPilotSeeder($pdo, $disposableSchema);
$pilotResult = $pilotSeeder->seed();
e2e_assert($pilotResult['declared'] === 61, 'Pilot seeder initialized prerequisite users and profiles');

$schoolId = '00000000-0000-4000-8000-000000000010';
$teacherProfileId = '00000000-0000-4000-8000-000000000021';
[$studentA, $studentB] = LearnerAiPilotSeeder::studentIds();

$catalogSeeder = new AssessmentCatalogMasterSeeder($pdo, $disposableSchema, false);
$catalogResult = $catalogSeeder->seedAll();
e2e_assert($catalogResult['failed'] === 0, 'All 12 assessment catalogs seeded successfully');

$activitySeeder = new LearnerCareerActivitySeeder($pdo, $disposableSchema, $schoolId, $teacherProfileId);
$activityResult = $activitySeeder->seed();
e2e_assert($activityResult['declared'] === 8, '8 career activities seeded across 4 career groups');

// Wire Services
$factory = new RepositoryFactory('database', $pdo);
$bandResolver = new EducationBandResolver($pdo);
$catalogService = new AssessmentCatalogService($factory->assessment(), $bandResolver);
$assessmentService = new LearnerAssessmentService($factory->assessment(), $factory->assessmentWrite());
$activityRepo = new DatabaseActivityRepository($pdo);
$classifier = new CareerGroupClassifier();

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
$ruleEngine = new RuleRecommendationEngine();
$validator = new RecommendationResultValidator();
$mapper = new RecommendationResponseMapper();

// Wire Shadow-only Model Engine for strict TALENTHUB_AI_VISIBLE_PERCENT=0 verification
$shadowHttpCount = 0;
$mockHttpTransport = static function (string $url, array $headers, string $body, int $timeout) use (&$shadowHttpCount): array {
    $shadowHttpCount++;
    return [
        'status' => 200,
        'headers' => ['content-type' => 'application/json'],
        'body' => json_encode(['items' => []]),
    ];
};

$configEnv = [
    'TALENTHUB_AI_ENABLED' => 'true',
    'TALENTHUB_AI_PROVIDER' => '9router_gemini',
    'TALENTHUB_AI_MODEL' => 'gemini-1.5-flash-test',
    'TALENTHUB_AI_API_URL' => 'https://gateway.9router.test/v1/chat/completions',
    'TALENTHUB_AI_ALLOWED_HOSTS' => 'gateway.9router.test',
    'TALENTHUB_AI_API_KEY' => 'test-e2e-shadow-key',
    'TALENTHUB_AI_SHADOW' => 'true',
    'TALENTHUB_AI_SHADOW_GATE_APPROVED' => 'true',
    'TALENTHUB_AI_VISIBLE_PERCENT' => '0',
];
$aiConfig = RecommendationConfig::fromEnvironment($configEnv);
$modelEngine = new ModelRecommendationEngine(
    new HttpRecommendationProvider($aiConfig, $mockHttpTransport),
    $ruleEngine,
    new PromptRegistry(),
    new RecommendationRateLimiter(10, 100, 60, static fn (): int => 1000),
    $aiConfig,
    $validator,
);
$rollout = new RecommendationRolloutSelector();

$recService = new RecommendationService(
    $repository,
    $ruleEngine,
    $validator,
    $mapper,
    static fn (string $id): bool => hash_equals($studentA, $id) || hash_equals($studentB, $id),
    static fn (string $id): array => $consent->allowedScopes($id),
    static fn (string $id, array $scopes) => $snapshotBuilder->build($id, $scopes),
    static fn ($input) => (new DataQualityGate())->evaluate($input),
    static fn ($input): bool => true,
    $modelEngine,
    $aiConfig,
    $rollout,
);

// -------------------------------------------------------------
// 1. Learner Discovery by Education Band
// -------------------------------------------------------------
echo "--- Step 1: Learner Discovery by Education Band ---\n";

// A. Check student grade resolution: Student A is grade 11 -> resolves to 'high'
$studentCatalog = $catalogService->catalog($studentA, null);
e2e_assert(($studentCatalog['education_band'] ?? '') === 'high', "Student A authoritative band resolves to high");
e2e_assert(isset($studentCatalog['assessments']) && count($studentCatalog['assessments']) >= 4, "Student catalog contains all published assessment types");

// B. Check catalog discovery across all 3 education bands
$assessmentRepo = $factory->assessment();
foreach (['middle', 'high', 'college'] as $band) {
    $publishedAssessments = $assessmentRepo->publishedCatalog($studentA, $band);
    e2e_assert(count($publishedAssessments) >= 4, "Published catalog contains all assessment types for band {$band}");

    $hollandCatalogItem = null;
    foreach ($publishedAssessments as $assessment) {
        if (($assessment['code'] ?? '') === 'holland') {
            $hollandCatalogItem = $assessment;
            break;
        }
    }
    e2e_assert($hollandCatalogItem !== null, "Holland assessment item found in band {$band}");
    e2e_assert(($hollandCatalogItem['education_band'] ?? '') === $band, "Holland catalog education band matches {$band}");
    e2e_assert(($hollandCatalogItem['status'] ?? '') === 'published', "Holland catalog status is published in band {$band}");
    e2e_assert(($hollandCatalogItem['question_count'] ?? 0) === 30, "Holland catalog question count is 30 in band {$band}");

    $detail = $assessmentRepo->publishedAssessment('holland', $band);
    e2e_assert($detail !== null, "Published assessment detail retrieved for holland in band {$band}");
    e2e_assert(($detail['code'] ?? '') === 'holland_' . $band, "Holland banded code is holland_{$band}");
    e2e_assert(($detail['type'] ?? '') === 'holland', "Holland test type is holland");
    $questions = $assessmentRepo->questionsForVersion($detail['version_id']);
    e2e_assert(count($questions) === 30, "Holland catalog for band {$band} has exactly 30 questions");
}

echo "Step 1 PASS\n\n";

// -------------------------------------------------------------
// 2. Deterministic Testing for All 4 Career Groups
// -------------------------------------------------------------
echo "--- Step 2: 4 Deterministic Career Group E2E Flows ---\n";

$scenarios = [
    'technical' => [
        'band' => 'high',
        'weights' => ['R' => 5, 'I' => 5, 'A' => 1, 'S' => 1, 'E' => 1, 'C' => 1],
        'expected_group' => 'technical',
        'expected_group_label' => 'Kỹ thuật',
        'expected_activity_ids' => [
            '00000000-0000-4000-8000-000000000301', // CLB Sáng tạo Robot & IoT
            '00000000-0000-4000-8000-000000000302', // Workshop Lập trình Python Ứng dụng
        ],
    ],
    'business' => [
        'band' => 'high',
        'weights' => ['R' => 1, 'I' => 1, 'A' => 1, 'S' => 1, 'E' => 5, 'C' => 1],
        'expected_group' => 'business',
        'expected_group_label' => 'Kinh doanh',
        'expected_activity_ids' => [
            '00000000-0000-4000-8000-000000000303', // CLB Nhà lãnh đạo & Khởi nghiệp Trẻ
            '00000000-0000-4000-8000-000000000304', // Dự án Mô phỏng Kinh doanh & Tài chính
        ],
    ],
    'arts' => [
        'band' => 'high',
        'weights' => ['R' => 1, 'I' => 1, 'A' => 5, 'S' => 1, 'E' => 1, 'C' => 1],
        'expected_group' => 'arts',
        'expected_group_label' => 'Nghệ thuật',
        'expected_activity_ids' => [
            '00000000-0000-4000-8000-000000000305', // CLB Mỹ thuật Sáng tạo & Thiết kế
            '00000000-0000-4000-8000-000000000306', // Workshop Kể chuyện Thị giác
        ],
    ],
    'sports_academic' => [
        'band' => 'high',
        'weights' => ['R' => 1, 'I' => 1, 'A' => 1, 'S' => 5, 'E' => 1, 'C' => 5],
        'expected_group' => 'sports_academic',
        'expected_group_label' => 'Thể thao & Học thuật',
        'expected_activity_ids' => [
            '00000000-0000-4000-8000-000000000307', // CLB Thể thao & Rèn luyện Thể chất
            '00000000-0000-4000-8000-000000000308', // Dự án Nghiên cứu Học thuật & Tranh biện
        ],
    ],
];

foreach ($scenarios as $groupKey => $scenario) {
    echo "  >> Testing Career Group Flow: {$groupKey}...\n";

    // Clean up previous attempts and registrations for student A so each scenario runs cleanly
    cleanupStudentData($pdo, $studentA);

    // A. Start or resume attempt
    $attempt = $assessmentService->startOrResume($studentA, 'holland', $scenario['band']);
    $attemptId = (string) $attempt['id'];
    e2e_assert(Uuid::isValid($attemptId), "Valid attempt UUID created for {$groupKey}");
    e2e_assert(($attempt['status'] ?? '') === 'in_progress', "Attempt status is in_progress for {$groupKey}");

    // Retrieve questions for this version
    $attemptWithQuestions = $assessmentService->ownedAttemptWithQuestions($studentA, $attemptId);
    $questions = $attemptWithQuestions['questions'];
    e2e_assert(count($questions) === 30, "Attempt has 30 questions for {$groupKey}");

    // B. Save answers for all questions (accounting for reverse-scored questions)
    foreach ($questions as $question) {
        $rawDim = (string) ($question['dimension_code'] ?? 'R');
        $dim = strtoupper(substr(trim($rawDim), 0, 1));
        $isReversed = str_contains($rawDim, ':-');
        $isHigh = ($scenario['weights'][$dim] ?? 1) >= 4;
        $score = $isHigh ? ($isReversed ? 1 : 5) : ($isReversed ? 5 : 1);

        $saved = $assessmentService->saveAnswer($studentA, $attemptId, (string) $question['id'], $score);
        e2e_assert(isset($saved['answers'][(string) $question['id']]), "Answer saved for question {$question['id']}");
    }

    // C. Submit Attempt and verify Persistence
    $submitResult = $assessmentService->submit($studentA, $attemptId);
    e2e_assert(!empty($submitResult['result_code']), "Submission result code returned for {$groupKey}");
    e2e_assert(isset($submitResult['dimension_scores']), "Dimension scores payload returned for {$groupKey}");

    $resultCode = (string) ($submitResult['result_code'] ?? '');
    $dimensionScores = $submitResult['dimension_scores'] ?? [];
    e2e_assert($dimensionScores !== [], "Dimension scores RIASEC returned for {$groupKey}");

    // Verify persisted rows in database
    $attemptStmt = $pdo->prepare('SELECT status FROM test_attempts WHERE id = :id AND studentId = :studentId');
    $attemptStmt->execute(['id' => $attemptId, 'studentId' => $studentA]);
    e2e_assert($attemptStmt->fetchColumn() === 'submitted', "test_attempts row updated to submitted in DB for {$groupKey}");

    $metaStmt = $pdo->prepare('SELECT status, submittedAt FROM learner_assessment_attempt_metadata WHERE attemptId = :id');
    $metaStmt->execute(['id' => $attemptId]);
    $metaRow = $metaStmt->fetch(PDO::FETCH_ASSOC);
    e2e_assert(($metaRow['status'] ?? '') === 'submitted' && !empty($metaRow['submittedAt']), "metadata marked submitted with timestamp for {$groupKey}");

    $resultStmt = $pdo->prepare('SELECT resultCode, dimensionScoresJson FROM test_results WHERE attemptId = :id');
    $resultStmt->execute(['id' => $attemptId]);
    $resultRow = $resultStmt->fetch(PDO::FETCH_ASSOC);
    e2e_assert($resultRow !== false, "test_results row created in DB for {$groupKey}");
    $persistedScores = json_decode((string) $resultRow['dimensionScoresJson'], true);
    e2e_assert(is_array($persistedScores) && count($persistedScores) === 6, "All 6 RIASEC scores persisted in JSON format for {$groupKey}");

    // D. Classify using CareerGroupClassifier
    $classifiedGroups = $classifier->classify($persistedScores, 'holland');
    e2e_assert(count($classifiedGroups) === 4, "4 ranked career groups classified for {$groupKey}");
    $topGroup = $classifiedGroups[0];
    e2e_assert($topGroup['code'] === $scenario['expected_group'], "Top classified career group is {$scenario['expected_group']}");
    e2e_assert($topGroup['score'] === 100.0, "Top group score is 100.0 for {$groupKey}");

    // E. Recommendation Generation & Activity Matching
    $reqId = sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff), mt_rand(0x8000, 0xbfff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    $idempKey = 'e2e-holland-' . $groupKey . '-' . bin2hex(random_bytes(8));

    $recResponse = $recService->generate($studentA, $reqId, $idempKey);
    e2e_assert($recResponse['state'] === 'ready_rule', "Recommendation service returns ready_rule for {$groupKey}");
    e2e_assert($recResponse['engine_type'] === 'rule', "engine_type is strictly rule for {$groupKey}");

    $careerStrengthItem = null;
    $matchingActivityItems = [];
    foreach ($recResponse['items'] as $item) {
        $action = $item['action'] ?? [];
        if (($action['type'] ?? '') === 'explore_career_group' && ($action['career_group'] ?? '') === $scenario['expected_group']) {
            $careerStrengthItem = $item;
        }
        if (($action['type'] ?? '') === 'register_activity' && ($action['career_group'] ?? '') === $scenario['expected_group']) {
            $matchingActivityItems[] = $item;
        }
    }

    e2e_assert($careerStrengthItem !== null, "explore_career_group item present in recommendations for {$groupKey}");
    e2e_assert(str_contains($careerStrengthItem['title'], $scenario['expected_group_label']), "Strength title references {$scenario['expected_group_label']}");

    e2e_assert(count($matchingActivityItems) >= 2, "At least 2 matching activities recommended for {$groupKey}");
    $recommendedActivityIds = array_map(static fn ($it) => (string) ($it['action']['activity_source_id'] ?? ''), $matchingActivityItems);

    foreach ($scenario['expected_activity_ids'] as $expectedActivityId) {
        e2e_assert(in_array($expectedActivityId, $recommendedActivityIds, true), "Recommended activity ID {$expectedActivityId} found in {$groupKey}");
        // Verify activity exists and is published in activities table
        $act = $activityRepo->findById($expectedActivityId);
        e2e_assert($act !== null, "Activity {$expectedActivityId} exists in database");
        e2e_assert($act['status'] === 'published', "Activity {$expectedActivityId} is in published status");
    }
}

echo "Step 2 PASS (All 4 Career Groups verified)\n\n";

// -------------------------------------------------------------
// 3. Activity Registration Flow & Real-Time Snapshot Exclusion
// -------------------------------------------------------------
echo "--- Step 3: Activity Registration Flow & Snapshot Exclusion ---\n";

// Student A registers for the first technical activity: 00000000-0000-4000-8000-000000000301 (Robot IoT Club)
$targetActivityId = '00000000-0000-4000-8000-000000000301';
$registrationId = '00000000-0000-4000-8000-000000000999';

// 1. Verify activity is initially in open opportunities
$oppSource = new DatabaseOpportunitySource($pdo);
$initialOpps = $oppSource->forStudent($studentA);
$initialOppIds = array_column($initialOpps, 'opportunity_id');
e2e_assert(in_array($targetActivityId, $initialOppIds, true), 'Target activity is initially available in open opportunities');

// 2. Perform registration
$regStmt = $pdo->prepare(
    "INSERT INTO activity_registrations (id, activityId, studentId, status, registeredAt, updatedAt)
     VALUES (:id, :activityId, :studentId, 'pending', NOW(6), NOW(6))"
);
$regStmt->execute([
    'id' => $registrationId,
    'activityId' => $targetActivityId,
    'studentId' => $studentA,
]);

// 3. Verify registration is recorded in activity_registrations
$checkReg = $activityRepo->registrationsFor($studentA);
$registeredActivityIds = array_column($checkReg, 'activity_id');
e2e_assert(in_array($targetActivityId, $registeredActivityIds, true), 'Registration successfully persisted for student A');

// 4. Verify DatabaseOpportunitySource now excludes the registered activity
$updatedOpps = $oppSource->forStudent($studentA);
$updatedOppIds = array_column($updatedOpps, 'opportunity_id');
e2e_assert(!in_array($targetActivityId, $updatedOppIds, true), 'Registered activity is dynamically excluded from open opportunities');

// 5. Verify the other activity (00000000-0000-4000-8000-000000000302) remains available
e2e_assert(in_array('00000000-0000-4000-8000-000000000302', $updatedOppIds, true), 'Unregistered activity remains available in opportunities');

echo "Step 3 PASS\n\n";

// -------------------------------------------------------------
// 4. Cross-Learner Isolation & Authorization
// -------------------------------------------------------------
echo "--- Step 4: Cross-Learner Isolation ---\n";

cleanupStudentData($pdo, $studentA);
cleanupStudentData($pdo, $studentB);

// Create an attempt for Student A
$attemptA = $assessmentService->startOrResume($studentA, 'holland', 'high');
$attemptIdA = (string) $attemptA['id'];

// Student B must NOT be able to view Student A's attempt
e2e_expect_exception(static function () use ($assessmentService, $studentB, $attemptIdA): void {
    $assessmentService->ownedAttempt($studentB, $attemptIdA);
}, 'Student B cannot view Student A attempt');

// Student B must NOT be able to save answers to Student A's attempt
e2e_expect_exception(static function () use ($assessmentService, $studentB, $attemptIdA): void {
    $assessmentService->saveAnswer($studentB, $attemptIdA, 'some-question-id', 5);
}, 'Student B cannot modify Student A attempt answers');

// Student B must NOT be able to submit Student A's attempt
e2e_expect_exception(static function () use ($assessmentService, $studentB, $attemptIdA): void {
    $assessmentService->submit($studentB, $attemptIdA);
}, 'Student B cannot submit Student A attempt');

// Student B does NOT see Student A's activity registrations
$studentBRegistrations = $activityRepo->registrationsFor($studentB);
$studentBRegActivityIds = array_column($studentBRegistrations, 'activity_id');
e2e_assert(!in_array($targetActivityId, $studentBRegActivityIds, true), 'Student B registration list is isolated from Student A');

echo "Step 4 PASS\n\n";

// -------------------------------------------------------------
// 5. Invariant: TALENTHUB_AI_VISIBLE_PERCENT=0
// -------------------------------------------------------------
echo "--- Step 5: TALENTHUB_AI_VISIBLE_PERCENT=0 Invariant ---\n";

// Answer and submit attemptA for Student A so student profile is complete (Technical weighting)
$attemptWithQuestions = $assessmentService->ownedAttemptWithQuestions($studentA, $attemptIdA);
foreach ($attemptWithQuestions['questions'] as $q) {
    $rawDim = (string) ($q['dimension_code'] ?? 'R');
    $dim = strtoupper(substr(trim($rawDim), 0, 1));
    $isReversed = str_contains($rawDim, ':-');
    $isHigh = in_array($dim, ['R', 'I'], true);
    $score = $isHigh ? ($isReversed ? 1 : 5) : ($isReversed ? 5 : 1);
    $assessmentService->saveAnswer($studentA, $attemptIdA, (string) $q['id'], $score);
}
$assessmentService->submit($studentA, $attemptIdA);

// A. Ready Profile: Outward result is strictly rule engine
$invReqA = sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff), mt_rand(0x8000, 0xbfff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
$invIdempA = 'idemp-inv-a-' . bin2hex(random_bytes(8));
$finalRecA = $recService->generate($studentA, $invReqA, $invIdempA);
e2e_assert(($finalRecA['state'] ?? '') === 'ready_rule', 'Recommendation state is ready_rule');
e2e_assert(($finalRecA['engine_type'] ?? '') === 'rule', 'Outward engine_type is strictly rule');
e2e_assert(empty($finalRecA['provider']), 'Outward provider is null');
e2e_assert(empty($finalRecA['model_version']), 'Outward model_version is null');

$latestA = $recService->latest($studentA);
e2e_assert($latestA !== null, 'Latest run exists');
e2e_assert(($latestA['engine_type'] ?? '') === 'rule', 'Latest run engine_type is strictly rule');

// B. Incomplete Profile (Student B): Outward result is insufficient_data
$invReqB = sprintf('%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff), mt_rand(0x8000, 0xbfff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
$invIdempB = 'idemp-inv-b-' . bin2hex(random_bytes(8));
$recB = $recService->generate($studentB, $invReqB, $invIdempB);
e2e_assert(($recB['state'] ?? '') === 'insufficient_data', 'Student B without assessment returns insufficient_data');
e2e_assert(empty($recB['items']), 'Student B items list is empty');
e2e_assert(!empty($recB['missing_categories']) || !empty($recB['completion_actions']), 'Student B returns missing categories / completion actions');

echo "Step 5 PASS\n\n";

// Cleanup test data and restore baseline pilot seed state in disposable database
cleanupStudentData($pdo, $studentA);
cleanupStudentData($pdo, $studentB);
$pilotSeeder->seed();

echo "=== ALL E2E SCENARIOS COMPLETED SUCCESSFULLY ===\n";
echo "learner_career_group_full_e2e_integration_test: OK\n";
