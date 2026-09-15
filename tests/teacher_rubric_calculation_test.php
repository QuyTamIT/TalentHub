<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Modules\Teacher\Service\RubricScoreCalculator;

$errors = [];
$assert = static function (bool $ok, string $why) use (&$errors): void {
    if (!$ok) {
        $errors[] = $why;
    }
};

$reject = static function (callable $fn, string $why) use ($assert): void {
    try {
        $fn();
        $assert(false, $why);
    } catch (InvalidArgumentException) {
        $assert(true, $why);
    } catch (Throwable $e) {
        $assert(false, "$why (threw unexpected " . get_class($e) . ": " . $e->getMessage() . ")");
    }
};

$calc = new RubricScoreCalculator();

// --- TEST 1: Standard 8/10 and 18/20 equal weights -> 85.00 ---
$criteria1 = [
    [
        'id' => 'crit-1',
        'code' => 'practice',
        'label' => 'Thực hành',
        'score' => 8.0,
        'min' => 0.0,
        'max' => 10.0,
        'weight' => 1.0,
        'required' => true,
    ],
    [
        'id' => 'crit-2',
        'code' => 'product',
        'label' => 'Sản phẩm',
        'score' => 18.0,
        'min' => 0.0,
        'max' => 20.0,
        'weight' => 1.0,
        'required' => true,
    ],
];

$res = $calc->calculate($criteria1);
$assert($res['score'] === 85.0, 'Equal weight 8/10 and 18/20 must equal 85.0');
$assert($res['formula_version'] === 'rubric-weighted-1.0', 'Formula version must be rubric-weighted-1.0');
$assert($res['score_method'] === 'rubric_weighted', 'Score method must be rubric_weighted');
$assert(is_array($res['calculation']), 'Calculation snapshot must be an array');
$assert($res['calculation']['total_score'] === 85.0, 'Calculation total_score must match score');

// --- TEST 2: Unequal weights ---
$criteria2 = [
    ['id' => 'c1', 'code' => 'c1', 'score' => 8.0, 'min' => 0.0, 'max' => 10.0, 'weight' => 2.0, 'required' => true],
    ['id' => 'c2', 'code' => 'c2', 'score' => 18.0, 'min' => 0.0, 'max' => 20.0, 'weight' => 1.0, 'required' => true],
];
$res2 = $calc->calculate($criteria2);
$assert($res2['score'] === 83.33, 'Weighted calculation 2*(8/10) + 1*(18/20) / 3 * 100 must equal 83.33');

// --- TEST 3: Validation checks ---
// Empty list
$reject(fn() => $calc->calculate([]), 'Empty criteria must be rejected');

// Score > max
$reject(fn() => $calc->calculate([
    ['id' => 'c1', 'code' => 'c1', 'score' => 11.0, 'min' => 0.0, 'max' => 10.0, 'weight' => 1.0, 'required' => true],
]), 'Score exceeding max must be rejected');

// Score < min
$reject(fn() => $calc->calculate([
    ['id' => 'c1', 'code' => 'c1', 'score' => -1.0, 'min' => 0.0, 'max' => 10.0, 'weight' => 1.0, 'required' => true],
]), 'Negative score must be rejected');

// min != 0
$reject(fn() => $calc->calculate([
    ['id' => 'c1', 'code' => 'c1', 'score' => 5.0, 'min' => 1.0, 'max' => 10.0, 'weight' => 1.0, 'required' => true],
]), 'Min non-zero must be rejected');

// max <= 0
$reject(fn() => $calc->calculate([
    ['id' => 'c1', 'code' => 'c1', 'score' => 0.0, 'min' => 0.0, 'max' => 0.0, 'weight' => 1.0, 'required' => true],
]), 'Max zero must be rejected');

// weight <= 0
$reject(fn() => $calc->calculate([
    ['id' => 'c1', 'code' => 'c1', 'score' => 5.0, 'min' => 0.0, 'max' => 10.0, 'weight' => 0.0, 'required' => true],
]), 'Weight zero must be rejected');

// Missing score on required criterion
$reject(fn() => $calc->calculate([
    ['id' => 'c1', 'code' => 'c1', 'score' => null, 'min' => 0.0, 'max' => 10.0, 'weight' => 1.0, 'required' => true],
]), 'Null score on required criterion must be rejected');

if ($errors !== []) {
    echo "FAIL: teacher_rubric_calculation_test\n";
    foreach ($errors as $e) echo "  - $e\n";
    exit(1);
}

echo "teacher_rubric_calculation_test: OK\n";
exit(0);