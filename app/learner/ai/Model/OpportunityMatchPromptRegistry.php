<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\OpportunityScore;
use TalentHub\Learner\Ai\Provider\ProviderRequest;

/**
 * Dedicated Gemini prompt, schema and payload for the learner Top 3
 * opportunity matching capability. The registry is the single source of
 * truth for the wire contract with the model and never invents catalog
 * ids, titles, providers, URLs, deadlines or capacity.
 */
final class OpportunityMatchPromptRegistry
{
    public const VERSION = 'learner-opportunity-match-1.0.0';

    public const MAX_CANDIDATES = 10;

    /**
     * @param list<OpportunityCandidate> $rankedCandidates
     * @param array<string,OpportunityScore> $structuredScores
     */
    public static function create(
        LearnerOpportunityProfile $profile,
        array $rankedCandidates,
        array $structuredScores,
        RecommendationContext $context,
    ): ProviderRequest {
        $sliced = array_slice($rankedCandidates, 0, self::MAX_CANDIDATES);
        $allowList = [];
        $skillCodes = [];
        $outcomeCodes = [];
        $evidenceRefs = [];
        $scoreById = [];
        foreach ($sliced as $candidate) {
            $payload = $candidate->providerPayload();
            $allowList[] = [
                'catalog_id' => $candidate->catalogId(),
                'catalog_type' => $candidate->catalogType(),
                'title' => $candidate->title(),
                'provider_name' => $candidate->providerName(),
                'summary' => $payload['summary'] ?? '',
                'category' => $payload['category'] ?? '',
                'difficulty' => $payload['difficulty'] ?? '',
                'required_skills' => $candidate->requiredSkills(),
                'learning_outcomes' => $candidate->learningOutcomes(),
                'education_bands' => $candidate->educationBands(),
                'deadline_at' => $payload['deadline_at'] ?? null,
                'availability' => $payload['availability'] ?? null,
                'url' => $candidate->canonicalUrl(),
                'evidence_refs' => $payload['evidence_refs'] ?? [],
            ];
            foreach ($candidate->requiredSkills() as $skill) {
                $skillCodes[$skill['code']] = true;
            }
            foreach ($candidate->learningOutcomes() as $outcome) {
                $outcomeCodes[$outcome['code']] = true;
            }
            foreach ($payload['evidence_refs'] ?? [] as $ref) {
                if (is_string($ref)) {
                    $evidenceRefs[$ref] = true;
                }
            }
            $id = $candidate->catalogId();
            if (isset($structuredScores[$id])) {
                $score = $structuredScores[$id];
                $scoreById[$id] = [
                    'structured_score' => $score->structuredScore(),
                    'breakdown' => $score->breakdown(),
                ];
            }
        }

        $payload = [
            'prompt_version' => self::VERSION,
            'system' => [
                'role' => 'You are a learner opportunity matching analyst. You rank canonical database opportunities and write evidence-backed, project-specific analyses for the student. You never invent canonical data; the database is the only source of truth for titles, providers, URLs, deadlines and capacity.',
            ],
            'instructions' => self::instructions(),
            'input' => [
                'student_profile' => self::profilePayload($profile),
                'candidate_allow_list' => $allowList,
                'skill_allow_list' => array_values(array_keys($skillCodes)),
                'outcome_allow_list' => array_values(array_keys($outcomeCodes)),
                'evidence_allow_list' => array_values(array_keys($evidenceRefs)),
                'structured_scores' => $scoreById,
                'context' => [
                    'request_id' => $context->requestId(),
                    'idempotency_key' => $context->idempotencyKey(),
                ],
            ],
            'output_schema' => self::outputSchema(),
        ];

        $evidenceByReference = [];
        $studentId = $context->studentId();
        $studentIdSuffix = is_string($studentId) && $studentId !== '' ? $studentId : 'student-1';
        $labelPrefix = "opportunity-match-{$studentIdSuffix}";
        foreach ($evidenceRefs as $ref => $_) {
            $evidenceByReference[$ref] = new RecommendationEvidence(
                'opportunity',
                $ref,
                null,
                "{$labelPrefix}-{$ref}",
                ['catalog_reference' => $ref],
            );
        }

        return new ProviderRequest(self::VERSION, $payload, $evidenceByReference);
    }

    /** @return list<string> */
    private static function instructions(): array
    {
        return [
            'Return exactly three distinct catalog IDs from the supplied candidate_allow_list.',
            'Write a project-specific why_fit for each candidate; do not reuse sentence templates.',
            'Use only supplied skill, outcome and evidence codes.',
            'Never invent a title, provider, URL, deadline, capacity, project or opportunity.',
            'Do not promise hiring, admission, awards, grades or employment.',
            'Reference only evidence_ref_ids present in the supplied evidence_allow_list.',
            'gemini_score must be an integer between 0 and 100 inclusive.',
        ];
    }

    /** @return array<string,mixed> */
    private static function outputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['items'],
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'minItems' => 3,
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'catalog_id', 'gemini_score', 'why_fit',
                            'matched_skill_codes', 'missing_skill_codes',
                            'expected_outcome_codes', 'evidence_ref_ids',
                        ],
                        'properties' => [
                            'catalog_id' => ['type' => 'string'],
                            'gemini_score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'why_fit' => ['type' => 'string', 'minLength' => 12],
                            'matched_skill_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'missing_skill_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'expected_outcome_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'evidence_ref_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function profilePayload(LearnerOpportunityProfile $profile): array
    {
        return [
            'education_band' => $profile->educationBand(),
            'skills' => $profile->skills(),
            'assessment_dimensions' => $profile->assessmentDimensions(),
            'experience_tags' => $profile->experienceTags(),
        ];
    }
}
