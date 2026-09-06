<?php
/** TalentHub Learner - Badges and levels */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Huy hiệu và cấp độ';
$currentRoute = '/app/learner/badges.php';

$badgeData = null;
$badgeLoadError = false;
if ($isDatabaseMode ?? false) {
    try {
        $studentId = (string) ($student['id'] ?? learner_current_student_id());
        $badgeData = learner_repository_factory()->badgeReadService()->forStudent($studentId);
    } catch (Throwable $e) {
        $badgeData = null;
        $badgeLoadError = true;
    }
}

$level = $badgeData['level'] ?? $level ?? [
    'name' => 'Explorer',
    'number' => 1,
    'currentHours' => 0.0,
    'targetHours' => 10.0,
    'nextLevel' => 'Innovator',
    'remainingHours' => 10.0,
    'progressPercent' => 0,
];

$levelProgressPercent = (int) ($level['progressPercent'] ?? 0);
$levelCurrentHours = (float) ($level['currentHours'] ?? 0.0);
$levelTargetHours = (float) ($level['targetHours'] ?? 10.0);
$levelNext = $level['nextLevel'] ?? null;
$levelRemaining = (float) ($level['remainingHours'] ?? 0.0);

$learnerLevels = [
    ['id' => 'explorer',  'name' => 'Explorer',  'number' => 1, 'hours' => 0,   'target' => 10,  'range' => '0 - 10 giờ',    'state' => $level['name'] === 'Explorer' ? 'current' : ($levelCurrentHours >= 10 ? 'achieved' : 'next'), 'status' => $level['name'] === 'Explorer' ? 'Đang thực hiện' : ($levelCurrentHours >= 10 ? 'Đã đạt' : 'Chưa mở')],
    ['id' => 'innovator', 'name' => 'Innovator', 'number' => 2, 'hours' => 10,  'target' => 50,  'range' => '10 - 50 giờ',   'state' => $level['name'] === 'Innovator' ? 'current' : ($levelCurrentHours >= 50 ? 'achieved' : ($level['name'] === 'Explorer' ? 'next' : 'locked')), 'status' => $level['name'] === 'Innovator' ? 'Đang thực hiện' : ($levelCurrentHours >= 50 ? 'Đã đạt' : 'Chưa mở')],
    ['id' => 'expert',    'name' => 'Expert',    'number' => 3, 'hours' => 50,  'target' => 100, 'range' => '50 - 100 giờ',  'state' => $level['name'] === 'Expert' ? 'current' : ($levelCurrentHours >= 100 ? 'achieved' : ($level['name'] === 'Innovator' ? 'next' : 'locked')), 'status' => $level['name'] === 'Expert' ? 'Đang thực hiện' : ($levelCurrentHours >= 100 ? 'Đã đạt' : 'Chưa mở')],
    ['id' => 'master',    'name' => 'Master',    'number' => 4, 'hours' => 100, 'target' => 200, 'range' => '100 - 200 giờ', 'state' => $level['name'] === 'Master' ? 'current' : ($level['name'] === 'Expert' ? 'next' : 'locked'), 'status' => $level['name'] === 'Master' ? 'Đã đạt cấp tối đa' : 'Chưa mở'],
];

