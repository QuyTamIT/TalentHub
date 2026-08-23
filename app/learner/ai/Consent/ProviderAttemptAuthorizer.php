<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Consent;

interface ProviderAttemptAuthorizer
{
    public function beforeAttempt(int $attemptNumber): ConsentDecision;
}
