<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

if (!class_exists('TalentHub\Learner\Data\Service\NotificationService', false)) {
    $learnerBootstrap = dirname(__DIR__, 2) . '/app/learner/data/bootstrap.php';
    if (file_exists($learnerBootstrap)) {
        require_once $learnerBootstrap;
    }
}

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

function schoolActivitiesCategoryLabel(mixed $category, mixed $displayCategory = null): string
{
    if (is_string($displayCategory) && trim($displayCategory) !== '') {
        return trim($displayCategory);
    }
    $code = strtolower(trim((string) $category));
    $catalog = [
        'career_technical' => 'Kỹ thuật & Công nghệ',
        'career_business' => 'Kinh doanh & Quản trị',
        'career_arts' => 'Nghệ thuật & Sáng tạo',
        'career_sports_academic' => 'Thể thao & Học thuật',
        'workshop' => 'Workshop & Thực hành',
        'talkshow' => 'Tọa đàm & Chia sẻ',
        'competition' => 'Cuộc thi & Thử thách',
        'field_trip' => 'Tham quan thực tế',
        'community' => 'Cộng đồng & Tình nguyện',
    ];
    return $catalog[$code] ?? (is_string($category) && $category !== '' ? ucwords(str_replace(['_', '-'], ' ', $category)) : 'Hoạt động trải nghiệm');
}

function schoolActivitiesInitials(string $name): string
{
    $name = trim($name);
    if ($name === '') return 'GV';
    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if (!$words || count($words) === 0) return 'GV';
    if (count($words) === 1) return mb_substr($words[0], 0, 2);
    return mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1);
}

$context = (new SchoolAppContext())->boot();
$service = $context['activityApprovals'];
$session = $context['session'];
$permissions = $context['permissions'];
$userId = (string) $context['user']['id'];
$permissions->require($userId, 'activity.review_school');

$statusLabels = [
    'pending_school_review' => 'Chờ duyệt',
    'changes_requested' => 'Cần chỉnh sửa',
    'approved' => 'Đã duyệt',
    'rejected' => 'Đã từ chối',
    'draft' => 'Chưa gửi duyệt',
];

$status = trim((string) ($_GET['status'] ?? 'pending_school_review'));
if (!isset($statusLabels[$status]) && $status !== 'all') {
    $status = 'pending_school_review';
}
$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 255);
$error = null;
$postedActivityId = '';
$postedReason = '';
$flash = isset($_SESSION['school_activity_review_flash']) && is_string($_SESSION['school_activity_review_flash'])
    ? $_SESSION['school_activity_review_flash'] : null;
unset($_SESSION['school_activity_review_flash']);

if (!$flash && isset($_GET['flash'])) {
    $flash = match ((string) $_GET['flash']) {
        'approved', 'approve' => 'Phê duyệt hoạt động thành công! Hoạt động đã chuyển sang trạng thái Đã duyệt.',
        'changes_requested', 'request_changes' => 'Đã gửi yêu cầu chỉnh sửa. Giáo viên sẽ nhận được thông báo để cập nhật và gửi lại.',
        'rejected', 'reject' => 'Đã từ chối hoạt động và gửi lý do chi tiết cho Giáo viên.',
        default => null,
    };
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedActivityId = trim((string) ($_POST['activityId'] ?? ''));
    $postedReason = (string) ($_POST['reason'] ?? '');
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $permissions->require($userId, 'activity.review_school');
        $decision = (string) ($_POST['action'] ?? '');
        $service->review($userId, $postedActivityId, $decision, $postedReason, RequestId::make(null));
        $_SESSION['school_activity_review_flash'] = match ($decision) {
            'approve' => 'Phê duyệt hoạt động thành công! Hoạt động đã chuyển sang trạng thái Đã duyệt.',
            'request_changes' => 'Đã gửi yêu cầu chỉnh sửa. Giáo viên sẽ nhận được thông báo để cập nhật và gửi lại.',
            'reject' => 'Đã từ chối hoạt động và gửi lý do chi tiết cho Giáo viên.',
            default => 'Đã cập nhật quyết định duyệt hoạt động.',
        };
        $redirectStatus = ($status === 'all') ? 'all' : match ($decision) {
            'approve' => 'approved',
            'request_changes' => 'changes_requested',
            'reject' => 'rejected',
            default => $status,
        };
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $redirectUrl = app_href('/app/school/activities.php?' . http_build_query([
            'status' => $redirectStatus,
            'q' => $search,
            'flash' => $decision === 'approve' ? 'approved' : $decision,
        ])) . '#activity-' . urlencode($postedActivityId);
        header('Location: ' . $redirectUrl, true, 303);
        exit;
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('School activity review failed: ' . $exception->getMessage());
        $error = 'Chưa thể lưu quyết định duyệt: ' . $exception->getMessage();
    }
}

