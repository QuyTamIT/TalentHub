<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';
require dirname(__DIR__, 3) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;

$context = (new SchoolAppContext())->boot();
$service = $context['projects'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$error = null;

$projectId = trim((string) ($_GET['id'] ?? $_POST['projectId'] ?? ''));
if ($projectId === '') {
    header('Location: ' . app_href('/app/school/projects/'), true, 302);
    exit;
}

$flash = isset($_SESSION['school_project_flash']) && is_string($_SESSION['school_project_flash'])
    ? $_SESSION['school_project_flash']
    : null;
unset($_SESSION['school_project_flash']);

$statusLabels = [
    'draft' => 'Bản nháp',
    'in_progress' => 'Đang thực hiện',
    'completed' => 'Đã hoàn thành',
    'archived' => 'Lưu trữ',
];

$memberStatusLabels = [
    'pending' => 'Chờ duyệt',
    'active' => 'Đang tham gia',
    'rejected' => 'Đã từ chối',
    'removed' => 'Đã gỡ',
    'left' => 'Đã rời',
];

if (isset($_GET['ajax']) && $_GET['ajax'] === 'members') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $members = $service->listProjectMembers($userId, $projectId);
        echo json_encode(['success' => true, 'members' => $members], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    $action = (string) ($_POST['action'] ?? '');
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

    try {
        if ($action === 'status') {
            $service->updateProject($userId, $projectId, ['status' => $_POST['status'] ?? 'draft']);
            $_SESSION['school_project_flash'] = 'Đã cập nhật trạng thái dự án.';
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            header('Location: ' . app_href('/app/school/projects/detail.php?id=' . urlencode($projectId)), true, 303);
            exit;
        }

        if ($action === 'approve_member') {
            $studentId = (string) ($_POST['studentId'] ?? '');
            $service->updateMemberStatus($userId, $projectId, $studentId, 'active');
            $flashMsg = 'Đã phê duyệt sinh viên tham gia dự án.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => $flashMsg], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['school_project_flash'] = $flashMsg;
            header('Location: ' . app_href('/app/school/projects/detail.php?id=' . urlencode($projectId)), true, 303);
            exit;
        }

        if ($action === 'reject_member') {
            $studentId = (string) ($_POST['studentId'] ?? '');
            $service->updateMemberStatus($userId, $projectId, $studentId, 'rejected');
            $flashMsg = 'Đã từ chối đơn đăng ký tham gia dự án.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => $flashMsg], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['school_project_flash'] = $flashMsg;
            header('Location: ' . app_href('/app/school/projects/detail.php?id=' . urlencode($projectId)), true, 303);
            exit;
        }

        if ($action === 'remove_member') {
            $studentId = (string) ($_POST['studentId'] ?? '');
            $service->updateMemberStatus($userId, $projectId, $studentId, 'removed');
            $flashMsg = 'Đã gỡ sinh viên khỏi dự án.';
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'message' => $flashMsg], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $_SESSION['school_project_flash'] = $flashMsg;
            header('Location: ' . app_href('/app/school/projects/detail.php?id=' . urlencode($projectId)), true, 303);
            exit;
        }

        throw new ApiException(422, 'VALIDATION_FAILED', 'Hành động không hợp lệ.');
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
        if ($isAjax) {
            http_response_code($exception->status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } catch (Throwable $exception) {
        $error = 'Không thể cập nhật: ' . $exception->getMessage();
        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $error], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

try {
    $project = $service->getProject($userId, $projectId);
    $members = $service->listProjectMembers($userId, $projectId);
} catch (ApiException $exception) {
    $_SESSION['school_project_flash'] = $exception->getMessage();
    header('Location: ' . app_href('/app/school/projects/'), true, 302);
    exit;
}

$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/projects/';
$pageTitle = (string) $project['title'];
$pageDescription = 'Chi tiết dự án, trạng thái, tài trợ và quản lý thành viên.';
$pageActions = '<a class="btn btn-outline" href="' . htmlspecialchars(app_href('/app/school/projects/'), ENT_QUOTES, 'UTF-8') . '">← Danh sách</a>';

$formatDate = static function (mixed $value): string {
    if (!is_string($value) || trim($value) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value))->format('d/m/Y');
    } catch (Throwable) {
        return '—';
    }
};

$formatDateTime = static function (mixed $value): string {
    if (!is_string($value) || trim($value) === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))
            ->format('d/m/Y H:i');
    } catch (Throwable) {
        return '—';
    }
};

