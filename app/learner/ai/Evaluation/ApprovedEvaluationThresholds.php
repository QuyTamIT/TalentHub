<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Evaluation;

final class ApprovedEvaluationThresholds
{
    private function __construct(
        private readonly bool $approved,
        private readonly ?float $p50Max,
        private readonly ?float $p95Max,
        private readonly ?float $errorRateMax,
        private readonly ?float $costPerRunMax,
        private readonly ?string $currency,
        private readonly ?string $version,
    ) {}
    public static function missing(): self { return new self(false, null, null, null, null, null, null); }
    public static function approved(float $p50Max, float $p95Max, float $errorRateMax, float $costPerRunMax, string $currency, string $version): self
    {
        if ($p50Max < 0 || $p95Max < $p50Max || $errorRateMax < 0 || $errorRateMax > 1 || $costPerRunMax < 0
            || preg_match('/\A[A-Z]{3}\z/', $currency) !== 1 || trim($version) === '') throw new \InvalidArgumentException('Evaluation thresholds are invalid.');
        return new self(true, $p50Max, $p95Max, $errorRateMax, $costPerRunMax, $currency, $version);
    }
    public function isApproved(): bool { return $this->approved; }
    /** @return array<string,mixed> */ public function toArray(): array { return [
        'p50_max_ms' => $this->p50Max, 'p95_max_ms' => $this->p95Max, 'provider_error_rate_max' => $this->errorRateMax,
        'cost_per_run_max' => $this->costPerRunMax, 'currency' => $this->currency, 'version' => $this->version,
    ]; }
}
