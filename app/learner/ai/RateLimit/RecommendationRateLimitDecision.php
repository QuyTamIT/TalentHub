<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\RateLimit;

final class RecommendationRateLimitDecision
{
    public function __construct(private readonly bool $allowed, private readonly ?int $retryAfterSeconds = null)
    {
    }

    public function allowed(): bool { return $this->allowed; }
    public function retryAfterSeconds(): ?int { return $this->retryAfterSeconds; }
}
