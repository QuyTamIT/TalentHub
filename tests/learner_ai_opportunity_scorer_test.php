<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Matching\OpportunityScore;
use TalentHub\Learner\Ai\Matching\StructuredOpportunityScorer;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function scorer_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "scorer_contract_violation={$message}\n");
        exit(1);
    }
}

function scorer_expect_invalid(callable $operation, string $expected, string $message): void
{
    try {
        $operation();
    } catch (\Throwable $caught) {
        if ($caught instanceof $expected) {
            return;
        }
        fwrite(STDERR, "scorer_contract_violation={$message} (got " . $caught::class . ")\n");
        exit(1);
    }
    fwrite(STDERR, "scorer_contract_violation={$message} (no exception)\n");
    exit(1);
}

function scorer_test_input(array $overrides = []): RecommendationInput
{
    $payload = array_merge([
        'education_band' => 'high',
        'profile' => ['grade_level' => 11],
        'skills' => [
            ['code' => 'python', 'score' => 82, 'verification_status' => 'verified'],
            ['code' => 'sql', 'score' => 35, 'verification_status' => 'active'],
        ],
        'assessments' => [
            ['dimension_scores' => ['logical_thinking' => 88, 'creativity' => 61], 'submitted_at' => '2026-08-01T00:00:00.000000+00:00'],
        ],
        'activities' => [
            ['experience_id' => 'exp-1', 'activity_category' => 'Robotics Club', 'hours' => 20],
            ['experience_id' => 'exp-2', 'activity_category' => 'STEM', 'tags' => ['IoT', 'python']],
        ],
    ], $overrides);
    return new RecommendationInput($payload, [], [], []);
}

function scorer_test_candidate(array $overrides = []): OpportunityCandidate
{
    $safe = array_merge([
        'catalog_id' => 'internship-1',
        'item_type' => 'internship',
        'title' => 'Data Internship',
        'provider_name' => 'Verified Enterprise',
        'required_skills' => [['code' => 'python', 'minimum_score' => 60], ['code' => 'sql', 'minimum_score' => 50]],
        'learning_outcomes' => [['code' => 'dashboard', 'label' => 'Dashboard dữ liệu']],
        'education_bands' => ['high', 'college'],
        'deadline_at' => '2026-10-01T00:00:00.000000+00:00',
        'availability' => ['remaining' => 2],
        'status' => 'active',
        'url' => '/app/learner/ecosystem.php?tab=opportunities&focus=internship-1',
        'category' => 'data',
        'difficulty' => 'intermediate',
    ], $overrides);
    return OpportunityCandidate::fromEvidence(['source_type' => 'opportunity', 'source_id' => 'internship-1', 'safe_value' => $safe]);
}

$score = new OpportunityScore([
    'skill_match' => 30,
    'assessment_alignment' => 20,
    'experience_relevance' => 10,
    'growth_potential' => 12,
    'feasibility' => 8,
]);
scorer_assert($score->structuredScore() === 80, 'structured score 30+20+10+12+8 = 80');
scorer_assert($score->withGeminiScore(90)->finalScore() === 83, 'final 0.7*80 + 0.3*90 = 83');
scorer_assert($score->withGeminiScore(0)->finalScore() === 56, 'final 0.7*80 + 0.3*0 = 56');
scorer_assert($score->breakdown() === [
    'skill_match' => 30,
    'assessment_alignment' => 20,
    'experience_relevance' => 10,
    'growth_potential' => 12,
    'feasibility' => 8,
], 'breakdown exposes the canonical dimension map');

