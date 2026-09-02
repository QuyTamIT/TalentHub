<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

final class RoadmapRefinementUnavailable extends \RuntimeException
{
    private const REASONS = [
        'model_disabled', 'rate_limited', 'provider_unavailable', 'provider_rejected',
        'consent_revoked', 'consent_missing', 'consent_changed',
        'invalid_request', 'invalid_refinement_contract',
    ];

    public function __construct(private readonly string $allowListedReason)
    {
        if (!in_array($allowListedReason, self::REASONS, true)) {
            throw new \InvalidArgumentException('Roadmap refinement reason is not allow-listed.');
        }
        parent::__construct("Roadmap refinement is unavailable: {$allowListedReason}");
    }

    public function reason(): string
    {
        return $this->allowListedReason;
    }
}
