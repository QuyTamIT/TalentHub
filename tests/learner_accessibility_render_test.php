<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

$header = $read('app/learner/includes/header.php');
$profile = $read('app/learner/profile.php');
$opportunity = $read('app/learner/opportunity.php');
$activities = $read('app/learner/my-activities.php');
$learnerJs = $read('assets/js/learner.js');
$css = $read('assets/css/learner.css');

$assert(str_contains($header, 'role="status" aria-live="polite" aria-atomic="true"'), 'Global mutation feedback is announced.');
$assert(str_contains($header, '<label class="learner-visually-hidden" for="learner-search-input">'), 'Header search has a visible-to-AT label.');
$assert(str_contains($learnerJs, "event.key === 'Escape'"), 'Dialogs and drawer support Escape.');
$assert(str_contains($learnerJs, 'returnFocusTarget'), 'Dialogs restore focus to their trigger.');
$assert(str_contains($learnerJs, "event.key !== 'Tab'"), 'Dialogs trap keyboard Tab navigation.');
$assert(str_contains($opportunity, 'data-application-error') && str_contains($opportunity, 'role="alert"'), 'Application validation error is announced.');
$assert(str_contains($profile, 'role="alert"'), 'Profile form errors are announced.');
$assert(str_contains($activities, 'role="status" aria-live="polite"'), 'Activity command status is announced.');
$assert(str_contains($css, ':focus-visible'), 'Interactive controls have visible keyboard focus.');
$assert(str_contains($css, '@media (prefers-reduced-motion: reduce)'), 'Reduced motion is supported.');
$assert(str_contains($css, '@media (max-width: 1024px)'), '1024px layout is explicitly covered.');
$assert(str_contains($css, '@media (max-width: 768px)'), '768px layout is explicitly covered.');
$assert(str_contains($css, '@media (max-width: 360px)'), '360px layout is explicitly covered.');

echo "learner_accessibility_render_test: OK\n";
