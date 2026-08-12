<?php
/** TalentHub Learner - Activities catalog */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Hoạt động';
$currentRoute = '/app/learner/activities.php';
$headerSearchLabel = 'Tìm hoạt động';
$headerSearchPlaceholder = 'Tìm hoạt động...';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Khám phá và đăng ký hoạt động trải nghiệm dành cho học sinh, sinh viên trên TalentHub.">
    <title>Khám phá hoạt động | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-activities">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <div class="learner-activity-toolbar">
                    <div class="learner-page-heading">
                        <h1>Khám phá hoạt động</h1>
                        <p>Tìm cơ hội phù hợp để học hỏi, trải nghiệm và kết nối.</p>
                    </div>

                    <div class="learner-filter-list" aria-label="Lọc hoạt động theo lĩnh vực">
                        <?php foreach ($activityCategories as $index => $category): ?>
                            <button
                                class="learner-filter-button"
                                type="button"
                                data-activity-filter="<?= learner_escape($category); ?>"
                                aria-pressed="<?= $index === 0 ? 'true' : 'false'; ?>"
                            >
                                <?= learner_escape($category); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <p class="learner-visually-hidden" data-activity-result-status role="status" aria-live="polite">6 hoạt động phù hợp</p>

                <section class="learner-activity-catalog" aria-label="Danh sách hoạt động">
                    <?php foreach ($activityCatalog as $activity): ?>
                        <?php
                        $remaining = max(0, $activity['capacity'] - $activity['participants']);
                        $occupancy = min(100, (int) round($activity['participants'] / $activity['capacity'] * 100));
                        ?>
                        <article
                            class="learner-card learner-catalog-card"
                            data-activity-card
                            data-title="<?= learner_escape($activity['title']); ?>"
                            data-category="<?= learner_escape($activity['category']); ?>"
                            data-filter-category="<?= learner_escape($activity['filter_category']); ?>"
                            data-location="<?= learner_escape($activity['location']); ?>"
                        >
                            <span class="learner-badge learner-badge--<?= learner_escape($activity['tone']); ?>">
                                <?= learner_escape($activity['category']); ?>
                            </span>
                            <h2><?= learner_escape($activity['title']); ?></h2>
                            <div class="learner-catalog-card__meta">
                                <span><?= learner_icon('clock', 18); ?> <?= learner_escape($activity['time']); ?></span>
                                <span><?= learner_icon('map-pin', 18); ?> <?= learner_escape($activity['location']); ?></span>
                            </div>
                            <div class="learner-catalog-card__capacity">
                                <span><?= learner_icon('users', 18); ?> <?= learner_escape($activity['participants']); ?>/<?= learner_escape($activity['capacity']); ?></span>
                                <strong>Còn <?= learner_escape($remaining); ?> chỗ</strong>
                            </div>
                            <div
                                class="learner-progress"
                                role="progressbar"
                                aria-label="Sức chứa <?= learner_escape($activity['title']); ?>"
                                aria-valuemin="0"
                                aria-valuemax="<?= learner_escape($activity['capacity']); ?>"
                                aria-valuenow="<?= learner_escape($activity['participants']); ?>"
                            >
                                <span class="learner-progress--<?= learner_escape($activity['tone']); ?>" style="--learner-progress: <?= learner_escape($occupancy); ?>%;"></span>
                            </div>
                            <button
                                class="learner-btn learner-btn--primary learner-btn--block"
                                type="button"
                                data-activity-register
                                data-activity-name="<?= learner_escape($activity['title']); ?>"
                            >
                                Đăng ký ngay
                            </button>
                        </article>
                    <?php endforeach; ?>
                </section>

                <section class="learner-card learner-empty-state" data-activity-empty role="status" aria-live="polite" hidden>
                    <span class="learner-empty-state__icon"><?= learner_icon('search', 28); ?></span>
                    <h2>Không tìm thấy hoạt động phù hợp</h2>
                    <p>Hãy thử từ khóa khác hoặc chọn lại lĩnh vực “Tất cả”.</p>
                </section>
            </main>
        </div>
    </div>

    <div class="learner-modal" id="learner-registration-modal" role="dialog" aria-modal="true" aria-labelledby="learner-registration-title" hidden>
        <div class="learner-modal__backdrop" data-close-modal></div>
        <div class="learner-modal__dialog learner-modal__dialog--compact" tabindex="-1">
            <div class="learner-modal__header">
                <div>
                    <span class="learner-modal__eyebrow">Xác nhận đăng ký</span>
                    <h2 id="learner-registration-title">Đăng ký hoạt động</h2>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng xác nhận đăng ký"><?= learner_icon('x', 22); ?></button>
            </div>
            <p class="learner-modal__copy">Bạn muốn đăng ký <strong data-registration-name>hoạt động này</strong>? Trạng thái chỉ được lưu trong phiên giao diện demo.</p>
            <div class="learner-modal__actions">
                <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Hủy</button>
                <button class="learner-btn learner-btn--primary" type="button" data-confirm-registration>Xác nhận đăng ký</button>
            </div>
        </div>
    </div>

    <script src="../../assets/js/learner.js"></script>
</body>
</html>
