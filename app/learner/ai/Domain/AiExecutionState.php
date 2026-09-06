<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

/**
 * Canonical AI execution states shared by the learner, school and enterprise
 * surfaces. Values are machine-readable and must never be silently downgraded
 * to a non-AI state (e.g. ready_rule) under the AI label.
 *
 * Legacy `ready_rule` and `ai_unavailable` strings are intentionally not
 * members of this enum. Strict-mode callers must translate the legacy
 * `ai_unavailable` response into `provider_unavailable` and a non-AI rule
 * surface must never be advertised as an AI execution result.
 */
final class AiExecutionState
{
    public const PENDING = 'pending';
    public const READY_MODEL = 'ready_model';
    public const PROVIDER_UNAVAILABLE = 'provider_unavailable';
    public const DATA_INSUFFICIENT = 'data_insufficient';
    public const CONSENT_REQUIRED = 'consent_required';
    public const STALE_MODEL = 'stale_model';

    /** @var list<string> */
    private const VALUES = [
        self::PENDING,
        self::READY_MODEL,
        self::PROVIDER_UNAVAILABLE,
        self::DATA_INSUFFICIENT,
        self::CONSENT_REQUIRED,
        self::STALE_MODEL,
    ];

    private function __construct()
    {
    }

    /** @return list<string> */
    public static function values(): array
    {
        return self::VALUES;
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::VALUES, true);
    }
}