$roundingBoundary = (new OpportunityScore([
    'skill_match' => 35,
    'assessment_alignment' => 25,
    'experience_relevance' => 15,
    'growth_potential' => 15,
    'feasibility' => 10,
]))->withGeminiScore(50);
scorer_assert($roundingBoundary->finalScore() === 85, 'final 0.7*100 + 0.3*50 = 85');
$zeroScore = (new OpportunityScore(['skill_match' => 0, 'assessment_alignment' => 0, 'experience_relevance' => 0, 'growth_potential' => 0, 'feasibility' => 0]))->withGeminiScore(0);
scorer_assert($zeroScore->finalScore() === 0, 'final 0');
$fullScore = (new OpportunityScore(['skill_match' => 35, 'assessment_alignment' => 25, 'experience_relevance' => 15, 'growth_potential' => 15, 'feasibility' => 10]))->withGeminiScore(100);
scorer_assert($fullScore->finalScore() === 100, 'final 0.7*100 + 0.3*100 = 100');
$nearBoundary = (new OpportunityScore(['skill_match' => 1, 'assessment_alignment' => 1, 'experience_relevance' => 1, 'growth_potential' => 1, 'feasibility' => 1]))->withGeminiScore(2);
scorer_assert(in_array($nearBoundary->finalScore(), [3, 4], true), 'rounding boundary produces either 3 or 4 depending on direction');

$original = new OpportunityScore(['skill_match' => 0, 'assessment_alignment' => 0, 'experience_relevance' => 0, 'growth_potential' => 0, 'feasibility' => 0]);
$augmented = $original->withGeminiScore(75);
scorer_assert($original->structuredScore() === 0 && $augmented->structuredScore() === 0, 'withGeminiScore is immutable on structured');
scorer_assert($augmented->finalScore() === 23, 'final 0.7*0 + 0.3*75 = 22.5 rounds to 23');
scorer_expect_invalid(
    static fn (): OpportunityScore => $original->withGeminiScore(-1),
    InvalidArgumentException::class,
    'withGeminiScore rejects negative'
);
scorer_expect_invalid(
    static fn (): OpportunityScore => $original->withGeminiScore(101),
    InvalidArgumentException::class,
    'withGeminiScore rejects >100'
);
scorer_expect_invalid(
    static fn (): OpportunityScore => $original->finalScore(),
    LogicException::class,
    'finalScore without gemini throws LogicException'
);
scorer_expect_invalid(
    static fn (): OpportunityScore => new OpportunityScore(['skill_match' => -1, 'assessment_alignment' => 0, 'experience_relevance' => 0, 'growth_potential' => 0, 'feasibility' => 0]),
    InvalidArgumentException::class,
    'negative dimension rejected'
);
scorer_expect_invalid(
    static fn (): OpportunityScore => new OpportunityScore(['skill_match' => 36, 'assessment_alignment' => 0, 'experience_relevance' => 0, 'growth_potential' => 0, 'feasibility' => 0]),
    InvalidArgumentException::class,
    'dimension exceeding max rejected'
);
scorer_expect_invalid(
    static fn (): OpportunityScore => new OpportunityScore(['skill_match' => 0, 'assessment_alignment' => 0, 'experience_relevance' => 0, 'growth_potential' => 0]),
    InvalidArgumentException::class,
    'missing dimension rejected'
);
scorer_expect_invalid(
    static fn (): OpportunityScore => new OpportunityScore(['skill_match' => 0, 'assessment_alignment' => 0, 'experience_relevance' => 0, 'growth_potential' => 0, 'feasibility' => 0, 'extra' => 1]),
    InvalidArgumentException::class,
    'extra dimension rejected'
);

$now = new DateTimeImmutable('2026-08-29T00:00:00Z');
$scorer = new StructuredOpportunityScorer($now);

