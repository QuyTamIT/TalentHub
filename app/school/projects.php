<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

$context = (new SchoolAppContext())->boot();
$service = $context['projects'];
$dashboard = $context['service'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$error = null;
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    try {
        $action = (string) ($_POST['action'] ?? 'create');
        if ($action === 'create') {
            $service->createProject($userId, [
                'title' => $_POST['title'] ?? '', 'category' => $_POST['category'] ?? 'general',
                'mentorTeacherId' => $_POST['mentorTeacherId'] ?? null, 'description' => $_POST['description'] ?? '',
                'fundingGoal' => $_POST['fundingGoal'] ?? null, 'startAt' => $_POST['startAt'] ?? null,
                'endAt' => $_POST['endAt'] ?? null, 'status' => $_POST['status'] ?? 'draft',
            ], RequestId::generate());
            $flash = 'Đã tạo dự án mới.';
        } elseif ($action === 'status') {
            $service->updateProject($userId, (string) ($_POST['projectId'] ?? ''), ['status' => $_POST['status'] ?? 'draft']);
            $flash = 'Đã cập nhật trạng thái dự án.';
        }
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Không thể cập nhật dự án: ' . $exception->getMessage();
    }
}

$projects = $service->listProjects($userId)['items'];
$teachers = $dashboard->teachers($userId, 100, 0);
$schoolInfo = ['name' => $context['school']['name'], 'logo_initials' => mb_substr($context['school']['name'], 0, 2), 'level' => $context['school']['level'] ?? '', 'district' => $context['school']['address'] ?? '', 'academic_year' => $context['school']['academicYear'] ?? ''];
$currentRoute = '/app/school/projects.php';
$pageTitle = 'Dự án nhà trường';
$statusLabels = ['draft' => 'Nháp', 'in_progress' => 'Đang thực hiện', 'completed' => 'Hoàn thành', 'archived' => 'Lưu trữ'];
ob_start();
?>
<?php $pageDescription = 'Tạo dự án thật, chỉ định giáo viên hướng dẫn và theo dõi tài trợ đã thanh toán.'; include __DIR__ . '/includes/page-banner.php'; ?>
<?php if ($flash): ?><div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error): ?><div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
<div class="school-grid-2col">
<section class="school-section-box"><div class="school-section-box__header"><h2 class="school-section-box__title">Tạo dự án</h2></div>
<form method="post" class="school-form"><input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="create">
<div class="school-form__grid">
<label class="school-form__field"><span>Tên dự án</span><input name="title" maxlength="255" required></label>
<label class="school-form__field"><span>Danh mục</span><input name="category" maxlength="100" value="general"></label>
<label class="school-form__field"><span>Giáo viên hướng dẫn</span><select name="mentorTeacherId"><option value="">Chưa chỉ định</option><?php foreach ($teachers as $teacher): ?><option value="<?= htmlspecialchars((string) $teacher['id']); ?>"><?= htmlspecialchars((string) $teacher['fullName']); ?></option><?php endforeach; ?></select></label>
<label class="school-form__field"><span>Mục tiêu tài trợ (VND)</span><input name="fundingGoal" type="number" min="1" step="0.01"></label>
<label class="school-form__field"><span>Bắt đầu</span><input name="startAt" type="date"></label><label class="school-form__field"><span>Kết thúc</span><input name="endAt" type="date"></label>
<label class="school-form__field"><span>Trạng thái</span><select name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= $value; ?>"><?= htmlspecialchars($label); ?></option><?php endforeach; ?></select></label>
<label class="school-form__field school-form__field--full"><span>Mô tả</span><textarea name="description" rows="4" maxlength="5000"></textarea></label>
</div><div class="school-form__actions"><button class="btn btn-primary" type="submit">Tạo dự án</button></div></form></section>
<section class="school-section-box"><div class="school-section-box__header"><h2 class="school-section-box__title">Danh sách dự án</h2></div>
<?php if ($projects === []): ?><p>Chưa có dự án.</p><?php else: ?><table class="school-class-table"><thead><tr><th>Dự án</th><th>Tiến độ tài trợ</th><th>Thành viên</th><th>Trạng thái</th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr>
<td><strong><?= htmlspecialchars((string) $project['title']); ?></strong><br><small><?= htmlspecialchars((string) ($project['category'] ?? 'general')); ?></small></td>
<td><?= number_format((float) ($project['raisedAmount'] ?? 0), 0, ',', '.'); ?> / <?= number_format((float) ($project['fundingGoal'] ?? 0), 0, ',', '.'); ?> ₫<br><small><?= (int) ($project['sponsorsCount'] ?? 0); ?> nhà tài trợ</small></td>
<td><?= (int) ($project['membersCount'] ?? 0); ?></td>
<td><form method="post"><input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="projectId" value="<?= htmlspecialchars((string) $project['id']); ?>"><select name="status" onchange="this.form.submit()"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= $value; ?>" <?= $project['status'] === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option><?php endforeach; ?></select></form></td>
</tr><?php endforeach; ?></tbody></table><?php endif; ?></section></div>
<?php
$pageBody = ob_get_clean();
$extraStyles = '<style>.school-grid-2col{align-items:start}</style>';
require __DIR__ . '/includes/layout.php';
