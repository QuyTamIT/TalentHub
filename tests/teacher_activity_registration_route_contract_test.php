<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/src/Bootstrap/Application.php') ?: '';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$route = "'/api/v1/teachers/me/activities/{activityId}/registrations/{registrationId}'";
$assert(str_contains($source, "'PATCH',{$route}"), 'Teacher registration transition uses the canonical PATCH route.');
$assert(str_contains($source, "'activity_registration.update_managed'"), 'Route requires the exact managed-registration permission.');
$assert(str_contains($source, "assertCsrf(\$r->header('x-csrf-token'))"), 'Route enforces CSRF for the mutation.');
$assert(str_contains($source, "transitionRegistration("), 'Route delegates the transition to the teacher domain service.');
$assert(str_contains($source, "pathParam('activityId')"), 'Activity ownership comes from the canonical path parameter.');
$assert(str_contains($source, "pathParam('registrationId')"), 'Registration identity comes from the canonical path parameter.');

echo "teacher_activity_registration_route_contract_test: OK\n";
