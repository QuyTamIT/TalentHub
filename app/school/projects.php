<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;
use TalentHub\Support\Uuid;

$context = (new SchoolAppContext())->boot();
$service = $context['projects'];
$schoolService = $context['service'];
$session = $context['session'];
$userId = $context['user']['id'];
$flash = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $action = (string) ($_POST['action'] ?? 'create');
        if ($action === 'create') {
            $service->createProject($userId, $_POST, RequestId::generate());
            $flash = 'Đã tạo dự án mới.';
        } elseif ($action === 'status') {
            $projectId = trim((string) ($_POST['projectId'] ?? ''));
            if (!Uuid::isValid($projectId)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Mã dự án không hợp lệ.');
            }
            $service->updateProject($userId, $projectId, ['status' => (string) ($_POST['status'] ?? '')]);
            $flash = 'Đã cập nhật trạng thái dự án.';
        }
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Không thể cập nhật dự án: ' . $exception->getMessage();
    }
}

$projects = $service->listProjects($userId)['items'];
$teachers = $schoolService->teachers($userId, 100, 0);
$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? 'Trung học',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/projects.php';
$pageTitle = 'Dự án nhà trường';
$statusLabels = ['draft' => 'Bản nháp', 'in_progress' => 'Đang triển khai', 'completed' => 'Hoàn thành', 'archived' => 'Lưu trữ'];

ob_start();
?>
<?php $pageDescription = 'Tạo dự án thật, chỉ định giáo viên hướng dẫn và theo dõi tài trợ.'; include __DIR__ . '/includes/page-banner.php'; ?>
<?php if ($flash): ?><div class="school-flash school-flash--success" role="status"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error): ?><div class="school-flash school-flash--error" role="alert"><?= htmlspecialchars($error); ?></div><?php endif; ?>

<div class="school-grid-2col">
    <div class="school-section-box">
        <div class="school-section-box__header"><h2 class="school-section-box__title">Tạo dự án</h2></div>
        <form method="post" class="school-form" novalidate>
            <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="create">
            <div class="school-form__grid">
                <label class="school-form__field"><span>Tên dự án <em>*</em></span><input name="title" maxlength="255" required></label>
                <label class="school-form__field"><span>Danh mục</span><input name="category" maxlength="100" value="general"></label>
                <label class="school-form__field"><span>Giáo viên hướng dẫn</span><select name="mentorTeacherId"><option value="">Chưa chỉ định</option><?php foreach ($teachers as $teacher): ?><option value="<?= htmlspecialchars($teacher['id']); ?>"><?= htmlspecialchars($teacher['fullName']); ?></option><?php endforeach; ?></select></label>
                <label class="school-form__field"><span>Mục tiêu tài trợ</span><input type="number" name="fundingGoal" min="1" step="0.01"></label>
                <label class="school-form__field"><span>Bắt đầu</span><input type="date" name="startAt"></label>
                <label class="school-form__field"><span>Kết thúc</span><input type="date" name="endAt"></label>
                <label class="school-form__field school-form__field--full"><span>Mô tả</span><textarea name="description" rows="4"></textarea></label>
                <input type="hidden" name="status" value="in_progress">
            </div>
            <div class="school-form__actions"><button class="btn btn-primary" type="submit">Tạo dự án</button></div>
        </form>
    </div>
    <div class="school-section-box">
        <div class="school-section-box__header"><h2 class="school-section-box__title">Danh sách dự án</h2></div>
        <?php if ($projects === []): ?><p style="color:var(--text-muted);">Chưa có dự án nào.</p><?php else: ?>
            <?php foreach ($projects as $project): ?>
                <article style="padding:1rem 0;border-bottom:1px solid var(--border);">
                    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;">
                        <div><strong><?= htmlspecialchars((string) $project['title']); ?></strong><div style="font-size:.8125rem;color:var(--text-muted);">Đã tài trợ <?= number_format((float) $project['raisedAmount'], 0, ',', '.'); ?> / <?= number_format((float) ($project['fundingGoal'] ?? 0), 0, ',', '.'); ?> · <?= (int) $project['membersCount']; ?> thành viên</div></div>
                        <span class="school-class-badge school-class-badge--neutral"><?= htmlspecialchars($statusLabels[(string) $project['status']] ?? (string) $project['status']); ?></span>
                    </div>
                    <form method="post" style="display:flex;gap:.5rem;margin-top:.75rem;align-items:center;">
                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="projectId" value="<?= htmlspecialchars((string) $project['id']); ?>">
                        <select name="status" aria-label="Trạng thái dự án"><?php foreach ($statusLabels as $code => $label): ?><option value="<?= $code; ?>"<?= $project['status'] === $code ? ' selected' : ''; ?>><?= htmlspecialchars($label); ?></option><?php endforeach; ?></select>
                        <button class="btn btn-sm btn-outline" type="submit">Cập nhật</button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php
$pageBody = ob_get_clean();
$extraStyles = '';
require __DIR__ . '/includes/layout.php';
