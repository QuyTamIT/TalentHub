<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Security;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use TalentHub\Http\ApiException;
use Throwable;

final class PersistentActionRateLimiter
{
    /** @var array<string,array{identity:int,ip:int,window:int,block:int}> */
    private const DEFAULT_POLICIES = [
        'learner.checkin' => ['identity' => 10, 'ip' => 60, 'window' => 60, 'block' => 60],
        'learner.application' => ['identity' => 5, 'ip' => 30, 'window' => 300, 'block' => 300],
        'learner.ai' => ['identity' => 3, 'ip' => 30, 'window' => 60, 'block' => 60],
    ];

    /** @var Closure():int */
    private readonly Closure $clock;

    /** @param array<string,array{identity:int,ip:int,window:int,block:int}>|null $policies */
    public function __construct(
        private readonly PDO $pdo,
        ?callable $clock = null,
        private readonly ?array $policies = null,
    ) {
        $this->clock = Closure::fromCallable($clock ?? static fn (): int => time());
    }

    public function consume(string $action, string $identity, ?string $ip): void
    {
        $policy = ($this->policies ?? self::DEFAULT_POLICIES)[$action] ?? null;
        if ($policy === null) {
            throw new InvalidArgumentException('Unknown rate-limit action.');
        }
        $identity = trim($identity);
        if ($identity === '') {
            throw new InvalidArgumentException('Rate-limit identity is required.');
        }
        foreach (['identity', 'ip', 'window', 'block'] as $field) {
            if (($policy[$field] ?? 0) < 1) {
                throw new InvalidArgumentException('Rate-limit policies must use positive integers.');
            }
        }

        $buckets = [
            ['scope' => 'identity', 'key' => hash('sha256', "{$action}:identity:{$identity}"), 'limit' => $policy['identity']],
        ];
        $normalizedIp = trim((string) $ip);
        if ($normalizedIp !== '') {
            $buckets[] = ['scope' => 'ip', 'key' => hash('sha256', "{$action}:ip:{$normalizedIp}"), 'limit' => $policy['ip']];
        }
        usort($buckets, static fn (array $left, array $right): int => strcmp($left['key'], $right['key']));

        $now = ($this->clock)();
        $nowValue = $this->dateValue($now);
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $deniedRetryAfter = 0;

        $this->pdo->beginTransaction();
        try {
            $states = [];
            foreach ($buckets as $bucket) {
                $this->ensureBucket($driver, $bucket['key'], $bucket['scope'], $nowValue);
                $statement = $this->pdo->prepare(
                    'SELECT failureCount, windowStartedAt, blockedUntil FROM auth_rate_limits WHERE bucketKey = ?'
                    . ($driver === 'mysql' ? ' FOR UPDATE' : '')
                );
                $statement->execute([$bucket['key']]);
                $row = $statement->fetch();
                if (!is_array($row)) {
                    throw new \RuntimeException('Rate-limit bucket could not be loaded.');
                }
                $blockedUntil = $this->timestamp($row['blockedUntil'] ?? null);
                if ($blockedUntil > $now) {
                    $deniedRetryAfter = max($deniedRetryAfter, $blockedUntil - $now);
                }
                $windowStartedAt = $this->timestamp($row['windowStartedAt'] ?? null);
                $windowExpired = $windowStartedAt <= 0 || ($now - $windowStartedAt) >= $policy['window'];
                $states[] = [
                    ...$bucket,
                    'count' => $windowExpired ? 1 : ((int) $row['failureCount'] + 1),
                    'window' => $windowExpired ? $nowValue : (string) $row['windowStartedAt'],
                ];
            }

            if ($deniedRetryAfter === 0) {
                foreach ($states as $state) {
                    if ($state['count'] > $state['limit']) {
                        $blockedUntil = $now + $policy['block'];
                        $this->updateBucket($state['key'], $state['count'], $state['window'], $this->dateValue($blockedUntil), $nowValue);
                        $deniedRetryAfter = max($deniedRetryAfter, $policy['block']);
                    }
                }
            }

            if ($deniedRetryAfter === 0) {
                foreach ($states as $state) {
                    $this->updateBucket($state['key'], $state['count'], $state['window'], null, $nowValue);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        if ($deniedRetryAfter > 0) {
            throw new ApiException(
                429,
                'RATE_LIMIT_EXCEEDED',
                'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau.',
                [],
                ['Retry-After' => (string) max(1, min(999999, $deniedRetryAfter))],
            );
        }
    }

    private function ensureBucket(string $driver, string $key, string $scope, string $now): void
    {
        $sql = $driver === 'mysql'
            ? 'INSERT INTO auth_rate_limits(bucketKey, scope, failureCount, windowStartedAt, blockedUntil, updatedAt) VALUES(?, ?, 0, ?, NULL, ?) ON DUPLICATE KEY UPDATE bucketKey = VALUES(bucketKey)'
            : 'INSERT OR IGNORE INTO auth_rate_limits(bucketKey, scope, failureCount, windowStartedAt, blockedUntil, updatedAt) VALUES(?, ?, 0, ?, NULL, ?)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$key, $scope, $now, $now]);
    }

    private function updateBucket(string $key, int $count, string $window, ?string $blockedUntil, string $now): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE auth_rate_limits SET failureCount = ?, windowStartedAt = ?, blockedUntil = ?, updatedAt = ? WHERE bucketKey = ?'
        );
        $statement->execute([$count, $window, $blockedUntil, $now, $key]);
    }

    private function dateValue(int $timestamp): string
    {
        return (new DateTimeImmutable("@{$timestamp}"))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s.u');
    }

    private function timestamp(mixed $value): int
    {
        if (!is_string($value) || trim($value) === '') {
            return 0;
        }
        $timestamp = strtotime($value . ' UTC');
        return $timestamp === false ? 0 : $timestamp;
    }
}
