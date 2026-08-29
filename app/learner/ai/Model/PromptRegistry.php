<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Provider\ProviderRequest;

final class PromptRegistry
{
    public const VERSION = 'learner-recommendation-1.0.1';

    public function create(RecommendationInput $input, RecommendationContext $context): ProviderRequest
    {
        $evidence = [];
        $byReference = [];
        foreach ($input->evidenceReferences() as $index => $reference) {
            $referenceId = sprintf('evidence-%03d', $index + 1);
            $record = new RecommendationEvidence(
                (string) $reference['source_type'],
                (string) $reference['source_id'],
                is_string($reference['observed_at']) ? $reference['observed_at'] : null,
                'provider_source',
                is_array($reference['safe_value']) ? $reference['safe_value'] : [],
            );
            $byReference[$referenceId] = $record;
            $evidence[] = [
                'reference_id' => $referenceId,
                'source_type' => $record->sourceType(),
                'observed_at' => $record->observedAt(),
                'safe_value' => $record->safeValue(),
            ];
        }

        return new ProviderRequest(self::VERSION, [
            'prompt_version' => self::VERSION,
            'instructions' => [
                'Return JSON with an items array only.',
                'Return 3 to 6 useful recommendations whenever the supplied evidence permits it; never return an empty items array.',
                'If no catalog opportunity is eligible, use evidence-backed development, activity, presentation-practice, or career-group actions instead of returning no items.',
                'Every item must include a concise reason or explanation grounded in its evidence.',
                'Use catalog_id only when it matches a supplied catalog evidence source; never invent catalog IDs.',
                'Use only supplied evidence records; never invent a project or opportunity, title, partner, identifier, deadline, location, or URL.',
                'Treat an item as an enterprise opportunity only when it cites supplied evidence whose source_type is opportunity and opportunity_type is internship.',
                'For an enterprise internship, set catalog_id to that opportunity source ID and use action.type open_catalog_item with the same catalog_id.',
                'Every item must cite one or more supplied evidence_ref_ids.',
                'item_type must be one of strength, improvement, development, activity, roadmap, group, community.',
                'action.type must be one of develop_skill, continue_technical_activity, practice_presentation, explore_career_group, register_activity, join_group, open_catalog_item.',
                'Use only the fields required by the selected action type; action catalog_id and activity_source_id must come from supplied catalog evidence.',
                'Do not infer diagnoses, protected traits, admissions outcomes, or hiring outcomes.',
                'Do not include a source ID, prompt, raw snapshot, or provider metadata in an item.',
            ],
            'allowed_scopes' => $context->allowedScopes(),
            'input_quality' => $this->safeQualityFlags($input->qualityFlags()),
            'input' => $this->safeInput($input->payload()),
            'evidence' => $evidence,
            'output_schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['items'],
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['item_type', 'title', 'summary', 'priority', 'confidence_band', 'action', 'evidence_ref_ids'],
                            'properties' => [
                                'item_type' => ['type' => 'string', 'enum' => ['strength', 'improvement', 'development', 'activity', 'roadmap', 'group', 'community']],
                                'title' => ['type' => 'string', 'minLength' => 1],
                                'summary' => ['type' => 'string', 'minLength' => 1],
                                'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                                'confidence_band' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                                'category' => ['type' => 'string'],
                                'catalog_id' => ['type' => 'string'],
                                'reason' => ['type' => 'string'],
                                'explanation' => ['type' => 'string'],
                                'reason_codes' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['career_match', 'eligible_catalog', 'skill_match', 'deadline_soon']]],
                                'action' => [
                                    'oneOf' => [
                                        ['type' => 'object', 'additionalProperties' => false, 'required' => ['type', 'skill_code'], 'properties' => ['type' => ['const' => 'develop_skill'], 'skill_code' => ['type' => 'string']]],
                                        ['type' => 'object', 'additionalProperties' => false, 'required' => ['type', 'activity_source_id'], 'properties' => ['type' => ['const' => 'continue_technical_activity'], 'activity_source_id' => ['type' => 'string']]],
                                        ['type' => 'object', 'additionalProperties' => false, 'required' => ['type', 'weeks'], 'properties' => ['type' => ['const' => 'practice_presentation'], 'weeks' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12], 'steps' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']]]],
                                        ['type' => 'object', 'additionalProperties' => false, 'required' => ['type', 'career_group'], 'properties' => ['type' => ['const' => 'explore_career_group'], 'career_group' => ['type' => 'string', 'enum' => ['technical', 'business', 'arts', 'sports_academic']]]],
                                        ['type' => 'object', 'additionalProperties' => false, 'required' => ['type', 'career_group', 'activity_source_id'], 'properties' => ['type' => ['const' => 'register_activity'], 'career_group' => ['type' => 'string', 'enum' => ['technical', 'business', 'arts', 'sports_academic']], 'activity_source_id' => ['type' => 'string']]],
                                        ['type' => 'object', 'additionalProperties' => false, 'required' => ['type', 'catalog_id'], 'properties' => ['type' => ['const' => 'join_group'], 'catalog_id' => ['type' => 'string']]],
                                        ['type' => 'object', 'additionalProperties' => false, 'required' => ['type', 'catalog_id'], 'properties' => ['type' => ['const' => 'open_catalog_item'], 'catalog_id' => ['type' => 'string']]],
                                    ],
                                ],
                                'evidence_ref_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'string']],
                            ],
                        ],
                    ],
                ],
            ],
        ], $byReference);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function safeInput(array $payload): array
    {
        if (!is_array($payload['sources'] ?? null)) {
            return $payload;
        }
        $safe = [];
        foreach ($payload['sources'] as $source) {
            if (!is_array($source)) continue;
            unset($source['evidence_ref'], $source['source_id']);
            $safe[] = $source;
        }
        $payload['sources'] = $safe;
        return $payload;
    }

    /** @param array<string,mixed> $flags @return array<string,mixed> */
    private function safeQualityFlags(array $flags): array
    {
        return [
            'allowed_scopes' => is_array($flags['allowed_scopes'] ?? null) ? array_values($flags['allowed_scopes']) : [],
            'missing_consent_scopes' => is_array($flags['missing_consent_scopes'] ?? null) ? array_values($flags['missing_consent_scopes']) : [],
            'missing_source_types' => is_array($flags['missing_source_types'] ?? null) ? array_values($flags['missing_source_types']) : [],
            'source_counts' => is_array($flags['source_counts'] ?? null) ? $flags['source_counts'] : [],
            'source_availability' => is_array($flags['source_availability'] ?? null) ? $flags['source_availability'] : [],
            'blocked_catalog_types' => is_array($flags['blocked_catalog_types'] ?? null) ? array_values($flags['blocked_catalog_types']) : [],
        ];
    }
}
