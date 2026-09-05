<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Contact\Repository\ConsultationRequestRepository;
use TalentHub\Modules\Contact\Service\ConsultationAdminAuthorization;
use TalentHub\Rbac\RoleCodes;
use TalentHub\Support\Uuid;

$sessionConfig = require dirname(__DIR__, 2) . '/config/session.php';
$sessionConfig['name'] = SessionManager::sessionNameForRole(RoleCodes::PLATFORM_ADMIN);
$session = new SessionManager($sessionConfig);
$session->start();
$adminUser = $session->user();

if ($adminUser === null) {
    $next = urlencode((string) ($_SERVER['REQUEST_URI'] ?? app_href('/app/admin/consultations.php')));
    header('Location: ' . app_href('/login.php?next=' . $next . '&role_required=platform_admin'), true, 302);
    exit;
}
if (!RoleCodes::matches((string) ($adminUser['role'] ?? ''), RoleCodes::PLATFORM_ADMIN)) {
    http_response_code(403);
    echo 'Bạn không có quyền truy cập trang này.';
    exit;
}

/** @param mixed $value */
function consultation_admin_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function consultation_admin_redirect(string $id = ''): never
{
    $path = '/app/admin/consultations.php' . ($id !== '' ? '?id=' . rawurlencode($id) : '');
    header('Location: ' . app_href($path), true, 303);
    exit;
}

function consultation_admin_error_message(Throwable $exception): string
{
    $safeMessages = [
        'Bạn không có quyền truy cập yêu cầu tư vấn.',
        'Phiên bảo mật đã hết hạn. Vui lòng thử lại.',
        'Biểu mẫu đã được xử lý hoặc hết hạn.',
        'Mã yêu cầu không hợp lệ.',
        'Không tìm thấy yêu cầu tư vấn.',
        'Trạng thái chỉ được cập nhật theo đúng thứ tự xử lý.',
    ];
    $message = $exception->getMessage();

    if (($exception instanceof ApiException || $exception instanceof RuntimeException)
        && in_array($message, $safeMessages, true)) {
        return $message;
    }

    return 'Không thể cập nhật yêu cầu lúc này. Vui lòng thử lại.';
}

$pdo = (new Connection(require dirname(__DIR__, 2) . '/config/database.php'))->connect();
$authorization = new ConsultationAdminAuthorization($pdo);
$repository = new ConsultationRequestRepository($pdo);

try {
    $authorization->require((string) $adminUser['id'], 'admin.consultation.read');
} catch (ApiException) {
    http_response_code(403);
    echo 'Bạn không có quyền xem yêu cầu tư vấn.';
    exit;
}

