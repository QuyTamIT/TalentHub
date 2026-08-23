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
    $dump = (string) (getenv('TALENTHUB_PHASE7_BASELINE_DUMP') ?: '');
    if ($dump === '') {
        $candidates = glob(sys_get_temp_dir() . '/TalentHubBackups/talenthub_local_pre_phase7_20*.sql') ?: [];
        $candidates = array_values(array_filter($candidates, static fn (string $path): bool => !str_contains(basename($path), '_repair_')));
        sort($candidates, SORT_STRING);
        $dump = (string) ($candidates[0] ?? '');
    }
    if (!is_file($dump) || filesize($dump) === 0) {
        throw new RuntimeException('A non-empty pre-Phase-7 baseline dump is required.');
    }
    $expectedDumpHash = strtolower((string) (getenv('TALENTHUB_PHASE7_BASELINE_SHA256') ?: 'c7435080598d68e495fe4ed514868bbd0644a900c06341020df5c4f7692e4c8c'));
    if (preg_match('/\A[a-f0-9]{64}\z/', $expectedDumpHash) !== 1) {
        throw new RuntimeException('TALENTHUB_PHASE7_BASELINE_SHA256 must be an exact SHA-256 digest.');
    }
    $actualDumpHash = hash_file('sha256', $dump);
    if (!is_string($actualDumpHash) || !hash_equals($expectedDumpHash, strtolower($actualDumpHash))) {
        throw new RuntimeException('Pre-Phase-7 baseline dump SHA-256 verification failed.');
    }
    $schema = 'talenthub_phase7_rehearsal_' . gmdate('YmdHis');
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
        putenv('TALENTHUB_PHASE7_REHEARSAL_SCHEMA=' . $schema);
        $commands = [
            [PHP_BINARY, dirname(__DIR__) . '/bin/migrate.php', 'validate'],
            [PHP_BINARY, dirname(__DIR__) . '/bin/migrate.php', 'migrate'],
            [PHP_BINARY, dirname(__DIR__) . '/bin/migrate.php', 'migrate'],
            [PHP_BINARY, __FILE__, '--verify'],
            [PHP_BINARY, dirname(__DIR__) . '/tests/Integration/EnterpriseApplicationLifecycleTest.php'],
            [PHP_BINARY, dirname(__DIR__) . '/tests/learner_application_endpoint_runtime_test.php'],
        ];
        foreach ($commands as $command) {
            echo $run($command);
        }
    } finally {
        if (preg_match('/\Atalenthub_phase7_rehearsal_\d{14}\z/', $schema) === 1) {
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
    echo "phase7_rehearsal_orchestrator: OK ({$schema} created, verified, grant revoked, and schema dropped)\n";
    exit(0);
}

$schema = (string) (getenv('TALENTHUB_PHASE7_REHEARSAL_SCHEMA') ?: '');
if (preg_match('/\Atalenthub_phase7_rehearsal_\d{14}\z/', $schema) !== 1) {
    fwrite(STDERR, "phase7_rehearsal_integrity_test: REFUSED unsafe or missing rehearsal schema\n");
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

$phase7Tables = ['internship_posts', 'internship_applications', 'application_status_history', 'application_profile_snapshots'];
foreach ($phase7Tables as $table) {
    $statement = $target->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table AND table_type = \'BASE TABLE\'');
    $statement->execute(['table' => $table]);
    $assert((int) $statement->fetchColumn() === 1, "{$table} exists");
}

$column = static function (PDO $pdo, string $table, string $name): ?array {
    $statement = $pdo->prepare('SELECT data_type AS dataType, character_maximum_length AS maximumLength, is_nullable AS isNullable, column_default AS columnDefault FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1');
    $statement->execute(['table' => $table, 'column' => $name]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
};
$workType = $column($target, 'internship_posts', 'workType');
$educationLevel = $column($target, 'internship_posts', 'educationLevel');
$schemaVersion = $column($target, 'application_profile_snapshots', 'schemaVersion');
$assert(($workType['dataType'] ?? null) === 'varchar' && ($workType['columnDefault'] ?? null) === 'full_time', 'workType has exact default');
$assert(($educationLevel['dataType'] ?? null) === 'varchar' && (int) ($educationLevel['maximumLength'] ?? 0) === 100, 'educationLevel has exact length');
$assert($column($target, 'internship_applications', 'cvUrl') === null, 'obsolete cvUrl is absent');
$assert(($schemaVersion['dataType'] ?? null) === 'varchar' && ($schemaVersion['columnDefault'] ?? null) === '1.0.0', 'schemaVersion has exact default');

$columns = static function (PDO $pdo, string $table): array {
    $statement = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table ORDER BY ordinal_position');
    $statement->execute(['table' => $table]);
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
};
$assert($columns($target, 'internship_posts') === ['id','enterpriseId','title','field','status','location','workType','duration','educationLevel','description','benefits','skillsJson','requirementsJson','slots','deadline','createdAt','updatedAt'], 'internship_posts exact columns');
$assert($columns($target, 'internship_applications') === ['id','postId','studentId','status','message','reviewerNote','reviewedAt','reviewedBy','appliedAt','createdAt','updatedAt'], 'internship_applications exact columns');
$assert($columns($target, 'application_status_history') === ['id','applicationId','fromStatus','toStatus','changedByUserId','changedByRole','note','createdAt'], 'application_status_history exact columns');
$assert($columns($target, 'application_profile_snapshots') === ['id','applicationId','consentId','schemaVersion','snapshotPayload','createdAt'], 'application_profile_snapshots exact columns');

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
$assert($indexes($target, 'internship_posts') === [
    'PRIMARY' => ['id'],
    'idx_internship_posts_enterprise' => ['enterpriseId'],
    'idx_internship_posts_status_deadline' => ['status','deadline'],
], 'internship_posts exact indexes');
$assert($indexes($target, 'internship_applications') === [
    'PRIMARY' => ['id'],
    'idx_internship_applications_post_status' => ['postId','status'],
    'idx_internship_applications_reviewed_by' => ['reviewedBy'],
    'idx_internship_applications_student' => ['studentId'],
    'uq_internship_applications_post_student' => ['postId','studentId'],
], 'internship_applications exact indexes');
$assert($indexes($target, 'application_status_history') === [
    'PRIMARY' => ['id'],
    'idx_application_status_history_application' => ['applicationId','createdAt'],
    'idx_application_status_history_changed_by' => ['changedByUserId'],
], 'application_status_history exact indexes');
$assert($indexes($target, 'application_profile_snapshots') === [
    'PRIMARY' => ['id'],
    'idx_application_profile_snapshots_consent' => ['consentId'],
    'uq_application_profile_snapshots_application' => ['applicationId'],
], 'application_profile_snapshots exact indexes');

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
$assert($foreignKeys($target, 'internship_posts') === ['fk_internship_posts_enterprise' => ['enterprises','RESTRICT','CASCADE']], 'post foreign keys exact');
$assert($foreignKeys($target, 'internship_applications') === [
    'fk_internship_applications_post' => ['internship_posts','RESTRICT','CASCADE'],
    'fk_internship_applications_reviewer' => ['users','SET NULL','CASCADE'],
    'fk_internship_applications_student' => ['student_profiles','RESTRICT','CASCADE'],
], 'application foreign keys exact');
$assert($foreignKeys($target, 'application_status_history') === [
    'fk_application_status_history_application' => ['internship_applications','RESTRICT','CASCADE'],
    'fk_application_status_history_user' => ['users','RESTRICT','CASCADE'],
], 'history foreign keys exact');
$assert($foreignKeys($target, 'application_profile_snapshots') === [
    'fk_application_profile_snapshots_application' => ['internship_applications','RESTRICT','CASCADE'],
    'fk_application_profile_snapshots_consent' => ['privacy_consents','RESTRICT','CASCADE'],
], 'snapshot foreign keys exact');

$checks = static function (PDO $pdo, string $table): array {
    $statement = $pdo->prepare("SELECT constraint_name FROM information_schema.table_constraints WHERE table_schema=DATABASE() AND table_name=:table AND constraint_type='CHECK' ORDER BY constraint_name");
    $statement->execute(['table' => $table]);
    return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
};
$assert($checks($target, 'internship_posts') === ['chk_internship_posts_requirements_json','chk_internship_posts_skills_json','chk_internship_posts_slots','chk_internship_posts_status'], 'post checks exact');
$assert($checks($target, 'internship_applications') === ['chk_internship_applications_status'], 'application checks exact');
$assert($checks($target, 'application_status_history') === ['chk_application_status_history_status'], 'history checks exact');
$assert($checks($target, 'application_profile_snapshots') === ['chk_application_profile_snapshots_payload'], 'snapshot checks exact');

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
$baselineTables = $source->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE' AND table_name NOT IN ('schema_migrations','internship_posts','internship_applications','application_status_history','application_profile_snapshots') ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$assert(count($baselineTables) === 51, 'all 51 non-registry baseline tables are covered');
foreach ($baselineTables as $table) {
    $assert($snapshotTable($source, (string) $table) === $snapshotTable($target, (string) $table), "baseline table {$table} is preserved");
}
$sourceMigrations = (int) $source->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
$targetMigrations = (int) $target->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
$assert(in_array($targetMigrations - $sourceMigrations, [0, 1], true), 'target has only the forward repair delta before primary apply');
$assert((int) $target->query("SELECT COUNT(*) FROM schema_migrations WHERE version IN ('20260821000500','20260821000510','20260821000520')")->fetchColumn() === 3, 'all Phase 7 migrations are applied on rehearsal');

$enterpriseId = (string) $target->query('SELECT id FROM enterprises ORDER BY id LIMIT 1')->fetchColumn();
$student = $target->query('SELECT id, userId FROM student_profiles ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$assert($enterpriseId !== '' && is_array($student), 'canonical parent fixtures exist');
$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
$deadline = (new DateTimeImmutable('+30 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
$target->beginTransaction();
try {
    $postId = Uuid::v4();
    $post = $target->prepare("INSERT INTO internship_posts (id, enterpriseId, title, field, status, location, workType, duration, educationLevel, description, skillsJson, slots, deadline, createdAt, updatedAt) VALUES (:id,:enterpriseId,'Gate','IT','active','Remote','hybrid','3 months','university','Gate post','[]',1,:deadline,:createdAt,:updatedAt)");
    $post->execute(['id' => $postId, 'enterpriseId' => $enterpriseId, 'deadline' => $deadline, 'createdAt' => $now, 'updatedAt' => $now]);
    $expectDatabaseFailure(static function () use ($target, $enterpriseId, $deadline, $now): void {
        $statement = $target->prepare("INSERT INTO internship_posts (id, enterpriseId, title, field, status, location, duration, educationLevel, description, skillsJson, slots, deadline, createdAt, updatedAt) VALUES (:id,:enterpriseId,'Invalid JSON','IT','active','Remote','3 months','university','Bad','{',1,:deadline,:createdAt,:updatedAt)");
        $statement->execute(['id' => Uuid::v4(), 'enterpriseId' => $enterpriseId, 'deadline' => $deadline, 'createdAt' => $now, 'updatedAt' => $now]);
    }, 'invalid JSON is rejected');
    $expectDatabaseFailure(static function () use ($target, $enterpriseId, $deadline, $now): void {
        $statement = $target->prepare("INSERT INTO internship_posts (id, enterpriseId, title, field, status, location, duration, educationLevel, description, skillsJson, slots, deadline, createdAt, updatedAt) VALUES (:id,:enterpriseId,'Invalid status','IT','published','Remote','3 months','university','Bad','[]',1,:deadline,:createdAt,:updatedAt)");
        $statement->execute(['id' => Uuid::v4(), 'enterpriseId' => $enterpriseId, 'deadline' => $deadline, 'createdAt' => $now, 'updatedAt' => $now]);
    }, 'invalid post status is rejected');

    $consentId = Uuid::v4();
    $target->prepare("INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, createdAt) VALUES (:id,:studentId,'application_profile_share',1,'phase7-v1',:grantedAt,:createdAt)")->execute(['id' => $consentId, 'studentId' => $student['id'], 'grantedAt' => $now, 'createdAt' => $now]);
    $applicationId = Uuid::v4();
    $expectDatabaseFailure(static function () use ($target, $postId, $student, $now): void {
        $target->prepare("INSERT INTO internship_applications (id, postId, studentId, status, appliedAt, createdAt, updatedAt) VALUES (:id,:postId,:studentId,'invalid',:appliedAt,:createdAt,:updatedAt)")->execute(['id' => Uuid::v4(), 'postId' => $postId, 'studentId' => $student['id'], 'appliedAt' => $now, 'createdAt' => $now, 'updatedAt' => $now]);
    }, 'invalid application status is rejected');
    $target->prepare("INSERT INTO internship_applications (id, postId, studentId, status, appliedAt, createdAt, updatedAt) VALUES (:id,:postId,:studentId,'submitted',:appliedAt,:createdAt,:updatedAt)")->execute(['id' => $applicationId, 'postId' => $postId, 'studentId' => $student['id'], 'appliedAt' => $now, 'createdAt' => $now, 'updatedAt' => $now]);
    $target->prepare("INSERT INTO application_status_history (id, applicationId, fromStatus, toStatus, changedByUserId, changedByRole, createdAt) VALUES (:id,:applicationId,NULL,'submitted',:userId,'student',:now)")->execute(['id' => Uuid::v4(), 'applicationId' => $applicationId, 'userId' => $student['userId'], 'now' => $now]);
    $expectDatabaseFailure(static function () use ($target, $applicationId, $consentId, $now): void {
        $target->prepare("INSERT INTO application_profile_snapshots (id, applicationId, consentId, snapshotPayload, createdAt) VALUES (:id,:applicationId,:consentId,'{',:now)")->execute(['id' => Uuid::v4(), 'applicationId' => $applicationId, 'consentId' => $consentId, 'now' => $now]);
    }, 'invalid snapshot JSON is rejected');
    $target->prepare("INSERT INTO application_profile_snapshots (id, applicationId, consentId, snapshotPayload, createdAt) VALUES (:id,:applicationId,:consentId,'{}',:now)")->execute(['id' => Uuid::v4(), 'applicationId' => $applicationId, 'consentId' => $consentId, 'now' => $now]);
    $expectDatabaseFailure(static function () use ($target, $postId, $student, $now): void {
        $target->prepare("INSERT INTO internship_applications (id, postId, studentId, status, appliedAt, createdAt, updatedAt) VALUES (:id,:postId,:studentId,'submitted',:appliedAt,:createdAt,:updatedAt)")->execute(['id' => Uuid::v4(), 'postId' => $postId, 'studentId' => $student['id'], 'appliedAt' => $now, 'createdAt' => $now, 'updatedAt' => $now]);
    }, 'duplicate post/student application is rejected');
    $expectDatabaseFailure(static function () use ($target, $applicationId, $consentId, $now): void {
        $target->prepare("INSERT INTO application_profile_snapshots (id, applicationId, consentId, snapshotPayload, createdAt) VALUES (:id,:applicationId,:consentId,'{}',:now)")->execute(['id' => Uuid::v4(), 'applicationId' => $applicationId, 'consentId' => $consentId, 'now' => $now]);
    }, 'duplicate application snapshot is rejected');
    $expectDatabaseFailure(static function () use ($target, $applicationId): void {
        $target->prepare('DELETE FROM internship_applications WHERE id = :id')->execute(['id' => $applicationId]);
    }, 'history and snapshot block application hard delete');
} finally {
    if ($target->inTransaction()) {
        $target->rollBack();
    }
}

echo "phase7_rehearsal_integrity_test: OK ({$assertions} assertions)\n";
