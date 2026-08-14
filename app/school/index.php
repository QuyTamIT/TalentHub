<?php
/**
 * TalentHub - School Dashboard Main Entry Point
 * Dashboard cho Nhà trường
 */

$currentRoute = 'index.php';
$pageTitle = 'Tổng quan';

// Mock data
$schoolInfo = [
    'name' => 'THPT Nguyễn Trãi',
    'logo_initials' => 'NT',
    'level' => 'Trung học Phổ thông',
    'academic_year' => '2025 - 2026'
];

$kpis = [
    ['label' => 'Học sinh đang hoạt động', 'value' => '1,247', 'change' => '+12 học sinh tuần này', 'change_type' => 'positive', 'icon' => 'users'],
    ['label' => 'Hoạt động tháng này', 'value' => '18', 'change' => '8 sắp diễn ra', 'change_type' => 'neutral', 'icon' => 'calendar'],
    ['label' => 'Chứng chỉ đã cấp', 'value' => '456', 'change' => '+23 tuần này', 'change_type' => 'positive', 'icon' => 'award'],
    ['label' => 'Tỷ lệ hoàn thiện hồ sơ', 'value' => '78%', 'change' => 'Mục tiêu: 85%', 'change_type' => 'neutral', 'icon' => 'check-circle']
];

$topTalents = [
    ['name' => 'Nguyễn Văn Minh', 'class' => '12A', 'talent' => 'Toán học', 'score' => '98/100'],
    ['name' => 'Trần Thu Hà', 'class' => '11B', 'talent' => 'Âm nhạc', 'score' => '95/100'],
    ['name' => 'Lê Hoàng Nam', 'class' => '10C', 'talent' => 'Lập trình', 'score' => '92/100'],
    ['name' => 'Phạm Thị Lan', 'class' => '12D', 'talent' => 'Ngữ Văn', 'score' => '90/100']
];

$classes = [
    ['name' => '10A', 'grade' => 'Khối 10', 'students' => 42, 'homeroom' => 'Nguyễn Thị Mai', 'status' => 'success', 'status_text' => 'Hoạt động tốt'],
    ['name' => '10B', 'grade' => 'Khối 10', 'students' => 40, 'homeroom' => 'Trần Văn Hùng', 'status' => 'success', 'status_text' => 'Hoạt động tốt'],
    ['name' => '11A', 'grade' => 'Khối 11', 'students' => 38, 'homeroom' => 'Lê Thị Hương', 'status' => 'warning', 'status_text' => 'Cần cải thiện'],
    ['name' => '12A', 'grade' => 'Khối 12', 'students' => 45, 'homeroom' => 'Phạm Văn Đức', 'status' => 'success', 'status_text' => 'Xuất sắc']
];

$quickActions = [
    ['title' => 'Thêm hoạt động mới', 'subtitle' => 'Tạo sự kiện hoặc cuộc thi', 'icon' => 'plus', 'type' => 'primary'],
    ['title' => 'Duyệt hồ sơ chờ xác nhận', 'subtitle' => '5 hồ sơ đang chờ', 'icon' => 'clock', 'type' => 'warning', 'count' => 5],
    ['title' => 'Xuất báo cáo tháng', 'subtitle' => 'Tổng hợp hoạt động tháng 8', 'icon' => 'download', 'type' => 'default'],
    ['title' => 'Gửi thông báo', 'subtitle' => 'Thông báo đến phụ huynh', 'icon' => 'send', 'type' => 'default']
];

$recentActivities = [
    ['text' => 'Nguyễn Văn Minh đạt giải Nhất cuộc thi Toán cấp Quận', 'time' => '2 giờ trước'],
    ['text' => 'Lớp 12A hoàn thành 100% hồ sơ năng lực', 'time' => '4 giờ trước'],
    ['text' => 'Câu lạc bộ Âm nhạc khai giảng khóa mới với 45 thành viên', 'time' => '1 ngày trước'],
    ['text' => '25 học sinh đăng ký tham gia sân chơi lập trình tháng 9', 'time' => '1 ngày trước'],
    ['text' => 'Trường được vinh danh "Top 10 trường có hoạt động năng khiếu xuất sắc"', 'time' => '2 ngày trước']
];

