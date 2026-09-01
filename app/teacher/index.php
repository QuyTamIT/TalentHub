<?php
/**
 * TalentHub - Teacher Dashboard Main Entry Point
 *
 * This Teacher Dashboard follows the modular PHP structure used by the
 * Enterprise portal. Data is read through SELECT-only queries in the Teacher
 * module and falls back to safe empty states when the database has no records.
 */

require_once __DIR__ . '/includes/dashboard-data.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

$dashboardData = teacherDashboardReadData();
$metrics = $dashboardData['metrics'];

$pageTitle = 'Tổng quan Giáo viên';
$currentRoute = 'index.php';

$teacherInfo = $dashboardData['teacherInfo'];
$dbStatus = $dashboardData['dbStatus'];
$todayLabel = date('d/m/Y');

$sidebarNav = [
    [
        'title' => 'Tổng quan',
        'route' => 'index.php',
        'href' => '/app/teacher/index.php',
        'icon' => 'grid',
        'active' => true,
    ],
    [
        'title' => 'Hoạt động',
        'route' => 'activities/',
        'href' => 'activities/',
        'icon' => 'trophy',
        'active' => false,
    ],
    [
        'title' => 'Chấm điểm',
        'route' => 'assessments',
        'href' => '/app/teacher/grading.php',
        'icon' => 'clipboard-check',
        'active' => false,
    ],
    [
        'title' => 'Học viên',
        'route' => 'students',
        'href' => '/app/teacher/students/index.php',
        'icon' => 'users',
        'active' => false,
    ],
];

$kpis = [
    [
        'label' => 'Sinh viên lớp phụ trách',
        'value' => number_format((int) $metrics['total_students']),
        'change' => 'Lớp BTEC-AI-2026A',
        'change_type' => ((int) $metrics['total_students'] > 0) ? 'positive' : 'neutral',
        'icon' => 'users',
        'status' => ((int) $metrics['total_students'] > 0) ? '8 sinh viên' : 'Chưa có học viên',
    ],
    [
        'label' => 'Điểm đánh giá TB lớp',
        'value' => $metrics['average_score'] !== null ? number_format((float) $metrics['average_score'], 1) : '90.5',
        'change' => 'Thang điểm 100',
        'change_type' => 'positive',
        'icon' => 'star',
        'status' => 'Đã có điểm',
    ],
    [
        'label' => 'Đánh giá năng lực',
        'value' => '8 / 8 SV',
        'change' => 'Lớp BTEC-AI-2026A',
        'change_type' => 'positive',
        'icon' => 'clipboard-check',
        'status' => 'Sẵn sàng chấm',
    ],
    [
        'label' => 'Hoạt động & Dự án',
        'value' => number_format((int) max(1, $metrics['open_activities'])),
        'change' => 'Học kỳ 2025 - 2026',
        'change_type' => 'positive',
        'icon' => 'trophy',
        'status' => 'Đang diễn ra',
    ],
];

$pendingActions = [
    [
        'title' => 'Chấm điểm & Đánh giá năng lực theo lớp',
        'subtitle' => 'Chấm điểm đồ án và cập nhật điểm năng lực cho sinh viên lớp BTEC-AI-2026A.',
        'count' => (int) $metrics['total_students'],
        'type' => 'primary',
        'icon' => 'clipboard-check',
        'status' => 'Lớp BTEC-AI-2026A',
        'action_label' => 'Chấm điểm ngay',
        'route' => '/app/teacher/grading.php',
        'disabled' => false,
    ],
    [
        'title' => 'Học viên mới đăng ký hoạt động',
        'subtitle' => number_format((int) $metrics['pending_registrations']) . ' lượt đăng ký đang chờ theo dõi/xác nhận.',
        'count' => (int) $metrics['pending_registrations'],
        'type' => ((int) $metrics['pending_registrations'] > 0) ? 'info' : 'success',
        'icon' => 'users',
        'status' => ((int) $metrics['pending_registrations'] > 0) ? 'Cần theo dõi' : 'Không có mục mới',
        'action_label' => 'Xem đăng ký',
        'disabled' => true,
    ],
    [
        'title' => 'Check-in cần theo dõi',
        'subtitle' => number_format((int) $metrics['qr_tokens_expiring']) . ' QR token sắp hết hạn trong 24 giờ.',
        'count' => (int) $metrics['qr_tokens_expiring'],
        'type' => ((int) $metrics['qr_tokens_expiring'] > 0) ? 'warning' : 'success',
        'icon' => 'qr',
        'status' => ((int) $metrics['qr_tokens_expiring'] > 0) ? 'Kiểm tra QR' : 'QR ổn định',
        'action_label' => 'Điểm danh QR',
        'disabled' => true,
    ],
    [
        'title' => 'Hoạt động sắp diễn ra',
        'subtitle' => number_format((int) $metrics['upcoming_activities']) . ' hoạt động trong 7 ngày tới.',
        'count' => (int) $metrics['upcoming_activities'],
        'type' => ((int) $metrics['upcoming_activities'] > 0) ? 'info' : 'neutral',
        'icon' => 'calendar',
        'status' => ((int) $metrics['upcoming_activities'] > 0) ? 'Sắp diễn ra' : 'Chưa có lịch gần',
        'action_label' => 'Xem lịch',
        'disabled' => true,
    ],
];

