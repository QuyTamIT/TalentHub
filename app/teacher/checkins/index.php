<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherRepository;
use TalentHub\Modules\Teacher\Repository\TeacherQrSessionRepository;
use TalentHub\Modules\Teacher\Service\TeacherQrSessionService;
use TalentHub\Rbac\Service\PermissionService;

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!function_exists('teacherQrEscape')) {
    function teacherQrEscape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('teacherQrInitials')) {
    function teacherQrInitials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'GV';
        }

        $cleanName = preg_replace('/^(Thầy|Cô|Gv\.|GV|Ths\.|TS\.|ThS\.)\s+/iu', '', $name);
        $cleanName = trim((string)$cleanName) ?: $name;

        $parts = preg_split('/\s+/u', $cleanName) ?: [];
        if (count($parts) === 1) {
            return mb_strtoupper(mb_substr($parts[0], 0, min(2, mb_strlen($parts[0]))));
        }
        $first = (string) ($parts[0] ?? '');
        $last = (string) ($parts[count($parts) - 1] ?? '');

        return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)) ?: 'GV';
    }
}

use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Rbac\RoleCodes;

$user = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/checkins/index.php');
$session = new SessionManager(array_merge(require dirname(__DIR__, 3) . '/config/session.php', ['name' => SessionManager::SESSION_TEACHER]));
$session->start();
$storedFlash = $_SESSION['teacherQrFlash'] ?? null;
unset($_SESSION['teacherQrFlash']);

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$pageTitle = 'Điểm danh QR';
$currentRoute = 'checkins';
$teacherSidebarHomeHref = app_href('/app/teacher/index.php');
$teacherSidebarRoleHref = app_href('/role-selection.php');
$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Sân chơi của tôi', 'route' => 'playgrounds', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm', 'route' => 'assessments', 'icon' => 'clipboard-check', 'active' => false],
    ['title' => 'Học viên', 'route' => 'students', 'icon' => 'users', 'active' => false],
];

$csrfToken = $session->csrfToken();
$service = null;
$data = ['activities' => [], 'sessions' => [], 'managedCheckins' => []];
$errors = [];
$bootError = null;
$formValues = [
    'activity_id' => is_string($_GET['activity_id'] ?? null) ? $_GET['activity_id'] : '',
    'duration_minutes' => (string) TeacherQrSessionService::DEFAULT_DURATION_MINUTES,
    'max_scans' => (string) TeacherQrSessionService::DEFAULT_MAX_SCANS,
    'confirmed_hours' => TeacherQrSessionService::DEFAULT_CONFIRMED_HOURS,
];
$oneTimeToken = null;
$flash = is_array($storedFlash) ? $storedFlash : null;
if ($flash !== null && isset($flash['rawToken'])) {
    $oneTimeToken = (string) $flash['rawToken'];
}

try {
    $pdo = (new Connection(require dirname(__DIR__, 3) . '/config/database.php'))->connect();
    $permissions = new PermissionService($pdo);
    $permissions->require((string) $user['id'], 'qr_session.read_managed');
    $permissions->require((string) $user['id'], 'checkin.read_managed');
    $service = new TeacherQrSessionService(new TeacherQrSessionRepository($pdo));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawActivityId = $_POST['activity_id'] ?? null;
        $rawDurationMinutes = $_POST['duration_minutes'] ?? null;
        $rawMaxScans = $_POST['max_scans'] ?? null;
        $rawConfirmedHours = $_POST['confirmed_hours'] ?? null;
        $formValues = [
            'activity_id' => is_string($rawActivityId) ? $rawActivityId : '',
            'duration_minutes' => is_string($rawDurationMinutes) ? $rawDurationMinutes : '',
            'max_scans' => is_string($rawMaxScans) ? $rawMaxScans : '',
            'confirmed_hours' => is_string($rawConfirmedHours) ? $rawConfirmedHours : '',
        ];

        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $formAction = trim((string) ($_POST['form_action'] ?? ''));

        if ($formAction === 'create_session') {
            $permissions->require((string) $user['id'], 'qr_session.create_managed');
            $result = $service->create(
                (string) $user['id'],
                $rawActivityId,
                $rawDurationMinutes,
                $rawMaxScans,
                $rawConfirmedHours,
            );

            $_SESSION['teacherQrFlash'] = [
                'type' => 'success',
                'message' => 'Đã tạo phiên QR. Mã QR và token chỉ hiển thị một lần trên trang tiếp theo.',
                'sessionId' => $result['sessionId'],
                'rawToken' => $result['rawToken'],
            ];
            header('Location: ' . app_href('/app/teacher/checkins/index.php'));
            exit;
        }

        if ($formAction === 'revoke_session') {
            $permissions->require((string) $user['id'], 'qr_session.revoke_managed');
            $service->revoke((string) $user['id'], $_POST['session_id'] ?? null);

            $_SESSION['teacherQrFlash'] = [
                'type' => 'success',
                'message' => 'Đã thu hồi phiên QR.',
            ];
            header('Location: ' . app_href('/app/teacher/checkins/index.php'));
            exit;
        }

        throw new ApiException(422, 'INVALID_ACTION', 'Thao tác QR không hợp lệ.');
    }

    $data = $service->pageData((string) $user['id']);
} catch (ApiException $exception) {
    $errors[] = $exception->getMessage();
} catch (Throwable) {
    $bootError = 'Chưa thể tải dữ liệu phiên QR. Vui lòng kiểm tra kết nối và trạng thái database.';
}

