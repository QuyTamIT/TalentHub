<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\RateLimit;

use Closure;

final class RecommendationRateLimiter
{
    /** @var Closure():int */
    private readonly Closure $clock;
    /** @var list<int> */
    private array $global = [];
    /** @var array<string,list<int>> */
    private array $students = [];

    /** @param callable():int $clock */
    public function __construct(
        private readonly int $perStudentLimit,
        private readonly int $globalLimit,
        private readonly int $windowSeconds,
        callable $clock,
    ) {
        if ($perStudentLimit < 1 || $globalLimit < 1 || $windowSeconds < 1) {
            throw new \InvalidArgumentException('Recommendation rate limits must be positive.');
        }
        $this->clock = Closure::fromCallable($clock);
    }

    public function acquire(string $studentId): RecommendationRateLimitDecision
    {
        $studentId = trim($studentId);
        if ($studentId === '') {
            return new RecommendationRateLimitDecision(false, $this->windowSeconds);
        }
        $now = ($this->clock)();
        $threshold = $now - $this->windowSeconds;
        $this->global = array_values(array_filter($this->global, static fn (int $time): bool => $time > $threshold));
        $this->students[$studentId] = array_values(array_filter($this->students[$studentId] ?? [], static fn (int $time): bool => $time > $threshold));
        if (count($this->students[$studentId]) >= $this->perStudentLimit || count($this->global) >= $this->globalLimit) {
            return new RecommendationRateLimitDecision(false, $this->windowSeconds);
        }
        $this->students[$studentId][] = $now;
        $this->global[] = $now;
        return new RecommendationRateLimitDecision(true);
    }
}
