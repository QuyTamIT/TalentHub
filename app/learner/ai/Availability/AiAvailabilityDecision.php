<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Availability;

final class AiAvailabilityDecision
{
    /** @var list<string> */
    private const STATES = ['ready_model', 'ready_rule', 'stale_model', 'pending', 'ai_unavailable'];

    public function __construct(
        private readonly string $state,
        private readonly string $reason,
        private readonly bool $canRefresh,
        private readonly bool $canRunShadow,
        private readonly bool $canShowModel,
        private readonly bool $canServeActiveModel,
        private readonly bool $canServeStaleModel,
    ) {
        if (!in_array($state, self::STATES, true) || trim($reason) === '') {
            throw new \InvalidArgumentException('AI availability decision is invalid.');
        }
        if ($canShowModel && !$canRefresh) {
            throw new \InvalidArgumentException('A visible model refresh must be refreshable.');
        }
        if ($canServeActiveModel && $canServeStaleModel) {
            throw new \InvalidArgumentException('An AI model cannot be fresh and stale simultaneously.');
        }
    }

    public function state(): string { return $this->state; }
    public function reason(): string { return $this->reason; }
    public function canRefresh(): bool { return $this->canRefresh; }
    public function canRunShadow(): bool { return $this->canRunShadow; }
    public function canShowModel(): bool { return $this->canShowModel; }
    public function canServeActiveModel(): bool { return $this->canServeActiveModel; }
    public function canServeStaleModel(): bool { return $this->canServeStaleModel; }
}
