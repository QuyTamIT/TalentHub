<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

$context = (new SchoolAppContext())->boot();
$service = $context['activityApprovals'];
$permissions = $context['permissions'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$permissions->require($userId, 'activity.review_school');

$flash = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $postedToken = is_string($_POST['csrfToken'] ?? null) ? trim((string) $_POST['csrfToken']) : '';
        if ($postedToken === '' || !hash_equals($session->csrfToken(), $postedToken)) {
            throw new ApiException(419, 'CSRF_INVALID', 'Yêu cầu không hợp lệ hoặc phiên làm việc đã hết hạn.');
        }
        $permissions->require($userId, 'activity.review_school');
        $decision = trim((string) ($_POST['action'] ?? ''));
        $service->review(
            $userId,
            trim((string) ($_POST['activityId'] ?? '')),
            $decision,
            isset($_POST['reason']) ? (string) $_POST['reason'] : null,
            RequestId::make(null),
        );
        $flash = $decision === 'approve'
            ? 'Đã phê duyệt hoạt động và chuyển sang Đã công bố.'
            : 'Đã từ chối hoạt động; Giáo viên có thể chỉnh sửa và gửi lại.';
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable) {
        $error = 'Không thể cập nhật hoạt động. Vui lòng thử lại sau.';
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$activities = $service->listPending($userId, $search);
$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/activities.php';
$pageTitle = 'Duyệt hoạt động';

ob_start();
?>
<?php $pageDescription = 'Xem xét các hoạt động Giáo viên đã gửi trước khi công bố cho học viên.'; include __DIR__ . '/includes/page-banner.php'; ?>
<?php if ($flash !== null): ?><div class="school-flash school-flash--success" role="status"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($error !== null): ?><div class="school-flash school-flash--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<section class="school-section-box">
    <div class="school-section-box__header">
        <div>
            <h2 class="school-section-box__title">Hàng đợi chờ duyệt</h2>
            <p class="school-section-box__subtitle">Chỉ hiển thị hoạt động thuộc Nhà trường hiện tại.</p>
        </div>
        <form method="get" style="display:flex;gap:.5rem">
            <label><span class="sr-only">Tìm hoạt động</span><input name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Tìm hoạt động"></label>
            <button class="btn btn-outline btn-sm" type="submit">Lọc</button>
        </form>
    </div>
    <?php if ($activities === []): ?><p>Hiện không có hoạt động đang chờ duyệt.</p><?php endif; ?>
    <?php foreach ($activities as $activity): ?>
        <article class="school-section-box" style="margin:1rem 0">
            <div class="school-section-box__header">
                <div>
                    <h3 class="school-section-box__title"><?= htmlspecialchars((string) $activity['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?= htmlspecialchars((string) ($activity['teacherName'] ?? 'Giáo viên'), ENT_QUOTES, 'UTF-8'); ?> · <?= htmlspecialchars((string) ($activity['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <span class="school-class-badge school-class-badge--neutral">Chờ duyệt</span>
            </div>
            <p><?= nl2br(htmlspecialchars((string) ($activity['summary'] ?? $activity['description'] ?? 'Chưa có mô tả'), ENT_QUOTES, 'UTF-8')); ?></p>
            <div class="school-grid-2col">
                <div><strong>Thời gian:</strong> <?= htmlspecialchars((string) $activity['startAt'], ENT_QUOTES, 'UTF-8'); ?> – <?= htmlspecialchars((string) ($activity['endAt'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong>Địa điểm:</strong> <?= htmlspecialchars((string) ($activity['locationName'] ?? $activity['locationAddress'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong>Sức chứa:</strong> <?= (int) $activity['capacity']; ?></div>
                <div><strong>Gửi lúc:</strong> <?= htmlspecialchars((string) ($activity['approvalRequestedAt'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <form method="post" class="school-form" style="margin-top:1rem" data-school-review-form>
                <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="activityId" value="<?= htmlspecialchars((string) $activity['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <label class="school-form__field"><span>Lý do từ chối (bắt buộc khi từ chối)</span><textarea name="reason" maxlength="1000" rows="2"></textarea></label>
                <div class="school-form__actions">
                    <button class="btn btn-primary" name="action" value="approve" type="submit">Phê duyệt</button>
                    <button class="btn btn-outline" name="action" value="reject" type="submit">Từ chối</button>
                </div>
            </form>
        </article>
    <?php endforeach; ?>
</section>
<?php
$pageBody = ob_get_clean();
$extraScripts = '<script>document.querySelectorAll("[data-school-review-form]").forEach(function (form) { form.addEventListener("submit", function (event) { var button = event.submitter; var message = button && button.value === "reject" ? "Bạn có chắc muốn từ chối hoạt động này?" : "Bạn có chắc muốn phê duyệt hoạt động này?"; if (!window.confirm(message)) event.preventDefault(); }); });</script>';
require __DIR__ . '/includes/layout.php';
