<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Readiness\GitScopeGuard;
use TalentHub\Learner\Data\Readiness\LearnerMigrationRunner;
use TalentHub\Learner\Data\Readiness\PhaseRequirements;
use TalentHub\Learner\Data\Readiness\ReadinessChecker;
use TalentHub\Learner\Data\Readiness\ReadinessResult;
use TalentHub\Learner\Runtime\LearnerRuntime;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function readiness_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function readiness_expect_exception(callable $callback, string $className, string $messageFragment): void
{
    try {
        $callback();
    } catch (Throwable $exception) {
        readiness_assert($exception instanceof $className, "expected {$className}, got " . $exception::class);
        readiness_assert(str_contains($exception->getMessage(), $messageFragment), "exception contains {$messageFragment}");
        return;
    }

    readiness_assert(false, "expected {$className}");
}

$tempDir = realpath(sys_get_temp_dir());
readiness_assert($tempDir !== false, 'system temp directory must exist');
$initialFixtureDirs = glob($tempDir . DIRECTORY_SEPARATOR . 'talenthub-readiness-*') ?: [];
$initialFixtureCount = count($initialFixtureDirs);

$readinessTestFixtures = [];

function readiness_cleanup_fixture_dir(string $dir): void
{
    $tempDir = realpath(sys_get_temp_dir());
    $realDir = realpath($dir);
    if ($tempDir === false || $realDir === false) {
        return;
    }
    $prefix = $tempDir . DIRECTORY_SEPARATOR . 'talenthub-readiness-';
    if (!str_starts_with($realDir, $prefix) || strlen($realDir) <= strlen($prefix)) {
        return;
    }
    if (!is_dir($realDir)) {
        return;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        exec('cmd /c "attrib -r -s -h ' . escapeshellarg($realDir . '\\*') . ' /s /d" 2>NUL');
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $path = $item->getRealPath();
        if ($item->isDir()) {
            @chmod($path, 0777);
            @rmdir($path);
        } else {
            @chmod($path, 0666);
            @unlink($path);
        }
    }
    @chmod($realDir, 0777);
    @rmdir($realDir);

    if (is_dir($realDir) && DIRECTORY_SEPARATOR === '\\') {
        exec('cmd /c "rmdir /s /q ' . escapeshellarg($realDir) . '" 2>NUL');
    }

    readiness_assert(!is_dir($realDir), "temporary fixture directory {$realDir} must be deleted after cleanup");
}

register_shutdown_function(static function () use (&$readinessTestFixtures): void {
    foreach ($readinessTestFixtures as $fixture) {
        readiness_cleanup_fixture_dir($fixture);
    }
});

function readiness_create_git_repo_fixture(): string
{
    global $readinessTestFixtures;
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'talenthub-readiness-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        fwrite(STDERR, "Assertion failed: unable to create temporary repo fixture\n");
        exit(1);
    }
    $readinessTestFixtures[] = $root;

    $commands = [
        ['git init -q', 'initializing repository'],
        ['git config user.email readiness@example.com', 'configuring email'],
        ['git config user.name readiness', 'configuring name'],
        ['mkdir app\\learner\\data', 'creating path'],
    ];

    foreach ($commands as [$command, $label]) {
        $output = [];
        $exitCode = 0;
        exec('cmd /c "cd /d ' . $root . ' && ' . $command . '" 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            fwrite(STDERR, "Assertion failed: temporary repo fixture failed while {$label}\n" . implode("\n", $output) . "\n");
            exit(1);
        }
    }

    file_put_contents($root . DIRECTORY_SEPARATOR . 'app\\learner\\data\\bootstrap.php', 'ok');
    return $root;
}

$ready = new ReadinessResult(0);
$ready->addPass('source', 'Mock mode is explicit.');
readiness_assert($ready->status() === 'READY', 'passing result is READY');
readiness_assert($ready->exitCode() === 0, 'READY has exit code 0');
readiness_assert($ready->toArray()['passes'][0]['check'] === 'source', 'structured output retains passes');

$notReady = new ReadinessResult(1);
$notReady->addFailure('config', 'TALENTHUB_LEARNER_SOURCE is required.');
readiness_assert($notReady->status() === 'NOT_READY', 'ordinary failure is NOT_READY');
readiness_assert($notReady->exitCode() === 2, 'NOT_READY has exit code 2');

