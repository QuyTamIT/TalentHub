<?php
/** TalentHub Learner - Aptitude discovery */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Khám phá năng khiếu';
$currentRoute = '/app/learner/discover.php';

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Khám phá năng khiếu và định hướng phát triển của bạn trên TalentHub.">
    <title>Khám phá năng khiếu | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-discover">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-discover-page-title',
                    'eyebrow' => 'Hiểu bản thân hơn',
                    'title' => 'Khám phá năng khiếu',
                    'description' => 'Bộ 4 bài đánh giá khoa học giúp bạn hiểu rõ điểm mạnh và định hướng phát triển toàn diện.',
                    'icon' => 'compass',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>

                <section class="learner-assessment-grid" aria-label="Các bài đánh giá năng khiếu" data-assessment-catalog data-catalog-endpoint="/app/learner/api/v1/assessments.php">
                    <div class="learner-card learner-assessment-state" data-catalog-loading>
                        <span class="learner-assessment-spinner" aria-hidden="true"></span>
                        <p>Đang tải danh mục bài đánh giá...</p>
                    </div>
                    <div class="learner-card learner-empty-catalog" data-empty-catalog hidden>
                        <p>Chưa có phiên bản được duyệt. Vui lòng quay lại sau.</p>
                    </div>
                    <div data-catalog-cards></div>
                </section>

                <div class="learner-data-note learner-discover-data-note"><?= learner_icon('info', 17); ?><p>Kết quả từ 4 bài đánh giá được sử dụng để cá nhân hóa gợi ý năng lực và trải nghiệm học tập.</p></div>

                <div class="learner-discovery-grid">
                    <section class="learner-card learner-radar-card" aria-labelledby="radar-title">
                        <div class="learner-section-heading learner-section-heading--stacked">
                            <h2 id="radar-title">Bản đồ năng khiếu</h2>
                            <p>Đa trí thông minh – Multiple Intelligence</p>
                        </div>

                        <div class="learner-discovery-talent-list" data-discovery-talents aria-label="Điểm đa trí thông minh" aria-live="polite"></div>
                    </section>

                    <section class="learner-card learner-directions" aria-labelledby="directions-title">
                        <div class="learner-section-heading learner-section-heading--stacked">
                            <p>Kết quả tổng hợp</p>
                            <h2 id="directions-title">Định hướng của bạn</h2>
                        </div>
                        <div class="learner-direction-list" data-discovery-career aria-live="polite"></div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script id="learner-session-boot" type="application/json"><?= json_encode(['csrfToken' => $GLOBALS['learner_page_context']['csrfToken'] ?? ''], JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-assessment.js"></script>
</body>
</html>
