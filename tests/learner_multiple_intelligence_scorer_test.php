<?php

declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\AssessmentScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScoringResult;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function mi_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function mi_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (\RuntimeException) {
        return;
    }

    mi_assert(false, $message);
}

// 1. Class exists and implements AssessmentScorer
mi_assert(class_exists(MultipleIntelligenceScorer::class), 'MultipleIntelligenceScorer class must exist');

$scorer = new MultipleIntelligenceScorer();
mi_assert($scorer instanceof AssessmentScorer, 'MultipleIntelligenceScorer must implement AssessmentScorer');

// 2. Golden eight-dimension test:
// We create questions for all 8 dimensions: LING, LOGI, SPAT, BODY, MUSIC, INTER, INTRA, NAT
// We design scores so top 3 are LOGI > INTER > SPAT:
// LOGI: 1 positive (5) + 1 reverse (1 -> 5) => norm(10, 2) = 100
// INTER: 2 positive (5, 4) => norm(9, 2) = 88
// SPAT: 2 positive (4, 4) => norm(8, 2) = 75
// LING: 2 positive (3, 3) => norm(6, 2) = 50
// BODY: 2 positive (3, 2) => norm(5, 2) = 38
// MUSIC: 2 positive (2, 2) => norm(4, 2) = 25
// INTRA: 2 positive (2, 1) => norm(3, 2) = 13
// NAT: 2 positive (1, 1) => norm(2, 2) = 0
$questions = [
    ['question_id' => 'q-logi-1', 'dimension_code' => 'LOGI:+', 'required' => 1],
    ['question_id' => 'q-logi-2', 'dimension_code' => 'LOGI:-', 'required' => 1], // reverse item
    ['question_id' => 'q-inter-1', 'dimension_code' => 'INTER:+', 'required' => 1],
    ['question_id' => 'q-inter-2', 'dimension_code' => 'INTER', 'required' => 1],
    ['question_id' => 'q-spat-1', 'dimension_code' => 'SPAT:+', 'required' => 1],
    ['question_id' => 'q-spat-2', 'dimension_code' => 'SPAT', 'required' => 1],
    ['question_id' => 'q-ling-1', 'dimension_code' => 'LING:+', 'required' => 1],
    ['question_id' => 'q-ling-2', 'dimension_code' => 'LING', 'required' => 1],
    ['question_id' => 'q-body-1', 'dimension_code' => 'BODY:+', 'required' => 1],
    ['question_id' => 'q-body-2', 'dimension_code' => 'BODY', 'required' => 1],
    ['question_id' => 'q-music-1', 'dimension_code' => 'MUSIC:+', 'required' => 1],
    ['question_id' => 'q-music-2', 'dimension_code' => 'MUSIC', 'required' => 1],
    ['question_id' => 'q-intra-1', 'dimension_code' => 'INTRA:+', 'required' => 1],
    ['question_id' => 'q-intra-2', 'dimension_code' => 'INTRA', 'required' => 1],
    ['question_id' => 'q-nat-1', 'dimension_code' => 'NAT:+', 'required' => 1],
    ['question_id' => 'q-nat-2', 'dimension_code' => 'NAT', 'required' => 1],
];

$answers = [
    'q-logi-1' => 5,
    'q-logi-2' => 1, // reverse item: 6 - 1 = 5
    'q-inter-1' => 5,
    'q-inter-2' => 4,
    'q-spat-1' => 4,
    'q-spat-2' => 4,
    'q-ling-1' => 3,
    'q-ling-2' => 3,
    'q-body-1' => 3,
    'q-body-2' => 2,
    'q-music-1' => 2,
    'q-music-2' => 2,
    'q-intra-1' => 2,
    'q-intra-2' => 1,
    'q-nat-1' => 1,
    'q-nat-2' => 1,
];

$result = $scorer->score($questions, $answers);
mi_assert($result instanceof ScoringResult, 'score() returns ScoringResult');

$data = $result->toArray();
mi_assert($data['result_code'] === 'LOGI-INTER-SPAT', "Expected result_code 'LOGI-INTER-SPAT', got '{$data['result_code']}'");
mi_assert(
    $data['summary'] === 'Định hướng đa trí thông minh phục vụ lựa chọn trải nghiệm học tập.',
    'Summary matches requirement'
);

