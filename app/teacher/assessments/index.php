<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Database\Exception\DatabaseConnectionException;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Exception\TeacherGradingConflictException;
use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Modules\Teacher\Service\TeacherGradingService;
use TalentHub\Rbac\Service\PermissionService;
use TalentHub\Support\Id\RequestId;

use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Rbac\RoleCodes;

date_default_timezone_set('Asia/Ho_Chi_Minh');

require __DIR__ . '/page-state.php';

function teacherGradingLogUnexpected(string $stage, Throwable $exception, string $requestId): void
{
    $environment = strtolower((string) ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));
    if (!in_array($environment, ['local', 'test'], true)) {
        return;
    }

    $file = str_replace('\\', '/', $exception->getFile());
    $root = rtrim(str_replace('\\', '/', dirname(__DIR__, 3)), '/') . '/';
    if (str_starts_with($file, $root)) {
        $file = substr($file, strlen($root));
    } else {
        $file = basename($file);
    }

    $sqlState = null;
    $vendorCode = null;
    $cause = $exception;
    while ($cause instanceof Throwable) {
        if ($cause instanceof DatabaseConnectionException) {
            $sqlState = $cause->sqlState();
            break;
        }
        if ($cause instanceof \PDOException) {
            $sqlState = is_string($cause->errorInfo[0] ?? null) ? $cause->errorInfo[0] : null;
            $vendorCode = is_int($cause->errorInfo[1] ?? null) ? $cause->errorInfo[1] : null;
            break;
        }
        $cause = $cause->getPrevious();
    }

    try {
        $payload = json_encode([
            'requestId' => $requestId,
            'stage' => $stage,
            'exception' => get_class($exception),
            'code' => (string) $exception->getCode(),
            'sqlState' => $sqlState,
            'vendorCode' => $vendorCode,
            'file' => $file,
            'line' => $exception->getLine(),
        ], JSON_UNESCAPED_SLASHES);
        if (is_string($payload)) {
            @error_log('[teacher-grading] ' . $payload);
        }
    } catch (Throwable) {
        // Diagnostics must never turn a handled page error into another failure.
    }
}

$user = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/assessments/index.php');
$session = new SessionManager(array_merge(require dirname(__DIR__, 3) . '/config/session.php', ['name' => SessionManager::SESSION_TEACHER]));
$session->start();

$modeInput = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['mode'] ?? 'class') : ($_GET['mode'] ?? 'class');
$mode = is_string($modeInput) ? $modeInput : '';
$modeLabels = ['class'=>'Lớp học', 'project'=>'Dự án hướng dẫn', 'activity'=>'Hoạt động'];
if (!isset($modeLabels[$mode])) {
    http_response_code(422);
    exit('Ngữ cảnh chấm điểm không hợp lệ.');
}

$pageTitle = 'Chấm điểm';
$currentRoute = 'assessments';
$teacherSidebarHomeHref = '/index.php';
$teacherSidebarRoleHref = '/role-selection.php';
$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'href' => '/app/teacher/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Sân chơi của tôi', 'route' => 'playgrounds', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm', 'route' => 'assessments', 'href' => '/app/teacher/assessments/index.php', 'icon' => 'clipboard-check', 'active' => true],
    ['title' => 'Học viên', 'route' => 'students', 'icon' => 'users', 'active' => false],
];

$flash = $_SESSION['teacherGradingFlash'] ?? null;
unset($_SESSION['teacherGradingFlash']);

$error = null;
$saveError = null;
$bootError = null;
$service = null;
$permissions = null;
$dataLoaded = false;
$unexpectedLoadError = false;
$saveStarted = false;
$stage = 'initialization';
$requestId = RequestId::make(null);
$unexpectedException = null;
$data = [
    'teacher' => [],
    'contexts' => [],
    'selectedContext' => null,
    'students' => [],
    'criteria' => [],
];

$contextField = \TalentHub\Modules\Teacher\Repository\TeacherAssessmentScope::column($mode);
$contextInput = $_GET['contextId'] ?? $_GET[$contextField] ?? null;
$selectedContextId = is_string($contextInput) ? trim($contextInput) : null;
$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