$blocked = new ReadinessResult(1);
$blocked->addFailure('database', 'Database connection is unavailable.', true);
$blocked->addFailure('schema', 'Missing table.', false);
readiness_assert($blocked->status() === 'BLOCKED', 'blocked failure takes precedence');
readiness_assert($blocked->exitCode() === 3, 'BLOCKED has exit code 3');

$requirements = new PhaseRequirements();
readiness_assert(count($requirements->all()) === 12, 'requirements cover phases 0 through 11');
readiness_assert($requirements->forPhase(0)['requires_database'] === false, 'phase 0 does not require a database');
readiness_assert(
    $requirements->forPhase(1)['config_keys'] === [],
    'phase 1 reuses shared DB_* configuration instead of learner-specific variables'
);
readiness_expect_exception(static fn (): array => $requirements->forPhase(12), InvalidArgumentException::class, 'between 0 and 11');

$database = new PDO('sqlite::memory:');
$database->exec('CREATE TABLE learner_schema_migrations (version TEXT PRIMARY KEY, appliedAt TEXT)');
$database->exec('CREATE INDEX idx_learner_schema_migrations_applied_at ON learner_schema_migrations (appliedAt)');
$inspector = new SchemaInspector($database, 'main');
readiness_assert($inspector->hasTable('learner_schema_migrations'), 'schema inspector finds a table');
readiness_assert($inspector->hasColumn('learner_schema_migrations', 'version'), 'schema inspector finds a column');
readiness_assert($inspector->hasIndex('learner_schema_migrations', 'idx_learner_schema_migrations_applied_at'), 'schema inspector finds an index');
readiness_assert(!$inspector->hasTable('missing_table'), 'schema inspector reports missing table');
readiness_expect_exception(static fn (): bool => $inspector->hasTable('bad-name'), InvalidArgumentException::class, 'validated identifier');

$guard = new GitScopeGuard();
$scope = $guard->inspectPaths(['app/learner/tools/readiness-check.php', 'app\\teacher\\new.php']);
readiness_assert($scope['allowed'] === false, 'scope guard rejects forbidden normalized path');
readiness_assert($scope['forbidden_paths'] === ['app/teacher/new.php'], 'scope guard reports normalized forbidden path');
readiness_assert($guard->inspectPaths(['app/learner/data/bootstrap.php'])['allowed'] === true, 'scope guard allows learner path');
$taskOneScope = $guard->inspectPaths([
    'src/Rbac/Service/PermissionService.php',
    'tests/permission_service_driver_compatibility_test.php',
]);
readiness_assert($taskOneScope['allowed'] === true, 'scope guard allows the narrowly approved Task 1 RBAC compatibility fix');
readiness_assert($guard->inspectPaths(['src/Database/Connection.php'])['allowed'] === false, 'scope guard still blocks unrelated shared source changes');
readiness_assert($guard->inspectPaths(['.env'])['allowed'] === false, 'scope guard always blocks .env');
readiness_assert(
    $guard->inspectPaths(['Database/migrations/learner/001_migration_registry.sql'])['allowed'] === false,
    'scope guard always blocks protected learner migrations even though application migrations are allowed',
);
$phaseThreeScope = $guard->inspectPaths([
    'Database/seeds/System/RolePermissionSeeder.php',
    'src/Bootstrap/StudentAppContext.php',
    'src/Modules/Student/Repository/StudentRepository.php',
    'src/Modules/Student/Service/StudentProfileService.php',
    'tests/phase_3_reconciliation_migration_test.php',
    'tests/qr_session_migration_contract_test.php',
    'tests/student_portal_cross_role_contract_test.php',
]);
readiness_assert($phaseThreeScope['allowed'] === true, 'scope guard allows reviewed Phase 0-3 shared files only');

