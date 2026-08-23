<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$endpoint = file_get_contents($root . '/app/learner/api/v1/profile-shares.php');
$context = file_get_contents($root . '/app/learner/api/LearnerApiContext.php');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$assert(is_string($endpoint), 'Profile sharing endpoint is readable.');
$assert(is_string($context), 'Learner API context is readable.');
$assert(!str_contains($endpoint, "studentId('student_profile.update_own')"), 'Sharing never reuses profile-update permission.');
$assert(substr_count($endpoint, 'student_profile.share_own') >= 3, 'Every sharing method requires student_profile.share_own.');
$assert(substr_count($endpoint, 'privacy_consent.manage_own') >= 2, 'Every sharing mutation requires privacy_consent.manage_own.');
$assert(substr_count($endpoint, 'mutation(') >= 2, 'Every sharing mutation enforces CSRF.');
$assert(str_contains($context, 'studentIdForPermissions'), 'Learner context supports multiple explicit permissions.');

echo "learner_profile_sharing_endpoint_contract_test: OK\n";