try {
    $stage = 'connection';
    $pdo = (new Connection(require dirname(__DIR__, 3) . '/config/database.php'))->connect();
    $permissions = new PermissionService($pdo);
    if ($mode === 'activity') {
        $permissions->require($user['id'], 'activity.read_managed');
        $permissions->require($user['id'], 'activity_registration.read_managed');
    }
    $stage = 'permission:assessment.read_managed';
    $permissions->require($user['id'], 'assessment.read_managed');

    $service = new TeacherGradingService(new TeacherGradingRepository($pdo));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $saveStarted = true;
        $selectedContextId = is_string($_POST['contextId'] ?? null) ? trim($_POST['contextId']) : null;
        $search = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
        $stage = 'save:csrf';
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $stage = 'save:permission';
        $permissions->require($user['id'], 'assessment.update_managed');
        $stage = 'save:assessment';
        $savedContextId = $service->save($user['id'], $_POST, $requestId);

        $_SESSION['teacherGradingFlash'] = 'Đã lưu đánh giá cho học viên.';
        $redirectQuery = ['mode'=>$mode, 'contextId' => $savedContextId];
        if (isset($_POST['q']) && trim((string) $_POST['q']) !== '') {
            $redirectQuery['q'] = trim((string) $_POST['q']);
        }
        header('Location: ./index.php?' . http_build_query($redirectQuery));
        exit;
    }

    $stage = 'load:page_data';
    $data = $service->pageData($user['id'], $selectedContextId, $search, $mode);
    if (($selectedContextId === null || $selectedContextId === '') && $data['contexts'] !== []) {
        $selectedContextId = (string) $data['contexts'][0]['id'];
        $stage = 'load:selected_page_data';
        $data = $service->pageData($user['id'], $selectedContextId, $search, $mode);
    }
    $dataLoaded = true;
} catch (TeacherGradingConflictException) {
    http_response_code(409);
    $error = 'Đánh giá này vừa được cập nhật ở nơi khác. Vui lòng tải lại trang và thử lại.';
} catch (ApiException $exception) {
    http_response_code($exception->status);
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $unexpectedException = $exception;
    $bootError = 'Chưa thể tải dữ liệu chấm điểm. Vui lòng kiểm tra kết nối và trạng thái migration của database.';
}

