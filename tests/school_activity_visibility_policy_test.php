<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$migration = file_get_contents($root . '/Database/migrations/20260825000100_add_activity_visibility_contract.php');
$service = file_get_contents($root . '/src/Modules/School/Service/SchoolDashboardService.php');
if ($migration === false || $service === false) {
    throw new RuntimeException('Cannot read School activity visibility sources.');
}
foreach ([
    "DEFAULT 'school_only'",
    "visibility IN ('school_only', 'public')",
    'idx_activities_school_visibility_status',
] as $contract) {
    if (!str_contains($migration, $contract)) {
        throw new RuntimeException("Missing activity visibility database contract: {$contract}");
    }
}
foreach (['defaultVisibility', 'allowedVisibilities', 'readableStatuses', 'registrableStatus'] as $field) {
    if (!str_contains($service, "'{$field}'")) {
        throw new RuntimeException("Missing School visibility policy field: {$field}");
    }
}
if (!str_contains($service, "'readableStatuses' => ['published', 'ongoing', 'completed']")) {
    throw new RuntimeException('Read status policy must differ from registration status.');
}
if (!str_contains($service, "'registrableStatus' => 'published'")) {
    throw new RuntimeException('New registration must only allow published activity.');
}

echo "school_activity_visibility_policy_test: OK\n";
