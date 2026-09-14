<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Repository\TeacherAssessmentReviewRepository;
use TalentHub\Modules\Teacher\Service\TeacherAssessmentReviewService;
use TalentHub\Rbac\Service\PermissionService;

use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Rbac\RoleCodes;

date_default_timezone_set('Asia/Ho_Chi_Minh');

function teacher_aptitude_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function teacher_aptitude_test_label(string $type): string
{
    return match (strtolower($type)) {
        'holland' => 'Trắc nghiệm Holland',
        'mbti' => 'Trắc nghiệm MBTI',
        'disc' => 'Trắc nghiệm DISC',
        'multiple_intelligence' => 'Trắc nghiệm Đa trí tuệ',
        default => $type,
    };
}

function teacher_aptitude_status_label(string $status): string
{
    return match ($status) {
        'ai_graded' => 'AI đã chấm',
        'teacher_verified' => 'Giáo viên đã duyệt',
        default => $status,
    };
}

function teacher_aptitude_status_tone(string $status): string
{
    return match ($status) {
        'teacher_verified' => 'success',
        default => 'info',
    };
}

$user = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/assessments/aptitude.php');
$session = new SessionManager(array_merge(require dirname(__DIR__, 3) . '/config/session.php', ['name' => SessionManager::SESSION_TEACHER]));
$session->start();

$error = null;
$flash = isset($_SESSION['teacherAptitudeFlash']) ? (string) $_SESSION['teacherAptitudeFlash'] : null;
unset($_SESSION['teacherAptitudeFlash']);
$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$data = ['teacher' => [], 'results' => [], 'search' => '', 'counts' => ['total' => 0, 'ai_graded' => 0, 'teacher_verified' => 0]];

try {
    $pdo = (new Connection(require dirname(__DIR__, 3) . '/config/database.php'))->connect();
    $permissions = new PermissionService($pdo);
    $permissions->require($user['id'], 'assessment.read_managed');

    $service = new TeacherAssessmentReviewService(new TeacherAssessmentReviewRepository($pdo));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $permissions->require($user['id'], 'assessment.update_managed');
        $resultId = is_string($_POST['resultId'] ?? null) ? trim($_POST['resultId']) : '';
        $overrides = [];
        $dimensions = $_POST['dimensions'] ?? [];
        if (is_array($dimensions)) {
            foreach ($dimensions as $code => $score) {
                $code = trim((string) $code);
                if ($code === '') {
                    continue;
                }
                $numeric = is_numeric($score) ? (float) $score : null;
                if ($numeric !== null) {
                    $overrides[$code] = $numeric;
                }
            }
        }
        $summary = $_POST['teacherSummary'] ?? null;
        $comment = $_POST['teacherComment'] ?? null;
        $service->verify(
            $user['id'],
            $resultId,
            $overrides,
            is_string($summary) ? $summary : null,
            is_string($comment) ? $comment : null
        );
        $_SESSION['teacherAptitudeFlash'] = 'Điểm chính thức (Giáo viên đã duyệt) đã được đồng bộ tới trang Analytics.';
        header('Location: ./aptitude.php' . ($search !== '' ? '?q=' . rawurlencode($search) : ''));
        exit;
    }

    $data = $service->pageData($user['id'], $search);
} catch (ApiException $exception) {
    http_response_code($exception->status);
    $error = $exception->getMessage();
} catch (Throwable $exception) {
    $error = 'Chưa thể tải danh sách kết quả năng lực. Vui lòng kiểm tra kết nối và trạng thái migration của database.';
}

