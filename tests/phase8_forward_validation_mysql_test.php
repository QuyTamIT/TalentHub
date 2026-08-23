<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationContext;

$config = require dirname(__DIR__) . '/config/database.php';
$schema = (string) ($config['database'] ?? '');
if (!filter_var(getenv('TALENTHUB_DISPOSABLE_TEST_DB'), FILTER_VALIDATE_BOOL)
    || preg_match('/\Atalenthub_phase8_rehearsal_\d{14}\z/', $schema) !== 1
) {
    fwrite(STDERR, "phase8_forward_validation_mysql_test: NOT RUN (exact disposable gate required)\n");
    exit(2);
}

$pdo = (new Connection($config))->connect();
$pdo->exec("SET time_zone = '+00:00'");
$migration = require dirname(__DIR__) . '/Database/migrations/20260821000610_validate_phase8_notification_contracts.php';
$context = new MigrationContext($pdo);

$pdo->exec('ALTER TABLE notifications DROP INDEX idx_notifications_user_unread');
$failedClosed = false;
try {
    $migration->preflight($context);
} catch (RuntimeException) {
    $failedClosed = true;
} finally {
    $pdo->exec('ALTER TABLE notifications ADD KEY idx_notifications_user_unread (userId, readAt, createdAt)');
}

if (!$failedClosed) {
    throw new RuntimeException('Phase 8 exact validation accepted a missing unread index.');
}

$migration->preflight($context);
echo "phase8_forward_validation_mysql_test: OK (conflict rejected, exact contract restored)\n";
