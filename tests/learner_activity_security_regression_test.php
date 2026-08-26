<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/bin/bootstrap.php';
require_once $root . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\ReadModel\ActivityReadModel;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);

$pages = [
    'index.php' => $read('app/learner/index.php'),
    'activities.php' => $read('app/learner/activities.php'),
    'activity-detail.php' => $read('app/learner/activity-detail.php'),
    'my-activities.php' => $read('app/learner/my-activities.php'),
    'activity-history.php' => $read('app/learner/activity-history.php'),
];
$activityJs = $read('assets/js/learner-activities.js');
$checkinJs = $read('assets/js/learner-checkin.js');
$checkinPage = $read('app/learner/checkin.php');

$payloads = [
    '<script>globalThis.pwned=true</script>',
    '<img src=x onerror=alert(1)>',
    'javascript:alert(1)',
    '" autofocus onfocus="alert(1)',
    '<svg/onload=alert(1)>',
    '{"label":"</script><script>alert(1)</script>"}',
];

$record = [
    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'school_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'school_name' => $payloads[1],
    'responsible_teacher_name' => $payloads[4],
    'title' => $payloads[0],
    'category' => $payloads[3],
    'display_category' => $payloads[3],
    'filter_category' => $payloads[3],
    'summary' => $payloads[1],
    'description' => $payloads[5],
    'experience_highlights' => [$payloads[0]],
    'skills' => [$payloads[1]],
    'requirements' => [$payloads[3]],
    'benefits' => [$payloads[4]],
    'location_name' => $payloads[1],
    'location_address' => $payloads[3],
    'organizer_name' => $payloads[4],
    'organizer_contact' => $payloads[1],
    'organizer_email' => 'javascript@example.invalid<script>',
    'organizer_phone' => $payloads[3],
    'cover_image_url' => 'javascript:alert(1)',
    'cover_image_alt' => $payloads[4],
    'capacity' => 10,
    'participants' => 0,
    'status' => 'published',
    'registration_opens_at' => '2026-08-01T00:00:00Z',
    'registration_closes_at' => '2099-08-31T00:00:00Z',
    'start_at' => '2099-09-01T00:00:00Z',
    'end_at' => '2099-09-01T02:00:00Z',
];
$view = ActivityReadModel::activity($record);
$assert(($view['cover_image_url'] ?? null) === '', 'Read model rejects a javascript cover URL.');
$assert(($view['organizer_email'] ?? null) === '', 'Read model rejects an invalid organizer email.');

foreach ($payloads as $payload) {
    $escaped = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
    $assert(!str_contains($escaped, '<script>') && !str_contains($escaped, '<svg'), 'HTML escaping neutralizes executable markup.');
}

foreach (['activities.php', 'activity-detail.php', 'my-activities.php'] as $page) {
    $assert(str_contains($pages[$page], 'learner_activity_cover_or_fallback('), "{$page} validates cover URLs at the output boundary.");
}
$assert(str_contains($pages['index.php'], '$dashboardActivityCover'), 'Dashboard validates upcoming and confirmed cover URLs at the output boundary.');
$assert(str_contains($pages['index.php'], 'learner_activity_category_label('), 'Dashboard maps canonical categories to safe Vietnamese labels.');
$assert(!str_contains($pages['index.php'], 'Địa điểm chưa có dữ liệu'), 'Dashboard does not invent a confirmed activity location.');

foreach ($pages as $page => $source) {
    $assert(!preg_match('/<\?=\s*\$(?:activity|registration|historyItem)\[[^?]*\?>/s', $source), "{$page} has no direct unescaped domain output.");
}

$assert(!preg_match('/\.(?:innerHTML|outerHTML)\s*=|insertAdjacentHTML\s*\(/', $activityJs), 'Activity JavaScript never parses server values as HTML.');
$assert(str_contains($activityJs, '.textContent='), 'Activity JavaScript writes dynamic status text with textContent.');

foreach (['activity-detail.php', 'my-activities.php'] as $page) {
    foreach (['JSON_HEX_TAG', 'JSON_HEX_AMP', 'JSON_HEX_APOS', 'JSON_HEX_QUOT'] as $flag) {
        $assert(str_contains($pages[$page], $flag), "{$page} boot JSON uses {$flag}.");
    }
}

$assert(!preg_match('/(?:localStorage|sessionStorage).*token|token.*(?:localStorage|sessionStorage)/i', $checkinJs), 'QR token is never persisted in browser storage.');
$assert(!preg_match('/console\.(?:log|info|warn|error)\s*\([^)]*token/i', $checkinJs), 'QR token is never written to the console.');
$assert(!preg_match('/[?&](?:token|qr)=/i', $checkinPage . $checkinJs), 'QR token is never placed in a URL.');
$assert(!preg_match('/(?:analytics|telemetry)[^;\n]*token|token[^;\n]*(?:analytics|telemetry)/i', $checkinJs), 'QR token is never sent to analytics.');

$detailBootTest = $read('tests/learner_activity_detail_boot_payload_runtime_test.php');
$assert(str_contains($detailBootTest, 'Activity B confidential'), 'Cross-school detail leakage has a foreign-title fixture.');
$assert(str_contains($detailBootTest, 'REGISTRATION_B_MUST_NOT_LEAK'), 'Cross-school detail leakage has a foreign-registration fixture.');

$httpContracts = [
    401 => ['src/Auth/Session/SessionManager.php', "ApiException(401"],
    403 => ['app/learner/data/Database/DatabaseActivityCommandRepository.php', "ApiException(403"],
    404 => ['app/learner/data/Database/DatabaseActivityCommandRepository.php', "ApiException(404"],
    409 => ['app/learner/data/Database/DatabaseCheckinRepository.php', "ApiException(409"],
    422 => ['app/learner/data/Database/DatabaseActivityCommandRepository.php', "ApiException(422"],
    429 => ['app/learner/data/Security/PersistentActionRateLimiter.php', "'RATE_LIMIT_EXCEEDED'"],
];
foreach ($httpContracts as $status => [$path, $needle]) {
    $assert(str_contains($read($path), $needle), "HTTP {$status} remains represented by its current domain contract.");
}

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_security_regression_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_security_regression_test: OK\n";
