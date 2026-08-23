<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

if (($argv[1] ?? '') !== '--verify') {
    $baseConfig = require dirname(__DIR__) . '/config/database.php';
    $username = (string) ($baseConfig['username'] ?? '');
    if (preg_match('/\A[a-zA-Z0-9_]+\z/', $username) !== 1) {
        throw new RuntimeException('Unsafe database username for rehearsal grant.');
    }
    $mysql = (string) (getenv('TALENTHUB_MYSQL_EXE') ?: 'D:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe');
    if (!is_file($mysql)) {
        throw new RuntimeException('TALENTHUB_MYSQL_EXE must point to mysql.exe.');
    }
    $dump = (string) (getenv('TALENTHUB_PHASE8_BASELINE_DUMP') ?: '');
    if (!is_file($dump) || filesize($dump) === 0) {
        throw new RuntimeException('TALENTHUB_PHASE8_BASELINE_DUMP must name the reviewed non-empty pre-Phase-8 dump.');
    }
    $expectedDumpHash = strtolower((string) (getenv('TALENTHUB_PHASE8_BASELINE_SHA256') ?: ''));
    if (preg_match('/\A[a-f0-9]{64}\z/', $expectedDumpHash) !== 1) {
        throw new RuntimeException('TALENTHUB_PHASE8_BASELINE_SHA256 must pin the reviewed dump SHA-256.');
    }
    $actualDumpHash = hash_file('sha256', $dump);
    if (!is_string($actualDumpHash) || !hash_equals($expectedDumpHash, strtolower($actualDumpHash))) {
        throw new RuntimeException('Pre-Phase-8 baseline dump SHA-256 verification failed.');
    }
    $schema = 'talenthub_phase8_rehearsal_' . gmdate('YmdHis');
    $root = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $baseConfig['host'], $baseConfig['port']),
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
    );
    $run = static function (array $command, ?string $stdinFile = null): string {
        $descriptors = [
            0 => $stdinFile === null ? ['pipe', 'r'] : ['file', $stdinFile, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start rehearsal command.');
        }
        if ($stdinFile === null) {
            fclose($pipes[0]);
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) {
            throw new RuntimeException("Rehearsal command failed ({$exit}): {$stderr}{$stdout}");
        }
        return (string) $stdout;
    };
    try {
        $root->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $root->exec("GRANT ALL PRIVILEGES ON `{$schema}`.* TO '{$username}'@'127.0.0.1'");
        $run([$mysql, '--host=' . $baseConfig['host'], '--port=' . $baseConfig['port'], '--user=root', $schema], $dump);
        putenv('DB_DATABASE=' . $schema);
        putenv('TALENTHUB_DISPOSABLE_TEST_DB=1');
        putenv('TALENTHUB_PHASE8_REHEARSAL_SCHEMA=' . $schema);

        $baselineConfig = $baseConfig;
        $baselineConfig['database'] = $schema;
        $baseline = (new Connection($baselineConfig))->connect();
        $hashQuery = static function (PDO $pdo, string $sql): string {
            $hash = hash_init('sha256');
            foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
            }
            return hash_final($hash);
        };
        putenv('TALENTHUB_PHASE8_BASELINE_PERMISSION_COUNT=' . (int) $baseline->query('SELECT COUNT(*) FROM permissions')->fetchColumn());
        putenv('TALENTHUB_PHASE8_BASELINE_PERMISSION_HASH=' . $hashQuery($baseline, 'SELECT id, code, description FROM permissions ORDER BY id'));
        putenv('TALENTHUB_PHASE8_BASELINE_ROLE_PERMISSION_COUNT=' . (int) $baseline->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn());
        putenv('TALENTHUB_PHASE8_BASELINE_ROLE_PERMISSION_HASH=' . $hashQuery($baseline, 'SELECT roleId, permissionId FROM role_permissions ORDER BY roleId, permissionId'));
        putenv('TALENTHUB_PHASE8_BASELINE_MIGRATION_COUNT=' . (int) $baseline->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());

        $commands = [
            [PHP_BINARY, dirname(__DIR__) . '/bin/migrate.php', 'validate'],
            [PHP_BINARY, dirname(__DIR__) . '/bin/migrate.php', 'migrate'],
            [PHP_BINARY, dirname(__DIR__) . '/bin/migrate.php', 'migrate'],
            [PHP_BINARY, __FILE__, '--verify'],
            [PHP_BINARY, dirname(__DIR__) . '/tests/phase8_forward_validation_migration_test.php'],
            [PHP_BINARY, dirname(__DIR__) . '/tests/learner_notifications_api_test.php'],
            [PHP_BINARY, dirname(__DIR__) . '/tests/learner_notifications_endpoint_runtime_test.php'],
            [PHP_BINARY, dirname(__DIR__) . '/tests/notification_domain_producer_test.php'],
            [PHP_BINARY, dirname(__DIR__) . '/tests/phase8_notification_mysql_concurrency_test.php'],
            [PHP_BINARY, dirname(__DIR__) . '/tests/student_portal_cross_role_contract_test.php'],
            [PHP_BINARY, dirname(__DIR__) . '/tests/phase8_forward_validation_mysql_test.php'],
        ];

        foreach ($commands as $command) {
            echo $run($command);
        }
    } finally {
        if (preg_match('/\Atalenthub_phase8_rehearsal_\d{14}\z/', $schema) === 1) {
            try {
                $grant = $root->prepare("SELECT COUNT(*) FROM mysql.db WHERE Host = '127.0.0.1' AND Db = :schema AND User = :username");
                $grant->execute(['schema' => $schema, 'username' => $username]);
                if ((int) $grant->fetchColumn() > 0) {
                    $root->exec("REVOKE ALL PRIVILEGES ON `{$schema}`.* FROM '{$username}'@'127.0.0.1'");
                }
            } finally {
                $root->exec("DROP DATABASE IF EXISTS `{$schema}`");
            }
        }
    }
    $remainingSchema = $root->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = :schema');
    $remainingSchema->execute(['schema' => $schema]);
    if ((int) $remainingSchema->fetchColumn() !== 0) {
        throw new RuntimeException('Phase 8 rehearsal schema cleanup failed.');
    }
    $remainingGrant = $root->prepare("SELECT COUNT(*) FROM mysql.db WHERE Host = '127.0.0.1' AND Db = :schema AND User = :username");
    $remainingGrant->execute(['schema' => $schema, 'username' => $username]);
    if ((int) $remainingGrant->fetchColumn() !== 0) {
        throw new RuntimeException('Phase 8 rehearsal grant cleanup failed.');
    }
    echo "phase8_rehearsal_orchestrator: OK ({$schema} created, verified, grant revoked, and schema dropped)\n";
    exit(0);
}

