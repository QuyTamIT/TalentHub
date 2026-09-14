<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/ReadModel/PassportCvViewModel.php';

use TalentHub\Database\Connection;
use TalentHub\Auth\Session\SessionManager;

echo "======================================================================\n";
echo " RUNNING COMPREHENSIVE TEST SUITE: STREAMLINED PROFILE & UNIFIED CV\n";
echo "======================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $title, bool $condition): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$title}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$title}\n";
    }
}

// ----------------------------------------------------------------------
// 1. Test Profile Page Action Buttons
// ----------------------------------------------------------------------
echo "--- 1. Profile Page Action Buttons ---\n";
$sessionConfig = array_merge(require dirname(__DIR__) . '/config/session.php', ['name' => SessionManager::SESSION_STUDENT]);
$session = new SessionManager($sessionConfig);
$session->start();
$session->login([
    'id' => '30000000-0000-4000-8000-000000000001',
    'email' => 'tamlangtu2005@gmail.com',
    'role' => 'student',
    'fullName' => 'Lê Quý Tam',
]);
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
require dirname(__DIR__) . '/app/learner/profile.php';
$profileHtml = ob_get_clean();

assertTest("Profile has primary 'Xuất CV' button linking to talent-passport-cv.php",
    str_contains($profileHtml, 'talent-passport-cv.php') && str_contains($profileHtml, 'Xuất CV'));
assertTest("Profile does NOT have 'Xuất CV A4' text",
    !str_contains($profileHtml, 'Xuất CV A4'));
assertTest("Profile does NOT link to talent-passport.php",
    !str_contains($profileHtml, 'href="talent-passport.php"'));
assertTest("Profile has 'Chia sẻ hồ sơ' button",
    str_contains($profileHtml, 'Chia sẻ hồ sơ'));
assertTest("Profile has 'Chỉnh sửa' button",
    str_contains($profileHtml, 'Chỉnh sửa'));
assertTest("Share modal links to talent-passport-cv.php",
    str_contains($profileHtml, 'href="talent-passport-cv.php"'));

// ----------------------------------------------------------------------
// 2. Test CV Template Content & '360' Removal
// ----------------------------------------------------------------------
echo "\n--- 2. CV Template & '360' Removal ---\n";
$mockData = [
    'student' => [
        'id' => '30000000-0000-4000-8000-000000000001',
        'fullName' => 'Lê Quý Tam',
        'email' => 'tam.lq@talenthub.vn',
        'phone' => '0901234567',
        'location' => 'Hà Nội',
        'school' => 'Đại học FPT',
        'class' => 'K18-AI',
        'headline' => 'Kỹ sư Trí tuệ Nhân tạo',
        'bio' => 'Năng động, sáng tạo.',
        'avatarUrl' => null,
    ],
    'skills' => [
        ['name' => 'Python', 'levelScore' => 90, 'verificationStatus' => 'verified'],
    ],
    'projects' => [
        ['id' => 'p1', 'title' => 'TalentHub AI Match', 'category' => 'AI', 'status' => 'completed', 'role' => 'Tech Lead', 'contribution' => 'Core model', 'memberStatus' => 'active'],
    ],
    'internships' => [],
    'teacher_evaluations' => [],
    'assessment_results' => [],
    'experience' => ['confirmed_entries' => [], 'summary' => ['total_hours' => 35, 'total_activities' => 5]],
    'badges' => [['name' => 'Innovator', 'description' => 'Huy hiệu Sáng tạo']],
];

$cv = \TalentHub\Learner\Data\ReadModel\PassportCvViewModel::build($mockData, '14/09/2026 21:00:00');

// Student View
$isGuestView = false;
$verificationUrl = 'https://talenthub.vn/app/learner/shared-profile.php?code=' . urlencode($cv['passport_code']);
ob_start();
require dirname(__DIR__) . '/app/learner/includes/passport-cv-template.php';
$studentCvHtml = ob_get_clean();

