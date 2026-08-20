<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Rules\CareerGroupClassifier;

$autoloadPath = dirname(__DIR__) . '/app/learner/ai/Rules/CareerGroupClassifier.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

function classifier_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

classifier_assert(class_exists(CareerGroupClassifier::class), 'CareerGroupClassifier class must exist');

$classifier = new CareerGroupClassifier();

// Test 1: Full 4 groups with clear winner Technical (R/I)
$techScores = ['R' => 85, 'I' => 78, 'A' => 60, 'S' => 50, 'E' => 65, 'C' => 55];
$techRanked = $classifier->classify($techScores, 'holland');
classifier_assert(count($techRanked) === 4, 'classify returns all 4 career groups');
classifier_assert($techRanked[0]['code'] === 'technical', 'R=85 leads technical to top group');
classifier_assert($techRanked[0]['label'] === 'Kỹ thuật', 'technical label is Kỹ thuật');
classifier_assert($techRanked[0]['score'] === 85.0, 'group score uses max dimension score (R=85, I=78 -> 85.0)');
classifier_assert($techRanked[0]['contributing_dimensions'] === ['R', 'I'], 'technical contributing dimensions are R, I');

// Test 2: Business winner (E)
$bizScores = ['R' => 50, 'I' => 55, 'A' => 60, 'S' => 65, 'E' => 90, 'C' => 70];
$bizRanked = $classifier->classify($bizScores, 'holland');
classifier_assert($bizRanked[0]['code'] === 'business', 'E=90 leads business to top group');
classifier_assert($bizRanked[0]['label'] === 'Kinh doanh', 'business label is Kinh doanh');
classifier_assert($bizRanked[0]['score'] === 90.0, 'business score is 90.0');
classifier_assert($bizRanked[0]['contributing_dimensions'] === ['E'], 'business contributing dimension is E');

// Test 3: Arts winner (A)
$artScores = ['R' => 40, 'I' => 45, 'A' => 95, 'S' => 60, 'E' => 50, 'C' => 55];
$artRanked = $classifier->classify($artScores, 'holland');
classifier_assert($artRanked[0]['code'] === 'arts', 'A=95 leads arts to top group');
classifier_assert($artRanked[0]['label'] === 'Nghệ thuật', 'arts label is Nghệ thuật');
classifier_assert($artRanked[0]['score'] === 95.0, 'arts score is 95.0');
classifier_assert($artRanked[0]['contributing_dimensions'] === ['A'], 'arts contributing dimension is A');

// Test 4: Sports & Academic winner (S/C)
$sportScores = ['R' => 40, 'I' => 45, 'A' => 50, 'S' => 75, 'E' => 60, 'C' => 88];
$sportRanked = $classifier->classify($sportScores, 'holland');
classifier_assert($sportRanked[0]['code'] === 'sports_academic', 'C=88 leads sports_academic to top group');
classifier_assert($sportRanked[0]['label'] === 'Thể thao & Học thuật', 'sports_academic label is Thể thao & Học thuật');
classifier_assert($sportRanked[0]['score'] === 88.0, 'sports_academic score is max(S=75, C=88) = 88.0');
classifier_assert($sportRanked[0]['contributing_dimensions'] === ['S', 'C'], 'sports_academic contributing dimensions are S, C');

// Test 5: Tie-break ascending by group code
// When all groups have score 80:
// Codes: arts, business, sports_academic, technical
$tieScores = ['R' => 80, 'I' => 80, 'A' => 80, 'E' => 80, 'S' => 80, 'C' => 80];
$tieRanked = $classifier->classify($tieScores, 'holland');
classifier_assert(
    array_map(static fn (array $g): string => $g['code'], $tieRanked) === ['arts', 'business', 'sports_academic', 'technical'],
    'tie-break orders groups by code ascending: arts < business < sports_academic < technical'
);

