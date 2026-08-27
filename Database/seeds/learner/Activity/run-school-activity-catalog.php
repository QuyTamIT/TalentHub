<?php

declare(strict_types=1);

use TalentHub\Database\Connection;
use TalentHub\Database\ProtectedDatabasePolicy;
use TalentHub\Learner\Seeds\Activity\SchoolActivityCatalogSeeder;
use TalentHub\Learner\Seeds\Activity\SchoolActivityQrHandoff;

require_once dirname(__DIR__, 4) . '/bin/bootstrap.php';
require_once __DIR__ . '/SchoolActivityCatalogSeeder.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "REFUSED: activity catalog runner is CLI-only.\n");
    exit(2);
}

$schema = null;
$dryRun = false;
$apply = false;
$allowPrimary = false;
$qrOutputDirectory = null;
$qrPython = null;
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--schema=')) {
        $schema = substr($argument, strlen('--schema='));
    } elseif ($argument === '--dry-run') {
        $dryRun = true;
    } elseif ($argument === '--apply') {
        $apply = true;
    } elseif ($argument === '--allow-primary') {
        $allowPrimary = true;
    } elseif (str_starts_with($argument, '--qr-output-dir=')) {
        $qrOutputDirectory = substr($argument, strlen('--qr-output-dir='));
    } elseif (str_starts_with($argument, '--qr-python=')) {
        $qrPython = substr($argument, strlen('--qr-python='));
    } else {
        fwrite(STDERR, "REFUSED: unsupported argument.\n");
        exit(2);
    }
}

try {
    if (!is_string($schema) || !in_array($schema, [SchoolActivityCatalogSeeder::DISPOSABLE_SCHEMA, ProtectedDatabasePolicy::PRIMARY], true)) {
        throw new RuntimeException('Schema must be exactly talenthub_activity_phase4_disposable or talenthub.');
    }
    if ($dryRun === $apply) {
        throw new RuntimeException('Choose exactly one explicit mode: --dry-run or --apply.');
    }
    if ($allowPrimary && $schema !== ProtectedDatabasePolicy::PRIMARY) {
        throw new RuntimeException('--allow-primary is valid only with --schema=talenthub.');
    }
    if ($apply && $schema === ProtectedDatabasePolicy::PRIMARY && !$allowPrimary) {
        throw new RuntimeException('Primary apply requires --schema=talenthub --allow-primary --apply.');
    }
    if ($apply && $schema === ProtectedDatabasePolicy::PRIMARY && (!is_string($qrOutputDirectory) || $qrOutputDirectory === '')) {
        throw new RuntimeException('Primary apply requires --qr-output-dir=.codex_tmp/activity-qr-fixtures/talenthub-<timestamp>.');
    }
    if ($apply && (!is_string($qrOutputDirectory) || $qrOutputDirectory === '' || !is_string($qrPython) || $qrPython === '')) {
        throw new RuntimeException('Apply requires explicit --qr-output-dir and --qr-python bundled runtime path.');
    }
    if ($dryRun && ($qrOutputDirectory !== null || $qrPython !== null)) {
        throw new RuntimeException('Dry-run does not accept QR output or renderer arguments.');
    }

    $qrHandoff = $apply
        ? new SchoolActivityQrHandoff(dirname(__DIR__, 4), (string) $qrOutputDirectory, (string) $qrPython)
        : null;

    $config = require dirname(__DIR__, 4) . '/config/database.php';
    $config['database'] = $schema;
    $pdo = (new Connection($config))->connect();
    $actual = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($actual !== $schema) {
        throw new RuntimeException("Runner connection mismatch: expected {$schema}, got {$actual}.");
    }

    $seeder = new SchoolActivityCatalogSeeder(
        pdo: $pdo,
        expectedSchema: $schema,
        allowPrimary: $allowPrimary,
        clock: new DateTimeImmutable('now', new DateTimeZone('UTC')),
        qrHandoff: $qrHandoff,
    );
    $result = $dryRun ? $seeder->preflight(false) : $seeder->run();
    fwrite(STDOUT, json_encode([
        'mode' => $dryRun ? 'dry-run' : 'apply',
        'schema' => $schema,
        'result' => $result,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'REFUSED: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}
