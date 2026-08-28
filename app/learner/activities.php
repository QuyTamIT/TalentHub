<?php

declare(strict_types=1);

/** TalentHub Learner - school-scoped activity discovery. */
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

$pageTitle = 'Hoạt động';
$currentRoute = '/app/learner/activities.php';
$headerSearchLabel = 'Tìm hoạt động';
$headerSearchPlaceholder = 'Tìm hoạt động, kỹ năng...';
$activityNavigationActive = 'discover';
$activityCatalog = learner_activity_catalog();
$participantCount = array_sum(array_map(
    static fn (array $activity): int => (int) ($activity['participants'] ?? 0),
    $activityCatalog
));
$newActivityThreshold = new DateTimeImmutable('-30 days', new DateTimeZone('UTC'));
$newActivityCount = count(array_filter(
    $activityCatalog,
    static function (array $activity) use ($newActivityThreshold): bool {
        $openedAt = trim((string) ($activity['registration_opens_at'] ?? ''));
        if ($openedAt === '') {
            return false;
        }
        try {
            return new DateTimeImmutable($openedAt, new DateTimeZone('UTC')) >= $newActivityThreshold;
        } catch (Throwable) {
            return false;
        }
    }
));
$discoveryCategories = ['Tất cả', 'Kỹ thuật', 'Kinh doanh', 'Sáng tạo', 'Cộng đồng'];
$activityDisplayTimezone = new DateTimeZone('Asia/Ho_Chi_Minh');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Khám phá hoạt động trải nghiệm đang mở dành riêng cho trường của bạn trên TalentHub.">
    <title>Khám phá hoạt động | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
    <link rel="stylesheet" href="assets/activities/activities.css">
