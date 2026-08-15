<?php
/** TalentHub Learner - QR check-in */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/activity-data.php';

$pageTitle = 'Check-in QR';
$currentRoute = '/app/learner/checkin.php';
$linkedActivity = isset($_GET['activity']) ? learner_activity_find((string) $_GET['activity']) : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Check-in hoạt động trải nghiệm và theo dõi lịch sử giờ trải nghiệm trên TalentHub.">
    <title>Check-in trải nghiệm | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-checkin">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <div class="learner-page-heading">
                    <h1>Check-in trải nghiệm</h1>
                    <p>Quét mã tại địa điểm hoạt động — giờ trải nghiệm sẽ được ghi nhận tự động.</p>
                </div>
                <?php if ($linkedActivity): ?>
                    <section class="learner-card learner-checkin-linked-activity">
                        <span class="learner-verified-pill"><?= learner_icon('check', 14); ?> Đã đăng ký</span>
                        <div><h2><?= learner_escape($linkedActivity['title']); ?></h2><p><?= learner_escape((new DateTimeImmutable($linkedActivity['start_at']))->format('d/m/Y · H:i')); ?> · <?= learner_escape($linkedActivity['location']); ?></p></div>
                        <a class="learner-btn learner-btn--outline" href="activity-detail.php?id=<?= learner_escape($linkedActivity['id']); ?>">Xem chi tiết</a>
                    </section>
                <?php endif; ?>

                <div class="learner-checkin-grid">
                    <section class="learner-card learner-qr-card" aria-labelledby="learner-qr-title">
                        <svg class="learner-qr-code" viewBox="0 0 210 210" role="img" aria-label="Mã QR mẫu của Nguyễn Văn A">
                            <rect width="210" height="210" rx="12" fill="var(--surface)"></rect>
                            <g fill="var(--text-primary)">
                                <path d="M20 20h55v55H20zm10 10v35h35V30zm10 10h15v15H40zM135 20h55v55h-55zm10 10v35h35V30zm10 10h15v15h-15zM20 135h55v55H20zm10 10v35h35v-35zm10 10h15v15H40z"></path>
                                <path d="M90 20h10v10H90zM110 20h10v20h-10zM85 45h20v10H85zM115 50h10v20h-10zM85 70h15v15H85zM105 85h20v10h-20zM130 85h10v20h-10zM150 85h20v10h-20zM180 85h10v15h-10zM80 105h20v10H80zM110 105h10v20h-10zM130 115h20v10h-20zM160 105h10v20h-10zM180 115h10v15h-10zM85 130h15v20H85zM105 135h20v10h-20zM135 140h10v20h-10zM155 135h20v10h-20zM180 150h10v20h-10zM85 165h20v10H85zM115 155h10v25h-10zM140 175h20v15h-20zM170 180h20v10h-20z"></path>
                            </g>
                        </svg>
                        <h2 id="learner-qr-title">Mã QR của bạn</h2>
                        <p>Đưa cho ban tổ chức quét mã tại địa điểm hoạt động để ghi nhận giờ trải nghiệm.</p>
                        <button class="learner-btn learner-btn--primary" type="button" data-open-modal="learner-scanner-modal">
                            <?= learner_icon('qr', 19); ?> Mở camera quét
                        </button>
                    </section>

                    <section class="learner-card learner-checkin-history" aria-labelledby="learner-checkin-history-title">
                        <h2 id="learner-checkin-history-title">Lịch sử check-in</h2>
                        <div class="learner-checkin-list">
                            <?php foreach ($checkinHistory as $record): ?>
                                <article class="learner-checkin-record" data-checkin-record>
                                    <span class="learner-checkin-record__icon" aria-hidden="true"><?= learner_icon('check', 20); ?></span>
                                    <div class="learner-checkin-record__content">
                                        <h3><?= learner_escape($record['activity']); ?></h3>
                                        <p>
                                            <span><?= learner_icon('clock', 16); ?> <?= learner_escape($record['time']); ?></span>
                                            <span><?= learner_icon('map-pin', 16); ?> <?= learner_escape($record['location']); ?></span>
                                        </p>
                                    </div>
                                    <div class="learner-checkin-record__status">
                                        <?php if ($record['confirmed']): ?>
                                            <span class="learner-verified-badge">Đã xác nhận</span>
                                        <?php endif; ?>
                                        <strong>+<?= learner_escape($record['hours']); ?>h</strong>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <div class="learner-modal" id="learner-scanner-modal" role="dialog" aria-modal="true" aria-labelledby="learner-scanner-title" hidden>
        <div class="learner-modal__backdrop" data-close-modal></div>
        <div class="learner-modal__dialog learner-modal__dialog--compact" tabindex="-1">
            <div class="learner-modal__header">
                <div>
                    <span class="learner-modal__eyebrow">Mô phỏng quét mã</span>
                    <h2 id="learner-scanner-title">Khu vực camera quét</h2>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng khu vực quét mã"><?= learner_icon('x', 22); ?></button>
            </div>
            <div class="learner-scanner-frame" aria-hidden="true">
                <span class="learner-scanner-frame__corner learner-scanner-frame__corner--top-left"></span>
                <span class="learner-scanner-frame__corner learner-scanner-frame__corner--top-right"></span>
                <span class="learner-scanner-frame__corner learner-scanner-frame__corner--bottom-left"></span>
                <span class="learner-scanner-frame__corner learner-scanner-frame__corner--bottom-right"></span>
                <span class="learner-scanner-frame__line"></span>
                <?= learner_icon('qr', 64); ?>
            </div>
            <p class="learner-modal__copy learner-scanner-note"><strong>Đây là giao diện demo.</strong> Hệ thống không yêu cầu quyền truy cập camera và chưa thực hiện quét mã thật.</p>
            <div class="learner-modal__actions">
                <button class="learner-btn learner-btn--primary" type="button" data-close-modal>Đã hiểu</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