assertTest("Student CV has no '360°'", !str_contains($studentCvHtml, '360°'));
assertTest("Student CV has no '360 '", !str_contains($studentCvHtml, ' 360 '));
assertTest("Verified badge is '✓ Xác thực Năng lực TalentHub'", str_contains($studentCvHtml, '✓ Xác thực Năng lực TalentHub'));
assertTest("Seal title is 'TALENT PASSPORT'", str_contains($studentCvHtml, '<span class="cv-seal-title">TALENT PASSPORT</span>'));
assertTest("Student CV toolbar links to profile.php", str_contains($studentCvHtml, 'href="profile.php"'));
assertTest("Student CV toolbar has 'Lấy dữ liệu mới & xuất PDF'", str_contains($studentCvHtml, 'Lấy dữ liệu mới &amp; xuất PDF'));

// Guest View
$isGuestView = true;
ob_start();
require dirname(__DIR__) . '/app/learner/includes/passport-cv-template.php';
$guestCvHtml = ob_get_clean();

assertTest("Guest CV shows 'Hồ sơ CV xác thực điện tử bởi TalentHub'", str_contains($guestCvHtml, 'Hồ sơ CV xác thực điện tử bởi TalentHub'));
assertTest("Guest CV has 'In / Tải PDF' button", str_contains($guestCvHtml, 'In / Tải PDF'));
assertTest("Guest CV does NOT contain link to profile.php or internal dashboard", !str_contains($guestCvHtml, 'href="profile.php"'));

// ----------------------------------------------------------------------
// 3. Test Shared Profile Integration (QR Scan & Share Link)
// ----------------------------------------------------------------------
echo "\n--- 3. Shared Profile Integration (QR & Share Link) ---\n";
$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$stmt = $pdo->query("SELECT sp.id, u.fullName FROM student_profiles sp JOIN users u ON u.id = sp.userId WHERE u.status = 'active' LIMIT 1");
$activeStudent = $stmt->fetch(PDO::FETCH_ASSOC);

if ($activeStudent) {
    $sId = (string)$activeStudent['id'];
    $passportCode = 'TP-' . strtoupper(substr(str_replace('-', '', $sId), 0, 8));

    // Test with QR code
    $_GET = ['code' => $passportCode];
    ob_start();
    require dirname(__DIR__) . '/app/learner/shared-profile.php';
    $sharedHtml = ob_get_clean();

    assertTest("Shared profile renders identical .cv-sheet element", str_contains($sharedHtml, 'class="cv-sheet"'));
    assertTest("Shared profile renders .cv-sidebar", str_contains($sharedHtml, 'class="cv-sidebar"'));
    assertTest("Shared profile renders .cv-main", str_contains($sharedHtml, 'class="cv-main"'));
    assertTest("Shared profile renders guest verified toolbar", str_contains($sharedHtml, 'Hồ sơ CV xác thực điện tử bởi TalentHub'));
    assertTest("Shared profile has In / Tải PDF button", str_contains($sharedHtml, 'In / Tải PDF'));
    assertTest("Shared profile does not have 360°", !str_contains($sharedHtml, '360°'));
} else {
    echo "  [SKIP] No active student for DB test\n";
}

// Test with invalid code -> 404 page
$_GET = ['code' => 'INVALID_TOKEN_TEST'];
ob_start();
require dirname(__DIR__) . '/app/learner/shared-profile.php';
$notFoundHtml = ob_get_clean();

assertTest("Invalid code renders friendly 404 page", str_contains($notFoundHtml, 'Không tìm thấy hồ sơ'));
assertTest("Invalid code page has link to homepage", str_contains($notFoundHtml, 'Về trang chủ TalentHub'));

// ----------------------------------------------------------------------
// 4. Test CSS A4 Page Fitting Specifications
// ----------------------------------------------------------------------
echo "\n--- 4. A4 1-Page CSS & Print Rules ---\n";
$cssContent = file_get_contents(dirname(__DIR__) . '/assets/css/learner-passport-cv.css');

assertTest("CSS defines A4 portrait size", str_contains($cssContent, 'size: A4 portrait;'));
assertTest("CSS defines 210mm width for sheet", str_contains($cssContent, 'width: 210mm;'));
assertTest("CSS defines print media hiding toolbar", str_contains($cssContent, '.cv-toolbar { display: none !important; }'));
assertTest("CSS handles page overflow check", str_contains($cssContent, 'body.cv-overflow'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passCount} | Failed: {$failCount}\n";
echo "======================================================================\n";

exit($failCount > 0 ? 1 : 0);
