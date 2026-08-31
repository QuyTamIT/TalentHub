<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

use RuntimeException;
use Throwable;

/**
 * Domain error raised when a strict-mode AI operation cannot be served.
 *
 * The reason is restricted to the canonical allow-list so the readiness gate,
 * recommendation engine, roadmap engine and worker can exchange machine
 * readable failure categories without leaking internal taxonomy. The
 * exception must be caught by orchestration code and translated into one of
 * the {@see \TalentHub\Learner\Ai\Domain\AiExecutionState} states
 * (typically `provider_unavailable` or `pending`).
 */
final class StrictAiUnavailable extends RuntimeException
{
    /** @var list<string> */
    public const REASONS = [
        'provider_unavailable',
        'consent_missing',
        'consent_required',
        'consent_changed',
        'consent_revoked',
        'data_insufficient',
        'missing_migration',
        'empty_snapshot',
        'model_disabled',
        'rate_limited',
    ];

    public function __construct(
        private readonly string $allowListedReason,
        ?string $message = null,
        ?Throwable $previous = null,
    ) {
        if (!in_array($allowListedReason, self::REASONS, true)) {
            throw new \InvalidArgumentException('StrictAiUnavailable reason is not allow-listed.');
        }
        parent::__construct($message ?? "Strict AI is unavailable: {$allowListedReason}", 0, $previous);
    }

    public function reason(): string
    {
        return $this->allowListedReason;
    }
}