</head>
<body class="learner-app learner-page-activities">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content" data-activity-discovery-page>
                <div class="learner-activities-shell">
                    <?php include __DIR__ . '/includes/activity-navigation.php'; ?>

                    <section class="learner-activity-discovery-hero" aria-labelledby="learner-activity-discovery-title">
                        <div class="learner-activity-discovery-hero__content">
                            <p class="learner-activity-discovery-hero__eyebrow">TRẢI NGHIỆM ĐỂ TRƯỞNG THÀNH</p>
                            <h1 id="learner-activity-discovery-title">Khám phá hoạt động</h1>
                            <p class="learner-activity-discovery-hero__description">Tìm cơ hội phù hợp để học hỏi, trải nghiệm và kết nối ngay trong cộng đồng trường bạn.</p>

                            <dl class="learner-activity-discovery-kpis" aria-label="Tổng quan hoạt động đang mở">
                                <div>
                                    <span class="learner-activity-discovery-kpis__icon is-orange"><?= learner_icon('calendar', 22); ?></span>
                                    <dt><strong><?= learner_escape(count($activityCatalog)); ?></strong> hoạt động</dt>
                                    <dd>đang mở</dd>
                                </div>
                                <div>
                                    <span class="learner-activity-discovery-kpis__icon is-blue"><?= learner_icon('users', 22); ?></span>
                                    <dt><strong><?= learner_escape($participantCount); ?></strong> lượt tham gia</dt>
                                    <dd>hiện nay</dd>
                                </div>
                                <div>
                                    <span class="learner-activity-discovery-kpis__icon is-green"><?= learner_icon('sparkles', 22); ?></span>
                                    <dt><strong><?= learner_escape($newActivityCount); ?></strong> hoạt động mới</dt>
                                    <dd>phù hợp</dd>
                                </div>
                            </dl>
                        </div>
                        <img
                            class="learner-activity-discovery-hero__illustration"
                            src="assets/activities/illustrations/hero-discover.svg"
                            alt="Minh họa lịch khám phá hoạt động trải nghiệm"
                            width="400"
                            height="250"
                        >
                    </section>

                    <section class="learner-activity-discovery-toolbar" aria-label="Tìm kiếm và lọc hoạt động">
                        <div class="learner-activity-discovery-toolbar__primary">
                            <label class="learner-activity-discovery-search">
                                <span class="learner-visually-hidden">Tìm hoạt động</span>
                                <?= learner_icon('search', 19); ?>
                                <input type="search" placeholder="Tìm hoạt động, kỹ năng, trường..." autocomplete="off" data-activity-search-input>
                            </label>

                            <label class="learner-activity-discovery-time">
                                <?= learner_icon('calendar', 19); ?>
                                <span class="learner-visually-hidden">Thời gian</span>
                                <select data-activity-time-filter aria-label="Thời gian">
                                    <option value="all">Thời gian</option>
                                    <option value="7d">7 ngày tới</option>
                                    <option value="30d">30 ngày tới</option>
                                </select>
                            </label>

                            <div class="learner-activity-discovery-categories" aria-label="Lọc theo lĩnh vực">
                                <?php foreach ($discoveryCategories as $categoryIndex => $category): ?>
                                    <button
                                        type="button"
                                        data-activity-filter="<?= learner_escape($category); ?>"
                                        aria-pressed="<?= $categoryIndex === 0 ? 'true' : 'false'; ?>"
                                    >
                                        <?= learner_escape($category); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <label class="learner-activity-discovery-availability">
                            <input type="checkbox" checked data-activity-availability-filter>
                            <span aria-hidden="true"></span>
                            <strong>Chỉ hiển thị hoạt động còn hạn và còn chỗ</strong>
                            <?= learner_icon('info', 16); ?>
                        </label>
                    </section>

                    <p class="learner-visually-hidden" data-activity-result-status role="status" aria-live="polite">
                        <?= learner_escape(count($activityCatalog)); ?> hoạt động phù hợp
                    </p>

                    <?php if ($activityCatalog === []): ?>
                        <section class="learner-activity-discovery-empty" data-activity-server-empty role="status">
                            <span><?= learner_icon('calendar', 30); ?></span>
                            <h2>Không có hoạt động đang mở</h2>
                            <p>Hiện chưa có hoạt động còn hạn và còn chỗ dành cho trường của bạn.</p>
                        </section>
                    <?php else: ?>
                        <section class="learner-activity-discovery-grid" aria-label="Danh sách hoạt động đang mở">
                            <?php foreach ($activityCatalog as $activity): ?>
                                <?php
                                $participants = max(0, (int) ($activity['participants'] ?? 0));
                                $capacity = max(1, (int) ($activity['capacity'] ?? 1));
                                $remaining = max(0, $capacity - $participants);
                                $filterCategory = (string) ($activity['filter_category'] ?? '');
                                $displayCategory = (string) ($activity['display_category'] ?? $filterCategory);
                                $locationName = trim((string) ($activity['location_name'] ?? ''));
                                $locationLabel = $locationName !== '' ? $locationName : 'Liên hệ đơn vị tổ chức';
                                $coverImage = learner_activity_cover_or_fallback(
                                    $activity['cover_image_url'] ?? '',
                                    'assets/activities/illustrations/hero-discover.svg',
                                );
                                $searchText = implode(' ', [
                                    (string) ($activity['title'] ?? ''),
                                    $displayCategory,
                                    $filterCategory,
                                    (string) ($activity['school_name'] ?? ''),
                                    $locationLabel,
                                    implode(' ', array_map('strval', $activity['skills'] ?? [])),
                                ]);
                                $startAt = new DateTimeImmutable((string) $activity['start_at'], $activityDisplayTimezone);
                                $endAt = !empty($activity['end_at'])
                                    ? new DateTimeImmutable((string) $activity['end_at'], $activityDisplayTimezone)
                                    : null;
                                $registrationClosesAt = new DateTimeImmutable((string) $activity['registration_closes_at'], $activityDisplayTimezone);

                                if ($endAt && $endAt->format('d/m/Y') !== $startAt->format('d/m/Y')) {
                                    $timeRangeFormatted = $startAt->format('d/m/Y H:i') . ' – ' . $endAt->format('d/m/Y H:i');
                                } elseif ($endAt) {
                                    $timeRangeFormatted = $startAt->format('d/m/Y · H:i') . ' – ' . $endAt->format('H:i');
                                } else {
                                    $timeRangeFormatted = $startAt->format('d/m/Y · H:i');
                                }
                                ?>
                                <article
                                    class="learner-activity-discovery-card"
                                    data-activity-card
                                    data-activity-search="<?= learner_escape($searchText); ?>"
                                    data-filter-category="<?= learner_escape($filterCategory); ?>"
                                    data-start-at="<?= learner_escape($startAt->format(DateTimeInterface::ATOM)); ?>"
                                    data-available="true"
                                >
                                    <div class="learner-activity-discovery-card__cover">
                                        <img
                                            src="<?= learner_escape($coverImage); ?>"
                                            alt="<?= learner_escape($activity['cover_image_alt']); ?>"
                                            loading="lazy"
                                            width="480"
                                            height="220"
                                        >
                                        <span class="learner-activity-discovery-card__open"><?= learner_icon('check', 14); ?> Đang mở</span>
                                        <span class="learner-activity-discovery-card__category"><?= learner_escape($displayCategory); ?></span>
                                    </div>

                                    <div class="learner-activity-discovery-card__body">
                                        <h2><?= learner_escape($activity['title']); ?></h2>
                                        <p class="learner-activity-discovery-card__school"><?= learner_icon('building', 17); ?> <?= learner_escape($activity['school_name']); ?></p>
                                        <dl class="learner-activity-discovery-card__meta">
                                            <div><dt><?= learner_icon('calendar', 17); ?><span class="learner-visually-hidden">Thời gian</span></dt><dd><?= learner_escape($timeRangeFormatted); ?></dd></div>
                                            <div><dt><?= learner_icon('map-pin', 17); ?><span class="learner-visually-hidden">Địa điểm</span></dt><dd><?= learner_escape($locationLabel); ?></dd></div>
                                            <div><dt><?= learner_icon('clock', 17); ?><span class="learner-visually-hidden">Hạn đăng ký</span></dt><dd>Hạn đăng ký: <?= learner_escape($registrationClosesAt->format('d/m/Y · H:i')); ?></dd></div>
                                        </dl>

                                        <div class="learner-activity-discovery-card__capacity">
                                            <span><?= learner_icon('users', 17); ?> <?= learner_escape($participants); ?>/<?= learner_escape($capacity); ?></span>
                                            <strong>Còn <?= learner_escape($remaining); ?> chỗ</strong>
                                        </div>
                                        <progress value="<?= learner_escape($participants); ?>" max="<?= learner_escape($capacity); ?>" aria-label="Mức đăng ký của <?= learner_escape($activity['title']); ?>"></progress>
                                        <a href="activity-detail.php?id=<?= learner_escape(rawurlencode((string) $activity['id'])); ?>">
                                            Xem chi tiết <?= learner_icon('arrow-right', 18); ?>
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </section>

                        <section class="learner-activity-discovery-empty" data-activity-filter-empty role="status" hidden>
                            <span><?= learner_icon('search', 30); ?></span>
                            <h2>Không tìm thấy kết quả</h2>
                            <p>Hãy thử từ khóa, thời gian hoặc lĩnh vực khác.</p>
                        </section>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-activities.js"></script>
</body>
</html>
