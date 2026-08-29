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
    public const VERSION = 'learner-opportunity-match-1.1.0';

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
        string $mode = 'top3',
        array $analysisContext = [],
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
        foreach ($profile->evidenceRefs() as $ref) {
            $evidenceRefs[$ref] = true;
        }

        $payload = [
            'prompt_version' => self::VERSION,
            'system' => [
                'role' => 'You are a learner opportunity matching analyst. You rank canonical database opportunities and write evidence-backed, project-specific analyses for the student. You never invent canonical data; the database is the only source of truth for titles, providers, URLs, deadlines and capacity.',
            ],
            'instructions' => self::instructions($mode),
            'input' => [
                'student_profile' => self::profilePayload($profile),
                'candidate_allow_list' => $allowList,
                'skill_allow_list' => array_values(array_keys($skillCodes)),
                'outcome_allow_list' => array_values(array_keys($outcomeCodes)),
                'evidence_allow_list' => array_values(array_keys($evidenceRefs)),
                'structured_scores' => $scoreById,
                'analysis_context' => $analysisContext,
                'context' => [
                    'request_id' => $context->requestId(),
                    'idempotency_key' => $context->idempotencyKey(),
                ],
            ],
            'output_schema' => self::outputSchema($mode),
        ];

        $evidenceByReference = [];
        $studentId = $context->studentId();
        $studentIdSuffix = is_string($studentId) && $studentId !== '' ? $studentId : 'student-1';
        $labelPrefix = "opportunity-match-{$studentIdSuffix}";
        foreach ($evidenceRefs as $ref => $_) {
            $parts = explode(':', $ref, 2);
            $sourceType = count($parts) === 2 && in_array($parts[0], [
                'profile', 'skill', 'assessment', 'achievement', 'certificate', 'project',
                'activity', 'activity_experience', 'checkin', 'badge', 'progress',
                'evaluation', 'mentor_evaluation', 'teacher_feedback', 'roadmap_feedback',
                'opportunity', 'catalog',
            ], true) ? $parts[0] : 'profile';
            $sourceId = count($parts) === 2 && trim($parts[1]) !== '' ? $parts[1] : $ref;
            $evidenceByReference[$ref] = new RecommendationEvidence(
                $sourceType,
                $sourceId,
                null,
                "{$labelPrefix}-{$ref}",
                ['reference' => $ref],
            );
        }

        return new ProviderRequest(self::VERSION, $payload, $evidenceByReference);
    }

    /** @return list<string> */
    private static function instructions(string $mode): array
    {
        $locale = [
            'Ngôn ngữ đầu ra bắt buộc là vi-VN. Viết toàn bộ nội dung hướng tới người học bằng tiếng Việt có dấu, tự nhiên, rõ ràng và phù hợp với học sinh, sinh viên.',
            'Không hiển thị mã kỹ năng hoặc mã điều kiện trong headline, explanation, why_fit, why_not_fit_yet, main_gaps, next_steps hay improvement_steps; hãy diễn đạt chúng thành tên tiếng Việt dễ hiểu.',
            'Các trường có hậu tố _codes và evidence_ref_ids vẫn phải giữ đúng mã trong allow-list để hệ thống kiểm chứng.',
        ];
        if ($mode === 'no_fit') {
            return [...$locale, ...[
                'Return a grounded summary explaining why no current opportunity reaches the suitable threshold.',
                'Use only the supplied learner strengths, catalog demand aggregates and exclusion reason counts.',
                'Never invent a title, provider, URL, deadline, capacity, project or opportunity.',
                'Do not promise hiring, admission, awards, grades or employment.',
                'Reference only evidence_ref_ids present in the supplied evidence_allow_list.',
            ]];
        }
        if ($mode === 'low_fit') {
            return [...$locale, ...[
                'Return one to three distinct catalog IDs from the supplied candidate_allow_list.',
                'Explain why each project is not suitable yet and give concrete improvement steps.',
                'Use only supplied skill, outcome and evidence codes and condition codes from analysis_context.',
                'Never invent a title, provider, URL, deadline, capacity, project or opportunity.',
                'Do not promise hiring, admission, awards, grades or employment.',
                'Reference only evidence_ref_ids present in the supplied evidence_allow_list.',
                'gemini_score must be an integer between 0 and 100 inclusive.',
            ]];
        }
        if ($mode === 'recommendation') {
            return [...$locale, ...[
                'Return one to three distinct catalog IDs from the supplied candidate_allow_list.',
                'Write a project-specific why_fit for each candidate; do not reuse sentence templates.',
                'Use only supplied skill, outcome and evidence codes.',
                'Never invent a title, provider, URL, deadline, capacity, project or opportunity.',
                'Do not promise hiring, admission, awards, grades or employment.',
                'Reference only evidence_ref_ids present in the supplied evidence_allow_list.',
                'gemini_score must be an integer between 0 and 100 inclusive.',
            ]];
        }
        return [...$locale, ...[
            'Return exactly three distinct catalog IDs from the supplied candidate_allow_list.',
            'Write a project-specific why_fit for each candidate; do not reuse sentence templates.',
            'Use only supplied skill, outcome and evidence codes.',
            'Never invent a title, provider, URL, deadline, capacity, project or opportunity.',
            'Do not promise hiring, admission, awards, grades or employment.',
            'Reference only evidence_ref_ids present in the supplied evidence_allow_list.',
            'gemini_score must be an integer between 0 and 100 inclusive.',
        ]];
    }

    /** @return array<string,mixed> */
    private static function outputSchema(string $mode): array
    {
        if ($mode === 'no_fit') {
            return [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['items'],
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 1,
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['headline', 'explanation', 'learner_strengths', 'catalog_demands', 'main_gaps', 'next_steps', 'evidence_ref_ids'],
                            'properties' => [
                                'headline' => ['type' => 'string', 'minLength' => 12],
                                'explanation' => ['type' => 'string', 'minLength' => 24],
                                'learner_strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'catalog_demands' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'main_gaps' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'next_steps' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
                                'evidence_ref_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
                            ],
                        ],
                    ],
                ],
            ];
        }
        $lowFit = $mode === 'low_fit';
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['items'],
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'minItems' => $mode === 'top3' ? 3 : 1,
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => [
                            'catalog_id', 'gemini_score', $lowFit ? 'why_not_fit_yet' : 'why_fit',
                            'matched_skill_codes', 'missing_skill_codes',
                            ...($lowFit ? ['missing_conditions', 'improvement_steps'] : ['expected_outcome_codes']),
                            'evidence_ref_ids',
                        ],
                        'properties' => [
                            'catalog_id' => ['type' => 'string'],
                            'gemini_score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                            'why_fit' => ['type' => 'string', 'minLength' => 12],
                            'why_not_fit_yet' => ['type' => 'string', 'minLength' => 12],
                            'matched_skill_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'missing_skill_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'missing_conditions' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'improvement_steps' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
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
