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

    $php = 'D:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
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

    $clock = new DateTimeImmutable('2026-08-20 00:00:00.000000', new DateTimeZone('UTC'));
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
    $firstReport = CompleteAiDemoAiRunner::run($pdo, $modelEngine, $heroIds);
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
    }

    $visibleRuns = (int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_runs WHERE engineType='rule' AND status='completed'")->fetchColumn();
    $shadowRuns = (int) $pdo->query("SELECT COUNT(*) FROM learner_recommendation_runs WHERE engineType='model' AND status='completed'")->fetchColumn();
    runner_assert($visibleRuns === 2, 'two persisted rule runs');
    runner_assert($shadowRuns === 2, 'two persisted shadow model runs');

    $secondReport = CompleteAiDemoAiRunner::run($pdo, $modelEngine, $heroIds);
    runner_assert($secondReport === $firstReport, 'completed stable runs are loaded and reported consistently');
    runner_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_recommendation_runs')->fetchColumn() === 4, 'second run creates no additional recommendation runs');
    runner_assert(count($provider->requests()) === 4, 'fake provider receives both heroes on both runner invocations');

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
