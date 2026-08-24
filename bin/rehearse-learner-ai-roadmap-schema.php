<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\SchemaInspector;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigrationRunner;

$root = dirname(__DIR__);
require_once $root . '/bin/bootstrap.php';
require_once $root . '/app/learner/data/bootstrap.php';

$appEnv = TalentHub\Config\Environment::appEnvironment();
if (!in_array($appEnv, ['local', 'test'], true)) {
    fwrite(STDERR, "Roadmap schema rehearsal is restricted to APP_ENV=local|test.\n");
    exit(2);
}

$config = require $root . '/config/database.php';
$database = 'talenthub_codex_roadmap_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(3));
if (preg_match('/\Atalenthub_codex_roadmap_[0-9]{8}_[0-9]{6}_[a-f0-9]{6}\z/', $database) !== 1) {
    throw new RuntimeException('Unsafe rehearsal database name.');
}
$serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']);
$server = new PDO($serverDsn, $config['username'], $config['password'], $config['options']);
$server->exec("SET SESSION time_zone = '+00:00'");
$quoted = '`' . $database . '`';
$created = false;
$stage = 'connect_server';
$result = ['success' => false, 'database_prefix' => 'talenthub_codex_roadmap_', 'used_local_admin' => false, 'first_apply' => [], 'second_apply' => [], 'new_table_count' => 0, 'sentinel_unchanged' => false, 'cleaned_up' => false];

try {
    $stage = 'create_database';
    try {
        $server->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $exception) {
        $host = strtolower(trim((string) $config['host']));
        if (($exception->errorInfo[0] ?? null) !== '42000'
            || $appEnv !== 'local'
            || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw $exception;
        }
        $server = new PDO($serverDsn, 'root', '', $config['options']);
        $server->exec("SET SESSION time_zone = '+00:00'");
        $server->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $result['used_local_admin'] = true;
    }
    $created = true;
    $stage = 'connect_target';
    $targetConfig = $config;
    $targetConfig['database'] = $database;
    $target = $result['used_local_admin']
        ? new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $database), 'root', '', $config['options'])
        : (new TalentHub\Database\Connection($targetConfig))->connect();
    $target->exec("SET SESSION time_zone = '+00:00'");
    $stage = 'create_baseline';
    $target->exec("CREATE TABLE student_profiles (id CHAR(36) NOT NULL PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $target->exec("CREATE TABLE learner_recommendation_runs (id CHAR(36) NOT NULL PRIMARY KEY, studentId CHAR(36) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $target->exec("CREATE TABLE learner_forward_migrations (version VARCHAR(191) PRIMARY KEY, name VARCHAR(255) NOT NULL, checksum CHAR(64) NOT NULL, description TEXT NOT NULL, appliedAt VARCHAR(40) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $target->exec("CREATE TABLE roadmap_rehearsal_sentinel (id INT NOT NULL PRIMARY KEY, payload VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $target->exec("INSERT INTO roadmap_rehearsal_sentinel (id,payload) VALUES (1,'unchanged-before-and-after')");
    $checksum = TalentHub\Learner\Data\Migrations\LearnerMigrationChecksum::canonical($root . '/Database/migrations/learner/004_create_recommendation_store.php');
    $record = $target->prepare('INSERT INTO learner_forward_migrations (version,name,checksum,description,appliedAt) VALUES (?,?,?,?,?)');
    $record->execute(['004_create_recommendation_store','Create learner recommendation store',$checksum,'Verified rehearsal dependency',gmdate('c')]);
    $before = hash('sha256', (string) $target->query("SELECT CONCAT(id,':',payload) FROM roadmap_rehearsal_sentinel ORDER BY id")->fetchColumn());

    $stage = 'apply_migration';
    $inspector = new SchemaInspector($target, $database);
    $runner = new LearnerForwardMigrationRunner($target, $root . '/Database/migrations/learner', $inspector);
    $result['first_apply'] = $runner->migrateApproved(['005_create_ai_roadmap_store']);
    $result['second_apply'] = $runner->migrateApproved(['005_create_ai_roadmap_store']);
    $stage = 'verify_migration';
    $count = $target->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('learner_ai_roadmaps','learner_ai_roadmap_phases','learner_ai_roadmap_tasks','learner_ai_roadmap_task_events')")->fetchColumn();
    $result['new_table_count'] = (int) $count;
    $after = hash('sha256', (string) $target->query("SELECT CONCAT(id,':',payload) FROM roadmap_rehearsal_sentinel ORDER BY id")->fetchColumn());
    $result['sentinel_unchanged'] = hash_equals($before, $after);
    $result['success'] = $result['first_apply'] === ['005_create_ai_roadmap_store']
        && $result['second_apply'] === []
        && $result['new_table_count'] === 4
        && $result['sentinel_unchanged'];
} catch (Throwable $exception) {
    $result['error_code'] = 'roadmap_schema_rehearsal_failed';
    $result['failure_stage'] = $stage;
    $result['sql_state'] = $exception instanceof PDOException && is_string($exception->errorInfo[0] ?? null)
        ? $exception->errorInfo[0]
        : null;
} finally {
    if ($created) {
        $server->exec("DROP DATABASE {$quoted}");
        $result['cleaned_up'] = true;
    }
}

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($result['success'] && $result['cleaned_up'] ? 0 : 1);
