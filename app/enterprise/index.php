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

$kpis = [
    [
        'id' => 'talents',
        'label' => 'Hồ sơ ứng tuyển',
        'value' => number_format($summary['total_applicants'], 0, ',', '.'),
        'change' => "{$summary['submitted_count']} hồ sơ mới chờ xem",
        'change_type' => 'positive',
        'icon' => 'user-check'
    ],
    [
        'id' => 'jobs',
        'label' => 'Tin tuyển dụng',
        'value' => (string) $summary['total_posts'],
        'change' => "{$summary['active_posts']} tin đang mở",
        'change_type' => 'positive',
        'icon' => 'file-text'
    ],
    [
        'id' => 'projects',
        'label' => 'Dự án đã tài trợ',
        'value' => (string) $summary['sponsored_projects_count'],
        'change' => "Tổng: " . $summary['total_sponsored_formatted'],
        'change_type' => 'positive',
        'icon' => 'gift'
    ],
    [
        'id' => 'pass_rate',
        'label' => 'Tỷ lệ qua phỏng vấn',
        'value' => $summary['pass_rate_formatted'],
        'change' => "{$summary['passed_candidates']} ứng viên trúng tuyển",
        'change_type' => 'positive',
        'icon' => 'trending-up'
    ]
];

$featuredTalents = [
    [
        'id' => 1,
        'name' => 'Nguyễn Văn An',
        'school' => 'Đại học Bách Khoa Hà Nội',
        'major' => 'Công nghệ Thông tin',
        'talent_score' => 95,
        'experience_hours' => '120h thực án',
        'skills' => ['React', 'Node.js', 'TypeScript', 'UI/UX']
    ],
    [
        'id' => 2,
        'name' => 'Lê Thị Bích Ngọc',
        'school' => 'Đại học Quốc Gia TP.HCM',
        'major' => 'Khoa học Dữ liệu & AI',
        'talent_score' => 92,
        'experience_hours' => '95h thực án',
        'skills' => ['Python', 'PyTorch', 'SQL', 'Data Analytics']
    ],
    [
        'id' => 3,
        'name' => 'Trần Minh Đức',
        'school' => 'Đại học FPT',
        'major' => 'Kỹ thuật Phần mềm',
        'talent_score' => 88,
        'experience_hours' => '150h thực án',
        'skills' => ['PHP', 'Laravel', 'MySQL', 'Docker']
    ]
];

$pendingActions = [];
if ($summary['submitted_count'] > 0) {
    $pendingActions[] = [
        'title' => "{$summary['submitted_count']} ứng viên mới cần xem",
        'subtitle' => 'Hồ sơ tuyển dụng thực tập sinh cần được đánh giá',
        'type' => 'urgent',
        'action_label' => 'Xem danh sách',
        'route' => '/app/enterprise/internships/'
    ];
}
if ($summary['active_posts'] > 0) {
    $pendingActions[] = [
        'title' => "{$summary['active_posts']} tin tuyển dụng đang hoạt động",
        'subtitle' => 'Theo dõi và quản lý các đợt tiếp nhận hồ sơ',
        'type' => 'info',
        'action_label' => 'Quản lý tin',
        'route' => '/app/enterprise/internships/'
    ];
}
if ($summary['sponsored_projects_count'] > 0) {
    $pendingActions[] = [
        'title' => "{$summary['sponsored_projects_count']} dự án nhận tài trợ",
        'subtitle' => 'Theo dõi tiến độ nghiên cứu & giải ngân',
        'type' => 'neutral',
        'action_label' => 'Xem tiến độ',
        'route' => '/app/enterprise/sponsorships/'
    ];
}
if (empty($pendingActions)) {
    $pendingActions[] = [
        'title' => 'Tất cả tác vụ đã được xử lý',
        'subtitle' => 'Không có công việc tồn đọng cần giải quyết ngay',
        'type' => 'neutral',
        'action_label' => 'Đăng tin mới',
        'route' => '/app/enterprise/internships/create.php'
    ];
}

$recentActivities = [
    [
        'title' => 'Ứng viên Nguyễn Văn An vừa nộp hồ sơ vào vị trí thực tập Frontend',
        'time' => '10 phút trước',
        'type' => 'applicant'
    ],
    [
        'title' => 'Đã lưu 3 hồ sơ tài năng từ ĐH Bách Khoa Hà Nội vào danh sách ưu tiên',
        'time' => '2 giờ trước',
        'type' => 'bookmark'
    ],
    [
        'title' => 'Cập nhật nội dung tin tuyển dụng Thực tập sinh PHP/Laravel 2026',
        'time' => 'Hôm qua',
        'type' => 'edit'
    ],
    [
        'title' => 'Hoàn tất thủ tục tài trợ 50.000.000 VNĐ cho Dự án Sân chơi Năng khiếu AI',
        'time' => '2 ngày trước',
        'type' => 'sponsorship'
    ]
];
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

            <!-- Page Body Content -->
            <main class="ent-body">
                <div class="container-fluid">
                    
                    <!-- Welcome Section Partial -->
                    <?php include __DIR__ . '/includes/welcome.php'; ?>

                    <!-- KPI Cards Partial -->
                    <?php include __DIR__ . '/includes/kpi-cards.php'; ?>

                    <!-- Main Grid Section (2 Columns) -->
                    <div class="ent-grid-layout">
                        
                        <!-- Left Column (Pending Actions + Featured Talents) -->
                        <div class="ent-grid-layout__main">
                            <!-- 1. Pending Action Items (High Priority) -->
                            <?php include __DIR__ . '/includes/pending-actions.php'; ?>

                            <!-- 2. Featured Weekly Talents -->
                            <?php include __DIR__ . '/includes/featured-talents.php'; ?>
                        </div>

                        <!-- Right Column (Activity Feed + Quick Info Widget) -->
                        <aside class="ent-grid-layout__sidebar">
                            <?php include __DIR__ . '/includes/recent-activity.php'; ?>

                            <!-- Enterprise Summary Card (Compact) -->
                            <div class="ent-section-box ent-section-box--compact">
                                <div class="ent-section-box__header mb-2">
                                    <h3 class="ent-section-box__title">Hồ sơ Doanh nghiệp</h3>
                                    <span class="badge-success font-medium"><?= htmlspecialchars($enterpriseInfo['account_type']); ?></span>
                                </div>
                                <div class="ent-info-widget">
                                    <div class="ent-info-widget__row">
                                        <span class="label">Đơn vị:</span>
                                        <span class="val font-semibold text-dark"><?= htmlspecialchars($enterpriseInfo['company_name']); ?></span>
                                    </div>
                                    <div class="ent-info-widget__row" style="border-bottom: none; padding-bottom: 0;">
                                        <span class="label">Trạng thái:</span>
                                        <span class="val text-accent font-medium d-inline-flex align-items-center gap-1">
                                            <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background-color: var(--accent);"></span>
                                            Đang hoạt động
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </aside>

                    </div>
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
