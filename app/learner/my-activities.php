<?php
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/activity-data.php';

if (!function_exists('learner_activity_cover_or_fallback')) {
    function learner_activity_cover_or_fallback(mixed $value, string $fallback): string
    {
        $candidate = trim((string) $value);
        if ($candidate === '' || str_contains($candidate, '..')) return $fallback;
        return preg_match('#\A(?:/app/learner/)?assets/activities/[a-z0-9/_-]+\.(?:webp|png|jpe?g|svg)\z#i', $candidate) === 1
            ? $candidate
            : $fallback;
    }
}

$pageTitle = 'Hoạt động của tôi';
$currentRoute = '/app/learner/activities.php';
$activityNavigationActive = 'registered';
$learnerDataSource = learner_safe_runtime_diagnostics()['source'];
$activeRegistrations = learner_activity_active_registrations(learner_current_student_id());
$approvedCount = count(array_filter($activeRegistrations, static fn (array $item): bool => ($item['status'] ?? '') === 'approved'));
$pendingCount = count(array_filter($activeRegistrations, static fn (array $item): bool => ($item['status'] ?? '') === 'pending'));
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$boot = [
    'source' => $learnerDataSource,
    'student_id' => learner_current_student_id(),
    'csrf_token' => (string) ($GLOBALS['learner_page_context']['csrfToken'] ?? ''),
    'catalog' => [],
    'registrations' => $activeRegistrations,
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Hoạt động của tôi | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
    <link rel="stylesheet" href="assets/activities/activities.css">
</head>
<body class="learner-app learner-page-my-activities">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content" id="main-content" data-my-activities-page>
                <div class="learner-activities-shell">
                    <section class="learner-activity-registered-hero" aria-labelledby="learner-my-activities-page-title">
                        <div>
                            <p>HÀNH TRÌNH CỦA BẠN</p>
                            <h1 id="learner-my-activities-page-title">Hoạt động đã đăng ký</h1>
                            <span>Theo dõi trạng thái duyệt và sẵn sàng check-in khi hoạt động bắt đầu.</span>
                        </div>
                        <img src="assets/activities/illustrations/hero-registered.svg" alt="Minh họa danh sách hoạt động đã đăng ký">
                    </section>
                    <?php include __DIR__ . '/includes/activity-navigation.php'; ?>

                    <section class="learner-activity-registered-kpis" aria-label="Tổng quan đăng ký">
                        <div><span><?= learner_icon('clipboard', 22); ?></span><strong data-registered-kpi="total"><?= count($activeRegistrations); ?></strong><small>Tổng đăng ký</small></div>
                        <div><span><?= learner_icon('check', 22); ?></span><strong data-registered-kpi="approved"><?= $approvedCount; ?></strong><small>Đã được duyệt</small></div>
                        <div><span><?= learner_icon('clock', 22); ?></span><strong data-registered-kpi="pending"><?= $pendingCount; ?></strong><small>Chờ duyệt</small></div>
                    </section>

                    <section class="learner-activity-registered-toolbar" aria-label="Tìm và lọc đăng ký">
                        <label><?= learner_icon('search', 19); ?><input type="search" aria-label="Tìm hoạt động hoặc đơn vị tổ chức" placeholder="Tìm hoạt động hoặc đơn vị tổ chức" data-registration-search></label>
                        <div class="learner-filter-list learner-registration-filters">
                            <?php foreach (['all' => 'Tất cả', 'approved' => 'Đã duyệt', 'pending' => 'Chờ duyệt'] as $id => $label): ?>
                                <button class="learner-filter-button" type="button" data-registration-filter="<?= $id; ?>" aria-pressed="<?= $id === 'all' ? 'true' : 'false'; ?>"><?= $label; ?></button>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <p class="learner-registration-message" data-registration-command-status role="status" aria-live="polite"></p>
                    <section class="learner-my-activities-list" data-my-registration-list aria-live="polite">
                        <?php foreach ($activeRegistrations as $registration): ?>
                            <?php
                            $status = (string) ($registration['status'] ?? '');
                            $activityId = (string) ($registration['activity_id'] ?? '');
                            $isApproved = $status === 'approved';
                            $statusLabel = match ($status) {
                                'approved' => 'Đã được duyệt',
                                'waitlisted' => 'Danh sách chờ',
                                default => 'Chờ giáo viên duyệt',
                            };
                            $cancellationClose = null;
                            try {
                                $rawCancellationClose = trim((string) ($registration['cancellation_closes_at'] ?? ''));
                                $cancellationClose = $rawCancellationClose === '' ? null : new DateTimeImmutable($rawCancellationClose, new DateTimeZone('UTC'));
                            } catch (Throwable) {
                                $cancellationClose = null;
                            }
                            $canCancel = in_array($status, ['pending', 'approved', 'waitlisted'], true)
                                && $cancellationClose instanceof DateTimeImmutable
                                && $now < $cancellationClose;
                            $cover = learner_activity_cover_or_fallback(
                                $registration['cover_image_url'] ?? '',
                                'assets/activities/illustrations/hero-registered.svg',
                            );
                            ?>
                            <article class="learner-activity-registered-card" data-status="<?= learner_escape($status); ?>" data-registration-card data-registration-search-text="<?= learner_escape(($registration['title'] ?? '') . ' ' . ($registration['organizer_name'] ?? '') . ' ' . ($registration['school_name'] ?? '')); ?>">
                                <div class="learner-activity-registered-card__cover"><img src="<?= learner_escape($cover); ?>" alt="<?= learner_escape($registration['cover_image_alt'] ?? ('Ảnh ' . ($registration['title'] ?? 'hoạt động'))); ?>"></div>
                                <div class="learner-activity-registered-card__body">
                                    <div class="learner-activity-registered-card__top"><span class="learner-activity-category-chip"><?= learner_escape($registration['filter_category'] ?? $registration['category'] ?? 'Hoạt động'); ?></span><span class="learner-registration-status learner-registration-status--<?= learner_escape($status); ?>"><?= learner_escape($statusLabel); ?></span></div>
                                    <h2><?= learner_escape($registration['title'] ?? 'Hoạt động TalentHub'); ?></h2>
                                    <p class="learner-activity-registered-card__school"><?= learner_icon('building', 17); ?> <?= learner_escape($registration['organizer_name'] ?? $registration['school_name'] ?? 'Đơn vị tổ chức'); ?></p>
                                    <div class="learner-activity-registered-card__meta">
                                        <span><?= learner_icon('calendar', 17); ?> <?= learner_escape((new DateTimeImmutable((string) ($registration['start_at'] ?? 'now')))->format('d/m/Y · H:i')); ?></span>
                                        <span><?= learner_icon('map-pin', 17); ?> <?= learner_escape($registration['location_name'] ?? $registration['location'] ?? 'Chưa cập nhật'); ?></span>
                                        <span><?= learner_icon('clock', 17); ?> Đăng ký <?= learner_escape((new DateTimeImmutable((string) ($registration['created_at'] ?? 'now')))->format('d/m/Y')); ?></span>
                                    </div>
                                    <ol class="learner-activity-registration-stepper" aria-label="Tiến trình đăng ký">
                                        <li class="is-complete"><span>1</span>Đăng ký</li>
                                        <li class="<?= $isApproved ? 'is-complete' : 'is-current'; ?>"<?= $isApproved ? '' : ' aria-current="step"'; ?>><span>2</span><?= $isApproved ? 'Đã duyệt' : ($status === 'waitlisted' ? 'Danh sách chờ' : 'Chờ duyệt'); ?></li>
                                        <li class="<?= $isApproved ? 'is-current' : ''; ?>"<?= $isApproved ? ' aria-current="step"' : ''; ?>><span>3</span><?= $isApproved ? 'Chưa check-in' : 'Check-in'; ?></li>
                                    </ol>
                                    <div class="learner-activity-registered-card__actions">
                                        <a class="learner-btn learner-btn--outline" href="activity-detail.php?id=<?= learner_escape(rawurlencode($activityId)); ?>">Xem chi tiết</a>
                                        <?php if ($isApproved): ?><a class="learner-btn learner-btn--primary" href="checkin.php?activity=<?= learner_escape(rawurlencode($activityId)); ?>">Đi tới Check-in QR</a><?php endif; ?>
                                        <?php if ($canCancel): ?><button type="button" class="learner-btn learner-btn--quiet" data-cancel-registration="<?= learner_escape((string) ($registration['id'] ?? '')); ?>" data-cancellation-closes-at="<?= learner_escape($cancellationClose->format(DATE_ATOM)); ?>">Hủy đăng ký</button><?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                    <section class="learner-activity-registered-empty" data-registration-empty <?= $activeRegistrations === [] ? '' : 'hidden'; ?> role="status">
                        <?= learner_icon('calendar', 32); ?><h2>Chưa có hoạt động đang đăng ký</h2><p>Khám phá catalog của trường để bắt đầu hành trình trải nghiệm.</p><a href="activities.php">Khám phá hoạt động</a>
                    </section>
                    <section class="learner-activity-qr-note"><?= learner_icon('info', 22); ?><div><strong>Check-in bằng mã QR</strong><p>Nút check-in chỉ xuất hiện sau khi đăng ký được duyệt. Mã QR được giáo viên cung cấp tại hoạt động.</p></div></section>
                </div>
            </main>
        </div>
    </div>
    <script id="learner-activities-boot" type="application/json"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-activities.js"></script>
</body>
</html>
