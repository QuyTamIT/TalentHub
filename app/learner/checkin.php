<?php
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/activity-data.php';

$pageTitle = 'Check-in QR';
$currentRoute = '/app/learner/checkin.php';
$activityRouteId = trim((string) ($_GET['activity'] ?? ''));
$activitySource = learner_repository_factory()->source();
$activityRouteIsValid = $activityRouteId !== ''
    && ($activitySource !== 'database' || \TalentHub\Learner\Data\Support\Uuid::isValid($activityRouteId));
$linkedActivity = $activityRouteIsValid ? learner_activity_find($activityRouteId) : null;
$linkedRegistration = null;
$linkedRegistrationLabel = null;
if ($linkedActivity !== null) {
    foreach (learner_activity_registration_history(learner_current_student_id()) as $registration) {
        if ((string) ($registration['activity_id'] ?? '') !== (string) ($linkedActivity['id'] ?? '')) {
            continue;
        }
        $status = (string) ($registration['status'] ?? '');
        $linkedRegistrationLabel = match ($status) {
            'approved' => 'Đã được duyệt',
            'pending' => 'Chờ giáo viên duyệt',
            'waitlisted' => 'Danh sách chờ',
            'attended' => 'Đã tham gia',
            default => null,
        };
        if ($linkedRegistrationLabel !== null) {
            $linkedRegistration = $registration;
        }
        break;
    }
}
if ($linkedRegistration === null) {
    $linkedActivity = null;
}
$checkinBoot = ['csrfToken' => (string) ($GLOBALS['learner_page_context']['csrfToken'] ?? ''), 'apiBase' => '/app/learner/api/v1', 'historyUrl' => '/app/learner/api/v1/checkins.php'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in trải nghiệm | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-checkin" data-checkin-page>
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content" id="main-content">
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-checkin-page-title',
                    'eyebrow' => 'Ghi nhận trải nghiệm',
                    'title' => 'Check-in trải nghiệm',
                    'description' => 'Quét mã hoặc nhập token thủ công để ghi nhận giờ trải nghiệm ngay lập tức.',
                    'icon' => 'qr',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>
                <div class="learner-checkin-layout">
                    <section class="learner-card learner-checkin-scanner-card" aria-labelledby="learner-qr-title">
                        <div class="learner-checkin-card-heading">
                            <div>
                                <span class="learner-checkin-eyebrow">Quét QR để ghi nhận</span>
                                <h2 id="learner-qr-title">Đưa mã QR vào giữa khung</h2>
                                <p>Quét mã được cấp cho hoạt động đang diễn ra để ghi nhận trải nghiệm ngay lập tức.</p>
                            </div>
                            <span class="learner-checkin-camera-status"><span aria-hidden="true"></span> Camera sẵn sàng</span>
                        </div>
                        <div class="learner-checkin-camera" data-camera-shell>
                            <video class="learner-checkin-camera__video" data-camera-video autoplay muted playsinline hidden></video>
                            <div class="learner-checkin-camera__placeholder" data-camera-placeholder>
                                <div class="learner-checkin-camera__placeholder-content">
                                    <?= learner_icon('qr', 34); ?>
                                    <strong>Sẵn sàng quét mã</strong>
                                    <span>Cho phép camera để bắt đầu</span>
                                </div>
                            </div>
                            <div class="learner-scanner-frame" aria-hidden="true">
                                <span class="learner-scanner-frame__corner learner-scanner-frame__corner--top-left"></span>
                                <span class="learner-scanner-frame__corner learner-scanner-frame__corner--top-right"></span>
                                <span class="learner-scanner-frame__corner learner-scanner-frame__corner--bottom-left"></span>
                                <span class="learner-scanner-frame__corner learner-scanner-frame__corner--bottom-right"></span>
                                <span class="learner-scanner-frame__line"></span>
                            </div>
                        </div>
                        <p class="learner-checkin-scanner-note">Đưa mã QR vào giữa khung để check-in</p>
                        <div class="learner-checkin-feedback" data-checkin-feedback aria-live="polite"></div>
                        <div class="learner-checkin-actions">
                            <button class="learner-btn learner-btn--primary" type="button" data-camera-start><?= learner_icon('qr', 19); ?> Bật camera</button>
                            <button class="learner-btn learner-btn--outline" type="button" data-camera-stop hidden>Tắt camera</button>
                        </div>
                    </section>

                    <div class="learner-checkin-side-column">
                        <?php if ($linkedActivity): ?>
                            <section class="learner-card learner-checkin-activity-card" aria-labelledby="learner-checkin-activity-title">
                                <div class="learner-checkin-card-icon"><?= learner_icon('calendar', 18); ?></div>
                                <span class="learner-checkin-card-label">Hoạt động đang diễn ra</span>
                                <span class="learner-verified-pill"><?= learner_icon('check', 14); ?> <?= learner_escape($linkedRegistrationLabel); ?></span>
                                <h2 id="learner-checkin-activity-title"><?= learner_escape($linkedActivity['title']); ?></h2>
                                <dl class="learner-checkin-activity-meta">
                                    <div><dt><?= learner_icon('clock', 15); ?></dt><dd><?= learner_escape((new DateTimeImmutable($linkedActivity['start_at']))->format('d/m/Y · H:i')); ?></dd></div>
                                    <div><dt><?= learner_icon('map-pin', 15); ?></dt><dd><?= learner_escape($linkedActivity['location']); ?></dd></div>
                                </dl>
                                <a class="learner-btn learner-btn--outline learner-checkin-activity-link" href="activity-detail.php?id=<?= learner_escape($linkedActivity['id']); ?>">Xem chi tiết hoạt động</a>
                            </section>
                        <?php else: ?>
                            <section class="learner-card learner-checkin-activity-card learner-checkin-activity-card--empty" aria-labelledby="learner-checkin-activity-title">
                                <div class="learner-checkin-card-icon"><?= learner_icon('calendar', 18); ?></div>
                                <span class="learner-checkin-card-label">Chưa chọn hoạt động</span>
                                <h2 id="learner-checkin-activity-title">Chọn hoạt động để bắt đầu</h2>
                                <p>Mở check-in từ trang chi tiết hoạt động bạn đang tham gia để liên kết đúng phiên QR.</p>
                                <a class="learner-btn learner-btn--outline learner-checkin-activity-link" href="activities.php">Xem hoạt động</a>
                            </section>
                        <?php endif; ?>

                        <details class="learner-card learner-checkin-manual-card">
                            <summary><span class="learner-checkin-manual-icon"><?= learner_icon('clipboard', 18); ?></span><span><strong>Nhập token thủ công</strong><small>Dùng khi camera bị chặn</small></span><span class="learner-checkin-summary-chevron" aria-hidden="true">⌄</span></summary>
                            <div class="learner-checkin-manual-card__body">
                                <form class="learner-checkin-form" data-manual-form novalidate>
                                    <label class="learner-form-field"><span>Token QR</span><textarea data-manual-token rows="3" placeholder="Dán token QR vào đây" required></textarea></label>
                                    <div class="learner-checkin-actions learner-checkin-actions--manual"><button class="learner-btn learner-btn--primary" type="submit" data-submit-checkin>Gửi check-in</button><button class="learner-btn learner-btn--ghost" type="button" data-reset-checkin>Xóa</button></div>
                                </form>
                                <div class="learner-checkin-api-state" data-api-state aria-live="polite"></div>
                                <a class="learner-btn learner-btn--outline learner-checkin-history-action" href="activity-history.php" data-checkin-history-action hidden style="display: none;">Xem lịch sử hoạt động</a>
                            </div>
                        </details>

                        <section class="learner-card learner-checkin-history" aria-labelledby="learner-checkin-history-title">
                            <div class="learner-checkin-section-heading"><div><span class="learner-checkin-eyebrow">Hoạt động gần đây</span><h2 id="learner-checkin-history-title">Lịch sử check-in</h2></div><a href="activity-history.php">Xem tất cả</a></div>
                            <div class="learner-checkin-list" data-checkin-history data-checkin-history-source="server">
                                <p class="learner-empty-state">Đang tải lịch sử check-in từ máy chủ...</p>
                            </div>
                        </section>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script id="learner-checkin-boot" type="application/json"><?= json_encode($checkinBoot, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/vendor/jsQR.js"></script>
    <script src="../../assets/js/learner-checkin.js"></script>
</body>
</html>
