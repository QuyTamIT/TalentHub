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
        'title' => 'Sân chơi của tôi',
        'route' => 'activities/',
        'href' => '/app/teacher/activities/index.php',
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
$openActivities = (int) ($metrics['open_activities'] ?? 0);
$managedActivitiesCount = (int) ($metrics['managed_activities'] ?? 0);
$pendingAssessments = (int) ($metrics['pending_assessments'] ?? 0);
$assessedCount = max(0, $totalStudents - $pendingAssessments);

$kpis = [
    [
        'label' => 'Học viên',
        'value' => number_format($totalStudents),
        'change' => $managedClassLabel,
        'change_type' => ($totalStudents > 0) ? 'positive' : 'neutral',
        'icon' => 'users',
        'status' => ($totalStudents > 0) ? ($totalStudents . ' học viên') : 'Chưa có học viên',
    ],
    [
        'label' => 'Sân chơi đang mở',
        'value' => number_format($openActivities),
        'change' => ($managedActivitiesCount > 0) ? "{$managedActivitiesCount} sân chơi phụ trách" : 'Tổng hoạt động',
        'change_type' => ($openActivities > 0) ? 'positive' : 'neutral',
        'icon' => 'trophy',
        'status' => ($openActivities > 0) ? 'Đang diễn ra' : 'Chưa có sân chơi',
    ],
    [
        'label' => 'Bài cần chấm',
        'value' => number_format($pendingAssessments),
        'change' => $managedClassName !== '' ? "Lớp {$managedClassName}" : 'Chờ đánh giá',
        'change_type' => ($pendingAssessments > 0) ? 'warning' : 'positive',
        'icon' => 'clipboard-check',
        'status' => ($pendingAssessments > 0) ? 'Cần chấm ngay' : 'Đã hoàn tất',
    ],
    [
        'label' => 'Đánh giá HV',
        'value' => ($totalStudents > 0) ? "{$assessedCount} / {$totalStudents} SV" : '0 / 0 SV',
        'change' => ($totalStudents > 0 && $assessedCount > 0) ? (round(($assessedCount / $totalStudents) * 100) . '% hoàn thành') : '0% hoàn thành',
        'change_type' => ($assessedCount > 0) ? 'positive' : 'neutral',
        'icon' => 'star',
        'status' => ($assessedCount > 0) ? 'Đã có điểm' : 'Chưa đánh giá',
    ],
];

$managedActivities = $dashboardData['managedActivities'] ?? [];
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
</head>
<body class="teacher-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require_once __DIR__ . '/includes/header.php'; ?>

            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    <?php require_once __DIR__ . '/includes/welcome.php'; ?>
                    <?php require_once __DIR__ . '/includes/kpi-cards.php'; ?>

                    <?php require_once __DIR__ . '/includes/activities-list.php'; ?>
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