$learnerBadgeFilters = [
    ['id' => 'all', 'label' => 'Tất cả'],
    ['id' => 'achieved', 'label' => 'Đã đạt'],
    ['id' => 'in_progress', 'label' => 'Đang tiến hành'],
    ['id' => 'locked', 'label' => 'Chưa mở'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Theo dõi cấp độ và bộ sưu tập huy hiệu cá nhân của học sinh, sinh viên trên TalentHub.">
    <title>Huy hiệu và cấp độ | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-badges">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-badges-page-title',
                    'eyebrow' => 'Ghi nhận nỗ lực',
                    'title' => 'Huy hiệu và cấp độ',
                    'description' => 'Theo dõi các cột mốc học tập và trải nghiệm thực tế của bạn.',
                    'icon' => 'award',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>

                <?php if ($badgeLoadError): ?>
                    <section class="learner-card learner-empty-state" role="alert" data-badge-load-error>
                        <span class="learner-empty-state__icon"><?= learner_icon('award', 30); ?></span>
                        <h2>Chưa có dữ liệu huy hiệu và cấp độ</h2>
                        <p>Không thể tải dữ liệu đã xác nhận ở thời điểm này.</p>
                        <a class="learner-btn learner-btn--outline" href="badges.php">Thử tải lại</a>
                    </section>
                <?php endif; ?>

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
                        <?php if ($levelNext !== null): ?>
                            <strong class="learner-level-overview__hours"><?= learner_escape($levelCurrentHours); ?>/<?= learner_escape($levelTargetHours); ?> giờ</strong>
                            <span>Cần thêm <?= learner_escape($levelRemaining); ?> giờ để lên cấp <?= learner_escape($levelNext); ?></span>
                        <?php else: ?>
                            <strong class="learner-level-overview__hours"><?= learner_escape($levelCurrentHours); ?> giờ</strong>
                            <span>Bạn đã đạt cấp độ cao nhất!</span>
                        <?php endif; ?>
                        <div class="learner-progress" role="progressbar" aria-label="Tiến độ cấp độ <?= learner_escape($level['name']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($levelProgressPercent); ?>">
                            <span style="--learner-progress: <?= learner_escape($levelProgressPercent); ?>%;"></span>
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
                                    <span style="display: block; font-weight: 600; color: #2563EB;"><?= learner_escape($levelItem['range'] ?? ''); ?></span>
                                    <?php if ($levelItem['state'] === 'achieved'): ?>Đã hoàn thành
                                    <?php elseif ($levelItem['state'] === 'current'): ?><?= learner_escape($levelCurrentHours); ?>/<?= learner_escape($levelItem['target']); ?> giờ
                                    <?php else: ?>Yêu cầu <?= learner_escape($levelItem['hours']); ?> giờ<?php endif; ?>
                                </small>
                                <span class="learner-level-path__status"><?= learner_escape($levelItem['status']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>

                <section class="learner-card learner-school-credential-section" aria-labelledby="school-badges-title">
                    <div class="learner-school-credential-heading">
                        <div>
                            <span class="learner-school-credential-heading__eyebrow"><?= learner_icon('building', 17); ?> Bộ thành tích của <?= learner_escape($schoolCredentialData['school']['name'] ?? 'nhà trường'); ?></span>
                            <h2 id="school-badges-title">Huy hiệu chính thức của trường</h2>
                            <p>Các huy hiệu được gợi ý theo hồ sơ năng lực; huy hiệu đủ điều kiện sẽ do hệ thống hoặc nhà trường ghi nhận.</p>
                        </div>
                        <a href="profile.php">Xem chứng chỉ <?= learner_icon('arrow-right', 16); ?></a>
                    </div>
                    <?php
                    $credentialItems = $schoolCredentialData['badges'] ?? [];
                    $credentialCompact = false;
                    include __DIR__ . '/includes/school-credential-grid.php';
                    unset($credentialItems, $credentialCompact);
                    ?>
                </section>

                <section class="learner-badge-section" aria-labelledby="learner-badge-collection-title">
                    <div class="learner-badge-section__heading">
                        <div>
                            <h2 id="learner-badge-collection-title">Huy hiệu toàn hệ thống</h2>
                            <p>Các cột mốc chung của TalentHub dựa trên trải nghiệm, hoạt động và bài đánh giá.</p>
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

                    <?php
                    $displayBadges = [];
                    if ($badgeData !== null && isset($badgeData['progress'])) {
                        foreach ($badgeData['progress'] as $p) {
                            $tone = $p['status'] === 'achieved' ? 'success' : ($p['status'] === 'in_progress' ? 'primary' : 'neutral');
                            $statusLabel = $p['status'] === 'achieved' ? 'Đạt được' : ($p['status'] === 'in_progress' ? 'Đang thực hiện' : 'Chưa mở');
                            $displayBadges[] = [
                                'id' => $p['badgeId'],
                                'code' => $p['badgeCode'],
                                'name' => $p['badgeName'],
                                'category' => $p['badgeCategory'],
                                'description' => $p['badgeDescription'],
                                'status' => $p['status'],
                                'status_label' => $statusLabel,
                                'tone' => $tone,
                                'current' => $p['current'],
                                'target' => $p['target'],
                                'progress' => $p['progressPercent'],
                                'icon' => 'award',
                                'awardedAt' => $p['awardedAt'] ?? null,
                            ];
                        }
                    } elseif (!($isDatabaseMode ?? false)) {
                        $displayBadges = $learnerBadges ?? [];
                    }
                    ?>

                    <p class="learner-visually-hidden" data-badge-result-status role="status" aria-live="polite"><?= count($displayBadges); ?> huy hiệu phù hợp</p>

                    <div class="learner-badge-grid" aria-label="Danh sách huy hiệu cá nhân">
                        <?php foreach ($displayBadges as $badge): ?>
                            <?php
                            $badgeProgress = (int) ($badge['progress'] ?? 0);
                            $badgeTone = $badge['tone'] ?? ($badge['status'] === 'achieved' ? 'success' : ($badge['status'] === 'in_progress' ? 'primary' : 'neutral'));
                            $statusLabel = $badge['status_label'] ?? ($badge['status'] === 'achieved' ? 'Đạt được' : ($badge['status'] === 'in_progress' ? 'Đang thực hiện' : 'Chưa mở'));
                            ?>
                            <article class="learner-card learner-badge-card learner-badge-card--<?= learner_escape($badge['status']); ?>" data-badge-card data-badge-status="<?= learner_escape($badge['status']); ?>">
                                <span class="learner-badge-card__icon learner-badge-card__icon--<?= learner_escape($badgeTone); ?>" aria-hidden="true">
                                    <?= learner_icon($badge['icon'] ?? 'award', 38); ?>
                                </span>
                                <h3><?= learner_escape($badge['name']); ?></h3>
                                <p><?= learner_escape($badge['description']); ?></p>
                                <span class="learner-badge-card__status learner-badge-card__status--<?= learner_escape($badgeTone); ?>">
                                    <?= learner_escape($statusLabel); ?>
                                </span>
                                <div class="learner-badge-card__progress">
                                    <div class="learner-progress" role="progressbar" aria-label="Tiến độ huy hiệu <?= learner_escape($badge['name']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($badgeProgress); ?>">
                                        <span class="learner-progress--<?= learner_escape($badgeTone); ?>" style="--learner-progress: <?= learner_escape($badgeProgress); ?>%;"></span>
                                    </div>
                                    <strong><?= learner_escape($badge['current']); ?>/<?= learner_escape($badge['target']); ?></strong>
                                </div>
                                <?php if (!empty($badge['awardedAt'])): ?>
                                    <small style="display: block; margin-top: 8px; color: #64748b; font-size: 0.75rem;">
                                        Ngày nhận: <?= learner_escape(date('d/m/Y', strtotime((string)$badge['awardedAt']))); ?>
                                    </small>
                                <?php endif; ?>
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
                    <span>Huy hiệu được tự động cập nhật khi hệ thống ghi nhận bạn đạt các mốc trải nghiệm và đánh giá.</span>
                </p>
            </main>
        </div>
    </div>

    <script type="application/json" id="learner-badges-data"><?=
        json_encode(
            $badgeData ?? [],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner-badges.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
