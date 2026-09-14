<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$migration = $root . '/Database/migrations/20260915000200_widen_learner_ai_evidence_source_id.php';
$assert(is_file($migration), 'Evidence migration must have its own unique version.');
$assert(!is_file($root . '/Database/migrations/20260914000100_widen_learner_ai_evidence_source_id.php'), 'Colliding evidence filename must be removed.');
require $root . '/bin/reconcile-ai-evidence-migration-version.php';
$contents = (string) file_get_contents($migration);
$canonical = hash('sha256', str_replace(["\r\n", "\r"], "\n", $contents));
$raw = hash('sha256', $contents);
$fixture = static function (int $length = 191): PDO {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE schema_migrations (version TEXT PRIMARY KEY, name TEXT UNIQUE, checksum TEXT, batch INTEGER, executionMs INTEGER, appliedAt TEXT)');
    foreach (['learner_recommendation_snapshot_evidence', 'learner_recommendation_evidence'] as $table) {
        $pdo->exec("CREATE TABLE {$table} (sourceId VARCHAR({$length}) NOT NULL)");
    }
    return $pdo;
};
$insert = static function (PDO $pdo, string $version, string $name, string $checksum): void {
    $pdo->prepare('INSERT INTO schema_migrations VALUES (?, ?, ?, 7, 123, ?)')->execute([$version, $name, $checksum, '2026-09-14 12:34:56.123456']);
};
$rows = static fn (PDO $pdo): array => $pdo->query('SELECT * FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_ASSOC);
$reject = static function (callable $action, string $needle) use ($assert): void {
    try {
        $action();
    } catch (RuntimeException $e) {
        $assert(str_contains($e->getMessage(), $needle), 'Unexpected refusal: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected reconciliation refusal: ' . $needle);
};
$name = 'widen_learner_ai_evidence_source_id';
$old = '20260914000100';
$new = '20260915000200';
$admin = 'allow_suspended_organization_verification';

foreach (array_unique([$canonical, $raw]) as $checksum) {
    $pdo = $fixture();
    $insert($pdo, $old, $name, $checksum);
    $before = $rows($pdo);
    $assert(str_contains(reconcileAiEvidenceMigrationVersion($pdo, $migration), 'would move'), 'Default must preview the move.');
    $assert($rows($pdo) === $before, 'Dry-run must leave all ledger fields unchanged.');
    $assert(str_contains(reconcileAiEvidenceMigrationVersion($pdo, $migration, true), 'moved'), 'Apply should move verified evidence record.');
    $expected = $before;
    $expected[0]['version'] = $new;
    $expected[0]['checksum'] = $canonical;
    $assert($rows($pdo) === $expected, 'Only version and checksum may change.');
    $assert(str_contains(reconcileAiEvidenceMigrationVersion($pdo, $migration, true), 'already reconciled'), 'Repeated apply must be idempotent.');
    $insert($pdo, $old, $admin, str_repeat('a', 64));
    $before = $rows($pdo);
    reconcileAiEvidenceMigrationVersion($pdo, $migration, true);
    $assert($rows($pdo) === $before, 'Admin migration must survive repeated reconciliation.');
}

$pdo = $fixture();
$insert($pdo, $old, $admin, str_repeat('a', 64));
$before = $rows($pdo);
$assert(str_contains(reconcileAiEvidenceMigrationVersion($pdo, $migration, true), 'no collision'), 'Real admin migration needs no reconciliation.');
$assert($rows($pdo) === $before, 'Never relabel a real admin migration.');

foreach (['checksum', 'name', 'target', 'capacity', 'target checksum'] as $scenario) {
    $pdo = $fixture($scenario === 'capacity' ? 36 : 191);
    $insert($pdo, $old, $scenario === 'name' ? 'unexpected_migration' : $name, $scenario === 'checksum' ? str_repeat('b', 64) : $canonical);
    if ($scenario === 'target') {
        $insert($pdo, $new, 'unexpected_target', $canonical);
    }
    if ($scenario === 'target checksum') {
        $pdo->exec('DELETE FROM schema_migrations');
        $insert($pdo, $new, $name, str_repeat('b', 64));
    }
    $before = $rows($pdo);
    $needle = match ($scenario) {
        'name' => 'Unexpected migration',
        'target' => 'Target version',
        'capacity' => 'sourceId capacity',
        default => 'checksum',
    };
    $reject(fn () => reconcileAiEvidenceMigrationVersion($pdo, $migration, true), $needle);
    $assert($rows($pdo) === $before, 'Refusal must leave the ledger unchanged: ' . $scenario);
    $assert(!$pdo->inTransaction(), 'Refusal must close its transaction.');
}

$pdo = $fixture();
$assert(str_contains(reconcileAiEvidenceMigrationVersion($pdo, $migration, true), 'no collision'), 'Fresh databases require no ledger changes.');
echo "ai_evidence_migration_version_reconciliation_test: OK\n";
