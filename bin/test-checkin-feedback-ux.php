<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Modules\Teacher\Repository\TeacherActivityRepository;
use TalentHub\Modules\Teacher\Service\TeacherActivityService;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: CHECK-IN SUCCESS FEEDBACK UX\n";
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
// TEST 1: CSS Design System Specifications
// ----------------------------------------------------------------------
echo "\n--- TEST 1: CSS Design System Specifications ---\n";
$css = file_get_contents(dirname(__DIR__) . '/assets/css/learner.css');

assertCondition("Success alert box class exists in CSS", str_contains($css, '.learner-checkin-success-box'));
assertCondition("Success alert box has #ECFDF5 background", str_contains($css, 'background: #ECFDF5;') || str_contains($css, 'background:#ECFDF5'));
assertCondition("Success alert box has 1.5px solid #34D399 border", str_contains($css, 'border: 1.5px solid #34D399') || str_contains($css, 'border:1.5px solid #34D399'));
assertCondition("Success alert box has 12px border radius", str_contains($css, 'border-radius: 12px') || str_contains($css, 'border-radius:12px'));
assertCondition("Success alert box icon has #059669 color & #D1FAE5 bg", str_contains($css, '#059669') && str_contains($css, '#D1FAE5'));
assertCondition("Success alert box title has #065F46 color", str_contains($css, '#065F46'));
assertCondition("Success alert box desc has #047857 color", str_contains($css, '#047857'));
assertCondition("Fade-in & Scale-up keyframes learnerCheckinSuccessIn exist", str_contains($css, '@keyframes learnerCheckinSuccessIn'));

// ----------------------------------------------------------------------
// TEST 2: JavaScript Check-in Logic & Feedback Box Rendering
// ----------------------------------------------------------------------
echo "\n--- TEST 2: JavaScript Logic & Feedback Box Generation ---\n";
$js = file_get_contents(dirname(__DIR__) . '/assets/js/learner-checkin.js');

assertCondition("JS defines renderSuccessBox helper", str_contains($js, 'renderSuccessBox'));
assertCondition("JS renders 'Điểm danh thành công!' title", str_contains($js, 'Điểm danh thành công!'));
assertCondition("JS renders 'Hệ thống đã ghi nhận +... giờ trải nghiệm' message", str_contains($js, 'Hệ thống đã ghi nhận +'));
assertCondition("JS renders checkmark SVG icon in success box", str_contains($js, 'learner-checkin-success-box__icon'));
assertCondition("JS auto-clears token input upon success", str_contains($js, "tokenField.value = ''") || str_contains($js, 'tokenField.value = ""'));
assertCondition("JS reveals history action link upon success", str_contains($js, 'historyAction.hidden = false'));

// ----------------------------------------------------------------------
// TEST 3: Page Renders & Forwarders
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Page Renders & Forwarders ---\n";

$studentUserId = 'fd6823de-d3d9-4d3a-b916-9f811853a24c';
$studentProfileId = 'f3150ce0-7a99-4d5f-8b03-c293b91e37e5';
$_SESSION['user'] = ['id' => $studentUserId, 'email' => 'tamlangtu2005@gmail.com', 'role' => 'student'];

ob_start();
include dirname(__DIR__) . '/app/learner/checkin.php';
$htmlLearner = ob_get_clean();

assertCondition("app/learner/checkin.php renders 200 OK with content", strlen($htmlLearner) > 3000);
assertCondition("app/learner/checkin.php has manual token textarea [data-manual-token]", str_contains($htmlLearner, 'data-manual-token'));
assertCondition("app/learner/checkin.php has [data-api-state] container", str_contains($htmlLearner, 'data-api-state'));
assertCondition("app/learner/checkin.php has [data-checkin-history-action] link", str_contains($htmlLearner, 'data-checkin-history-action'));
assertCondition("app/learner/checkin.php has [data-checkin-history] list", str_contains($htmlLearner, 'data-checkin-history'));

ob_start();
include dirname(__DIR__) . '/app/student/checkin.php';
$htmlStudent = ob_get_clean();

assertCondition("app/student/checkin.php forwards cleanly and renders 200 OK", strlen($htmlStudent) > 3000 && str_contains($htmlStudent, 'data-manual-token'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
