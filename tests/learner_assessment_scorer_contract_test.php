<?php

declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\AssessmentScorer;
use TalentHub\Learner\Assessment\Scoring\LikertScore;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Learner\Assessment\Scoring\ScoringResult;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

function scorer_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function scorer_contract_expect_exception(callable $callback, string $expectedClass, string $message): void
{
    try {
        $callback();
    } catch (\Throwable $e) {
        if ($e instanceof $expectedClass) {
            return;
        }
        $actualClass = get_class($e);
        fwrite(STDERR, "Assertion failed (wrong exception {$actualClass}): {$message}\n");
        exit(1);
    }

    scorer_contract_assert(false, $message);
}

// 1. Check classes and interfaces exist
scorer_contract_assert(interface_exists(AssessmentScorer::class), 'AssessmentScorer interface must exist');
scorer_contract_assert(class_exists(ScoringResult::class), 'ScoringResult class must exist');
scorer_contract_assert(class_exists(LikertScore::class), 'LikertScore class must exist');
scorer_contract_assert(class_exists(ScorerRegistry::class), 'ScorerRegistry class must exist');

// 2. ScoringResult tests
$validResult = new ScoringResult('RIA', 'Định hướng RIASEC.', ['R' => 80, 'I' => 70, 'A' => 60]);
$arrayResult = $validResult->toArray();
scorer_contract_assert($arrayResult['result_code'] === 'RIA', 'ScoringResult result_code matches');
scorer_contract_assert($arrayResult['summary'] === 'Định hướng RIASEC.', 'ScoringResult summary matches');
scorer_contract_assert($arrayResult['dimension_scores'] === ['R' => 80, 'I' => 70, 'A' => 60], 'ScoringResult dimension_scores matches');

// Empty result_code
scorer_contract_expect_exception(
    static fn () => new ScoringResult('', 'Summary', ['R' => 50]),
    InvalidArgumentException::class,
    'ScoringResult must reject empty result_code'
);
scorer_contract_expect_exception(
    static fn () => new ScoringResult('   ', 'Summary', ['R' => 50]),
    InvalidArgumentException::class,
    'ScoringResult must reject whitespace-only result_code'
);

// Empty summary
scorer_contract_expect_exception(
    static fn () => new ScoringResult('RIA', '', ['R' => 50]),
    InvalidArgumentException::class,
    'ScoringResult must reject empty summary'
);
scorer_contract_expect_exception(
    static fn () => new ScoringResult('RIA', " \n\t ", ['R' => 50]),
    InvalidArgumentException::class,
    'ScoringResult must reject whitespace-only summary'
);

// Empty dimension key
scorer_contract_expect_exception(
    static fn () => new ScoringResult('RIA', 'Summary', ['' => 50]),
    InvalidArgumentException::class,
    'ScoringResult must reject empty dimension key'
);

// Invalid dimension score values (not integer or out of bounds)
scorer_contract_expect_exception(
    static fn () => new ScoringResult('RIA', 'Summary', ['R' => -1]),
    InvalidArgumentException::class,
    'ScoringResult must reject negative score'
);
scorer_contract_expect_exception(
    static fn () => new ScoringResult('RIA', 'Summary', ['R' => 101]),
    InvalidArgumentException::class,
    'ScoringResult must reject score > 100'
);

// 3. LikertScore tests
scorer_contract_assert(LikertScore::value(5) === 5, 'LikertScore::value(5) is 5');
scorer_contract_assert(LikertScore::value('5') === 5, 'LikertScore::value("5") is 5');
scorer_contract_assert(LikertScore::value(1, true) === 5, 'LikertScore::value(1, true) is 5 (6 - 1)');
scorer_contract_assert(LikertScore::value(5, true) === 1, 'LikertScore::value(5, true) is 1 (6 - 5)');
scorer_contract_assert(LikertScore::value(3, true) === 3, 'LikertScore::value(3, true) is 3 (6 - 3)');

scorer_contract_expect_exception(
    static fn () => LikertScore::value(0),
    RuntimeException::class,
    'LikertScore must reject value 0'
);
scorer_contract_expect_exception(
    static fn () => LikertScore::value(6),
    RuntimeException::class,
    'LikertScore must reject value 6'
);
scorer_contract_expect_exception(
    static fn () => LikertScore::value('invalid'),
    RuntimeException::class,
    'LikertScore must reject non-numeric string'
);
scorer_contract_expect_exception(
    static fn () => LikertScore::value(3.5),
    RuntimeException::class,
    'LikertScore must reject float values'
);
scorer_contract_expect_exception(
    static fn () => LikertScore::value('3.5'),
    RuntimeException::class,
    'LikertScore must reject decimal strings'
);

// Normalization: round(((total - count) / (count * 4)) * 100)
scorer_contract_assert(LikertScore::normalize(0, 0) === 0, 'normalize count 0 returns 0');
scorer_contract_assert(LikertScore::normalize(10, 2) === 100, 'normalize total 10 count 2 returns 100');
scorer_contract_assert(LikertScore::normalize(2, 2) === 0, 'normalize total 2 count 2 returns 0');
scorer_contract_assert(LikertScore::normalize(6, 2) === 50, 'normalize total 6 count 2 returns 50');

// 4. ScorerRegistry tests
$dummyScorer = new class implements AssessmentScorer {
    public function score(array $questions, array $answers): ScoringResult
    {
        return new ScoringResult('TEST', 'Dummy test summary.', ['D' => 100]);
    }
};

$registry = new ScorerRegistry([
    'dummy-version-1.0' => $dummyScorer,
]);

scorer_contract_assert($registry->forVersion('dummy-version-1.0') === $dummyScorer, 'ScorerRegistry returns registered scorer');

scorer_contract_expect_exception(
    static fn () => $registry->forVersion('unregistered-version'),
    RuntimeException::class,
    'ScorerRegistry must throw RuntimeException for unregistered version'
);

echo "learner_assessment_scorer_contract_test: OK\n";
