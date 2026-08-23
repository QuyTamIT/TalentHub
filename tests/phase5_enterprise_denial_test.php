<?php

declare(strict_types=1);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$root = dirname(__DIR__);
$permissions = file_get_contents($root . '/Database/seeds/System/RolePermissionSeeder.php') ?: '';
$enterpriseStart = strpos($permissions, "'enterprise' => [");
$enterpriseEnd = is_int($enterpriseStart) ? strpos($permissions, '],', $enterpriseStart) : false;
$assert(is_int($enterpriseStart) && is_int($enterpriseEnd), 'Enterprise permission map is readable.');
$enterprisePermissions = substr($permissions, $enterpriseStart, $enterpriseEnd - $enterpriseStart);
foreach (['checkin.', 'qr_session.', 'experience_log.'] as $forbiddenPrefix) {
    $assert(!str_contains($enterprisePermissions, $forbiddenPrefix), "Enterprise has no {$forbiddenPrefix} permission.");
}

$application = file_get_contents($root . '/src/Bootstrap/Application.php') ?: '';
$assert(!preg_match("~/api/v1/businesses/[^'\"]*(?:checkin|qr-session|experience)~i", $application), 'Enterprise routes do not expose Phase 5 data.');

$enterprise = file_get_contents($root . '/app/enterprise/includes/talents-data.php') ?: '';
$assert(!str_contains($enterprise, 'enterprise_allows_checkin_write'), 'Enterprise denial is enforced by RBAC and route absence, not a bypassable helper.');
$assert(!str_contains($enterprise, 'loadStudentProfileFromDb'), 'Phase 3 consent boundary remains intact.');

echo "phase5_enterprise_denial_test: OK\n";
