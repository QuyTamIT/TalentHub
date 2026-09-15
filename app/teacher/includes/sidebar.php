<?php
/**
 * Teacher Dashboard - Sidebar Component
 *
 * Strictly aligned with the Teacher Portal slide specifications:
 * 1. TalentHub logo at the top with role tag
 * 2. Dedicated Teacher menu group with exactly 4 canonical items:
 *    - Tổng quan (/app/teacher/index.php)
 *    - Sân chơi của tôi (/app/teacher/activities/index.php)
 *    - Chấm điểm (/app/teacher/grading.php)
 *    - Học viên (/app/teacher/students/index.php)
 * 3. Clear and reliable active page highlight
 * 4. Teacher information block at the bottom:
 *    - Teacher Full Name
 *    - Teacher Role
 *    - Managed Classes count (Số lớp phụ trách: X lớp)
 *    - Total Students count (Số học viên: X)
 * 5. Logout action at the very bottom
 */

if (!function_exists('app_href') && is_file(dirname(__DIR__, 3) . '/bin/bootstrap.php')) {
    require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';
}

// 1. Ensure teacher info and metrics are loaded if not already passed by the caller
if (!isset($teacherInfo) || empty($teacherInfo['full_name']) || !isset($metrics) || !isset($metrics['total_students'])) {
    if (!function_exists('teacherDashboardReadData') && is_file(__DIR__ . '/dashboard-data.php')) {
        require_once __DIR__ . '/dashboard-data.php';
    }
    if (function_exists('teacherDashboardReadData')) {
        $dashData = teacherDashboardReadData();
        if (!isset($teacherInfo) || empty($teacherInfo['full_name'])) {
            $teacherInfo = $dashData['teacherInfo'] ?? [];
        }
        if (!isset($metrics) || !isset($metrics['total_students'])) {
            $metrics = $dashData['metrics'] ?? [];
        }
    }
}

// 2. Resolve Teacher profile details
$rawSessionName = (string) ($_SESSION['user']['fullName'] ?? ($_SESSION['user']['full_name'] ?? ($_SESSION['user_name'] ?? '')));
$teacherFullName = !empty($teacherInfo['full_name'])
    ? (string) $teacherInfo['full_name']
    : ($rawSessionName !== '' ? $rawSessionName : 'Trần Minh Tuấn');

$teacherRoleLabel = !empty($teacherInfo['role_label'])
    ? (string) $teacherInfo['role_label']
    : 'Giáo viên / Hướng dẫn viên';

$cleanName = preg_replace('/^(Thầy|Cô|Gv\.|GV|Ths\.|TS\.|ThS\.)\s+/iu', '', $teacherFullName);
$cleanName = trim((string) $cleanName) ?: $teacherFullName;
$nameParts = preg_split('/\s+/u', trim($cleanName)) ?: [];
if (count($nameParts) === 1) {
    $teacherAvatarInitials = mb_strtoupper(mb_substr($nameParts[0], 0, min(2, mb_strlen($nameParts[0]))));
} else {
    $teacherAvatarInitials = $nameParts === [] ? 'GV' : mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
}

// 3. Resolve Class count and Student count
$sidebarClassCount = (int) ($metrics['managed_classes'] ?? (!empty($teacherInfo['managed_class_name']) ? 1 : 1));
$sidebarStudentCount = (int) ($metrics['total_students'] ?? 1);

// 4. Resolve Active Route deterministically
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');
$currentPath = (string) (parse_url($reqUri, PHP_URL_PATH) ?: $scriptName);

$activeKey = 'overview';
if (str_contains($currentPath, '/activities') || str_contains($scriptName, '/activities')) {
    $activeKey = 'activities';
} elseif (str_contains($currentPath, '/grading.php') || str_contains($scriptName, 'grading.php') || str_contains($currentPath, '/assessments') || str_contains($scriptName, 'assessments')) {
    $activeKey = 'grading';
} elseif (str_contains($currentPath, '/students') || str_contains($scriptName, '/students')) {
    $activeKey = 'students';
} else {
    $activeKey = 'overview';
}

// 5. Exactly 4 menu items according to slide
$teacherNav = [
    'overview' => [
        'title' => 'Tổng quan',
        'href'  => function_exists('app_href') ? app_href('/app/teacher/index.php') : '/app/teacher/index.php',
        'icon'  => 'grid',
    ],
    'activities' => [
        'title' => 'Sân chơi của tôi',
        'href'  => function_exists('app_href') ? app_href('/app/teacher/activities/index.php') : '/app/teacher/activities/index.php',
        'icon'  => 'trophy',
    ],
    'grading' => [
        'title' => 'Chấm điểm',
        'href'  => function_exists('app_href') ? app_href('/app/teacher/grading.php') : '/app/teacher/grading.php',
        'icon'  => 'clipboard-check',
    ],
    'students' => [
        'title' => 'Học viên',
        'href'  => function_exists('app_href') ? app_href('/app/teacher/students/index.php') : '/app/teacher/students/index.php',
        'icon'  => 'users',
    ],
];
?>
<div class="teacher-sidebar-backdrop" id="teacher-sidebar-backdrop" aria-hidden="true"></div>

