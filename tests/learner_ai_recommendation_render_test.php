<?php

declare(strict_types=1);

function learner_ai_render_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
}

$root = dirname(__DIR__);
$page = file_get_contents($root . '/app/learner/ai-recommendations.php');
$studentData = file_get_contents($root . '/app/learner/includes/student-data.php');
$stylesheet = file_get_contents($root . '/assets/css/learner.css');
learner_ai_render_assert(is_string($page) && is_string($studentData), 'AI recommendation shell sources can be read');
learner_ai_render_assert(is_string($stylesheet), 'learner stylesheet can be read');

foreach ([$page, $studentData] as $source) {
    learner_ai_render_assert(!str_contains($source, '$aiRecommendation'), 'hard-coded AI recommendation fixture is absent');
    learner_ai_render_assert(!str_contains($source, 'IoT và Drone'), 'fixed IoT/Drone claim is absent');
    learner_ai_render_assert(!str_contains($source, 'Lộ trình gợi ý 3 tháng tới'), 'fixed three-month roadmap is absent');
}

foreach ([
    'data-ai-page',
    'data-ai-state-status',
    'data-ai-loading',
    'data-ai-consent',
    'data-ai-insufficient',
    'data-ai-source-error',
    'data-ai-results',
    'data-ai-result-list',
    'data-ai-feedback-status',
    'data-ai-generate',
    'data-ai-retry',
] as $semanticHook) {
    learner_ai_render_assert(str_contains($page, $semanticHook), "AI page contains {$semanticHook} semantic hook");
}

learner_ai_render_assert(
    preg_match('/data-ai-state-status[^>]*role="status"[^>]*aria-live="polite"/', $page) === 1,
    'AI state changes use a polite live region',
);
learner_ai_render_assert(
    preg_match('/data-ai-feedback-status[^>]*role="status"[^>]*aria-live="polite"/', $page) === 1,
    'feedback changes use a polite live region',
);
learner_ai_render_assert(!str_contains($page, 'learner-ai-data'), 'page does not serialize a server-side recommendation fixture');
learner_ai_render_assert(str_contains($page, 'learner-recommendations.js'), 'page loads the learner recommendation client');
learner_ai_render_assert(!str_contains($stylesheet, '--surface-subtle'), 'AI evidence UI uses existing design tokens only');

echo "learner_ai_recommendation_render_test: OK\n";
