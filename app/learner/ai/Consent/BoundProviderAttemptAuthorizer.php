<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Consent;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;

final class BoundProviderAttemptAuthorizer implements ProviderAttemptAuthorizer
{
    private int $attemptsAuthorized = 0;

    public function __construct(
        private readonly ProviderConsentGate $gate,
        private readonly string $studentId,
        private readonly RecommendationInput $input,
        private readonly RecommendationContext $context,
    ) {}

    public function beforeAttempt(int $attemptNumber): ConsentDecision
    {
        if ($attemptNumber < 1) {
            throw new \InvalidArgumentException('Provider attempt number must be positive.');
        }
        $decision = $this->gate->authorize($this->studentId, $this->input, $this->context);
        $this->attemptsAuthorized++;
        return $decision;
    }

    public function attemptsAuthorized(): int { return $this->attemptsAuthorized; }
}