$candidate = scorer_test_candidate();
$profile = LearnerOpportunityProfile::fromInput(scorer_test_input());
$breakdown = $scorer->score($profile, $candidate)->breakdown();
scorer_assert($breakdown['skill_match'] === 18, 'skill_match 1 of 2 met threshold: round(0.5*35) = 18');
scorer_assert($breakdown['assessment_alignment'] === 0, 'no overlap between candidate category data and assessment dimensions');
scorer_assert($breakdown['experience_relevance'] === 8, '1 of 2 required tags overlaps with verified experience tags: round(0.5*15) = 8');
scorer_assert($breakdown['growth_potential'] === 0, 'mandatory sql missing and not in outcomes');
scorer_assert($breakdown['feasibility'] === 10, 'feasibility 10 when candidate passes all gates');
scorer_assert($scorer->score($profile, $candidate)->structuredScore() === 36, 'sum 18+0+8+0+10 = 36');

$advancedCandidate = scorer_test_candidate(['difficulty' => 'advanced']);
$advancedBreakdown = $scorer->score($profile, $advancedCandidate)->breakdown();
scorer_assert($advancedBreakdown['feasibility'] === 0, 'advanced difficulty is not feasible when relevant skill readiness is below 80');

$advancedReadyProfile = LearnerOpportunityProfile::fromInput(scorer_test_input([
    'skills' => [
        ['code' => 'python', 'score' => 90],
        ['code' => 'sql', 'score' => 85],
    ],
]));
$advancedReadyBreakdown = $scorer->score($advancedReadyProfile, $advancedCandidate)->breakdown();
scorer_assert($advancedReadyBreakdown['feasibility'] === 10, 'advanced difficulty is feasible when relevant skill readiness is at least 80');

scorer_expect_invalid(
    static fn (): OpportunityScore => $scorer->score($profile, scorer_test_candidate(['difficulty' => 'expert-only'])),
    DomainException::class,
    'unknown difficulty fails closed'
);

$matchedCandidate = scorer_test_candidate([
    'required_skills' => [['code' => 'python', 'minimum_score' => 60], ['code' => 'sql', 'minimum_score' => 50]],
    'category' => 'logical_thinking',
]);
$matchedProfile = LearnerOpportunityProfile::fromInput(scorer_test_input([
    'assessments' => [['dimension_scores' => ['logical_thinking' => 90, 'creativity' => 60], 'submitted_at' => '2026-08-01T00:00:00.000000+00:00']],
]));
$matchedBreakdown = $scorer->score($matchedProfile, $matchedCandidate)->breakdown();
scorer_assert($matchedBreakdown['skill_match'] === 18, 'still 1 of 2 met');
scorer_assert($matchedBreakdown['assessment_alignment'] === 25, 'full overlap with assessment dimension');
scorer_assert($matchedBreakdown['experience_relevance'] === 8, '1 of 2 overlaps via python');
scorer_assert($matchedBreakdown['growth_potential'] === 0, 'mandatory missing still applies');

$learningOutcomesCandidate = scorer_test_candidate([
    'required_skills' => [['code' => 'sql', 'minimum_score' => 50]],
    'learning_outcomes' => [['code' => 'sql', 'label' => 'SQL']],
]);
$sqlMissingProfile = LearnerOpportunityProfile::fromInput(scorer_test_input([
    'skills' => [['code' => 'python', 'score' => 82]],
]));
$learnableBreakdown = $scorer->score($sqlMissingProfile, $learningOutcomesCandidate)->breakdown();
scorer_assert($learnableBreakdown['growth_potential'] === 15, 'mandatory missing skill listed in outcomes is learnable');

$allMetCandidate = scorer_test_candidate([
    'required_skills' => [['code' => 'python', 'minimum_score' => 60]],
    'learning_outcomes' => [],
]);
$allMetProfile = LearnerOpportunityProfile::fromInput(scorer_test_input([
    'skills' => [['code' => 'python', 'score' => 82]],
]));
$allMetBreakdown = $scorer->score($allMetProfile, $allMetCandidate)->breakdown();
scorer_assert($allMetBreakdown['skill_match'] === 35, 'full skill match');
scorer_assert($allMetBreakdown['growth_potential'] === 15, 'no missing required skills yields full growth');

