<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Learner\Data\ReadModel\ActivityReadModel;

/** @var list<string> $phase1CategoryFailures */
$phase1CategoryFailures = [];
$phase1CategoryAssert = static function (bool $condition, string $message) use (&$phase1CategoryFailures): void {
    if (!$condition) {
        $phase1CategoryFailures[] = $message;
    }
};

$categories = [
    'career_technical' => 'Kỹ thuật',
    'career_business' => 'Kinh doanh',
    'career_arts' => 'Sáng tạo',
    'career_sports_academic' => 'Thể thao & học thuật',
];

foreach ($categories as $canonical => $expectedLabel) {
    $activity = ActivityReadModel::activity([
        'id' => 'activity-' . $canonical,
        'school_id' => '11111111-1111-4111-8111-111111111111',
        'title' => $canonical,
        'category' => $canonical,
        'start_at' => '2026-09-01 09:00:00',
        'end_at' => '2026-09-01 11:00:00',
        'capacity' => 10,
        'status' => 'published',
    ]);
    $filterCategory = $activity['filter_category'] ?? $categories[$activity['category']] ?? 'Khác';
    $phase1CategoryAssert($filterCategory === $expectedLabel, "Canonical category {$canonical} must render as {$expectedLabel}.");
}

$unknown = ActivityReadModel::activity([
    'id' => 'activity-unknown',
    'school_id' => '11111111-1111-4111-8111-111111111111',
    'title' => 'Unknown',
    'category' => 'unmapped_group',
    'start_at' => '2026-09-01 09:00:00',
    'end_at' => '2026-09-01 11:00:00',
    'capacity' => 10,
    'status' => 'published',
]);
$unknownFilter = $unknown['filter_category'] ?? $categories[$unknown['category']] ?? 'Khác';
$phase1CategoryAssert($unknownFilter === 'Khác', 'Unknown canonical categories must render as Khác.');

$community = ActivityReadModel::activity([
    'id' => 'activity-community',
    'school_id' => '11111111-1111-4111-8111-111111111111',
    'title' => 'Community',
    'category' => 'career_sports_academic',
    'filter_category' => 'Cộng đồng',
    'start_at' => '2026-09-01 09:00:00',
    'end_at' => '2026-09-01 11:00:00',
    'capacity' => 10,
    'status' => 'published',
]);
$phase1CategoryAssert($community['category'] === 'career_sports_academic', 'Community display metadata must preserve canonical career_sports_academic for AI career groups.');
$phase1CategoryAssert($community['filter_category'] === 'Cộng đồng', 'Community metadata must override the display filter category without changing its canonical category.');

$camelCaseCommunity = ActivityReadModel::activity([
    'id' => 'activity-community-camel-case',
    'school_id' => '11111111-1111-4111-8111-111111111111',
    'title' => 'Community camel case',
    'category' => 'career_sports_academic',
    'filterCategory' => 'Cộng đồng',
    'start_at' => '2026-09-01 09:00:00',
    'end_at' => '2026-09-01 11:00:00',
    'capacity' => 10,
    'status' => 'published',
]);
$phase1CategoryAssert($camelCaseCommunity['category'] === 'career_sports_academic', 'CamelCase community metadata must preserve canonical career_sports_academic.');
$phase1CategoryAssert($camelCaseCommunity['filter_category'] === 'Cộng đồng', 'CamelCase filterCategory metadata must normalize to filter_category Cộng đồng.');

if ($phase1CategoryFailures !== []) {
    fwrite(STDERR, "learner_activity_category_mapping_test: RED\n- " . implode("\n- ", $phase1CategoryFailures) . "\n");
    exit(1);
}

echo "learner_activity_category_mapping_test: OK\n";
