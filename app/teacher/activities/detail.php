<?php

require_once __DIR__ . '/../includes/dashboard-data.php';
require_once __DIR__ . '/../includes/activity-data.php';
require_once __DIR__ . '/../includes/cover-upload.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!function_exists('teacherActivitiesEscape')) {
    function teacherActivitiesEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('teacherCoverWebUrl')) {
    function teacherCoverWebUrl(?string $path): string
    {
        if ($path === null || trim($path) === '') {
            return function_exists('app_href') ? app_href('/app/learner/assets/activities/illustrations/hero-discover.svg') : '/app/learner/assets/activities/illustrations/hero-discover.svg';
        }
        $path = trim($path);
        return function_exists('app_href') ? app_href($path) : $path;
    }
}

if (!function_exists('teacherActivitiesLifecycleAction')) {
    function teacherActivitiesLifecycleAction(array $activity): ?array
    {
        $rawStatus = strtolower(trim((string) ($activity['raw_status'] ?? '')));

        if ($rawStatus === 'draft') {
            return match ((string) ($activity['approval_status'] ?? 'draft')) {
                'draft' => ['label' => 'Gửi Nhà trường duyệt', 'form_action' => 'submit_for_school_review'],
                'changes_requested' => ['label' => 'Gửi duyệt lại', 'form_action' => 'submit_for_school_review'],
                'approved' => ['label' => 'Công bố hoạt động', 'form_action' => 'advance_status'],
                default => null,
            };
        }

        if ($rawStatus === 'published') {
            return [
                'label' => 'Bắt đầu hoạt động',
                'form_action' => 'advance_status',
            ];
        }

        return match ($rawStatus) {
            'ongoing' => ['label' => 'Kết thúc hoạt động', 'form_action' => 'advance_status'],
            'completed' => ['label' => 'Lưu trữ hoạt động', 'form_action' => 'advance_status'],
            'archived' => null,
            default => null,
        };
    }
}

$dashboardData = teacherDashboardReadData();
$dashboardContext = teacherDashboardBackendContext();
$session = $dashboardContext['session'] ?? null;
$csrfToken = $session instanceof \TalentHub\Auth\Session\SessionManager ? $session->csrfToken() : '';
$teacherInfo = $dashboardData['teacherInfo'];
$teacherId = (string) ($teacherInfo['id'] ?? '');
$pdo = $teacherId !== '' ? teacherDashboardConnect() : null;
$activityService = $pdo ? teacherActivitiesService($pdo) : null;

$pageTitle = 'Chi tiết hoạt động';
$currentRoute = 'index.php';
$teacherSidebarHomeHref = '../index.php';
$teacherSidebarRoleHref = '../../../role-selection.php';
$sidebarNav = [
    [
        'title' => 'Tổng quan',
        'route' => '../index.php',
        'href' => '../index.php',
        'icon' => 'grid',
        'active' => false,
    ],
    [
        'title' => 'Hoạt động',
        'route' => 'index.php',
        'icon' => 'trophy',
        'active' => true,
    ],
    [
        'title' => 'Chấm điểm',
        'route' => '../assessments',
        'icon' => 'clipboard-check',
        'active' => false,
    ],
    [
        'title' => 'Học viên',
        'route' => '../students',
        'icon' => 'users',
        'active' => false,
    ],
];

$activityId = trim((string) ($_GET['id'] ?? ''));
$errors = [];
$errorHeading = 'Không thể cập nhật hoạt động.';
$notice = '';
$noticeType = 'success';

if (isset($_GET['saved'])) {
    $noticeMessages = [
        'created' => 'Đã lưu bản nháp. Chọn “Gửi Nhà trường duyệt” để xin phê duyệt trước khi công bố.',
        'updated' => 'Đã cập nhật hoạt động. Bạn có thể gửi Nhà trường duyệt khi thông tin đã hoàn tất.',
        'advanced' => 'Đã chuyển hoạt động sang trạng thái mới.',
        'started' => 'Đã bắt đầu hoạt động thành công. Hoạt động hiện đang diễn ra.',
        'submitted' => 'Đã gửi hoạt động đến Nhà trường. Bạn có thể công bố sau khi được duyệt.',
    ];
    $notice = $noticeMessages[(string) $_GET['saved']] ?? 'Đã lưu thay đổi hoạt động.';
}

