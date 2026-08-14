<?php
/**
 * TalentHub - School Dashboard Analytics Page
 * Phân tích dữ liệu chi tiết cho Nhà trường
 */

$currentRoute = 'analytics.php';
$pageTitle = 'Phân tích dữ liệu';

$monthlyStats = [
    ['month' => 'T9', 'students' => 45, 'activities' => 8],
    ['month' => 'T10', 'students' => 52, 'activities' => 12],
    ['month' => 'T11', 'students' => 48, 'activities' => 10],
    ['month' => 'T12', 'students' => 61, 'activities' => 15],
    ['month' => 'T1', 'students' => 55, 'activities' => 9],
    ['month' => 'T2', 'students' => 67, 'activities' => 14],
    ['month' => 'T3', 'students' => 72, 'activities' => 18],
    ['month' => 'T4', 'students' => 68, 'activities' => 16],
    ['month' => 'T5', 'students' => 75, 'activities' => 20],
    ['month' => 'T6', 'students' => 70, 'activities' => 17],
    ['month' => 'T7', 'students' => 58, 'activities' => 11],
    ['month' => 'T8', 'students' => 62, 'activities' => 13]
];

$talentDistribution = [
    ['name' => 'Khoa học', 'count' => 320, 'percentage' => 25.7],
    ['name' => 'Nghệ thuật', 'count' => 280, 'percentage' => 22.5],
    ['name' => 'Thể thao', 'count' => 250, 'percentage' => 20.0],
    ['name' => 'Công nghệ', 'count' => 220, 'percentage' => 17.7],
    ['name' => 'Ngôn ngữ', 'count' => 177, 'percentage' => 14.2]
];

$gradeStats = [
    ['grade' => 'Khối 10', 'students' => 420, 'completion' => 82],
    ['grade' => 'Khối 11', 'students' => 415, 'completion' => 75],
    ['grade' => 'Khối 12', 'students' => 412, 'completion' => 78]
];

