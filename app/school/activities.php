<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

$context = (new SchoolAppContext())->boot();
$service = $context['activityApprovals'];
$session = $context['session'];
$permissions = $context['permissions'];
$userId = (string) $context['user']['id'];
$permissions->require($userId, 'activity.review_school');
$error = null;
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $permissions->require($userId, 'activity.review_school');
        $decision = (string) ($_POST['action'] ?? '');
        $service->review(
            $userId,
            trim((string) ($_POST['activityId'] ?? '')),
            $decision,
            isset($_POST['reason']) ? (string) $_POST['reason'] : null,
            RequestId::make(null),
        );
        $flash = match ($decision) {
            'approve' => 'Đã phê duyệt hoạt động.',
            'request_changes' => 'Đã gửi yêu cầu chỉnh sửa cho Giáo viên.',
            'reject' => 'Đã từ chối hoạt động.',
            default => 'Đã cập nhật hoạt động.',
        };
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Không thể cập nhật hoạt động: ' . $exception->getMessage();
    }
}

$status = trim((string) ($_GET['status'] ?? 'pending_school_review'));
$allowedStatuses = ['draft', 'pending_school_review', 'changes_requested', 'approved', 'rejected'];
if (!in_array($status, $allowedStatuses, true)) $status = 'pending_school_review';
$search = trim((string) ($_GET['q'] ?? ''));
$activities = $service->list($userId, $status, $search);
$statusLabels = ['draft' => 'Nháp', 'pending_school_review' => 'Chờ duyệt', 'changes_requested' => 'Cần chỉnh sửa', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'];
$schoolInfo = [
    'name' => $context['school']['name'], 'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '', 'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/activities.php';
$pageTitle = 'Duyệt hoạt động';

ob_start();
?>
<?php $pageDescription = 'Duyệt nội dung và chính sách đăng ký của hoạt động trước khi Giáo viên công bố cho học viên.'; include __DIR__ . '/includes/page-banner.php'; ?>
<?php if ($flash): ?><div class="school-flash school-flash--success"><?= htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($error): ?><div class="school-flash school-flash--error"><?= htmlspecialchars($error); ?></div><?php endif; ?>
<section class="school-section-box">
    <div class="school-section-box__header">
        <h2 class="school-section-box__title">Hàng đợi duyệt</h2>
        <form method="get" style="display:flex;gap:.5rem">
            <input name="q" value="<?= htmlspecialchars($search); ?>" placeholder="Tìm hoạt động">
            <select name="status"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= $value; ?>" <?= $status === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option><?php endforeach; ?></select>
            <button class="btn btn-outline btn-sm" type="submit">Lọc</button>
        </form>
    </div>
    <?php if ($activities === []): ?><p>Không có hoạt động phù hợp.</p><?php endif; ?>
    <?php foreach ($activities as $activity): ?>
        <article class="school-section-box" style="margin:1rem 0">
            <div class="school-section-box__header"><div><h3 class="school-section-box__title"><?= htmlspecialchars((string) $activity['title']); ?></h3><p><?= htmlspecialchars((string) ($activity['teacherName'] ?? '')); ?> · <?= htmlspecialchars((string) ($activity['category'] ?? '')); ?></p></div><span class="school-class-badge school-class-badge--neutral"><?= htmlspecialchars($statusLabels[(string) $activity['approvalStatus']] ?? (string) $activity['approvalStatus']); ?></span></div>
            <p><?= nl2br(htmlspecialchars((string) ($activity['summary'] ?? 'Chưa có tóm tắt'))); ?></p>
            <div class="school-grid-2col"><div><strong>Thời gian:</strong> <?= htmlspecialchars((string) $activity['startAt']); ?> UTC – <?= htmlspecialchars((string) ($activity['endAt'] ?? '')); ?> UTC</div><div><strong>Địa điểm:</strong> <?= htmlspecialchars((string) ($activity['locationName'] ?? '—')); ?></div><div><strong>Đơn vị:</strong> <?= htmlspecialchars((string) ($activity['organizerName'] ?? '—')); ?></div><div><strong>Sức chứa:</strong> <?= (int) $activity['capacity']; ?> · <?= htmlspecialchars((string) ($activity['approvalMode'] ?? 'automatic')); ?></div><div><strong>Đăng ký:</strong> <?= htmlspecialchars((string) ($activity['registrationOpensAt'] ?? '—')); ?> – <?= htmlspecialchars((string) ($activity['registrationClosesAt'] ?? '—')); ?></div><div><strong>Đối tượng:</strong> <?= htmlspecialchars((string) ($activity['targetAudience'] ?? '—')); ?></div></div>
            <?php if (($activity['approvalStatus'] ?? '') === 'pending_school_review'): ?>
            <form method="post" class="school-form" style="margin-top:1rem">
                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="activityId" value="<?= htmlspecialchars((string) $activity['id']); ?>">
                <label class="school-form__field"><span>Lý do (bắt buộc khi yêu cầu sửa/từ chối)</span><textarea name="reason" maxlength="1000" rows="2"></textarea></label>
                <div class="school-form__actions"><button class="btn btn-primary" name="action" value="approve" type="submit">Phê duyệt</button><button class="btn btn-outline" name="action" value="request_changes" type="submit">Yêu cầu chỉnh sửa</button><button class="btn btn-outline" name="action" value="reject" type="submit">Từ chối</button></div>
            </form>
            <?php elseif (!empty($activity['approvalReason'])): ?><p><strong>Phản hồi:</strong> <?= htmlspecialchars((string) $activity['approvalReason']); ?></p><?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php
$pageBody = ob_get_clean();
$extraStyles = '';
require __DIR__ . '/includes/layout.php';
