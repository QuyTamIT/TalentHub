<?php
/**
 * TalentHub - Enterprise Dashboard Main Entry Point
 *
 * Boots EnterpriseAppContext and resolves dynamic company data from database.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/EnterpriseAppContext.php';

use TalentHub\Bootstrap\EnterpriseAppContext;

$context = (new EnterpriseAppContext())->boot();
$user       = $context['user'];
$enterprise = $context['enterprise'];
$dashboard  = $context['dashboard'];
$workflowService = $context['workflows'];

if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        $words = preg_split('/\s+/', trim($name));
        if (empty($words) || $words[0] === '') return 'DN';
        if (count($words) === 1) return mb_strtoupper(mb_substr($words[0], 0, 2));
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
    }
}

$companyInitials = getInitials($enterprise['name']);
$isVerified = ($enterprise['verificationStatus'] ?? 'pending') === 'verified';
$accountType = $isVerified ? 'Doanh nghiệp Đã xác thực' : 'Tài khoản Doanh nghiệp';

$summary = [
    'total_posts' => 0,
    'active_posts' => 0,
    'closed_posts' => 0,
    'total_applicants' => 0,
    'submitted_count' => 0,
    'reviewing_count' => 0,
    'qualified_candidates' => 0,
    'qualified_percentage' => '0%',
    'interviewing' => 0,
    'passed_candidates' => 0,
    'declined_candidates' => 0,
    'pass_rate' => 0.0,
    'pass_rate_formatted' => '0%',
    'sponsored_projects_count' => 0,
    'total_sponsored_amount' => '0.00',
    'total_sponsored_formatted' => '0 VNĐ',
    'matched_talents_count' => 0,
];

try {
    $analyticsData = $workflowService->analytics((string) $user['id']);
    if (!empty($analyticsData['summary'])) {
        $summary = array_merge($summary, $analyticsData['summary']);
    }
} catch (\Throwable $e) {
    error_log('Enterprise index analytics fetch failed: ' . $e->getMessage());
}

$enterpriseInfo = [
    'id'                => $enterprise['id'],
    'company_name'      => $enterprise['name'],
    'account_type'      => $accountType,
    'logo_initials'     => $companyInitials,
    'logo_url'          => $enterprise['logoUrl'] ?? null,
    'new_matches_count' => $summary['qualified_candidates'],
    'total_talents'     => $summary['matched_talents_count'],
];

$sidebarNav = [
    [
        'title'  => 'Tổng quan',
        'route'  => '/app/enterprise/index.php',
        'icon'   => 'grid',
        'active' => true,
    ],
    [
        'title'  => 'Tìm nhân tài',
        'route'  => '/app/enterprise/talents.php',
        'icon'   => 'search-users',
        'active' => false,
    ],
    [
        'title'  => 'Tuyển thực tập',
        'route'  => '/app/enterprise/internships/',
        'icon'   => 'briefcase',
        'active' => false,
    ],
    [
        'title'  => 'Tài trợ dự án',
        'route'  => '/app/enterprise/sponsorships/',
        'icon'   => 'award',
        'active' => false,
    ],
    [
        'title'  => 'Phân tích tuyển dụng',
        'route'  => '/app/enterprise/analytics.php',
        'icon'   => 'bar-chart-2',
        'active' => false,
    ],
    [
        'title'  => 'Hồ sơ doanh nghiệp',
        'route'  => '/app/enterprise/profile.php',
        'icon'   => 'building',
        'active' => false,
    ],
];

$kpiApplicantsVal = (string) ($summary['total_applicants'] ?? 0);
$kpiApplicantsChange = ($summary['total_applicants'] ?? 0) > 0 
    ? "+ {$summary['total_applicants']} hồ sơ mới" 
    : '0 hồ sơ mới chờ xem';

$kpiJobsVal = (string) (max((int)($summary['active_posts'] ?? 0), 2));
$kpiJobsChange = "{$kpiJobsVal} tin đang mở";

$kpiProjectsVal = (string) ($summary['sponsored_projects_count'] ?? 0);
$kpiProjectsChange = (!empty($summary['total_sponsored_formatted']) && $summary['total_sponsored_formatted'] !== '0 VNĐ') 
    ? "Tổng: {$summary['total_sponsored_formatted']}" 
    : 'Tổng: 0 VNĐ';

$kpiPassRateVal = (!empty($summary['pass_rate_formatted']) && $summary['pass_rate_formatted'] !== '0%') 
    ? $summary['pass_rate_formatted'] 
    : '0%';
$kpiPassRateChange = (!empty($summary['pass_rate']) && $summary['pass_rate'] > 0) 
    ? "↑ " . round($summary['pass_rate']) . "%" 
    : 'Chưa có ứng viên';

$kpis = [
    [
        'id' => 'talents',
        'label' => 'Hồ sơ ứng tuyển',
        'value' => $kpiApplicantsVal,
        'change' => $kpiApplicantsChange,
        'change_type' => 'neutral',
        'icon' => 'user-check',
        'color' => 'blue'
    ],
    [
        'id' => 'jobs',
        'label' => 'Tin tuyển dụng',
        'value' => $kpiJobsVal,
        'change' => $kpiJobsChange,
        'change_type' => 'positive',
        'icon' => 'file-text',
        'color' => 'amber'
    ],
    [
        'id' => 'projects',
        'label' => 'Dự án đã tài trợ',
        'value' => $kpiProjectsVal,
        'change' => $kpiProjectsChange,
        'change_type' => 'neutral',
        'icon' => 'gift',
        'color' => 'purple'
    ],
    [
        'id' => 'pass_rate',
        'label' => 'Tỷ lệ tuyển dụng',
        'value' => $kpiPassRateVal,
        'change' => $kpiPassRateChange,
        'change_type' => 'neutral',
        'icon' => 'trending-up',
        'color' => 'emerald'
    ]
];

$featuredTalents = [];
$pdo = $context['pdo'] ?? null;
$talentService = $context['talents'] ?? null;
$avatarColors = ['#F97316', '#3B82F6', '#8B5CF6'];

if ($isVerified && $talentService !== null) {
    try {
        $talentRes = $talentService->listTalents((string) $user['id'], ['limit' => 3]);
        $items = $talentRes['items'] ?? [];
        $index = 0;
        foreach ($items as $t) {
            $name = (string) ($t['name'] ?? $t['displayName'] ?? $t['fullName'] ?? 'Ứng viên tiềm năng');
            $nameWords = preg_split('/\s+/', trim($name));
            $lastWord = end($nameWords);
            $avatarLetter = mb_strtoupper(mb_substr((string)$lastWord, 0, 1, 'UTF-8'));
            $skills = is_array($t['skills'] ?? null) ? $t['skills'] : [];
            if (empty($skills) && !empty($t['skillsStr'])) {
                $skills = array_filter(array_map('trim', explode(',', $t['skillsStr'])));
            }
            $className = (string) ($t['className'] ?? $t['class_name'] ?? 'Lớp 12B3');
            $schoolName = (string) ($t['schoolName'] ?? $t['school'] ?? 'THPT Nguyễn Du');
            $skillsText = !empty($skills) ? implode(', ', array_slice($skills, 0, 3)) : 'AI, Robotics, Python';

            $featuredTalents[] = [
                'id'               => (string) ($t['id'] ?? $t['studentId'] ?? $t['student_id'] ?? ''),
                'name'             => $name,
                'avatar_letter'    => $avatarLetter,
                'avatar_bg'        => $avatarColors[$index % count($avatarColors)],
                'talent_score'     => (int) ($t['talent_score'] ?? $t['match_score'] ?? 95),
                'meta_description' => "{$className} • {$schoolName} • {$skillsText}",
            ];
            $index++;
        }
    } catch (\Throwable $e) {
        $featuredTalents = [];
    }
}

if (empty($featuredTalents) && $pdo !== null) {
    try {
        $stmtTalent = $pdo->query(<<<'SQL'
            SELECT sp.id, u.fullName AS name, 
                   COALESCE(c.name, 'Lớp học') AS className,
                   COALESCE(s.name, 'Trường học') AS schoolName,
                   COALESCE((SELECT GROUP_CONCAT(sk.name SEPARATOR ', ') FROM student_skills sk WHERE sk.studentId = sp.id), '') AS skillsStr,
                   COALESCE((SELECT COUNT(*) FROM student_skills sk WHERE sk.studentId = sp.id), 0) AS skillCount
            FROM student_profiles sp
            JOIN users u ON u.id = sp.userId
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            WHERE u.status = 'active'
            ORDER BY skillCount DESC, sp.createdAt DESC
            LIMIT 3
        SQL);
        if ($stmtTalent !== false) {
            $rows = $stmtTalent->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $index = 0;
            foreach ($rows as $r) {
                $name = trim((string) ($r['name'] ?? ''));
                if ($name === '') continue;
                $nameWords = preg_split('/\s+/', $name);
                $lastWord = end($nameWords);
                $avatarLetter = mb_strtoupper(mb_substr((string)$lastWord, 0, 1, 'UTF-8'));
                $skillsStr = trim((string) ($r['skillsStr'] ?? ''));
                $className = (string) ($r['className'] ?? 'Lớp học');
                $schoolName = (string) ($r['schoolName'] ?? 'Trường học');
                $skillCount = (int) ($r['skillCount'] ?? 0);
                $talentScore = min(99, max(85, 85 + ($skillCount * 4)));

                $metaParts = array_filter([$className, $schoolName, $skillsStr]);
                $metaText = !empty($metaParts) ? implode(' • ', $metaParts) : 'Học sinh tiềm năng';

                $featuredTalents[] = [
                    'id'               => (string) $r['id'],
                    'name'             => $name,
                    'avatar_letter'    => $avatarLetter,
                    'avatar_bg'        => $avatarColors[$index % count($avatarColors)],
                    'talent_score'     => $talentScore,
                    'meta_description' => $metaText,
                ];
                $index++;
            }
        }
    } catch (\Throwable $e) {
        $featuredTalents = [];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub Enterprise Dashboard - Quản lý tuyển dụng và kết nối tài năng dành cho Doanh nghiệp.">
    <title>Dashboard Doanh Nghiệp - <?= htmlspecialchars($enterpriseInfo['company_name']); ?> | TalentHub</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/enterprise.css">
</head>
<body class="enterprise-dashboard">

    <!-- Layout Wrapper -->
    <div class="ent-layout">
        
        <!-- Sidebar Navigation Partial -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="ent-main-wrapper">
            
            <!-- Top Header Partial -->
            <?php include __DIR__ . '/includes/header.php'; ?>

            <!-- Page Body Content - Single Column Stack -->
            <main class="ent-body">
                <div class="container-fluid ent-dashboard-container">
                    
                    <!-- 1. Hero Banner Chào Mừng -->
                    <?php include __DIR__ . '/includes/welcome.php'; ?>

                    <!-- 2. Hàng Thẻ Thống Kê Chỉ Số (Metrics Bar) -->
                    <?php include __DIR__ . '/includes/kpi-cards.php'; ?>

                    <!-- 3. Bảng Nhân Tài Nổi Bật Tuần Này (Featured Talents) -->
                    <?php include __DIR__ . '/includes/featured-talents.php'; ?>

                </div>
            </main>
        </div>
    </div>

    <!-- Notification Toast for Temporary Routes -->
    <div class="ent-toast" id="ent-toast" aria-live="polite" aria-atomic="true">
        <div class="ent-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span class="ent-toast__message">Chức năng đang được phát triển!</span>
        </div>
    </div>

    <!-- JavaScript Assets -->
    <script src="../../assets/js/enterprise.js"></script>
</body>
</html>
