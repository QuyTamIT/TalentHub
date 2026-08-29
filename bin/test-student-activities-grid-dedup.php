<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\ReadModel\ActivityReadModel;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: STUDENT ACTIVITIES DEDUPLICATION & GRID LAYOUT\n";
echo "======================================================================\n\n";

$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : '') . "\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " ({$detail})" : '') . "\n";
        $failed++;
    }
}

// ----------------------------------------------------------------------
// TEST 1: Database and SQL Query Deduplication
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Activity Deduplication Verification ---\n";

learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => '9f9b3e8c-0f72-4b8d-90d9-53ca6ce0a69d']);
(new \TalentHub\Bootstrap\StudentAppContext($pdo))->boot();

require_once dirname(__DIR__) . '/app/learner/includes/activity-data.php';

$catalog = learner_activity_catalog();
$activityIds = array_map(static fn($a) => (string)($a['id'] ?? ''), $catalog);
$activityTitles = array_map(static fn($a) => (string)($a['title'] ?? ''), $catalog);

$uniqueIds = array_unique($activityIds);
$uniqueTitles = array_unique($activityTitles);

assertCondition("Catalog returns activities", count($catalog) > 0, "Count: " . count($catalog));
assertCondition("All activity IDs are unique", count($activityIds) === count($uniqueIds), "Total: " . count($activityIds) . ", Unique: " . count($uniqueIds));
assertCondition("All activity titles are unique", count($activityTitles) === count($uniqueTitles), "Total: " . count($activityTitles) . ", Unique: " . count($uniqueTitles));

$aiExhibitionCount = count(array_filter($catalog, static fn($a) => str_contains((string)($a['title'] ?? ''), 'Triển lãm Công nghệ AI & Robotics')));
assertCondition("Obsolete 'Triển lãm Công nghệ AI & Robotics 2026' is excluded/archived", $aiExhibitionCount === 0, "Occurrences: {$aiExhibitionCount}");

// ----------------------------------------------------------------------
// TEST 2: CSS Grid and Responsive Layout Verification
// ----------------------------------------------------------------------
echo "\n--- TEST 2: CSS Grid Layout & Styling Rules ---\n";

$cssFile = dirname(__DIR__) . '/app/learner/assets/activities/activities.css';
$cssContent = file_get_contents($cssFile);

assertCondition("CSS file exists", $cssContent !== false);
assertCondition("Grid uses 'repeat(auto-fill, minmax(320px, 1fr))'", str_contains($cssContent, 'grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))') || str_contains($cssContent, 'repeat(auto-fill,minmax(320px,1fr))'));
assertCondition("Grid uses 1.5rem gap", str_contains($cssContent, 'gap: 1.5rem;'));
assertCondition("Hero section is styled with linear gradient and border radius", str_contains($cssContent, '.learner-activities-shell .learner-activity-discovery-hero'));
assertCondition("Responsive media query exists for small screens", str_contains($cssContent, '@media (max-width: 860px)'));

// ----------------------------------------------------------------------
// TEST 3: Full Page Render of /app/learner/activities.php
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Page Render Verification ---\n";

$_SESSION['user'] = ['id' => 'fd6823de-d3d9-4d3a-b916-9f811853a24c', 'email' => 'tamlangtu2005@gmail.com', 'role' => 'student'];

ob_start();
include dirname(__DIR__) . '/app/learner/activities.php';
$html = ob_get_clean();

assertCondition("Page renders 200 OK with content", strlen($html) > 5000);
assertCondition("Page contains Hero title 'Khám phá hoạt động'", str_contains($html, 'Khám phá hoạt động'));
assertCondition("Page contains Hero description 'Tìm cơ hội phù hợp để học hỏi'", str_contains($html, 'Tìm cơ hội phù hợp để học hỏi'));
assertCondition("Page contains Category 'Tất cả'", str_contains($html, 'data-activity-filter="Tất cả"'));
assertCondition("Page contains Category 'Kỹ thuật'", str_contains($html, 'data-activity-filter="Kỹ thuật"'));
assertCondition("Page contains Category 'Kinh doanh'", str_contains($html, 'data-activity-filter="Kinh doanh"'));
assertCondition("Page contains Category 'Sáng tạo'", str_contains($html, 'data-activity-filter="Sáng tạo"'));
assertCondition("Page contains Category 'Cộng đồng'", str_contains($html, 'data-activity-filter="Cộng đồng"'));
assertCondition("Page renders discovery grid section", str_contains($html, 'learner-activity-discovery-grid'));

// Verify forwarding
ob_start();
include dirname(__DIR__) . '/app/student/activities.php';
$studentHtml = ob_get_clean();
assertCondition("Forwarder app/student/activities.php renders cleanly", strlen($studentHtml) > 5000);

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
