<?php
/** TalentHub Learner - AI recommendations */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'AI phân tích năng lực';
$currentRoute = '/app/learner/ai-recommendations.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gợi ý phát triển năng lực cá nhân cho học sinh, sinh viên trên TalentHub.">
    <title>AI phân tích năng lực | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-ai">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content" data-ai-page>
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-ai-page-title',
                    'eyebrow' => 'Gợi ý cá nhân hóa',
                    'title' => 'AI phân tích năng lực',
                    'description' => 'Hiểu rõ điểm mạnh và những hoạt động phù hợp với dữ liệu của bạn.',
                    'icon' => 'sparkles',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>

                <p class="learner-visually-hidden" data-ai-state-status role="status" aria-live="polite" aria-atomic="true">
                    Đang tải gợi ý năng lực.
                </p>

                <section class="learner-card learner-ai-loading" data-ai-loading aria-label="AI đang phân tích dữ liệu" hidden>
                    <span class="learner-ai-loading__spinner" aria-hidden="true"></span>
                    <div>
                        <h2>AI đang tổng hợp hồ sơ năng lực...</h2>
                        <p>Đang đối chiếu kỹ năng, hoạt động và kết quả đánh giá của bạn.</p>
                    </div>
                </section>

                <section class="learner-card learner-empty-state learner-ai-consent" data-ai-consent hidden>
                    <span class="learner-empty-state__icon"><?= learner_icon('info', 30); ?></span>
                    <h2>Cần sự đồng ý để tạo gợi ý</h2>
                    <p data-ai-consent-copy>Chọn những nhóm dữ liệu bạn muốn dùng để cá nhân hóa gợi ý.</p>
                    <div class="learner-ai-consent__actions" data-ai-consent-actions></div>
                </section>

                <section class="learner-card learner-empty-state learner-ai-insufficient" data-ai-insufficient hidden>
                    <span class="learner-empty-state__icon"><?= learner_icon('sparkles', 30); ?></span>
                    <h2>Chưa đủ dữ liệu để tạo gợi ý</h2>
                    <p data-ai-insufficient-copy>Hãy hoàn thiện hồ sơ kỹ năng, bài đánh giá và hoạt động trải nghiệm.</p>
                    <div class="learner-ai-state-actions">
                        <a class="learner-btn learner-btn--secondary" href="profile.php">Hoàn thiện hồ sơ năng lực</a>
                        <button class="learner-btn learner-btn--primary" type="button" data-ai-generate>Tạo gợi ý</button>
                    </div>
                </section>

                <section class="learner-card learner-empty-state learner-ai-source-error" data-ai-source-error hidden>
                    <span class="learner-empty-state__icon"><?= learner_icon('x', 30); ?></span>
                    <h2>Chưa thể lấy dữ liệu gợi ý</h2>
                    <p>Hệ thống chưa thể xác minh dữ liệu nguồn. Bạn có thể thử lại sau.</p>
                    <button class="learner-btn learner-btn--primary" type="button" data-ai-retry>Thử lại</button>
                </section>

                <section class="learner-ai-results" data-ai-results hidden aria-label="Gợi ý năng lực cá nhân">
                    <div class="learner-ai-results__heading">
                        <div>
                            <p class="learner-ai-results__eyebrow" data-ai-engine-label>Gợi ý theo quy tắc</p>
                            <h2>Gợi ý dành cho bạn</h2>
                            <p class="learner-ai-results__generated" data-ai-generated-at></p>
                            <div data-ai-engine-details></div>
                        </div>
                        <button class="learner-btn learner-btn--secondary" type="button" data-ai-generate>Tạo lại</button>
                    </div>
                    <div class="learner-ai-result-list" data-ai-result-list></div>
                </section>

                <p class="learner-ai-feedback-status" data-ai-feedback-status role="status" aria-live="polite" aria-atomic="true" tabindex="-1"></p>

                <p class="learner-ai-disclaimer">
                    <?= learner_icon('activity', 19); ?>
                    <span>Gợi ý chỉ mang tính định hướng, không thay thế đánh giá chuyên môn từ giáo viên và cố vấn.</span>
                </p>
            </main>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-recommendations.js"></script>
</body>
</html>
