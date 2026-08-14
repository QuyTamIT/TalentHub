<?php
/**
 * TalentHub - School Dashboard Classes Page
 * Quản lý Lớp & Khối cho Nhà trường
 */

$currentRoute = 'classes.php';
$pageTitle = 'Lớp & Khối';

$classes = [
    ['name' => '10A', 'grade' => 'Khối 10', 'students' => 42, 'homeroom' => 'Nguyễn Thị Mai', 'status' => 'success', 'status_text' => 'Hoạt động tốt', 'completion' => 82],
    ['name' => '10B', 'grade' => 'Khối 10', 'students' => 40, 'homeroom' => 'Trần Văn Hùng', 'status' => 'success', 'status_text' => 'Hoạt động tốt', 'completion' => 78],
    ['name' => '10C', 'grade' => 'Khối 10', 'students' => 38, 'homeroom' => 'Lê Thị Hương', 'status' => 'warning', 'status_text' => 'Cần cải thiện', 'completion' => 65],
    ['name' => '11A', 'grade' => 'Khối 11', 'students' => 40, 'homeroom' => 'Phạm Văn Đức', 'status' => 'success', 'status_text' => 'Hoạt động tốt', 'completion' => 85],
    ['name' => '11B', 'grade' => 'Khối 11', 'students' => 42, 'homeroom' => 'Hoàng Thị Lan', 'status' => 'warning', 'status_text' => 'Cần cải thiện', 'completion' => 72],
    ['name' => '12A', 'grade' => 'Khối 12', 'students' => 45, 'homeroom' => 'Vũ Thị Hà', 'status' => 'success', 'status_text' => 'Xuất sắc', 'completion' => 92],
    ['name' => '12B', 'grade' => 'Khối 12', 'students' => 43, 'homeroom' => 'Đặng Văn Minh', 'status' => 'success', 'status_text' => 'Hoạt động tốt', 'completion' => 88],
    ['name' => '12C', 'grade' => 'Khối 12', 'students' => 41, 'homeroom' => 'Bùi Thị Mai', 'status' => 'success', 'status_text' => 'Hoạt động tốt', 'completion' => 80]
];

$grades = ['Khối 10' => [], 'Khối 11' => [], 'Khối 12' => []];
foreach ($classes as $class) {
    $grades[$class['grade']][] = $class;
}

$gradeStats = [
    ['name' => 'Khối 10', 'classes' => 3, 'students' => 120, 'avgCompletion' => 75],
    ['name' => 'Khối 11', 'classes' => 2, 'students' => 82, 'avgCompletion' => 78.5],
    ['name' => 'Khối 12', 'classes' => 3, 'students' => 129, 'avgCompletion' => 86.7]
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Quản lý lớp và khối - TalentHub School Dashboard">
    <title>Lớp & Khối - THPT Nguyễn Trãi | TalentHub</title>
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
                                    Quản lý Lớp & Khối
                                </h2>
                                <p style="font-size: 0.875rem; color: var(--text-secondary);">
                                    12 lớp học • 1,247 học sinh
                                </p>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn btn-outline btn-sm" onclick="showSchoolToast('Đang xuất danh sách...')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                        <polyline points="7 10 12 15 17 10"></polyline>
                                    </svg>
                                    Xuất danh sách
                                </button>
                                <button class="btn btn-primary btn-sm" onclick="showSchoolToast('Đang mở form thêm lớp...')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    Thêm lớp mới
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Grade Overview Cards -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 1.75rem;">
                        <?php foreach ($gradeStats as $stat): ?>
                            <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem;">
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                                    <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                                        <?= htmlspecialchars($stat['name']) ?>
                                    </h3>
                                    <span style="font-size: 0.75rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 4px; background: #EFF6FF; color: #2563EB;">
                                        <?= $stat['classes'] ?> lớp
                                    </span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                    <div>
                                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"><?= $stat['students'] ?></div>
                                        <div style="font-size: 0.8125rem; color: var(--text-muted);">học sinh</div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 1.125rem; font-weight: 700; color: #2563EB;"><?= $stat['avgCompletion'] ?>%</div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">hoàn thiện TB</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Classes by Grade -->
                    <?php foreach ($grades as $gradeName => $gradeClasses): ?>
                        <div style="margin-bottom: 2rem;">
                            <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2">
                                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                                </svg>
                                <?= htmlspecialchars($gradeName) ?>
                                <span style="font-size: 0.8125rem; font-weight: 500; color: var(--text-muted);">(<?= count($gradeClasses) ?> lớp)</span>
                            </h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
                                <?php foreach ($gradeClasses as $class): ?>
                                    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1.25rem; transition: all 0.2s;">
                                        <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1rem;">
                                            <div>
                                                <h4 style="font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin: 0 0 0.25rem 0;">
                                                    Lớp <?= htmlspecialchars($class['name']) ?>
                                                </h4>
                                                <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0;">
                                                    GVCM: <?= htmlspecialchars($class['homeroom']) ?>
                                                </p>
                                            </div>
                                            <span class="school-class-badge school-class-badge--<?= $class['status']; ?>">
                                                <?= htmlspecialchars($class['status_text']) ?>
                                            </span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                                            <div>
                                                <span style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary);"><?= $class['students'] ?></span>
                                                <span style="font-size: 0.8125rem; color: var(--text-muted); margin-left: 0.25rem;">học sinh</span>
                                            </div>
                                            <div style="text-align: right;">
                                                <span style="font-size: 1rem; font-weight: 700; color: #2563EB;"><?= $class['completion'] ?>%</span>
                                                <span style="font-size: 0.75rem; color: var(--text-muted);"> hồ sơ</span>
                                            </div>
                                        </div>
                                        <div style="height: 6px; background: var(--background); border-radius: 3px; overflow: hidden; margin-bottom: 1rem;">
                                            <div style="height: 100%; width: <?= $class['completion'] ?>%; background: <?= $class['completion'] >= 80 ? '#22C55E' : ($class['completion'] >= 70 ? '#F59E0B' : '#EF4444'); ?>; border-radius: 3px;"></div>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <button class="btn btn-sm btn-outline" style="flex: 1;" onclick="showSchoolToast('Đang xem chi tiết lớp <?= $class['name'] ?>...')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                                Chi tiết
                                            </button>
                                            <button class="btn btn-sm btn-outline" onclick="showSchoolToast('Đang chỉnh sửa...')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
        [style*="grid-template-columns: repeat(3, 1fr)"] {
            grid-template-columns: 1fr !important;
        }
        [style*="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr))"] {
            grid-template-columns: 1fr !important;
        }
    }
    </style>
</body>
</html>
