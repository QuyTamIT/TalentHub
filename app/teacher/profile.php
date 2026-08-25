<?php
/**
 * TalentHub - Teacher Profile Page
 *
 * Displays and allows updating of Teacher profile details.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/dashboard-data.php';

use TalentHub\Modules\Teacher\Repository\TeacherRepository;
use TalentHub\Modules\Teacher\Service\TeacherProfileService;

$backendContext = teacherDashboardBackendContext();
$pdo = $backendContext['pdo'] ?? null;
$user = $backendContext['user'] ?? null;
$session = $backendContext['session'] ?? null;

$teacherService = $pdo ? new TeacherProfileService(new TeacherRepository($pdo)) : null;

$successMessage = null;
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $teacherService && $user) {
    try {
        $csrfToken = (string) ($_POST['csrfToken'] ?? '');
        if ($session) {
            $session->assertCsrf($csrfToken);
        }

        $fullName = trim((string) ($_POST['fullName'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $specialization = trim((string) ($_POST['specialization'] ?? ''));
        $bio = trim((string) ($_POST['bio'] ?? ''));

        $updated = $teacherService->update((string) $user['id'], [
            'fullName' => $fullName,
            'phone' => $phone ?: null,
            'specialization' => $specialization ?: null,
            'bio' => $bio ?: null,
        ]);

        $successMessage = 'Cập nhật hồ sơ giáo viên thành công!';
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$profile = ($teacherService && $user) ? $teacherService->get((string) $user['id']) : null;

$teacherInfo = [
    'full_name' => $profile['fullName'] ?? ($user['fullName'] ?? 'Thầy Nguyễn Văn Bình'),
    'email' => $profile['email'] ?? ($user['email'] ?? 'teacher@talenthub.local'),
    'role_label' => 'Giáo viên / Hướng dẫn viên',
    'school_name' => $profile['school']['name'] ?? 'THPT Nguyễn Trãi',
    'avatar_initials' => 'TB',
    'phone' => $profile['phone'] ?? '0912345678',
    'specialization' => $profile['specialization'] ?? 'Toán - Tin học',
    'bio' => $profile['bio'] ?? 'Giáo viên Tin học & Hướng nghiệp Công nghệ',
    'notification_count' => 0,
];

$pageTitle = 'Hồ sơ Giáo viên';
$currentRoute = '/app/teacher/profile.php';

$sidebarNav = [
    [
        'title' => 'Tổng quan',
        'route' => 'index.php',
        'href' => '/app/teacher/index.php',
        'icon' => 'grid',
        'active' => false,
    ],
    [
        'title' => 'Hoạt động',
        'route' => 'activities/',
        'href' => '/app/teacher/activities/index.php',
        'icon' => 'trophy',
        'active' => false,
    ],
    [
        'title' => 'Chấm điểm',
        'route' => 'assessments',
        'href' => '/app/teacher/assessments/index.php',
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
    [
        'title' => 'Điểm danh QR',
        'route' => 'checkins',
        'href' => '/app/teacher/checkins/index.php',
        'icon' => 'qr',
        'active' => false,
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?> | TalentHub Teacher</title>
    
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/teacher.css">
</head>
<body class="teacher-dashboard">

    <div class="teacher-layout">
        <!-- Sidebar Navigation -->
        <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <!-- Header -->
            <?php require_once __DIR__ . '/includes/header.php'; ?>

            <main class="teacher-body">
                <div class="teacher-container" style="max-width: 960px;">
                    
                    <?php if ($successMessage): ?>
                        <div class="ent-alert ent-alert--success mb-4" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 0.875rem 1.25rem; border-radius: 8px; font-weight: 500; margin-bottom: 1.5rem;">
                            <?= htmlspecialchars($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($errorMessage): ?>
                        <div class="ent-alert ent-alert--danger mb-4" style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.875rem 1.25rem; border-radius: 8px; font-weight: 500; margin-bottom: 1.5rem;">
                            <?= htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Header Card -->
                    <section class="teacher-section-box mb-4" style="padding: 1.75rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
                            <div style="width: 4.5rem; height: 4.5rem; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 800; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);">
                                <?= htmlspecialchars($teacherInfo['avatar_initials']); ?>
                            </div>
                            <div style="flex: 1; min-width: 240px;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.25rem;">
                                    <h2 style="font-size: 1.375rem; font-weight: 700; color: var(--text-primary); margin: 0;">
                                        <?= htmlspecialchars($teacherInfo['full_name']); ?>
                                    </h2>
                                    <span style="display: inline-block; padding: 0.2rem 0.625rem; font-size: 0.6875rem; font-weight: 600; color: #c2410c; background: #fff7ed; border: 1px solid rgba(249, 115, 22, 0.25); border-radius: 9999px;">
                                        <?= htmlspecialchars($teacherInfo['role_label']); ?>
                                    </span>
                                </div>
                                <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;">
                                    Đơn vị công tác: <strong style="color: var(--text-primary);"><?= htmlspecialchars($teacherInfo['school_name']); ?></strong>
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Edit Profile Form -->
                    <section class="teacher-section-box" style="padding: 1.75rem;">
                        <div class="teacher-section-box__header" style="margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border);">
                            <h3 class="teacher-section-box__title" style="font-size: 1.125rem;">Thông tin chi tiết</h3>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="update_profile" value="1">
                            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session instanceof \TalentHub\Auth\Session\SessionManager ? $session->csrfToken() : ''); ?>">

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                                <div>
                                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">
                                        Họ và tên <span style="color: #ef4444;">*</span>
                                    </label>
                                    <input type="text" name="fullName" value="<?= htmlspecialchars($teacherInfo['full_name']); ?>" required style="width: 100%; height: 2.625rem; padding: 0 0.875rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--surface);">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">
                                        Email tài khoản
                                    </label>
                                    <input type="email" value="<?= htmlspecialchars($teacherInfo['email']); ?>" disabled style="width: 100%; height: 2.625rem; padding: 0 0.875rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: #f8fafc; color: var(--text-muted); cursor: not-allowed;">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">
                                        Số điện thoại liên hệ
                                    </label>
                                    <input type="tel" name="phone" value="<?= htmlspecialchars($teacherInfo['phone']); ?>" placeholder="Ví dụ: 0912345678" style="width: 100%; height: 2.625rem; padding: 0 0.875rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--surface);">
                                </div>

                                <div>
                                    <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">
                                        Chuyên môn / Bộ môn
                                    </label>
                                    <input type="text" name="specialization" value="<?= htmlspecialchars($teacherInfo['specialization']); ?>" placeholder="Ví dụ: Toán - Tin học" style="width: 100%; height: 2.625rem; padding: 0 0.875rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--surface);">
                                </div>
                            </div>

                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.375rem;">
                                    Giới thiệu bản thân / Tiểu sử
                                </label>
                                <textarea name="bio" rows="4" placeholder="Nhập tóm tắt quá trình công tác, hướng dẫn học sinh năng khiếu..." style="width: 100%; padding: 0.75rem 0.875rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--surface); resize: vertical;"><?= htmlspecialchars($teacherInfo['bio']); ?></textarea>
                            </div>

                            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                                <a href="index.php" style="display: inline-flex; align-items: center; padding: 0.625rem 1.25rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: var(--text-secondary); text-decoration: none; background: var(--surface);">
                                    Hủy bỏ
                                </a>
                                <button type="submit" style="display: inline-flex; align-items: center; padding: 0.625rem 1.25rem; border: none; border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: #fff; background: var(--primary); cursor: pointer; transition: background 0.15s ease;">
                                    Lưu thay đổi
                                </button>
                            </div>
                        </form>
                    </section>

                </div>
            </main>
        </div>
    </div>

    <!-- Notification Toast -->
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