// Test 6: Non-Holland test codes ignored
classifier_assert($classifier->classify(['R' => 80, 'I' => 80], 'mbti') === [], 'MBTI test is ignored');
classifier_assert($classifier->classify(['D' => 80, 'I' => 80], 'disc') === [], 'DISC test is ignored');
classifier_assert($classifier->classify(['L' => 80], 'multiple_intelligence') === [], 'MI test is ignored');
classifier_assert($classifier->classify(['R' => 80], '') === [], 'empty test code is ignored');

// Test 7: Invalid / missing / out-of-range scores
classifier_assert($classifier->classify([], 'holland') === [], 'empty scores return empty array');
classifier_assert($classifier->classify(['R' => -5, 'I' => 80], 'holland') === [], 'negative score returns empty array');
classifier_assert($classifier->classify(['R' => 105, 'I' => 80], 'holland') === [], 'score > 100 returns empty array');
classifier_assert($classifier->classify(['R' => 'invalid_string', 'I' => 80], 'holland') === [], 'non-numeric score returns empty array');
classifier_assert($classifier->classify(['X' => 100], 'holland') === [], 'unknown Holland dimension returns empty array');
classifier_assert($classifier->classify(['R' => 0, 'I' => 0, 'A' => 0, 'S' => 0, 'E' => 0, 'C' => 0], 'holland') === [], 'all-zero scores do not produce a speculative group');

// Test 8: topGroup helper
$top = $classifier->topGroup($techScores, 'holland');
classifier_assert($top !== null && $top['code'] === 'technical', 'topGroup returns first ranked group');
classifier_assert($classifier->topGroup([], 'holland') === null, 'topGroup on invalid input returns null');

// Test 9: Locked Contract Assertions (Task 1)
classifier_assert(
    $classifier->topGroup(
        ['R' => 90, 'I' => 85, 'A' => 20, 'S' => 20, 'E' => 20, 'C' => 20],
        'holland_high'
    )['code'] === 'technical',
    'banded Holland technical',
);

classifier_assert(
    $classifier->topGroup(
        ['R' => 20, 'I' => 20, 'A' => 20, 'S' => 20, 'E' => 95, 'C' => 20],
        'holland_college'
    )['code'] === 'business',
    'banded Holland business',
);

classifier_assert(
    $classifier->topGroup(
        ['R' => 20, 'I' => 20, 'A' => 95, 'S' => 20, 'E' => 20, 'C' => 20],
        'holland_middle'
    )['code'] === 'arts',
    'banded Holland arts',
);

classifier_assert(
    $classifier->topGroup(
        ['R' => 20, 'I' => 20, 'A' => 20, 'S' => 90, 'E' => 20, 'C' => 88],
        'holland_middle'
    )['code'] === 'sports_academic',
    'banded Holland sports academic',
);

classifier_assert(
    $classifier->classify(
        ['R' => 50, 'I' => 50, 'A' => 50, 'S' => 50, 'E' => 50, 'C' => 50],
        'holland'
    )[0]['code'] === 'arts',
    'stable career-group tie-break',
);

classifier_assert(
    $classifier->classify(['R' => 50, 'I' => 50], 'holland') === [],
    'incomplete dimension map rejected',
);

classifier_assert(
    $classifier->classify(
        ['R' => 101, 'I' => 50, 'A' => 50, 'S' => 50, 'E' => 50, 'C' => 50],
        'holland'
    ) === [],
    'out-of-range score rejected',
);

classifier_assert(
    $classifier->classify(
        ['R' => 90, 'I' => 80, 'A' => 70, 'S' => 60, 'E' => 50, 'C' => 40],
        'mbti_high'
    ) === [],
    'non-Holland test rejected',
);

classifier_assert(
    $classifier->classify(
        ['R' => 90, 'I' => 80, 'A' => 70, 'S' => 60, 'E' => 50, 'C' => 40],
        'holland_unknown'
    ) === [],
    'unknown banded Holland suffix rejected',
);

classifier_assert(
    $classifier->classify(
        ['R' => 90, 'I' => 80, 'A' => 70, 'S' => 60, 'E' => 50, 'C' => 40, 'EXTRA' => 50],
        'holland'
    ) === [],
    'extra dimension rejected',
);

echo "learner_career_group_classifier_test: OK\n";
