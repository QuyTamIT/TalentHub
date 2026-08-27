<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

$context = (new SchoolAppContext())->boot();
$service = $context['partnerships'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$error = null;
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
    try {
        $partnershipId = (string) ($_POST['partnershipId'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        if (!Uuid::isValid($partnershipId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã quan hệ đối tác không hợp lệ.');
        }
        $service->reviewPartnership($userId, $partnershipId, ['status' => $status]);
        $flash = match ($status) {
            'approved' => 'Đã chấp thuận quan hệ đối tác.',
            'rejected' => 'Đã từ chối yêu cầu hợp tác.',
            'suspended' => 'Đã tạm dừng quan hệ đối tác.',
            default => 'Đã cập nhật quan hệ đối tác.',
        };
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    }
}

$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
if (!in_array($statusFilter, [null, '', 'pending', 'approved', 'rejected', 'suspended'], true)) {
    $statusFilter = null;
}
$partnerships = $service->listSchoolPartnerships($userId, $statusFilter)['items'];
$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/partnerships.php';
$pageTitle = 'Đối tác doanh nghiệp';
$labels = ['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Đã từ chối', 'suspended' => 'Tạm dừng'];

ob_start();
?>
<?php $pageDescription = 'Xét duyệt doanh nghiệp được phép kết nối và nhắm mục tiêu cơ hội tới trường.'; include __DIR__ . '/includes/page-banner.php'; ?>
<?php if ($flash): ?><div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error): ?><div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
<div class="school-section-box">
    <div class="school-section-box__header">
        <h2 class="school-section-box__title">Quan hệ hợp tác</h2>
        <form method="get"><select name="status" onchange="this.form.submit()"><option value="">Tất cả trạng thái</option><?php foreach ($labels as $value => $label): ?><option value="<?= $value; ?>" <?= $statusFilter === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option><?php endforeach; ?></select></form>
    </div>
    <?php if ($partnerships === []): ?>
        <p>Chưa có yêu cầu hợp tác phù hợp.</p>
    <?php else: ?>
        <table class="school-class-table"><thead><tr><th>Doanh nghiệp</th><th>Ngành</th><th>Trạng thái</th><th>Cập nhật</th><th style="text-align:right">Thao tác</th></tr></thead><tbody>
        <?php foreach ($partnerships as $item): ?><tr>
            <td><strong><?= htmlspecialchars((string) $item['enterpriseName']); ?></strong><?php if (!empty($item['website'])): ?><br><a href="<?= htmlspecialchars((string) $item['website']); ?>" rel="noopener" target="_blank">Website</a><?php endif; ?></td>
            <td><?= htmlspecialchars((string) ($item['industry'] ?? '—')); ?></td>
            <td><span class="school-class-badge school-class-badge--neutral"><?= htmlspecialchars($labels[(string) $item['status']] ?? (string) $item['status']); ?></span></td>
            <td><?= htmlspecialchars((string) $item['updatedAt']); ?> UTC</td>
            <td style="text-align:right"><div style="display:flex;gap:.4rem;justify-content:flex-end">
                <?php foreach ((($item['status'] ?? '') === 'pending' ? ['approved' => 'Chấp thuận', 'rejected' => 'Từ chối'] : (($item['status'] ?? '') === 'approved' ? ['suspended' => 'Tạm dừng'] : [])) as $status => $label): ?>
                <form method="post"><input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="partnershipId" value="<?= htmlspecialchars((string) $item['id']); ?>"><input type="hidden" name="status" value="<?= $status; ?>"><button class="btn btn-sm btn-outline" type="submit" data-confirm="Xác nhận cập nhật quan hệ đối tác?"><?= htmlspecialchars($label); ?></button></form>
                <?php endforeach; ?>
            </div></td>
        </tr><?php endforeach; ?>
        </tbody></table>
    <?php endif; ?>
</div>
<?php
$pageBody = ob_get_clean();
$extraStyles = '';
require __DIR__ . '/includes/layout.php';
