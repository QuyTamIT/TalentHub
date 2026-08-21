<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

function runner_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$runnerFile = $projectRoot . '/Database/seeds/Demo/CompleteAiDemoAiRunner.php';
runner_assert(is_file($runnerFile), 'Complete AI demo AI runner exists');

$cliFile = $projectRoot . '/bin/run-demo-ai.php';
runner_assert(is_file($cliFile), 'Complete AI demo AI runner CLI exists');

require_once $projectRoot . '/bin/bootstrap.php';
require_once $projectRoot . '/app/learner/data/bootstrap.php';
require_once $projectRoot . '/app/learner/ai/bootstrap.php';
require_once $projectRoot . '/Database/seeds/Demo/CompleteAiDemoDataset.php';
require_once $projectRoot . '/Database/seeds/Demo/CompleteAiDemoSeeder.php';
require_once $runnerFile;

use TalentHub\Database\Connection;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoAiRunner;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoDataset;
use TalentHub\Database\Seeds\Demo\CompleteAiDemoSeeder;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Model\ModelRecommendationEngine;
use TalentHub\Learner\Ai\Model\PromptRegistry;
use TalentHub\Learner\Ai\Provider\FakeRecommendationProvider;
use TalentHub\Learner\Ai\Provider\ProviderResponse;
use TalentHub\Learner\Ai\RateLimit\RecommendationRateLimiter;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

/** @return array{code:int,output:string} */
function runner_command(string $command): array
{
    $output = [];
    $code = 0;
    exec($command, $output, $code);
    return ['code' => $code, 'output' => implode("\n", $output)];
}

/** @param array<string,string> $overrides @return array{code:int,output:string} */
function runner_cli_guard_probe(string $php, string $cliFile, string $projectRoot, array $overrides): array
{
    $environment = getenv();
    runner_assert(is_array($environment), 'process environment is available for CLI guard probe');
    $environment = array_merge($environment, [
        'APP_ENV' => 'test',
        'DB_HOST' => 'guard-must-not-connect.invalid',
        'DB_PORT' => '3306',
        'DB_DATABASE' => 'guard_must_not_connect',
        'DB_USERNAME' => 'guard_must_not_connect',
        'DB_PASSWORD' => 'guard-secret-never-printed',
        'TALENTHUB_AI_ENABLED' => 'true',
        'TALENTHUB_AI_PROVIDER' => 'guard-provider',
        'TALENTHUB_AI_MODEL' => 'guard-model',
        'TALENTHUB_AI_API_URL' => 'http://127.0.0.1:20128/v1',
        'TALENTHUB_AI_API_KEY' => 'guard-api-secret-never-printed',
        'TALENTHUB_AI_ALLOWED_HOSTS' => '127.0.0.1',
        'TALENTHUB_AI_SHADOW' => 'true',
        'TALENTHUB_AI_VISIBLE_PERCENT' => '0',
    ], $overrides);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([$php, $cliFile], $descriptors, $pipes, $projectRoot, $environment);
    runner_assert(is_resource($process), 'runner CLI guard subprocess starts');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    $output = trim((is_string($stdout) ? $stdout : '') . "\n" . (is_string($stderr) ? $stderr : ''));
    runner_assert(!str_contains($output, 'guard-api-secret-never-printed'), 'runner guard never prints API key');
    runner_assert(!str_contains($output, 'guard-secret-never-printed'), 'runner guard never prints database secret');
    return ['code' => $code, 'output' => $output];
}

function runner_assert_schema_dropped(PDO $adminPdo, string $schema): void
{
    $statement = $adminPdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name=:schema');
    $statement->execute(['schema' => $schema]);
    runner_assert((int) $statement->fetchColumn() === 0, 'disposable runner schema was dropped');
}

