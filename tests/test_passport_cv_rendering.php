<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/ReadModel/PassportCvViewModel.php';

$mockData = [
    'student' => [
        'id' => '11111111-1111-4111-8111-111111111111',
        'fullName' => 'Nguyễn Hoài An',
        'email' => 'an.nh@talenthub.vn',
        'phone' => '0901234567',
        'location' => 'Hà Nội',
        'school' => 'Đại học FPT',
        'class' => 'K18-AI',
        'headline' => 'Kỹ sư Trí tuệ Nhân tạo',
        'bio' => 'Đam mê nghiên cứu Machine Learning.',
        'avatarUrl' => null,
    ],
    'skills' => [
        ['name' => 'Python', 'levelScore' => 88, 'verificationStatus' => 'verified'],
    ],
    'projects' => [
        ['id' => 'p1', 'title' => 'Hệ thống AI Khuyến nghị', 'category' => 'AI', 'status' => 'completed', 'role' => 'Lead', 'contribution' => 'Thiết kế model', 'memberStatus' => 'active'],
    ],
    'internships' => [],
    'teacher_evaluations' => [],
    'assessment_results' => [],
    'experience' => ['confirmed_entries' => [], 'summary' => ['total_hours' => 20, 'total_activities' => 3]],
    'badges' => [['name' => 'Innovator', 'description' => 'Huy hiệu Đổi mới Sáng tạo']],
];

$cv = \TalentHub\Learner\Data\ReadModel\PassportCvViewModel::build($mockData, '14/09/2026 21:00:00');

// Test 1: Student view
$isGuestView = false;
$verificationUrl = 'https://talenthub.vn/app/learner/shared-profile.php?code=' . urlencode($cv['passport_code']);
ob_start();
require dirname(__DIR__) . '/app/learner/includes/passport-cv-template.php';
$studentHtml = ob_get_clean();

assert(!str_contains($studentHtml, '360°'), 'Student CV must not contain 360°');
assert(!str_contains($studentHtml, ' 360 '), 'Student CV must not contain 360');
assert(str_contains($studentHtml, 'profile.php'), 'Student toolbar must link to profile.php');
assert(str_contains($studentHtml, 'Lấy dữ liệu mới &amp; xuất PDF'), 'Student toolbar has refresh & export button');

// Test 2: Guest view
$isGuestView = true;
ob_start();
require dirname(__DIR__) . '/app/learner/includes/passport-cv-template.php';
$guestHtml = ob_get_clean();

assert(str_contains($guestHtml, 'Hồ sơ CV xác thực điện tử bởi TalentHub'), 'Guest toolbar shows verified banner');
assert(str_contains($guestHtml, 'In / Tải PDF'), 'Guest toolbar has In / Tải PDF button');
assert(!str_contains($guestHtml, 'talent-passport.php'), 'Guest view must not contain internal links');

echo "PASS: Passport CV template renders cleanly without 360 and supports both student and guest view.\n";
