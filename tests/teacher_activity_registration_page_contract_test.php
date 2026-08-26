<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/app/teacher/activities/index.php') ?: '';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$assert(str_contains($source, "form_action\" value=\"registration_transition"), 'Teacher page exposes registration transition forms.');
$assert(str_contains($source, 'activity_registration.update_managed'), 'Teacher page enforces managed-registration permission server-side.');
$assert(str_contains($source, "name=\"expected_status\" value=\"pending\""), 'Teacher page submits an optimistic expected status.');
$assert(str_contains($source, "name=\"registration_action\" value=\"approve\""), 'Teacher page exposes approve action.');
$assert(str_contains($source, "name=\"registration_action\" value=\"reject\""), 'Teacher page exposes reject action.');
$assert(str_contains($source, 'assertCsrf'), 'Teacher page mutations keep CSRF protection.');

echo "teacher_activity_registration_page_contract_test: OK\n";
