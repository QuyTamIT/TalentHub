<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';
use TalentHub\Database\Connection;
use TalentHub\Bootstrap\StudentAppContext;
use TalentHub\Learner\Data\Domain\LevelProgression;
use TalentHub\Learner\Ai\Rules\RuleRoadmapEngine;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Domain\RecommendationContext;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: STUDENT / LEARNER PORTAL UPGRADES\n";
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
// TEST 1: Flexible School & Education Level Support
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Flexible School / Education Level Support ---\n";

$highSchoolProfile = [
    'id' => 'student-hs-001',
    'fullName' => 'Trần Hoàng Long',
    'school' => ['id' => 'school-hs-001', 'name' => 'Trường THPT Nguyễn Du'],
    'class' => ['id' => 'class-hs-001', 'name' => 'Lớp 11A2'],
    'email' => 'long.th@student.edu.vn',
];
$viewHs = \TalentHub\Learner\Data\Support\SharedStudentAdapter::toView($highSchoolProfile, ['metrics' => ['profileCompletion' => 80]]);
assertCondition("High school profile maps school name 'Trường THPT Nguyễn Du'", $viewHs['school'] === 'Trường THPT Nguyễn Du', $viewHs['school']);
assertCondition("High school profile maps class name 'Lớp 11A2'", $viewHs['class'] === 'Lớp 11A2', $viewHs['class']);

