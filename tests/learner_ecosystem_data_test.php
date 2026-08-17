<?php

declare(strict_types=1);

$providerPath = dirname(__DIR__) . '/app/learner/includes/ecosystem-data.php';

if (!is_file($providerPath)) {
    fwrite(STDERR, "Missing learner ecosystem data provider.\n");
    exit(1);
}

require_once $providerPath;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

$enterprises = learner_ecosystem_enterprises();
$schools = learner_ecosystem_schools();
$opportunities = learner_ecosystem_opportunities();
$applications = learner_ecosystem_applications();

assert_true(count($enterprises) >= 1, 'at least one enterprise is available');
assert_true(count($schools) >= 3, 'school demo data is available');
assert_true(count($applications) >= 3, 'application demo states are available');

$fpt = learner_ecosystem_partner('enterprise', 'fpt-software');
assert_true($fpt !== null, 'FPT Software is available through the learner adapter');
assert_true(($fpt['source'] ?? '') === 'enterprise_mock', 'enterprise source remains traceable');

$statuses = [];
foreach ($opportunities as $opportunity) {
    $statuses[] = $opportunity['status'] ?? '';
    assert_true(($opportunity['status'] ?? '') !== 'draft', 'draft enterprise posts are not exposed');
}

assert_true(in_array('active', $statuses, true), 'active opportunities are exposed');
assert_true(in_array('closed', $statuses, true), 'closed opportunities remain available for history');

$frontend = learner_ecosystem_opportunity('internship', 1);
assert_true($frontend !== null, 'enterprise internship id is preserved');
assert_true(($frontend['partner_id'] ?? '') === 'fpt-software', 'internship maps to its enterprise');
assert_true(($frontend['source'] ?? '') === 'enterprise_mock', 'internship source remains traceable');
assert_true(($frontend['title'] ?? '') === 'Thực tập sinh Frontend Developer (React / TypeScript)', 'enterprise mock title is reused without mutation');

assert_true(learner_ecosystem_partner('school', 'missing-school') === null, 'unknown partner returns null');
assert_true(learner_ecosystem_opportunity('internship', 9999) === null, 'unknown opportunity returns null');

echo "learner_ecosystem_data_test: OK\n";
