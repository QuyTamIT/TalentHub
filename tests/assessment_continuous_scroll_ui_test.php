<?php
/**
 * Verification test for Continuous Scroll Assessment UI across all 4 assessments.
 */
declare(strict_types=1);

$errors = [];

function assert_true(bool $condition, string $message, array &$errors): void {
    if (!$condition) {
        $errors[] = "FAIL: $message";
        echo "❌ $message\n";
    } else {
        echo "✅ $message\n";
    }
}

echo "=== 1. Verifying app/learner/assessment.php ===\n";
$assessmentPhp = file_get_contents(__DIR__ . '/../app/learner/assessment.php');
assert_true($assessmentPhp !== false, 'assessment.php exists and is readable', $errors);

// 4 assessment codes supported
$validCodes = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
foreach ($validCodes as $code) {
    assert_true(
        strpos($assessmentPhp, "'$code'") !== false,
        "Assessment code '$code' configured in assessment.php",
        $errors
    );
}

// Check stream and sticky header DOM markers
assert_true(strpos($assessmentPhp, 'data-assessment-stream') !== false, 'Continuous question stream container present', $errors);
assert_true(strpos($assessmentPhp, 'data-assessment-sticky-header') !== false, 'Sticky header present', $errors);
assert_true(strpos($assessmentPhp, 'data-assessment-answered-counter') !== false, 'Answered progress counter present', $errors);
assert_true(strpos($assessmentPhp, 'data-assessment-total-counter') !== false, 'Total progress counter present', $errors);
assert_true(strpos($assessmentPhp, 'data-assessment-open-sheet') !== false, 'Open sheet modal button (Xem chi tiết) present', $errors);
assert_true(strpos($assessmentPhp, 'data-assessment-sheet-modal') !== false, 'Question sheet modal present', $errors);
assert_true(strpos($assessmentPhp, 'data-assessment-sheet-grid') !== false, 'Question navigator grid in sheet modal present', $errors);
assert_true(strpos($assessmentPhp, 'data-assessment-stream-footer') !== false, 'Stream footer present', $errors);

// Verify timer elements are removed
assert_true(strpos($assessmentPhp, 'data-assessment-timer') === false, 'data-assessment-timer removed from template', $errors);
assert_true(strpos($assessmentPhp, 'learner-assessment-timer') === false, 'learner-assessment-timer class removed from template', $errors);
assert_true(strpos($assessmentPhp, 'data-assessment-intro-duration') === false, 'Intro duration clock facts removed', $errors);


echo "\n=== 2. Verifying assets/css/learner.css ===\n";
$learnerCss = file_get_contents(__DIR__ . '/../assets/css/learner.css');
assert_true($learnerCss !== false, 'learner.css exists and is readable', $errors);

assert_true(strpos($learnerCss, '.learner-assessment-sticky-header') !== false, 'Sticky header CSS class defined', $errors);
assert_true(strpos($learnerCss, '.learner-assessment-stream') !== false, 'Continuous stream CSS class defined', $errors);
assert_true(strpos($learnerCss, '.learner-assessment-question-item') !== false, 'Question card CSS class defined', $errors);
assert_true(strpos($learnerCss, '.learner-likert-pills') !== false, 'Likert pills container CSS class defined', $errors);
assert_true(strpos($learnerCss, '.learner-likert-pill') !== false, 'Likert pill CSS class defined', $errors);
assert_true(strpos($learnerCss, '.learner-likert-pill.is-checked') !== false, 'Pill checked state defined with TalentHub primary', $errors);
assert_true(strpos($learnerCss, '[data-assessment-sheet-grid]') !== false, 'Sheet modal grid CSS defined', $errors);


echo "\n=== 3. Verifying assets/js/learner-assessment.js ===\n";
$learnerJs = file_get_contents(__DIR__ . '/../assets/js/learner-assessment.js');
assert_true($learnerJs !== false, 'learner-assessment.js exists and is readable', $errors);

assert_true(strpos($learnerJs, 'function renderLikertPill') !== false, 'renderLikertPill helper defined', $errors);
assert_true(strpos($learnerJs, 'stream: root.querySelector(\'[data-assessment-stream]\')') !== false, 'stream DOM element queried', $errors);
assert_true(strpos($learnerJs, 'stickyHeader: root.querySelector(\'[data-assessment-sticky-header]\')') !== false, 'stickyHeader DOM element queried', $errors);
assert_true(strpos($learnerJs, 'openSheetBtn: root.querySelector(\'[data-assessment-open-sheet]\')') !== false, 'openSheetBtn DOM element queried', $errors);
assert_true(strpos($learnerJs, 'sheetModal: doc.querySelector(\'[data-assessment-sheet-modal]\')') !== false, 'sheetModal DOM element queried', $errors);
assert_true(strpos($learnerJs, 'scrollIntoView') !== false, 'Auto-scroll implementation present', $errors);


echo "\n=== SUMMARY ===\n";
if (empty($errors)) {
    echo "🎉 ALL TESTS PASSED! Continuous Scroll UI verified successfully.\n";
    exit(0);
} else {
    echo "❌ " . count($errors) . " test(s) failed:\n";
    foreach ($errors as $err) {
        echo "  - $err\n";
    }
    exit(1);
}
