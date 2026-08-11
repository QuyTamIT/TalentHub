<?php
/**
 * TalentHub - Enterprise Dashboard Main Entry Point
 * 
 * Note for Junior Developers:
 * - This file orchestrates the Enterprise Dashboard layout and loads modular PHP partials from includes/
 * - Mock data is defined in arrays below and passed cleanly into partials.
 * - When database/API is ready, replace static arrays with DB fetch functions.
 */

// --------------------------------------------------------------------------
// 1. Temporary Mock Data Configuration
// --------------------------------------------------------------------------
$enterpriseInfo = [
    'company_name' => 'FPT Software',
    'account_type' => 'Gói Premium',
    'logo_initials' => 'FPT',
    'new_matches_count' => 86,
    'total_talents' => 1247
];

$sidebarNav = [
    [
        'title' => 'Tổng quan',
        'route' => '/app/enterprise',
        'icon' => 'grid',
        'active' => true
    ],
    [
        'title' => 'Tìm nhân tài',
        'route' => '/app/enterprise/talents',
        'icon' => 'search-users',
        'active' => false
    ],
    [
        'title' => 'Tuyển thực tập',
        'route' => '/app/enterprise/internships',
        'icon' => 'briefcase',
        'active' => false
    ],
    [
        'title' => 'Tài trợ dự án',
        'route' => '/app/enterprise/sponsorships',
        'icon' => 'award',
        'active' => false
    ],
    [
        'title' => 'Phân tích tuyển dụng',
        'route' => '/app/enterprise/analytics',
        'icon' => 'bar-chart',
        'active' => false
    ]
];

$kpis = [
    [
        'id' => 'talents',
        'label' => 'Hồ sơ phù hợp',
        'value' => '1,247',
        'change' => '+86 tuần này',
        'change_type' => 'positive',
        'icon' => 'user-check'
    ],
    [
        'id' => 'jobs',
        'label' => 'Tin tuyển dụng',
        'value' => '12',
        'change' => '5 tin đang mở',
        'change_type' => 'positive',
        'icon' => 'file-text'
    ],
    [
        'id' => 'projects',
        'label' => 'Dự án đã tài trợ',
        'value' => '3',
        'change' => 'Tổng: 120 triệu VNĐ',
        'change_type' => 'positive',
        'icon' => 'gift'
    ],
    [
        'id' => 'pass_rate',
        'label' => 'Tỷ lệ qua phỏng vấn',
        'value' => '94%',
        'change' => '+8% so với tháng trước',
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
        'match_score' => 98,
        'experience_hours' => '120h thực án',
        'skills' => ['React', 'Node.js', 'TypeScript', 'UI/UX']
    ],
    [
        'id' => 2,
        'name' => 'Lê Thị Bích Ngọc',
        'school' => 'Đại học Quốc Gia TP.HCM',
        'major' => 'Khoa học Dữ liệu & AI',
        'match_score' => 95,
        'experience_hours' => '95h thực án',
        'skills' => ['Python', 'PyTorch', 'SQL', 'Data Analytics']
    ],
    [
        'id' => 3,
        'name' => 'Trần Minh Đức',
        'school' => 'Đại học FPT',
        'major' => 'Kỹ thuật Phần mềm',
        'match_score' => 92,
        'experience_hours' => '150h thực án',
        'skills' => ['PHP', 'Laravel', 'MySQL', 'Docker']
    ]
];

$pendingActions = [
    [
        'title' => '8 ứng viên mới cần xem',
        'subtitle' => 'Hồ sơ tuyển dụng thực tập sinh tháng này',
        'type' => 'urgent',
        'action_label' => 'Xem danh sách',
        'route' => '/app/enterprise/internships'
    ],
    [
        'title' => '2 tin tuyển dụng sắp hết hạn',
        'subtitle' => 'Vị trí Frontend Developer & AI Research',
        'type' => 'warning',
        'action_label' => 'Gia hạn tin',
        'route' => '/app/enterprise/internships'
    ],
    [
        'title' => '1 giao dịch tài trợ đang chờ xử lý',
        'subtitle' => 'Dự án Sân chơi Năng khiếu Công nghệ 2026',
        'type' => 'info',
        'action_label' => 'Xác nhận tài trợ',
        'route' => '/app/enterprise/sponsorships'
    ],
    [
        'title' => '3 yêu cầu liên hệ chưa xử lý',
        'subtitle' => 'Yêu cầu kết nối từ Giảng viên Hướng dẫn ĐH Bách Khoa',
        'type' => 'neutral',
        'action_label' => 'Trả lời ngay',
        'route' => '/app/enterprise/talents'
    ]
];

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
    <title>Dashboard Doanh Nghiệp - FPT Software | TalentHub</title>
    
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
                        
                        <!-- Left Column (Featured Talents + Pending Actions) -->
                        <div class="ent-grid-layout__main">
                            <?php include __DIR__ . '/includes/featured-talents.php'; ?>
                            <?php include __DIR__ . '/includes/pending-actions.php'; ?>
                        </div>

                        <!-- Right Column (Activity Feed + Quick Info Widget) -->
                        <aside class="ent-grid-layout__sidebar">
                            <?php include __DIR__ . '/includes/recent-activity.php'; ?>

                            <!-- Enterprise Summary Card -->
                            <div class="ent-section-box">
                                <div class="ent-section-box__header">
                                    <h3 class="ent-section-box__title">Hồ sơ Doanh nghiệp</h3>
                                </div>
                                <div class="ent-info-widget">
                                    <div class="ent-info-widget__row">
                                        <span class="label">Doanh nghiệp:</span>
                                        <span class="val font-bold"><?= htmlspecialchars($enterpriseInfo['company_name']); ?></span>
                                    </div>
                                    <div class="ent-info-widget__row">
                                        <span class="label">Gói dịch vụ:</span>
                                        <span class="val badge-success"><?= htmlspecialchars($enterpriseInfo['account_type']); ?></span>
                                    </div>
                                    <div class="ent-info-widget__row">
                                        <span class="label">Trạng thái kết nối:</span>
                                        <span class="val text-accent">● Đang hoạt động</span>
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