$reviewedFixture = readiness_create_git_repo_fixture();
mkdir($reviewedFixture . DIRECTORY_SEPARATOR . '.qwen', 0777, true);
$reviewedSettings = $reviewedFixture . DIRECTORY_SEPARATOR . '.qwen' . DIRECTORY_SEPARATOR . 'settings.json';
file_put_contents($reviewedSettings, '{"reviewed":true}');
$reviewedHash = hash_file('sha256', $reviewedSettings);
$reviewedGuard = new GitScopeGuard(['.qwen/settings.json' => $reviewedHash]);
$reviewedScope = $reviewedGuard->inspectWorkspace($reviewedFixture);
readiness_assert($reviewedScope['allowed'] === true, 'unchanged reviewed protected file is accepted');
readiness_assert($reviewedScope['reviewed_paths'] === ['.qwen/settings.json'], 'reviewed protected path is reported explicitly');
file_put_contents($reviewedSettings, '{"reviewed":false}');
$changedReviewedScope = $reviewedGuard->inspectWorkspace($reviewedFixture);
readiness_assert($changedReviewedScope['allowed'] === false, 'changed reviewed protected file becomes forbidden again');
readiness_assert(in_array('.qwen/settings.json', $changedReviewedScope['forbidden_paths'], true), 'changed reviewed path remains visible');
$untrustedBaselineGuard = new GitScopeGuard([
    '.env' => hash('sha256', 'DB_PASSWORD=unsafe'),
    'Database/migrations/learner/001_migration_registry.sql' => hash('sha256', 'unsafe'),
]);
readiness_assert($untrustedBaselineGuard->inspectPaths(['.env'])['allowed'] === false, 'review input cannot authorize .env');
readiness_assert(
    $untrustedBaselineGuard->inspectPaths(['Database/migrations/learner/001_migration_registry.sql'])['allowed'] === false,
    'review input cannot authorize learner migrations',
);

$checker = new ReadinessChecker(new PhaseRequirements(), $guard);
$fixtureRepo = readiness_create_git_repo_fixture();
$phaseZero = $checker->check(0, $fixtureRepo, static fn (): PDO => throw new RuntimeException('must not connect'));
readiness_assert($phaseZero->status() === 'READY', 'phase 0 does not invoke database factory');
$unavailable = $checker->check(1, $fixtureRepo, static fn (): PDO => throw new RuntimeException('offline'));
readiness_assert($unavailable->status() === 'BLOCKED', 'shared database outage is blocked');
readiness_assert($unavailable->exitCode() === 3, 'blocked database returns exit 3');
$schemaInitializationFailure = $checker->check(1, $fixtureRepo, static fn (): PDO => new PDO('sqlite::memory:'));
readiness_assert($schemaInitializationFailure->status() === 'BLOCKED', 'shared schema initialization outage is blocked');
readiness_assert($schemaInitializationFailure->exitCode() === 3, 'schema initialization outage returns exit 3');

$migrationRunner = new LearnerMigrationRunner($database);
$migrationRunner->ensureRegistry();
readiness_assert($migrationRunner->apply('001_registry') === true, 'migration runner applies a new version');
readiness_assert($migrationRunner->apply('001_registry') === false, 'migration runner safely skips an applied version');

$mockRuntime = LearnerRuntime::fromConfig(['source' => 'mock']);
readiness_assert($mockRuntime->source() === 'mock' && $mockRuntime->studentId() === 'student-demo-001', 'runtime permits demo data only in explicit mock mode');
readiness_expect_exception(static fn (): LearnerRuntime => LearnerRuntime::fromConfig(['source' => 'database']), \TalentHub\Learner\Data\Exceptions\LearnerDataConfigurationException::class, 'requires a real PDO');
$databaseRuntime = LearnerRuntime::fromConfig(['source' => 'database', 'pdo' => $database, 'student_id' => 'student-test']);
readiness_assert($databaseRuntime->studentId() === 'student-test', 'database runtime requires an explicit student identity');
readiness_assert(!array_key_exists('pdo', $databaseRuntime->diagnostics()), 'runtime diagnostics omit PDO and connection details');

$reviewedWorkspaceHashes = [
    '.claude/settings.local.json' => '33875503CD2A8A822E9F1B4759A7FA003654EE53F3D018E4C87D31F1CAFB0874',
    '.qwen/settings.json' => '6979FF28D933BBB504CAE4EEE75F07AFF325AA9B8CB93C07CE6C8EF53202ADF2',
];
$expectedWorkspaceScope = (new GitScopeGuard($reviewedWorkspaceHashes))->inspectWorkspace(dirname(__DIR__));
$php = escapeshellarg(PHP_BINARY);
$cli = escapeshellarg(dirname(__DIR__) . '/app/learner/tools/readiness-check.php');
$reviewArgs = '';
foreach ($reviewedWorkspaceHashes as $path => $hash) {
    $reviewArgs .= ' --reviewed-hash=' . escapeshellarg($path . '=' . $hash);
}

