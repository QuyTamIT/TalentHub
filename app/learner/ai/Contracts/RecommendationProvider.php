<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Contracts;

use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderResponse;

interface RecommendationProvider
{
    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse;
}
