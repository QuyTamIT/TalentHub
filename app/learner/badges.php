<?php
/** TalentHub Learner - Badges and levels */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Huy hiệu và cấp độ';
$currentRoute = '/app/learner/badges.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Theo dõi cấp độ và bộ sưu tập huy hiệu cá nhân của học sinh, sinh viên trên TalentHub.">
    <title>Huy hiệu và cấp độ | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-badges">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <div class="learner-page-heading">
                    <h1>Huy hiệu và cấp độ</h1>
                    <p>Ghi nhận hành trình học tập và trải nghiệm của bạn.</p>
                </div>

                <section class="learner-card learner-level-overview" aria-labelledby="learner-current-level-title">
                    <div class="learner-level-overview__current">
                        <p>Cấp độ hiện tại</p>
                        <div class="learner-level-overview__identity">
                            <span class="learner-level-emblem" aria-hidden="true"><?= learner_icon('award', 34); ?></span>
                            <div>
                                <strong id="learner-current-level-title"><?= learner_escape($level['name']); ?></strong>
                                <span>Cấp <?= learner_escape($level['number']); ?></span>
                            </div>
                        </div>
                        <strong class="learner-level-overview__hours"><?= learner_escape($level['progress']); ?>/<?= learner_escape($level['target']); ?> giờ</strong>
                        <span>đến cấp <?= learner_escape($level['next_level']); ?></span>
                        <div class="learner-progress" role="progressbar" aria-label="Tiến độ đến cấp <?= learner_escape($level['next_level']); ?>" aria-valuemin="0" aria-valuemax="<?= learner_escape($level['target']); ?>" aria-valuenow="<?= learner_escape($level['progress']); ?>">
                            <span style="--learner-progress: <?= learner_escape($level['progress']); ?>%;"></span>
                        </div>
                    </div>

                    <ol class="learner-level-path" aria-label="Lộ trình cấp độ">
                        <?php foreach ($learnerLevels as $levelItem): ?>
                            <li class="learner-level-path__item learner-level-path__item--<?= learner_escape($levelItem['state']); ?>" data-level-item>
                                <span class="learner-level-path__medal" aria-hidden="true">
                                    <?= learner_icon($levelItem['id'] === 'master' ? 'trophy' : 'award', 30); ?>
                                    <?php if ($levelItem['number'] > 0): ?><b><?= learner_escape($levelItem['number']); ?></b><?php endif; ?>
                                </span>
                                <strong><?= learner_escape($levelItem['name']); ?></strong>
                                <small>
                                    <?php if ($levelItem['state'] === 'achieved'): ?>Hoàn thành <?= learner_escape($levelItem['hours']); ?> giờ
                                    <?php elseif ($levelItem['state'] === 'current'): ?>Hoàn thành <?= learner_escape($levelItem['hours']); ?>/<?= learner_escape($levelItem['target']); ?> giờ
                                    <?php elseif ($levelItem['state'] === 'next'): ?>Cần thêm <?= learner_escape($levelItem['hours']); ?> giờ
                                    <?php else: ?>Cần <?= learner_escape($levelItem['hours']); ?> giờ<?php endif; ?>
                                </small>
                                <span class="learner-level-path__status"><?= learner_escape($levelItem['status']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>

                <section class="learner-badge-section" aria-labelledby="learner-badge-collection-title">
                    <div class="learner-badge-section__heading">
                        <div>
                            <h2 id="learner-badge-collection-title">Bộ sưu tập huy hiệu</h2>
                            <p>Tiếp tục trải nghiệm để mở khóa thêm các dấu mốc mới.</p>
                        </div>
                        <div class="learner-filter-list" aria-label="Lọc huy hiệu theo trạng thái">
                            <?php foreach ($learnerBadgeFilters as $index => $filter): ?>
                                <button
                                    class="learner-filter-button"
                                    type="button"
                                    data-badge-filter="<?= learner_escape($filter['id']); ?>"
                                    aria-pressed="<?= $index === 0 ? 'true' : 'false'; ?>"
                                >
                                    <?= learner_escape($filter['label']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <p class="learner-visually-hidden" data-badge-result-status role="status" aria-live="polite">6 huy hiệu phù hợp</p>

                    <div class="learner-badge-grid" aria-label="Danh sách huy hiệu cá nhân">
                        <?php foreach ($learnerBadges as $badge): ?>
                            <?php
                            $badgeProgress = $badge['target'] > 0
                                ? min(100, (int) round($badge['current'] / $badge['target'] * 100))
                                : 0;
                            $badgeTone = $badge['status'] === 'achieved'
                                ? 'success'
                                : ($badge['status'] === 'in_progress' ? 'primary' : 'neutral');
                            ?>
                            <article class="learner-card learner-badge-card learner-badge-card--<?= learner_escape($badge['status']); ?>" data-badge-card data-badge-status="<?= learner_escape($badge['status']); ?>">
                                <span class="learner-badge-card__icon learner-badge-card__icon--<?= learner_escape($badgeTone); ?>" aria-hidden="true">
                                    <?= learner_icon($badge['icon'], 38); ?>
                                </span>
                                <h3><?= learner_escape($badge['name']); ?></h3>
                                <p><?= learner_escape($badge['description']); ?></p>
                                <span class="learner-badge-card__status learner-badge-card__status--<?= learner_escape($badgeTone); ?>">
                                    <?= learner_escape($badge['status_label']); ?>
                                </span>
                                <div class="learner-badge-card__progress">
                                    <div class="learner-progress" role="progressbar" aria-label="Tiến độ huy hiệu <?= learner_escape($badge['name']); ?>" aria-valuemin="0" aria-valuemax="<?= learner_escape($badge['target']); ?>" aria-valuenow="<?= learner_escape($badge['current']); ?>">
                                        <span class="learner-progress--<?= learner_escape($badgeTone); ?>" style="--learner-progress: <?= learner_escape($badgeProgress); ?>%;"></span>
                                    </div>
                                    <strong><?= learner_escape($badge['current']); ?>/<?= learner_escape($badge['target']); ?></strong>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <section class="learner-card learner-empty-state" data-badge-empty role="status" aria-live="polite" hidden>
                        <span class="learner-empty-state__icon"><?= learner_icon('award', 30); ?></span>
                        <h2>Chưa có huy hiệu ở trạng thái này</h2>
                        <p>Hãy chọn một bộ lọc khác để xem bộ sưu tập của bạn.</p>
                    </section>
                </section>

                <p class="learner-badge-note">
                    <?= learner_icon('activity', 18); ?>
                    <span>Huy hiệu được cập nhật khi hệ thống ghi nhận hoàn thành hoạt động hoặc khóa học.</span>
                </p>
            </main>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
