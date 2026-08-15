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

$checker = new ReadinessChecker(new PhaseRequirements(), $guard);
$phaseZero = $checker->check(0, dirname(__DIR__), static fn (): PDO => throw new RuntimeException('must not connect'));
readiness_assert($phaseZero->status() === 'READY', 'phase 0 does not invoke database factory');
$unavailable = $checker->check(1, dirname(__DIR__), static fn (): PDO => throw new RuntimeException('offline'));
readiness_assert($unavailable->status() === 'BLOCKED', 'shared database outage is blocked');
readiness_assert($unavailable->exitCode() === 3, 'blocked database returns exit 3');

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

$php = escapeshellarg(PHP_BINARY);
$cli = escapeshellarg(dirname(__DIR__) . '/app/learner/tools/readiness-check.php');
exec("{$php} {$cli} --phase=0 --format=json 2>&1", $cliOutput, $cliExitCode);
readiness_assert($cliExitCode === 0, 'phase 0 CLI exits READY');
$cliPayload = json_decode(implode("\n", $cliOutput), true, 512, JSON_THROW_ON_ERROR);
readiness_assert($cliPayload['status'] === 'READY' && $cliPayload['phase'] === 0, 'phase 0 CLI emits deterministic JSON');
exec("{$php} {$cli} --phase=1 --format=json 2>&1", $blockedCliOutput, $blockedCliExitCode);
readiness_assert(
    in_array($blockedCliExitCode, [0, 3], true),
    'phase 1 is READY with a canonical shared database or BLOCKED when the shared database is unavailable'
);
$blockedCliPayload = json_decode(implode("\n", $blockedCliOutput), true, 512, JSON_THROW_ON_ERROR);
readiness_assert($blockedCliPayload['status'] !== 'READY', 'phase 1 without an explicit database is not ready');
readiness_assert(!str_contains(json_encode($blockedCliPayload, JSON_THROW_ON_ERROR), 'password='), 'CLI diagnostics do not disclose secrets');
exec("{$php} {$cli} --phase=0 --format=text 2>&1", $textCliOutput, $textCliExitCode);
readiness_assert($textCliExitCode === 0, 'phase 0 CLI text format exits READY');
readiness_assert(str_contains(implode("\n", $textCliOutput), 'Phase 0: READY'), 'phase 0 CLI text output contains status');

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

echo "learner_readiness_test: OK\n";