// Fetch all activities in school for metric summary counters
$allActivities = [];
try {
    $allActivities = $service->list($userId, null, null);
} catch (Throwable) {
    $allActivities = [];
}

$metrics = [
    'pending_school_review' => 0,
    'changes_requested' => 0,
    'approved' => 0,
    'rejected' => 0,
    'all' => count($allActivities),
];
foreach ($allActivities as $item) {
    $st = (string) ($item['approvalStatus'] ?? 'draft');
    if (isset($metrics[$st])) {
        $metrics[$st]++;
    }
}

$activities = $service->list($userId, $status === 'all' ? null : $status, $search);

$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];
$currentRoute = '/app/school/activities.php';
$pageTitle = 'Duyệt hoạt động';
$pageDescription = 'Xem nội dung và thiết lập đăng ký do Giáo viên gửi. Sau khi được Nhà trường phê duyệt, Giáo viên có thể công bố hoạt động cho học viên.';
$deliveryLabels = ['in_person' => 'Trực tiếp tại trường', 'online' => 'Trực tuyến', 'hybrid' => 'Kết hợp'];
$registrationLabels = ['automatic' => 'Tự động duyệt đăng ký', 'teacher_review' => 'Giáo viên duyệt đăng ký'];
$publicationLabels = ['draft' => 'Chưa công bố', 'published' => 'Đã công bố', 'ongoing' => 'Đang diễn ra', 'completed' => 'Đã hoàn tất', 'archived' => 'Đã lưu trữ'];
$csrfToken = $session->csrfToken();

