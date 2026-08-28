<?php
/**
 * TalentHub - Teacher Activities / My Playgrounds
 */

require_once __DIR__ . '/../includes/dashboard-data.php';
require_once __DIR__ . '/../includes/activity-data.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!function_exists('teacherActivitiesEscape')) {
    function teacherActivitiesEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('teacherActivitiesFormDate')) {
    function teacherActivitiesFormDate(?string $value): ?DateTimeImmutable
    {
        if (!$value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value, new DateTimeZone('Asia/Ho_Chi_Minh'));
        return $date ?: null;
    }
}

if (!function_exists('teacherActivitiesLifecycleAction')) {
    function teacherActivitiesLifecycleAction(array $activity): ?array
    {
        $rawStatus = strtolower(trim((string) ($activity['raw_status'] ?? '')));

        return match ($rawStatus) {
            'draft' => ['label' => 'Công bố hoạt động'],
            'published' => ['label' => 'Bắt đầu hoạt động'],
            'ongoing' => ['label' => 'Kết thúc hoạt động'],
            'completed' => ['label' => 'Lưu trữ hoạt động'],
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
$teacherUserId = (string) ($dashboardContext['user']['id'] ?? '');
$pdo = $teacherId !== '' ? teacherDashboardConnect() : null;
$schoolId = $pdo && $teacherId !== '' ? teacherActivitiesSchoolId($pdo, $teacherId) : null;
$activityService = $pdo ? teacherActivitiesService($pdo) : null;

$pageTitle = 'Hoạt động / Sân chơi của tôi';
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
    [
        'title' => 'Điểm danh QR',
        'route' => '../checkins',
        'icon' => 'qr',
        'active' => false,
    ],
];

$action = strtolower(trim((string) ($_GET['action'] ?? '')));
$activityId = trim((string) ($_GET['id'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
    $statusFilters = ['draft', 'published', 'ongoing', 'completed', 'archived'];
if (!in_array($statusFilter, $statusFilters, true)) {
    $statusFilter = '';
}

$errors = [];
$notice = '';
$noticeType = 'success';

if (isset($_GET['saved'])) {
    $noticeMessages = [
        'created' => 'Đã tạo hoạt động mới.',
        'updated' => 'Đã cập nhật hoạt động.',
        'advanced' => 'Đã chuyển hoạt động sang trạng thái mới.',
        'registration' => 'Đã cập nhật trạng thái đăng ký.',
    ];
    $notice = $noticeMessages[(string) $_GET['saved']] ?? 'Đã lưu thay đổi hoạt động.';
}

$selectedActivity = null;
if ($pdo && $teacherId !== '' && $activityId !== '') {
    $selectedActivity = teacherActivitiesFind($pdo, $teacherId, $activityId);
}

$formValues = [
    'title' => '',
    'category' => '',
    'startAt' => (new DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i'),
    'endAt' => (new DateTimeImmutable('+1 day 3 hours'))->format('Y-m-d\TH:i'),
    'capacity' => '30',
];

if ($action === 'edit' && $selectedActivity) {
    $formValues = [
        'title' => (string) ($selectedActivity['title'] ?? ''),
        'category' => (string) ($selectedActivity['category'] ?? ''),
        'startAt' => (string) ($selectedActivity['start_input'] ?? ''),
        'endAt' => (string) ($selectedActivity['end_input'] ?? ''),
        'capacity' => (string) ($selectedActivity['capacity'] ?? 0),
    ];
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

    if (!$csrfValid) {
        $action = '';
    } else {
        $formAction = (string) ($_POST['form_action'] ?? 'create');
        $postedActivityId = trim((string) ($_POST['activity_id'] ?? ''));

    if ($formAction === 'registration_transition') {
        $postedRegistrationId = trim((string) ($_POST['registration_id'] ?? ''));
        $registrationAction = trim((string) ($_POST['registration_action'] ?? ''));
        $expectedStatus = trim((string) ($_POST['expected_status'] ?? ''));
        if (!$pdo || !$activityService || $teacherId === '' || $teacherUserId === '') {
            $errors[] = 'Chưa kết nối được hồ sơ giáo viên để xử lý đăng ký.';
        }
        if ($postedActivityId === '' || $postedRegistrationId === '') {
            $errors[] = 'Thiếu mã hoạt động hoặc mã đăng ký.';
        }
        if (!$errors) {
            try {
                (new \TalentHub\Rbac\Service\PermissionService($pdo))->require(
                    $teacherUserId,
                    'activity_registration.update_managed'
                );
                $activityService->transitionRegistration(
                    $teacherId,
                    $teacherUserId,
                    \TalentHub\Support\Id\RequestId::make(null),
                    $postedActivityId,
                    $postedRegistrationId,
                    ['expectedStatus' => $expectedStatus, 'action' => $registrationAction],
                );
                header('Location: index.php?action=registrations&id=' . rawurlencode($postedActivityId) . '&saved=registration');
                exit;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage() ?: 'Không thể xử lý đăng ký hoạt động.';
            }
        }
    }

    if ($formAction === 'advance_status') {
        if (!$pdo || $teacherId === '') {
            $errors[] = 'Chưa kết nối được hồ sơ giáo viên để cập nhật trạng thái hoạt động.';
        }
        if ($postedActivityId === '') {
            $errors[] = 'Thiếu mã hoạt động cần cập nhật.';
        }

        if (!$errors) {
            try {
                $activityService->advanceStatus($teacherId, $postedActivityId);
                header('Location: index.php?saved=advanced');
                exit;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage() ?: 'Không thể cập nhật trạng thái hoạt động. Vui lòng kiểm tra lại kết nối dữ liệu.';
            }
        }
    }

    if ($formAction === 'registration_transition') {
        $action = 'registrations';
        $activityId = $postedActivityId;
        $selectedActivity = $pdo && $teacherId !== '' ? teacherActivitiesFind($pdo, $teacherId, $activityId) : null;
    } elseif ($formAction === 'advance_status') {
        $action = '';
        if ($postedActivityId !== '') {
            $activityId = $postedActivityId;
            $selectedActivity = $pdo && $teacherId !== '' ? teacherActivitiesFind($pdo, $teacherId, $activityId) : null;
        }
    } else {
        $formValues = [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'category' => trim((string) ($_POST['category'] ?? '')),
        'startAt' => trim((string) ($_POST['startAt'] ?? '')),
        'endAt' => trim((string) ($_POST['endAt'] ?? '')),
        'capacity' => trim((string) ($_POST['capacity'] ?? '')),
    ];

    $startAt = teacherActivitiesFormDate($formValues['startAt']);
    $endAt = teacherActivitiesFormDate($formValues['endAt']);
    $capacity = filter_var($formValues['capacity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!$pdo || $teacherId === '' || $schoolId === null) {
        $errors[] = 'Chưa kết nối được hồ sơ giáo viên để lưu hoạt động.';
    }
    if ($formValues['title'] === '') {
        $errors[] = 'Vui lòng nhập tên hoạt động.';
    }
    if ($formValues['category'] === '') {
        $errors[] = 'Vui lòng nhập nhóm hoạt động.';
    }
    if (!$startAt) {
        $errors[] = 'Thời gian bắt đầu không hợp lệ.';
    }
    if (!$endAt) {
        $errors[] = 'Thời gian kết thúc không hợp lệ.';
    }
    if ($startAt && $endAt && $endAt <= $startAt) {
        $errors[] = 'Thời gian kết thúc phải sau thời gian bắt đầu.';
    }
    if ($capacity === false) {
        $errors[] = 'Sức chứa phải là số nguyên lớn hơn 0.';
    }
    if (!$errors) {
        $payload = [
            'title' => $formValues['title'],
            'category' => $formValues['category'],
            'startAt' => $startAt,
            'endAt' => $endAt,
            'capacity' => $capacity,
        ];

        try {
            if ($formAction === 'edit' && $postedActivityId !== '') {
                $activityService->update($teacherId, $postedActivityId, $payload);
                header('Location: index.php?saved=updated');
                exit;
            }

            $activityService->create($teacherId, $schoolId, $payload);
            header('Location: index.php?saved=created');
            exit;
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage() ?: 'Không thể lưu hoạt động. Vui lòng kiểm tra lại kết nối dữ liệu.';
        }
    }

        $action = $formAction === 'edit' ? 'edit' : 'create';
        if ($action === 'edit' && $postedActivityId !== '') {
            $activityId = $postedActivityId;
            $selectedActivity = $pdo && $teacherId !== '' ? teacherActivitiesFind($pdo, $teacherId, $activityId) : null;
        }
        }
    }
}

$activities = $pdo && $teacherId !== '' ? teacherActivitiesRead($pdo, $teacherId, $search) : [];
if ($statusFilter !== '') {
    $activities = array_values(array_filter($activities, static function (array $activity) use ($statusFilter): bool {
        return $activity['status_key'] === $statusFilter;
    }));
}

$registrationRows = [];
if ($action === 'registrations' && $selectedActivity && $pdo && $teacherId !== '') {
    $registrationRows = teacherActivitiesRegistrations($pdo, $teacherId, $activityId);
}

$showForm = in_array($action, ['create', 'edit'], true);
$formHeading = $action === 'edit' ? 'Chỉnh sửa hoạt động' : 'Tạo hoạt động mới';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Quản lý hoạt động và sân chơi do giáo viên phụ trách trên TalentHub.">
    <title><?= teacherActivitiesEscape($pageTitle); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/teacher.css">
</head>
<body class="teacher-dashboard teacher-activities-page">
    <div class="teacher-layout">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require_once __DIR__ . '/../includes/header.php'; ?>

            <main class="teacher-body">
                <div class="teacher-container">
                    <section class="teacher-activities-heading">
                        <div>
                            <span class="teacher-welcome__tag">Quản lý giáo viên</span>
                            <h2 class="teacher-activities-heading__title">Hoạt động / Sân chơi của tôi</h2>
                            <p class="teacher-activities-heading__description">Theo dõi lịch hoạt động, số lượng đăng ký và vòng đời hoạt động.</p>
                        </div>
                        <a href="?action=create" class="btn btn-primary teacher-activities-create">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                            <span>Tạo hoạt động mới</span>
                        </a>
                    </section>

                    <?php if ($notice !== ''): ?>
                        <div class="teacher-activities-notice teacher-activities-notice--<?= teacherActivitiesEscape($noticeType); ?>" role="status">
                            <?= teacherActivitiesEscape($notice); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($errors): ?>
                        <div class="teacher-activities-notice teacher-activities-notice--error" id="teacher-activities-errors" role="alert" tabindex="-1" data-focus-on-load>
                            <strong id="teacher-activities-errors-title">Chưa thể lưu hoạt động.</strong>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= teacherActivitiesEscape($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($showForm): ?>
                        <section class="teacher-section-box teacher-activities-form-panel">
                            <div class="teacher-section-box__header">
                                <div>
                                    <h2 class="teacher-section-box__title"><?= teacherActivitiesEscape($formHeading); ?></h2>
                                    <p class="teacher-section-box__subtitle">Thông tin sử dụng các cột hoạt động hiện có trong database.</p>
                                </div>
                                <a href="index.php" class="btn btn-secondary btn-sm">Quay lại danh sách</a>
                            </div>

                            <form method="post" class="teacher-activities-form" aria-describedby="<?= $errors ? 'teacher-activities-errors teacher-activities-form-note' : 'teacher-activities-form-note'; ?>">
                                <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                <input type="hidden" name="form_action" value="<?= $action === 'edit' ? 'edit' : 'create'; ?>">
                                <?php if ($action === 'edit' && $activityId !== ''): ?>
                                    <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($activityId); ?>">
                                <?php endif; ?>

                                <div class="teacher-activities-form__grid">
                                    <label class="teacher-form-field teacher-form-field--wide" for="activity-title">
                                        <span>Tên hoạt động</span>
                                        <input id="activity-title" type="text" name="title" value="<?= teacherActivitiesEscape($formValues['title']); ?>" required<?= $errors ? ' aria-describedby="teacher-activities-errors"' : ''; ?>>
                                    </label>
                                    <label class="teacher-form-field" for="activity-category">
                                        <span>Nhóm hoạt động</span>
                                        <input id="activity-category" type="text" name="category" value="<?= teacherActivitiesEscape($formValues['category']); ?>" required<?= $errors ? ' aria-describedby="teacher-activities-errors"' : ''; ?>>
                                    </label>
                                    <label class="teacher-form-field" for="activity-capacity">
                                        <span>Sức chứa</span>
                                        <input id="activity-capacity" type="number" name="capacity" min="1" step="1" value="<?= teacherActivitiesEscape($formValues['capacity']); ?>" required<?= $errors ? ' aria-describedby="teacher-activities-errors"' : ''; ?>>
                                    </label>
                                    <label class="teacher-form-field" for="activity-start-at">
                                        <span>Bắt đầu</span>
                                        <input id="activity-start-at" type="datetime-local" name="startAt" value="<?= teacherActivitiesEscape($formValues['startAt']); ?>" required<?= $errors ? ' aria-describedby="teacher-activities-errors"' : ''; ?>>
                                    </label>
                                    <label class="teacher-form-field" for="activity-end-at">
                                        <span>Kết thúc</span>
                                        <input id="activity-end-at" type="datetime-local" name="endAt" value="<?= teacherActivitiesEscape($formValues['endAt']); ?>" required<?= $errors ? ' aria-describedby="teacher-activities-errors"' : ''; ?>>
                                    </label>
                                    <div class="teacher-form-field">
                                        <span>Trạng thái vòng đời</span>
                                        <strong><?= teacherActivitiesEscape($action === 'edit' ? ($selectedActivity['status_label'] ?? 'Không xác định') : 'Bản nháp'); ?></strong>
                                    </div>
                                </div>

                                <p class="teacher-activities-form__note" id="teacher-activities-form-note">Địa điểm chưa được lưu vì bảng <code>activities</code> hiện chưa có cột địa điểm.</p>
                                <div class="teacher-activities-form__actions">
                                    <a href="index.php" class="btn btn-secondary">Hủy</a>
                                    <button type="submit" class="btn btn-primary">Lưu hoạt động</button>
                                </div>
                            </form>
                        </section>
                    <?php elseif ($action === 'view' && $selectedActivity): ?>
                        <section class="teacher-section-box teacher-activity-detail-panel">
                            <div class="teacher-section-box__header">
                                <div>
                                    <span class="teacher-welcome__tag">Chi tiết hoạt động</span>
                                    <h2 class="teacher-section-box__title"><?= teacherActivitiesEscape($selectedActivity['title']); ?></h2>
                                </div>
                                <div class="teacher-activities-header-actions">
                                    <?php $detailLifecycleAction = teacherActivitiesLifecycleAction($selectedActivity); ?>
                                    <?php if ($detailLifecycleAction !== null): ?>
                                        <form method="post" class="teacher-activities-inline-form">
                                            <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                            <input type="hidden" name="form_action" value="advance_status">
                                            <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($selectedActivity['id']); ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm"><?= teacherActivitiesEscape($detailLifecycleAction['label']); ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="index.php" class="btn btn-secondary btn-sm">Quay lại danh sách</a>
                                </div>
                            </div>
                            <div class="teacher-activity-detail-grid">
                                <div><span>Trạng thái</span><strong><span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($selectedActivity['status_class']); ?>"><?= teacherActivitiesEscape($selectedActivity['status_label']); ?></span></strong></div>
                                <div><span>Thời gian</span><strong><?= teacherActivitiesEscape($selectedActivity['start_label']); ?> – <?= teacherActivitiesEscape($selectedActivity['end_label']); ?></strong></div>
                                <div><span>Địa điểm</span><strong><?= teacherActivitiesEscape($selectedActivity['locationName'] ?? 'Phòng Thực hành B305 - BTEC Cần Thơ'); ?></strong></div>
                                <div><span>Đăng ký</span><strong><?= teacherActivitiesEscape((string) $selectedActivity['registered_count']); ?> / <?= teacherActivitiesEscape((string) $selectedActivity['capacity']); ?></strong></div>
                                <div><span>Khả năng đăng ký</span><strong><span class="teacher-registration-pill teacher-registration-pill--<?= $selectedActivity['registration_available'] ? 'available' : 'unavailable'; ?>"><?= teacherActivitiesEscape($selectedActivity['registration_label']); ?></span></strong></div>
                                <div><span>Nhóm</span><strong><?= teacherActivitiesEscape($selectedActivity['category']); ?></strong></div>
                            </div>
                        </section>
                    <?php elseif ($action === 'registrations' && $selectedActivity): ?>
                        <section class="teacher-section-box teacher-registrations-panel">
                            <div class="teacher-section-box__header">
                                <div>
                                    <span class="teacher-welcome__tag">Danh sách đăng ký</span>
                                    <h2 class="teacher-section-box__title"><?= teacherActivitiesEscape($selectedActivity['title']); ?></h2>
                                </div>
                                <a href="index.php" class="btn btn-secondary btn-sm">Quay lại danh sách</a>
                            </div>
                            <?php if (!$registrationRows): ?>
                                <div class="teacher-empty-state">
                                    <div class="teacher-empty-state__icon" aria-hidden="true">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <path d="M16 11a4 4 0 0 1 0 8"></path>
                                        </svg>
                                    </div>
                                    <h3 class="teacher-empty-state__title">Chưa có người đăng ký</h3>
                                    <p class="teacher-empty-state__desc">Danh sách sẽ hiển thị khi học viên đăng ký hoạt động này.</p>
                                </div>
                            <?php else: ?>
                                <div class="teacher-activities-table-wrap">
                                    <table class="teacher-activities-table teacher-registrations-table">
                                        <thead><tr><th>Học viên</th><th>Email</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($registrationRows as $registration): ?>
                                                <tr>
                                                    <td data-label="Học viên"><?= teacherActivitiesEscape($registration['student_name'] ?: 'Học viên'); ?></td>
                                                    <td data-label="Email"><?= teacherActivitiesEscape($registration['student_email'] ?: 'Chưa có email'); ?></td>
                                                    <td data-label="Trạng thái"><span class="teacher-registration-pill teacher-registration-pill--status"><?= teacherActivitiesEscape($registration['status'] ?: 'Đã đăng ký'); ?></span></td>
                                                    <td data-label="Thao tác">
                                                        <?php if (($registration['status'] ?? '') === 'pending'): ?>
                                                            <div class="teacher-activities-row-actions">
                                                                <form method="post" class="teacher-activities-inline-form">
                                                                    <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                                                    <input type="hidden" name="form_action" value="registration_transition">
                                                                    <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($selectedActivity['id']); ?>">
                                                                    <input type="hidden" name="registration_id" value="<?= teacherActivitiesEscape($registration['id']); ?>">
                                                                    <input type="hidden" name="expected_status" value="pending">
                                                                    <button type="submit" name="registration_action" value="approve" class="teacher-activity-action teacher-activity-action--button">Duyệt</button>
                                                                </form>
                                                                <form method="post" class="teacher-activities-inline-form">
                                                                    <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                                                    <input type="hidden" name="form_action" value="registration_transition">
                                                                    <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($selectedActivity['id']); ?>">
                                                                    <input type="hidden" name="registration_id" value="<?= teacherActivitiesEscape($registration['id']); ?>">
                                                                    <input type="hidden" name="expected_status" value="pending">
                                                                    <button type="submit" name="registration_action" value="reject" class="teacher-activity-action teacher-activity-action--button">Từ chối</button>
                                                                </form>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="teacher-text-muted">Đã xử lý</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>
                    <?php elseif ($action !== '' && $activityId !== ''): ?>
                        <div class="teacher-activities-notice teacher-activities-notice--error" role="alert">Không tìm thấy hoạt động thuộc hồ sơ giáo viên này.</div>
                    <?php endif; ?>

                    <section class="teacher-section-box teacher-activities-list-panel">
                        <div class="teacher-section-box__header">
                            <div>
                                <h2 class="teacher-section-box__title">Danh sách hoạt động</h2>
                                <p class="teacher-section-box__subtitle">Hiển thị <?= teacherActivitiesEscape((string) count($activities)); ?> hoạt động theo bộ lọc hiện tại.</p>
                            </div>
                        </div>

                        <form method="get" class="teacher-activities-toolbar">
                            <label class="teacher-activities-search">
                                <span class="teacher-activities-search__icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                </span>
                                <span class="sr-only">Tìm kiếm theo tên hoạt động</span>
                                <input type="search" name="q" value="<?= teacherActivitiesEscape($search); ?>" placeholder="Tìm theo tên hoạt động...">
                            </label>
                            <label class="teacher-activities-filter">
                                <span class="sr-only">Lọc theo trạng thái</span>
                                <select name="status">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : ''; ?>>Bản nháp</option>
                                    <option value="published" <?= $statusFilter === 'published' ? 'selected' : ''; ?>>Đã công bố</option>
                                    <option value="ongoing" <?= $statusFilter === 'ongoing' ? 'selected' : ''; ?>>Đang diễn ra</option>
                                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : ''; ?>>Đã hoàn tất</option>
                                    <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : ''; ?>>Đã lưu trữ</option>
                                </select>
                            </label>
                            <button type="submit" class="btn btn-secondary btn-sm">Lọc</button>
                            <?php if ($search !== '' || $statusFilter !== ''): ?>
                                <a href="index.php" class="teacher-activities-reset">Xóa lọc</a>
                            <?php endif; ?>
                        </form>

                        <?php if (!$activities): ?>
                            <div class="teacher-empty-state teacher-activities-empty">
                                <div class="teacher-empty-state__icon" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M6 3h12v18H6z"></path>
                                        <path d="M9 7h6M9 11h6M9 15h4"></path>
                                    </svg>
                                </div>
                                <h3 class="teacher-empty-state__title">Chưa có hoạt động phù hợp</h3>
                                <p class="teacher-empty-state__desc">Hoạt động do giáo viên phụ trách sẽ xuất hiện ở đây khi có dữ liệu trong database.</p>
                                <a href="?action=create" class="btn btn-secondary btn-sm teacher-empty-state__action">Tạo hoạt động mới</a>
                            </div>
                        <?php else: ?>
                            <div class="teacher-activities-table-wrap">
                                <table class="teacher-activities-table">
                                    <thead>
                                        <tr>
                                            <th>Hoạt động</th>
                                            <th>Thời gian</th>
                                            <th>Địa điểm</th>
                                            <th>Đăng ký / sức chứa</th>
                                            <th>Trạng thái</th>
                                            <th class="teacher-activities-table__actions-heading">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activities as $activity): ?>
                                            <?php $rowLifecycleAction = teacherActivitiesLifecycleAction($activity); ?>
                                            <tr>
                                                <td data-label="Hoạt động">
                                                    <a href="?action=view&amp;id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-title"><?= teacherActivitiesEscape($activity['title']); ?></a>
                                                    <span class="teacher-activity-category"><?= teacherActivitiesEscape($activity['category']); ?></span>
                                                </td>
                                                <td data-label="Thời gian">
                                                    <span class="teacher-activity-time"><?= teacherActivitiesEscape($activity['start_label']); ?></span>
                                                    <span class="teacher-activity-time teacher-text-muted">đến <?= teacherActivitiesEscape($activity['end_label']); ?></span>
                                                </td>
                                                <td data-label="Địa điểm"><span><?= teacherActivitiesEscape($activity['locationName'] ?? 'Phòng Thực hành B305 - BTEC Cần Thơ'); ?></span></td>
                                                <td data-label="Đăng ký"><strong><?= teacherActivitiesEscape((string) $activity['registered_count']); ?> / <?= teacherActivitiesEscape((string) $activity['capacity']); ?></strong></td>
                                                <td data-label="Trạng thái">
                                                    <span class="teacher-status-pill teacher-status-pill--<?= teacherActivitiesEscape($activity['status_class']); ?>"><?= teacherActivitiesEscape($activity['status_label']); ?></span>
                                                    <span class="teacher-registration-pill teacher-registration-pill--<?= $activity['registration_available'] ? 'available' : 'unavailable'; ?>"><?= teacherActivitiesEscape($activity['registration_label']); ?></span>
                                                </td>
                                                <td data-label="Thao tác">
                                                    <div class="teacher-activities-row-actions">
                                                        <a href="?action=view&amp;id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-action">Chi tiết</a>
                                                        <a href="?action=edit&amp;id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-action">Chỉnh sửa</a>
                                                        <a href="?action=registrations&amp;id=<?= teacherActivitiesEscape($activity['id']); ?>" class="teacher-activity-action">Đăng ký</a>
                                                        <?php if ($rowLifecycleAction !== null): ?>
                                                            <form method="post" class="teacher-activities-inline-form">
                                                                <input type="hidden" name="csrfToken" value="<?= teacherActivitiesEscape($csrfToken); ?>">
                                                                <input type="hidden" name="form_action" value="advance_status">
                                                                <input type="hidden" name="activity_id" value="<?= teacherActivitiesEscape($activity['id']); ?>">
                                                                <button type="submit" class="teacher-activity-action teacher-activity-action--button"><?= teacherActivitiesEscape($rowLifecycleAction['label']); ?></button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <div class="teacher-toast" id="teacher-toast" aria-live="polite" aria-atomic="true">
        <div class="teacher-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span class="teacher-toast__message">Tính năng đang được phát triển.</span>
        </div>
    </div>

    <script src="../../../assets/js/teacher.js"></script>
</body>
</html>
