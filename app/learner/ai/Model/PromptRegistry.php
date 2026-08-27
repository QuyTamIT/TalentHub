<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Model;

use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Provider\ProviderRequest;

final class PromptRegistry
{
    public const VERSION = 'learner-recommendation-1.0.0';

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
                'Every item must include a concise reason or explanation grounded in its evidence.',
                'Use catalog_id only when it matches a supplied catalog evidence source; never invent catalog IDs.',
                'Every item must cite one or more supplied evidence_ref_ids.',
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
                                'item_type' => ['type' => 'string'],
                                'title' => ['type' => 'string', 'minLength' => 1],
                                'summary' => ['type' => 'string', 'minLength' => 1],
                                'priority' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                                'confidence_band' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                                'category' => ['type' => 'string'],
                                'catalog_id' => ['type' => 'string'],
                                'reason' => ['type' => 'string'],
                                'explanation' => ['type' => 'string'],
                                'reason_codes' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['career_match', 'eligible_catalog', 'skill_match', 'deadline_soon']]],
                                'action' => ['type' => 'object'],
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