$cliSource = file_get_contents($cliFile);
runner_assert(is_string($cliSource) && str_contains($cliSource, "app/learner/data/bootstrap.php"), 'runner CLI loads learner data bootstrap');
runner_assert(is_string($cliSource) && str_contains($cliSource, "app/learner/ai/bootstrap.php"), 'runner CLI loads AI bootstrap');
runner_assert(is_string($cliSource) && str_contains($cliSource, 'Environment::appEnvironment()'), 'runner CLI uses the validated application environment');
runner_assert(is_string($cliSource) && str_contains($cliSource, 'HttpRecommendationProvider'), 'runner CLI constructs the configured HTTP provider');
runner_assert(is_string($cliSource) && str_contains($cliSource, 'CompleteAiDemoDataset::heroStudentIds()'), 'runner CLI selects the exact demo heroes');
runner_assert(is_string($cliSource) && !str_contains($cliSource, 'apiKey()'), 'runner CLI never reads the provider key for output');
runner_assert(is_string($cliSource) && !str_contains($cliSource, 'json_encode'), 'runner CLI prints scalar output rather than payload JSON');
foreach (['provider_unavailable', 'provider_fallback', 'shadow_invalid'] as $safeCode) {
    runner_assert(str_contains($cliSource, $safeCode), 'runner CLI maps safe failure code ' . $safeCode);
}

$php = 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
foreach ([
    'production' => [['APP_ENV' => 'production'], 'status=environment_forbidden'],
    'disabled' => [['TALENTHUB_AI_ENABLED' => 'false'], 'status=configuration_invalid'],
    'shadow_disabled' => [['TALENTHUB_AI_SHADOW' => 'false'], 'status=configuration_invalid'],
    'visible_enabled' => [['TALENTHUB_AI_VISIBLE_PERCENT' => '1'], 'status=configuration_invalid'],
] as $case => [$environment, $expectedOutput]) {
    $probe = runner_cli_guard_probe($php, $cliFile, $projectRoot, $environment);
    runner_assert($probe['code'] !== 0, 'runner CLI guard rejects ' . $case);
    runner_assert($probe['output'] === $expectedOutput, 'runner CLI guard returns redacted code for ' . $case . ': ' . $probe['output']);
}

$schema = 'talenthub_complete_demo_test_runner_' . bin2hex(random_bytes(6));
runner_assert(
    preg_match('/^talenthub_complete_demo_test_[a-z0-9_]+$/', $schema) === 1,
    'runner schema must match ^talenthub_complete_demo_test_[a-z0-9_]+$',
);
runner_assert($schema !== 'talenthub_local', 'refusing to run runner integration on talenthub_local');

$adminHost = (string) (getenv('COMPLETE_AI_DEMO_TEST_ADMIN_HOST') ?: '127.0.0.1');
$adminPort = (string) (getenv('COMPLETE_AI_DEMO_TEST_ADMIN_PORT') ?: '3306');
$adminUsername = (string) (getenv('COMPLETE_AI_DEMO_TEST_ADMIN_USERNAME') ?: 'root');
$adminPassword = (string) (getenv('COMPLETE_AI_DEMO_TEST_ADMIN_PASSWORD') ?: '');
runner_assert(
    filter_var($adminPort, FILTER_VALIDATE_INT) !== false && (int) $adminPort > 0,
    'COMPLETE_AI_DEMO_TEST_ADMIN_PORT must be a positive integer',
);

