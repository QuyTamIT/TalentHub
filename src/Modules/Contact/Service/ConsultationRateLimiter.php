<?php

declare(strict_types=1);

namespace TalentHub\Modules\Contact\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;

final class ConsultationRateLimiter
{
    private const WINDOW_SECONDS = 600;
    private const BLOCK_SECONDS = 900;
    private const IDENTITY_LIMIT = 5;
    private const IP_LIMIT = 20;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function consume(string $identity, ?string $ip): void
    {
        $buckets = [
            ['scope' => 'identity', 'key' => hash('sha256', 'consultation:identity:' . strtolower(trim($identity))), 'limit' => self::IDENTITY_LIMIT],
        ];
        $normalizedIp = trim((string) $ip);
        if ($normalizedIp !== '') {
            $buckets[] = ['scope' => 'ip', 'key' => hash('sha256', 'consultation:ip:' . $normalizedIp), 'limit' => self::IP_LIMIT];
        }
        usort($buckets, static fn (array $left, array $right): int => strcmp($left['key'], $right['key']));

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $driver = strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        $retryAfter = 0;
        $this->pdo->beginTransaction();
        try {
            foreach ($buckets as $bucket) {
                $this->ensureBucket($driver, $bucket['key'], $bucket['scope'], $now);
                $lock = $driver === 'mysql' ? ' FOR UPDATE' : '';
                $statement = $this->pdo->prepare(
                    'SELECT failureCount, windowStartedAt, blockedUntil FROM auth_rate_limits WHERE bucketKey = ?' . $lock
                );
                $statement->execute([$bucket['key']]);
                $row = $statement->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    throw new \RuntimeException('Không thể đọc trạng thái giới hạn yêu cầu.');
                }

                $blockedUntil = $this->timestamp($row['blockedUntil'] ?? null);
                if ($blockedUntil > $now->getTimestamp()) {
                    $retryAfter = max($retryAfter, $blockedUntil - $now->getTimestamp());
                    continue;
                }

                $windowStartedAt = $this->timestamp($row['windowStartedAt'] ?? null);
                $expired = $windowStartedAt <= 0 || ($now->getTimestamp() - $windowStartedAt) >= self::WINDOW_SECONDS;
                $count = $expired ? 1 : ((int) $row['failureCount'] + 1);
                $window = $expired ? $this->format($now) : (string) $row['windowStartedAt'];
                $blocked = null;
                if ($count > $bucket['limit']) {
                    $blocked = $this->format($now->modify('+' . self::BLOCK_SECONDS . ' seconds'));
                    $retryAfter = max($retryAfter, self::BLOCK_SECONDS);
                }
                $update = $this->pdo->prepare(
                    'UPDATE auth_rate_limits SET failureCount = ?, windowStartedAt = ?, blockedUntil = ?, updatedAt = ? WHERE bucketKey = ?'
                );
                $update->execute([$count, $window, $blocked, $this->format($now), $bucket['key']]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        if ($retryAfter > 0) {
            throw new ApiException(
                429,
                'RATE_LIMIT_EXCEEDED',
                'Bạn đã gửi quá nhiều yêu cầu. Vui lòng thử lại sau.',
                [],
                ['Retry-After' => (string) $retryAfter],
            );
        }
    }

    private function ensureBucket(string $driver, string $key, string $scope, DateTimeImmutable $now): void
    {
        $sql = $driver === 'mysql'
            ? 'INSERT INTO auth_rate_limits(bucketKey, scope, failureCount, windowStartedAt, blockedUntil, updatedAt) VALUES(?, ?, 0, ?, NULL, ?) ON DUPLICATE KEY UPDATE bucketKey = VALUES(bucketKey)'
            : 'INSERT OR IGNORE INTO auth_rate_limits(bucketKey, scope, failureCount, windowStartedAt, blockedUntil, updatedAt) VALUES(?, ?, 0, ?, NULL, ?)';
        $statement = $this->pdo->prepare($sql);
        $statement->execute([$key, $scope, $this->format($now), $this->format($now)]);
    }

    private function format(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
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