if (!isset($_SESSION['admin_consultation_form_token'])) {
    $_SESSION['admin_consultation_form_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $requestId = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
    $csrf = is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : '';
    $formToken = is_string($_POST['formToken'] ?? null) ? $_POST['formToken'] : '';
    try {
        $authorization->require((string) $adminUser['id'], 'admin.consultation.update');
        if ($csrf === '' || !hash_equals($session->csrfToken(), $csrf)) {
            throw new RuntimeException('Phiên bảo mật đã hết hạn. Vui lòng thử lại.');
        }
        if (!hash_equals((string) $_SESSION['admin_consultation_form_token'], $formToken)) {
            throw new RuntimeException('Biểu mẫu đã được xử lý hoặc hết hạn.');
        }
        if (!Uuid::isValid($requestId)) {
            throw new RuntimeException('Mã yêu cầu không hợp lệ.');
        }
        $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
        $repository->updateStatus($requestId, $status, (string) $adminUser['id']);
        $_SESSION['admin_consultation_form_token'] = bin2hex(random_bytes(32));
        $_SESSION['admin_consultation_flash'] = ['success' => 'Đã cập nhật trạng thái yêu cầu.'];
    } catch (Throwable $exception) {
        $safeMessage = consultation_admin_error_message($exception);
        if ($safeMessage === 'Không thể cập nhật yêu cầu lúc này. Vui lòng thử lại.') {
            error_log('Consultation admin status update failed [consultation-status-update].');
        }
        $_SESSION['admin_consultation_flash'] = ['error' => $safeMessage];
    }
    consultation_admin_redirect($requestId);
}

$statusFilter = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
if (!in_array($statusFilter, ['', 'new', 'in_progress', 'completed'], true)) {
    $statusFilter = '';
}
$selectedId = is_string($_GET['id'] ?? null) && Uuid::isValid($_GET['id']) ? $_GET['id'] : '';
$flash = is_array($_SESSION['admin_consultation_flash'] ?? null) ? $_SESSION['admin_consultation_flash'] : [];
unset($_SESSION['admin_consultation_flash']);
$loadError = '';
$requests = [];
$selected = null;
try {
    $requests = $repository->list($statusFilter);
    $selected = $selectedId !== '' ? $repository->find($selectedId) : null;
} catch (Throwable) {
    $loadError = 'Chưa thể tải dữ liệu tư vấn. Hãy kiểm tra migration trước khi sử dụng trang này.';
}

$statusLabels = ['new' => 'Mới tiếp nhận', 'in_progress' => 'Đang xử lý', 'completed' => 'Đã hoàn thành'];
$audienceLabels = ['student' => 'Học sinh/Sinh viên', 'teacher' => 'Giáo viên', 'school' => 'Nhà trường', 'enterprise' => 'Doanh nghiệp', 'other' => 'Khác'];
$nextStatus = ['new' => 'in_progress', 'in_progress' => 'completed'];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Yêu cầu tư vấn | TalentHub Admin</title>
    <link rel="icon" href="<?= consultation_admin_escape(app_href('/assets/images/logo.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= consultation_admin_escape(app_href('/assets/css/admin-consultations.css')) ?>">
</head>
<body>
<header class="admin-contact-header">
    <div>
        <a href="<?= consultation_admin_escape(app_href('/app/admin/index.php')) ?>" class="admin-brand">Talent<span>Hub</span> <small>Admin</small></a>
        <p>Tiếp nhận yêu cầu tư vấn</p>
    </div>
    <a class="back-link" href="<?= consultation_admin_escape(app_href('/app/admin/index.php')) ?>">← Về Trung tâm vận hành</a>
</header>

<main class="admin-contact-main">
    <section class="admin-contact-title">
        <div><p class="eyebrow">Hộp thư tư vấn</p><h1>Yêu cầu cần tiếp nhận</h1></div>
        <form method="get" class="filter-form">
            <label for="status">Trạng thái</label>
            <select id="status" name="status" onchange="this.form.submit()">
                <option value="">Tất cả</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= consultation_admin_escape($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= consultation_admin_escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </section>

    <?php if (isset($flash['success'])): ?><div class="notice notice--success" role="status"><?= consultation_admin_escape($flash['success']) ?></div><?php endif; ?>
    <?php if (isset($flash['error'])): ?><div class="notice notice--error" role="alert"><?= consultation_admin_escape($flash['error']) ?></div><?php endif; ?>
    <?php if ($loadError !== ''): ?><div class="notice notice--error" role="alert"><?= consultation_admin_escape($loadError) ?></div><?php endif; ?>

    <div class="admin-contact-grid">
        <section class="request-list" aria-label="Danh sách yêu cầu tư vấn">
            <?php if ($requests === []): ?>
                <div class="empty-state">Chưa có yêu cầu phù hợp.</div>
            <?php endif; ?>
            <?php foreach ($requests as $request): ?>
                <a class="request-item <?= $selectedId === $request['id'] ? 'is-selected' : '' ?>" href="?id=<?= consultation_admin_escape($request['id']) ?><?= $statusFilter !== '' ? '&amp;status=' . consultation_admin_escape($statusFilter) : '' ?>">
                    <div class="request-item__top"><strong><?= consultation_admin_escape($request['fullName']) ?></strong><span class="status status--<?= consultation_admin_escape($request['status']) ?>"><?= consultation_admin_escape($statusLabels[$request['status']] ?? $request['status']) ?></span></div>
                    <p><?= consultation_admin_escape($audienceLabels[$request['audience']] ?? $request['audience']) ?> · <?= consultation_admin_escape($request['email']) ?></p>
                    <time datetime="<?= consultation_admin_escape($request['createdAt']) ?>"><?= consultation_admin_escape($request['createdAt']) ?></time>
                </a>
            <?php endforeach; ?>
        </section>

        <section class="request-detail" aria-label="Chi tiết yêu cầu tư vấn">
            <?php if ($selected === null): ?>
                <div class="empty-state">Chọn một yêu cầu để xem nội dung.</div>
            <?php else: ?>
                <div class="detail-heading">
                    <div><p class="eyebrow">Chi tiết yêu cầu</p><h2><?= consultation_admin_escape($selected['fullName']) ?></h2></div>
                    <span class="status status--<?= consultation_admin_escape($selected['status']) ?>"><?= consultation_admin_escape($statusLabels[$selected['status']] ?? $selected['status']) ?></span>
                </div>
                <dl class="detail-meta">
                    <div><dt>Đối tượng</dt><dd><?= consultation_admin_escape($audienceLabels[$selected['audience']] ?? $selected['audience']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= consultation_admin_escape($selected['email']) ?></dd></div>
                    <div><dt>Số điện thoại</dt><dd><?= consultation_admin_escape($selected['phone'] ?: 'Không cung cấp') ?></dd></div>
                    <div><dt>Ngày gửi</dt><dd><?= consultation_admin_escape($selected['createdAt']) ?></dd></div>
                </dl>
                <div class="message-box"><h3>Nội dung cần tư vấn</h3><p><?= nl2br(consultation_admin_escape($selected['message']), false) ?></p></div>
                <?php if (isset($nextStatus[$selected['status']])): ?>
                    <form method="post" class="status-form">
                        <input type="hidden" name="_csrf" value="<?= consultation_admin_escape($session->csrfToken()) ?>">
                        <input type="hidden" name="formToken" value="<?= consultation_admin_escape($_SESSION['admin_consultation_form_token']) ?>">
                        <input type="hidden" name="id" value="<?= consultation_admin_escape($selected['id']) ?>">
                        <input type="hidden" name="status" value="<?= consultation_admin_escape($nextStatus[$selected['status']]) ?>">
                        <button type="submit">Chuyển sang “<?= consultation_admin_escape($statusLabels[$nextStatus[$selected['status']]]) ?>”</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