$csrfToken = $session->csrfToken();
$pageTitle = 'Duyệt kết quả năng lực';
$currentRoute = 'assessments';
$teacherSidebarHomeHref = '/index.php';
$teacherSidebarRoleHref = '/role-selection.php';
$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'href' => '/app/teacher/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Sân chơi của tôi', 'route' => 'playgrounds', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm', 'route' => 'assessments', 'href' => '/app/teacher/assessments/index.php', 'icon' => 'clipboard-check', 'active' => true],
    ['title' => 'Học viên', 'route' => 'students', 'href' => '/app/teacher/students/index.php', 'icon' => 'users', 'active' => false],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="description" content="Duyệt và xác nhận điểm chính thức của 4 bài đánh giá năng lực (AI chấm tự động) trên TalentHub.">
    <title><?= teacher_aptitude_escape($pageTitle); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/global.css">
    <link rel="stylesheet" href="../../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../../assets/css/polish.css">
    <link rel="stylesheet" href="../../../assets/css/teacher.css">
    <link rel="stylesheet" href="../../../assets/css/typeui-selects.css">
    <style>
        .aptitude-summary-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:16px 0 22px}
        .aptitude-summary-card{background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px 18px}
        .aptitude-summary-card__label{font-size:0.8rem;color:var(--text-secondary,#64748B)}
        .aptitude-summary-card__value{font-size:1.5rem;font-weight:900;color:var(--text-primary,#1E293B);margin-top:4px}
        .aptitude-result-card{background:#FFFFFF;border:1px solid #E2E8F0;border-radius:12px;padding:16px 18px;margin-bottom:12px}
        .aptitude-result-card__head{display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;justify-content:space-between}
        .aptitude-result-card__student{font-weight:800;color:var(--text-primary,#1E293B)}
        .aptitude-dim-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-top:12px}
        .aptitude-dim-item{background:#F8FAFC;border:1px solid #EDF2F7;border-radius:8px;padding:10px 12px}
        .aptitude-dim-item label{font-size:0.8rem;color:var(--text-secondary,#64748B);display:block;margin-bottom:6px}
        .aptitude-dim-item input,.aptitude-dim-item textarea{width:100%;padding:7px 10px;border:1px solid #CBD5E1;border-radius:6px;font-size:0.9rem}
        .aptitude-alert{background:#FFF0EB;border:1px solid #FFDACB;color:#C2410C;border-radius:8px;padding:12px 16px;margin-bottom:16px}
        .aptitude-flash{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;border-radius:8px;padding:12px 16px;margin-bottom:16px}
        .aptitude-ai-box{margin-top:12px;padding:12px 14px;background:#F0F7FF;border-left:4px solid #2563EB;border-radius:0 8px 8px 0}
        .aptitude-ai-box__title{font-size:0.8rem;font-weight:700;color:#1D4ED8;margin-bottom:6px}
        .aptitude-access[open]{border-color:#C7D2FE}
        .aptitude-access summary{cursor:pointer;list-style:none}
        .aptitude-access summary::-webkit-details-marker{display:none}
    </style>
</head>
<body class="teacher-dashboard">
<a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require dirname(__DIR__) . '/includes/header.php'; ?>

            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    <section class="teacher-welcome">
                        <div class="teacher-welcome__content">
                            <div>
                                <span class="teacher-welcome__tag">4 bài đánh giá năng lực · AI chấm tự động</span>
                                <h2 class="teacher-welcome__title">Duyệt kết quả năng lực</h2>
                                <p class="teacher-welcome__description">
                                    Xem bài làm và kết quả do AI chấm. Duyệt lại nếu cần, chỉnh điểm/nhận xét rồi lưu.
                                    Bài được duyệt (Giáo viên đã duyệt) sẽ là điểm chính thức và tự động đồng bộ lên trang Analytics của Nhà trường.
                                </p>
                            </div>
                        </div>
                    </section>

                    <?php if ($flash !== null): ?>
                        <div class="aptitude-flash" role="status"><?= teacher_aptitude_escape($flash); ?></div>
                    <?php endif; ?>

                    <?php if ($error !== null): ?>
                        <div class="aptitude-alert" role="alert"><?= teacher_aptitude_escape($error); ?></div>
                    <?php endif; ?>

                    <div class="aptitude-summary-cards">
                        <div class="aptitude-summary-card"><div class="aptitude-summary-card__label">Tổng kết quả</div><div class="aptitude-summary-card__value"><?= (int) $data['counts']['total']; ?></div></div>
                        <div class="aptitude-summary-card"><div class="aptitude-summary-card__label">Đang chờ duyệt</div><div class="aptitude-summary-card__value"><?= (int) $data['counts']['ai_graded']; ?></div></div>
                        <div class="aptitude-summary-card"><div class="aptitude-summary-card__label">Đã duyệt (chính thức)</div><div class="aptitude-summary-card__value"><?= (int) $data['counts']['teacher_verified']; ?></div></div>
                    </div>

                    <form method="get" action="./aptitude.php" class="teacher-students-filter-bar">
                        <label class="teacher-students-filter-group">
                            <span>Học viên / bài test</span>
                            <input type="text" name="q" value="<?= teacher_aptitude_escape($data['search']); ?>" placeholder="Tìm theo tên hoặc bài test...">
                        </label>
                        <div class="teacher-students-filter-actions">
                            <a class="btn btn-sm btn-outline" href="./aptitude.php">Xoá lọc</a>
                            <button class="btn btn-sm btn-primary" type="submit">Lọc</button>
                        </div>
                    </form>
<?php if ($data['results'] === []): ?>
                        <div class="teacher-empty-state">
                            <div class="teacher-empty-state__icon" aria-hidden="true">📊</div>
                            <h4 class="teacher-empty-state__title">Chưa có kết quả năng lực nào</h4>
                            <p class="teacher-empty-state__desc">Dữ liệu sẽ xuất hiện khi học viên trong trường hoàn thành nộp bài đánh giá năng lực.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($data['results'] as $result): ?>
                            <details class="aptitude-result-card aptitude-access">
                                <summary>
                                    <div class="aptitude-result-card__head">
                                        <div>
                                            <div class="aptitude-result-card__student"><?= teacher_aptitude_escape($result['studentName']); ?></div>
                                            <div class="teacher-assessment-date"><?= teacher_aptitude_escape($result['className'] ?? ''); ?> · <?= teacher_aptitude_escape(teacher_aptitude_test_label((string) $result['testType'])); ?> · Nộp lúc <?= teacher_aptitude_escape((string) ($result['submittedAt'] ?? '')); ?></div>
                                        </div>
                                        <span class="teacher-chip teacher-chip--<?= teacher_aptitude_status_tone((string) $result['gradingStatus']); ?>"><?= teacher_aptitude_escape(teacher_aptitude_status_label((string) $result['gradingStatus'])); ?></span>
                                    </div>
                                </summary>

                                <?php $aiFeedback = $result['aiFeedback'] ?? []; ?>
                                <?php if (($aiFeedback['summary'] ?? '') !== ''): ?>
                                    <div class="aptitude-ai-box">
                                        <div class="aptitude-ai-box__title">Nhận xét của AI (AI_GRADED)</div>
                                        <p style="margin:0;font-size:0.9rem;color:#1E3A8A;"><?= teacher_aptitude_escape($aiFeedback['summary']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <form method="post" action="./aptitude.php">
                                    <input type="hidden" name="csrfToken" value="<?= teacher_aptitude_escape($csrfToken); ?>">
                                    <input type="hidden" name="resultId" value="<?= teacher_aptitude_escape($result['resultId']); ?>">
                                    <input type="hidden" name="q" value="<?= teacher_aptitude_escape($data['search']); ?>">

                                    <div class="aptitude-dim-grid">
                                        <?php
                                        $dims = $result['dimensionScores'] ?? [];
                                        $overrides = $result['teacherOverrides'] ?? [];
                                        foreach ($dims as $code => $score):
                                            $prefill = array_key_exists((string) $code, $overrides) ? $overrides[(string) $code] : $score;
                                            ?>
                                            <div class="aptitude-dim-item">
                                                <label><?= teacher_aptitude_escape($code); ?> (điểm 0-100)</label>
                                                <input type="number" name="dimensions[<?= teacher_aptitude_escape($code); ?>]" value="<?= teacher_aptitude_escape((string) $prefill); ?>" min="0" max="100" step="0.5">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="aptitude-dim-grid" style="grid-template-columns:1fr;">
                                        <div class="aptitude-dim-item">
                                            <label>Nhận xét tổng của giáo viên</label>
                                            <textarea name="teacherSummary" rows="2" maxlength="4000"><?= teacher_aptitude_escape((string) ($result['teacherSummary'] ?? '')); ?></textarea>
                                        </div>
                                        <div class="aptitude-dim-item">
                                            <label>Ghi chú giáo viên (hiển thị nội bộ)</label>
                                            <textarea name="teacherComment" rows="2" maxlength="4000"><?= teacher_aptitude_escape((string) ($result['teacherComment'] ?? '')); ?></textarea>
                                        </div>
                                    </div>

                                    <div style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                                        <button class="btn btn-primary" type="submit">Lưu & Xác nhận (Giáo viên đã duyệt)</button>
                                        <?php if (($result['gradingStatus'] ?? '') === 'teacher_verified'): ?>
                                            <span class="teacher-chip teacher-chip--success">Điểm này đang là điểm chính thức cho Analytics</span>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </details>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
];