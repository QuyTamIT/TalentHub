<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Uuid;

$context = (new SchoolAppContext())->boot();
$service = $context['partnerships'];
$session = $context['session'];
$userId = $context['user']['id'];
$flash = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $partnershipId = trim((string) ($_POST['partnershipId'] ?? ''));
        if (!Uuid::isValid($partnershipId)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Mã quan hệ đối tác không hợp lệ.');
        }
        $updated = $service->reviewPartnership($userId, $partnershipId, [
            'status' => (string) ($_POST['status'] ?? ''),
        ]);
        $flash = 'Đã cập nhật quan hệ với ' . (string) ($updated['enterpriseName'] ?? 'doanh nghiệp') . '.';
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Không thể cập nhật quan hệ đối tác: ' . $exception->getMessage();
    }
}

$allowedStatuses = ['pending', 'approved', 'rejected', 'suspended'];
$statusFilter = trim((string) ($_GET['status'] ?? ''));
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}
$partnerships = $service->listSchoolPartnerships($userId, $statusFilter === '' ? null : $statusFilter)['items'];

$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? 'Trung học',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/partnerships.php';
$pageTitle = 'Đối tác doanh nghiệp';
$labels = ['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Đã từ chối', 'suspended' => 'Tạm dừng'];

ob_start();
?>
<?php $pageDescription = 'Duyệt và quản lý doanh nghiệp được phép hợp tác với nhà trường.'; include __DIR__ . '/includes/page-banner.php'; ?>
<?php if ($flash): ?><div class="school-flash school-flash--success" role="status"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error): ?><div class="school-flash school-flash--error" role="alert"><?= htmlspecialchars($error); ?></div><?php endif; ?>

<div class="school-section-box">
    <div class="school-section-box__header">
        <h2 class="school-section-box__title">Quan hệ hợp tác</h2>
        <form method="get">
            <select name="status" onchange="this.form.submit()" aria-label="Lọc trạng thái">
                <option value="">Tất cả trạng thái</option>
                <?php foreach ($labels as $code => $label): ?>
                    <option value="<?= $code; ?>"<?= $statusFilter === $code ? ' selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if ($partnerships === []): ?>
        <p style="color:var(--text-muted);">Chưa có yêu cầu hợp tác phù hợp bộ lọc.</p>
    <?php else: ?>
        <table class="school-class-table">
            <thead><tr><th>Doanh nghiệp</th><th>Ngành</th><th>Trạng thái</th><th>Cập nhật</th><th style="text-align:right;">Thao tác</th></tr></thead>
            <tbody>
            <?php foreach ($partnerships as $item): $status = (string) $item['status']; ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) $item['enterpriseName']); ?></strong></td>
                    <td><?= htmlspecialchars((string) ($item['industry'] ?? '—')); ?></td>
                    <td><span class="school-class-badge <?= $status === 'approved' ? 'school-class-badge--success' : ($status === 'pending' ? 'school-class-badge--warning' : 'school-class-badge--neutral'); ?>"><?= htmlspecialchars($labels[$status] ?? $status); ?></span></td>
                    <td><?= htmlspecialchars(substr((string) $item['updatedAt'], 0, 16)); ?></td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:.375rem;justify-content:flex-end;">
                        <?php foreach (($status === 'pending' ? ['approved' => 'Duyệt', 'rejected' => 'Từ chối'] : ($status === 'approved' ? ['suspended' => 'Tạm dừng'] : ['approved' => 'Duyệt lại'])) as $target => $label): ?>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="partnershipId" value="<?= htmlspecialchars((string) $item['id']); ?>">
                                <input type="hidden" name="status" value="<?= $target; ?>">
                                <button type="submit" class="btn btn-sm btn-outline" data-confirm="Xác nhận cập nhật quan hệ đối tác?"><?= htmlspecialchars($label); ?></button>
                            </form>
                        <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
$pageBody = ob_get_clean();
$extraStyles = '';
require __DIR__ . '/includes/layout.php';