<aside class="teacher-sidebar" id="teacher-sidebar">
    <!-- Brand Logo -->
    <div class="teacher-sidebar__brand">
        <a href="<?= htmlspecialchars(function_exists('app_href') ? app_href('/app/teacher/index.php') : '/app/teacher/index.php'); ?>"
           class="teacher-sidebar__brand-link"
           aria-label="Về trang chủ FTalentHub Giáo viên">
            <img src="<?= htmlspecialchars(function_exists('app_href') ? app_href('/assets/images/talenthub-logo.png') : '/assets/images/talenthub-logo.png'); ?>"
                 alt="FTalentHub Logo"
                 class="teacher-sidebar__logo-img" />
        </a>
        <span class="teacher-sidebar__role-tag">Khu vực Giáo viên</span>
    </div>

    <!-- Navigation Menu: Exactly 4 items from Slide -->
    <nav class="teacher-sidebar__nav" aria-label="Điều hướng Giáo viên">
        <div class="teacher-sidebar__nav-title">QUẢN LÝ GIÁO VIÊN</div>
        <ul>
            <?php foreach ($teacherNav as $key => $item):
                $isActive = ($key === $activeKey);
            ?>
                <li>
                    <a href="<?= htmlspecialchars($item['href']); ?>"
                       class="teacher-sidebar__link <?= $isActive ? 'is-active' : ''; ?>"
                       data-route="<?= htmlspecialchars($key); ?>"
                       <?= $isActive ? 'aria-current="page"' : ''; ?>>
                        <span class="teacher-sidebar__icon">
                            <?php if ($item['icon'] === 'grid'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                                    <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                                    <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                                    <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                                </svg>
                            <?php elseif ($item['icon'] === 'trophy'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                                    <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                                    <path d="M4 22h16"></path>
                                    <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path>
                                </svg>
                            <?php elseif ($item['icon'] === 'clipboard-check'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"></path>
                                    <rect x="9" y="3" width="6" height="4" rx="1.5"></rect>
                                    <polyline points="9 14 11 16 15 12"></polyline>
                                </svg>
                            <?php elseif ($item['icon'] === 'users'): ?>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            <?php endif; ?>
                        </span>
                        <span class="teacher-sidebar__text"><?= htmlspecialchars($item['title']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Teacher Info & Stats Block at the Bottom -->
    <div class="teacher-sidebar__teacher-card">
        <div class="teacher-sidebar__teacher-header">
            <div class="teacher-sidebar__teacher-avatar" aria-hidden="true">
                <?= htmlspecialchars($teacherAvatarInitials); ?>
            </div>
            <div class="teacher-sidebar__teacher-details">
                <div class="teacher-sidebar__teacher-name" title="<?= htmlspecialchars($teacherFullName); ?>">
                    <?= htmlspecialchars($teacherFullName); ?>
                </div>
                <div class="teacher-sidebar__teacher-role" title="<?= htmlspecialchars($teacherRoleLabel); ?>">
                    <?= htmlspecialchars($teacherRoleLabel); ?>
                </div>
            </div>
        </div>
        <div class="teacher-sidebar__teacher-stats">
            <div class="teacher-sidebar__stat-row">
                <span class="label">Số lớp phụ trách:</span>
                <span class="value"><?= htmlspecialchars((string) $sidebarClassCount); ?> lớp</span>
            </div>
            <div class="teacher-sidebar__stat-row">
                <span class="label">Số học viên:</span>
                <span class="value"><?= htmlspecialchars((string) $sidebarStudentCount); ?></span>
            </div>
        </div>
    </div>

    <!-- Bottom Action: Logout -->
    <div class="teacher-sidebar__footer">
        <a href="<?= function_exists('app_href') ? app_href('/logout.php?role=teacher') : '/logout.php?role=teacher'; ?>"
           class="teacher-sidebar__link teacher-sidebar__link--logout"
           aria-label="Đăng xuất khỏi hệ thống">
            <span class="teacher-sidebar__icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </span>
            <span class="teacher-sidebar__text">Đăng xuất</span>
        </a>
    </div>
</aside>
