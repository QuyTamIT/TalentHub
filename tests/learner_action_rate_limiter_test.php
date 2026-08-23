<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/Security/PersistentActionRateLimiter.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Security\PersistentActionRateLimiter;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(<<<'SQL'
    CREATE TABLE auth_rate_limits (
        bucketKey TEXT PRIMARY KEY,
        scope TEXT NOT NULL CHECK (scope IN ('identity', 'ip')),
        failureCount INTEGER NOT NULL DEFAULT 0,
        windowStartedAt TEXT NOT NULL,
        blockedUntil TEXT NULL,
        updatedAt TEXT NOT NULL
    )
SQL);

$now = 1_800_000_000;
$clock = static function () use (&$now): int {
    return $now;
};
$limiter = new PersistentActionRateLimiter($pdo, $clock, [
    'learner.checkin' => ['identity' => 2, 'ip' => 4, 'window' => 60, 'block' => 60],
    'learner.application' => ['identity' => 1, 'ip' => 2, 'window' => 300, 'block' => 300],
]);

$limiter->consume('learner.checkin', 'student-1', '127.0.0.1');
$limiter->consume('learner.checkin', 'student-1', '127.0.0.1');
try {
    $limiter->consume('learner.checkin', 'student-1', '127.0.0.1');
    $assert(false, 'Third check-in request must be rate limited.');
} catch (ApiException $exception) {
    $assert($exception->status === 429, 'Rate limit uses HTTP 429.');
    $assert($exception->errorCode === 'RATE_LIMIT_EXCEEDED', 'Rate limit uses the stable error code.');
    $assert(($exception->headers['Retry-After'] ?? '') === '60', 'Rate limit returns a safe Retry-After value.');
}

$limiter->consume('learner.checkin', 'student-2', '127.0.0.2');
$limiter->consume('learner.application', 'student-1', '127.0.0.1');
try {
    $limiter->consume('learner.application', 'student-1', '127.0.0.1');
    $assert(false, 'Application policy is isolated and enforced.');
} catch (ApiException $exception) {
    $assert(($exception->headers['Retry-After'] ?? '') === '300', 'Application policy exposes its own retry window.');
}

$now += 301;
$limiter->consume('learner.application', 'student-1', '127.0.0.1');

try {
    $limiter->consume('unknown.action', 'student-1', '127.0.0.1');
    $assert(false, 'Unknown actions fail closed.');
} catch (InvalidArgumentException) {
    // Expected.
}

$rows = $pdo->query('SELECT bucketKey, scope FROM auth_rate_limits')->fetchAll();
$assert(count($rows) >= 4, 'Identity and IP buckets are persisted separately.');
foreach ($rows as $row) {
    $assert(preg_match('/\A[a-f0-9]{64}\z/', (string) $row['bucketKey']) === 1, 'Raw identity and IP values are never stored.');
    $assert(in_array($row['scope'], ['identity', 'ip'], true), 'Existing checked scope values are preserved.');
}

echo "learner_action_rate_limiter_test: OK\n";