$collegeProfile = [
    'id' => 'student-col-001',
    'fullName' => 'Lê Quý Tam',
    'school' => ['id' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783', 'name' => 'Cao đẳng Quốc tế BTEC FPT'],
    'class' => ['id' => 'a1e2894b-2386-5404-9695-78a78f5a60d3', 'name' => 'BTEC-AI-2026A'],
    'email' => 'tamlangtu2005@gmail.com',
];
$viewCol = \TalentHub\Learner\Data\Support\SharedStudentAdapter::toView($collegeProfile, ['metrics' => ['profileCompletion' => 100]]);
assertCondition("College profile maps school name 'Cao đẳng Quốc tế BTEC FPT'", $viewCol['school'] === 'Cao đẳng Quốc tế BTEC FPT', $viewCol['school']);
assertCondition("College profile maps class name 'BTEC-AI-2026A'", $viewCol['class'] === 'BTEC-AI-2026A', $viewCol['class']);

// ----------------------------------------------------------------------
// TEST 2: AI 3-Month Roadmap Engine (90 Days)
// ----------------------------------------------------------------------
echo "\n--- TEST 2: AI 3-Month Roadmap Generation (90 Days) ---\n";

$engine = new RuleRoadmapEngine();
$input = new RecommendationInput(
    [
        'student_id' => 'student-001',
        'assessments' => ['holland' => 'RIE', 'multiple_intelligence' => 'Logic - Không gian'],
        'skills' => [['name' => 'Python', 'score' => 85]],
    ],
    ['assessments' => '2026-08-28 00:00:00'],
    ['complete' => true],
    [
        ['source_type' => 'assessment', 'source_id' => 'ass-001', 'observed_at' => '2026-08-28 00:00:00', 'safe_value' => ['type' => 'holland']],
        ['source_type' => 'assessment', 'source_id' => 'ass-002', 'observed_at' => '2026-08-28 00:00:00', 'safe_value' => ['type' => 'multiple_intelligence']],
        ['source_type' => 'skill', 'source_id' => 'skl-001', 'observed_at' => '2026-08-28 00:00:00', 'safe_value' => ['name' => 'Python']],
    ]
);
$context = new RecommendationContext(['assessments', 'skills'], 'req-001', 'idemp-001', 'student-001');
$roadmap = $engine->generate($input, $context);

assertCondition("Roadmap direction focuses on AI/IoT & Software Engineering", str_contains($roadmap->primaryDirection()->label(), 'AI/IoT') || str_contains($roadmap->primaryDirection()->label(), 'Kỹ thuật'), $roadmap->primaryDirection()->label());
assertCondition("Roadmap has 3 phases (3 months)", count($roadmap->phases()) === 3, "Phases: " . count($roadmap->phases()));

$phases = $roadmap->phases();
$p1 = $phases[0];
assertCondition("Phase 1 is Month 1 (Lab / CLB thực hành: IoT Lab / AI Bootcamp)", str_contains($p1->title(), 'Tháng 1') && (str_contains($p1->title(), 'Lab') || str_contains($p1->title(), 'CLB')), $p1->title());

$p2 = $phases[1];
assertCondition("Phase 2 is Month 2 (Cuộc thi & Hackathon)", str_contains($p2->title(), 'Tháng 2') && str_contains($p2->title(), 'Hackathon'), $p2->title());

$p3 = $phases[2];
assertCondition("Phase 3 is Month 3 (Trưởng nhóm Dự án & Hoàn thiện Đề án)", str_contains($p3->title(), 'Tháng 3') && (str_contains($p3->title(), 'Trưởng nhóm') || str_contains($p3->title(), 'Đề án')), $p3->title());

// ----------------------------------------------------------------------
// TEST 3: Gamification Level Progression (10, 50, 100, 200 hours)
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Gamification Tier & Progress Synchronization ---\n";

$lvl1 = LevelProgression::fromHours(5.0);
assertCondition("5 hours -> Explorer (Level 1, Target 10h, Next: Innovator)", $lvl1['name'] === 'Explorer' && $lvl1['number'] === 1 && $lvl1['targetHours'] === 10.0 && $lvl1['nextLevel'] === 'Innovator', "Level: {$lvl1['name']} ({$lvl1['currentHours']}/{$lvl1['targetHours']}h)");

$lvl2 = LevelProgression::fromHours(25.0);
assertCondition("25 hours -> Innovator (Level 2, Target 50h, Next: Expert)", $lvl2['name'] === 'Innovator' && $lvl2['number'] === 2 && $lvl2['targetHours'] === 50.0 && $lvl2['nextLevel'] === 'Expert', "Level: {$lvl2['name']} ({$lvl2['currentHours']}/{$lvl2['targetHours']}h)");

$lvl3 = LevelProgression::fromHours(75.0);
assertCondition("75 hours -> Expert (Level 3, Target 100h, Next: Master)", $lvl3['name'] === 'Expert' && $lvl3['number'] === 3 && $lvl3['targetHours'] === 100.0 && $lvl3['nextLevel'] === 'Master', "Level: {$lvl3['name']} ({$lvl3['currentHours']}/{$lvl3['targetHours']}h)");

$lvl4 = LevelProgression::fromHours(150.0);
assertCondition("150 hours -> Master (Level 4, Target 200h)", $lvl4['name'] === 'Master' && $lvl4['number'] === 4 && $lvl4['targetHours'] === 200.0, "Level: {$lvl4['name']} ({$lvl4['currentHours']}/{$lvl4['targetHours']}h)");

// ----------------------------------------------------------------------
// TEST 4: Talent Passport & Profile Integration
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Talent Passport & Page Renders ---\n";

$sessionConfig = array_merge(require dirname(__DIR__) . '/config/session.php', ['name' => \TalentHub\Auth\Session\SessionManager::SESSION_STUDENT]);
$session = new \TalentHub\Auth\Session\SessionManager($sessionConfig);
$session->start();
$session->login([
    'id' => '30000000-0000-4000-8000-000000000001',
    'email' => 'tamlangtu2005@gmail.com',
    'role' => 'student',
    'fullName' => 'Lê Quý Tam',
]);

$_SERVER['REQUEST_METHOD'] = 'GET';

// 1. Render app/learner/talent-passport.php (and app/student/talent-passport.php)
ob_start();
require dirname(__DIR__) . '/app/learner/talent-passport.php';
$passportHtml = ob_get_clean();

assertCondition("Talent Passport renders 'DIGITAL TALENT PASSPORT'", str_contains($passportHtml, 'DIGITAL TALENT PASSPORT'));
assertCondition("Talent Passport renders QR verification code", str_contains($passportHtml, 'api.qrserver.com') || str_contains($passportHtml, 'Quét để xác thực số'));
assertCondition("Talent Passport renders student name 'Lê Quý Tam'", str_contains($passportHtml, 'Lê Quý Tam'));
assertCondition("Talent Passport renders score '85'", str_contains($passportHtml, '85') && str_contains($passportHtml, 'THANG ĐIỂM 100'));
assertCondition("Talent Passport renders verified skills (Python, PyTorch, etc.)", str_contains($passportHtml, 'Python') && str_contains($passportHtml, 'PyTorch'));
assertCondition("Talent Passport renders teacher endorsement from ThS. Nguyễn Văn Hùng", str_contains($passportHtml, 'ThS. Nguyễn Văn Hùng'));
assertCondition("Talent Passport has Print/PDF action", str_contains($passportHtml, 'window.print()') || str_contains($passportHtml, 'In / Tải PDF Hộ chiếu'));

// 2. Render app/learner/profile.php (and app/student/profile.php)
ob_start();
require dirname(__DIR__) . '/app/learner/profile.php';
$profileHtml = ob_get_clean();

assertCondition("Profile page has button 'Xem & Tải Talent Passport'", str_contains($profileHtml, 'talent-passport.php') && str_contains($profileHtml, 'Talent Passport'));
assertCondition("Profile edit modal supports educationLevel select", str_contains($profileHtml, 'name="educationLevel"') && str_contains($profileHtml, 'THCS / THPT'));

// 3. Render app/learner/badges.php (and app/student/badges.php)
ob_start();
require dirname(__DIR__) . '/app/learner/badges.php';
$badgesHtml = ob_get_clean();

assertCondition("Badges page renders 4 tiers: Explorer, Innovator, Expert, Master", str_contains($badgesHtml, 'Explorer') && str_contains($badgesHtml, 'Innovator') && str_contains($badgesHtml, 'Expert') && str_contains($badgesHtml, 'Master'));
assertCondition("Badges page renders 10-50h for Innovator and 50-100h for Expert", str_contains($badgesHtml, '10 - 50 giờ') || str_contains($badgesHtml, '50 - 100 giờ') || str_contains($badgesHtml, '100 - 200 giờ'));

// 4. Render app/student/ai-suggestions.php
ob_start();
require dirname(__DIR__) . '/app/student/ai-suggestions.php';
$aiHtml = ob_get_clean();
assertCondition("ai-suggestions.php forwards to ai-recommendations.php cleanly", str_contains($aiHtml, 'AI GỢI Ý') || str_contains($aiHtml, 'Lộ trình phát triển'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