$selectedActivity = null;
if ($pdo && $teacherId !== '' && $activityId !== '') {
    $selectedActivity = teacherActivitiesFind($pdo, $teacherId, $activityId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfValid = true;
    try {
        if (!$session instanceof \TalentHub\Auth\Session\SessionManager) {
            throw new RuntimeException('Teacher session is unavailable.');
        }
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    } catch (Throwable) {
        $csrfValid = false;
        $errors[] = 'Yêu cầu không hợp lệ hoặc phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.';
    }

    $formAction = (string) ($_POST['form_action'] ?? '');
    $postedActivityId = trim((string) ($_POST['activity_id'] ?? ''));
    $targetId = $postedActivityId !== '' ? $postedActivityId : $activityId;

    if ($csrfValid && in_array($formAction, ['create', 'edit', 'registration_transition'], true)) {
        if ($formAction === 'registration_transition' && $targetId !== '') {
            header('Location: index.php?action=registrations&id=' . rawurlencode($targetId));
            exit;
        }
        if (in_array($formAction, ['create', 'edit'], true)) {
            $redirect = $targetId !== '' ? 'create.php?id=' . rawurlencode($targetId) : 'create.php';
            header('Location: ' . $redirect);
            exit;
        }
    }

    if ($csrfValid && $formAction === 'advance_status') {
        if (!$pdo || $teacherId === '') {
            $errors[] = 'Chưa kết nối được hồ sơ giáo viên để cập nhật trạng thái hoạt động.';
        }
        if ($postedActivityId === '') {
            $errors[] = 'Thiếu mã hoạt động cần cập nhật.';
        }
        if (!$errors) {
            try {
                $nextStatus = $activityService->advanceStatus($teacherId, $postedActivityId);
                $savedKey = $nextStatus === 'ongoing' ? 'started' : 'advanced';
                header('Location: detail.php?id=' . rawurlencode($postedActivityId) . '&saved=' . $savedKey);
                exit;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage() ?: 'Không thể cập nhật trạng thái hoạt động. Vui lòng kiểm tra lại kết nối dữ liệu.';
            }
        }
    }

    if ($csrfValid && $formAction === 'submit_for_school_review') {
        if (!$pdo || $teacherId === '') {
            $errors[] = 'Chưa kết nối được hồ sơ giáo viên để gửi duyệt hoạt động.';
        }
        if ($postedActivityId === '') {
            $errors[] = 'Thiếu mã hoạt động cần gửi duyệt.';
        }
        if (!$errors) {
            try {
                $activityService->submitForSchoolReview($teacherId, $postedActivityId, \TalentHub\Support\Id\RequestId::make(null));
                header('Location: detail.php?id=' . rawurlencode($postedActivityId) . '&saved=submitted');
                exit;
            } catch (Throwable $exception) {
                $message = $exception->getMessage() ?: 'Không thể gửi hoạt động đến Nhà trường. Vui lòng kiểm tra dữ liệu hoạt động.';
                if ($postedActivityId !== '' && isset($_SESSION) && is_array($_SESSION)) {
                    $_SESSION['teacher_activity_review_error'] = [
                        'heading' => 'Chưa thể gửi duyệt hoạt động.',
                        'messages' => [$message],
                    ];
                    header('Location: create.php?id=' . rawurlencode($postedActivityId));
                    exit;
                }
                $errorHeading = 'Chưa thể gửi duyệt hoạt động.';
                $errors[] = $message;
            }
        } else {
            $errorHeading = 'Chưa thể gửi duyệt hoạt động.';
        }
    }

    if ($postedActivityId !== '') {
        $activityId = $postedActivityId;
        $selectedActivity = $pdo && $teacherId !== '' ? teacherActivitiesFind($pdo, $teacherId, $activityId) : null;
    }
}

if ($selectedActivity) {
    $pageTitle = (string) ($selectedActivity['title'] ?? 'Chi tiết hoạt động');
}

$responsibleTeacherName = '';
if ($selectedActivity && $pdo && $teacherId !== '') {
    $responsibleId = (string) ($selectedActivity['responsibleTeacherId'] ?? '');
    if ($responsibleId !== '') {
        $teachers = $activityService?->responsibleTeachers($teacherId) ?? [];
        foreach ($teachers as $teacher) {
            if ((string) ($teacher['id'] ?? '') === $responsibleId) {
                $responsibleTeacherName = (string) ($teacher['name'] ?? '');
                break;
            }
        }
    }
}

$approvalModeLabel = match ((string) ($selectedActivity['approvalMode'] ?? '')) {
    'teacher_review' => 'Giáo viên duyệt',
    'automatic' => 'Duyệt tự động',
    default => 'Chưa thiết lập',
};

$feeAmount = (float) ($selectedActivity['feeAmount'] ?? 0);
$feeLabel = $feeAmount > 0
    ? number_format($feeAmount, 0, ',', '.') . ' ' . (string) ($selectedActivity['currency'] ?? 'VND')
    : 'Miễn phí';

$detailLifecycleAction = $selectedActivity ? teacherActivitiesLifecycleAction($selectedActivity) : null;
$coverUrl = trim((string) ($selectedActivity['coverImageUrl'] ?? ''));
?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="description" content="Chi tiết hoạt động do giáo viên phụ trách trên TalentHub.">
    <title><?= teacherActivitiesEscape($pageTitle); ?> | TalentHub</title>
<?php
$teacherAssetUrl = static function (string $relPath): string {
    $fsPath = dirname(__DIR__, 3) . $relPath;
    $ver = is_file($fsPath) ? (string) filemtime($fsPath) : '1.0';
    $href = function_exists('app_href') ? app_href($relPath) : $relPath;
    return $href . '?v=' . $ver;
};
?>
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/home.css')); ?>">
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/global.css')); ?>">
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/brand-component.css')); ?>">
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/polish.css')); ?>">
    <link rel="stylesheet" href="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/css/teacher.css')); ?>">
</head>
<body class="teacher-dashboard teacher-activities-page teacher-activity-detail-page">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require_once __DIR__ . '/../includes/header.php'; ?>

            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    <nav class="teacher-reg-breadcrumb" aria-label="Điều hướng">
                        <a href="index.php" class="teacher-reg-breadcrumb__back">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                            <span>Quay lại danh sách</span>
                        </a>
                    </nav>

                    <?php if ($notice !== ''): ?>
                        <div class="teacher-activities-notice teacher-activities-notice--<?= teacherActivitiesEscape($noticeType); ?>" role="status">
                            <?= teacherActivitiesEscape($notice); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($errors): ?>
                        <div class="teacher-activities-notice teacher-activities-notice--error" role="alert">
                            <strong><?= teacherActivitiesEscape($errorHeading); ?></strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= teacherActivitiesEscape($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($activityId === '' || $selectedActivity === null): ?>
                        <div class="teacher-activities-notice teacher-activities-notice--error" role="alert">
                            Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.
                            <div style="margin-top:0.75rem;">
                                <a href="index.php" class="btn btn-secondary btn-sm">Quay lại danh sách</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <section class="teacher-section-box teacher-activity-detail-panel">
                            <?php if ($coverUrl !== ''): ?>
                                <div class="teacher-activity-detail-cover">
                                    <img src="<?= teacherActivitiesEscape(teacherCoverWebUrl($coverUrl)); ?>" alt="<?= teacherActivitiesEscape($selectedActivity['coverImageAlt'] ?: $selectedActivity['title']); ?>" class="teacher-activity-detail-cover__img">
                                </div>
                            <?php endif; ?>

                            <div class="teacher-section-box__header teacher-activity-detail-header">
                                <div>
                                    <span class="teacher-welcome__tag">Chi tiết hoạt động</span>
                                    <div class="teacher-activities-form__heading-row">
                                        <h2 class="teacher-section-box__title"><?= teacherActivitiesEscape($selectedActivity['title']); ?></h2>
                                        <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($selectedActivity['status_class']); ?>"><?= teacherActivitiesEscape($selectedActivity['status_label']); ?></span>
                                        <?php if (!empty($selectedActivity['approval_status_label']) && ($selectedActivity['approval_status'] ?? '') !== 'draft'): ?>
                                            <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($selectedActivity['approval_status_class']); ?>"><?= teacherActivitiesEscape($selectedActivity['approval_status_label']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($selectedActivity['approval_guidance'])): ?>
                                        <p class="teacher-section-box__subtitle"><?= teacherActivitiesEscape($selectedActivity['approval_guidance']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="teacher-activities-header-actions">
                                    <a href="index.php" class="btn btn-secondary btn-sm">Quay lại danh sách</a>
                                    <?php if (!empty($selectedActivity['can_edit'])): ?>
                                        <a href="create.php?id=<?= teacherActivitiesEscape($selectedActivity['id']); ?>" class="btn btn-secondary btn-sm">Chỉnh sửa</a>
                                    <?php endif; ?>
                                    <a href="index.php?action=registrations&amp;id=<?= teacherActivitiesEscape($selectedActivity['id']); ?>" class="btn btn-secondary btn-sm">Xem sinh viên</a>
                                    <?php if (in_array($selectedActivity['status'] ?? '', ['published', 'ongoing'], true)): ?>
                                        <a href="../checkins/index.php?activity_id=<?= teacherActivitiesEscape($selectedActivity['id']); ?>" class="btn btn-primary btn-sm">QR checkin</a>
                                    <?php endif; ?>
                                    <?php if ($detailLifecycleAction !== null): ?>
                                        <form method="post" class="teacher-activities-inline-form">
                                            <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                            <input type="hidden" name="form_action" value="<?= teacherActivitiesEscape($detailLifecycleAction['form_action']); ?>">
                                            <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($selectedActivity['id']); ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm"><?= teacherActivitiesEscape($detailLifecycleAction['label']); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($selectedActivity['approval_reason']) || !empty($selectedActivity['approval_requested_label']) || !empty($selectedActivity['approved_at_label'])): ?>
                                <div class="teacher-activity-detail-approval">
                                    <?php if (!empty($selectedActivity['approval_reason'])): ?>
                                        <p><span>Lý do / phản hồi</span><strong><?= teacherActivitiesEscape($selectedActivity['approval_reason']); ?></strong></p>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedActivity['approval_requested_label'])): ?>
                                        <p><span>Ngày gửi duyệt</span><strong><?= teacherActivitiesEscape($selectedActivity['approval_requested_label']); ?></strong></p>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedActivity['approved_at_label'])): ?>
                                        <p><span>Ngày duyệt</span><strong><?= teacherActivitiesEscape($selectedActivity['approved_at_label']); ?></strong></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="teacher-activity-detail-sections">
                                <section class="teacher-activity-detail-section">
                                    <h3>Thông tin chung</h3>
                                    <div class="teacher-activity-detail-grid">
                                        <div><span>Nhóm</span><strong><?= teacherActivitiesEscape(!empty($selectedActivity['category_label']) ? $selectedActivity['category_label'] : (!empty($selectedActivity['displayCategory']) ? $selectedActivity['displayCategory'] : 'Chưa phân loại')); ?></strong></div>
                                        <div><span>Hình thức</span><strong><?= teacherActivitiesEscape($selectedActivity['delivery_mode_label'] ?? 'Chưa xác định'); ?></strong></div>
                                        <div><span>Đối tượng</span><strong><?= teacherActivitiesEscape($selectedActivity['targetAudience'] ?: 'Chưa cập nhật'); ?></strong></div>
                                        <div><span>Chứng nhận</span><strong><?= teacherActivitiesEscape($selectedActivity['certificateLabel'] ?: 'Chưa cập nhật'); ?></strong></div>
                                        <div><span>Chi phí</span><strong><?= teacherActivitiesEscape($feeLabel); ?></strong></div>
                                        <div><span>Giờ công nhận</span><strong><?= teacherActivitiesEscape((string) ($selectedActivity['confirmedHours'] ?? '0')); ?> giờ</strong></div>
                                    </div>
                                    <?php if (!empty($selectedActivity['summary'])): ?>
                                        <div class="teacher-activity-detail-prose">
                                            <span>Tóm tắt</span>
                                            <p><?= nl2br(teacherActivitiesEscape($selectedActivity['summary'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($selectedActivity['description'])): ?>
                                        <div class="teacher-activity-detail-prose">
                                            <span>Mô tả</span>
                                            <p><?= nl2br(teacherActivitiesEscape($selectedActivity['description'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </section>

                                <section class="teacher-activity-detail-section">
                                    <h3>Lịch trình & địa điểm</h3>
                                    <div class="teacher-activity-detail-grid">
                                        <div><span>Bắt đầu</span><strong><?= teacherActivitiesEscape($selectedActivity['start_label']); ?></strong></div>
                                        <div><span>Kết thúc</span><strong><?= teacherActivitiesEscape($selectedActivity['end_label']); ?></strong></div>
                                        <div><span>Địa điểm</span><strong><?= teacherActivitiesEscape($selectedActivity['locationName'] ?: 'Chưa xác định địa điểm'); ?></strong></div>
                                        <div><span>Địa chỉ</span><strong><?= teacherActivitiesEscape($selectedActivity['locationAddress'] ?: 'Chưa cập nhật'); ?></strong></div>
                                        <div class="teacher-activity-detail-grid__wide"><span>Link trực tuyến</span><strong>
                                            <?php if (!empty($selectedActivity['onlineMeetingUrl'])): ?>
                                                <a href="<?= teacherActivitiesEscape($selectedActivity['onlineMeetingUrl']); ?>" target="_blank" rel="noopener noreferrer"><?= teacherActivitiesEscape($selectedActivity['onlineMeetingUrl']); ?></a>
                                            <?php else: ?>
                                                Không có
                                            <?php endif; ?>
                                        </strong></div>
                                    </div>
                                </section>

                                <section class="teacher-activity-detail-section">
                                    <h3>Đăng ký</h3>
                                    <div class="teacher-activity-detail-grid">
                                        <div><span>Mở đăng ký</span><strong><?= teacherActivitiesEscape($selectedActivity['registration_opens_input'] ? date('d/m/Y H:i', strtotime(str_replace('T', ' ', $selectedActivity['registration_opens_input']))) : 'Chưa thiết lập'); ?></strong></div>
                                        <div><span>Đóng đăng ký</span><strong><?= teacherActivitiesEscape($selectedActivity['registration_closes_input'] ? date('d/m/Y H:i', strtotime(str_replace('T', ' ', $selectedActivity['registration_closes_input']))) : 'Chưa thiết lập'); ?></strong></div>
                                        <div><span>Cho phép hủy đến</span><strong><?= teacherActivitiesEscape($selectedActivity['cancellation_closes_input'] ? date('d/m/Y H:i', strtotime(str_replace('T', ' ', $selectedActivity['cancellation_closes_input']))) : 'Chưa thiết lập'); ?></strong></div>
                                        <div><span>Đăng ký / sức chứa</span><strong><?= teacherActivitiesEscape((string) $selectedActivity['registered_count']); ?> / <?= teacherActivitiesEscape((string) $selectedActivity['capacity']); ?></strong></div>
                                        <div><span>Khả năng đăng ký</span><strong><span class="teacher-registration-pill teacher-registration-pill--<?= $selectedActivity['registration_available'] ? 'available' : 'unavailable'; ?>"><?= teacherActivitiesEscape($selectedActivity['registration_label']); ?></span></strong></div>
                                        <div><span>Cách duyệt</span><strong><?= teacherActivitiesEscape($approvalModeLabel); ?></strong></div>
                                    </div>
                                </section>

                                <section class="teacher-activity-detail-section">
                                    <h3>Trải nghiệm & kỹ năng</h3>
                                    <?php
                                    $highlights = $selectedActivity['experience_highlights_list'] ?? [];
                                    $skills = $selectedActivity['assigned_skills'] ?? [];
                                    $skillTags = $selectedActivity['skill_tags_list'] ?? [];
                                    $eligibility = $selectedActivity['eligibility_rules_list'] ?? [];
                                    $benefits = $selectedActivity['benefit_items_list'] ?? [];
                                    ?>
                                    <div class="teacher-activity-detail-lists">
                                        <div>
                                            <span>Điểm nhấn trải nghiệm</span>
                                            <?php if ($highlights): ?>
                                                <ul><?php foreach ($highlights as $item): ?><li><?= teacherActivitiesEscape($item); ?></li><?php endforeach; ?></ul>
                                            <?php else: ?>
                                                <p class="teacher-text-muted">Chưa cập nhật</p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span>Kỹ năng</span>
                                            <?php if ($skills): ?>
                                                <ul><?php foreach ($skills as $skill): ?><li><?= teacherActivitiesEscape(is_array($skill) ? ($skill['name'] ?? '') : $skill); ?></li><?php endforeach; ?></ul>
                                            <?php elseif ($skillTags): ?>
                                                <div class="teacher-activity-detail-tags">
                                                    <?php foreach ($skillTags as $tag): ?>
                                                        <span class="teacher-chip"><?= teacherActivitiesEscape($tag); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <p class="teacher-text-muted">Chưa cập nhật</p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span>Điều kiện tham gia</span>
                                            <?php if ($eligibility): ?>
                                                <ul><?php foreach ($eligibility as $item): ?><li><?= teacherActivitiesEscape($item); ?></li><?php endforeach; ?></ul>
                                            <?php else: ?>
                                                <p class="teacher-text-muted">Chưa cập nhật</p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span>Quyền lợi</span>
                                            <?php if ($benefits): ?>
                                                <ul><?php foreach ($benefits as $item): ?><li><?= teacherActivitiesEscape($item); ?></li><?php endforeach; ?></ul>
                                            <?php else: ?>
                                                <p class="teacher-text-muted">Chưa cập nhật</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </section>

                                <section class="teacher-activity-detail-section">
                                    <h3>Tổ chức & liên hệ</h3>
                                    <div class="teacher-activity-detail-grid">
                                        <div><span>Đơn vị tổ chức</span><strong><?= teacherActivitiesEscape($selectedActivity['organizerName'] ?: 'Chưa cập nhật'); ?></strong></div>
                                        <div><span>Đầu mối liên hệ</span><strong><?= teacherActivitiesEscape($selectedActivity['organizerContact'] ?: 'Chưa cập nhật'); ?></strong></div>
                                        <div><span>Email</span><strong><?= teacherActivitiesEscape($selectedActivity['organizerEmail'] ?: 'Chưa cập nhật'); ?></strong></div>
                                        <div><span>Điện thoại</span><strong><?= teacherActivitiesEscape($selectedActivity['organizerPhone'] ?: 'Chưa cập nhật'); ?></strong></div>
                                        <?php if ($responsibleTeacherName !== ''): ?>
                                            <div><span>Giáo viên phụ trách</span><strong><?= teacherActivitiesEscape($responsibleTeacherName); ?></strong></div>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="<?= teacherActivitiesEscape($teacherAssetUrl('/assets/js/teacher.js')); ?>"></script>
</body>
</html>
