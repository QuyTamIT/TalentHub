<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Rollout;

final class PilotEligibility
{
    public function __construct(private readonly bool $eligible, private readonly string $reason) {}
    public function eligible(): bool { return $this->eligible; }
    public function reason(): string { return $this->reason; }
}
