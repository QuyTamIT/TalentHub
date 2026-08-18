<?php

declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\AssessmentScorer;
use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\ScoringResult;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function holland_scorer_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function holland_scorer_expect_exception(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (\RuntimeException) {
        return;
    }

    holland_scorer_assert(false, $message);
}

holland_scorer_assert(class_exists(HollandScorer::class), 'HollandScorer class must exist');

$scorer = new HollandScorer();
holland_scorer_assert($scorer instanceof AssessmentScorer, 'HollandScorer must implement AssessmentScorer');

// Build 2 questions per RIASEC dimension (12 questions total)
$dimensions = ['R', 'I', 'A', 'S', 'E', 'C'];
$answerPairs = [
    'R' => [5, 1], // norm: 5 + (6-1) = 10/2 -> 100
    'I' => [5, 2], // norm: 5 + (6-2) = 9/2  -> 88
    'A' => [4, 2], // norm: 4 + (6-2) = 8/2  -> 75
    'S' => [3, 3], // norm: 3 + (6-3) = 6/2  -> 50
    'E' => [2, 4], // norm: 2 + (6-4) = 4/2  -> 25
    'C' => [1, 5], // norm: 1 + (6-5) = 2/2  -> 0
];

$questions = [];
$answers = [];

foreach ($dimensions as $dim) {
    $posId = "{$dim}-positive";
    $revId = "{$dim}-reversed";

    $questions[] = [
        'question_id' => $posId,
        'dimension_code' => "{$dim}:+",
        'required' => 1,
    ];
    $questions[] = [
        'question_id' => $revId,
        'dimension_code' => "{$dim}:-",
        'required' => 1,
    ];

    $answers[$posId] = $answerPairs[$dim][0];
    $answers[$revId] = $answerPairs[$dim][1];
}

$result = $scorer->score($questions, $answers);
holland_scorer_assert($result instanceof ScoringResult, 'score() returns ScoringResult instance');

$data = $result->toArray();
holland_scorer_assert($data['result_code'] === 'RIA', "Expected result_code 'RIA', got '{$data['result_code']}'");
holland_scorer_assert($data['dimension_scores']['R'] === 100, "R score is 100 (got {$data['dimension_scores']['R']})");
holland_scorer_assert($data['dimension_scores']['I'] === 88, "I score is 88 (got {$data['dimension_scores']['I']})");
holland_scorer_assert($data['dimension_scores']['A'] === 75, "A score is 75 (got {$data['dimension_scores']['A']})");
holland_scorer_assert($data['dimension_scores']['S'] === 50, "S score is 50 (got {$data['dimension_scores']['S']})");
holland_scorer_assert($data['dimension_scores']['E'] === 25, "E score is 25 (got {$data['dimension_scores']['E']})");
holland_scorer_assert($data['dimension_scores']['C'] === 0, "C score is 0 (got {$data['dimension_scores']['C']})");

// Tie-break verification: all same score -> RIA
$tiedAnswers = [];
foreach ($questions as $q) {
    $tiedAnswers[$q['question_id']] = 3;
}
$tiedResult = $scorer->score($questions, $tiedAnswers)->toArray();
holland_scorer_assert($tiedResult['result_code'] === 'RIA', "Tie-break must follow R,I,A,S,E,C order, got '{$tiedResult['result_code']}'");

// Missing required question throws exception
holland_scorer_expect_exception(
    static fn () => $scorer->score($questions, ['R-positive' => 5]),
    'Missing required question must throw RuntimeException'
);

// Unanswered optional question is skipped without throwing
$optionalQuestions = [
    ['question_id' => 'q1', 'dimension_code' => 'R', 'required' => 1],
    ['question_id' => 'q2', 'dimension_code' => 'I', 'required' => 0],
];
$optResult = $scorer->score($optionalQuestions, ['q1' => 5])->toArray();
holland_scorer_assert($optResult['dimension_scores']['R'] === 100, 'R is scored from answered question');
holland_scorer_assert($optResult['dimension_scores']['I'] === 0, 'Unanswered optional dimension defaults to 0');

echo "learner_holland_scorer_test: OK\n";
