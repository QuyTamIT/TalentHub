<?php
/**
 * TalentHub - School Dashboard (Tổng quan / Overview)
 *
 * Note for Developers:
 * - This is the main entry point for the School Dashboard at /app/school.
 * - Mock data is loaded from includes/school-data.php. Replace with DB queries when API is ready.
 * - Page-specific $currentRoute is set so sidebar can highlight the active item.
 * - TODO: temporary route — replace when API endpoints are ready.
 */

// TODO for future Backend module: replace static arrays with DB fetch functions.
require_once __DIR__ . '/includes/school-data.php';

// Page-specific variables consumed by header/sidebar partials
$currentRoute   = '/app/school/index.php';
$pageTitle      = 'Tổng quan Nhà trường';
$pageSubtitle   = 'Cập nhật chỉ số năng lực theo thời gian thực';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub School Dashboard - Quản lý năng lực và hoạt động ngoại khóa cho Nhà trường.">
    <title>Tổng quan Nhà trường - <?= htmlspecialchars($schoolInfo['name']); ?> | TalentHub</title>

    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/school.css">
</head>
<body class="school-dashboard">

    <!-- Layout Wrapper -->
    <div class="sch-layout">

        <!-- Sidebar Navigation Partial -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="sch-main-wrapper">

            <!-- Top Header Partial -->
            <?php include __DIR__ . '/includes/header.php'; ?>

            <!-- Page Body Content -->
            <main class="sch-body">
                <div class="container-fluid">

                    <!-- Welcome Banner Partial -->
                    <?php include __DIR__ . '/includes/welcome.php'; ?>

                    <!-- KPI Cards Partial -->
                    <?php include __DIR__ . '/includes/kpi-cards.php'; ?>

                    <!-- Main Grid Section (2 Columns) -->
                    <div class="sch-grid-layout">

                        <!-- Left Column (Talent Distribution + Grade Ranking) -->
                        <div class="sch-grid-layout__main">
                            <?php include __DIR__ . '/includes/talent-distribution.php'; ?>
                            <?php include __DIR__ . '/includes/grade-ranking.php'; ?>
                            <?php include __DIR__ . '/includes/top-classes.php'; ?>
                        </div>

                        <!-- Right Column (Pending Actions + Activity + Info Widget) -->
                        <aside class="sch-grid-layout__sidebar">
                            <?php include __DIR__ . '/includes/pending-actions.php'; ?>
                            <?php include __DIR__ . '/includes/recent-activities.php'; ?>

                            <!-- School Summary Card -->
                            <div class="sch-section-box">
                                <div class="sch-section-box__header">
                                    <h3 class="sch-section-box__title">Hồ sơ Nhà trường</h3>
                                </div>
                                <div class="sch-info-widget">
                                    <div class="sch-info-widget__row">
                                        <span class="label">Trường:</span>
                                        <span class="val font-bold"><?= htmlspecialchars($schoolInfo['name']); ?></span>
                                    </div>
                                    <div class="sch-info-widget__row">
                                        <span class="label">Hiệu trưởng:</span>
                                        <span class="val font-bold"><?= htmlspecialchars($schoolInfo['principal']); ?></span>
                                    </div>
                                    <div class="sch-info-widget__row">
                                        <span class="label">Năm học:</span>
                                        <span class="val badge-secondary"><?= htmlspecialchars($schoolInfo['academic_year']); ?></span>
                                    </div>
                                    <div class="sch-info-widget__row">
                                        <span class="label">Sĩ số toàn trường:</span>
                                        <span class="val font-bold"><?= number_format($schoolInfo['student_total']); ?> HS</span>
                                    </div>
                                    <div class="sch-info-widget__row">
                                        <span class="label">Tổng số lớp:</span>
                                        <span class="val font-bold"><?= $schoolInfo['class_total']; ?> lớp</span>
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
    <div class="sch-toast" id="sch-toast" aria-live="polite" aria-atomic="true">
        <div class="sch-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span class="sch-toast__message">Chức năng đang được phát triển!</span>
        </div>
    </div>

    <!-- JavaScript Assets -->
    <script src="../../assets/js/school.js"></script>
</body>
</html>