ob_start();
?>
<div class="school-activities">
    <!-- 1. Breadcrumb -->
    <nav class="school-act-breadcrumb" aria-label="Breadcrumb">
        <ol>
            <li>
                <a href="<?= schoolActivitiesEscape(app_href('/app/school/index.php')); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <span>Nhà trường</span>
                </a>
            </li>
            <li class="separator" aria-hidden="true">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </li>
            <li aria-current="page">Duyệt hoạt động</li>
        </ol>
    </nav>

    <!-- 2. Hero Header Banner -->
    <header class="school-act-hero">
        <div class="school-act-hero__content">
            <div class="school-act-hero__badge">
                <span class="badge-dot"></span>
                <span>HỆ THỐNG PHÊ DUYỆT HOẠT ĐỘNG EDTECH</span>
            </div>
            <h1 class="school-act-hero__title">Duyệt hoạt động giáo dục & trải nghiệm</h1>
            <p class="school-act-hero__desc">Kiểm tra thông tin chi tiết, hình thức tổ chức và chính sách đăng ký do Giảng viên gửi duyệt. Sau khi Nhà trường phê duyệt, hoạt động sẽ sẵn sàng công bố cho sinh viên.</p>
        </div>
        <div class="school-act-hero__meta">
            <span class="school-act-hero__tz">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>Giờ Việt Nam (UTC+7)</span>
            </span>
        </div>
    </header>

    <!-- 3. Flash Notifications -->
    <?php if ($flash): ?>
        <div class="school-flash school-flash--success" role="status" style="border-radius: var(--th-radius-md); box-shadow: var(--th-shadow-sm);">
            <strong>Thành công:</strong> <?= schoolActivitiesEscape($flash); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="school-flash school-flash--error" role="alert" style="border-radius: var(--th-radius-md); box-shadow: var(--th-shadow-sm);">
            <strong>Chưa thể lưu quyết định duyệt:</strong>
            <p style="margin: 0.25rem 0 0;"><?= schoolActivitiesEscape($error); ?></p>
        </div>
    <?php endif; ?>

    <!-- 4. Approval Summary Metrics Grid -->
    <section class="school-act-summary" aria-label="Thống kê trạng thái duyệt">
        <!-- Chờ duyệt -->
        <a href="<?= schoolActivitiesEscape(app_href('/app/school/activities.php?status=pending_school_review' . ($search !== '' ? '&q=' . urlencode($search) : ''))); ?>"
           class="school-act-stat school-act-stat--pending <?= $status === 'pending_school_review' ? 'school-act-stat--active' : ''; ?>">
            <div class="school-act-stat__head">
                <span class="school-act-stat__label">Chờ phê duyệt</span>
                <span class="school-act-stat__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
            </div>
            <div class="school-act-stat__value-wrap">
                <span class="school-act-stat__value"><?= number_format($metrics['pending_school_review']); ?></span>
            </div>
            <span class="school-act-stat__sub">Cần xem xét & đưa ra quyết định</span>
        </a>

        <!-- Cần chỉnh sửa -->
        <a href="<?= schoolActivitiesEscape(app_href('/app/school/activities.php?status=changes_requested' . ($search !== '' ? '&q=' . urlencode($search) : ''))); ?>"
           class="school-act-stat school-act-stat--changes <?= $status === 'changes_requested' ? 'school-act-stat--active' : ''; ?>">
            <div class="school-act-stat__head">
                <span class="school-act-stat__label">Cần chỉnh sửa</span>
                <span class="school-act-stat__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </span>
            </div>
            <div class="school-act-stat__value-wrap">
                <span class="school-act-stat__value"><?= number_format($metrics['changes_requested']); ?></span>
            </div>
            <span class="school-act-stat__sub">Đang chờ Giảng viên hoàn thiện</span>
        </a>

        <!-- Đã phê duyệt -->
        <a href="<?= schoolActivitiesEscape(app_href('/app/school/activities.php?status=approved' . ($search !== '' ? '&q=' . urlencode($search) : ''))); ?>"
           class="school-act-stat school-act-stat--approved <?= $status === 'approved' ? 'school-act-stat--active' : ''; ?>">
            <div class="school-act-stat__head">
                <span class="school-act-stat__label">Đã phê duyệt</span>
                <span class="school-act-stat__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </span>
            </div>
            <div class="school-act-stat__value-wrap">
                <span class="school-act-stat__value"><?= number_format($metrics['approved']); ?></span>
            </div>
            <span class="school-act-stat__sub">Sẵn sàng công bố cho sinh viên</span>
        </a>

        <!-- Tất cả hoạt động -->
        <a href="<?= schoolActivitiesEscape(app_href('/app/school/activities.php?status=all' . ($search !== '' ? '&q=' . urlencode($search) : ''))); ?>"
           class="school-act-stat school-act-stat--all <?= $status === 'all' ? 'school-act-stat--active' : ''; ?>">
            <div class="school-act-stat__head">
                <span class="school-act-stat__label">Tất cả hoạt động</span>
                <span class="school-act-stat__icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                </span>
            </div>
            <div class="school-act-stat__value-wrap">
                <span class="school-act-stat__value"><?= number_format($metrics['all']); ?></span>
            </div>
            <span class="school-act-stat__sub">Toàn bộ hồ sơ hoạt động tại trường</span>
        </a>
    </section>

    <!-- 5. Search & Filter Bar -->
    <section class="school-act-filters" aria-labelledby="activity-filter-heading">
        <div class="school-act-filters__header">
            <h2 class="school-act-filters__title" id="activity-filter-heading">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#FF5B26" stroke-width="2" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                <span>Hàng đợi duyệt hoạt động</span>
                <span class="school-act-filters__count-pill"><?= count($activities); ?> hoạt động</span>
            </h2>
            <span style="font-size: 0.8rem; color: var(--th-text-muted);">Trạng thái: <strong><?= schoolActivitiesEscape($statusLabels[$status] ?? 'Tất cả'); ?></strong></span>
        </div>
        <form method="get" class="school-act-filters__form" role="search" aria-label="Tìm hoạt động cần duyệt">
            <div class="school-act-filters__search">
                <span class="school-act-filters__search-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>
                <input type="search" name="q" maxlength="255" value="<?= schoolActivitiesEscape($search); ?>" placeholder="Tìm theo tên hoạt động, giảng viên...">
            </div>
            <div class="school-act-filters__select">
                <select name="status" aria-label="Chọn trạng thái phê duyệt">
                    <option value="all" <?= $status === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= $value; ?>" <?= $status === $value ? 'selected' : ''; ?>><?= schoolActivitiesEscape($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="school-act-filters__btn" type="submit">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span>Lọc danh sách</span>
            </button>
            <?php if ($search !== '' || $status !== 'pending_school_review'): ?>
                <a class="school-act-filters__reset" href="<?= schoolActivitiesEscape(app_href('/app/school/activities.php')); ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
                    <span>Về hàng đợi</span>
                </a>
            <?php endif; ?>
        </form>
    </section>

    <!-- 6. Empty State -->
    <?php if ($activities === []): ?>
        <section class="school-act-empty">
            <div class="school-act-empty__icon-wrap">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <h3 class="school-act-empty__title"><?= $search === '' && $status === 'pending_school_review' ? 'Tuyệt vời! Không còn hoạt động chờ duyệt' : 'Không tìm thấy hoạt động phù hợp'; ?></h3>
            <p class="school-act-empty__desc"><?= $search === '' && $status === 'pending_school_review' ? 'Hiện tại không có hoạt động nào đang chờ Nhà trường phê duyệt. Hoạt động mới sẽ xuất hiện tại đây khi Giảng viên nhấn “Gửi Nhà trường duyệt”.' : 'Vui lòng thử thay đổi từ khóa tìm kiếm hoặc chọn bộ lọc trạng thái khác để xem hoạt động.'; ?></p>
            <?php if ($search !== '' || $status !== 'pending_school_review'): ?>
                <a class="school-act-empty__action" href="<?= schoolActivitiesEscape(app_href('/app/school/activities.php?status=all')); ?>">
                    <span>Xem tất cả hoạt động</span>
                </a>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- 7. Activity Cards List -->
    <?php foreach ($activities as $activity):
        $activityId = (string) $activity['id'];
        $approvalStatus = (string) ($activity['approvalStatus'] ?? 'draft');
        $isPending = $approvalStatus === 'pending_school_review';
        $hasError = $error !== null && $postedActivityId === $activityId;
        $badgeTone = match ($approvalStatus) {
            'approved' => 'approved',
            'pending_school_review' => 'pending',
            'changes_requested' => 'changes',
            'rejected' => 'rejected',
            default => 'draft'
        };
        $meetingUrl = schoolActivitiesUrl($activity['onlineMeetingUrl'] ?? '');
        $coverUrl = schoolActivitiesUrl($activity['coverImageUrl'] ?? '');
        $feeAmount = $activity['feeAmount'] ?? null;
        $feeLabel = $feeAmount === null
            ? 'Chưa thiết lập'
            : ((float) $feeAmount > 0 ? number_format((float) $feeAmount, 0, ',', '.') . ' ' . ((string) ($activity['currency'] ?? '') ?: 'VND') : 'Miễn phí tham gia');
        $categoryLabel = schoolActivitiesCategoryLabel($activity['category'] ?? '', $activity['displayCategory'] ?? null);
        $teacherName = (string) (($activity['teacherName'] ?? '') ?: 'Giảng viên');
        $teacherInitials = schoolActivitiesInitials($teacherName);
        $experienceList = schoolActivitiesItems($activity['experienceHighlights'] ?? null);
        $skillsList = schoolActivitiesItems($activity['skillTags'] ?? null);
        $eligibilityList = schoolActivitiesItems($activity['eligibilityRules'] ?? null);
        $benefitsList = schoolActivitiesItems($activity['benefitItems'] ?? null);
    ?>
        <article class="school-act-card school-act-card--<?= $badgeTone; ?>" id="activity-<?= schoolActivitiesEscape($activityId); ?>" aria-labelledby="activity-title-<?= schoolActivitiesEscape($activityId); ?>">
            <!-- Card Header -->
            <header class="school-act-card__header">
                <div class="school-act-card__head-left">
                    <div class="school-act-card__top-meta">
                        <span class="school-act-card__category">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?= schoolActivitiesEscape($categoryLabel); ?>
                        </span>
                        <span class="school-act-card__teacher-pill">
                            <span class="school-act-card__avatar"><?= schoolActivitiesEscape($teacherInitials); ?></span>
                            <span>Giảng viên: <strong><?= schoolActivitiesEscape($teacherName); ?></strong></span>
                        </span>
                        <?php if (!empty($activity['approvalRequestedAt'])): ?>
                            <span class="school-act-card__time">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span>Gửi lúc <?= schoolActivitiesDate($activity['approvalRequestedAt']); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <h3 class="school-act-card__title" id="activity-title-<?= schoolActivitiesEscape($activityId); ?>">
                        <?= schoolActivitiesEscape($activity['title']); ?>
                    </h3>
                    <p class="school-act-card__summary">
                        <?= nl2br(schoolActivitiesEscape(($activity['summary'] ?? '') ?: 'Chưa có thông tin tóm tắt ngắn.')); ?>
                    </p>
                </div>
                <!-- Status Badge -->
                <span class="school-act-badge school-act-badge--<?= $badgeTone; ?>">
                    <?php if ($isPending): ?><span class="pulse-dot"></span><?php endif; ?>
                    <span><?= schoolActivitiesEscape($statusLabels[$approvalStatus] ?? 'Chưa xác định'); ?></span>
                </span>
            </header>

            <!-- Quick Facts Ribbon (Thông tin tổng quan & Hình thức) -->
            <div class="school-act-ribbon" aria-label="Thông số tổng quan của hoạt động">
                <!-- Thời gian tổ chức -->
                <div class="school-act-pill school-act-pill--time">
                    <div class="school-act-pill__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div class="school-act-pill__data">
                        <span class="school-act-pill__label">Thời gian tổ chức</span>
                        <span class="school-act-pill__val">
                            <span class="pill-date-start"><?= schoolActivitiesDate($activity['startAt'] ?? null); ?></span>
                            <span class="pill-date-sep" aria-hidden="true">→</span>
                            <span class="pill-date-end"><?= schoolActivitiesDate($activity['endAt'] ?? null); ?></span>
                        </span>
                    </div>
                </div>

                <!-- Hình thức tổ chức (Fix triệt để lỗi icon đè chữ) -->
                <div class="school-act-pill school-act-pill--delivery">
                    <div class="school-act-pill__icon" aria-hidden="true">
                        <?php if (($activity['deliveryMode'] ?? '') === 'online'): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        <?php elseif (($activity['deliveryMode'] ?? '') === 'hybrid'): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                        <?php else: ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="school-act-pill__data">
                        <span class="school-act-pill__label">Hình thức</span>
                        <span class="school-act-pill__val"><?= schoolActivitiesEscape($deliveryLabels[(string) ($activity['deliveryMode'] ?? '')] ?? 'Chưa thiết lập'); ?></span>
                    </div>
                </div>

                <!-- Sức chứa tối đa -->
                <div class="school-act-pill school-act-pill--capacity">
                    <div class="school-act-pill__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="school-act-pill__data">
                        <span class="school-act-pill__label">Sức chứa</span>
                        <span class="school-act-pill__val"><?= number_format((int) ($activity['capacity'] ?? 0), 0, ',', '.'); ?> học viên</span>
                    </div>
                </div>

                <!-- Chi phí tham gia -->
                <div class="school-act-pill school-act-pill--fee">
                    <div class="school-act-pill__icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                    </div>
                    <div class="school-act-pill__data">
                        <span class="school-act-pill__label">Chi phí</span>
                        <span class="school-act-pill__val"><?= schoolActivitiesEscape($feeLabel); ?></span>
                    </div>
                </div>
            </div>

            <!-- Structured Detail Sections (Accordion) -->
            <details class="school-act-details" <?= $isPending || $hasError ? 'open' : ''; ?>>
                <summary>
                    <span class="summary-left">
                        <span class="summary-icon-box" aria-hidden="true">
                            <svg class="summary-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                        </span>
                        <span class="summary-title">Hồ sơ chi tiết & Chính sách hoạt động</span>
                        <span class="summary-pill">5 nội dung</span>
                    </span>
                    <span class="summary-toggle">
                        <span class="toggle-text">Chi tiết</span>
                        <svg class="toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </span>
                </summary>

                <div class="school-act-details__body">
                    <!-- Split Row: Section 1 (Tổng quan) & Section 2 (Kỹ năng & Trải nghiệm) -->
                    <div class="act-sections-row act-sections-row--split">
                        <!-- Group 1: Tổng quan & Mô tả nội dung -->
                        <div class="act-group-card act-group-card--overview">
                            <div class="act-group-card__header">
                                <span class="act-group-card__badge">01</span>
                                <h4 class="act-group-card__title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    <span>Tổng quan & Mô tả nội dung</span>
                                </h4>
                            </div>
                            <div class="act-group-card__content">
                                <div class="act-desc-text">
                                    <?= nl2br(schoolActivitiesEscape(($activity['description'] ?? '') ?: 'Chưa có mô tả chi tiết từ Giảng viên.')); ?>
                                </div>
                                <?php if ($coverUrl): ?>
                                    <div class="act-cover-wrap">
                                        <div class="act-cover-wrap__icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                        </div>
                                        <div class="act-cover-wrap__content">
                                            <a href="<?= schoolActivitiesEscape($coverUrl); ?>" target="_blank" rel="noopener noreferrer" class="act-cover-wrap__link">
                                                <span>Xem ảnh bìa hoạt động gốc</span>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                            </a>
                                            <?php if (!empty($activity['coverImageAlt'])): ?>
                                                <div class="act-cover-wrap__alt">Mô tả ảnh: <?= schoolActivitiesEscape($activity['coverImageAlt']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Group 2: Kỹ năng & Trải nghiệm đạt được (Fix lỗi ký tự $) -->
                        <div class="act-group-card act-group-card--skills">
                            <div class="act-group-card__header">
                                <span class="act-group-card__badge">02</span>
                                <h4 class="act-group-card__title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    <span>Kỹ năng & Trải nghiệm đạt được</span>
                                </h4>
                            </div>
                            <div class="act-group-card__content">
                                <?php if ($skillsList !== []): ?>
                                    <div class="act-sub-section">
                                        <span class="act-sub-section__heading">Kỹ năng phát triển:</span>
                                        <div class="act-skills-wrap">
                                            <?php foreach ($skillsList as $skill): ?>
                                                <span class="act-skill-chip">
                                                    <span class="act-skill-chip__prefix" aria-hidden="true">#</span>
                                                    <span class="act-skill-chip__text"><?= schoolActivitiesEscape($skill); ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($experienceList !== []): ?>
                                    <div class="act-sub-section">
                                        <span class="act-sub-section__heading">Nội dung trải nghiệm thực tế:</span>
                                        <ul class="act-highlights-list">
                                            <?php foreach ($experienceList as $highlight): ?>
                                                <li>
                                                    <span class="act-highlight-icon" aria-hidden="true">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                    </span>
                                                    <span><?= schoolActivitiesEscape($highlight); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if ($skillsList === [] && $experienceList === []): ?>
                                    <p class="act-empty-note">Chưa có thông tin kỹ năng & nội dung chi tiết từ Giảng viên.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Group 3: Điều kiện & Quyền lợi tham gia (2 Columns Balanced Grid) -->
                    <div class="act-group-card act-group-card--terms">
                        <div class="act-group-card__header">
                            <span class="act-group-card__badge">03</span>
                            <h4 class="act-group-card__title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>Điều kiện & Quyền lợi tham gia</span>
                            </h4>
                        </div>
                        <div class="act-split-grid">
                            <!-- Box A: Điều kiện tham gia -->
                            <div class="act-sub-box act-sub-box--eligibility">
                                <div class="act-sub-box__head">
                                    <span class="act-sub-box__icon-wrap" aria-hidden="true">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    </span>
                                    <span>Điều kiện tham gia</span>
                                </div>
                                <?php if ($eligibilityList !== []): ?>
                                    <ul class="act-sub-box__list">
                                        <?php foreach ($eligibilityList as $rule): ?>
                                            <li>
                                                <span class="act-bullet act-bullet--check" aria-hidden="true">✓</span>
                                                <span><?= schoolActivitiesEscape($rule); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="act-empty-note">Mở rộng cho tất cả sinh viên phù hợp trong trường.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Box B: Quyền lợi học viên -->
                            <div class="act-sub-box act-sub-box--benefits">
                                <div class="act-sub-box__head">
                                    <span class="act-sub-box__icon-wrap" aria-hidden="true">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
                                    </span>
                                    <span>Quyền lợi học viên</span>
                                </div>
                                <?php if ($benefitsList !== []): ?>
                                    <ul class="act-sub-box__list">
                                        <?php foreach ($benefitsList as $benefit): ?>
                                            <li>
                                                <span class="act-bullet act-bullet--star" aria-hidden="true">★</span>
                                                <span><?= schoolActivitiesEscape($benefit); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="act-empty-note">Được tham gia trải nghiệm thực tế và nâng cao năng lực chuyên môn.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Group 4: Đơn vị tổ chức & Địa điểm (Tiled Grid Layout) -->
                    <div class="act-group-card act-group-card--organization">
                        <div class="act-group-card__header">
                            <span class="act-group-card__badge">04</span>
                            <h4 class="act-group-card__title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                <span>Đơn vị tổ chức & Địa điểm</span>
                            </h4>
                        </div>
                        <div class="act-tiles-grid">
                            <!-- Địa điểm tổ chức -->
                            <div class="act-tile">
                                <span class="act-tile__label">Địa điểm tổ chức</span>
                                <div class="act-tile__value">
                                    <strong><?= schoolActivitiesEscape(($activity['locationName'] ?? '') ?: 'Chưa cập nhật'); ?></strong>
                                    <?php if (!empty($activity['locationAddress'])): ?>
                                        <div class="act-tile__sub"><?= schoolActivitiesEscape($activity['locationAddress']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Phòng họp trực tuyến -->
                            <div class="act-tile">
                                <span class="act-tile__label">Phòng họp trực tuyến</span>
                                <div class="act-tile__value">
                                    <?php if ($meetingUrl): ?>
                                        <a href="<?= schoolActivitiesEscape($meetingUrl); ?>" target="_blank" rel="noopener noreferrer" class="act-tile__link">
                                            <span><?= schoolActivitiesEscape($meetingUrl); ?></span>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="act-tile__muted"><?= ($activity['deliveryMode'] ?? '') === 'in_person' ? 'Tổ chức trực tiếp tại trường' : 'Chưa cập nhật link phòng họp'; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Đơn vị tổ chức -->
                            <div class="act-tile">
                                <span class="act-tile__label">Đơn vị tổ chức</span>
                                <div class="act-tile__value">
                                    <strong><?= schoolActivitiesEscape(($activity['organizerName'] ?? '') ?: 'Trường Đại học Cần Thơ'); ?></strong>
                                </div>
                            </div>

                            <!-- Đầu mối liên hệ -->
                            <div class="act-tile">
                                <span class="act-tile__label">Đầu mối liên hệ</span>
                                <div class="act-tile__value">
                                    <span><?= schoolActivitiesEscape(($activity['organizerContact'] ?? '') ?: 'Chưa cập nhật'); ?></span>
                                    <?php if (!empty($activity['organizerEmail']) || !empty($activity['organizerPhone'])): ?>
                                        <div class="act-tile__sub">
                                            <?php if (!empty($activity['organizerEmail'])): ?>
                                                <div>Email: <a href="mailto:<?= schoolActivitiesEscape($activity['organizerEmail']); ?>"><?= schoolActivitiesEscape($activity['organizerEmail']); ?></a></div>
                                            <?php endif; ?>
                                            <?php if (!empty($activity['organizerPhone'])): ?>
                                                <div>SĐT: <?= schoolActivitiesEscape($activity['organizerPhone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Đối tượng tham gia -->
                            <div class="act-tile">
                                <span class="act-tile__label">Đối tượng tham gia</span>
                                <div class="act-tile__value">
                                    <span><?= schoolActivitiesEscape(($activity['targetAudience'] ?? '') ?: 'Sinh viên trong trường'); ?></span>
                                </div>
                            </div>

                            <!-- Giáo viên phụ trách -->
                            <?php if (!empty($activity['responsibleTeacherName'])): ?>
                                <div class="act-tile act-tile--highlight">
                                    <span class="act-tile__label">Giáo viên phụ trách</span>
                                    <div class="act-tile__value">
                                        <strong style="color: #C2410C;"><?= schoolActivitiesEscape($activity['responsibleTeacherName']); ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Group 5: Đăng ký & Công nhận (Pipeline + Tile Grid) -->
                    <div class="act-group-card act-group-card--timeline">
                        <div class="act-group-card__header">
                            <span class="act-group-card__badge">05</span>
                            <h4 class="act-group-card__title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <span>Tiến trình đăng ký & Công nhận trải nghiệm</span>
                            </h4>
                        </div>

                        <!-- 3-Phase Milestone Pipeline -->
                        <div class="act-timeline-pipeline">
                            <div class="act-timeline-step">
                                <div class="act-timeline-step__marker act-timeline-step__marker--open" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <div class="act-timeline-step__content">
                                    <span class="act-timeline-step__label">Mở cổng đăng ký</span>
                                    <span class="act-timeline-step__date"><?= schoolActivitiesDate($activity['registrationOpensAt'] ?? null); ?></span>
                                </div>
                            </div>

                            <div class="act-timeline-step">
                                <div class="act-timeline-step__marker act-timeline-step__marker--close" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                </div>
                                <div class="act-timeline-step__content">
                                    <span class="act-timeline-step__label">Đóng cổng đăng ký</span>
                                    <span class="act-timeline-step__date"><?= schoolActivitiesDate($activity['registrationClosesAt'] ?? null); ?></span>
                                </div>
                            </div>

                            <div class="act-timeline-step">
                                <div class="act-timeline-step__marker act-timeline-step__marker--cancel" aria-hidden="true">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                </div>
                                <div class="act-timeline-step__content">
                                    <span class="act-timeline-step__label">Hạn chót hủy tham gia</span>
                                    <span class="act-timeline-step__date"><?= schoolActivitiesDate($activity['cancellationClosesAt'] ?? null); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Chính sách & Công nhận Grid -->
                        <div class="act-tiles-grid" style="margin-top: 1rem;">
                            <div class="act-tile">
                                <span class="act-tile__label">Chính sách duyệt đăng ký</span>
                                <div class="act-tile__value">
                                    <strong><?= schoolActivitiesEscape($registrationLabels[(string) ($activity['approvalMode'] ?? '')] ?? 'Chưa thiết lập'); ?></strong>
                                </div>
                            </div>

                            <div class="act-tile">
                                <span class="act-tile__label">Thời lượng công nhận</span>
                                <div class="act-tile__value">
                                    <?php if (isset($activity['confirmedHours'])): ?>
                                        <span class="act-badge-hours">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                                            <span><?= schoolActivitiesEscape(rtrim(rtrim(number_format((float) $activity['confirmedHours'], 2, ',', ''), '0'), ',')); ?> giờ trải nghiệm</span>
                                        </span>
                                    <?php else: ?>
                                        <span class="act-tile__muted">Chưa thiết lập</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="act-tile">
                                <span class="act-tile__label">Chứng nhận cấp sau sự kiện</span>
                                <div class="act-tile__value">
                                    <span><?= schoolActivitiesEscape(($activity['certificateLabel'] ?? '') ?: 'Chưa thiết lập'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </details>

            <!-- Review Action & Feedback Section -->
            <?php if ($isPending): ?>
                <form method="post" class="school-act-form" action="<?= schoolActivitiesEscape(app_href('/app/school/activities.php?' . http_build_query(['status' => $status, 'q' => $search]))); ?>#activity-<?= schoolActivitiesEscape($activityId); ?>" data-activity-review>
                    <input type="hidden" name="csrfToken" value="<?= schoolActivitiesEscape($csrfToken); ?>">
                    <input type="hidden" name="activityId" value="<?= schoolActivitiesEscape($activityId); ?>">
                    <input type="hidden" name="action" value="" class="school-act-action-field">

                    <!-- Feedback Box -->
                    <div class="school-act-feedback-card">
                        <div class="school-act-feedback-card__head">
                            <label class="school-act-feedback-card__label" for="reason-<?= schoolActivitiesEscape($activityId); ?>">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                <span>Ý kiến phản hồi & Ghi chú cho Giảng viên</span>
                            </label>
                            <span class="school-act-feedback-card__counter" data-char-counter>0 / 1.000 ký tự</span>
                        </div>
                        <textarea id="reason-<?= schoolActivitiesEscape($activityId); ?>"
                                  name="reason"
                                  maxlength="1000"
                                  rows="3"
                                  aria-describedby="reason-hint-<?= schoolActivitiesEscape($activityId); ?>"
                                  placeholder="Nhập nội dung cần điều chỉnh hoặc lý do từ chối để Giảng viên nắm rõ thông tin..."><?= $hasError ? schoolActivitiesEscape($postedReason) : ''; ?></textarea>
                        <p class="school-act-feedback-card__hint" id="reason-hint-<?= schoolActivitiesEscape($activityId); ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span>Bắt buộc nhập phản hồi khi chọn “Yêu cầu chỉnh sửa” hoặc “Từ chối”. Tối đa 1.000 ký tự.</span>
                        </p>
                    </div>

                    <!-- Approval Action Buttons -->
                    <div class="school-act-actions">
                        <button class="btn-approve" name="action" value="approve" type="submit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>Phê duyệt hoạt động</span>
                        </button>
                        <button class="btn-request-changes" name="action" value="request_changes" type="submit">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            <span>Yêu cầu chỉnh sửa</span>
                        </button>
                        <button class="btn-reject" name="action" value="reject" type="submit">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <span>Từ chối</span>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <!-- Outcome message for non-pending activities -->
                <div class="school-act-outcome school-act-outcome--<?= $badgeTone; ?>">
                    <div class="school-act-outcome__icon" aria-hidden="true">
                        <?php if ($approvalStatus === 'approved'): ?>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <?php elseif ($approvalStatus === 'changes_requested'): ?>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#EA580C" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        <?php elseif ($approvalStatus === 'rejected'): ?>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#E11D48" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <?php else: ?>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <?php endif; ?>
                    </div>
                    <div class="school-act-outcome__text">
                        <?php if ($approvalStatus === 'approved'): ?>
                            <p><strong>Hoạt động đã được phê duyệt<?= !empty($activity['approvedAt']) ? ' vào lúc ' . schoolActivitiesDate($activity['approvedAt']) : ''; ?>.</strong> Trạng thái công bố: <span style="font-weight: 600;"><?= schoolActivitiesEscape($publicationLabels[(string) ($activity['status'] ?? '')] ?? ''); ?></span><?= ($activity['status'] ?? '') === 'draft' ? ' · Giảng viên hiện có thể mở công bố cho sinh viên đăng ký.' : '.'; ?></p>
                        <?php elseif ($approvalStatus === 'draft'): ?>
                            <p>Giảng viên đang trong quá trình soạn thảo và chưa đệ trình hoạt động này cho Nhà trường duyệt.</p>
                        <?php elseif (!empty($activity['approvalReason'])): ?>
                            <p><strong>Phản hồi của Nhà trường gửi Giảng viên:</strong><br><?= nl2br(schoolActivitiesEscape($activity['approvalReason'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php
$pageBody = ob_get_clean();
$extraStyles = '<link rel="stylesheet" href="../../assets/css/school-activities.css?v=' . (file_exists(dirname(__DIR__, 2) . '/assets/css/school-activities.css') ? filemtime(dirname(__DIR__, 2) . '/assets/css/school-activities.css') : time()) . '">';
$toastScript = '';
if ($flash) {
    $toastScript .= '<script>window.addEventListener("DOMContentLoaded", function() { if (typeof showSchoolToast === "function") { showSchoolToast(' . json_encode($flash, JSON_UNESCAPED_UNICODE) . '); } });</script>';
} elseif ($error) {
    $toastScript .= '<script>window.addEventListener("DOMContentLoaded", function() { if (typeof showSchoolToast === "function") { showSchoolToast(' . json_encode($error, JSON_UNESCAPED_UNICODE) . ', "error"); } });</script>';
}
$extraScripts = $toastScript . '<script src="../../assets/js/school-activities.js?v=' . (file_exists(dirname(__DIR__, 2) . '/assets/js/school-activities.js') ? filemtime(dirname(__DIR__, 2) . '/assets/js/school-activities.js') : time()) . '" defer></script>';
require __DIR__ . '/includes/layout.php';
