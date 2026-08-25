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

$featuredTalents = [];
$pdo = $context['pdo'] ?? null;
$talentService = $context['talents'] ?? null;

if ($isVerified && $talentService !== null) {
    try {
        $talentRes = $talentService->listTalents((string) $user['id'], ['limit' => 3]);
        $items = $talentRes['items'] ?? [];
        foreach ($items as $t) {
            $skills = is_array($t['skills'] ?? null) ? $t['skills'] : [];
            if (empty($skills) && !empty($t['skillsStr'])) {
                $skills = array_filter(array_map('trim', explode(',', $t['skillsStr'])));
            }
            $featuredTalents[] = [
                'id'               => (string) ($t['id'] ?? $t['student_id'] ?? ''),
                'name'             => (string) ($t['name'] ?? $t['fullName'] ?? 'Ứng viên tiềm năng'),
                'school'           => (string) ($t['school'] ?? $t['schoolName'] ?? 'Trường liên kết'),
                'major'            => (string) ($t['major'] ?? 'Chuyên ngành kỹ thuật'),
                'talent_score'     => (int) ($t['match_score'] ?? $t['talent_score'] ?? 90),
                'experience_hours' => (string) ($t['experience_hours'] ?? '100+ giờ dự án'),
                'skills'           => $skills,
            ];
        }
    } catch (\Throwable $e) {
        $featuredTalents = [];
    }
}

if (empty($featuredTalents) && $pdo !== null) {
    try {
        $stmtTalent = $pdo->query(<<<'SQL'
            SELECT sp.id, u.fullName AS name, COALESCE(s.name, 'Trường đại học') AS school,
                   COALESCE(sp.major, 'Công nghệ thông tin') AS major,
                   COALESCE((SELECT GROUP_CONCAT(sk.name SEPARATOR ', ') FROM student_skills sk WHERE sk.studentId = sp.id), '') AS skillsStr
            FROM student_profiles sp
            JOIN users u ON u.id = sp.userId
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            WHERE u.status = 'active'
            ORDER BY sp.createdAt DESC
            LIMIT 3
        SQL);
        if ($stmtTalent !== false) {
            $rows = $stmtTalent->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $skills = $r['skillsStr'] !== '' ? array_filter(array_map('trim', explode(',', $r['skillsStr']))) : ['Lập trình', 'Giải quyết vấn đề'];
                $featuredTalents[] = [
                    'id'               => (string) $r['id'],
                    'name'             => (string) $r['name'],
                    'school'           => (string) $r['school'],
                    'major'            => (string) $r['major'],
                    'talent_score'     => 90,
                    'experience_hours' => '90+ giờ thực tế',
                    'skills'           => $skills,
                ];
            }
        }
    } catch (\Throwable) {}
}

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

$recentActivities = [];
if ($pdo !== null) {
    try {
        $stmtAct = $pdo->prepare(<<<'SQL'
            SELECT ia.appliedAt AS act_time, CONCAT('Ứng viên nộp hồ sơ vào vị trí "', ip.title, '"') AS title, 'applicant' AS type
            FROM internship_applications ia
            JOIN internship_posts ip ON ip.id = ia.postId
            WHERE ip.enterpriseId = :eId
            ORDER BY ia.appliedAt DESC
            LIMIT 4
        SQL);
        $stmtAct->execute(['eId' => $enterprise['id']]);
        $acts = $stmtAct->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($acts as $act) {
            $timeFormatted = 'Vừa xong';
            try {
                $timeFormatted = (new DateTimeImmutable($act['act_time']))->format('d/m/Y H:i');
            } catch (\Throwable) {}
            $recentActivities[] = [
                'title' => (string) $act['title'],
                'time' => $timeFormatted,
                'type' => (string) $act['type'],
            ];
        }
    } catch (\Throwable) {}
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
