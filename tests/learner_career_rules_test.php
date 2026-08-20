<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Contracts\RecommendationEngine;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationEvidence;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationItem;
use TalentHub\Learner\Ai\Domain\RecommendationResult;
use TalentHub\Learner\Ai\Explanation\RecommendationExplainer;
use TalentHub\Learner\Ai\Rules\CareerGroupClassifier;
use TalentHub\Learner\Ai\Rules\RuleRecommendationEngine;
use TalentHub\Learner\Ai\Rules\RuleSetV1;
use TalentHub\Learner\Ai\Validation\RecommendationResultValidator;

require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
require_once __DIR__ . '/learner_ai_rule_cases_fixture.php';

function career_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function career_expect_exception(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        return;
    }
    fwrite(STDERR, "Expected exception not thrown: {$message}\n");
    exit(1);
}

/** @return list<string> */
function career_evidence_ids(RecommendationItem $item, string $sourceType): array
{
    $ids = [];
    foreach ($item->evidence() as $evidence) {
        if ($evidence->sourceType() === $sourceType) {
            $ids[] = $evidence->sourceId();
        }
    }
    return $ids;
}

/** Helper to build open opportunity/activity record */
function career_open_activity(string $sourceId, string $title, string $category, string $status = 'published'): array
{
    return [
        'opportunity_id' => $sourceId,
        'title' => $title,
        'category' => $category,
        'location' => 'Trường học',
        'deadline_at' => '2026-09-30T17:00:00.000000+00:00',
        'opportunity_type' => 'activity',
        'status' => $status,
    ];
}

/** Helper to build holland assessment with specific dimension scores */
function career_holland_assessment(string $sourceId, array $dimensionScores): array
{
    return [
        '_source_id' => $sourceId,
        'test_code' => 'holland',
        'assessment_version' => '1.0',
        'scoring_version' => 'holland-riasec-1.0',
        'dimension_scores' => $dimensionScores,
        'submitted_at' => '2026-08-01T09:00:00.000000+00:00',
    ];
}

// -------------------------------------------------------------
// SECTION 1: RecommendationResultValidator validation on new action types
// -------------------------------------------------------------
$validator = new RecommendationResultValidator();

$validStrengthItem = new RecommendationItem(
    'strength',
    'Định hướng nghề nghiệp: Kỹ thuật',
    'Thiên hướng kỹ thuật nổi bật.',
    25,
    'high',
    ['type' => 'explore_career_group', 'career_group' => 'technical'],
    [new RecommendationEvidence('assessment', 'holland-1', '2026-08-01T09:00:00.000000+00:00', 'holland_score', ['dimension' => 'R', 'score' => 85])],
);

$validActivityResult = new RecommendationResult('rule', 'learner-rules-1.0.0', null, null, null, null, [
    $validStrengthItem,
    new RecommendationItem(
        'activity',
        'CLB Sáng tạo Robot & IoT',
        'Hoạt động phù hợp với nhóm Kỹ thuật.',
        35,
        'medium',
        ['type' => 'register_activity', 'career_group' => 'technical', 'activity_source_id' => '00000000-0000-4000-8000-000000000301'],
        [
            new RecommendationEvidence('assessment', 'holland-1', '2026-08-01T09:00:00.000000+00:00', 'holland_score', ['score' => 85]),
            new RecommendationEvidence('opportunity', '00000000-0000-4000-8000-000000000301', '2026-09-30T17:00:00.000000+00:00', 'open_activity', ['title' => 'CLB Robot']),
        ],
    ),
]);

$validator->validate($validActivityResult);
career_assert(true, 'validator accepts valid explore_career_group and register_activity actions');

