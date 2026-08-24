<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Exception\TeacherGradingConflictException;
use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Modules\Teacher\Service\TeacherGradingService;
use TalentHub\Rbac\Service\PermissionService;

date_default_timezone_set('Asia/Ho_Chi_Minh');

$session = new SessionManager(require dirname(__DIR__, 3) . '/config/session.php');
$session->start();

$user = $session->user();
if ($user === null) {
    header('Location: ' . app_href('/login.php') . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/app/teacher/assessments/'));
    exit;
}
if (($user['role'] ?? null) !== 'teacher') {
    header('Location: ' . app_href('/role-selection.php'));
    exit;
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
    ['title' => 'Điểm danh QR', 'route' => 'checkins', 'icon' => 'qr', 'active' => false],
];

$flash = $_SESSION['teacherGradingFlash'] ?? null;
unset($_SESSION['teacherGradingFlash']);

$error = null;
$bootError = null;
$service = null;
$permissions = null;
$data = [
    'teacher' => [],
    'activities' => [],
    'selectedActivity' => null,
    'students' => [],
    'criteria' => [],
];

$selectedActivityId = isset($_GET['activityId']) ? trim((string) $_GET['activityId']) : null;
$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

try {
    $pdo = (new Connection(require dirname(__DIR__, 3) . '/config/database.php'))->connect();
    $permissions = new PermissionService($pdo);
    $permissions->require($user['id'], 'activity.read_managed');
    $permissions->require($user['id'], 'activity_registration.read_managed');
    $permissions->require($user['id'], 'assessment.read_managed');

    $service = new TeacherGradingService(new TeacherGradingRepository($pdo));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selectedActivityId = isset($_POST['activityId']) ? trim((string) $_POST['activityId']) : null;
        $search = isset($_POST['q']) ? trim((string) $_POST['q']) : '';
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $permissions->require($user['id'], 'assessment.update_managed');
        $savedActivityId = $service->save($user['id'], $_POST);

        $_SESSION['teacherGradingFlash'] = 'Đã lưu đánh giá cho học viên.';
        $redirectQuery = ['activityId' => $savedActivityId];
        if (isset($_POST['q']) && trim((string) $_POST['q']) !== '') {
            $redirectQuery['q'] = trim((string) $_POST['q']);
        }
        header('Location: ./index.php?' . http_build_query($redirectQuery));
        exit;
    }

    $data = $service->pageData($user['id'], $selectedActivityId, $search);
    if (($selectedActivityId === null || $selectedActivityId === '') && $data['activities'] !== []) {
        $selectedActivityId = (string) $data['activities'][0]['id'];
        $data = $service->pageData($user['id'], $selectedActivityId, $search);
    }
} catch (TeacherGradingConflictException) {
    $error = 'Đánh giá này vừa được cập nhật ở nơi khác. Vui lòng tải lại trang và thử lại.';
} catch (ApiException $exception) {
    $error = $exception->getMessage();
} catch (Throwable) {
    $bootError = 'Chưa thể tải dữ liệu chấm điểm. Vui lòng kiểm tra kết nối và trạng thái migration của database.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $service !== null && $error !== null) {
    try {
        $data = $service->pageData($user['id'], $selectedActivityId, $search);
    } catch (Throwable) {
        // Keep the validation message visible even when the page cannot reload its context.
    }
}

