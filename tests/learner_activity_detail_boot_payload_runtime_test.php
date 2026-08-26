<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$pagePath = $root . '/app/learner/activity-detail.php';
$source = (string) file_get_contents($pagePath);
$functionName = 'learner_activity_detail_boot_payload';

if (!str_contains($source, "function {$functionName}")) {
    fwrite(STDERR, "learner_activity_detail_boot_payload_runtime_test: RED\n- Production boot payload builder is missing.\n");
    exit(1);
}

$GLOBALS['__TALENTHUB_ACTIVITY_DETAIL_CONTRACT_ONLY__'] = true;
require $pagePath;
unset($GLOBALS['__TALENTHUB_ACTIVITY_DETAIL_CONTRACT_ONLY__']);

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$activityA = [
    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    'title' => 'Activity A',
    'description' => 'Metadata A',
];
$activityB = [
    'id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    'title' => 'Activity B confidential',
    'description' => 'FOREIGN_METADATA_B_MUST_NOT_LEAK',
];
$registrationA = [
    'id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
    'activity_id' => $activityA['id'],
    'status' => 'approved',
];
$registrationB = [
    'id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
    'activity_id' => $activityB['id'],
    'status' => 'pending',
    'private_marker' => 'REGISTRATION_B_MUST_NOT_LEAK',
];
$catalog = [$activityA, $activityB];
$registrations = [$registrationA, $registrationB];

$databaseBoot = learner_activity_detail_boot_payload(
    $activityA,
    'database',
    'student-a',
    'csrf-token',
    $catalog,
    $registrations,
    $registrationA,
);
$assert(($databaseBoot['activity']['id'] ?? null) === $activityA['id'], 'Database boot activity is the requested Activity A.');
$assert(array_column($databaseBoot['catalog'] ?? [], 'id') === [$activityA['id']], 'Database boot catalog contains only Activity A.');
$assert(array_column($databaseBoot['registrations'] ?? [], 'activity_id') === [$activityA['id']], 'Database boot registrations contain only Activity A registration.');
$databaseJson = json_encode($databaseBoot, JSON_THROW_ON_ERROR);
$assert(!str_contains($databaseJson, $activityB['title']), 'Database boot does not leak Activity B title.');
$assert(!str_contains($databaseJson, $activityB['description']), 'Database boot does not leak Activity B metadata.');
$assert(!str_contains($databaseJson, $registrationB['private_marker']), 'Database boot does not leak Activity B registration metadata.');

$noRegistrationBoot = learner_activity_detail_boot_payload(
    $activityA,
    'database',
    'student-a',
    'csrf-token',
    $catalog,
    $registrations,
    null,
);
$assert(($noRegistrationBoot['registrations'] ?? null) === [], 'Database detail without a current registration emits an empty registrations list.');

$assert(
    learner_activity_detail_boot_payload(null, 'database', 'student-a', 'csrf-token', $catalog, $registrations, null) === null,
    'Not-found or cross-school detail emits no boot payload.'
);

$mockBoot = learner_activity_detail_boot_payload(
    $activityA,
    'mock',
    'student-a',
    'csrf-token',
    $catalog,
    $registrations,
    $registrationA,
);
$assert(count($mockBoot['catalog'] ?? []) === 2, 'Mock mode may retain the full catalog.');
$assert(count($mockBoot['registrations'] ?? []) === 2, 'Mock mode may retain registrations for local schedule-conflict behavior.');

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_detail_boot_payload_runtime_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_detail_boot_payload_runtime_test: OK\n";