$teacher = [];
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $teacher = (new TeacherRepository($pdo))->findByUserId((string) $user['id']) ?? [];
    } catch (Throwable) {
        $teacher = [];
    }
}
$rawName = $_SESSION['user']['fullName'] ?? ($_SESSION['user']['full_name'] ?? ($_SESSION['user_name'] ?? ''));
$teacherName = trim((string) ($rawName !== '' ? $rawName : ($teacher['fullName'] ?? ($user['fullName'] ?? 'Giáo viên'))));
if ($teacherName === 'minh triet') {
    $teacherName = 'Minh Triết';
}
$teacherInfo = [
    'full_name' => $teacherName !== '' ? $teacherName : 'Giáo viên',
    'role_label' => 'Giáo viên / Hướng dẫn viên',
    'avatar_initials' => teacherQrInitials($teacherName),
    'notification_count' => 0,
];

$statusLabels = [
    'active' => 'Đang hoạt động',
    'expired' => 'Đã hết hạn',
    'revoked' => 'Đã thu hồi',
];
$statusClasses = [
    'active' => 'success',
    'expired' => 'muted',
    'revoked' => 'danger',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Quản lý phiên QR cho các hoạt động đang diễn ra do giáo viên phụ trách trên TalentHub.">
    <title><?= teacherQrEscape($pageTitle); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/teacher.css">
</head>
<body class="teacher-dashboard teacher-qr-page">
    <div class="teacher-layout">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

            <main class="teacher-body">
                <div class="teacher-container teacher-qr-container">
                    <section class="teacher-section-box teacher-qr-intro">
                        <div>
                            <span class="teacher-section-box__eyebrow">QUẢN LÝ PHIÊN QR</span>
                            <h2 class="teacher-qr-intro__title">Điểm danh QR cho hoạt động đang diễn ra</h2>
                            <p class="teacher-qr-intro__description">Tạo mã QR tạm thời cho hoạt động do bạn phụ trách, theo dõi trạng thái và thu hồi khi cần.</p>
                        </div>
                        <span class="teacher-chip teacher-chip--primary">Phạm vi: hoạt động của tôi</span>
                    </section>

                    <?php if ($flash !== null): ?>
                        <div class="teacher-qr-flash teacher-qr-flash--<?= teacherQrEscape((string) ($flash['type'] ?? 'success')); ?>" role="status">
                            <?= teacherQrEscape((string) ($flash['message'] ?? 'Đã cập nhật phiên QR.')); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($errors !== [] || $bootError !== null): ?>
                        <div class="teacher-qr-flash teacher-qr-flash--error" role="alert" tabindex="-1">
                            <?php foreach ($errors as $error): ?>
                                <div><?= teacherQrEscape($error); ?></div>
                            <?php endforeach; ?>
                            <?php if ($bootError !== null): ?>
                                <div><?= teacherQrEscape($bootError); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($oneTimeToken !== null && $oneTimeToken !== ''): ?>
                        <section class="teacher-section-box teacher-qr-reveal" aria-labelledby="teacher-qr-reveal-title">
                            <div class="teacher-qr-reveal__copy">
                                <span class="teacher-section-box__eyebrow">HIỂN THỊ MỘT LẦN</span>
                                <h2 id="teacher-qr-reveal-title" class="teacher-section-box__title">QR đã sẵn sàng để chia sẻ</h2>
                                <p class="teacher-section-box__subtitle">Hãy mở mã QR cho học viên trong thời gian hiệu lực. Token thô sẽ không được hiển thị lại sau lần tải trang này.</p>
                                <div class="teacher-qr-token-block">
                                    <span class="teacher-qr-token-block__label">Token một lần</span>
                                    <code class="teacher-qr-token" id="teacher-qr-token-value"><?= teacherQrEscape($oneTimeToken); ?></code>
                                    <button type="button" class="btn btn-secondary teacher-qr-copy" data-copy-qr-token data-token="<?= teacherQrEscape($oneTimeToken); ?>">Sao chép token</button>
                                    <span class="teacher-qr-copy-status" data-copy-status aria-live="polite"></span>
                                </div>
                            </div>
                            <div class="teacher-qr-code-shell">
                                <div class="teacher-qr-code" id="teacher-qr-code" data-qr-token="<?= teacherQrEscape($oneTimeToken); ?>" role="img" aria-label="Mã QR của phiên vừa tạo"></div>
                                <p class="teacher-qr-code-shell__hint">Nội dung QR là token opaque, không phải đường dẫn.</p>
                            </div>
                        </section>
                    <?php endif; ?>

                    <section class="teacher-section-box teacher-qr-create-panel" aria-labelledby="teacher-qr-create-title">
                        <div class="teacher-section-box__header">
                            <div>
                                <h2 id="teacher-qr-create-title" class="teacher-section-box__title">Tạo phiên QR mới</h2>
                                <p class="teacher-section-box__subtitle">Chỉ hoạt động có trạng thái “Đang diễn ra” và thuộc phạm vi quản lý của bạn mới được chọn.</p>
                            </div>
                        </div>

                        <form method="post" class="teacher-qr-form">
                            <input type="hidden" name="csrfToken" value="<?= teacherQrEscape($csrfToken); ?>">
                            <input type="hidden" name="form_action" value="create_session">
                            <div class="teacher-qr-form__grid">
                                <label class="teacher-form-field teacher-qr-form__activity" for="teacher-qr-activity">
                                    <span>Hoạt động</span>
                                    <select id="teacher-qr-activity" name="activity_id" required>
                                        <option value="">Chọn hoạt động đang diễn ra</option>
                                        <?php foreach ($data['activities'] as $activity): ?>
                                            <option value="<?= teacherQrEscape($activity['id']); ?>" <?= $formValues['activity_id'] === (string) $activity['id'] ? 'selected' : ''; ?>>
                                                <?= teacherQrEscape($activity['title']); ?><?= !empty($activity['category']) ? ' · ' . teacherQrEscape($activity['category']) : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="teacher-form-field" for="teacher-qr-duration">
                                    <span>Thời hạn (phút)</span>
                                    <input id="teacher-qr-duration" type="number" name="duration_minutes" min="1" max="120" step="1" value="<?= teacherQrEscape($formValues['duration_minutes']); ?>" required>
                                    <small>Cho phép từ 1 đến 120 phút.</small>
                                </label>
                                <label class="teacher-form-field" for="teacher-qr-max-scans">
                                    <span>Giới hạn lượt quét</span>
                                    <input id="teacher-qr-max-scans" type="number" name="max_scans" min="1" max="10000" step="1" value="<?= teacherQrEscape($formValues['max_scans']); ?>" required>
                                    <small>Số nguyên dương, tối đa 10.000 lượt.</small>
                                </label>
                                <label class="teacher-form-field" for="teacher-qr-confirmed-hours">
                                    <span>Giờ trải nghiệm xác nhận</span>
                                    <input id="teacher-qr-confirmed-hours" type="number" name="confirmed_hours" min="0" max="24" step="0.25" value="<?= teacherQrEscape($formValues['confirmed_hours']); ?>" required>
                                    <small>Áp dụng cho các lượt check-in hợp lệ của hoạt động này.</small>
                                </label>
                            </div>
                            <div class="teacher-qr-form__actions">
                                <p class="teacher-qr-form__note">Mã QR là token opaque tạm thời. Giờ trải nghiệm được lưu theo chính sách của hoạt động và dùng cho check-in tương lai.</p>
                                <button type="submit" class="btn btn-primary">Tạo phiên QR</button>
                            </div>
                        </form>
                    </section>

                    <section class="teacher-section-box teacher-qr-list-panel" aria-labelledby="teacher-qr-list-title">
                        <div class="teacher-section-box__header">
                            <div>
                                <h2 id="teacher-qr-list-title" class="teacher-section-box__title">Các phiên QR đã tạo</h2>
                                <p class="teacher-section-box__subtitle">Trạng thái “Đã hết hạn” được suy ra khi tải trang; GET không cập nhật database.</p>
                            </div>
                            <span class="teacher-section-box__count"><?= number_format(count($data['sessions'])); ?> phiên</span>
                        </div>

                        <?php if ($data['sessions'] === []): ?>
                            <div class="teacher-qr-empty">
                                <div class="teacher-qr-empty__icon" aria-hidden="true">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="7" height="7"></rect>
                                        <rect x="14" y="3" width="7" height="7"></rect>
                                        <rect x="3" y="14" width="7" height="7"></rect>
                                        <path d="M14 14h3v3h-3zM20 14v7M14 20h3"></path>
                                    </svg>
                                </div>
                                <h3>Chưa có phiên QR</h3>
                                <p>Các phiên được tạo cho hoạt động đang diễn ra sẽ xuất hiện tại đây.</p>
                            </div>
                        <?php else: ?>
                            <div class="teacher-qr-table-wrap">
                                <table class="teacher-qr-table">
                                    <thead>
                                        <tr>
                                            <th>Hoạt động</th>
                                            <th>Trạng thái</th>
                                            <th>Hết hạn lúc</th>
                                            <th>Lượt quét</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data['sessions'] as $qrSession): ?>
                                            <?php $status = (string) $qrSession['status']; ?>
                                            <tr>
                                                <td data-label="Hoạt động">
                                                    <strong><?= teacherQrEscape($qrSession['activityTitle']); ?></strong>
                                                    <?php if ($qrSession['activityCategory'] !== ''): ?>
                                                        <span><?= teacherQrEscape($qrSession['activityCategory']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Trạng thái">
                                                    <span class="teacher-status-pill teacher-status-pill--<?= teacherQrEscape($statusClasses[$status] ?? 'muted'); ?>">
                                                        <?= teacherQrEscape($statusLabels[$status] ?? 'Không xác định'); ?>
                                                    </span>
                                                </td>
                                                <td data-label="Hết hạn lúc">
                                                    <?= teacherQrEscape($qrSession['expiresAt'] ?? 'Không xác định'); ?>
                                                </td>
                                                <td data-label="Lượt quét">
                                                    <strong><?= number_format((int) $qrSession['usedScans']); ?></strong> / <?= number_format((int) $qrSession['maxScans']); ?>
                                                    <span class="teacher-text-muted"> · <?= teacherQrEscape((string) ($qrSession['confirmedHours'] ?? '0.00')); ?>h</span>
                                                </td>
                                                <td data-label="Thao tác">
                                                    <?php if ($status === 'active'): ?>
                                                        <form method="post" class="teacher-qr-revoke-form">
                                                            <input type="hidden" name="csrfToken" value="<?= teacherQrEscape($csrfToken); ?>">
                                                            <input type="hidden" name="form_action" value="revoke_session">
                                                            <input type="hidden" name="session_id" value="<?= teacherQrEscape($qrSession['id']); ?>">
                                                            <button type="submit" class="teacher-qr-revoke-button">Thu hồi</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="teacher-text-muted">Không còn thao tác</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="teacher-section-box teacher-qr-managed-panel" aria-labelledby="teacher-managed-checkins-title">
                        <div class="teacher-section-box__header">
                            <div>
                                <h2 id="teacher-managed-checkins-title" class="teacher-section-box__title">Lượt check-in đã xác nhận</h2>
                                <p class="teacher-section-box__subtitle">Chỉ hiển thị lượt check-in thuộc hoạt động do bạn quản lý.</p>
                            </div>
                            <span class="teacher-section-box__count"><?= number_format(count($data['managedCheckins'] ?? [])); ?> lượt</span>
                        </div>
                        <?php if (($data['managedCheckins'] ?? []) === []): ?>
                            <div class="teacher-qr-empty"><h3>Chưa có lượt check-in</h3><p>Lượt check-in hợp lệ sẽ xuất hiện sau khi học viên quét QR.</p></div>
                        <?php else: ?>
                            <div class="teacher-qr-table-wrap">
                                <table class="teacher-qr-table">
                                    <thead><tr><th>Hoạt động</th><th>Trạng thái</th><th>Thời gian</th><th>Giờ xác nhận</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($data['managedCheckins'] as $managedCheckin): ?>
                                        <tr>
                                            <td data-label="Hoạt động"><strong><?= teacherQrEscape($managedCheckin['activityTitle']); ?></strong></td>
                                            <td data-label="Trạng thái"><?= teacherQrEscape($managedCheckin['status']); ?></td>
                                            <td data-label="Thời gian"><?= teacherQrEscape($managedCheckin['checkedInAt']); ?></td>
                                            <td data-label="Giờ xác nhận"><strong><?= teacherQrEscape($managedCheckin['confirmedHours']); ?>h</strong></td>
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

    <script src="../../../assets/js/teacher.js"></script>
    <?php if ($oneTimeToken !== null && $oneTimeToken !== ''): ?>
        <script src="../../../assets/vendor/qrcodejs/qrcode.min.js"></script>
    <?php endif; ?>
    <script src="../../../assets/js/teacher-qr.js"></script>
</body>
</html>
