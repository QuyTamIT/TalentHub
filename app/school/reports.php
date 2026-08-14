<?php
/**
 * TalentHub - School Dashboard Reports Page
 * Báo cáo và xuất dữ liệu cho Nhà trường.
 *
 * No `reports` table exists yet, so the listing is built from real
 * school metrics. The UI stays close to the original layout.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;

$context = (new SchoolAppContext())->boot();
$school  = $context['school'];
$dashboard = $context['dashboard'];
$metrics = $dashboard['metrics'];

$today = date('d/m/Y');
$reports = [
    ['title' => sprintf('Báo cáo tổng kết tháng %s', date('n/Y')), 'type' => 'Báo cáo tháng', 'created' => $today, 'status' => 'ready', 'status_text' => 'Sẵn sàng'],
    ['title' => 'Danh sách học sinh có hồ sơ năng lực', 'type' => 'Danh sách', 'created' => date('d/m/Y', strtotime('-3 days')), 'status' => 'ready', 'status_text' => 'Sẵn sàng'],
    ['title' => sprintf('Báo cáo %d lớp đang hoạt động', (int) $metrics['totalClasses']), 'type' => 'Báo cáo lớp', 'created' => date('d/m/Y', strtotime('-7 days')), 'status' => 'ready', 'status_text' => 'Sẵn sàng'],
    ['title' => sprintf('Thống kê %d giáo viên', (int) $metrics['totalTeachers']), 'type' => 'Thống kê', 'created' => date('d/m/Y', strtotime('-14 days')), 'status' => 'ready', 'status_text' => 'Sẵn sàng'],
    ['title' => 'Báo cáo tiến độ hồ sơ theo lớp', 'type' => 'Báo cáo lớp', 'created' => 'Đang xử lý...', 'status' => 'processing', 'status_text' => 'Đang xử lý'],
];

$schoolInfo = [
    'name'          => $school['name'],
    'logo_initials' => mb_substr($school['name'], 0, 2),
    'level'         => $school['level'] ?? 'Trung học',
    'district'      => $school['address'] ?? '',
    'academic_year' => $school['academicYear'] ?? '',
];

$currentRoute = '/app/school/reports.php';
$pageTitle = 'Báo cáo';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Quản lý báo cáo - TalentHub School Dashboard">
    <title>Báo cáo - <?= htmlspecialchars($schoolInfo['name']); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/school.css">
</head>
<body class="school-dashboard">
    <div class="school-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="school-main-wrapper">
            <?php include __DIR__ . '/includes/header.php'; ?>
            
            <main class="school-body">
                <div class="container-fluid">
                    <!-- Page Header -->
                    <div class="school-section-box" style="margin-bottom: 1.75rem;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
                                    Quản lý Báo cáo
                                </h2>
                                <p style="font-size: 0.875rem; color: var(--text-secondary);">
                                    Tạo, xuất và theo dõi các báo cáo của trường
                                </p>
                            </div>
                            <button class="btn btn-primary" onclick="showSchoolToast('Đang mở trình tạo báo cáo...')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Tạo báo cáo mới
                            </button>
                        </div>
                    </div>

                    <!-- Quick Report Templates -->
                    <div style="margin-bottom: 1.75rem;">
                        <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 1rem;">Mẫu báo cáo nhanh</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem; cursor: pointer; transition: all 0.2s;" onclick="showSchoolToast('Đang tạo báo cáo...')">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div style="width: 2.5rem; height: 2.5rem; background: #EFF6FF; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                        </svg>
                                    </div>
                                    <span style="font-weight: 600; font-size: 0.875rem;">Báo cáo tháng</span>
                                </div>
                                <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0;">Tổng hợp hoạt động theo tháng</p>
                            </div>
                            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem; cursor: pointer; transition: all 0.2s;" onclick="showSchoolToast('Đang tạo báo cáo...')">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div style="width: 2.5rem; height: 2.5rem; background: #F0FDF4; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                    <span style="font-weight: 600; font-size: 0.875rem;">Danh sách học sinh</span>
                                </div>
                                <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0;">Toàn bộ hồ sơ năng lực</p>
                            </div>
                            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem; cursor: pointer; transition: all 0.2s;" onclick="showSchoolToast('Đang tạo báo cáo...')">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div style="width: 2.5rem; height: 2.5rem; background: #FEF3C7; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2">
                                            <circle cx="12" cy="8" r="7"></circle>
                                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                        </svg>
                                    </div>
                                    <span style="font-weight: 600; font-size: 0.875rem;">Giải thưởng</span>
                                </div>
                                <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0;">Danh sách & thống kê giải</p>
                            </div>
                            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem; cursor: pointer; transition: all 0.2s;" onclick="showSchoolToast('Đang tạo báo cáo...')">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div style="width: 2.5rem; height: 2.5rem; background: #F3E8FF; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9333EA" stroke-width="2">
                                            <rect x="3" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="3" width="7" height="7"></rect>
                                        </svg>
                                    </div>
                                    <span style="font-weight: 600; font-size: 0.875rem;">Báo cáo lớp</span>
                                </div>
                                <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0;">Theo từng lớp học</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Reports List -->
                    <div class="school-section-box">
                        <div class="school-section-box__header">
                            <div>
                                <h3 class="school-section-box__title">Báo cáo gần đây</h3>
                                <p class="school-section-box__subtitle">5 báo cáo mới nhất</p>
                            </div>
                            <a href="#" class="school-section-box__link">Xem tất cả</a>
                        </div>
                        <table class="school-class-table">
                            <thead>
                                <tr>
                                    <th>Tên báo cáo</th>
                                    <th>Loại</th>
                                    <th>Ngày tạo</th>
                                    <th>Trạng thái</th>
                                    <th style="text-align: right;">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td><strong style="color: var(--text-primary);"><?= htmlspecialchars($report['title']) ?></strong></td>
                                        <td><span style="font-size: 0.8125rem; color: var(--text-secondary);"><?= htmlspecialchars($report['type']) ?></span></td>
                                        <td><span style="font-size: 0.8125rem; color: var(--text-muted);"><?= htmlspecialchars($report['created']) ?></span></td>
                                        <td>
                                            <?php if ($report['status'] === 'ready'): ?>
                                                <span style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 9999px; background: #F0FDF4; color: #16A34A;">
                                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #16A34A;"></span>
                                                    <?= htmlspecialchars($report['status_text']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 9999px; background: #FEF3C7; color: #D97706;">
                                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #D97706;"></span>
                                                    <?= htmlspecialchars($report['status_text']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                                <?php if ($report['status'] === 'ready'): ?>
                                                    <button class="btn btn-sm btn-outline" onclick="showSchoolToast('Đang tải báo cáo...')">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                            <polyline points="7 10 12 15 17 10"></polyline>
                                                        </svg>
                                                        Tải xuống
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-outline" disabled>
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="12" cy="12" r="10"></circle>
                                                            <polyline points="12 6 12 12 16 14"></polyline>
                                                        </svg>
                                                        Chờ
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="school-toast" id="school-toast">
        <div class="school-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <span id="toast-message">Thao tác thành công!</span>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('school-sidebar-toggle');
        const sidebar = document.getElementById('school-sidebar');
        const backdrop = document.getElementById('school-sidebar-backdrop');

        if (sidebarToggle && sidebar && backdrop) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('is-open');
                backdrop.classList.toggle('is-active');
                document.body.classList.toggle('school-sidebar-open');
            });

            backdrop.addEventListener('click', function() {
                sidebar.classList.remove('is-open');
                backdrop.classList.remove('is-active');
                document.body.classList.remove('school-sidebar-open');
            });
        }

        window.showSchoolToast = function(message) {
            const toast = document.getElementById('school-toast');
            const toastMessage = document.getElementById('toast-message');
            if (toast && toastMessage) {
                toastMessage.textContent = message;
                toast.classList.add('is-visible');
                setTimeout(function() {
                    toast.classList.remove('is-visible');
                }, 3000);
            }
        };
    });
    </script>
</body>
</html>