$schema = (string) (getenv('TALENTHUB_PHASE8_REHEARSAL_SCHEMA') ?: '');
if (preg_match('/\Atalenthub_phase8_rehearsal_\d{14}\z/', $schema) !== 1) {
    fwrite(STDERR, "phase8_rehearsal_integrity_test: REFUSED unsafe or missing rehearsal schema\n");
    exit(2);
}

$config = require dirname(__DIR__) . '/config/database.php';
$sourceConfig = $config;
$sourceConfig['database'] = 'talenthub_local';
$targetConfig = $config;
$targetConfig['database'] = $schema;
$source = (new Connection($sourceConfig))->connect();
$target = (new Connection($targetConfig))->connect();
$source->exec("SET time_zone = '+00:00'");
$target->exec("SET time_zone = '+00:00'");

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};
$expectDatabaseFailure = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
        $assert(false, $message);
    } catch (PDOException) {
        $assert(true, $message);
    }
};

$assert((string) $source->query('SELECT DATABASE()')->fetchColumn() === 'talenthub_local', 'source is primary read-only comparison');
$assert((string) $target->query('SELECT DATABASE()')->fetchColumn() === $schema, 'target is exact disposable schema');

$phase8Tables = ['notifications', 'learner_notification_preferences'];
foreach ($phase8Tables as $table) {
    $statement = $target->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table AND table_type = 'BASE TABLE'");
    $statement->execute(['table' => $table]);
    $assert((int) $statement->fetchColumn() === 1, "{$table} exists in rehearsal database");
}

$columns = static function (PDO $pdo, string $table): array {
    $statement = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table ORDER BY ordinal_position');
    $statement->execute(['table' => $table]);
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
};
$assert($columns($target, 'notifications') === ['id', 'userId', 'eventKey', 'notificationType', 'title', 'message', 'deepLink', 'readAt', 'createdAt'], 'notifications exact columns');
$assert($columns($target, 'learner_notification_preferences') === ['studentId', 'notificationType', 'inAppEnabled', 'emailEnabled', 'updatedAt'], 'learner_notification_preferences exact columns');