exec("{$php} {$cli} --phase=0 --format=json{$reviewArgs} 2>&1", $cliOutput, $cliExitCode);
$cliPayload = json_decode(implode("\n", $cliOutput), true, 512, JSON_THROW_ON_ERROR);
if ($expectedWorkspaceScope['allowed']) {
    readiness_assert($cliExitCode === 0, 'phase 0 CLI exits READY on clean workspace');
    readiness_assert($cliPayload['status'] === 'READY' && $cliPayload['phase'] === 0, 'phase 0 CLI emits deterministic JSON');
} else {
    readiness_assert($cliExitCode === 2, 'phase 0 CLI exits NOT_READY on dirty workspace');
    readiness_assert($cliPayload['status'] === 'NOT_READY' && $cliPayload['phase'] === 0, 'phase 0 CLI reports NOT_READY on dirty workspace');
}

exec("{$php} {$cli} --phase=1 --format=json{$reviewArgs} 2>&1", $blockedCliOutput, $blockedCliExitCode);
$blockedCliPayload = json_decode(implode("\n", $blockedCliOutput), true, 512, JSON_THROW_ON_ERROR);
if ($expectedWorkspaceScope['allowed']) {
    readiness_assert(
        in_array($blockedCliExitCode, [0, 3], true),
        'phase 1 is READY with a canonical shared database or BLOCKED when the shared database is unavailable'
    );
    if ($blockedCliExitCode === 0) {
        readiness_assert($blockedCliPayload['status'] === 'READY', 'canonical shared database makes phase 1 READY');
    } else {
        readiness_assert($blockedCliExitCode === 3, 'unavailable shared database blocks phase 1');
        readiness_assert($blockedCliPayload['status'] === 'BLOCKED', 'unavailable shared database reports BLOCKED');
    }
} else {
    readiness_assert(
        in_array($blockedCliExitCode, [2, 3], true),
        'phase 1 is NOT_READY (scope) or BLOCKED (database) on dirty workspace'
    );
}
readiness_assert(!str_contains(json_encode($blockedCliPayload, JSON_THROW_ON_ERROR), 'password='), 'CLI diagnostics do not disclose secrets');

exec("{$php} {$cli} --phase=0 --format=text{$reviewArgs} 2>&1", $textCliOutput, $textCliExitCode);
if ($expectedWorkspaceScope['allowed']) {
    readiness_assert($textCliExitCode === 0, 'phase 0 CLI text format exits READY');
    readiness_assert(str_contains(implode("\n", $textCliOutput), 'Phase 0: READY'), 'phase 0 CLI text output contains status');
} else {
    readiness_assert($textCliExitCode === 2, 'phase 0 CLI text format exits NOT_READY on dirty workspace');
    readiness_assert(str_contains(implode("\n", $textCliOutput), 'Phase 0: NOT_READY'), 'phase 0 CLI text output contains status');
}

exec("{$php} {$cli} --phase=12 --format=json 2>&1", $invalidCliOutput, $invalidCliExitCode);
readiness_assert($invalidCliExitCode === 2, 'invalid phase has validation exit code');
readiness_assert(str_contains(implode("\n", $invalidCliOutput), 'between 0 and 11'), 'invalid phase explains valid range');

$diagnostics = learner_safe_runtime_diagnostics();
readiness_assert(isset($diagnostics['source']) && !isset($diagnostics['password']), 'diagnostics do not leak secrets');

learner_configure_data(['source' => 'database', 'pdo' => null, 'student_id' => null]);
readiness_expect_exception(static fn (): \TalentHub\Learner\Data\RepositoryFactory => learner_repository_factory(), \TalentHub\Learner\Data\Exceptions\LearnerDataConfigurationException::class, 'requires an injected PDO');
readiness_expect_exception(static fn (): string => learner_current_student_id(), \TalentHub\Learner\Data\Exceptions\LearnerDataConfigurationException::class, 'requires an explicit student_id');
learner_configure_data(['source' => 'mock', 'pdo' => null, 'student_id' => null]);
readiness_assert(learner_current_student_id() === 'student-demo-001', 'demo student is confined to explicit mock mode');

foreach ($readinessTestFixtures as $fixture) {
    readiness_cleanup_fixture_dir($fixture);
    readiness_assert(!is_dir($fixture), "fixture directory {$fixture} must be deleted after test run");
}

$finalFixtureDirs = glob($tempDir . DIRECTORY_SEPARATOR . 'talenthub-readiness-*') ?: [];
$finalFixtureCount = count($finalFixtureDirs);
readiness_assert(
    $finalFixtureCount <= $initialFixtureCount,
    "temporary readiness fixtures leaked: was {$initialFixtureCount}, now {$finalFixtureCount}"
);

echo "learner_readiness_test: OK\n";
