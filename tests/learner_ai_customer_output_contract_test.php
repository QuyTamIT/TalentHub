<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RoadmapAnalysis;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;
use TalentHub\Learner\Ai\Validation\RoadmapAnalysisValidator;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/fixtures/learner_ai_roadmap_v1.php';

function learner_ai_customer_output_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

learner_ai_customer_output_assert(method_exists(RoadmapAnalysis::class, 'talentMap'), 'roadmap exposes talent map');
learner_ai_customer_output_assert(method_exists(RoadmapAnalysis::class, 'trendSignals'), 'roadmap exposes trend signals');
learner_ai_customer_output_assert(method_exists(RoadmapAnalysis::class, 'growthHypotheses'), 'roadmap exposes growth hypotheses');

$payload = learner_ai_roadmap_provider_fixture();
$payload['talent_map'] = [
    ['field' => 'Tư duy Logic & Hệ thống', 'score' => 0.82, 'evidence_ref_ids' => ['evidence-001']],
    ['field' => 'Kỹ năng Thực hành & Thao tác', 'score' => 0.68, 'evidence_ref_ids' => ['evidence-002']],
    ['field' => 'Tổ chức & Điều phối', 'score' => 0.74, 'evidence_ref_ids' => ['evidence-003']],
];
$payload['strengths'] = [['text' => 'Tư duy hệ thống', 'evidence_ref_ids' => ['evidence-001']]];
$payload['improvements'] = [['text' => 'Luyện trình bày', 'evidence_ref_ids' => ['evidence-002']]];
$payload['potential_paths'] = [['label' => 'Phân tích dữ liệu', 'evidence_ref_ids' => ['evidence-003']]];
$payload['trend_signals'] = [['direction' => 'up', 'label' => 'Tiến bộ', 'evidence_ref_ids' => ['evidence-001']]];
$payload['growth_hypotheses'] = [['text' => 'Có thể phát triển', 'confidence' => 0.7, 'evidence_ref_ids' => ['evidence-002']]];
$payload['confidence'] = 0.8;
$payload['evidence'] = ['evidence-001', 'evidence-002', 'evidence-003'];
$payload['potential_paths'][0]['catalog_id'] = 'catalog-existing-1';

$validator = new RoadmapAnalysisValidator(
    ['evidence-001', 'evidence-002', 'evidence-003'],
    [],
    ['catalog-existing-1'],
);
$analysis = $validator->fromProviderPayload($payload, learner_ai_roadmap_model_metadata());
learner_ai_customer_output_assert($analysis instanceof RoadmapAnalysis, 'extended roadmap output is accepted');
learner_ai_customer_output_assert($analysis->talentMap() !== [], 'talent map is preserved');
learner_ai_customer_output_assert($analysis->trendSignals() !== [], 'trend signals are preserved');
learner_ai_customer_output_assert($analysis->growthHypotheses() !== [], 'growth hypotheses are preserved');

$recommendationItem = new RecommendationItem(
    'activity',
    'Tham gia hoạt động',
    'Rèn luyện kỹ năng qua cơ hội đã xác minh.',
    10,
    'high',
    ['type' => 'develop_skill', 'skill_code' => 'data'],
    [new RecommendationEvidence('opportunity', 'catalog-existing-1', '2026-08-26T00:00:00+00:00', 'catalog_source', ['title' => 'Cơ hội'])],
    'activity',
    'catalog-existing-1',
    'Phù hợp với tín hiệu phát triển.',
);
learner_ai_customer_output_assert($recommendationItem->catalogId() === 'catalog-existing-1', 'recommendation preserves catalog id');
learner_ai_customer_output_assert($recommendationItem->reason() !== null, 'recommendation preserves explanation');
$recommendationResult = new RecommendationResult('model', null, 'test-provider', 'test-model', 'prompt-1', null, [$recommendationItem]);
(new RecommendationResultValidator(['catalog-existing-1']))->validate($recommendationResult);
$unknownRecommendationRejected = false;
try {
    $invalidRecommendation = new RecommendationItem(
        'activity', 'Cơ hội', 'Cơ hội chưa xác minh.', 10, 'high', ['type' => 'develop_skill', 'skill_code' => 'data'],
        [new RecommendationEvidence('opportunity', 'catalog-unknown', '2026-08-26T00:00:00+00:00', 'catalog_source', ['title' => 'Cơ hội'])],
        'activity', 'catalog-unknown', 'Không có trong catalog.',
    );
    (new RecommendationResultValidator(['catalog-existing-1']))->validate(new RecommendationResult('model', null, 'test-provider', 'test-model', 'prompt-1', null, [$invalidRecommendation]));
} catch (RuntimeException $exception) {
    $unknownRecommendationRejected = str_contains($exception->getMessage(), 'catalog');
}
learner_ai_customer_output_assert($unknownRecommendationRejected, 'unknown recommendation catalog id must be rejected with an explicit error');

$catalogWithoutAllowListRejected = false;
try {
    $untrustedCatalogItem = new RecommendationItem(
        'activity', 'Cơ hội', 'Cơ hội chưa xác minh.', 10, 'high', ['type' => 'develop_skill', 'skill_code' => 'data'],
        [new RecommendationEvidence('certificate', 'certificate-1', '2026-08-26T00:00:00+00:00', 'certificate_source', ['title' => 'Chứng chỉ'])],
        'activity', 'catalog-not-proven', 'Không có trong catalog.',
    );
    (new RecommendationResultValidator())->validate(new RecommendationResult('model', null, 'test-provider', 'test-model', 'prompt-1', null, [$untrustedCatalogItem]));
} catch (RuntimeException $exception) {
    $catalogWithoutAllowListRejected = str_contains($exception->getMessage(), 'catalog');
}
learner_ai_customer_output_assert(
    $catalogWithoutAllowListRejected,
    'catalog id must be rejected when no catalog allow-list is available',
);

$invalidTalentMap = $payload;
$invalidTalentMap['talent_map'][0]['score'] = 1.2;
try {
    $validator->fromProviderPayload($invalidTalentMap, learner_ai_roadmap_model_metadata());
    throw new RuntimeException('Assertion failed: invalid talent map must be rejected');
} catch (InvalidArgumentException $exception) {
    learner_ai_customer_output_assert(str_contains($exception->getMessage(), 'talent map'), 'invalid talent map has an explicit error');
}

$missingEvidence = $payload;
$missingEvidence['trend_signals'][0]['evidence_ref_ids'] = [];
try {
    $validator->fromProviderPayload($missingEvidence, learner_ai_roadmap_model_metadata());
    throw new RuntimeException('Assertion failed: missing trend evidence must be rejected');
} catch (InvalidArgumentException $exception) {
    learner_ai_customer_output_assert(str_contains($exception->getMessage(), 'evidence'), 'missing evidence has an explicit error');
}

$unknownCatalog = $payload;
$unknownCatalog['potential_paths'][0]['catalog_id'] = 'catalog-does-not-exist';
try {
    $validator->fromProviderPayload($unknownCatalog, learner_ai_roadmap_model_metadata());
    throw new RuntimeException('Assertion failed: unknown catalog id must be rejected');
} catch (InvalidArgumentException $exception) {
    learner_ai_customer_output_assert(str_contains($exception->getMessage(), 'catalog'), 'unknown catalog id has an explicit error');
}

echo "learner_ai_customer_output_contract_test: OK\n";
