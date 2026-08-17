<?php

declare(strict_types=1);

$provider = dirname(__DIR__) . '/app/learner/includes/assessment-data.php';
if (!is_file($provider)) {
    fwrite(STDERR, "Missing learner assessment provider.\n");
    exit(1);
}
require_once $provider;

function holland_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$definition = learner_assessment_definition('holland');
$questions = learner_assessment_questions('holland');
$history = learner_assessment_history('student-demo-001', 'holland');

holland_assert($definition !== null, 'Holland definition exists');
holland_assert(($definition['version'] ?? '') === '1.0', 'definition has a stable version');
holland_assert(($definition['source_role'] ?? '') === 'school_expert', 'question ownership is explicit');
holland_assert(($definition['status'] ?? '') === 'published', 'only a published definition is served');
holland_assert(count($questions) === 24, 'Holland contains 24 questions');

$ids = array_column($questions, 'id');
holland_assert(count(array_unique($ids)) === 24, 'question ids are unique');

$dimensions = array_count_values(array_column($questions, 'dimension'));
foreach (['R', 'I', 'A', 'S', 'E', 'C'] as $dimension) {
    holland_assert(($dimensions[$dimension] ?? 0) === 4, "dimension {$dimension} has four questions");
}

foreach ($questions as $question) {
    holland_assert(str_starts_with($question['id'], 'holland-'), 'question id is namespaced');
    holland_assert(count($question['options'] ?? []) === 5, 'each question uses five Likert options');
}

holland_assert(count($history) >= 2, 'cross-device mock history is available');
foreach ($history as $attempt) {
    holland_assert(($attempt['student_id'] ?? '') === 'student-demo-001', 'history is scoped to the learner');
    holland_assert(($attempt['assessment_id'] ?? '') === 'holland', 'history matches Holland');
    holland_assert(($attempt['assessment_version'] ?? '') === '1.0', 'history records the version');
    holland_assert(($attempt['status'] ?? '') === 'submitted', 'history only includes submitted attempts');
}

echo "learner_holland_data_test: OK\n";
