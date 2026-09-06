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
        string $mode = 'top3',
        array $analysisContext = [],
    ): array {
        if ($mode === 'top3' && count($rankedCandidates) < 3) {
            throw new InvalidArgumentException('Opportunity match engine requires at least three valid candidates.');
        }
        if (!in_array($mode, ['top3', 'recommendation', 'low_fit'], true)) {
            throw new InvalidArgumentException('Unsupported opportunity match engine mode.');
        }
        if ($mode !== 'top3' && count($rankedCandidates) < 1) {
            throw new InvalidArgumentException('Opportunity match engine requires at least one valid candidate.');
        }

        $candidateAllowList = array_slice($rankedCandidates, 0, OpportunityMatchPromptRegistry::MAX_CANDIDATES);
        $request = OpportunityMatchPromptRegistry::create(
            $profile,
            $candidateAllowList,
            $structuredScores,
            $context,
            $mode,
            $analysisContext,
        );

        $response = $this->provider->generate($request, $this->authorizer);
        if (!$response->isSuccess()) {
            throw new InvalidArgumentException('Opportunity match provider returned a failure: ' . (string) $response->errorCode());
        }

        $items = self::stripProviderFabricatedFields($response->items(), $mode);

        $validator = $this->validator ?? new OpportunityMatchValidator();
        return $validator->validate($items, $candidateAllowList, $profile, $mode);
    }

    /** @return array<string,mixed> */
    public function generateNoFitSummary(
        LearnerOpportunityProfile $profile,
        array $structuredScores,
        RecommendationContext $context,
        array $analysisContext = [],
        array $evidenceAllowList = [],
    ): array {
        $request = OpportunityMatchPromptRegistry::create(
            $profile,
            [],
            $structuredScores,
            $context,
            'no_fit',
            $analysisContext,
        );
        $response = $this->provider->generate($request, $this->authorizer);
        if (!$response->isSuccess()) {
            throw new InvalidArgumentException('Opportunity match provider returned a failure: ' . (string) $response->errorCode());
        }
        $items = $response->items();
        if (count($items) !== 1 || !is_array($items[0])) {
            throw new InvalidArgumentException('Opportunity match no-fit summary must contain exactly one object.');
        }
        $validator = $this->validator ?? new OpportunityMatchValidator();
        return $validator->validateSummary($items[0], $profile, $evidenceAllowList);
    }

    private const PROVIDER_FABRICATED_FIELDS = [
        'title', 'url', 'provider', 'provider_name', 'deadline', 'deadline_at',
        'capacity', 'availability', 'summary', 'category', 'difficulty',
    ];

    /**
     * Gemini occasionally includes fields from a neighbouring output mode
     * even though the request schema declares additionalProperties=false.
     * They are not safe to pass to the mode-specific validator: a positive
     * explanation is not a low-fit diagnostic and vice versa. Keep the
     * validator strict for unknown fields while dropping only these known,
     * mutually exclusive fields at the provider boundary.
     */
    private const LOW_FIT_ONLY_FIELDS = [
        'why_not_fit_yet', 'missing_conditions', 'improvement_steps',
    ];

    private const POSITIVE_ONLY_FIELDS = [
        'why_fit', 'expected_outcome_codes',
    ];

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private static function stripProviderFabricatedFields(array $items, string $mode): array
    {
        $fields = self::PROVIDER_FABRICATED_FIELDS;
        if ($mode === 'low_fit') {
            $fields = [...$fields, ...self::POSITIVE_ONLY_FIELDS];
        } else {
            $fields = [...$fields, ...self::LOW_FIT_ONLY_FIELDS];
        }

        $cleaned = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                $cleaned[] = $item;
                continue;
            }
            $cleaned[] = array_diff_key($item, array_flip($fields));
        }
        return $cleaned;
    }
}