$csrfToken = $session->csrfToken();
$detailAjaxUrl = app_href('/app/school/projects/detail.php?id=' . urlencode($projectId));

ob_start();
include dirname(__DIR__) . '/includes/page-banner.php';
?>

<?php if ($flash): ?>
    <div class="school-flash school-flash--success">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <?= htmlspecialchars($flash); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="school-flash school-flash--error">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <?= htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="school-projects-layout" style="grid-template-columns:1fr;">
    <section class="school-section-box">
        <div class="school-section-box__header school-section-box__header--bordered">
            <h2 class="school-section-box__title">Thông tin dự án</h2>
            <span class="school-project-status-badge school-project-status-badge--<?= htmlspecialchars((string) $project['status']); ?>">
                <?= htmlspecialchars($statusLabels[$project['status']] ?? (string) $project['status']); ?>
            </span>
        </div>

        <div class="school-form__grid" style="padding:0.5rem 0 1rem;">
            <div class="school-form__field">
                <span>Lĩnh vực</span>
                <strong><?= htmlspecialchars(trim((string) ($project['category'] ?? '')) !== '' ? (string) $project['category'] : '—'); ?></strong>
            </div>
            <div class="school-form__field">
                <span>Đề tài</span>
                <strong><?= htmlspecialchars(trim((string) ($project['topic'] ?? '')) !== '' ? (string) $project['topic'] : '—'); ?></strong>
            </div>
            <div class="school-form__field">
                <span>Giảng viên hướng dẫn</span>
                <strong><?= htmlspecialchars(trim((string) ($project['mentorName'] ?? '')) !== '' ? (string) $project['mentorName'] : 'Chưa phân công'); ?></strong>
            </div>
            <div class="school-form__field">
                <span>Thời gian</span>
                <strong><?= htmlspecialchars($formatDate($project['startAt'] ?? null)); ?> → <?= htmlspecialchars($formatDate($project['endAt'] ?? null)); ?></strong>
            </div>
            <div class="school-form__field school-form__field--full">
                <span>Mô tả</span>
                <p style="margin:0.35rem 0 0;white-space:pre-wrap;color:var(--text-primary);"><?= htmlspecialchars(trim((string) ($project['description'] ?? '')) !== '' ? (string) $project['description'] : 'Chưa có mô tả.'); ?></p>
            </div>
        </div>

        <?php if (!empty($project['fundingGoal']) && (float) $project['fundingGoal'] > 0): ?>
            <div class="school-project-card__stats" style="margin-bottom:1rem;">
                <div class="school-project-card__stat school-project-card__stat--funding">
                    <span style="font-weight:700;font-size:0.7rem;padding:0.15rem 0.4rem;background:rgba(16,185,129,0.12);color:#059669;border-radius:4px;">VNĐ</span>
                    <span>
                        <strong><?= number_format((float) ($project['raisedAmount'] ?? 0), 0, ',', '.'); ?></strong>
                        /
                        <?= number_format((float) $project['fundingGoal'], 0, ',', '.'); ?>
                        <small>(<?= (int) ($project['sponsorsCount'] ?? 0); ?> nhà tài trợ)</small>
                    </span>
                </div>
                <div class="school-project-card__stat">
                    <span><?= (int) ($project['membersCount'] ?? 0); ?> thành viên active</span>
                    <?php if ((int) ($project['pendingMembersCount'] ?? 0) > 0): ?>
                        <span class="school-badge" style="background:#FEF3C7;color:#B45309;border:1px solid #FCD34D;font-size:0.7rem;font-weight:700;padding:2px 8px;border-radius:12px;">
                            <?= (int) $project['pendingMembersCount']; ?> chờ duyệt
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;">
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="projectId" value="<?= htmlspecialchars($projectId, ENT_QUOTES, 'UTF-8'); ?>">
            <label style="font-size:0.85rem;color:var(--text-secondary);">Cập nhật trạng thái:</label>
            <select name="status" class="school-status-select typeui-select typeui-select--compact typeui-select--status" onchange="this.form.submit()">
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= $value; ?>" <?= $project['status'] === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </section>

    <section class="school-section-box">
        <div class="school-section-box__header school-section-box__header--bordered">
            <h2 class="school-section-box__title">Thành viên &amp; Đơn đăng ký</h2>
            <span class="school-badge school-badge--info"><?= count($members) ?> hồ sơ</span>
        </div>

        <?php if ($members === []): ?>
            <div class="school-empty-state">
                <p>Chưa có thành viên hoặc đơn đăng ký nào.</p>
                <small>Sinh viên đăng ký tham gia sẽ xuất hiện tại đây để phê duyệt.</small>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem;">
                    <thead>
                        <tr style="border-bottom:2px solid #E2E8F0;text-align:left;color:#64748B;font-size:0.75rem;text-transform:uppercase;letter-spacing:0.05em;">
                            <th style="padding:0.75rem 0.5rem;">Sinh viên</th>
                            <th style="padding:0.75rem 0.5rem;">Lớp</th>
                            <th style="padding:0.75rem 0.5rem;">Ngày gửi</th>
                            <th style="padding:0.75rem 0.5rem;">Trạng thái</th>
                            <th style="padding:0.75rem 0.5rem;text-align:right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody id="membersTableBody">
                        <?php foreach ($members as $member): ?>
                            <?php $mStatus = (string) ($member['status'] ?? ''); ?>
                            <tr style="border-bottom:1px solid #F1F5F9;" data-student-id="<?= htmlspecialchars((string) $member['studentId'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td style="padding:0.75rem 0.5rem;">
                                    <div style="font-weight:600;color:#1E293B;"><?= htmlspecialchars((string) ($member['studentName'] ?? 'Sinh viên')); ?></div>
                                    <div style="font-size:0.75rem;color:#64748B;"><?= htmlspecialchars((string) ($member['studentEmail'] ?? '')); ?></div>
                                </td>
                                <td style="padding:0.75rem 0.5rem;color:#475569;"><?= htmlspecialchars((string) ($member['className'] ?? '—')); ?></td>
                                <td style="padding:0.75rem 0.5rem;color:#64748B;"><?= htmlspecialchars($formatDateTime($member['createdAt'] ?? null)); ?></td>
                                <td style="padding:0.75rem 0.5rem;"><?= htmlspecialchars($memberStatusLabels[$mStatus] ?? $mStatus); ?></td>
                                <td style="padding:0.75rem 0.5rem;text-align:right;">
                                    <div style="display:flex;justify-content:flex-end;gap:6px;flex-wrap:wrap;">
                                        <?php if ($mStatus === 'pending'): ?>
                                            <button type="button" class="btn btn-sm" style="background:#10B981;color:#fff;border:none;" onclick="submitMemberStatus('<?= htmlspecialchars((string) $member['studentId'], ENT_QUOTES); ?>','approve_member')">Chấp thuận</button>
                                            <button type="button" class="btn btn-sm" style="background:#EF4444;color:#fff;border:none;" onclick="submitMemberStatus('<?= htmlspecialchars((string) $member['studentId'], ENT_QUOTES); ?>','reject_member')">Từ chối</button>
                                        <?php elseif ($mStatus === 'active'): ?>
                                            <button type="button" class="btn btn-sm btn-outline" style="border-color:#EF4444;color:#EF4444;" onclick="submitMemberStatus('<?= htmlspecialchars((string) $member['studentId'], ENT_QUOTES); ?>','remove_member')">Gỡ khỏi dự án</button>
                                        <?php elseif ($mStatus === 'rejected' || $mStatus === 'removed'): ?>
                                            <button type="button" class="btn btn-sm btn-outline" style="border-color:#10B981;color:#10B981;" onclick="submitMemberStatus('<?= htmlspecialchars((string) $member['studentId'], ENT_QUOTES); ?>','approve_member')">Duyệt lại</button>
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

<?php
$pageBody = ob_get_clean();
$extraStyles = '';
$csrfJs = json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$postUrlJs = json_encode($detailAjaxUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$extraScripts = <<<HTML
<script>
const PROJECT_CSRF = {$csrfJs};
const PROJECT_POST_URL = {$postUrlJs};

async function submitMemberStatus(studentId, action) {
    if (!studentId || !action) return;
    const formData = new FormData();
    formData.append('csrfToken', PROJECT_CSRF);
    formData.append('action', action);
    formData.append('projectId', new URL(PROJECT_POST_URL, window.location.href).searchParams.get('id') || '');
    formData.append('studentId', studentId);
    formData.append('ajax', '1');
    try {
        const res = await fetch(PROJECT_POST_URL, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            window.location.reload();
            return;
        }
        alert(data.message || 'Không thể cập nhật');
    } catch (err) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = PROJECT_POST_URL;
        form.innerHTML = '<input type="hidden" name="csrfToken" value="' + PROJECT_CSRF + '">'
            + '<input type="hidden" name="action" value="' + action + '">'
            + '<input type="hidden" name="studentId" value="' + studentId + '">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
HTML;
require dirname(__DIR__) . '/includes/layout.php';