$maxStudents = max(array_column($monthlyStats, 'students'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Phân tích dữ liệu - TalentHub School Dashboard">
    <title>Phân tích dữ liệu - THPT Nguyễn Trãi | TalentHub</title>
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
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
                                    Thống kê theo năm học 2025 - 2026
                                </h2>
                                <p style="font-size: 0.875rem; color: var(--text-secondary);">
                                    Dữ liệu cập nhật đến tháng 8/2026
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn btn-outline btn-sm">Xuất Excel</button>
                                <button class="btn btn-outline btn-sm">In báo cáo</button>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Chart -->
                    <div class="school-chart-container">
                        <div class="school-chart-header">
                            <h3 class="school-chart-title">Số lượng học sinh & Hoạt động theo tháng</h3>
                            <div class="school-chart-filters">
                                <button class="btn btn-sm btn-primary">12 tháng</button>
                                <button class="btn btn-sm btn-outline">6 tháng</button>
                                <button class="btn btn-sm btn-outline">3 tháng</button>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 0.5rem; margin-bottom: 1.5rem;">
                            <?php foreach ($monthlyStats as $stat): ?>
                                <div style="background: var(--background); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.75rem; text-align: center;">
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem;"><?= $stat['month'] ?></div>
                                    <div style="font-size: 1.125rem; font-weight: 700; color: #2563EB;"><?= $stat['students'] ?></div>
                                    <div style="font-size: 0.625rem; color: var(--text-muted);">HS mới</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="display: flex; align-items: flex-end; gap: 0.5rem; height: 180px; padding: 1rem 0;">
                            <?php foreach ($monthlyStats as $stat): 
                                $height = ($stat['students'] / $maxStudents) * 140;
                            ?>
                                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.25rem;">
                                    <div style="width: 100%; background: linear-gradient(180deg, #3B82F6 0%, #93C5FD 100%); border-radius: 4px 4px 0 0; height: <?= $height ?>px; min-height: 16px;" title="<?= $stat['students'] ?> học sinh"></div>
                                    <span style="font-size: 0.6875rem; color: var(--text-muted);"><?= $stat['month'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Two Column Grid -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.75rem; margin-bottom: 1.75rem;">
                        <!-- Talent Distribution -->
                        <div class="school-section-box">
                            <div class="school-section-box__header">
                                <div>
                                    <h3 class="school-section-box__title">Phân bố năng khiếu</h3>
                                    <p class="school-section-box__subtitle">Theo lĩnh vực</p>
                                </div>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($talentDistribution as $talent): ?>
                                    <div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.375rem;">
                                            <span style="font-size: 0.875rem; font-weight: 500;"><?= htmlspecialchars($talent['name']) ?></span>
                                            <span style="font-size: 0.875rem; color: var(--text-muted);"><?= $talent['count'] ?> HS (<?= $talent['percentage'] ?>%)</span>
                                        </div>
                                        <div style="height: 8px; background: var(--background); border-radius: 4px; overflow: hidden;">
                                            <div style="height: 100%; width: <?= $talent['percentage'] ?>%; background: linear-gradient(90deg, #3B82F6 0%, #60A5FA 100%); border-radius: 4px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Grade Overview -->
                        <div class="school-section-box">
                            <div class="school-section-box__header">
                                <div>
                                    <h3 class="school-section-box__title">Tổng quan theo khối</h3>
                                    <p class="school-section-box__subtitle">Số lượng & tỷ lệ hoàn thiện</p>
                                </div>
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                                <?php foreach ($gradeStats as $grade): ?>
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <div style="width: 80px; font-size: 0.875rem; font-weight: 600;"><?= htmlspecialchars($grade['grade']) ?></div>
                                        <div style="flex: 1;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                                <span style="font-size: 0.8125rem; color: var(--text-secondary);"><?= $grade['students'] ?> học sinh</span>
                                                <span style="font-size: 0.8125rem; font-weight: 600; color: #2563EB;"><?= $grade['completion'] ?>%</span>
                                            </div>
                                            <div style="height: 6px; background: var(--background); border-radius: 3px; overflow: hidden;">
                                                <div style="height: 100%; width: <?= $grade['completion'] ?>%; background: #2563EB; border-radius: 3px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Metrics -->
                    <div class="school-section-box">
                        <div class="school-section-box__header">
                            <div>
                                <h3 class="school-section-box__title">Chỉ số hiệu suất</h3>
                                <p class="school-section-box__subtitle">Đánh giá toàn diện hoạt động trường</p>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem;">
                            <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: var(--radius-sm); padding: 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div style="width: 2.5rem; height: 2.5rem; background: #22C55E; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                            <polyline points="17 6 23 6 23 12"></polyline>
                                        </svg>
                                    </div>
                                    <span style="font-weight: 600; color: #166534;">Tăng trưởng tích cực</span>
                                </div>
                                <p style="font-size: 0.875rem; color: #166534; margin: 0;">Số lượng học sinh có hồ sơ năng lực tăng 18% so với năm trước.</p>
                            </div>
                            <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-sm); padding: 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div style="width: 2.5rem; height: 2.5rem; background: #3B82F6; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                            <circle cx="12" cy="8" r="7"></circle>
                                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                        </svg>
                                    </div>
                                    <span style="font-weight: 600; color: #1E40AF;">Giải thưởng</span>
                                </div>
                                <p style="font-size: 0.875rem; color: #1E40AF; margin: 0;">Trường đạt 24 giải thưởng cấp Quận, 8 giải cấp Thành phố.</p>
                            </div>
                            <div style="background: #FEF3C7; border: 1px solid #FDE68A; border-radius: var(--radius-sm); padding: 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div style="width: 2.5rem; height: 2.5rem; background: #F59E0B; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polyline points="12 6 12 12 16 14"></polyline>
                                        </svg>
                                    </div>
                                    <span style="font-weight: 600; color: #92400E;">Cần cải thiện</span>
                                </div>
                                <p style="font-size: 0.875rem; color: #92400E; margin: 0;">Tỷ lệ hoàn thiện hồ sơ Khối 11 còn 75%, dưới mục tiêu 85%.</p>
                            </div>
                        </div>
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

    <style>
    @media (max-width: 768px) {
        [style*="grid-template-columns: repeat(2, 1fr)"] {
            grid-template-columns: 1fr !important;
        }
    }
    </style>
</body>
</html>
