<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Provider;

use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Consent\ProviderConsentDenied;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;

final class FakeRecommendationProvider implements RecommendationProvider
{
    /** @var list<ProviderRequest> */
    private array $requests = [];

    public function __construct(private readonly ProviderResponse $response)
    {
    }

    public function generate(ProviderRequest $request, ProviderAttemptAuthorizer $authorizer): ProviderResponse
    {
        try {
            $authorizer->beforeAttempt(1);
        } catch (ProviderConsentDenied $exception) {
            return ProviderResponse::failure($exception->reason());
        }
        $this->requests[] = $request;
        return $this->response;
    }

    /** @return list<ProviderRequest> */
    public function requests(): array
    {
        return $this->requests;
    }
}
