<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationContext;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Learner\Data\Database\DatabaseBadgeRepository;
use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\BadgeReadService;
use TalentHub\Learner\Data\Service\BadgeRuleEngine;
use TalentHub\Learner\Data\Service\StatisticsService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

/** @return array{code:int,stdout:string,stderr:string} */
$run = static function (array $command, array $environment = [], ?string $stdinFile = null): array {
    $descriptors = [
        0 => $stdinFile === null ? ['pipe', 'r'] : ['file', $stdinFile, 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__), array_merge($_ENV, getenv(), $environment));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start rehearsal child process.');
    }
    if ($stdinFile === null) {
        fclose($pipes[0]);
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['code' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
};

/** @return array<string, array{count:int,sha256:string}> */
$snapshot = static function (PDO $pdo, array $excludedTables = []): array {
    $tables = $pdo->query(<<<'SQL'
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
        ORDER BY table_name
    SQL)->fetchAll(PDO::FETCH_COLUMN);
    $result = [];
    foreach ($tables as $tableValue) {
        $table = (string) $tableValue;
        if (in_array($table, $excludedTables, true)) {
            continue;
        }
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        $columnsStatement = $pdo->prepare(<<<'SQL'
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = :table
            ORDER BY ordinal_position
        SQL);
        $columnsStatement->execute(['table' => $table]);
        $columns = array_map('strval', $columnsStatement->fetchAll(PDO::FETCH_COLUMN));
        $primaryStatement = $pdo->prepare(<<<'SQL'
            SELECT column_name
            FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = :table AND index_name = 'PRIMARY'
            ORDER BY seq_in_index
        SQL);
        $primaryStatement->execute(['table' => $table]);
        $orderColumns = array_map('strval', $primaryStatement->fetchAll(PDO::FETCH_COLUMN));
        if ($orderColumns === []) {
            $orderColumns = $columns;
        }
        $quotedColumns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns);
        $quotedOrder = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', $orderColumns);
        $rows = $pdo->query(
            'SELECT ' . implode(', ', $quotedColumns) . ' FROM ' . $quotedTable . ' ORDER BY ' . implode(', ', $quotedOrder)
        );
        $hash = hash_init('sha256');
        $count = 0;
        while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
            hash_update($hash, serialize($row));
            $count++;
        }
        $result[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
    }

    return $result;
};

$dumpPath = trim((string) (getenv('TALENTHUB_PHASE9_BASELINE_DUMP') ?: ''));
$dumpSha = strtolower(trim((string) (getenv('TALENTHUB_PHASE9_BASELINE_SHA256') ?: '')));
$assert($dumpPath !== '' && is_file($dumpPath), 'TALENTHUB_PHASE9_BASELINE_DUMP must reference an existing explicit dump.');
$assert(preg_match('/\A[a-f0-9]{64}\z/', $dumpSha) === 1, 'TALENTHUB_PHASE9_BASELINE_SHA256 must be a 64-character SHA-256.');
$assert(hash_equals($dumpSha, (string) hash_file('sha256', $dumpPath)), 'Pinned baseline dump SHA-256 mismatch.');

$rawConfig = require dirname(__DIR__) . '/config/database.php';
$rootPdo = new PDO(
    sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $rawConfig['host'], $rawConfig['port']),
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
);
$timestamp = gmdate('YmdHis');
$disposableDb = "talenthub_phase9_rehearsal_{$timestamp}";
$assert(preg_match('/\Atalenthub_phase9_(?:rehearsal|test)_\d{14}\z/', $disposableDb) === 1, 'Unsafe disposable database name.');
$assert($disposableDb !== 'talenthub_local', 'Disposable database must never equal talenthub_local.');

$phpBin = 'D:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$mysqlBin = 'D:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe';
$assert(is_file($phpBin) && is_file($mysqlBin), 'Pinned PHP/MySQL executables must exist.');

