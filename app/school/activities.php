<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

function schoolActivitiesEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function schoolActivitiesDate(mixed $value): string
{
    if (!is_string($value) || trim($value) === '') return 'Chưa thiết lập';
    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i');
    } catch (Throwable) {
        return 'Chưa thiết lập';
    }
}

/** @return list<string> */
function schoolActivitiesItems(mixed $value): array
{
    if (is_string($value)) $value = json_decode($value, true);
    if (!is_array($value) || !array_is_list($value)) return [];
    return array_values(array_filter(array_map(
        static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
        $value,
    ), static fn (string $item): bool => $item !== ''));
}

function schoolActivitiesUrl(mixed $value): ?string
{
    $url = trim((string) $value);
    return filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
}

$context = (new SchoolAppContext())->boot();
$service = $context['activityApprovals'];
$session = $context['session'];
$permissions = $context['permissions'];
$userId = (string) $context['user']['id'];
$permissions->require($userId, 'activity.review_school');
$statusLabels = [
    'pending_school_review' => 'Chờ duyệt', 'changes_requested' => 'Cần chỉnh sửa',
    'approved' => 'Đã duyệt', 'rejected' => 'Đã từ chối', 'draft' => 'Chưa gửi duyệt',
];
$status = trim((string) ($_GET['status'] ?? 'pending_school_review'));
if (!isset($statusLabels[$status]) && $status !== 'all') $status = 'pending_school_review';
$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 255);
$error = null;
$postedActivityId = '';
$postedReason = '';
$flash = isset($_SESSION['school_activity_review_flash']) && is_string($_SESSION['school_activity_review_flash'])
    ? $_SESSION['school_activity_review_flash'] : null;
unset($_SESSION['school_activity_review_flash']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedActivityId = trim((string) ($_POST['activityId'] ?? ''));
    $postedReason = (string) ($_POST['reason'] ?? '');
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $permissions->require($userId, 'activity.review_school');
        $decision = (string) ($_POST['action'] ?? '');
        $service->review($userId, $postedActivityId, $decision, $postedReason, RequestId::make(null));
        $_SESSION['school_activity_review_flash'] = match ($decision) {
            'approve' => 'Đã phê duyệt hoạt động. Giáo viên có thể công bố hoạt động cho học viên.',
            'request_changes' => 'Đã gửi yêu cầu chỉnh sửa. Giáo viên cần cập nhật và gửi lại để Nhà trường duyệt.',
            'reject' => 'Đã từ chối hoạt động và gửi lý do cho Giáo viên.',
            default => 'Đã cập nhật hoạt động.',
        };
        header('Location: ' . app_href('/app/school/activities.php?' . http_build_query(['status' => $status, 'q' => $search])), true, 303);
        exit;
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('School activity review failed: ' . $exception->getMessage());
        $error = 'Chưa thể lưu quyết định duyệt. Vui lòng thử lại.';
    }
}

