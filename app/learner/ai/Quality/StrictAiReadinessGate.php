<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Quality;

use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Provider\StrictAiUnavailable;
use Throwable;

/**
 * Shared readiness gate for strict-mode AI operations.
 *
 * `assertReady` performs the minimum sanity checks before a model engine is
 * allowed to make a provider call. The gate never calls the provider; it
 * only inspects the {@see RecommendationConfig} plus caller-supplied
 * readiness signals (migration status, snapshot availability, consent).
 *
 * Reasons raised by this gate are restricted to the
 * {@see StrictAiUnavailable::REASONS} allow-list so downstream services
 * can map them to {@see \TalentHub\Learner\Ai\Domain\AiExecutionState}
 * values without bespoke translation.
 */
final class StrictAiReadinessGate
{
    private const STRICT_ENVIRONMENTS = ['production', 'staging'];

    public function __construct(private readonly RecommendationConfig $config)
    {
    }

    public static function create(RecommendationConfig $config): self
    {
        return new self($config);
    }

    public function config(): RecommendationConfig
    {
        return $this->config;
    }

    /**
     * @param array<string,mixed> $signals Optional readiness signals:
     *  - migrations_ready (bool)
     *  - snapshot_present (bool)
     *  - snapshot_evidence_count (int) – minimum number of evidence rows
     *  - consent_ready (bool)
     *  - required_scopes (list<string>)
     *  - allowed_scopes (list<string>)
     */
    public function assertReady(string $studentId, string $operation, array $signals = []): void
    {
        $studentId = trim($studentId);
        $operation = trim($operation) === '' ? 'model.generate' : trim($operation);
        $context = sprintf('student=%s operation=%s', $studentId === '' ? 'unknown' : $studentId, $operation);

        if (!$this->config->enabled()) {
            throw new StrictAiUnavailable('model_disabled', "Strict AI model is disabled ({$context}).");
        }
        if (array_key_exists('migrations_ready', $signals) && $signals['migrations_ready'] === false) {
            throw new StrictAiUnavailable(
                'missing_migration',
                "Strict AI migrations are not applied ({$context}).",
            );
        }

        $snapshotPresent = $signals['snapshot_present'] ?? true;
        if ($snapshotPresent === false) {
            throw new StrictAiUnavailable('data_insufficient', "Strict AI snapshot is missing ({$context}).");
        }
        $evidenceCount = $signals['snapshot_evidence_count'] ?? null;
        if (is_int($evidenceCount) && $evidenceCount < 1) {
            throw new StrictAiUnavailable(
                'empty_snapshot',
                "Strict AI snapshot has no evidence rows ({$context}).",
            );
        }

        $consentReady = $signals['consent_ready'] ?? true;
        $consentState = is_string($signals['consent_state'] ?? null) ? strtolower(trim($signals['consent_state'])) : null;
        $requiredScopes = is_array($signals['required_scopes'] ?? null) ? $signals['required_scopes'] : [];
        $allowedScopes = is_array($signals['allowed_scopes'] ?? null) ? $signals['allowed_scopes'] : [];
        if ($consentReady === false) {
            $reason = $consentState === 'revoked' ? 'consent_revoked' : 'consent_missing';
            throw new StrictAiUnavailable(
                $reason,
                "Strict AI consent is not satisfied ({$context}).",
            );
        }
        if ($requiredScopes !== [] && $this->missingScopes($requiredScopes, $allowedScopes) !== []) {
            throw new StrictAiUnavailable(
                'consent_required',
                "Strict AI is missing required consent scopes ({$context}).",
            );
        }

        if (array_key_exists('provider_ready', $signals) && $signals['provider_ready'] === false) {
            throw new StrictAiUnavailable(
                'provider_unavailable',
                "Strict AI provider is not ready ({$context}).",
            );
        }
    }

    /**
     * Convenience helper used by orchestration code that wants to translate a
     * Throwable into a StrictAiUnavailable without losing the reason. When the
     * throwable already is StrictAiUnavailable the original instance is
     * returned so callers preserve the original message.
     */
    public static function rethrowAsStrict(Throwable $exception, string $fallbackReason = 'provider_unavailable'): StrictAiUnavailable
    {
        if ($exception instanceof StrictAiUnavailable) {
            return $exception;
        }
        $reason = in_array($fallbackReason, StrictAiUnavailable::REASONS, true)
            ? $fallbackReason
            : 'provider_unavailable';
        return new StrictAiUnavailable($reason, $exception->getMessage(), $exception);
    }

    /** @param list<string> $required @param list<string> $allowed @return list<string> */
    private function missingScopes(array $required, array $allowed): array
    {
        $allowedSet = [];
        foreach ($allowed as $scope) {
            if (is_string($scope) && trim($scope) !== '') {
                $allowedSet[trim($scope)] = true;
            }
        }
        $missing = [];
        foreach ($required as $scope) {
            if (!is_string($scope) || trim($scope) === '') {
                continue;
            }
            if (!isset($allowedSet[trim($scope)])) {
                $missing[] = trim($scope);
            }
        }
        $missing = array_values(array_unique($missing));
        sort($missing, SORT_STRING);
        return $missing;
    }
}
