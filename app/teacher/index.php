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

$teacherSlideUi = true;
$pageTitle = 'Tổng quan Giáo viên';
$currentRoute = 'index.php';

$teacherInfo = $dashboardData['teacherInfo'];
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

$managedClassName = (string) ($teacherInfo['managed_class_name'] ?? '');
$managedClassLabel = $managedClassName !== '' ? "Lớp {$managedClassName}" : 'Chưa phân công lớp';
$totalStudents = (int) ($metrics['total_students'] ?? 0);
$pendingAssessments = (int) ($metrics['pending_assessments'] ?? 0);
$assessedCount = max(0, $totalStudents - $pendingAssessments);

$kpis = [
    [
        'label' => 'Sinh viên lớp phụ trách',
        'value' => number_format($totalStudents),
        'change' => $managedClassLabel,
        'change_type' => ($totalStudents > 0) ? 'positive' : 'neutral',
        'icon' => 'users',
        'status' => ($totalStudents > 0) ? ($totalStudents . ' sinh viên') : 'Chưa có học viên',
    ],
    [
        'label' => 'Điểm đánh giá TB lớp',
        'value' => $metrics['average_score'] !== null ? number_format((float) $metrics['average_score'], 1) : 'Chưa có',
        'change' => 'Thang điểm 100',
        'change_type' => $metrics['average_score'] !== null ? 'positive' : 'neutral',
        'icon' => 'star',
        'status' => $metrics['average_score'] !== null ? 'Đã có điểm' : 'Chưa có điểm',
    ],
    [
        'label' => 'Đánh giá năng lực',
        'value' => ($totalStudents > 0) ? "{$assessedCount} / {$totalStudents} SV" : '0 / 0 SV',
        'change' => $managedClassLabel,
        'change_type' => ($totalStudents > 0) ? 'positive' : 'neutral',
        'icon' => 'clipboard-check',
        'status' => ($totalStudents > 0) ? 'Sẵn sàng chấm' : 'Chưa có học viên',
    ],
    [
        'label' => 'Hoạt động & Dự án',
        'value' => number_format((int) $metrics['open_activities']),
        'change' => 'Học kỳ 2025 - 2026',
        'change_type' => ((int) $metrics['open_activities'] > 0) ? 'positive' : 'neutral',
        'icon' => 'trophy',
        'status' => ((int) $metrics['open_activities'] > 0) ? 'Đang diễn ra' : 'Chưa có hoạt động',
    ],
];

// Reuse the managed activity reader so the overview uses the same data as the list.
require_once __DIR__ . '/includes/activity-data.php';
$overviewPdo = teacherDashboardConnect();
$managedActivities = $overviewPdo && !empty($teacherInfo['id'])
    ? array_slice(teacherActivitiesRead($overviewPdo, (string) $teacherInfo['id']), 0, 4) : [];
$kpis = [$kpis[0], $kpis[3], $kpis[2], $kpis[1]];
$kpis[0]['label'] = 'Học viên';
$kpis[1]['label'] = 'Sân chơi đang mở';
$kpis[1]['change'] = number_format((int) $metrics['upcoming_activities']) . ' sắp diễn ra';
$kpis[2]['label'] = 'Học viên cần đánh giá';
$kpis[2]['value'] = number_format($pendingAssessments);
$kpis[2]['change'] = 'Theo lớp phụ trách';
$kpis[3]['label'] = 'Điểm đánh giá trung bình';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub Bảng điều khiển Giáo viên - Tổng quan quản lý học viên, sân chơi, chấm điểm và điểm danh QR.">
    <title>Tổng quan Giáo viên | TalentHub</title>

    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/teacher.css">
    <link rel="stylesheet" href="<?= app_href('/assets/css/teacher-slide.css'); ?>">
</head>
<body class="teacher-dashboard teacher-slide-ui">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require_once __DIR__ . '/includes/header.php'; ?>

            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    <?php require_once __DIR__ . '/includes/welcome.php'; ?>
                    <?php require_once __DIR__ . '/includes/kpi-cards.php'; ?>

                    <?php require __DIR__ . '/includes/slide-overview.php'; ?>
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