// Invalid career_group in explore_career_group
career_expect_exception(static function () use ($validator): void {
    $validator->validate(new RecommendationResult('rule', '1.0', null, null, null, null, [
        new RecommendationItem('strength', 'Title', 'Summary', 25, 'high', ['type' => 'explore_career_group', 'career_group' => 'invalid_group'], [new RecommendationEvidence('assessment', 'a1', null, 'ev', [])]),
    ]));
}, 'validator rejects invalid career_group code');

// Missing activity_source_id in register_activity
career_expect_exception(static function () use ($validator): void {
    $validator->validate(new RecommendationResult('rule', '1.0', null, null, null, null, [
        new RecommendationItem('activity', 'Title', 'Summary', 35, 'medium', ['type' => 'register_activity', 'career_group' => 'technical', 'activity_source_id' => ''], [new RecommendationEvidence('assessment', 'a1', null, 'ev', [])]),
    ]));
}, 'validator rejects empty activity_source_id in register_activity');

// Invalid non-UUID activity_source_id
career_expect_exception(static function () use ($validator): void {
    $validator->validate(new RecommendationResult('rule', '1.0', null, null, null, null, [
        new RecommendationItem('activity', 'Title', 'Summary', 35, 'medium', ['type' => 'register_activity', 'career_group' => 'technical', 'activity_source_id' => 'act-1'], [new RecommendationEvidence('assessment', 'a1', null, 'ev', [])]),
    ]));
}, 'validator rejects non-UUID activity_source_id');

// Unsupported field in register_activity
career_expect_exception(static function () use ($validator): void {
    $validator->validate(new RecommendationResult('rule', '1.0', null, null, null, null, [
        new RecommendationItem('activity', 'Title', 'Summary', 35, 'medium', ['type' => 'register_activity', 'career_group' => 'technical', 'activity_source_id' => 'act-1', 'extra_field' => 'bad'], [new RecommendationEvidence('assessment', 'a1', null, 'ev', [])]),
    ]));
}, 'validator rejects extra fields in register_activity action');

// -------------------------------------------------------------
// SECTION 2: Rule Engine Recommendations for all 4 Career Groups
// -------------------------------------------------------------
$engine = new RuleRecommendationEngine();
career_assert(RuleRecommendationEngine::categoryToCareerGroup('career_technical') === 'technical', 'canonical technical category maps explicitly');
career_assert(RuleRecommendationEngine::categoryToCareerGroup('technical_workshop') === null, 'non-canonical technical category is not guessed');
career_assert(RuleRecommendationEngine::categoryToCareerGroup('career_finance') === null, 'non-canonical finance category is not guessed as business');

// Group 1: Technical (R/I)
$techAssessment = career_holland_assessment('assess-tech', ['R' => 88, 'I' => 82, 'A' => 40, 'S' => 50, 'E' => 60, 'C' => 55]);
$techInput = new RecommendationInput(
    [
        'profile' => ['study_status' => 'active'],
        'skills' => [],
        'assessments' => [$techAssessment],
        'activities' => [],
        'evaluations' => [],
        'opportunities' => [career_open_activity('00000000-0000-4000-8000-000000000301', 'CLB Sáng tạo Robot & IoT', 'career_technical')],
    ],
    [],
    ['allowed_scopes' => ['assessment', 'activity'], 'missing_consent_scopes' => []],
    [
        ['source_type' => 'assessment', 'source_id' => 'assess-tech', 'observed_at' => '2026-08-01T09:00:00.000000+00:00', 'safe_value' => $techAssessment],
        ['source_type' => 'opportunity', 'source_id' => '00000000-0000-4000-8000-000000000301', 'observed_at' => '2026-09-30T17:00:00.000000+00:00', 'safe_value' => ['title' => 'CLB Sáng tạo Robot & IoT', 'category' => 'career_technical', 'status' => 'published']],
    ]
);
$techResult = $engine->generate($techInput, new RecommendationContext(['assessment', 'activity'], 'req-1', 'idemp-1'));
$validator->validate($techResult);
career_assert(count($techResult->items()) >= 2, 'Technical profile produces strength and activity recommendations');
$techStrength = $techResult->items()[0];
career_assert($techStrength->itemType() === 'strength', 'first item is strength');
career_assert($techStrength->action()['career_group'] === 'technical', 'strength action specifies career_group technical');
career_assert(career_evidence_ids($techStrength, 'assessment') === ['assess-tech'], 'strength carries assessment evidence');
$techAct = $techResult->items()[1];
career_assert($techAct->itemType() === 'activity', 'second item is activity');
career_assert($techAct->action()['type'] === 'register_activity', 'activity action type is register_activity');
career_assert($techAct->action()['activity_source_id'] === '00000000-0000-4000-8000-000000000301', 'activity action has real activity ID');
career_assert($techAct->action()['career_group'] === 'technical', 'activity action specifies technical group');
career_assert(career_evidence_ids($techAct, 'assessment') === ['assess-tech'], 'activity item carries assessment evidence');
career_assert(career_evidence_ids($techAct, 'opportunity') === ['00000000-0000-4000-8000-000000000301'], 'activity item carries opportunity evidence');

