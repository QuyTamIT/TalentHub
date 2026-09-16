<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Database\ApplicationSnapshotVersions;
use TalentHub\Support\Uuid;

// Deliberately CLI-only, single application, explicit owner. No automatic live backfill.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    if (count($argv) !== 3 || !Uuid::isValid($argv[1]) || !Uuid::isValid($argv[2])) {
        throw new InvalidArgumentException('Usage: php bin/recover-application-snapshot.php APPLICATION_ID STUDENT_ID');
    }
    $pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
    $result = (new ApplicationSnapshotVersions($pdo))->recover($argv[1], $argv[2]);
    fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