$indexes = static function (PDO $pdo, string $table): array {
    $statement = $pdo->prepare('SELECT index_name, column_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table ORDER BY index_name, seq_in_index');
    $statement->execute(['table' => $table]);
    $result = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[(string) $row['INDEX_NAME']][] = (string) $row['COLUMN_NAME'];
    }
    ksort($result);
    return $result;
};
$assert($indexes($target, 'notifications') === [
    'PRIMARY' => ['id'],
    'idx_notifications_user_timeline' => ['userId', 'createdAt', 'id'],
    'idx_notifications_user_unread' => ['userId', 'readAt', 'createdAt'],
    'uq_notifications_user_event' => ['userId', 'eventKey'],
], 'notifications exact indexes');
$assert($indexes($target, 'learner_notification_preferences') === [
    'PRIMARY' => ['studentId', 'notificationType'],
], 'learner_notification_preferences exact indexes');

$foreignKeys = static function (PDO $pdo, string $table): array {
    $statement = $pdo->prepare(<<<'SQL'
        SELECT constraint_name, referenced_table_name, delete_rule, update_rule
        FROM information_schema.referential_constraints
        WHERE constraint_schema=DATABASE() AND table_name=:table
        ORDER BY constraint_name
    SQL);
    $statement->execute(['table' => $table]);
    $result = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $result[(string) $row['CONSTRAINT_NAME']] = [(string) $row['REFERENCED_TABLE_NAME'], (string) $row['DELETE_RULE'], (string) $row['UPDATE_RULE']];
    }
    return $result;
};
$assert($foreignKeys($target, 'notifications') === [
    'fk_notifications_user' => ['users', 'RESTRICT', 'CASCADE'],
], 'notifications foreign keys exact');
$assert($foreignKeys($target, 'learner_notification_preferences') === [
    'fk_learner_notification_preferences_student' => ['student_profiles', 'RESTRICT', 'CASCADE'],
], 'learner_notification_preferences foreign keys exact');

// Check RBAC permission delta
$perm = $target->query("SELECT id, code FROM permissions WHERE code = 'notification.manage_preferences_own'")->fetch(PDO::FETCH_ASSOC);
$assert(is_array($perm), 'notification.manage_preferences_own permission exists in DB');
$roleMappings = $target->query("SELECT r.code as role FROM role_permissions rp JOIN roles r ON rp.roleId = r.id JOIN permissions p ON rp.permissionId = p.id WHERE p.code = 'notification.manage_preferences_own'")->fetchAll(PDO::FETCH_COLUMN);
sort($roleMappings);
$assert($roleMappings === ['enterprise', 'school', 'student', 'teacher'], 'manage_preferences_own mapped to all 4 roles');

$hashQuery = static function (PDO $pdo, string $sql): string {
    $hash = hash_init('sha256');
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
    }
    return hash_final($hash);
};
$baselinePermissionCount = (int) getenv('TALENTHUB_PHASE8_BASELINE_PERMISSION_COUNT');
$baselineRolePermissionCount = (int) getenv('TALENTHUB_PHASE8_BASELINE_ROLE_PERMISSION_COUNT');
$assert((int) $target->query('SELECT COUNT(*) FROM permissions')->fetchColumn() === $baselinePermissionCount + 1, 'permission delta is exactly +1');
$assert((int) $target->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn() === $baselineRolePermissionCount + 4, 'role permission delta is exactly +4');
$assert(
    $hashQuery($target, "SELECT id, code, description FROM permissions WHERE code <> 'notification.manage_preferences_own' ORDER BY id")
        === (string) getenv('TALENTHUB_PHASE8_BASELINE_PERMISSION_HASH'),
    'all baseline permissions are byte-stable after the exact +1 delta'
);
$assert(
    $hashQuery($target, "SELECT rp.roleId, rp.permissionId FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permissionId WHERE p.code <> 'notification.manage_preferences_own' ORDER BY rp.roleId, rp.permissionId")
        === (string) getenv('TALENTHUB_PHASE8_BASELINE_ROLE_PERMISSION_HASH'),
    'all baseline role mappings are byte-stable after the exact +4 delta'
);

$baselineMigrationCount = (int) getenv('TALENTHUB_PHASE8_BASELINE_MIGRATION_COUNT');
$assert((int) $target->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === $baselineMigrationCount + 2, 'Phase 8 applies exactly migrations 00600 and 00610 to the pinned baseline');
$phase8MigrationCount = (int) $target->query("SELECT COUNT(*) FROM schema_migrations WHERE version IN ('20260821000600','20260821000610')")->fetchColumn();
$assert($phase8MigrationCount === 2, 'both Phase 8 migrations are registered');

