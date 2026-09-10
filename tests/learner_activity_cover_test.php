<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/includes/icons.php';
require_once dirname(__DIR__) . '/app/learner/includes/activity-data.php';

function check(bool $condition, string $label): void {
    if (!$condition) {
        throw new RuntimeException('[FAIL] ' . $label);
    }
    echo '[PASS] ' . $label . PHP_EOL;
}

$fallback = 'assets/activities/illustrations/hero-discover.svg';

// Case 1: Database path starting with /app/learner/
$res1 = learner_activity_cover_or_fallback(
    '/app/learner/assets/activities/covers/fpt-product-sprint.webp',
    $fallback
);
check($res1 === 'assets/activities/covers/fpt-product-sprint.webp', 'Normalized /app/learner/ path to clean relative path');
check(is_file(dirname(__DIR__) . '/app/learner/' . $res1), 'Target file exists on disk');

// Case 2: Relative path already
$res2 = learner_activity_cover_or_fallback(
    'assets/activities/covers/fpt-product-sprint.webp',
    $fallback
);
check($res2 === 'assets/activities/covers/fpt-product-sprint.webp', 'Preserved clean relative path');

// Case 3: Missing file falls back
$res3 = learner_activity_cover_or_fallback(
    'assets/activities/covers/not-found-image.webp',
    $fallback
);
check($res3 === $fallback, 'Missing cover falls back to SVG illustration');

// Case 4: Invalid traversal
$res4 = learner_activity_cover_or_fallback(
    '../../../etc/passwd',
    $fallback
);
check($res4 === $fallback, 'Traversal input safely falls back');

// Case 5: Empty input
$res5 = learner_activity_cover_or_fallback('', $fallback);
check($res5 === $fallback, 'Empty input safely falls back');

// Case 6: ActivityReadModel normalization
$rawRecord = [
    'id' => '31000000-0000-4000-8000-000000000010',
    'title' => 'Product Sprint: Xây dựng Sản phẩm Số',
    'cover_image_url' => '/app/learner/assets/activities/covers/fpt-product-sprint.webp',
    'cover_image_alt' => 'Minh họa cho hoạt động Product Sprint: Xây dựng Sản phẩm Số',
    'status' => 'published',
    'capacity' => 30,
    'participants' => 3,
];
$normalized = \TalentHub\Learner\Data\ReadModel\ActivityReadModel::activity($rawRecord);
check($normalized['cover_image_url'] === 'assets/activities/covers/fpt-product-sprint.webp', 'ReadModel normalized /app/learner/ asset to relative path');
check(is_file(dirname(__DIR__) . '/app/learner/' . $normalized['cover_image_url']), 'Normalized path corresponds to an existing WebP file');

$resolvedCover = learner_activity_cover_or_fallback($normalized['cover_image_url'], $fallback);
check($resolvedCover === 'assets/activities/covers/fpt-product-sprint.webp', 'Resolved cover matches exactly');

// Case 7: Uploaded cover image in /storage/activity-covers/ that exists
$storageDir = dirname(__DIR__) . '/storage/activity-covers';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}
$dummyCoverFile = $storageDir . '/cover-test-sample.webp';
file_put_contents($dummyCoverFile, 'RIFFdummyWEBP');

$res7 = learner_activity_cover_or_fallback(
    '/storage/activity-covers/cover-test-sample.webp',
    $fallback
);
check($res7 === '/storage/activity-covers/cover-test-sample.webp', 'Accepted existing /storage/activity-covers/ path');

// Case 8: Missing storage cover image falls back
$res8 = learner_activity_cover_or_fallback(
    '/storage/activity-covers/missing-cover.webp',
    $fallback
);
check($res8 === $fallback, 'Missing storage cover safely falls back to illustration');

// Case 9: ReadModel normalization for /storage/activity-covers/
$uploadRecord = [
    'id' => '31000000-0000-4000-8000-000000000099',
    'title' => 'Hoạt động có ảnh tải lên',
    'cover_image_url' => '/storage/activity-covers/cover-test-sample.webp',
    'cover_image_alt' => '',
    'status' => 'published',
    'capacity' => 20,
    'participants' => 5,
];
$uploadNormalized = \TalentHub\Learner\Data\ReadModel\ActivityReadModel::activity($uploadRecord);
check($uploadNormalized['cover_image_url'] === '/storage/activity-covers/cover-test-sample.webp', 'ReadModel preserves /storage/activity-covers/ URL');
$uploadResolved = learner_activity_cover_or_fallback($uploadNormalized['cover_image_url'], $fallback);
check($uploadResolved === '/storage/activity-covers/cover-test-sample.webp', 'Resolved upload cover matches exactly');

// Clean up dummy file
if (is_file($dummyCoverFile)) {
    unlink($dummyCoverFile);
}

echo '[ALL PASSED] learner_activity_cover_test.php completed successfully' . PHP_EOL;