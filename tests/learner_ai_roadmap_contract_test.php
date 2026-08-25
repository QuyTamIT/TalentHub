<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/fixtures/learner_ai_roadmap_v1.php';

function roadmap_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function roadmap_contract_expect(callable $operation, string $messagePart): void
{
    try {
        $operation();
    } catch (InvalidArgumentException|RuntimeException $exception) {
        roadmap_contract_assert(
            str_contains($exception->getMessage(), $messagePart),
            'expected message containing "' . $messagePart . '", got "' . $exception->getMessage() . '"',
        );
        return;
    }
    throw new RuntimeException('Assertion failed: expected exception containing ' . $messagePart);
}

roadmap_contract_assert(class_exists(RoadmapAnalysisValidator::class), 'RoadmapAnalysisValidator is loaded by AI bootstrap');

$allowedEvidence = ['evidence-001', 'evidence-002', 'evidence-003', 'evidence-004'];
$validator = new RoadmapAnalysisValidator($allowedEvidence, []);
$analysis = $validator->fromProviderPayload(
    learner_ai_roadmap_provider_fixture(),
    learner_ai_roadmap_model_metadata(),
);

roadmap_contract_assert($analysis instanceof RoadmapAnalysis, 'provider payload maps to RoadmapAnalysis');
roadmap_contract_assert($analysis->origin() === 'model', 'valid provider payload has model origin');
roadmap_contract_assert($analysis->confidenceBand() === 'high', 'server confidence band is preserved');
roadmap_contract_assert(count($analysis->alternativeDirections()) === 2, 'exactly two alternatives are preserved');
roadmap_contract_assert(count($analysis->insights()) === 3, 'exactly three insight categories are preserved');
roadmap_contract_assert(count($analysis->phases()) === 3, 'exactly three roadmap phases are preserved');
roadmap_contract_assert(
    array_sum(array_map(static fn ($phase): int => count($phase->tasks()), $analysis->phases())) === 9,
    'three tasks are preserved in every phase',
);
roadmap_contract_assert($analysis->evidenceReferenceIds() === ['evidence-001', 'evidence-002', 'evidence-003'], 'evidence references are normalized');

$nonCanonicalDirectionCodes = learner_ai_roadmap_provider_fixture();
$nonCanonicalDirectionCodes['primary_direction']['code'] = 'product-technology';
$nonCanonicalDirectionCodes['alternative_directions'][0]['code'] = '1';
$normalizedDirections = $validator->fromProviderPayload(
    $nonCanonicalDirectionCodes,
    learner_ai_roadmap_model_metadata(),
);
roadmap_contract_assert(
    $normalizedDirections->primaryDirection()->code() === 'product_technology',
    'a safe model direction code is normalized to the internal code format',
);
roadmap_contract_assert(
    $normalizedDirections->alternativeDirections()[0]->code() === 'alternative_direction_1',
    'an unusable model direction code receives a stable internal fallback',
);

$missing = learner_ai_roadmap_provider_fixture();
unset($missing['executive_summary']);
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($missing, learner_ai_roadmap_model_metadata()),
    'provider payload fields',
);

$unknown = learner_ai_roadmap_provider_fixture();
$unknown['raw_assessment_results'] = ['must-not-pass'];
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($unknown, learner_ai_roadmap_model_metadata()),
    'provider payload fields',
);

$englishOnly = learner_ai_roadmap_provider_fixture();
$englishOnly['executive_summary'] = 'You are suited to product technology and practical problem solving.';
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($englishOnly, learner_ai_roadmap_model_metadata()),
    'Vietnamese',
);

$duplicate = learner_ai_roadmap_provider_fixture();
$duplicate['phases'][1]['position'] = 1;
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($duplicate, learner_ai_roadmap_model_metadata()),
    'phase positions',
);

$wrongPhaseCode = learner_ai_roadmap_provider_fixture();
$wrongPhaseCode['phases'][1]['code'] = 'discover';
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($wrongPhaseCode, learner_ai_roadmap_model_metadata()),
    'phase codes',
);

$overlap = learner_ai_roadmap_provider_fixture();
$overlap['phases'][1]['start_day'] = 30;
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($overlap, learner_ai_roadmap_model_metadata()),
    'must not overlap',
);

$tooMany = learner_ai_roadmap_provider_fixture();
$tooMany['phases'][] = array_merge($tooMany['phases'][2], ['position' => 4, 'start_day' => 91, 'end_day' => 120]);
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($tooMany, learner_ai_roadmap_model_metadata()),
    'exactly three phases',
);

$noEvidence = learner_ai_roadmap_provider_fixture();
$noEvidence['insights'][0]['evidence_ref_ids'] = [];
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($noEvidence, learner_ai_roadmap_model_metadata()),
    'evidence references',
);

$badAction = learner_ai_roadmap_provider_fixture();
$badAction['phases'][0]['tasks'][0]['action'] = ['type' => 'open_external_url', 'url' => 'https://example.test'];
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload($badAction, learner_ai_roadmap_model_metadata()),
    'action type',
);

$missingMetadata = learner_ai_roadmap_model_metadata();
unset($missingMetadata['provider']);
roadmap_contract_expect(
    static fn () => $validator->fromProviderPayload(learner_ai_roadmap_provider_fixture(), $missingMetadata),
    'model metadata',
);

echo "learner_ai_roadmap_contract_test: OK\n";
