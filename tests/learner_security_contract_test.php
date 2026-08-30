<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: {$message}");
    }
};
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

$learnerJs = $read('assets/js/learner.js');
$activityJs = $read('assets/js/learner-activities.js');
$notificationJs = $read('assets/js/learner-notifications.js');
$statisticsJs = $read('assets/js/learner-statistics.js');
$indexPage = $read('app/learner/index.php');
$opportunityPage = $read('app/learner/opportunity.php');
$projectPage = $read('app/learner/project.php');
$responder = $read('app/learner/api/JsonResponder.php');
$checkinEndpoint = $read('app/learner/api/v1/checkins.php');
$applicationEndpoint = $read('app/learner/api/v1/applications.php');
$recommendationEndpoint = $read('app/learner/api/v1/recommendations.php');
$loginLimiter = $read('src/Auth/Service/LoginRateLimiter.php');

foreach (glob($root . '/assets/js/learner*.js') ?: [] as $path) {
    $source = (string) file_get_contents($path);
    $assert(!preg_match('/\.(?:innerHTML|outerHTML)\s*=|insertAdjacentHTML\s*\(/', $source), basename($path) . ' must not parse untrusted HTML.');
    $assert(!preg_match('/\beval\s*\(|new\s+Function\s*\(/', $source), basename($path) . ' must not execute dynamic code.');
}

$assert(!str_contains($learnerJs, 'data-save-opportunity'), 'Saved opportunity cannot claim UI-only persistence.');
$assert(!str_contains($opportunityPage, 'data-save-opportunity'), 'Opportunity page cannot expose fake persistence.');
$assert(str_contains($projectPage, "learner_project((string) (\$_GET['id'] ?? ''))"), 'Project detail must use the authenticated student scope.');
$assert(!preg_match('/projectUrl|project_url|github\.com/i', $projectPage), 'Project detail must not expose repository URLs.');
$assert(!str_contains($projectPage, 'data-application-form'), 'Project detail must remain outside the application workflow.');
$assert(!str_contains($learnerJs, '[data-register-activity]'), 'Dashboard registration cannot mutate UI without the activity endpoint.');
$assert(!str_contains($indexPage, 'data-register-activity'), 'Dashboard must link to the canonical activity flow.');
$assert(!str_contains($learnerJs, '[data-confirm-registration]'), 'Unused optimistic registration modal must be removed.');
$assert(!str_contains($learnerJs, '[data-assessment-action]'), 'Unused optimistic assessment state handler must be removed.');
$assert(str_contains($activityJs, "source==='mock'"), 'Browser-local activity state is restricted to explicit mock mode.');
$assert(str_contains($activityJs, "source==='database'&&gateway!==null"), 'Database activity mutations require the server gateway.');
$assert(!preg_match('/\bfetch\s*\(/', $notificationJs), 'Notifications use the shared learner API client.');
$assert(!preg_match('/\bfetch\s*\(/', $statisticsJs), 'Statistics use the shared learner API client.');
$assert(str_contains($responder, 'foreach ($exception->headers as $name => $value)'), 'Safe ApiException headers must be emitted by JsonResponder.');
$assert(str_contains($responder, "['Retry-After']"), 'JsonResponder must allow Retry-After explicitly.');
$assert(str_contains($loginLimiter, "new ApiException(429,'RATE_LIMIT_EXCEEDED'"), 'Login retains persistent rate limiting.');
$assert(preg_match("/consume\\s*\\(\\s*'learner\\.checkin'/", $checkinEndpoint) === 1, 'Check-in consumes the persistent action limit before writing.');
$assert(preg_match("/consume\\s*\\(\\s*'learner\\.application'/", $applicationEndpoint) === 1, 'Application submit consumes the persistent action limit before writing.');
$assert(preg_match("/consume\\s*\\(\\s*'learner\\.ai'/", $recommendationEndpoint) === 1, 'AI generation consumes the persistent action limit before work begins.');

echo "learner_security_contract_test: OK\n";