foreach ([
    'APP_ENV' => 'test',
    'DB_DATABASE' => $schema,
    'DB_HOST' => $adminHost,
    'DB_PORT' => $adminPort,
    'DB_USERNAME' => $adminUsername,
    'DB_PASSWORD' => $adminPassword,
] as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$databaseConfig = [
    'driver' => 'mysql',
    'host' => $adminHost,
    'port' => (int) $adminPort,
    'database' => $schema,
    'username' => $adminUsername,
    'password' => $adminPassword,
    'charset' => 'utf8mb4',
    'connectTimeout' => 5,
    'persistent' => false,
];

$pdo = null;
$adminPdo = null;
$primaryFailure = null;
$cleanupFailure = null;

try {
    $adminPdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $adminHost, (int) $adminPort),
        $adminUsername,
        $adminPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $adminPdo->exec('CREATE DATABASE `' . str_replace('`', '``', $schema) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $pdo = (new Connection($databaseConfig))->connect();
    $pdo->exec("SET time_zone = '+00:00'");
    runner_assert((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === $schema, 'runner PDO is pinned to the disposable schema');

    $migration = runner_command(escapeshellarg($php) . ' ' . escapeshellarg($projectRoot . '/bin/migrate.php') . ' migrate --step=12 2>&1');
    runner_assert($migration['code'] === 0, 'base migrations succeed: ' . $migration['output']);

    $learnerMigrations = new TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner(
        $pdo,
        $projectRoot . '/Database/migrations/learner',
        new TalentHub\Learner\Data\Database\SchemaInspector($pdo, $schema),
    );
    foreach ([
        '002_create_ai_input_foundation',
        '003_create_ai_input_extensions',
        '004_create_recommendation_store',
    ] as $version) {
        runner_assert($learnerMigrations->migrateApproved([$version]) === [$version], 'learner migration applies: ' . $version);
    }

    $reconciliation = runner_command(escapeshellarg($php) . ' ' . escapeshellarg($projectRoot . '/bin/migrate.php') . ' migrate 2>&1');
    runner_assert($reconciliation['code'] === 0, 'reconciliation migrations succeed: ' . $reconciliation['output']);

    require_once $projectRoot . '/Database/seeds/System/RolePermissionSeeder.php';
    require_once $projectRoot . '/Database/seeds/Demo/SchoolDemoSeeder.php';
    require_once $projectRoot . '/Database/seeds/learner/AssessmentCatalogMasterSeeder.php';

    (new TalentHub\Database\Seeds\System\RolePermissionSeeder())->run($pdo);
    $password = 'CompleteDemoPass!2026';
    putenv('TALENTHUB_TEST_PASSWORD=' . $password);
    $_ENV['TALENTHUB_TEST_PASSWORD'] = $password;
    $_SERVER['TALENTHUB_TEST_PASSWORD'] = $password;
    (new TalentHub\Database\Seeds\Demo\SchoolDemoSeeder())->run($pdo, 'test', $password);
    (new TalentHub\Learner\Seeds\AssessmentCatalogMasterSeeder($pdo, $schema))->seedAll();

    $clock = new DateTimeImmutable('2024-08-20 00:00:00.000000', new DateTimeZone('UTC'));
    (new CompleteAiDemoSeeder())->run($pdo, 'test', $password, $clock);

    $modelConfig = RecommendationConfig::fromEnvironment([
        'APP_ENV' => 'test',
        'TALENTHUB_AI_ENABLED' => 'true',
        'TALENTHUB_AI_PROVIDER' => 'fake-test-provider',
        'TALENTHUB_AI_MODEL' => 'fake-test-model',
        'TALENTHUB_AI_API_URL' => 'https://fake.test/v1/recommendations',
        'TALENTHUB_AI_API_KEY' => 'test-key-never-printed',
        'TALENTHUB_AI_ALLOWED_HOSTS' => 'fake.test',
        'TALENTHUB_AI_TIMEOUT_SECONDS' => '2',
        'TALENTHUB_AI_MAX_ATTEMPTS' => '1',
        'TALENTHUB_AI_PER_STUDENT_LIMIT' => '2',
        'TALENTHUB_AI_GLOBAL_LIMIT' => '10',
        'TALENTHUB_AI_SHADOW' => 'true',
        'TALENTHUB_AI_VISIBLE_PERCENT' => '0',
    ]);
    $provider = new FakeRecommendationProvider(ProviderResponse::success([
        [
            'item_type' => 'development',
            'title' => 'Thực hành kỹ năng từ bằng chứng hiện có',
            'summary' => 'Chọn một bước phát triển cụ thể và theo dõi tiến độ.',
            'priority' => 50,
            'confidence_band' => 'medium',
            'action' => ['type' => 'develop_skill', 'skill_code' => 'communication'],
            'evidence_ref_ids' => ['evidence-001'],
        ],
    ]));
    $modelEngine = new ModelRecommendationEngine(
        $provider,
        new RuleRecommendationEngine(),
        new PromptRegistry(),
        new RecommendationRateLimiter(
            $modelConfig->perStudentLimit(),
            $modelConfig->globalLimit(),
            60,
            static fn (): int => 1_777_777_777,
        ),
        $modelConfig,
        new RecommendationResultValidator(),
    );

    $heroIds = array_values(CompleteAiDemoDataset::heroStudentIds());
    $firstReport = CompleteAiDemoAiRunner::run($pdo, $modelEngine, $heroIds, $clock);
    runner_assert(array_keys($firstReport) === $heroIds, 'runner reports exactly both supplied hero IDs');
    foreach ($firstReport as $studentId => $row) {
        runner_assert(array_keys($row) === [
            'quality_state',
            'visible_engine',
            'visible_item_count',
            'shadow_engine',
            'shadow_valid',
            'shadow_violation_codes',
        ], 'runner returns only the redacted report contract for ' . $studentId);
        runner_assert($row['quality_state'] === 'ready', 'hero passes quality gate');
        runner_assert($row['visible_engine'] === 'rule', 'visible output remains rule');
        runner_assert((int) $row['visible_item_count'] > 0, 'visible rule output contains items');
        runner_assert($row['shadow_engine'] === 'model', 'shadow model executed');
        runner_assert($row['shadow_valid'] === true, 'shadow evaluation valid');
        runner_assert($row['shadow_violation_codes'] === [], 'shadow evaluation has no violation codes');

        $profile = $pdo->prepare('SELECT COUNT(*) FROM student_profiles WHERE id=:studentId');
        $profile->execute(['studentId' => $studentId]);
        runner_assert((int) $profile->fetchColumn() === 1, 'stage 1 has a learner profile for ' . $studentId);

        $assessmentResults = $pdo->prepare('SELECT COUNT(*) FROM test_results result INNER JOIN test_attempts attempt ON attempt.id=result.attemptId WHERE attempt.studentId=:studentId AND attempt.status=\'submitted\'');
        $assessmentResults->execute(['studentId' => $studentId]);
        runner_assert((int) $assessmentResults->fetchColumn() === 4, 'stage 2 has all four assessment results for ' . $studentId);
        $hollandResult = $pdo->prepare(<<<'SQL'
SELECT result.dimensionScoresJson
FROM test_results result
JOIN test_attempts attempt ON attempt.id = result.attemptId
JOIN talent_tests test ON test.id = attempt.testId
WHERE attempt.studentId = :studentId AND test.code LIKE 'holland\_%'
LIMIT 1
SQL);
        $hollandResult->execute(['studentId' => $studentId]);
        $hollandScores = json_decode((string) $hollandResult->fetchColumn(), true, 512, JSON_THROW_ON_ERROR);
        runner_assert(is_array($hollandScores) && isset($hollandScores['A']), 'stage 2 has Holland artistic score for ' . $studentId);
        $topHollandScore = max(array_map('intval', $hollandScores));
        runner_assert((int) $hollandScores['A'] === $topHollandScore, 'Holland A is the top hero dimension for ' . $studentId);
        runner_assert(count(array_filter($hollandScores, static fn (mixed $score): bool => (int) $score === $topHollandScore)) === 1, 'Holland A is uniquely highest for ' . $studentId);

        $visibleJourney = $pdo->prepare(<<<'SQL'
SELECT items.actionJson, COUNT(evidence.id) AS evidenceCount
FROM learner_recommendation_runs runs
JOIN learner_recommendation_items items ON items.runId = runs.id
LEFT JOIN learner_recommendation_evidence evidence ON evidence.itemId = items.id
WHERE runs.studentId = :studentId
  AND runs.engineType = 'rule'
  AND runs.status = 'completed'
GROUP BY items.id, items.actionJson
ORDER BY items.id
SQL);
        $visibleJourney->execute(['studentId' => $studentId]);
        $visibleItems = $visibleJourney->fetchAll(PDO::FETCH_ASSOC);
        runner_assert($visibleItems !== [], 'stage 3 persists quality-ready recommendation items for ' . $studentId);
        $actionTypes = [];
        $registerActivityIds = [];
        $careerGroupsByAction = [];
        foreach ($visibleItems as $item) {
            runner_assert((int) $item['evidenceCount'] > 0, 'stage 3 recommendation item has evidence for ' . $studentId);
            $action = json_decode((string) $item['actionJson'], true, 512, JSON_THROW_ON_ERROR);
            if (is_array($action) && is_string($action['type'] ?? null)) {
                $actionTypes[] = $action['type'];
                if (in_array($action['type'], ['explore_career_group', 'register_activity'], true)) {
                    $careerGroupsByAction[$action['type']][] = $action['career_group'] ?? null;
                }
                if ($action['type'] === 'register_activity' && is_string($action['activity_source_id'] ?? null)) {
                    $registerActivityIds[] = $action['activity_source_id'];
                }
            }
        }
        runner_assert(in_array('explore_career_group', $actionTypes, true), 'stage 4 has a career-group recommendation for ' . $studentId);
        runner_assert(in_array('register_activity', $actionTypes, true), 'stage 5 has an open activity recommendation for ' . $studentId);
        runner_assert(($careerGroupsByAction['explore_career_group'] ?? []) !== [] && array_unique($careerGroupsByAction['explore_career_group']) === ['arts'], 'stage 4 recommendation derives from the artistic Holland group for ' . $studentId);
        runner_assert(($careerGroupsByAction['register_activity'] ?? []) !== [] && array_unique($careerGroupsByAction['register_activity']) === ['arts'], 'stage 5 recommendation derives from the artistic Holland group for ' . $studentId);
        runner_assert($registerActivityIds !== [], 'stage 5 register action includes a concrete activity source for ' . $studentId);
        foreach ($registerActivityIds as $activityId) {
            $openActivity = $pdo->prepare(<<<'SQL'
SELECT COUNT(*)
FROM activities activity
JOIN classes class ON class.schoolId = activity.schoolId
JOIN student_profiles student ON student.classId = class.id
WHERE student.id = :studentId
  AND activity.id = :activityId
  AND activity.status IN ('published', 'ongoing')
  AND activity.endAt >= :currentTime
  AND NOT EXISTS (
      SELECT 1 FROM activity_registrations registration
      WHERE registration.activityId = activity.id
        AND registration.studentId = student.id
        AND registration.status IN ('pending', 'approved', 'attended')
  )
SQL);
            $openActivity->execute([
                'studentId' => $studentId,
                'activityId' => $activityId,
                'currentTime' => $clock->format('Y-m-d H:i:s.u'),
            ]);
            runner_assert((int) $openActivity->fetchColumn() === 1, 'stage 5 action references an open same-organization unregistered activity for ' . $studentId);
        }

        $experiences = $pdo->prepare(<<<'SQL'
SELECT COUNT(*)
FROM activity_registrations registration
JOIN checkins checkin_record ON checkin_record.registrationId = registration.id AND checkin_record.status = 'confirmed'
JOIN experience_logs experience ON experience.checkinId = checkin_record.id AND experience.status = 'confirmed'
WHERE registration.studentId = :studentId AND registration.status = 'attended'
SQL);
        $experiences->execute(['studentId' => $studentId]);
        runner_assert((int) $experiences->fetchColumn() >= 2, 'stage 6 has confirmed check-ins and experiences for ' . $studentId);

        $evaluations = $pdo->prepare("SELECT COUNT(*) FROM assessments WHERE studentId=:studentId AND status='published'");
        $evaluations->execute(['studentId' => $studentId]);
        runner_assert((int) $evaluations->fetchColumn() >= 2, 'stage 7 has published educator evaluations for ' . $studentId);

        $history = $pdo->prepare(<<<'SQL'
SELECT COUNT(DISTINCT runs.id), COUNT(DISTINCT items.id), COUNT(DISTINCT evidence.id)
FROM learner_recommendation_runs runs
JOIN learner_recommendation_items items ON items.runId = runs.id
JOIN learner_recommendation_evidence evidence ON evidence.itemId = items.id
WHERE runs.studentId = :studentId AND runs.status = 'completed'
SQL);
        $history->execute(['studentId' => $studentId]);
        $historyCounts = array_map('intval', $history->fetch(PDO::FETCH_NUM));
        runner_assert($historyCounts[0] >= 2 && $historyCounts[1] >= 2 && $historyCounts[2] >= 2, 'stage 8 persists recommendation run, item, and evidence history for ' . $studentId);
    }

    $visibleRuns = (int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_runs WHERE engineType='rule' AND status='completed'")->fetchColumn();
    $shadowRuns = (int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_runs WHERE engineType='model' AND status='completed'")->fetchColumn();
    runner_assert($visibleRuns === 2, 'two persisted rule runs');
    runner_assert($shadowRuns === 2, 'two persisted shadow model runs');
    $shadowItems = (int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_items items INNER JOIN learner_recommendation_runs runs ON runs.id=items.runId WHERE runs.idempotencyKey LIKE 'shadow-%' AND runs.engineType='model' AND runs.status='completed'")->fetchColumn();
    $shadowEvidence = (int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_evidence evidence INNER JOIN learner_recommendation_items items ON items.id=evidence.itemId INNER JOIN learner_recommendation_runs runs ON runs.id=items.runId WHERE runs.idempotencyKey LIKE 'shadow-%' AND runs.engineType='model' AND runs.status='completed'")->fetchColumn();
    runner_assert($shadowItems === 2, 'completed shadow runs persist one safe item per hero');
    runner_assert($shadowEvidence === 2, 'completed shadow runs persist evidence for every safe item');

    $secondReport = CompleteAiDemoAiRunner::run($pdo, $modelEngine, $heroIds, $clock);
    runner_assert($secondReport === $firstReport, 'completed stable runs are loaded and reported consistently');
    runner_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_recommendation_runs')->fetchColumn() === 4, 'second run creates no additional recommendation runs');
    runner_assert(count($provider->requests()) === 2, 'completed stable shadow runs avoid repeated provider invocation');

    echo "complete_ai_demo_runner_test: OK\n";
} catch (Throwable $exception) {
    $primaryFailure = $exception;
} finally {
    try {
        $pdo = null;
        $cleanupPdo = $adminPdo instanceof PDO
            ? $adminPdo
            : new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $adminHost, (int) $adminPort),
                $adminUsername,
                $adminPassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        $cleanupPdo->exec('DROP DATABASE IF EXISTS `' . str_replace('`', '``', $schema) . '`');
        runner_assert_schema_dropped($cleanupPdo, $schema);
        echo 'complete_ai_demo_runner_test: schema dropped ' . $schema . PHP_EOL;
    } catch (Throwable $exception) {
        $cleanupFailure = $exception;
    }
}

if ($primaryFailure instanceof Throwable) {
    if ($cleanupFailure instanceof Throwable) {
        throw new RuntimeException(
            'Primary test failure: ' . $primaryFailure->getMessage() . '; cleanup failure: ' . $cleanupFailure->getMessage(),
            0,
            $primaryFailure,
        );
    }
    throw $primaryFailure;
}
if ($cleanupFailure instanceof Throwable) {
    throw $cleanupFailure;
}
