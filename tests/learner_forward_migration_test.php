<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function forward_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function forward_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        forward_assert($exception->getMessage() === $message, "exception is {$message}");
        return;
    }

    forward_assert(false, "expected RuntimeException: {$message}");
}

$directory = sys_get_temp_dir() . '/learner-forward-migration-' . bin2hex(random_bytes(8));
mkdir($directory, 0700, true);
$fixture = $directory . '/002_create_sample.php';
file_put_contents($fixture, <<<'PHP'
<?php

return new \TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition(
    '002_create_sample',
    'Create learner sample',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements \TalentHub\Learner\Data\Migrations\LearnerForwardMigration {
        public function version(): string { return '002_create_sample'; }
        public function description(): string { return 'Create learner sample'; }
        public function statements(string $driver): array { return ['CREATE TABLE learner_sample (id INTEGER PRIMARY KEY, name TEXT)']; }
        public function expectedSchema(): array { return ['learner_sample' => ['columns' => ['id', 'name'], 'indexes' => []]]; }
    }
);
PHP
);

try {
    $pdo = new PDO('sqlite::memory:');
    $runner = new LearnerForwardMigrationRunner($pdo, $directory, new SchemaInspector($pdo, 'main'));
    forward_assert($runner->migrateApproved([]) === [], 'unapproved migration does not run');
    forward_assert(!(new SchemaInspector($pdo, 'main'))->hasTable('learner_forward_migrations'), 'unapproved migration does not create registry');
    $pdo->beginTransaction();
    forward_assert($runner->migrateApproved(['002_create_sample']) === ['002_create_sample'], 'approved migration runs');
    forward_assert($pdo->inTransaction(), 'SQLite runner uses the current transaction');
    $pdo->commit();
    forward_assert($runner->migrateApproved(['002_create_sample']) === [], 'second run is a no-op');
    forward_assert((int) $pdo->query('SELECT COUNT(*) FROM learner_forward_migrations')->fetchColumn() === 1, 'one registry row');

    file_put_contents($directory . '/bad-name.php', "<?php return null;");
    forward_expect_exception(static fn (): array => $runner->status(), 'Invalid learner migration filename: bad-name.php');

    unlink($directory . '/bad-name.php');
    file_put_contents($directory . '/003_version_mismatch.php', <<<'PHP'
<?php
return new \TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition(
    '003_version_mismatch', 'Version mismatch', __FILE__, hash_file('sha256', __FILE__),
    new class implements \TalentHub\Learner\Data\Migrations\LearnerForwardMigration {
        public function version(): string { return '999_wrong_version'; }
        public function description(): string { return 'Version mismatch'; }
        public function statements(string $driver): array { return []; }
        public function expectedSchema(): array { return []; }
    }
);
PHP
    );
    forward_expect_exception(static fn (): array => $runner->status(), 'Learner migration version mismatch: 003_version_mismatch.php');

    unlink($directory . '/003_version_mismatch.php');
    file_put_contents($directory . '/003_drop_sample.php', <<<'PHP'
<?php
return new \TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition(
    '003_drop_sample', 'Unsafe statement', __FILE__, hash_file('sha256', __FILE__),
    new class implements \TalentHub\Learner\Data\Migrations\LearnerForwardMigration {
        public function version(): string { return '003_drop_sample'; }
        public function description(): string { return 'Unsafe statement'; }
        public function statements(string $driver): array { return ['DROP TABLE learner_sample']; }
        public function expectedSchema(): array { return []; }
    }
);
PHP
    );
    forward_expect_exception(static fn (): array => $runner->migrateApproved(['003_drop_sample']), 'Rejected destructive learner migration statement: DROP');

    unlink($directory . '/003_drop_sample.php');
    file_put_contents($fixture, "\n// checksum drift\n", FILE_APPEND);
    forward_expect_exception(static fn (): array => $runner->status(), 'Applied learner migration drift');
} finally {
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
}

echo "learner_forward_migration_test: OK\n";