if ($unexpectedException !== null) {
    teacherGradingLogUnexpected($stage, $unexpectedException, $requestId);
    if ($saveStarted) {
        $saveError = "\u{004b}h\u{00f4}ng th\u{1ec3} l\u{01b0}u \u{0111}\u{00e1}nh gi\u{00e1}. Vui l\u{00f2}ng th\u{1eed} l\u{1ea1}i.";
        $bootError = null;
    } else {
        $unexpectedLoadError = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $service !== null && ($error !== null || $saveError !== null)) {
    try {
        $stage = 'load:post_error_reload';
        $data = $service->pageData($user['id'], $selectedContextId, $search, $mode);
        $dataLoaded = true;
    } catch (Throwable $exception) {
        $unexpectedLoadError = true;
        teacherGradingLogUnexpected('load:post_error_reload', $exception, $requestId);
        $bootError = "\u{0043}h\u{01b0}a th\u{1ec3} t\u{1ea3}i d\u{1eef} li\u{1ec7}u ch\u{1ea5}m \u{0111}i\u{1ec3}m. Vui l\u{00f2}ng ki\u{1ec3}m tra k\u{1ebf}t n\u{1ed1}i v\u{00e0} tr\u{1ea1}ng th\u{00e1}i migration c\u{1ee7}a database.";
        // Keep the validation message visible even when the page cannot reload its context.
    }
}

$rawName = $_SESSION['user']['fullName'] ?? ($_SESSION['user']['full_name'] ?? ($_SESSION['user_name'] ?? ''));
$teacher = $data['teacher'] ?? [];
$teacherName = trim((string) ($rawName !== '' ? $rawName : ($teacher['fullName'] ?? ($user['fullName'] ?? 'Giáo viên'))));
if ($teacherName === 'minh triet') {
    $teacherName = 'Minh Triết';
}
$teacherInfo = [
    'full_name' => $teacherName !== '' ? $teacherName : 'Giáo viên',
    'role_label' => 'Giáo viên / Hướng dẫn viên',
    'avatar_initials' => teacherGradingInitials($teacherName),
    'notification_count' => 0,
];

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$activityStatusLabels = [
    'active' => 'Đang hoạt động',
    'in_progress' => 'Đang triển khai',
    'revoked' => 'Đã thu hồi',
    'draft' => 'Bản nháp',
    'published' => 'Đã công bố',
    'ongoing' => 'Đang diễn ra',
    'completed' => 'Đã hoàn tất',
    'archived' => 'Đã lưu trữ',
];
$registrationStatusLabels = [
    'pending' => 'Chờ xác nhận',
    'approved' => 'Đã duyệt',
    'attended' => 'Đã tham gia',
];
$assessmentStatusLabels = [
    'draft' => 'Bản nháp',
    'published' => 'Đã công bố',
];

$pageState = teacherGradingPageState($dataLoaded, $unexpectedLoadError, $data);
$retryParams = ['mode'=>$mode];
if ($selectedContextId !== null && $selectedContextId !== '') {
    $retryParams['contextId'] = $selectedContextId;
}
if ($search !== '') {
    $retryParams['q'] = $search;
}
$retryUrl = app_href('/app/teacher/assessments/index.php');
if ($retryParams !== []) {
    $retryUrl .= '?' . http_build_query($retryParams);
}

function teacherGradingInitials(string $name): string
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($pageTitle); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/global.css">
    <link rel="stylesheet" href="../../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../../assets/css/polish.css">
    <link rel="stylesheet" href="../../../assets/css/teacher.css">
    <link rel="stylesheet" href="../../../assets/css/typeui-selects.css">
    <style>
        .teacher-assessment-tabs{display:flex;gap:12px;flex-wrap:wrap;margin:20px 0}
        .teacher-assessment-readonly{padding:20px;overflow-wrap:anywhere}
        .teacher-assessment-readonly dl{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px}
        .teacher-assessment-readonly dd{margin:0}
        .teacher-assessment-comment{white-space:pre-wrap;overflow-wrap:anywhere}
        @media(max-width:600px){.teacher-assessment-tabs a{flex:1 1 100%}.teacher-grading-form__actions{flex-wrap:wrap}.teacher-grading-form__actions button{width:100%}}
    </style>
</head>
<body class="teacher-dashboard teacher-grading-page">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    <section class="teacher-section-box teacher-grading-intro">
                        <div class="teacher-grading-intro__content">
                            <div class="teacher-grading-intro__mark" aria-hidden="true">✦</div>
                            <div>
                                <span class="teacher-section-box__eyebrow">ĐÁNH GIÁ HỌC VIÊN</span>
                                <h2 class="teacher-grading-intro__title">Chấm Rubric năng lực</h2>
                                <p class="teacher-grading-intro__description">Chọn lớp, dự án hoặc hoạt động được phân công để đánh giá học viên.</p>
                            </div>
                        </div>
                        <div class="teacher-grading-intro__aside">
                            <span class="teacher-chip teacher-chip--primary"><?= $escape($modeLabels[$mode]); ?></span>
                            <span class="teacher-grading-intro__tip">Mỗi nhận xét là một bước tiến</span>
                        </div>
                    </section>

                    <nav class="teacher-assessment-tabs" aria-label="Ngữ cảnh đánh giá">
                        <?php foreach ($modeLabels as $tabMode=>$tabLabel): ?>
                            <a class="teacher-grading-button <?= $mode===$tabMode ? 'teacher-grading-button--primary' : 'teacher-grading-button--secondary'; ?>"
                               href="<?= $escape(app_href('/app/teacher/assessments/index.php').'?mode='.$tabMode); ?>"
                               <?= $mode===$tabMode ? 'aria-current="page"' : ''; ?>><?= $escape($tabLabel); ?></a>
                        <?php endforeach; ?>
                    </nav>
                    <?php if ($flash): ?>
                        <div class="teacher-grading-flash teacher-grading-flash--success" role="status"><?= $escape($flash); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="teacher-grading-flash teacher-grading-flash--error" role="alert"><?= $escape($error); ?></div>
                    <?php endif; ?>
                    <?php if ($saveError): ?>
                        <div class="teacher-grading-flash teacher-grading-flash--error" role="alert"><?= $escape($saveError); ?></div>
                    <?php endif; ?>
                    <?php if ($pageState === 'load_error'): ?>
                        <div class="teacher-grading-flash teacher-grading-flash--error" role="alert">
                            <?= $escape($bootError ?? ''); ?>
                        </div>
                        <a class="teacher-grading-button teacher-grading-button--secondary" href="<?= $escape($retryUrl); ?>"><?= $escape("\u{0054}h\u{1eed} l\u{1ea1}i"); ?></a>
                    <?php elseif ($pageState !== 'request_error'): ?>
                    <section class="teacher-section-box teacher-grading-toolbar" aria-label="Bộ lọc chấm điểm">
                        <div class="teacher-grading-toolbar__header">
                            <div>
                                <span class="teacher-section-box__eyebrow">KHÔNG GIAN LÀM VIỆC</span>
                                <p class="teacher-grading-toolbar__title">Chọn <?= $escape(mb_strtolower($modeLabels[$mode])); ?></p>
                            </div>
                            <span class="teacher-grading-toolbar__note">Chọn ngữ cảnh và tìm theo tên học viên</span>
                        </div>
                        <form method="get" class="teacher-grading-toolbar__form">
                            <input type="hidden" name="mode" value="<?= $escape($mode); ?>">
                            <label class="teacher-grading-field teacher-grading-field--activity">
                                <span><?= $escape($modeLabels[$mode]); ?></span>
                                <select name="contextId" class="typeui-select typeui-select--compact" onchange="this.form.submit()">
                                    <option value="">Chọn <?= $escape(mb_strtolower($modeLabels[$mode])); ?></option>
                                    <?php foreach ($data['contexts'] as $activity): ?>
                                        <option value="<?= $escape($activity['id']); ?>" <?= (string) ($data['selectedContext']['id'] ?? '') === (string) $activity['id'] ? 'selected' : ''; ?>>
                                            <?= $escape($activity['title']); ?> · <?= $escape($activityStatusLabels[$activity['status']] ?? $activity['status']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="teacher-grading-field teacher-grading-field--search">
                                <span>Tìm học viên</span>
                                <input type="search" name="q" value="<?= $escape($search); ?>" maxlength="100" placeholder="Tên hoặc email">
                            </label>
                            <button type="submit" class="teacher-grading-button teacher-grading-button--secondary">Lọc</button>
                        </form>
                    </section>

                    <?php if ($data['selectedContext'] !== null): ?>
                        <section class="teacher-section-box teacher-grading-activity-summary">
                            <div class="teacher-grading-activity-summary__identity">
                                <div class="teacher-grading-activity-summary__icon" aria-hidden="true">✦</div>
                                <div>
                                    <span class="teacher-section-box__eyebrow">NGỮ CẢNH ĐANG CHỌN</span>
                                    <h2 class="teacher-grading-activity-summary__title"><?= $escape($data['selectedContext']['title']); ?></h2>
                                    <div class="teacher-grading-activity-summary__meta">
                                        <span class="teacher-status-pill teacher-status-pill--info"><?= $escape($activityStatusLabels[$data['selectedContext']['status']] ?? $data['selectedContext']['status']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="teacher-grading-activity-summary__stats">
                                <div class="teacher-grading-activity-summary__stat">
                                    <strong><?= number_format(count($data['students'])); ?></strong>
                                    <span>học viên hiển thị</span>
                                </div>

                            </div>
                        </section>

                        <?php if ($data['students'] === []): ?>
                            <div class="teacher-empty-state teacher-grading-empty">
                                <div class="teacher-empty-state__icon" aria-hidden="true">✓</div>
                                <h3 class="teacher-empty-state__title">Chưa có học viên phù hợp</h3>
                                <p class="teacher-empty-state__desc">Chưa có học viên đủ điều kiện trong ngữ cảnh đã chọn, hoặc bộ lọc không có kết quả.</p>
                            </div>
                        <?php else: ?>
                            <div class="teacher-grading-list">
                                <?php foreach ($data['students'] as $student):
                                    $assessmentStatus = $student['assessmentStatus'] ?? null;
                                    $assessmentLabel = $assessmentStatusLabels[$assessmentStatus] ?? 'Chưa chấm';
                                ?>
                                    <article class="teacher-section-box teacher-grading-card">
                                        <div class="teacher-grading-card__header">
                                            <div class="teacher-grading-card__identity">
                                                <div class="teacher-grading-card__avatar" aria-hidden="true"><?= $escape(teacherGradingInitials((string) $student['fullName'])); ?></div>
                                                <div>
                                                    <h3 class="teacher-grading-card__title"><?= $escape($student['fullName']); ?></h3>
                                                    <p class="teacher-grading-card__meta"><?= $escape($student['email']); ?></p>

                                                </div>
                                            </div>
                                            <span class="teacher-status-pill <?= $assessmentStatus === 'published' ? 'teacher-status-pill--positive' : ($assessmentStatus === 'draft' ? 'teacher-status-pill--warning' : ''); ?>">
                                                <?= $escape($assessmentLabel); ?>
                                            </span>
                                        </div>

                                        <?php require __DIR__ . '/rubric-form.php'; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($data['contexts'] === []): ?>
                        <div class="teacher-empty-state teacher-grading-empty">
                            <div class="teacher-empty-state__icon" aria-hidden="true">−</div>
                            <h3 class="teacher-empty-state__title">Chưa có ngữ cảnh được phân công</h3>
                            <p class="teacher-empty-state__desc">Chỉ lớp được phân công cùng trường, dự án bạn làm mentor hoặc hoạt động của bạn mới xuất hiện tại đây.</p>
                        </div>
                    <?php else: ?>
                        <div class="teacher-empty-state teacher-grading-empty">
                            <div class="teacher-empty-state__icon" aria-hidden="true">↗</div>
                            <h3 class="teacher-empty-state__title">Chọn một hoạt động để bắt đầu</h3>
                            <p class="teacher-empty-state__desc">Danh sách chỉ bao gồm ngữ cảnh được phân công cho giáo viên hiện tại.</p>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <div class="teacher-toast" id="teacher-toast" aria-live="polite" aria-atomic="true">
        <div class="teacher-toast__content">
            <span class="teacher-toast__message">Đang tải.</span>
        </div>
    </div>
    <script src="../../../assets/js/teacher.js"></script>
</body>
</html>
