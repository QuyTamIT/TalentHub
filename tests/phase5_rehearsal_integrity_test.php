<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Connection;

$schema = (string) (getenv('TALENTHUB_PHASE5_REHEARSAL_SCHEMA') ?: '');
if (preg_match('/\Atalenthub_phase5_rehearsal_\d{14}\z/', $schema) !== 1) {
    fwrite(STDERR, "phase5_rehearsal_integrity_test: REFUSED unsafe or missing rehearsal schema\n");
    exit(2);
}

$baseConfig = require dirname(__DIR__) . '/config/database.php';
if (($baseConfig['database'] ?? null) !== 'talenthub_local') {
    fwrite(STDERR, "phase5_rehearsal_integrity_test: source must be talenthub_local\n");
    exit(2);
}
$rehearsalConfig = $baseConfig;
$rehearsalConfig['database'] = $schema;
$source = (new Connection($baseConfig))->connect();
$target = (new Connection($rehearsalConfig))->connect();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assert((string) $source->query('SELECT DATABASE()')->fetchColumn() === 'talenthub_local', 'Source connection is pinned to primary read-only comparison.');
$assert((string) $target->query('SELECT DATABASE()')->fetchColumn() === $schema, 'Target connection is pinned to disposable rehearsal.');

$tables = static function (PDO $pdo): array {
    $statement = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' ORDER BY table_name");
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
};
$sourceTables = $tables($source);
$targetTables = $tables($target);
$expectedTargetTables = $sourceTables;
$expectedTargetTables[] = 'activity_experience_policies';
sort($expectedTargetTables, SORT_STRING);
$assert($targetTables === $expectedTargetTables, 'Rehearsal adds exactly the Phase 5 policy table.');

$snapshotTable = static function (PDO $pdo, string $table): array {
    if (preg_match('/\A[a-z0-9_]+\z/', $table) !== 1) {
        throw new RuntimeException('Unsafe table metadata.');
    }
    $primary = $pdo->prepare(<<<'SQL'
        SELECT column_name
        FROM information_schema.key_column_usage
        WHERE table_schema=DATABASE() AND table_name=:table AND constraint_name='PRIMARY'
        ORDER BY ordinal_position
    SQL);
    $primary->execute(['table' => $table]);
    $order = array_map(static fn (string $column): string => '`' . $column . '`', $primary->fetchAll(PDO::FETCH_COLUMN));
    $sql = "SELECT * FROM `{$table}`" . ($order === [] ? '' : ' ORDER BY ' . implode(',', $order));
    $statement = $pdo->query($sql);
    $hash = hash_init('sha256');
    $count = 0;
    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        $count++;
    }
    return ['count' => $count, 'sha256' => hash_final($hash)];
};

$manifest = [];
foreach ($sourceTables as $table) {
    if ($table === 'schema_migrations') {
        continue;
    }
    $sourceSnapshot = $snapshotTable($source, $table);
    $targetSnapshot = $snapshotTable($target, $table);
    $assert($sourceSnapshot === $targetSnapshot, "Existing table {$table} changed during rehearsal or tests.");
    $manifest[$table] = $sourceSnapshot;
}
$sourceMigrations = (int) $source->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
$targetMigrations = (int) $target->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
$assert($targetMigrations === $sourceMigrations + 1, 'Rehearsal records exactly one new migration.');
$assert((int) $target->query('SELECT COUNT(*) FROM activity_experience_policies')->fetchColumn() === 0, 'Additive policy table has no backfill rows.');

echo json_encode([
    'source' => 'talenthub_local',
    'target' => $schema,
    'comparedTables' => count($manifest),
    'sourceMigrations' => $sourceMigrations,
    'targetMigrations' => $targetMigrations,
    'manifestSha256' => hash('sha256', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), PHP_EOL;
