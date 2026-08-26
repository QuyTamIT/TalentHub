<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;

$context = (new SchoolAppContext())->boot();
$service = $context['service'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$error = null;
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    try {
        $service->assignInternshipMentor($userId, (string) ($_POST['applicationId'] ?? ''), (string) ($_POST['mentorTeacherId'] ?? ''));
        $flash = 'Đã gán giáo viên hướng dẫn.';
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    }
}

$oversight = $service->internshipOversight($userId);
$teachers = $service->teachers($userId, 100, 0);
$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/internships.php';
$pageTitle = 'Giám sát thực tập';
$labels = ['submitted' => 'Đã nộp', 'reviewing' => 'Đang xét', 'interview' => 'Phỏng vấn', 'accepted' => 'Đã nhận', 'declined' => 'Từ chối', 'withdrawn' => 'Đã rút'];
ob_start();
?>
<?php $pageDescription = 'Theo dõi projection tối thiểu của đơn thuộc học sinh trong trường; không hiển thị ghi chú tuyển dụng hoặc snapshot riêng tư.'; include __DIR__ . '/includes/page-banner.php'; ?>
<?php if ($flash): ?><div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error): ?><div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
<div class="school-section-box" style="margin-bottom:1rem">
    <div style="display:flex;gap:1rem;flex-wrap:wrap"><?php foreach ($oversight['summary'] as $status => $count): ?><div><strong><?= (int) $count; ?></strong> <?= htmlspecialchars($labels[$status] ?? $status); ?></div><?php endforeach; ?></div>
</div>
<div class="school-section-box">
<?php if ($oversight['items'] === []): ?><p>Chưa có đơn thực tập thuộc trường.</p><?php else: ?>
<table class="school-class-table"><thead><tr><th>Học sinh</th><th>Vị trí</th><th>Doanh nghiệp</th><th>Trạng thái</th><th>Mentor</th></tr></thead><tbody>
<?php foreach ($oversight['items'] as $item): ?><tr>
<td><strong><?= htmlspecialchars((string) $item['studentName']); ?></strong></td>
<td><?= htmlspecialchars((string) $item['postTitle']); ?></td>
<td><?= htmlspecialchars((string) $item['enterpriseName']); ?></td>
<td><?= htmlspecialchars($labels[(string) $item['status']] ?? (string) $item['status']); ?></td>
<td><?php if (in_array($item['status'], ['interview', 'accepted'], true)): ?>
<form method="post"><input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="applicationId" value="<?= htmlspecialchars((string) $item['id']); ?>"><select name="mentorTeacherId" onchange="this.form.submit()" required><option value="">Chọn giáo viên</option><?php foreach ($teachers as $teacher): ?><option value="<?= htmlspecialchars((string) $teacher['id']); ?>" <?= ($item['mentorTeacherId'] ?? '') === $teacher['id'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) $teacher['fullName']); ?></option><?php endforeach; ?></select></form>
<?php else: ?><?= htmlspecialchars((string) ($item['mentorName'] ?? 'Chưa thể gán')); ?><?php endif; ?></td>
</tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
</div>
<?php
$pageBody = ob_get_clean();
$extraStyles = '';
require __DIR__ . '/includes/layout.php';
