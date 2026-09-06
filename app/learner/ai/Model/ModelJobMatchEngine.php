<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use InvalidArgumentException;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Matching\JobMatchAnalysisValidator;
use TalentHub\Learner\Ai\Matching\JobMatchResult;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;

final class ModelJobMatchEngine
{
    public function __construct(
        private readonly RecommendationProvider $provider,
        private readonly ProviderAttemptAuthorizer $authorizer,
        private readonly ?JobMatchAnalysisValidator $validator = null,
    ) {
    }

    /**
     * @param list<OpportunityCandidate> $candidates
     * @param array<string,JobMatchResult> $matches
     * @param array<string,array<string,mixed>> $gaps
     * @return list<\TalentHub\Learner\Ai\Matching\JobMatchAnalysis>
     */
    public function generate(LearnerOpportunityProfile $profile, array $candidates, array $matches, array $gaps, RecommendationContext $context): array
    {
        if ($candidates === [] || count($candidates) > 10) {
            throw new InvalidArgumentException('Job match engine requires one to ten candidates.');
        }
        $request = JobMatchPromptRegistry::create($profile, $candidates, $matches, $gaps, $context);
        $response = $this->provider->generate($request, $this->authorizer);
        if (!$response->isSuccess()) {
            throw new InvalidArgumentException('Job match provider returned a failure: ' . ($response->errorCode() ?? 'provider_unavailable'));
        }
        return ($this->validator ?? new JobMatchAnalysisValidator())->validate(
            $response->items(), $candidates, $matches, $gaps, $profile,
        );
    }
}
