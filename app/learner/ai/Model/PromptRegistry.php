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
                'Every item must cite one or more supplied evidence_ref_ids.',
                'Do not infer diagnoses, protected traits, admissions outcomes, or hiring outcomes.',
                'Do not include a source ID, prompt, raw snapshot, or provider metadata in an item.',
            ],
            'allowed_scopes' => $context->allowedScopes(),
            'input' => $input->payload(),
            'evidence' => $evidence,
        ], $byReference);
    }
}
