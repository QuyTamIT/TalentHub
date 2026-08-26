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
                <?php if ($linkedActivity): ?>
                    <section class="learner-card learner-checkin-linked-activity">
                        <span class="learner-verified-pill"><?= learner_icon('check', 14); ?> <?= learner_escape($linkedRegistrationLabel); ?></span>
                        <div>
                            <h2><?= learner_escape($linkedActivity['title']); ?></h2>
                            <p><?= learner_escape((new DateTimeImmutable($linkedActivity['start_at']))->format('d/m/Y · H:i')); ?> · <?= learner_escape($linkedActivity['location']); ?></p>
                        </div>
                        <a class="learner-btn learner-btn--outline" href="activity-detail.php?id=<?= learner_escape($linkedActivity['id']); ?>">Xem chi tiết</a>
                    </section>
                <?php endif; ?>
                <div class="learner-checkin-grid">
                    <section class="learner-card learner-qr-card" aria-labelledby="learner-qr-title">
                        <div class="learner-checkin-camera" data-camera-shell>
                            <video class="learner-checkin-camera__video" data-camera-video autoplay muted playsinline hidden></video>
                            <div class="learner-checkin-camera__placeholder" data-camera-placeholder><p>Camera quét sẽ hiển thị ở đây.</p></div>
                        </div>
                        <h2 id="learner-qr-title">Camera quét mã</h2>
                        <p>Quét mã QR được cấp cho hoạt động đang diễn ra hoặc dán token nếu camera bị chặn.</p>
                        <div class="learner-checkin-feedback" data-checkin-feedback aria-live="polite"></div>
                        <div class="learner-modal__actions"><button class="learner-btn learner-btn--primary" type="button" data-camera-start><?= learner_icon('qr', 19); ?> Bật camera</button><button class="learner-btn learner-btn--outline" type="button" data-camera-stop hidden>Tắt camera</button></div>
                    </section>
                    <section class="learner-card learner-checkin-manual" aria-labelledby="learner-manual-title">
                        <h2 id="learner-manual-title">Nhập token thủ công</h2>
                        <form class="learner-checkin-form" data-manual-form novalidate><label class="learner-form-field"><span>Token QR</span><textarea data-manual-token rows="4" placeholder="Dán token QR vào đây" required></textarea></label><div class="learner-modal__actions"><button class="learner-btn learner-btn--primary" type="submit" data-submit-checkin>Gửi check-in</button><button class="learner-btn learner-btn--ghost" type="button" data-reset-checkin>Xóa</button></div></form>
                        <div class="learner-checkin-api-state" data-api-state></div>
                        <a class="learner-btn learner-btn--outline" href="activity-history.php" data-checkin-history-action hidden style="display: none;">Xem lịch sử hoạt động</a>
                    </section>
                    <section class="learner-card learner-checkin-history" aria-labelledby="learner-checkin-history-title">
                        <h2 id="learner-checkin-history-title">Lịch sử check-in</h2>
                        <div class="learner-checkin-list" data-checkin-history data-checkin-history-source="server">
                            <p class="learner-empty-state">Đang tải lịch sử check-in từ máy chủ...</p>
                        </div>
                    </section>
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
