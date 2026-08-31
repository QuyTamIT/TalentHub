<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Provider;

final class RetryPolicy
{
    public function __construct(private readonly int $maxAttempts = 3, private readonly int $baseDelayMs = 250, private readonly int $maxDelayMs = 5000) {}
    public function maxAttempts(): int { return max(1, $this->maxAttempts); }
    public function shouldRetry(int $status, ?string $error = null, int $attempt = 1): bool
    {
        if ($attempt >= $this->maxAttempts()) return false;
        return in_array($status, [408, 425, 429, 500, 502, 503, 504], true) || in_array($error, ['network','timeout','provider_unavailable'], true);
    }
    public function delayMs(int $attempt, ?int $retryAfter = null): int
    {
        if ($retryAfter !== null) return min($this->maxDelayMs, max(0, $retryAfter * 1000));
        $exp = min($this->maxDelayMs, $this->baseDelayMs * (2 ** max(0, $attempt - 1)));
        return random_int((int) floor($exp * 0.8), $exp);
    }
}
