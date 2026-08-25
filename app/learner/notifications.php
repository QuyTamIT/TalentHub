<?php
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Thông báo';
$currentRoute = '/app/learner/notifications.php';
$learnerDataSource = learner_safe_runtime_diagnostics()['source'];
$boot = [
    'source' => $learnerDataSource,
    'student_id' => learner_current_student_id(),
    'csrfToken' => (string) ($GLOBALS['learner_page_context']['csrfToken'] ?? ''),
    'apiBase' => '/app/learner/api/v1',
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Thông báo | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-notifications">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content" id="main-content" data-notifications-page>
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-notifications-page-title',
                    'eyebrow' => 'Trung tâm cập nhật',
                    'title' => 'Thông báo',
                    'description' => 'Theo dõi thông báo hoạt động, check-in, ứng tuyển và kết quả đánh giá năng lực.',
                    'icon' => 'bell',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>

                <div class="learner-notification-heading">
                    <div class="learner-filter-list">
                        <button class="learner-filter-button is-active" type="button" data-notification-filter="all" aria-pressed="true">
                            Tất cả
                        </button>
                        <button class="learner-filter-button" type="button" data-notification-filter="unread" aria-pressed="false">
                            Chưa đọc
                        </button>
                    </div>
                    <div class="learner-notification-heading__actions">
                        <button class="learner-btn learner-btn--ghost learner-btn--sm" id="learner-open-prefs" type="button">
                            <?= learner_icon('filter', 16); ?>
                            <span>Cài đặt thông báo</span>
                        </button>
                        <button class="learner-btn learner-btn--secondary learner-btn--sm" id="learner-mark-all-read" type="button">
                            <?= learner_icon('check', 16); ?>
                            <span>Đánh dấu tất cả đã đọc</span>
                        </button>
                    </div>
                </div>

                <section class="learner-notification-list" id="learner-notification-list" aria-live="polite">
                    <div class="learner-notification-loading">Đang tải thông báo...</div>
                </section>
                <div class="learner-notification-pagination">
                    <button class="learner-btn learner-btn--secondary learner-btn--sm" id="learner-notification-load-more" type="button" hidden>
                        Tải thêm thông báo
                    </button>
                </div>
            </main>
        </div>
    </div>

    <!-- Notification Preferences Modal -->
    <div class="learner-notification-modal" id="learner-notification-prefs-modal" role="dialog" aria-modal="true" aria-labelledby="learner-prefs-title" aria-hidden="true">
        <div class="learner-notification-modal__content">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <h2 id="learner-prefs-title" style="margin: 0; font-size: 1.15rem;">Cài đặt nhận thông báo</h2>
                <button class="learner-icon-button" id="learner-prefs-close" type="button" aria-label="Đóng cài đặt">
                    <?= learner_icon('x', 20); ?>
                </button>
            </div>
            <p style="color: var(--text-secondary); font-size: 0.84rem; margin-bottom: 20px;">
                Tùy chỉnh thông báo trong ứng dụng và lưu lựa chọn email. Hệ thống chưa gửi email trong v1.
            </p>
            <div id="learner-prefs-list">
                <div>Đang tải cài đặt...</div>
            </div>
        </div>
    </div>

    <script id="learner-notifications-boot" type="application/json"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