$teacher = $data['teacher'];
$teacherName = trim((string) ($teacher['fullName'] ?? $user['fullName'] ?? 'Giáo viên'));
$teacherInfo = [
    'full_name' => $teacherName !== '' ? $teacherName : 'Giáo viên',
    'role_label' => 'Giáo viên / Hướng dẫn viên',
    'avatar_initials' => teacherGradingInitials($teacherName),
    'notification_count' => 0,
];

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$activityStatusLabels = [
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

function teacherGradingInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if ($parts === []) {
        return 'GV';
    }

    $first = (string) ($parts[0] ?? '');
    $last = (string) ($parts[count($parts) - 1] ?? '');
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) ?: 'GV';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($pageTitle); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/teacher.css">
</head>
<body class="teacher-dashboard teacher-grading-page">
    <div class="teacher-layout">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

            <main class="teacher-body">
                <div class="teacher-container">
                    <section class="teacher-section-box teacher-grading-intro">
                        <div>
                            <span class="teacher-section-box__eyebrow">ĐÁNH GIÁ HỌC VIÊN</span>
                            <h2 class="teacher-grading-intro__title">Chấm điểm theo hoạt động</h2>
                            <p class="teacher-grading-intro__description">Chọn hoạt động do bạn phụ trách để xem học viên đã đăng ký và cập nhật đánh giá.</p>
                        </div>
                        <span class="teacher-chip teacher-chip--primary">Phạm vi: hoạt động của tôi</span>
                    </section>

                    <?php if ($flash): ?>
                        <div class="teacher-grading-flash teacher-grading-flash--success" role="status"><?= $escape($flash); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="teacher-grading-flash teacher-grading-flash--error" role="alert"><?= $escape($error); ?></div>
                    <?php endif; ?>
                    <?php if ($bootError): ?>
                        <div class="teacher-grading-flash teacher-grading-flash--error" role="alert"><?= $escape($bootError); ?></div>
                    <?php endif; ?>

                    <section class="teacher-section-box teacher-grading-toolbar" aria-label="Bộ lọc chấm điểm">
                        <form method="get" class="teacher-grading-toolbar__form">
                            <label class="teacher-grading-field teacher-grading-field--activity">
                                <span>Hoạt động phụ trách</span>
                                <select name="activityId" onchange="this.form.submit()">
                                    <option value="">Chọn hoạt động</option>
                                    <?php foreach ($data['activities'] as $activity): ?>
                                        <option value="<?= $escape($activity['id']); ?>" <?= (string) ($data['selectedActivity']['id'] ?? '') === (string) $activity['id'] ? 'selected' : ''; ?>>
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

                    <?php if ($data['selectedActivity'] !== null): ?>
                        <section class="teacher-section-box teacher-grading-activity-summary">
                            <div>
                                <span class="teacher-section-box__eyebrow">HOẠT ĐỘNG ĐANG CHỌN</span>
                                <h2 class="teacher-grading-activity-summary__title"><?= $escape($data['selectedActivity']['title']); ?></h2>
                                <p class="teacher-grading-activity-summary__meta">
                                    <?= $escape($data['selectedActivity']['category']); ?> ·
                                    <?= $escape($activityStatusLabels[$data['selectedActivity']['status']] ?? $data['selectedActivity']['status']); ?> ·
                                    <?= number_format(count($data['students'])); ?> học viên hiển thị
                                </p>
                            </div>
                            <div class="teacher-grading-activity-summary__range">
                                <span>Sức chứa</span>
                                <strong><?= $escape($data['selectedActivity']['capacity']); ?></strong>
                            </div>
                        </section>

                        <?php if ($data['students'] === []): ?>
                            <div class="teacher-empty-state teacher-grading-empty">
                                <div class="teacher-empty-state__icon" aria-hidden="true">✓</div>
                                <h3 class="teacher-empty-state__title">Chưa có học viên phù hợp</h3>
                                <p class="teacher-empty-state__desc">Hoạt động này chưa có đăng ký ở trạng thái approved hoặc attended, hoặc bộ lọc không có kết quả.</p>
                            </div>
                        <?php else: ?>
                            <div class="teacher-grading-list">
                                <?php foreach ($data['students'] as $student):
                                    $assessmentStatus = $student['assessmentStatus'] ?? null;
                                    $assessmentLabel = $assessmentStatusLabels[$assessmentStatus] ?? 'Chưa chấm';
                                ?>
                                    <article class="teacher-section-box teacher-grading-card">
                                        <div class="teacher-grading-card__header">
                                            <div>
                                                <h3 class="teacher-grading-card__title"><?= $escape($student['fullName']); ?></h3>
                                                <p class="teacher-grading-card__meta"><?= $escape($student['email']); ?> · Đăng ký: <?= $escape($registrationStatusLabels[$student['registrationStatus']] ?? $student['registrationStatus']); ?></p>
                                            </div>
                                            <span class="teacher-status-pill <?= $assessmentStatus === 'published' ? 'teacher-status-pill--positive' : ($assessmentStatus === 'draft' ? 'teacher-status-pill--warning' : ''); ?>">
                                                <?= $escape($assessmentLabel); ?>
                                            </span>
                                        </div>

                                        <form method="post" class="teacher-grading-form">
                                            <input type="hidden" name="csrfToken" value="<?= $escape($session->csrfToken()); ?>">
                                            <input type="hidden" name="activityId" value="<?= $escape($data['selectedActivity']['id']); ?>">
                                            <input type="hidden" name="studentId" value="<?= $escape($student['studentId']); ?>">
                                            <input type="hidden" name="assessmentId" value="<?= $escape($student['assessmentId'] ?? ''); ?>">
                                            <input type="hidden" name="expectedVersion" value="<?= $escape($student['assessmentVersion'] ?? 0); ?>">
                                            <input type="hidden" name="q" value="<?= $escape($search); ?>">

                                            <div class="teacher-grading-form__topline">
                                                <label class="teacher-grading-field">
                                                    <span>Trạng thái đánh giá</span>
                                                    <select name="assessmentStatus" required>
                                                        <?php foreach ($assessmentStatusLabels as $status => $label): ?>
                                                            <option value="<?= $escape($status); ?>" <?= ($assessmentStatus ?? 'draft') === $status ? 'selected' : ''; ?>><?= $escape($label); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label class="teacher-grading-field">
                                                    <span>Điểm tổng kết / 100</span>
                                                    <input type="number" name="overallScore" min="0" max="100" step="0.01" value="<?= $escape($student['overallScore'] ?? ''); ?>">
                                                </label>
                                            </div>

                                            <?php if ($data['criteria'] !== []): ?>
                                                <fieldset class="teacher-grading-criteria">
                                                    <legend>Điểm tiêu chí</legend>
                                                    <div class="teacher-grading-criteria__grid">
                                                        <?php foreach ($data['criteria'] as $criterion): ?>
                                                            <label class="teacher-grading-field">
                                                                <span><?= $escape($criterion['name']); ?> (<?= $escape($criterion['minScore']); ?>–<?= $escape($criterion['maxScore']); ?>)</span>
                                                                <input type="number" name="criteria[<?= $escape($criterion['id']); ?>]" min="<?= $escape($criterion['minScore']); ?>" max="<?= $escape($criterion['maxScore']); ?>" step="0.01" value="<?= $escape($student['criteriaScores'][$criterion['id']] ?? ''); ?>">
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </fieldset>
                                            <?php endif; ?>

                                            <label class="teacher-grading-field teacher-grading-field--comment">
                                                <span>Nhận xét</span>
                                                <textarea name="comment" rows="3" maxlength="1000" placeholder="Nhận xét tiến bộ, điểm mạnh hoặc hướng cải thiện"><?= $escape($student['comment'] ?? ''); ?></textarea>
                                            </label>

                                            <div class="teacher-grading-form__actions">
                                                <span class="teacher-grading-form__updated">
                                                    <?= $student['assessmentUpdatedAt'] ? 'Cập nhật ' . $escape($student['assessmentUpdatedAt']) : 'Chưa có lần chấm'; ?>
                                                </span>
                                                <button type="submit" class="teacher-grading-button teacher-grading-button--primary">Lưu đánh giá</button>
                                            </div>
                                        </form>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($data['activities'] === []): ?>
                        <div class="teacher-empty-state teacher-grading-empty">
                            <div class="teacher-empty-state__icon" aria-hidden="true">−</div>
                            <h3 class="teacher-empty-state__title">Chưa có hoạt động phụ trách</h3>
                            <p class="teacher-empty-state__desc">Khi có hoạt động với createdByTeacherId là hồ sơ của bạn, hoạt động sẽ xuất hiện tại đây.</p>
                        </div>
                    <?php else: ?>
                        <div class="teacher-empty-state teacher-grading-empty">
                            <div class="teacher-empty-state__icon" aria-hidden="true">↗</div>
                            <h3 class="teacher-empty-state__title">Chọn một hoạt động để bắt đầu</h3>
                            <p class="teacher-empty-state__desc">Danh sách chỉ bao gồm hoạt động do giáo viên hiện tại phụ trách.</p>
                        </div>
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