// Group 2: Business (E)
$bizAssessment = career_holland_assessment('assess-biz', ['R' => 45, 'I' => 50, 'A' => 60, 'S' => 55, 'E' => 92, 'C' => 65]);
$bizInput = new RecommendationInput(
    [
        'profile' => ['study_status' => 'active'],
        'skills' => [],
        'assessments' => [$bizAssessment],
        'activities' => [],
        'evaluations' => [],
        'opportunities' => [career_open_activity('00000000-0000-4000-8000-000000000303', 'CLB Nhà lãnh đạo & Khởi nghiệp Trẻ', 'career_business')],
    ],
    [],
    ['allowed_scopes' => ['assessment', 'activity'], 'missing_consent_scopes' => []],
    [
        ['source_type' => 'assessment', 'source_id' => 'assess-biz', 'observed_at' => '2026-08-01T09:00:00.000000+00:00', 'safe_value' => $bizAssessment],
        ['source_type' => 'opportunity', 'source_id' => '00000000-0000-4000-8000-000000000303', 'observed_at' => '2026-09-30T17:00:00.000000+00:00', 'safe_value' => ['title' => 'CLB Khởi nghiệp', 'category' => 'career_business', 'status' => 'published']],
    ]
);
$bizResult = $engine->generate($bizInput, new RecommendationContext(['assessment', 'activity'], 'req-2', 'idemp-2'));
$validator->validate($bizResult);
career_assert(count($bizResult->items()) >= 2, 'Business profile produces strength and activity recommendations');
career_assert($bizResult->items()[0]->action()['career_group'] === 'business', 'strength group is business');
career_assert($bizResult->items()[1]->action()['activity_source_id'] === '00000000-0000-4000-8000-000000000303', 'business activity action has real business activity ID');

// Group 3: Arts (A)
$artAssessment = career_holland_assessment('assess-art', ['R' => 30, 'I' => 40, 'A' => 94, 'S' => 60, 'E' => 50, 'C' => 45]);
$artInput = new RecommendationInput(
    [
        'profile' => ['study_status' => 'active'],
        'skills' => [],
        'assessments' => [$artAssessment],
        'activities' => [],
        'evaluations' => [],
        'opportunities' => [career_open_activity('00000000-0000-4000-8000-000000000305', 'CLB Mỹ thuật Sáng tạo & Thiết kế Đồ họa', 'career_arts')],
    ],
    [],
    ['allowed_scopes' => ['assessment', 'activity'], 'missing_consent_scopes' => []],
    [
        ['source_type' => 'assessment', 'source_id' => 'assess-art', 'observed_at' => '2026-08-01T09:00:00.000000+00:00', 'safe_value' => $artAssessment],
        ['source_type' => 'opportunity', 'source_id' => '00000000-0000-4000-8000-000000000305', 'observed_at' => '2026-09-30T17:00:00.000000+00:00', 'safe_value' => ['title' => 'CLB Mỹ thuật', 'category' => 'career_arts', 'status' => 'published']],
    ]
);
$artResult = $engine->generate($artInput, new RecommendationContext(['assessment', 'activity'], 'req-3', 'idemp-3'));
$validator->validate($artResult);
career_assert(count($artResult->items()) >= 2, 'Arts profile produces strength and activity recommendations');
career_assert($artResult->items()[0]->action()['career_group'] === 'arts', 'strength group is arts');
career_assert($artResult->items()[1]->action()['activity_source_id'] === '00000000-0000-4000-8000-000000000305', 'arts activity action has real arts activity ID');

