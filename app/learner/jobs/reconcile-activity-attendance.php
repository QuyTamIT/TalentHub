<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__, 3) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Database\DatabaseActivityAttendanceReconciliationRepository;
use TalentHub\Learner\Data\Service\ActivityAttendanceReconciliationService;

$schema = null;
$graceHours = 24;
$limit = 100;
$dryRun = false;
$allowPrimary = false;

try {
    foreach (array_slice($argv, 1) as $argument) {
        if (str_starts_with($argument, '--schema=')) {
            if ($schema !== null) throw new InvalidArgumentException('Schema may be specified only once.');
            $schema = substr($argument, strlen('--schema='));
        } elseif (str_starts_with($argument, '--grace-hours=')) {
            $value = substr($argument, strlen('--grace-hours='));
            if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) throw new InvalidArgumentException('Grace hours must be positive.');
            $graceHours = (int) $value;
        } elseif (str_starts_with($argument, '--limit=')) {
            $value = substr($argument, strlen('--limit='));
            if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) throw new InvalidArgumentException('Limit must be positive.');
            $limit = (int) $value;
        } elseif ($argument === '--dry-run') {
            $dryRun = true;
        } elseif ($argument === '--allow-primary') {
            $allowPrimary = true;
        } else {
            throw new InvalidArgumentException('Unsupported reconciliation option.');
        }
    }

    $allowedSchemas = ['talenthub', 'talenthub_local', 'talenthub_activity_phase9_disposable'];
    if (!is_string($schema) || !in_array($schema, $allowedSchemas, true)) {
        throw new InvalidArgumentException('Schema is not allowed for attendance reconciliation.');
    }
    if ($graceHours < 1) throw new InvalidArgumentException('Grace hours must be positive.');
    if ($limit < 1 || $limit > 1000) throw new InvalidArgumentException('Limit must be between 1 and 1000.');
    if ($allowPrimary && $schema !== 'talenthub') throw new InvalidArgumentException('--allow-primary is valid only for talenthub.');
    if (!$dryRun && $schema === 'talenthub' && !$allowPrimary) throw new RuntimeException('Primary apply requires explicit --allow-primary.');
    if (!$dryRun && $schema === 'talenthub_local') throw new RuntimeException('Apply is forbidden on talenthub_local.');

    $configuration = require dirname(__DIR__, 3) . '/config/database.php';
    $configuration['database'] = $schema;
    $pdo = (new Connection($configuration))->connect();
    $actualSchema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($actualSchema !== $schema) throw new RuntimeException('Reconciliation connection does not match --schema.');
    if (!$dryRun && $schema === 'talenthub_activity_phase9_disposable' && $actualSchema !== 'talenthub_activity_phase9_disposable') {
        throw new RuntimeException('Disposable apply requires the exact Phase 9 schema.');
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $repository = new DatabaseActivityAttendanceReconciliationRepository($pdo);
    if ($dryRun) {
        $candidates = $repository->previewDueNoShows($now, $graceHours, $limit);
        $output = ['mode' => 'dry-run', 'schema' => $schema, 'grace_hours' => $graceHours, 'limit' => $limit, 'candidate_count' => count($candidates)];
    } else {
        $reconciled = (new ActivityAttendanceReconciliationService($repository))->run($now, $graceHours, $limit);
        $output = ['mode' => 'apply', 'schema' => $schema, 'grace_hours' => $graceHours, 'limit' => $limit, 'reconciled_count' => count($reconciled)];
    }
    fwrite(STDOUT, json_encode($output, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (InvalidArgumentException|RuntimeException $exception) {
    fwrite(STDERR, 'Reconciliation refused: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Reconciliation failed safely (' . basename(str_replace('\\', '/', get_class($exception))) . ').' . PHP_EOL);
    exit(3);
}
