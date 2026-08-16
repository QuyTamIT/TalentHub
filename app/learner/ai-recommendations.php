<?php
/** TalentHub Learner - AI recommendations */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'AI phân tích năng lực';
$currentRoute = '/app/learner/ai-recommendations.php';
$aiState = $aiRecommendation['sufficient'] ? 'ready' : 'insufficient';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gợi ý lộ trình phát triển năng lực cá nhân cho học sinh, sinh viên trên TalentHub.">
    <title>AI phân tích năng lực | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-ai">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content" data-ai-page data-ai-state="<?= learner_escape($aiState); ?>">
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-ai-page-title',
                    'eyebrow' => 'Gợi ý cá nhân hóa',
                    'title' => 'AI phân tích năng lực',
                    'description' => 'Hiểu rõ điểm mạnh, điểm cần cải thiện và lộ trình phát triển của bạn.',
                    'icon' => 'sparkles',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>

                <p class="learner-visually-hidden" data-ai-state-status role="status" aria-live="polite" aria-atomic="true">
                    <?= $aiState === 'ready' ? 'Phân tích năng lực đã sẵn sàng.' : 'Chưa đủ dữ liệu để phân tích năng lực.'; ?>
                </p>

                <section class="learner-card learner-ai-loading" data-ai-loading aria-label="AI đang phân tích dữ liệu" hidden>
                    <span class="learner-ai-loading__spinner" aria-hidden="true"></span>
                    <div>
                        <h2>AI đang tổng hợp hồ sơ năng lực...</h2>
                        <p>Đang đối chiếu kỹ năng, hoạt động và kết quả đánh giá của bạn.</p>
                    </div>
                </section>

                <section class="learner-card learner-empty-state learner-ai-insufficient" data-ai-insufficient role="status" <?= $aiState === 'ready' ? 'hidden' : ''; ?>>
                    <span class="learner-empty-state__icon"><?= learner_icon('sparkles', 30); ?></span>
                    <h2><?= learner_escape($aiRecommendation['insufficient_title']); ?></h2>
                    <p><?= learner_escape($aiRecommendation['insufficient_copy']); ?></p>
                    <a class="learner-btn learner-btn--primary" href="profile.php">Hoàn thiện hồ sơ năng lực</a>
                </section>

                <div class="learner-ai-content" data-ai-ready <?= $aiState === 'insufficient' ? 'hidden' : ''; ?>>
                    <section class="learner-ai-summary" aria-label="Tóm tắt phân tích AI">
                        <span class="learner-ai-summary__icon" aria-hidden="true">i</span>
                        <p><?= learner_escape($aiRecommendation['summary']); ?></p>
                    </section>

                    <section class="learner-ai-analysis-grid" aria-label="Phân tích năng lực cá nhân">
                        <?php foreach ($aiRecommendation['groups'] as $group): ?>
                            <article class="learner-card learner-ai-analysis-card learner-ai-analysis-card--<?= learner_escape($group['tone']); ?>" data-ai-analysis-card>
                                <div class="learner-ai-analysis-card__heading">
                                    <span aria-hidden="true"><?= learner_icon($group['icon'], 23); ?></span>
                                    <h2><?= learner_escape($group['title']); ?></h2>
                                </div>
                                <ul>
                                    <?php foreach ($group['items'] as $item): ?>
                                        <li><?= learner_escape($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <section class="learner-card learner-ai-roadmap" aria-labelledby="learner-ai-roadmap-title">
                        <h2 id="learner-ai-roadmap-title">Lộ trình gợi ý 3 tháng tới</h2>
                        <div class="learner-ai-roadmap__steps">
                            <?php foreach ($aiRecommendation['roadmap'] as $index => $step): ?>
                                <article class="learner-ai-roadmap__step" data-ai-roadmap-step>
                                    <span class="learner-ai-roadmap__number" aria-hidden="true"><?= learner_escape($index + 1); ?></span>
                                    <div>
                                        <h3><?= learner_escape($step['month']); ?></h3>
                                        <p><?= learner_escape($step['action']); ?></p>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <a class="learner-btn learner-btn--primary learner-ai-roadmap__cta" href="activities.php">
                            Khám phá hoạt động phù hợp <?= learner_icon('arrow-right', 18); ?>
                        </a>
                    </section>
                </div>

                <p class="learner-ai-disclaimer">
                    <?= learner_icon('activity', 19); ?>
                    <span><?= learner_escape($aiRecommendation['disclaimer']); ?></span>
                </p>
            </main>
        </div>
    </div>

    <script type="application/json" id="learner-ai-data"><?=
        json_encode(
            ['sufficient' => $aiRecommendation['sufficient'], 'updated_at' => $aiRecommendation['updated_at']],
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
