<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$expectedPages = [
    'app/learner/ecosystem.php' => ['Hệ sinh thái & Cơ hội', 'data-ecosystem-page'],
    'app/learner/partner.php' => ['Chi tiết đối tác', 'data-partner-page'],
    'app/learner/opportunity.php' => ['Chi tiết cơ hội', 'data-opportunity-page'],
];

function route_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

foreach ($expectedPages as $relativePath => $markers) {
    $path = $root . '/' . $relativePath;
    route_assert(is_file($path), "{$relativePath} exists");
    $source = file_get_contents($path);
    foreach ($markers as $marker) {
        route_assert(str_contains($source, $marker), "{$relativePath} contains {$marker}");
    }
}

$studentData = file_get_contents($root . '/app/learner/includes/student-data.php');
route_assert(str_contains($studentData, '/app/learner/ecosystem.php'), 'learner navigation exposes the ecosystem module');
route_assert(str_contains($studentData, "'icon' => 'ecosystem'"), 'learner navigation uses the ecosystem icon');

$icons = file_get_contents($root . '/app/learner/includes/icons.php');
route_assert(str_contains($icons, "'ecosystem' =>"), 'ecosystem icon is whitelisted');

$javascript = file_get_contents($root . '/assets/js/learner.js');
foreach (['/app/learner/ecosystem.php', '/app/learner/partner.php', '/app/learner/opportunity.php'] as $route) {
    route_assert(str_contains($javascript, "'{$route}'"), "{$route} is an implemented learner route");
}

echo "learner_ecosystem_routes_test: OK\n";
