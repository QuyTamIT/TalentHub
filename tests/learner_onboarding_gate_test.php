<?php

declare(strict_types=1);

use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Service\LearnerOnboardingGate;

require_once dirname(__DIR__) . '/bin/bootstrap.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$gate = new LearnerOnboardingGate();
$assert($gate->pageDestination(['required' => false], '/app/learner/profile.php') === null, 'Legacy account allowed.');
$assert($gate->pageDestination(['required' => true, 'status' => 'pending'], '/app/learner/index.php') === null, 'Pending can reach overview.');
$assert($gate->pageDestination(['required' => true, 'status' => 'pending'], '/app/learner/profile.php') === '/app/learner/index.php', 'Pending is redirected.');
$assert(
    $gate->pageDestination(
        ['required' => true, 'status' => 'accepted', 'next_url' => '/app/learner/assessment.php?code=disc'],
        '/app/learner/profile.php',
    ) === '/app/learner/assessment.php?code=disc',
    'Accepted resumes next test.',
);
$assert(
    $gate->pageDestination(
        ['required' => true, 'status' => 'accepted', 'next_url' => '/app/learner/assessment.php?code=disc'],
        '/app/learner/assessment-result.php',
    ) === null,
    'Accepted learner can reach assessment result.',
);
$assert($gate->pageDestination(['required' => true, 'status' => 'completed'], '/app/learner/profile.php') === null, 'Completed allowed.');

foreach (['https://evil.test/x', '//evil.test/x', '/app/learner/assessment.php?code=disc&next=//evil.test'] as $external) {
    $destination = $gate->pageDestination(
        ['required' => true, 'status' => 'accepted', 'next_url' => $external],
        '/app/learner/profile.php',
    );
    $assert($destination === null || str_starts_with($destination, '/app/learner/'), 'Malformed/external next URL is never returned.');
}

$gate->assertApiAllowed(['required' => false], 'profile.php');
$gate->assertApiAllowed(['required' => true, 'status' => 'accepted'], 'assessment-submit.php');

$apiError = null;
try {
    $gate->assertApiAllowed(['required' => true, 'status' => 'accepted'], 'profile.php');
} catch (ApiException $exception) {
    $apiError = $exception;
}
$assert($apiError?->status === 403 && $apiError?->errorCode === 'ONBOARDING_REQUIRED', 'Non-assessment API is denied while accepted.');

$pendingError = null;
try {
    $gate->assertApiAllowed(['required' => true, 'status' => 'pending'], 'assessments.php');
} catch (ApiException $exception) {
    $pendingError = $exception;
}
$assert($pendingError?->errorCode === 'ONBOARDING_REQUIRED', 'Pending learner cannot call assessment APIs before accepting.');

echo "learner_onboarding_gate_test: OK\n";
