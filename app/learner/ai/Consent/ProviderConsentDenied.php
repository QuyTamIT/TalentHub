<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Consent;

final class ProviderConsentDenied extends \RuntimeException
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct('Provider execution is not authorized by current learner consent.');
    }

    public function reason(): string { return $this->reason; }
}
