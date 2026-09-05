<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$navigationPath = $root . '/app/learner/includes/activity-navigation.php';
$assert(is_file($navigationPath), 'Shared activity navigation include exists.');
$navigationSource = is_file($navigationPath) ? (string) file_get_contents($navigationPath) : '';

$expected = [
    'discover' => ['Khám phá', 'activities.php'],
    'registered' => ['Đã đăng ký', 'my-activities.php'],
    'history' => ['Lịch sử', 'activity-history.php'],
];
foreach ($expected as [$label, $href]) {
    $assert(str_contains($navigationSource, "'label' => '{$label}'"), "Navigation contains {$label}.");
    $assert(str_contains($navigationSource, "'href' => '{$href}'"), "Navigation links {$label} to {$href}.");
    $assert(is_file($root . '/app/learner/' . $href), "Navigation target {$href} exists.");
}
$assert(substr_count($navigationSource, "'key' =>") === 3, 'Navigation has exactly three tabs.');
$assert(!str_contains($navigationSource, 'checkin.php') && !str_contains($navigationSource, "'key' => 'detail'"), 'Navigation has no Check-in or Detail tab.');
$assert(str_contains($navigationSource, 'aria-current'), 'Navigation exposes aria-current for its active tab.');

$routes = [
    'activities.php' => 'discover',
    'activity-detail.php' => 'discover',
    'my-activities.php' => 'registered',
    'activity-history.php' => 'history',
];
foreach ($routes as $route => $activeKey) {
    $path = $root . '/app/learner/' . $route;
    $source = is_file($path) ? (string) file_get_contents($path) : '';
    $assert(str_contains($source, 'assets/activities/activities.css'), "{$route} loads the scoped activity stylesheet.");
    $assert(str_contains($source, "\$activityNavigationActive = '{$activeKey}'"), "{$route} selects the {$activeKey} tab.");
    $assert(str_contains($source, "includes/activity-navigation.php"), "{$route} includes shared activity navigation.");
    $assert(str_contains($source, 'learner-activities-shell'), "{$route} uses the shared activity shell.");
}

$learnerJs = (string) file_get_contents($root . '/assets/js/learner.js');
$assert(substr_count($learnerJs, "'/app/learner/activity-history.php'") === 1, 'History is added once to the existing route registry.');
$registryStart = strpos($learnerJs, 'const implementedRoutes = new Set([');
$registryEnd = $registryStart === false ? false : strpos($learnerJs, ']);', $registryStart);
$registry = $registryStart !== false && $registryEnd !== false ? substr($learnerJs, $registryStart, $registryEnd - $registryStart) : '';
$assert(str_contains($registry, "'/app/learner/activity-history.php'"), 'History belongs to the existing implementedRoutes Set.');
foreach (['/app/learner/activities.php', '/app/learner/activity-detail.php', '/app/learner/my-activities.php'] as $route) {
    $assert(str_contains($registry, "'{$route}'"), "Existing route {$route} remains registered.");
}

if ($failures !== []) {
    fwrite(STDERR, "learner_activity_navigation_contract_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_activity_navigation_contract_test: OK\n";
