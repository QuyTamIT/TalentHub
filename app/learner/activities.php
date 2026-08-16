<?php
/** TalentHub Learner - Activities catalog */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/activity-data.php';

$pageTitle = 'Hoạt động';
$currentRoute = '/app/learner/activities.php';
$headerSearchLabel = 'Tìm hoạt động';
$headerSearchPlaceholder = 'Tìm hoạt động...';
$activityCatalog = learner_activity_catalog();
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
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-activities-page-title',
                    'eyebrow' => 'Trải nghiệm để trưởng thành',
                    'title' => 'Khám phá hoạt động',
                    'description' => 'Tìm cơ hội phù hợp để học hỏi, trải nghiệm và kết nối.',
                    'icon' => 'calendar',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>
                <div class="learner-activity-toolbar learner-activity-toolbar--actions">
                    <div class="learner-filter-list" aria-label="Lọc hoạt động theo lĩnh vực">
                        <a class="learner-btn learner-btn--outline" href="my-activities.php">Hoạt động của tôi</a>
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
                                <span><?= learner_icon('clock', 18); ?> <?= learner_escape((new DateTimeImmutable($activity['start_at']))->format('d/m/Y · H:i')); ?></span>
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
                            <a class="learner-btn learner-btn--primary learner-btn--block" href="activity-detail.php?id=<?= learner_escape($activity['id']); ?>">Xem chi tiết</a>
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

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
