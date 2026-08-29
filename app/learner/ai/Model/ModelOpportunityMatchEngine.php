<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use InvalidArgumentException;
use TalentHub\Learner\Ai\Consent\ProviderAttemptAuthorizer;
use TalentHub\Learner\Ai\Contracts\RecommendationProvider;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\OpportunityMatch;
use TalentHub\Learner\Ai\Matching\OpportunityMatchValidator;
use TalentHub\Learner\Ai\Matching\OpportunityScore;
use TalentHub\Learner\Ai\Provider\ProviderRequest;
use TalentHub\Learner\Ai\Provider\ProviderResponse;

/**
 * Provider mapper for the learner Top 3 opportunity matching capability.
 * The engine is the only place that calls Gemini for opportunity
 * analyses. It builds the request through OpportunityMatchPromptRegistry,
 * ignores any title or URL the model returns, and runs every response
 * through OpportunityMatchValidator before producing a list of
 * OpportunityMatch value objects. Final 70/30 match scores are attached
 * by the service layer via OpportunityMatch::withScore().
 */
final class ModelOpportunityMatchEngine
{
    public function __construct(
        private readonly RecommendationProvider $provider,
        private readonly ProviderAttemptAuthorizer $authorizer,
        private readonly ?OpportunityMatchValidator $validator = null,
    ) {
    }

    /**
     * @param list<OpportunityCandidate> $rankedCandidates
     * @param array<string,OpportunityScore> $structuredScores
     * @return list<OpportunityMatch>
     */
    public function generate(
        LearnerOpportunityProfile $profile,
        array $rankedCandidates,
        array $structuredScores,
        RecommendationContext $context,
    ): array {
        if (count($rankedCandidates) < 3) {
            throw new InvalidArgumentException('Opportunity match engine requires at least three valid candidates.');
        }

        $candidateAllowList = array_slice($rankedCandidates, 0, OpportunityMatchPromptRegistry::MAX_CANDIDATES);
        $request = OpportunityMatchPromptRegistry::create(
            $profile,
            $candidateAllowList,
            $structuredScores,
            $context,
        );

        $response = $this->provider->generate($request, $this->authorizer);
        if (!$response->isSuccess()) {
            throw new InvalidArgumentException('Opportunity match provider returned a failure: ' . (string) $response->errorCode());
        }

        $items = self::stripProviderFabricatedFields($response->items());

        $validator = $this->validator ?? new OpportunityMatchValidator();
        return $validator->validate($items, $candidateAllowList, $profile);
    }

    private const PROVIDER_FABRICATED_FIELDS = [
        'title', 'url', 'provider', 'provider_name', 'deadline', 'deadline_at',
        'capacity', 'availability', 'summary', 'category', 'difficulty',
    ];

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private static function stripProviderFabricatedFields(array $items): array
    {
        $cleaned = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                $cleaned[] = $item;
                continue;
            }
            $cleaned[] = array_diff_key($item, array_flip(self::PROVIDER_FABRICATED_FIELDS));
        }
        return $cleaned;
    }
}