$primaryBefore = [
    'tables' => (int) $rootPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
    'migrations' => (int) $rootPdo->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
    'phase9' => (int) $rootPdo->query("SELECT COUNT(*) FROM talenthub_local.schema_migrations WHERE version = '20260821000700'")->fetchColumn(),
];
$failure = null;

try {
    $rootPdo->exec("CREATE DATABASE `{$disposableDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach (['127.0.0.1', 'localhost'] as $host) {
        $rootPdo->exec("GRANT ALL PRIVILEGES ON `{$disposableDb}`.* TO '{$rawConfig['username']}'@'{$host}'");
    }

    $import = $run([
        $mysqlBin,
        '--host=' . $rawConfig['host'],
        '--port=' . (string) $rawConfig['port'],
        '--user=root',
        '--database=' . $disposableDb,
    ], [], $dumpPath);
    $assert($import['code'] === 0, 'Pinned dump import failed: ' . $import['stderr']);

    $disposableConfig = $rawConfig;
    $disposableConfig['database'] = $disposableDb;
    $pdo = (new Connection($disposableConfig))->connect();
    $baseline = $snapshot($pdo, ['schema_migrations']);
    $phase9PrimaryTables = $primaryBefore['phase9'] === 1 ? 3 : 0;
    $expectedBaselineTables = $primaryBefore['tables'] - 1 - $phase9PrimaryTables;
    $assert(count($baseline) === $expectedBaselineTables, 'Pinned dump base-table count differs from primary baseline.');
    foreach (['badges', 'badge_rule_definitions', 'student_badges'] as $target) {
        $assert(!isset($baseline[$target]), "{$target} must be absent from the pinned baseline.");
    }

    $pdo->exec('CREATE TABLE badges (id CHAR(36) NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $partialRejected = false;
    try {
        (new MigrationRunner($pdo, dirname(__DIR__) . '/Database/migrations'))->migrate();
    } catch (Throwable $error) {
        $partialRejected = str_contains($error->getMessage(), 'partial state');
    }
    $assert($partialRejected, 'Migration rejects a partial Phase 9 target-table state.');
    $pdo->exec('DROP TABLE badges');
    $assert((int) $pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE version = '20260821000700'")->fetchColumn() === 0, 'Failed preflight must not record migration 00700.');

    $runner = new MigrationRunner($pdo, dirname(__DIR__) . '/Database/migrations');
    $applied = $runner->migrate();
    $assert($applied === ['20260821000700'], 'Disposable apply must contain only migration 00700.');
    $assert($runner->migrate() === [], 'Second migration run must be a no-op.');

    $afterMigration = $snapshot($pdo, ['schema_migrations', 'badges', 'badge_rule_definitions', 'student_badges']);
    $assert($afterMigration === $baseline, 'Migration changed a pre-existing base table.');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM badges')->fetchColumn() === 5, 'Exactly five canonical badges are seeded.');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM badge_rule_definitions WHERE isActive = 1 AND version = 1')->fetchColumn() === 5, 'Exactly five active v1 rules are seeded.');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM student_badges')->fetchColumn() === 0, 'Migration creates no learner awards.');

    $pdo->beginTransaction();
    $pdo->exec("UPDATE badges SET name = 'conflicting name' WHERE code = 'first_experience'");
    $catalogRejected = false;
    try {
        $migration = require dirname(__DIR__) . '/Database/migrations/20260821000700_create_badges_and_award_rules.php';
        $migration->preflight(new MigrationContext($pdo));
    } catch (Throwable $error) {
        $catalogRejected = str_contains($error->getMessage(), 'conflicting metadata');
    }
    $pdo->rollBack();
    $assert($catalogRejected, 'Migration preflight rejects conflicting canonical catalog metadata.');

    $environment = ['DB_DATABASE' => $disposableDb];
    $dryRun = $run([$phpBin, dirname(__DIR__) . '/bin/run-badge-awards.php', '--dry-run', '--all', '--json'], $environment);
    $assert($dryRun['code'] === 0, 'Dry-run CLI failed: ' . $dryRun['stderr']);
    $dryJson = json_decode($dryRun['stdout'], true, 512, JSON_THROW_ON_ERROR);
    $eligible = (int) ($dryJson['total_eligible_awards'] ?? -1);
    $assert($eligible > 0, 'Dry-run must discover eligible demo awards.');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM student_badges')->fetchColumn() === 0, 'Dry-run must not persist awards.');

    $factsBefore = [];
    $statsRepo = new DatabaseStatisticsRepository($pdo);
    foreach ($pdo->query('SELECT id FROM student_profiles ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
        $factsBefore[(string) $studentId] = $statsRepo->lifetimeFacts((string) $studentId);
    }
    $notificationBefore = (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();

    $apply = $run([$phpBin, dirname(__DIR__) . '/bin/run-badge-awards.php', '--apply', '--all', '--json'], $environment);
    $assert($apply['code'] === 0, 'Disposable apply CLI failed: ' . $apply['stderr']);
    $applyJson = json_decode($apply['stdout'], true, 512, JSON_THROW_ON_ERROR);
    $awards = (int) ($applyJson['total_persisted_awards'] ?? -1);
    $assert($awards === $eligible, 'Apply count must equal the pinned dry-run eligible count.');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM student_badges')->fetchColumn() === $awards, 'Persisted award rows must equal apply count.');

    $replay = $run([$phpBin, dirname(__DIR__) . '/bin/run-badge-awards.php', '--apply', '--all', '--json'], $environment);
    $assert($replay['code'] === 0, 'Replay CLI failed: ' . $replay['stderr']);
    $replayJson = json_decode($replay['stdout'], true, 512, JSON_THROW_ON_ERROR);
    $assert((int) ($replayJson['total_persisted_awards'] ?? -1) === 0, 'Replay must persist zero awards.');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM student_badges')->fetchColumn() === $awards, 'Replay must preserve award count.');

    $awardRows = $pdo->query(<<<'SQL'
        SELECT sb.studentId, sb.badgeId, sb.ruleDefinitionId, sb.awardContext,
               br.thresholdCriteria, br.version
        FROM student_badges sb
        INNER JOIN badge_rule_definitions br ON br.id = sb.ruleDefinitionId
        ORDER BY sb.studentId, sb.badgeId
    SQL)->fetchAll(PDO::FETCH_ASSOC);
    $engine = new BadgeRuleEngine();
    foreach ($awardRows as $award) {
        $studentId = (string) $award['studentId'];
        $criteria = json_decode((string) $award['thresholdCriteria'], true, 512, JSON_THROW_ON_ERROR);
        $context = json_decode((string) $award['awardContext'], true, 512, JSON_THROW_ON_ERROR);
        $evaluation = $engine->evaluate($criteria, $factsBefore[$studentId] ?? []);
        $assert($evaluation['eligible'] === true, "Award {$award['badgeId']} is not justified by the saved fact snapshot.");
        $assert(($context['ruleDefinitionId'] ?? null) === $award['ruleDefinitionId'], 'Award context rule ID mismatch.');
        $assert((int) ($context['ruleVersion'] ?? 0) === (int) $award['version'], 'Award context rule version mismatch.');
    }

    $notificationAfter = (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
    $assert($notificationAfter - $notificationBefore === $awards, 'Each backfilled award must add one notification.');
    $missingNotifications = (int) $pdo->query(<<<'SQL'
        SELECT COUNT(*)
        FROM student_badges sb
        INNER JOIN badge_rule_definitions br ON br.id = sb.ruleDefinitionId
        INNER JOIN student_profiles sp ON sp.id = sb.studentId
        LEFT JOIN notifications n
          ON n.userId = sp.userId
         AND n.eventKey = CONCAT('badge_award:', sb.studentId, ':', sb.badgeId, ':v', br.version)
        WHERE n.id IS NULL
    SQL)->fetchColumn();
    $assert($missingNotifications === 0, 'Every award must have its exact idempotency-keyed notification.');

    $afterBackfill = $snapshot($pdo, ['schema_migrations', 'badges', 'badge_rule_definitions', 'student_badges', 'notifications']);
    $baselineWithoutNotifications = $baseline;
    unset($baselineWithoutNotifications['notifications']);
    $assert($afterBackfill === $baselineWithoutNotifications, 'Backfill changed a table outside student_badges/notifications.');

    $badgeRepo = new DatabaseBadgeRepository($pdo);
    $readService = new BadgeReadService($badgeRepo, $statsRepo, $engine);
    $statsService = new StatisticsService($statsRepo);
    $firstStudent = (string) $pdo->query('SELECT id FROM student_profiles ORDER BY id LIMIT 1')->fetchColumn();
    $badgeView = $readService->forStudent($firstStudent);
    $statisticsView = $statsService->forStudentPeriod($firstStudent, 'month');
    $assert(isset($badgeView['badges'], $badgeView['progress'], $badgeView['facts'], $badgeView['level']), 'Badge read service returns the complete owner view.');
    $assert(isset($statisticsView['kpis'], $statisticsView['experience'], $statisticsView['fields'], $statisticsView['level']), 'Statistics service returns the complete owner view.');

    $concurrency = $run([$phpBin, dirname(__DIR__) . '/tests/learner_badge_award_mysql_concurrency_test.php']);
    $assert($concurrency['code'] === 0, 'Eight-worker concurrency gate failed: ' . $concurrency['stderr']);

    $primaryAfter = [
        'tables' => (int) $rootPdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'talenthub_local' AND table_type = 'BASE TABLE'")->fetchColumn(),
        'migrations' => (int) $rootPdo->query('SELECT COUNT(*) FROM talenthub_local.schema_migrations')->fetchColumn(),
        'phase9' => (int) $rootPdo->query("SELECT COUNT(*) FROM talenthub_local.schema_migrations WHERE version = '20260821000700'")->fetchColumn(),
    ];
    $assert($primaryAfter === $primaryBefore, 'Disposable rehearsal mutated talenthub_local.');

    echo json_encode([
        'result' => 'PASS',
        'database' => $disposableDb,
        'dump' => ['path' => $dumpPath, 'sha256' => $dumpSha, 'size' => filesize($dumpPath)],
        'baseline_tables_hashed' => count($baseline),
        'migration_applied' => $applied,
        'dry_run_eligible' => $eligible,
        'awards_persisted' => $awards,
        'replay_persisted' => 0,
        'notification_delta' => $notificationAfter - $notificationBefore,
        'concurrency' => trim($concurrency['stdout']),
        'primary_before_after_equal' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $error) {
    $failure = $error;
} finally {
    foreach (['127.0.0.1', 'localhost'] as $host) {
        try {
            $rootPdo->exec("REVOKE ALL PRIVILEGES ON `{$disposableDb}`.* FROM '{$rawConfig['username']}'@'{$host}'");
        } catch (Throwable) {
        }
    }
    try {
        $rootPdo->exec("DROP DATABASE IF EXISTS `{$disposableDb}`");
    } catch (Throwable $cleanupError) {
        $failure ??= $cleanupError;
    }
}

$schemaCheck = $rootPdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = :schema');
$schemaCheck->execute(['schema' => $disposableDb]);
$grantCheck = $rootPdo->prepare("SELECT COUNT(*) FROM mysql.db WHERE Db = :schema AND User = :user AND Host IN ('127.0.0.1', 'localhost')");
$grantCheck->execute(['schema' => $disposableDb, 'user' => $rawConfig['username']]);
$cleanupOkay = (int) $schemaCheck->fetchColumn() === 0 && (int) $grantCheck->fetchColumn() === 0;
if (!$cleanupOkay) {
    throw new RuntimeException("Disposable cleanup failed for {$disposableDb}.", previous: $failure);
}
if ($failure !== null) {
    throw $failure;
}

echo "phase9_rehearsal_integrity_test: OK; cleanup verified\n";