$emptyRequiredCandidate = scorer_test_candidate(['required_skills' => []]);
$emptyBreakdown = $scorer->score($profile, $emptyRequiredCandidate)->breakdown();
scorer_assert($emptyBreakdown['skill_match'] === 0, 'no required skills = 0');
scorer_assert($emptyBreakdown['growth_potential'] === 0, 'no required skills = 0 growth potential');

$emptyAssessmentProfile = LearnerOpportunityProfile::fromInput(scorer_test_input(['assessments' => []]));
$emptyAssessmentBreakdown = $scorer->score($emptyAssessmentProfile, $candidate)->breakdown();
scorer_assert($emptyAssessmentBreakdown['assessment_alignment'] === 0, 'no assessment dimensions = 0');

$emptyExperienceProfile = LearnerOpportunityProfile::fromInput(scorer_test_input(['activities' => []]));
$emptyExperienceBreakdown = $scorer->score($emptyExperienceProfile, $candidate)->breakdown();
scorer_assert($emptyExperienceBreakdown['experience_relevance'] === 0, 'no experience tags = 0');

scorer_expect_invalid(
    static fn (): OpportunityScore => $scorer->score($profile, scorer_test_candidate(['status' => 'closed'])),
    DomainException::class,
    'closed candidate throws DomainException'
);
scorer_expect_invalid(
    static fn (): OpportunityScore => $scorer->score($profile, scorer_test_candidate(['deadline_at' => '2026-08-01T00:00:00.000000+00:00'])),
    DomainException::class,
    'expired candidate throws DomainException'
);
scorer_expect_invalid(
    static fn (): OpportunityScore => $scorer->score($profile, scorer_test_candidate(['availability' => ['remaining' => 0]])),
    DomainException::class,
    'full candidate throws DomainException'
);
scorer_expect_invalid(
    static fn (): OpportunityScore => $scorer->score(LearnerOpportunityProfile::fromInput(scorer_test_input(['education_band' => 'middle'])), $candidate),
    DomainException::class,
    'wrong education band throws DomainException'
);

$capCandidate = scorer_test_candidate([
    'required_skills' => [['code' => 'a', 'minimum_score' => 60], ['code' => 'b', 'minimum_score' => 60], ['code' => 'c', 'minimum_score' => 60], ['code' => 'd', 'minimum_score' => 60]],
    'category' => 'logical_thinking',
]);
$capProfile = LearnerOpportunityProfile::fromInput(scorer_test_input([
    'skills' => [['code' => 'a', 'score' => 60], ['code' => 'b', 'score' => 60], ['code' => 'c', 'score' => 60], ['code' => 'd', 'score' => 60]],
    'assessments' => [['dimension_scores' => ['logical_thinking' => 60], 'submitted_at' => '2026-08-01T00:00:00.000000+00:00']],
    'activities' => [
        ['experience_id' => 'exp-1', 'activity_category' => 'a', 'tags' => ['b', 'c', 'd'], 'hours' => 10],
    ],
]));
$capBreakdown = $scorer->score($capProfile, $capCandidate)->breakdown();
scorer_assert($capBreakdown['skill_match'] === 35, 'skill_match capped at 35');
scorer_assert($capBreakdown['assessment_alignment'] === 25, 'assessment_alignment capped at 25');
scorer_assert($capBreakdown['experience_relevance'] === 15, 'experience_relevance capped at 15');
scorer_assert($capBreakdown['growth_potential'] === 15, 'no missing required skills yields full 15');
scorer_assert($capBreakdown['feasibility'] === 10, 'feasibility capped at 10');

$first = $scorer->score($profile, $candidate);
$second = $scorer->score($profile, $candidate);
scorer_assert($first->breakdown() === $second->breakdown(), 'scoring is deterministic across calls');
scorer_assert($first->withGeminiScore(50)->finalScore() === $second->withGeminiScore(50)->finalScore(), 'final score is deterministic');

echo "learner_ai_opportunity_scorer_test: OK\n";
