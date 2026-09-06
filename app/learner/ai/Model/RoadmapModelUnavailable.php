<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use RuntimeException;

/**
 * Thrown by `ModelRoadmapEngine` when the Gemini call cannot produce a
 * roadmap. The runtime must either retain the last-known-good model
 * roadmap or surface `ai_unavailable`; it must never substitute a
 * rule roadmap.
 *
 * Reasons are restricted to the allow-list the availability policy
 * recognises so that diagnostics, metrics and HTTP responses stay
 * machine-readable.
 */
final class RoadmapModelUnavailable extends RuntimeException
{
    /** @var list<string> */
    public const REASONS = [
        'model_disabled',
        'rate_limited',
        'provider_disabled',
        'provider_unavailable',
        'provider_rejected',
        'consent_revoked',
        'consent_missing',
        'consent_changed',
        'malformed_response',
        'invalid_request',
        'invalid_model_response',
    ];

    public function __construct(
        private readonly string $allowListedReason,
        ?string $message = null,
    ) {
        if (!in_array($allowListedReason, self::REASONS, true)) {
            throw new \InvalidArgumentException('RoadmapModelUnavailable reason is not allow-listed.');
        }
        parent::__construct($message ?? "Roadmap model is unavailable: {$allowListedReason}");
    }

    public function reason(): string
    {
        return $this->allowListedReason;
    }
}