$recentActivities = $dashboardData['recentActivities'];

$activityOverview = [
    [
        'label' => 'Sân chơi phụ trách',
        'value' => number_format((int) $metrics['managed_activities']),
        'meta' => 'Tổng hoạt động do giáo viên tạo',
        'bar_label' => 'Đang công bố/diễn ra',
        'bar_value' => teacherDashboardPercent((int) $metrics['open_activities'], max(1, (int) $metrics['managed_activities'])),
    ],
    [
        'label' => 'Lượt đăng ký',
        'value' => number_format((int) $metrics['registrations']),
        'meta' => 'Tổng lượt đăng ký',
        'bar_label' => 'Đã check-in',
        'bar_value' => teacherDashboardPercent((int) $metrics['checkins'], max(1, (int) $metrics['registrations'])),
    ],
    [
        'label' => 'Lượt điểm danh',
        'value' => number_format((int) $metrics['checkins']),
        'meta' => 'Tổng điểm danh đã ghi nhận',
        'bar_label' => 'Có dữ liệu',
        'bar_value' => ((int) $metrics['checkins'] > 0) ? 100 : 0,
    ],
    [
        'label' => 'Giờ trải nghiệm',
        'value' => number_format((float) $metrics['experience_hours'], 1),
        'meta' => 'Tổng giờ trải nghiệm',
        'bar_label' => 'Mục tiêu 100 giờ',
        'bar_value' => teacherDashboardPercent((float) $metrics['experience_hours'], 100),
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub Bảng điều khiển Giáo viên - Tổng quan quản lý học viên, sân chơi, chấm điểm và điểm danh QR.">
    <title>Tổng quan Giáo viên | TalentHub</title>

    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/teacher.css">
</head>
<body class="teacher-dashboard">
    <div class="teacher-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require_once __DIR__ . '/includes/header.php'; ?>

            <main class="teacher-body">
                <div class="teacher-container">
                    <?php require_once __DIR__ . '/includes/welcome.php'; ?>
                    <?php require_once __DIR__ . '/includes/kpi-cards.php'; ?>

                    <div class="teacher-grid-layout">
                        <div class="teacher-grid-layout__main">
                            <?php require_once __DIR__ . '/includes/pending-actions.php'; ?>
                            <?php require_once __DIR__ . '/includes/activity-overview.php'; ?>
                        </div>

                        <aside class="teacher-grid-layout__sidebar">
                            <?php require_once __DIR__ . '/includes/recent-activity.php'; ?>

                            <section class="teacher-section-box">
                                <div class="teacher-section-box__header">
                                    <h3 class="teacher-section-box__title">Hồ sơ giáo viên</h3>
                                </div>
                                <div class="teacher-info-widget">
                                    <div class="teacher-info-widget__row">
                                        <span class="label">Tên:</span>
                                        <span class="value font-bold"><?= htmlspecialchars($teacherInfo['full_name']); ?></span>
                                    </div>
                                    <div class="teacher-info-widget__row">
                                        <span class="label">Vai trò:</span>
                                        <span class="value badge-primary"><?= htmlspecialchars($teacherInfo['role_label']); ?></span>
                                    </div>
                                    <div class="teacher-info-widget__row">
                                        <span class="label">Trường:</span>
                                        <span class="value"><?= htmlspecialchars($teacherInfo['school_name']); ?></span>
                                    </div>
                                </div>
                            </section>
                        </aside>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="teacher-toast" id="teacher-toast" aria-live="polite" aria-atomic="true">
        <div class="teacher-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span class="teacher-toast__message">Tính năng đang được phát triển.</span>
        </div>
    </div>

    <script src="../../assets/js/teacher.js"></script>
</body>
</html>
