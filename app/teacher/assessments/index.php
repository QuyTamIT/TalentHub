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
        .teacher-assessment-readonly{padding:22px;background:#FBFDFF;border-radius:12px;border:1px solid #E2E8F0;margin-top:16px}
        .teacher-assessment-readonly__meta-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #EDF2F7;flex-wrap:wrap;gap:8px}
        .teacher-assessment-date{font-size:0.85rem;color:var(--text-secondary,#64748B)}
        .teacher-assessment-score-card{display:flex;align-items:center;gap:12px;padding:12px 18px;background:#FFF0EB;border:1px solid #FFDACB;border-radius:10px;margin-bottom:20px}
        .teacher-assessment-score-label{font-weight:700;color:var(--text-primary,#1E293B);font-size:0.95rem}
        .teacher-assessment-score-val{font-size:1.75rem;font-weight:900;color:var(--primary-coral,#E04058)}
        .teacher-assessment-score-denom{font-size:1rem;color:var(--text-secondary,#64748B);font-weight:600}
        .teacher-assessment-section{margin-top:20px}
        .teacher-assessment-section__title{font-size:0.95rem;font-weight:800;color:var(--text-primary,#1E293B);margin-bottom:10px;display:flex;align-items:center;gap:8px}
        .teacher-rubric-summary-grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:12px}
        .teacher-rubric-summary-item{background:#FFFFFF;border:1px solid #E2E8F0;border-radius:8px;padding:10px 14px}
        .teacher-rubric-summary-item dt{font-size:0.85rem;color:var(--text-secondary,#64748B);margin-bottom:4px}
        .teacher-rubric-summary-item dd{margin:0;font-size:1rem;color:var(--text-primary,#1E293B)}
        .teacher-skills-pill-list{display:flex;flex-wrap:wrap;gap:8px}
        .teacher-skill-pill{display:inline-flex;align-items:center;gap:8px;background:#ECFDF5;border:1px solid #A7F3D0;padding:6px 14px;border-radius:20px}
        .teacher-skill-pill__cat{font-size:0.75rem;font-weight:700;color:#047857;text-transform:uppercase;background:#D1FAE5;padding:2px 8px;border-radius:10px}
        .teacher-skill-pill__name{font-size:0.9rem;font-weight:700;color:#065F46}
        .teacher-skill-pill__score{font-size:0.85rem;font-weight:800;color:#047857}
        .teacher-assessment-comment-box{margin:0;padding:14px 18px;background:#F8FAFC;border-left:4px solid var(--primary,#FF6B00);border-radius:0 8px 8px 0;font-style:normal;font-size:0.95rem;line-height:1.6;color:var(--text-primary,#1E293B)}
        
        /* Grading Form Styles */
        .teacher-grading-form{margin-top:16px}
        .teacher-grading-score-panel{background:#FFFBF8;border:1.5px solid #FFDACB;border-radius:12px;padding:16px 20px;margin-bottom:20px}
        .teacher-field-hint{font-size:0.825rem;color:var(--text-secondary,#64748B);font-weight:normal;margin-left:6px}
        .teacher-input-affix-group{position:relative;display:flex;align-items:center}
        .teacher-input-affix-group input{flex:1;padding-right:60px;font-size:1.1rem;font-weight:700;height:44px;border:1.5px solid var(--border,#CBD5E1);border-radius:8px;padding-left:12px;background:#FFFFFF}
        .teacher-input-affix-group input:focus{border-color:var(--primary,#FF6B00);outline:none;box-shadow:0 0 0 3px rgba(255,107,0,0.15)}
        .teacher-input-affix{position:absolute;right:14px;font-weight:700;color:var(--text-secondary,#64748B);font-size:0.9rem;pointer-events:none}
        
        .teacher-grading-fieldset{border:1px solid #E2E8F0;border-radius:12px;padding:18px 20px;background:#FFFFFF;margin-bottom:20px}
        .teacher-grading-fieldset__legend{display:flex;align-items:center;gap:10px;font-size:1.05rem;font-weight:800;color:var(--text-primary,#1E293B);padding:0 8px}
        .teacher-field-badge{font-size:0.75rem;font-weight:600;background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;padding:2px 8px;border-radius:6px}
        .teacher-badge-ai{font-size:0.75rem;font-weight:700;background:linear-gradient(135deg, #FFF0EB 0%, #EDE9FE 100%);color:#7C3AED;border:1px solid #DDD6FE;padding:3px 10px;border-radius:20px}
        .teacher-field-scale{font-size:0.8rem;color:var(--text-secondary,#64748B);font-weight:normal}
        
        .teacher-grading-criteria__grid{display:grid;grid-template-columns:repeat(auto-fill, minmax(260px, 1fr));gap:16px;margin-top:14px}
        .teacher-grading-field{display:flex;flex-direction:column;gap:6px}
        .teacher-grading-field__label{font-size:0.9rem;font-weight:700;color:var(--text-primary,#1E293B)}
        .teacher-grading-field input[type="number"], .teacher-grading-field textarea{border:1.5px solid var(--border,#CBD5E1);border-radius:8px;padding:10px 12px;font-size:0.95rem;background:#FFFFFF}
        .teacher-grading-field input[type="number"]:focus, .teacher-grading-field textarea:focus{border-color:var(--primary,#FF6B00);outline:none;box-shadow:0 0 0 3px rgba(255,107,0,0.15)}
        
        /* Skills Evaluation Section */
        .teacher-grading-skills__header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:14px;flex-wrap:wrap}
        .teacher-grading-skills__hint{font-size:0.85rem;color:var(--text-secondary,#64748B);margin:6px 0 0;line-height:1.5;max-width:700px}
        .teacher-btn-add-skill{background:#FFF0EB;border:1px solid #FFDACB;color:var(--primary-coral,#E04058);padding:7px 16px;border-radius:8px;font-size:0.85rem;font-weight:700;cursor:pointer;transition:all 0.2s;white-space:nowrap}
        .teacher-btn-add-skill:hover{background:var(--primary-coral,#E04058);color:#FFFFFF;border-color:var(--primary-coral,#E04058)}
        .teacher-grading-skills__list{display:flex;flex-direction:column;gap:10px;margin-top:12px}
        .teacher-skill-row{display:flex;align-items:flex-end;gap:12px;background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;padding:12px 14px;flex-wrap:wrap}
        .teacher-skill-col{display:flex;flex-direction:column;gap:4px}
        .teacher-skill-col--select{flex:2;min-width:220px}
        .teacher-skill-col--custom{flex:2;min-width:200px}
        .teacher-skill-col--cat{flex:1.5;min-width:180px}
        .teacher-skill-col--score{width:140px}
        .teacher-skill-col--remove{display:flex;align-items:center;padding-bottom:2px}
        .teacher-sublabel{font-size:0.775rem;font-weight:700;color:var(--text-secondary,#64748B);text-transform:uppercase;letter-spacing:0.03em}
        .teacher-skill-select, .teacher-skill-name-input, .teacher-skill-cat-select{height:40px;border:1.5px solid var(--border,#CBD5E1);border-radius:6px;padding:0 10px;font-size:0.9rem;background:#FFFFFF;width:100%}
        .teacher-btn-remove-skill{width:36px;height:36px;border-radius:8px;border:1px solid #FECACA;background:#FFF5F5;color:#EF4444;font-size:1.3rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all 0.15s;line-height:1}
        .teacher-btn-remove-skill:hover{background:#EF4444;color:#FFFFFF;border-color:#EF4444}
        
        .teacher-grading-field--comment{margin-top:18px}
        .teacher-grading-field--comment textarea{min-height:90px;resize:vertical}
        .teacher-grading-form__actions{display:flex;justify-content:flex-end;gap:12px;margin-top:20px}
        @media(max-width:768px){
            .teacher-skill-row{flex-direction:column;align-items:stretch}
            .teacher-skill-col--score{width:100%}
            .teacher-skill-col--remove{align-self:flex-end}
            .teacher-assessment-tabs a{flex:1 1 100%}
            .teacher-grading-form__actions{flex-wrap:wrap}
            .teacher-grading-form__actions button{width:100%}
        }
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

    <template id="teacher-skill-template">
        <div class="teacher-skill-row" data-index="__INDEX__">
            <div class="teacher-skill-col teacher-skill-col--select">
                <label class="teacher-sublabel">Chọn kỹ năng</label>
                <select name="skills[__INDEX__][skillId]" class="typeui-select teacher-skill-select" onchange="window.teacherOnSkillSelectChange(this)">
                    <option value="">-- Chọn kỹ năng trong danh mục --</option>
                    <?php
                    $skillCategoryLabels = [
                        'technical' => 'Công nghệ & Kỹ thuật',
                        'business' => 'Kinh doanh & Khởi nghiệp',
                        'marketing' => 'Marketing & Truyền thông',
                        'creative' => 'Thiết kế & Sáng tạo',
                        'data' => 'Dữ liệu & Thống kê',
                        'academic' => 'Học thuật & Nghiên cứu',
                        'finance' => 'Tài chính & Kế toán',
                        'music' => 'Âm nhạc & Trình diễn',
                        'arts' => 'Mỹ thuật & Nghệ thuật',
                        'soft' => 'Kỹ năng mềm & Giao tiếp',
                        'operations' => 'Vận hành & Sản xuất',
                        'sports' => 'Thể thao & Thể chất',
                    ];
                    $skillsByCat = [];
                    foreach ($data['availableSkills'] ?? [] as $avail) {
                        $cat = $avail['category'] ?? 'technical';
                        $skillsByCat[$cat][] = $avail;
                    }
                    foreach ($skillsByCat as $catKey => $catSkills): ?>
                        <optgroup label="<?= $escape($skillCategoryLabels[$catKey] ?? $catKey); ?>">
                            <?php foreach ($catSkills as $avail): ?>
                                <option value="<?= $escape($avail['id']); ?>" data-category="<?= $escape($avail['category']); ?>">
                                    <?= $escape($avail['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                    <option value="__custom__">+ Kỹ năng khác (tự nhập)...</option>
                </select>
            </div>

            <div class="teacher-skill-col teacher-skill-col--custom" style="display: none;">
                <label class="teacher-sublabel">Tên kỹ năng mới</label>
                <input type="text" name="skills[__INDEX__][skillName]" class="teacher-skill-name-input"
                       placeholder="Ví dụ: Thiết kế hệ thống, Piano...">
            </div>

            <div class="teacher-skill-col teacher-skill-col--cat" style="display: none;">
                <label class="teacher-sublabel">Nhóm ngành</label>
                <select name="skills[__INDEX__][category]" class="typeui-select teacher-skill-cat-select">
                    <?php foreach ($skillCategoryLabels as $catVal => $catName): ?>
                        <option value="<?= $escape($catVal); ?>"><?= $escape($catName); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="teacher-skill-col teacher-skill-col--score">
                <label class="teacher-sublabel">Điểm năng lực</label>
                <div class="teacher-input-affix-group">
                    <input type="number" name="skills[__INDEX__][score]" min="0" max="100" step="0.1"
                           placeholder="0 - 100">
                    <span class="teacher-input-affix">/ 100</span>
                </div>
            </div>

            <div class="teacher-skill-col teacher-skill-col--remove">
                <button type="button" class="teacher-btn-remove-skill" title="Xóa kỹ năng này" onclick="window.teacherRemoveSkillRow(this)">
                    &times;
                </button>
            </div>
        </div>
    </template>

    <script src="../../../assets/js/teacher.js"></script>
    <script>
    window.teacherOnSkillSelectChange = function(select) {
        const row = select.closest('.teacher-skill-row');
        if (!row) return;
        const customCol = row.querySelector('.teacher-skill-col--custom');
        const catCol = row.querySelector('.teacher-skill-col--cat');
        const customInput = row.querySelector('.teacher-skill-name-input');
        const catSelect = row.querySelector('.teacher-skill-cat-select');

        if (select.value === '__custom__') {
            if (customCol) customCol.style.display = '';
            if (catCol) catCol.style.display = '';
            if (customInput) customInput.focus();
        } else {
            if (customCol) customCol.style.display = 'none';
            if (catCol) catCol.style.display = 'none';
            if (customInput) customInput.value = '';
            
            const selectedOpt = select.options[select.selectedIndex];
            const cat = selectedOpt ? selectedOpt.getAttribute('data-category') : '';
            if (cat && catSelect) {
                catSelect.value = cat;
            }
        }
    };

    window.teacherAddSkillRow = function(studentId) {
        const container = document.getElementById('skills-list-' + studentId);
        const template = document.getElementById('teacher-skill-template');
        if (!container || !template) return;

        const nextIndex = container.querySelectorAll('.teacher-skill-row').length;
        const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html.trim();
        const newRow = tempDiv.firstElementChild;
        container.appendChild(newRow);
    };

    window.teacherRemoveSkillRow = function(btn) {
        const row = btn.closest('.teacher-skill-row');
        const container = row ? row.closest('.teacher-grading-skills__list') : null;
        if (row) {
            row.remove();
        }
        if (container && container.querySelectorAll('.teacher-skill-row').length === 0) {
            const studentId = container.id.replace('skills-list-', '');
            window.teacherAddSkillRow(studentId);
        }
    };
    </script>
</body>
</html>