$expectedDimensions = ['LING', 'LOGI', 'SPAT', 'BODY', 'MUSIC', 'INTER', 'INTRA', 'NAT'];
mi_assert(count($data['dimension_scores']) === 8, 'Dimension scores must contain exactly 8 dimensions');
foreach ($expectedDimensions as $dim) {
    mi_assert(isset($data['dimension_scores'][$dim]), "Dimension {$dim} must exist in scores");
    mi_assert(is_int($data['dimension_scores'][$dim]), "Dimension {$dim} score must be integer");
    mi_assert(
        $data['dimension_scores'][$dim] >= 0 && $data['dimension_scores'][$dim] <= 100,
        "Dimension {$dim} score must be in 0-100 range"
    );
}

mi_assert($data['dimension_scores']['LOGI'] === 100, 'LOGI score is 100');
mi_assert($data['dimension_scores']['INTER'] === 88, 'INTER score is 88');
mi_assert($data['dimension_scores']['SPAT'] === 75, 'SPAT score is 75');
mi_assert($data['dimension_scores']['LING'] === 50, 'LING score is 50');
mi_assert($data['dimension_scores']['BODY'] === 38, 'BODY score is 38');
mi_assert($data['dimension_scores']['MUSIC'] === 25, 'MUSIC score is 25');
mi_assert($data['dimension_scores']['INTRA'] === 13, 'INTRA score is 13');
mi_assert($data['dimension_scores']['NAT'] === 0, 'NAT score is 0');

// 3. Reverse-item test
mi_assert($data['dimension_scores']['LOGI'] === 100, 'Reverse item test: low answer 1 on LOGI:- produces score 100');

// 4. Stable tie-break: all equal scores produce top 3 LING-LOGI-SPAT
$tiedAnswers = [];
foreach ($questions as $q) {
    $tiedAnswers[$q['question_id']] = 3;
}
$tiedResult = $scorer->score($questions, $tiedAnswers)->toArray();
mi_assert(
    $tiedResult['result_code'] === 'LING-LOGI-SPAT',
    "Tie-break for all equal scores must produce 'LING-LOGI-SPAT', got '{$tiedResult['result_code']}'"
);

// 5. Missing required answer throws RuntimeException
mi_expect_exception(
    static fn () => $scorer->score($questions, ['q-logi-1' => 5]),
    'Missing required questions must throw RuntimeException'
);

// 6. Optional question without answer is skipped, unattempted dimension has score 0
$optionalQuestions = [
    ['question_id' => 'q1', 'dimension_code' => 'LOGI', 'required' => 1],
    ['question_id' => 'q2', 'dimension_code' => 'INTER', 'required' => 0],
];
$optResult = $scorer->score($optionalQuestions, ['q1' => 5])->toArray();
mi_assert($optResult['dimension_scores']['LOGI'] === 100, 'LOGI is scored from answered question');
mi_assert($optResult['dimension_scores']['INTER'] === 0, 'Unanswered optional INTER defaults to 0');
mi_assert($optResult['dimension_scores']['SPAT'] === 0, 'Unanswered SPAT defaults to 0');

// 7. Invalid dimension codes throw RuntimeException
mi_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'UNKNOWN', 'required' => 1]], ['q1' => 3]),
    'Dimension UNKNOWN must throw RuntimeException'
);
mi_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'LING:*', 'required' => 1]], ['q1' => 3]),
    'Dimension LING:* must throw RuntimeException'
);
mi_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'LOGI:?', 'required' => 1]], ['q1' => 3]),
    'Dimension LOGI:? must throw RuntimeException'
);
mi_expect_exception(
    static fn () => $scorer->score([['question_id' => 'q1', 'dimension_code' => 'BAD:Z', 'required' => 1]], ['q1' => 3]),
    'Dimension BAD:Z must throw RuntimeException'
);

// 8. Invalid Likert values throw RuntimeException via LikertScore
mi_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-logi-1' => 0])),
    'Likert value 0 must throw RuntimeException'
);
mi_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-logi-1' => 6])),
    'Likert value 6 must throw RuntimeException'
);
mi_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-logi-1' => 3.5])),
    'Decimal Likert value must throw RuntimeException'
);
mi_expect_exception(
    static fn () => $scorer->score($questions, array_merge($answers, ['q-logi-1' => 'invalid'])),
    'Non-numeric Likert value must throw RuntimeException'
);

echo "learner_multiple_intelligence_scorer_test: OK\n";