$activities = $service->list($userId, $status === 'all' ? null : $status, $search);
$schoolInfo = [
    'name' => $context['school']['name'], 'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '', 'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/activities.php';
$pageTitle = 'Duyệt hoạt động';
$pageDescription = 'Xem nội dung và thiết lập đăng ký do Giáo viên gửi. Sau khi được Nhà trường phê duyệt, Giáo viên có thể công bố hoạt động cho học viên.';
$deliveryLabels = ['in_person' => 'Trực tiếp', 'online' => 'Trực tuyến', 'hybrid' => 'Kết hợp'];
$registrationLabels = ['automatic' => 'Tự động duyệt đăng ký', 'teacher_review' => 'Giáo viên duyệt đăng ký'];
$publicationLabels = ['draft' => 'Chưa công bố', 'published' => 'Đã công bố', 'ongoing' => 'Đang diễn ra', 'completed' => 'Đã hoàn tất', 'archived' => 'Đã lưu trữ'];
$csrfToken = $session->csrfToken();

ob_start();
?>
<?php include __DIR__ . '/includes/page-banner.php'; ?>
<div class="school-activities">
    <?php if ($flash): ?><div class="school-flash school-flash--success" role="status"><?= schoolActivitiesEscape($flash); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="school-flash school-flash--error" role="alert"><strong>Chưa thể lưu quyết định duyệt.</strong><p><?= schoolActivitiesEscape($error); ?></p></div><?php endif; ?>
    <section class="school-section-box school-activities__filters" aria-labelledby="activity-queue-heading">
        <div class="school-section-box__header">
            <div>
                <h2 class="school-section-box__title" id="activity-queue-heading">Hàng đợi duyệt hoạt động</h2>
                <p class="school-section-box__subtitle"><?= count($activities); ?> hoạt động · <?= schoolActivitiesEscape($statusLabels[$status] ?? 'Tất cả trạng thái'); ?></p>
            </div>
            <span class="school-activities__timezone">Thời gian hiển thị theo giờ Việt Nam (UTC+7)</span>
        </div>
        <form method="get" class="school-activities__filter-form" role="search" aria-label="Tìm hoạt động cần duyệt">
            <label class="school-form__field school-activities__search"><span>Tìm kiếm</span><input type="search" name="q" maxlength="255" value="<?= schoolActivitiesEscape($search); ?>" placeholder="Nhập tên hoạt động"></label>
            <label class="school-form__field"><span>Trạng thái phê duyệt</span><select name="status"><option value="all" <?= $status === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option><?php foreach ($statusLabels as $value => $label): ?><option value="<?= $value; ?>" <?= $status === $value ? 'selected' : ''; ?>><?= schoolActivitiesEscape($label); ?></option><?php endforeach; ?></select></label>
            <button class="btn btn-primary" type="submit">Lọc hoạt động</button>
            <?php if ($search !== '' || $status !== 'pending_school_review'): ?><a class="btn btn-outline" href="<?= schoolActivitiesEscape(app_href('/app/school/activities.php')); ?>">Về hàng đợi</a><?php endif; ?>
        </form>
    </section>
    <?php if ($activities === []): ?>
        <section class="school-section-box school-empty-state">
            <h3 class="school-empty-state__title"><?= $search === '' && $status === 'pending_school_review' ? 'Chưa có hoạt động chờ duyệt' : 'Không tìm thấy hoạt động phù hợp'; ?></h3>
            <p class="school-empty-state__desc"><?= $search === '' && $status === 'pending_school_review' ? 'Hoạt động sẽ xuất hiện tại đây khi Giáo viên chọn “Gửi Nhà trường duyệt”.' : 'Thử thay đổi từ khóa hoặc chọn trạng thái khác để xem hoạt động.'; ?></p>
        </section>
    <?php endif; ?>
    <?php foreach ($activities as $activity):
        $activityId = (string) $activity['id'];
        $approvalStatus = (string) ($activity['approvalStatus'] ?? 'draft');
        $isPending = $approvalStatus === 'pending_school_review';
        $hasError = $error !== null && $postedActivityId === $activityId;
        $badgeTone = match ($approvalStatus) { 'approved' => 'approved', 'pending_school_review' => 'pending', 'changes_requested' => 'changes', 'rejected' => 'rejected', default => 'draft' };
        $meetingUrl = schoolActivitiesUrl($activity['onlineMeetingUrl'] ?? '');
        $coverUrl = schoolActivitiesUrl($activity['coverImageUrl'] ?? '');
        $feeAmount = $activity['feeAmount'] ?? null;
        $feeLabel = $feeAmount === null ? 'Chưa thiết lập' : ((float) $feeAmount > 0 ? number_format((float) $feeAmount, 0, ',', '.') . ' ' . ((string) ($activity['currency'] ?? '') ?: 'VND') : 'Miễn phí');
    ?>
        <article class="school-section-box school-activity-review" id="activity-<?= schoolActivitiesEscape($activityId); ?>" aria-labelledby="activity-title-<?= schoolActivitiesEscape($activityId); ?>">
            <header class="school-activity-review__header">
                <div>
                    <p class="school-activity-review__eyebrow"><?= schoolActivitiesEscape(($activity['displayCategory'] ?? '') ?: ($activity['category'] ?? 'Hoạt động')); ?></p>
                    <h3 id="activity-title-<?= schoolActivitiesEscape($activityId); ?>"><?= schoolActivitiesEscape($activity['title']); ?></h3>
                    <p class="school-activity-review__meta">Giáo viên gửi: <strong><?= schoolActivitiesEscape(($activity['teacherName'] ?? '') ?: 'Chưa cập nhật'); ?></strong><?php if (!empty($activity['approvalRequestedAt'])): ?> · Gửi lúc <?= schoolActivitiesDate($activity['approvalRequestedAt']); ?><?php endif; ?></p>
                </div>
                <span class="school-activity-review__badge school-activity-review__badge--<?= $badgeTone; ?>"><?= schoolActivitiesEscape($statusLabels[$approvalStatus] ?? 'Chưa xác định'); ?></span>
            </header>
            <p class="school-activity-review__summary"><?= nl2br(schoolActivitiesEscape(($activity['summary'] ?? '') ?: 'Chưa có giới thiệu ngắn.')); ?></p>
            <dl class="school-activity-review__facts">
                <div><dt>Bắt đầu</dt><dd><?= schoolActivitiesDate($activity['startAt'] ?? null); ?></dd></div>
                <div><dt>Kết thúc</dt><dd><?= schoolActivitiesDate($activity['endAt'] ?? null); ?></dd></div>
                <div><dt>Hình thức tổ chức</dt><dd><?= schoolActivitiesEscape($deliveryLabels[(string) ($activity['deliveryMode'] ?? '')] ?? 'Chưa thiết lập'); ?></dd></div>
                <div><dt>Sức chứa</dt><dd><?= number_format((int) ($activity['capacity'] ?? 0), 0, ',', '.'); ?> người</dd></div>
            </dl>
            <details class="school-activity-review__details" <?= $isPending || $hasError ? 'open' : ''; ?>>
                <summary>Xem nội dung và chính sách hoạt động</summary>
                <div class="school-activity-review__details-body">
                    <section class="school-activity-review__section">
                        <h4>Nội dung hoạt động</h4>
                        <p class="school-activity-review__text"><?= nl2br(schoolActivitiesEscape(($activity['description'] ?? '') ?: 'Chưa có mô tả đầy đủ.')); ?></p>
                        <?php foreach (['experienceHighlights' => 'Nội dung trải nghiệm', 'skillTags' => 'Kỹ năng phát triển', 'eligibilityRules' => 'Điều kiện tham gia', 'benefitItems' => 'Quyền lợi tham gia'] as $field => $label): $items = schoolActivitiesItems($activity[$field] ?? null); ?>
                            <?php if ($items !== []): ?><div class="school-activity-review__list"><h5><?= $label; ?></h5><ul><?php foreach ($items as $item): ?><li><?= schoolActivitiesEscape($item); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($coverUrl): ?><p class="school-activity-review__resource"><a href="<?= schoolActivitiesEscape($coverUrl); ?>" target="_blank" rel="noopener noreferrer">Xem ảnh bìa hoạt động</a><?php if (!empty($activity['coverImageAlt'])): ?> · <?= schoolActivitiesEscape($activity['coverImageAlt']); ?><?php endif; ?></p><?php endif; ?>
                    </section>
                    <section class="school-activity-review__section">
                        <h4>Tổ chức và đối tượng tham gia</h4>
                        <dl class="school-activity-review__facts">
                            <div><dt>Địa điểm</dt><dd><?= schoolActivitiesEscape(($activity['locationName'] ?? '') ?: 'Chưa cập nhật'); ?><?php if (!empty($activity['locationAddress'])): ?><br><?= schoolActivitiesEscape($activity['locationAddress']); ?><?php endif; ?></dd></div>
                            <div><dt>Tham gia trực tuyến</dt><dd><?php if ($meetingUrl): ?><a href="<?= schoolActivitiesEscape($meetingUrl); ?>" target="_blank" rel="noopener noreferrer"><?= schoolActivitiesEscape($meetingUrl); ?></a><?php else: ?><?= ($activity['deliveryMode'] ?? '') === 'in_person' ? 'Không áp dụng' : 'Chưa cập nhật'; ?><?php endif; ?></dd></div>
                            <div><dt>Đơn vị tổ chức</dt><dd><?= schoolActivitiesEscape(($activity['organizerName'] ?? '') ?: 'Chưa cập nhật'); ?></dd></div>
                            <div><dt>Liên hệ</dt><dd><?= schoolActivitiesEscape(($activity['organizerContact'] ?? '') ?: 'Chưa cập nhật'); ?><?php foreach (['organizerEmail', 'organizerPhone'] as $field): ?><?php if (!empty($activity[$field])): ?><br><?= schoolActivitiesEscape($activity[$field]); ?><?php endif; ?><?php endforeach; ?></dd></div>
                            <div><dt>Đối tượng tham gia</dt><dd><?= schoolActivitiesEscape(($activity['targetAudience'] ?? '') ?: 'Chưa cập nhật'); ?></dd></div>
                            <div><dt>Chi phí tham gia</dt><dd><?= schoolActivitiesEscape($feeLabel); ?></dd></div>
                            <?php if (!empty($activity['responsibleTeacherName'])): ?><div><dt>Giáo viên phụ trách</dt><dd><?= schoolActivitiesEscape($activity['responsibleTeacherName']); ?></dd></div><?php endif; ?>
                        </dl>
                    </section>
                    <section class="school-activity-review__section">
                        <h4>Thiết lập đăng ký và công nhận</h4>
                        <dl class="school-activity-review__facts">
                            <div><dt>Mở đăng ký</dt><dd><?= schoolActivitiesDate($activity['registrationOpensAt'] ?? null); ?></dd></div>
                            <div><dt>Đóng đăng ký</dt><dd><?= schoolActivitiesDate($activity['registrationClosesAt'] ?? null); ?></dd></div>
                            <div><dt>Cho phép hủy đến</dt><dd><?= schoolActivitiesDate($activity['cancellationClosesAt'] ?? null); ?></dd></div>
                            <div><dt>Cách duyệt đăng ký</dt><dd><?= schoolActivitiesEscape($registrationLabels[(string) ($activity['approvalMode'] ?? '')] ?? 'Chưa thiết lập'); ?></dd></div>
                            <div><dt>Giờ được công nhận</dt><dd><?= isset($activity['confirmedHours']) ? schoolActivitiesEscape(rtrim(rtrim(number_format((float) $activity['confirmedHours'], 2, ',', ''), '0'), ',')) . ' giờ' : 'Chưa thiết lập'; ?></dd></div>
                            <div><dt>Chứng nhận</dt><dd><?= schoolActivitiesEscape(($activity['certificateLabel'] ?? '') ?: 'Chưa thiết lập'); ?></dd></div>
                        </dl>
                    </section>
                </div>
            </details>
            <?php if ($isPending): ?>
                <form method="post" class="school-form school-activity-review__form" action="<?= schoolActivitiesEscape(app_href('/app/school/activities.php?' . http_build_query(['status' => $status, 'q' => $search]))); ?>#activity-<?= schoolActivitiesEscape($activityId); ?>" data-activity-review>
                    <input type="hidden" name="csrfToken" value="<?= schoolActivitiesEscape($csrfToken); ?>">
                    <input type="hidden" name="activityId" value="<?= schoolActivitiesEscape($activityId); ?>">
                    <label class="school-form__field" for="reason-<?= schoolActivitiesEscape($activityId); ?>"><span>Phản hồi cho Giáo viên</span><textarea id="reason-<?= schoolActivitiesEscape($activityId); ?>" name="reason" maxlength="1000" rows="3" aria-describedby="reason-hint-<?= schoolActivitiesEscape($activityId); ?>" placeholder="Nêu rõ nội dung cần chỉnh sửa hoặc lý do từ chối..."><?= $hasError ? schoolActivitiesEscape($postedReason) : ''; ?></textarea></label>
                    <p class="school-activity-review__hint" id="reason-hint-<?= schoolActivitiesEscape($activityId); ?>">Bắt buộc nhập lý do khi yêu cầu chỉnh sửa hoặc từ chối. Tối đa 1.000 ký tự.</p>
                    <div class="school-form__actions">
                        <button class="btn btn-primary" name="action" value="approve" type="submit">Phê duyệt hoạt động</button>
                        <button class="btn btn-outline" name="action" value="request_changes" type="submit">Yêu cầu chỉnh sửa</button>
                        <button class="btn btn-outline school-activity-review__reject" name="action" value="reject" type="submit">Từ chối</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="school-activity-review__outcome">
                    <?php if ($approvalStatus === 'approved'): ?><p><strong>Đã phê duyệt<?= !empty($activity['approvedAt']) ? ' lúc ' . schoolActivitiesDate($activity['approvedAt']) : ''; ?>.</strong> <?= schoolActivitiesEscape($publicationLabels[(string) ($activity['status'] ?? '')] ?? ''); ?><?= ($activity['status'] ?? '') === 'draft' ? ' · Giáo viên có thể công bố hoạt động.' : '.'; ?></p>
                    <?php elseif ($approvalStatus === 'draft'): ?><p>Giáo viên chưa gửi hoạt động này để Nhà trường duyệt.</p>
                    <?php elseif (!empty($activity['approvalReason'])): ?><p><strong>Phản hồi của Nhà trường:</strong><br><?= nl2br(schoolActivitiesEscape($activity['approvalReason'])); ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php
$pageBody = ob_get_clean();
$extraStyles = '<link rel="stylesheet" href="../../assets/css/school-activities.css">';
$extraScripts = '<script src="../../assets/js/school-activities.js" defer></script>';
require __DIR__ . '/includes/layout.php';