// Group 4: Sports & Academic (S/C)
$sportAssessment = career_holland_assessment('assess-sport', ['R' => 40, 'I' => 50, 'A' => 45, 'S' => 70, 'E' => 55, 'C' => 89]);
$sportInput = new RecommendationInput(
    [
        'profile' => ['study_status' => 'active'],
        'skills' => [],
        'assessments' => [$sportAssessment],
        'activities' => [],
        'evaluations' => [],
        'opportunities' => [career_open_activity('00000000-0000-4000-8000-000000000307', 'CLB Thể thao & Rèn luyện Thể chất Năng động', 'career_sports_academic')],
    ],
    [],
    ['allowed_scopes' => ['assessment', 'activity'], 'missing_consent_scopes' => []],
    [
        ['source_type' => 'assessment', 'source_id' => 'assess-sport', 'observed_at' => '2026-08-01T09:00:00.000000+00:00', 'safe_value' => $sportAssessment],
        ['source_type' => 'opportunity', 'source_id' => '00000000-0000-4000-8000-000000000307', 'observed_at' => '2026-09-30T17:00:00.000000+00:00', 'safe_value' => ['title' => 'CLB Thể thao', 'category' => 'career_sports_academic', 'status' => 'published']],
    ]
);
$sportResult = $engine->generate($sportInput, new RecommendationContext(['assessment', 'activity'], 'req-4', 'idemp-4'));
$validator->validate($sportResult);
career_assert(count($sportResult->items()) >= 2, 'Sports & Academic profile produces strength and activity recommendations');
career_assert($sportResult->items()[0]->action()['career_group'] === 'sports_academic', 'strength group is sports_academic');
career_assert($sportResult->items()[1]->action()['activity_source_id'] === '00000000-0000-4000-8000-000000000307', 'sports_academic activity action has real sports activity ID');

// Inactive or closed activities are not recommended
$closedInput = new RecommendationInput(
    [
        'profile' => ['study_status' => 'active'],
        'skills' => [],
        'assessments' => [$techAssessment],
        'activities' => [],
        'evaluations' => [],
        'opportunities' => [career_open_activity('act-closed', 'Closed Tech Workshop', 'career_technical', 'inactive')],
    ],
    [],
    ['allowed_scopes' => ['assessment', 'activity'], 'missing_consent_scopes' => []],
    [
        ['source_type' => 'assessment', 'source_id' => 'assess-tech', 'observed_at' => '2026-08-01T09:00:00.000000+00:00', 'safe_value' => $techAssessment],
        ['source_type' => 'opportunity', 'source_id' => 'act-closed', 'observed_at' => '2026-09-30T17:00:00.000000+00:00', 'safe_value' => ['title' => 'Closed Tech Workshop', 'category' => 'career_technical', 'status' => 'inactive']],
    ]
);
$closedResult = $engine->generate($closedInput, new RecommendationContext(['assessment', 'activity'], 'req-5', 'idemp-5'));
$types = array_map(static fn (RecommendationItem $i): string => $i->itemType(), $closedResult->items());
career_assert(!in_array('activity', $types, true), 'inactive activities are never recommended');

echo "learner_career_rules_test: OK\n";
