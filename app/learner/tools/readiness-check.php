<?php

declare(strict_types=1);

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Readiness\GitScopeGuard;
use TalentHub\Learner\Data\Readiness\PhaseRequirements;
use TalentHub\Learner\Data\Readiness\ReadinessChecker;

$repositoryRoot = dirname(__DIR__, 3);
require_once $repositoryRoot . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/data/bootstrap.php';

function readiness_cli_error(string $message, string $format): never
{
    if ($format === 'json') {
        fwrite(STDOUT, json_encode(['status' => 'NOT_READY', 'exit_code' => 2, 'error' => $message], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    } else {
        fwrite(STDERR, "NOT_READY: {$message}" . PHP_EOL);
    }
    exit(2);
}

$options = getopt('', ['phase:', 'format:', 'reviewed-hash:']);
$format = strtolower((string) ($options['format'] ?? 'text'));
if (!in_array($format, ['text', 'json'], true)) {
    readiness_cli_error('Format must be text or json.', 'text');
}
if (!isset($options['phase']) || filter_var($options['phase'], FILTER_VALIDATE_INT) === false) {
    readiness_cli_error('Phase must be an integer between 0 and 11.', $format);
}

$phase = (int) $options['phase'];
if ($phase < 0 || $phase > 11) {
    readiness_cli_error('Phase must be between 0 and 11.', $format);
}

try {
    $reviewedProtectedHashes = [];
    $reviewedHashArguments = $options['reviewed-hash'] ?? [];
    if (is_string($reviewedHashArguments)) {
        $reviewedHashArguments = [$reviewedHashArguments];
    }
    if (!is_array($reviewedHashArguments)) {
        readiness_cli_error('Reviewed hashes must use path=sha256.', $format);
    }
    foreach ($reviewedHashArguments as $argument) {
        if (!is_string($argument) || !str_contains($argument, '=')) {
            readiness_cli_error('Reviewed hashes must use path=sha256.', $format);
        }
        [$path, $hash] = explode('=', $argument, 2);
        $path = str_replace('\\', '/', trim($path));
        if (!in_array($path, GitScopeGuard::REVIEWABLE_PROTECTED_PATHS, true)
            || preg_match('/\A[0-9a-f]{64}\z/i', $hash) !== 1) {
            readiness_cli_error('Reviewed hash path or SHA-256 is not allowed.', $format);
        }
        $reviewedProtectedHashes[$path] = strtolower($hash);
    }
    $checker = new ReadinessChecker(new PhaseRequirements(), new GitScopeGuard($reviewedProtectedHashes));
    $result = $checker->check(
        $phase,
        $repositoryRoot,
        static function () use ($repositoryRoot): PDO {
            $config = require $repositoryRoot . '/config/database.php';
            return (new Connection($config))->connect();
        }
    );
    $payload = $result->toArray();
    if ($format === 'json') {
        fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
    } else {
        fwrite(STDOUT, "Phase {$payload['phase']}: {$payload['status']} (exit {$payload['exit_code']})" . PHP_EOL);
        foreach ($payload['passes'] as $pass) {
            fwrite(STDOUT, "PASS [{$pass['check']}] {$pass['message']}" . PHP_EOL);
        }
        foreach ($payload['failures'] as $failure) {
            fwrite(STDOUT, "FAIL [{$failure['check']}] {$failure['message']}" . PHP_EOL);
        }
    }
    exit($result->exitCode());
} catch (Throwable) {
    readiness_cli_error('Readiness check could not be completed.', $format);
}