function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_substr($word, 0, 1);
    }
    return $initials;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub School Dashboard - Quản lý hoạt động năng khiếu cho Nhà trường.">
    <title>Dashboard Nhà Trường - THPT Nguyễn Trãi | TalentHub</title>
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
                    <!-- Welcome Banner -->
                    <section class="school-welcome">
                        <div class="school-welcome__body">
                            <div class="school-welcome__content">
                                <span class="school-welcome__tag">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                        <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                                    </svg>
                                    Khu vực Nhà trường
                                </span>
                                <h2 class="school-welcome__title">Xin chào, Ban Giám hiệu <?= htmlspecialchars($schoolInfo['name']); ?>!</h2>
                                <p class="school-welcome__description">
                                    Theo dõi tổng quan hoạt động năng khiếu của trường, quản lý hồ sơ học sinh và xem báo cáo chi tiết về tiềm năng phát triển tài năng trong năm học <?= htmlspecialchars($schoolInfo['academic_year']); ?>.
                                </p>
                                <div class="school-welcome__actions">
                                    <a href="analytics.php" class="btn btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                            <polyline points="17 6 23 6 23 12"></polyline>
                                        </svg>
                                        Xem phân tích
                                    </a>
                                    <a href="reports.php" class="btn btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                        </svg>
                                        Tạo báo cáo
                                    </a>
                                </div>
                            </div>
                            <div class="school-welcome__graphic">
                                <svg width="180" height="140" viewBox="0 0 180 140" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="20" y="60" width="140" height="70" rx="4" fill="#EFF6FF" stroke="#3B82F6" stroke-width="2"/>
                                    <rect x="35" y="75" width="25" height="20" rx="2" fill="#BFDBFE" stroke="#3B82F6" stroke-width="1.5"/>
                                    <rect x="70" y="75" width="25" height="20" rx="2" fill="#BFDBFE" stroke="#3B82F6" stroke-width="1.5"/>
                                    <rect x="105" y="75" width="25" height="20" rx="2" fill="#BFDBFE" stroke="#3B82F6" stroke-width="1.5"/>
                                    <rect x="77" y="105" width="26" height="25" rx="2" fill="#3B82F6"/>
                                    <path d="M10 62 L90 20 L170 62" stroke="#3B82F6" stroke-width="2" fill="#DBEAFE"/>
                                    <line x1="90" y1="20" x2="90" y2="5" stroke="#3B82F6" stroke-width="2"/>
                                    <circle cx="90" cy="5" r="3" fill="#3B82F6"/>
                                </svg>
                            </div>
                        </div>
                    </section>

                    <!-- KPI Cards -->
                    <section class="school-kpis-grid">
                        <?php foreach ($kpis as $kpi): ?>
                            <article class="school-kpi-card">
                                <div class="school-kpi-card__header">
                                    <span class="school-kpi-card__label"><?= htmlspecialchars($kpi['label']); ?></span>
                                    <span class="school-kpi-card__icon">
                                        <?php if ($kpi['icon'] === 'users'): ?>
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                            </svg>
                                        <?php elseif ($kpi['icon'] === 'calendar'): ?>
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                            </svg>
                                        <?php elseif ($kpi['icon'] === 'award'): ?>
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="8" r="7"></circle>
                                                <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                            </svg>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="school-kpi-card__value"><?= htmlspecialchars($kpi['value']); ?></div>
                                <div class="school-kpi-card__footer">
                                    <span class="school-kpi-card__change school-kpi-card__change--<?= htmlspecialchars($kpi['change_type']); ?>">
                                        <?= htmlspecialchars($kpi['change']); ?>
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <!-- Main Grid Layout -->
                    <div class="school-grid-layout">
                        <div class="school-grid-layout__main">
                            <!-- Top Talents -->
                            <section class="school-section-box">
                                <div class="school-section-box__header">
                                    <div>
                                        <h3 class="school-section-box__title">Top tài năng nổi bật</h3>
                                        <p class="school-section-box__subtitle">Học sinh có thành tích xuất sắc</p>
                                    </div>
                                    <a href="analytics.php" class="school-section-box__link">Xem tất cả</a>
                                </div>
                                <div class="school-talents-list">
                                    <?php foreach ($topTalents as $talent): ?>
                                        <div class="school-talent-card">
                                            <div class="school-talent-card__left">
                                                <div class="school-talent-card__avatar"><?= htmlspecialchars(getInitials($talent['name'])); ?></div>
                                                <div class="school-talent-card__details">
                                                    <div class="school-talent-card__name-row">
                                                        <span class="school-talent-card__name"><?= htmlspecialchars($talent['name']); ?></span>
                                                        <span class="school-talent-card__score-badge"><?= htmlspecialchars($talent['score']); ?></span>
                                                    </div>
                                                    <div class="school-talent-card__meta-line">
                                                        <span>Lớp <?= htmlspecialchars($talent['class']); ?></span>
                                                        <span style="color: var(--text-muted);">•</span>
                                                        <span><?= htmlspecialchars($talent['talent']); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="school-talent-card__actions">
                                                <button class="btn btn-sm btn-outline">Xem hồ sơ</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            
                            <!-- Class List -->
                            <section class="school-section-box">
                                <div class="school-section-box__header">
                                    <div>
                                        <h3 class="school-section-box__title">Danh sách lớp</h3>
                                        <p class="school-section-box__subtitle">12 lớp trong trường</p>
                                    </div>
                                    <a href="classes.php" class="school-section-box__link">Quản lý lớp</a>
                                </div>
                                <table class="school-class-table">
                                    <thead>
                                        <tr>
                                            <th>Lớp</th>
                                            <th>Khối</th>
                                            <th>Sĩ số</th>
                                            <th>GV Chủ nhiệm</th>
                                            <th>Trạng thái</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classes as $class): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($class['name']); ?></strong></td>
                                                <td><?= htmlspecialchars($class['grade']); ?></td>
                                                <td><?= htmlspecialchars($class['students']); ?> HS</td>
                                                <td><?= htmlspecialchars($class['homeroom']); ?></td>
                                                <td>
                                                    <span class="school-class-badge school-class-badge--<?= $class['status']; ?>">
                                                        <?= htmlspecialchars($class['status_text']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </section>
                        </div>

                        <div class="school-grid-layout__sidebar">
                            <!-- Quick Actions -->
                            <section class="school-section-box">
                                <div class="school-section-box__header">
                                    <h3 class="school-section-box__title">Hành động nhanh</h3>
                                </div>
                                <div class="school-actions-list">
                                    <?php foreach ($quickActions as $action): ?>
                                        <div class="school-action-row <?= $action['type'] === 'warning' ? 'school-action-row--warning' : ''; ?>">
                                            <div class="school-action-row__icon">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <?php if ($action['icon'] === 'plus'): ?>
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="12" y1="8" x2="12" y2="16"></line>
                                                        <line x1="8" y1="12" x2="16" y2="12"></line>
                                                    <?php elseif ($action['icon'] === 'clock'): ?>
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <polyline points="12 6 12 12 16 14"></polyline>
                                                    <?php elseif ($action['icon'] === 'download'): ?>
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                        <polyline points="7 10 12 15 17 10"></polyline>
                                                    <?php else: ?>
                                                        <line x1="22" y1="2" x2="11" y2="13"></line>
                                                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                                    <?php endif; ?>
                                                </svg>
                                            </div>
                                            <div class="school-action-row__body">
                                                <div class="school-action-row__title"><?= htmlspecialchars($action['title']); ?></div>
                                                <div class="school-action-row__subtitle"><?= htmlspecialchars($action['subtitle']); ?></div>
                                            </div>
                                            <div class="school-action-row__btn">
                                                <button class="btn btn-sm <?= $action['type'] === 'primary' ? 'btn-primary' : ($action['type'] === 'warning' ? 'btn-warning' : 'btn-outline'); ?>">
                                                    <?= $action['type'] === 'primary' ? 'Tạo mới' : 'Thực hiện'; ?>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            
                            <!-- Recent Activity -->
                            <section class="school-section-box">
                                <div class="school-section-box__header">
                                    <div>
                                        <h3 class="school-section-box__title">Hoạt động gần đây</h3>
                                        <p class="school-section-box__subtitle">Cập nhật mới nhất từ trường</p>
                                    </div>
                                </div>
                                <div class="school-activity-timeline">
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="school-activity-item">
                                            <span class="school-activity-item__indicator"></span>
                                            <span class="school-activity-item__text"><?= htmlspecialchars($activity['text']); ?></span>
                                            <span class="school-activity-item__time"><?= htmlspecialchars($activity['time']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Toast Notification -->
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