// Verify preservation of baseline 56 non-registry tables
$snapshotTable = static function (PDO $pdo, string $table): array {
    if (preg_match('/\A[a-z0-9_]+\z/', $table) !== 1) {
        throw new RuntimeException('Unsafe table metadata.');
    }
    $primary = $pdo->prepare("SELECT column_name FROM information_schema.key_column_usage WHERE table_schema=DATABASE() AND table_name=:table AND constraint_name='PRIMARY' ORDER BY ordinal_position");
    $primary->execute(['table' => $table]);
    $order = array_map(static fn (string $name): string => '`' . $name . '`', $primary->fetchAll(PDO::FETCH_COLUMN));
    $query = $pdo->query("SELECT * FROM `{$table}`" . ($order === [] ? '' : ' ORDER BY ' . implode(',', $order)));
    $hash = hash_init('sha256');
    $count = 0;
    while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
        hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        $count++;
    }
    return ['count' => $count, 'sha256' => hash_final($hash)];
};

$excludedTables = ['schema_migrations', 'permissions', 'role_permissions', 'notifications', 'learner_notification_preferences'];
$baselineTables = $source->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' AND table_name NOT IN ('" . implode("','", $excludedTables) . "') ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$assert(count($baselineTables) === 53, 'all 53 unchanged baseline tables are covered');
foreach ($baselineTables as $table) {
    $assert($snapshotTable($source, (string) $table) === $snapshotTable($target, (string) $table), "baseline table {$table} is preserved");
}

// Behavioral verification on target schema
$target->beginTransaction();
try {
    $userId = (string) $target->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    $studentId = (string) $target->query('SELECT id FROM student_profiles ORDER BY id LIMIT 1')->fetchColumn();
    $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

    // 1. Insert notification with eventKey
    $n1 = Uuid::v4();
    $target->prepare("INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, createdAt) VALUES (:id, :userId, 'event-1', 'activity_registration_created', 'Test Title', 'Test Msg', '/app/learner/my-activities.php', :now)")
        ->execute(['id' => $n1, 'userId' => $userId, 'now' => $now]);

    // 2. Duplicate eventKey for same user fails
    $expectDatabaseFailure(static function () use ($target, $userId, $now): void {
        $n2 = Uuid::v4();
        $target->prepare("INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, createdAt) VALUES (:id, :userId, 'event-1', 'activity_registration_created', 'Test Title 2', 'Test Msg 2', '/app/learner/my-activities.php', :now)")
            ->execute(['id' => $n2, 'userId' => $userId, 'now' => $now]);
    }, 'duplicate eventKey for same user is rejected');

    // 3. Multiple NULL eventKeys allowed
    $n3 = Uuid::v4();
    $n4 = Uuid::v4();
    $target->prepare("INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, createdAt) VALUES (:id, :userId, NULL, 'activity_registration_created', 'Title 3', 'Msg 3', NULL, :now)")
        ->execute(['id' => $n3, 'userId' => $userId, 'now' => $now]);
    $target->prepare("INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, createdAt) VALUES (:id, :userId, NULL, 'activity_registration_created', 'Title 4', 'Msg 4', NULL, :now)")
        ->execute(['id' => $n4, 'userId' => $userId, 'now' => $now]);

    // 4. Preference insert
    $target->prepare("INSERT INTO learner_notification_preferences (studentId, notificationType, inAppEnabled, emailEnabled, updatedAt) VALUES (:studentId, 'activity_registration_created', 1, 0, :now)")
        ->execute(['studentId' => $studentId, 'now' => $now]);

    // 5. Duplicate preference rejected
    $expectDatabaseFailure(static function () use ($target, $studentId, $now): void {
        $target->prepare("INSERT INTO learner_notification_preferences (studentId, notificationType, inAppEnabled, emailEnabled, updatedAt) VALUES (:studentId, 'activity_registration_created', 0, 1, :now)")
            ->execute(['studentId' => $studentId, 'now' => $now]);
    }, 'duplicate preference PK is rejected');

} finally {
    if ($target->inTransaction()) {
        $target->rollBack();
    }
}

echo "phase8_rehearsal_integrity_test: OK ({$assertions} assertions)\n";
