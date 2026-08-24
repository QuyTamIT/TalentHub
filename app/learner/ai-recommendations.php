<?php
/** TalentHub Learner - AI Roadmap-first */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'AI gợi ý';
$currentRoute = '/app/learner/ai-recommendations.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Lộ trình phát triển 90 ngày do AI TalentHub đề xuất từ dữ liệu bạn đã cho phép.">
    <title>AI gợi ý | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-ai">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content learner-roadmap" id="main-content" data-ai-roadmap-page>
                <header class="learner-roadmap__header">
                    <div><span class="learner-roadmap__eyebrow">AI GỢI Ý</span><h1>Lộ trình phát triển cá nhân dành riêng cho bạn</h1></div>
                    <div class="learner-roadmap__header-actions">
                        <label class="learner-roadmap-version"><span>Phiên bản</span><select data-roadmap-version-select aria-label="Chọn phiên bản lộ trình"><option>Chưa có</option></select></label>
                        <span class="learner-roadmap__freshness" data-roadmap-freshness><?= learner_icon('check', 16); ?> Chưa có phân tích</span>
                        <button class="learner-btn learner-btn--outline" type="button" data-roadmap-generate="refresh"><?= learner_icon('activity', 17); ?> Cập nhật phân tích</button>
                    </div>
                </header>

                <p class="learner-visually-hidden" data-roadmap-status role="status" aria-live="polite" aria-atomic="true">Đang tải lộ trình.</p>
                <section class="learner-card learner-roadmap-state" data-roadmap-loading aria-label="AI đang tải lộ trình"><span class="learner-ai-loading__spinner" aria-hidden="true"></span><div><h2>Đang tải lộ trình của bạn...</h2><p>TalentHub đang kiểm tra bản phân tích mới nhất.</p></div></section>
                <section class="learner-card learner-roadmap-state" data-roadmap-not-generated hidden><span class="learner-roadmap-state__icon"><?= learner_icon('sparkles', 30); ?></span><div><h2>Sẵn sàng tạo lộ trình 90 ngày</h2><p>AI sẽ tổng hợp bốn kết quả đánh giá đã hoàn thành để đề xuất các bước phát triển có thể thực hiện.</p></div><button class="learner-btn learner-btn--primary" type="button" data-roadmap-generate="generate">Phân tích và tạo lộ trình</button></section>
                <section class="learner-card learner-roadmap-state" data-roadmap-consent hidden><span class="learner-roadmap-state__icon"><?= learner_icon('info', 30); ?></span><div><h2>Cần quyền sử dụng kết quả đánh giá</h2><p>Chỉ dữ liệu bạn cho phép mới được gửi tới dịch vụ AI.</p></div><a class="learner-btn learner-btn--primary" href="profile.php">Quản lý quyền dữ liệu</a></section>
                <section class="learner-card learner-roadmap-state" data-roadmap-insufficient hidden><span class="learner-roadmap-state__icon"><?= learner_icon('clipboard', 30); ?></span><div><h2>Chưa đủ dữ liệu để tạo lộ trình</h2><p>Hãy hoàn thành đủ bộ đánh giá bắt buộc rồi quay lại đây.</p></div><a class="learner-btn learner-btn--primary" href="discover.php">Tiếp tục bài đánh giá</a></section>
                <section class="learner-card learner-roadmap-state" data-roadmap-pending hidden><span class="learner-ai-loading__spinner" aria-hidden="true"></span><div><h2>AI đang xây dựng lộ trình...</h2><p>Bạn có thể để trang mở hoặc quay lại sau.</p></div></section>
                <section class="learner-card learner-roadmap-state learner-roadmap-state--error" data-roadmap-error hidden><span class="learner-roadmap-state__icon"><?= learner_icon('x', 30); ?></span><div><h2>Chưa thể tải lộ trình</h2><p>Dữ liệu đã lưu không bị ảnh hưởng. Vui lòng thử lại.</p></div><button class="learner-btn learner-btn--primary" type="button" data-roadmap-retry>Thử lại</button></section>

                <div class="learner-roadmap__ready" data-roadmap-ready hidden>
                    <p class="learner-roadmap-version__changes" data-roadmap-version-changes></p>
                    <div class="learner-roadmap__fallback" data-roadmap-fallback role="status" hidden><?= learner_icon('info', 18); ?><span><strong>Gợi ý dự phòng theo quy tắc.</strong> AI tạm thời chưa phản hồi; nội dung này không được gắn nhãn là kết quả từ mô hình.</span></div>
                    <div class="learner-roadmap__overview">
                        <section class="learner-card learner-roadmap-summary" data-roadmap-summary aria-labelledby="roadmap-summary-title">
                            <div class="learner-roadmap-card-heading"><span class="learner-roadmap-card-heading__icon"><?= learner_icon('sparkles', 20); ?></span><div><span data-roadmap-summary-label>Tóm tắt từ AI</span><h2 id="roadmap-summary-title">Định hướng phát triển</h2></div></div>
                            <p class="learner-roadmap-summary__text" data-roadmap-summary-text></p>
                            <div class="learner-roadmap-summary__meta"><span data-roadmap-evidence-total></span><span data-roadmap-confidence></span></div>
                        </section>
                        <section class="learner-card learner-roadmap-direction" data-roadmap-direction aria-labelledby="roadmap-direction-title">
                            <h2 id="roadmap-direction-title">Hướng phát triển ưu tiên</h2>
                            <div class="learner-roadmap-direction__primary"><span class="learner-roadmap-direction__icon"><?= learner_icon('bot', 24); ?></span><div><strong data-roadmap-direction-label></strong><p data-roadmap-direction-rationale></p></div></div>
                            <span class="learner-roadmap-direction__subheading">Hướng khác phù hợp</span><div class="learner-roadmap-direction__alternatives" data-roadmap-direction-alternatives></div>
                        </section>
                    </div>
                    <section class="learner-roadmap-insights" data-roadmap-insights aria-label="Nhận định phát triển"></section>
                    <div class="learner-roadmap__workspace">
                        <section class="learner-card learner-roadmap-plan" aria-labelledby="roadmap-plan-title">
                            <div class="learner-roadmap-section-heading"><div><span>Kế hoạch hành động</span><h2 id="roadmap-plan-title">LỘ TRÌNH PHÁT TRIỂN 90 NGÀY</h2></div><span data-roadmap-overall-progress></span></div>
                            <div class="learner-roadmap-phases" data-roadmap-phases></div>
                        </section>
                        <aside class="learner-card learner-roadmap-next" aria-labelledby="roadmap-next-title"><h2 id="roadmap-next-title">Việc nên làm tiếp theo</h2><div class="learner-roadmap-next__list" data-roadmap-next-actions></div><button class="learner-btn learner-btn--primary" type="button" data-roadmap-continue>Tiếp tục lộ trình</button></aside>
                    </div>
                    <section class="learner-card learner-roadmap-activities" aria-labelledby="roadmap-activities-title"><div class="learner-roadmap-section-heading"><div><span>Gợi ý theo lộ trình</span><h2 id="roadmap-activities-title">Hoạt động phù hợp với bạn</h2></div></div><div class="learner-roadmap-activities__list" data-roadmap-activities></div></section>
                    <div class="learner-roadmap__details">
                        <details class="learner-card learner-roadmap-disclosure" data-roadmap-evidence><summary>Dữ liệu AI đã sử dụng</summary><div data-roadmap-evidence-content></div></details>
                        <details class="learner-card learner-roadmap-disclosure" data-roadmap-engine><summary>Thông tin kỹ thuật</summary><dl data-roadmap-engine-content></dl></details>
                    </div>
                    <section class="learner-roadmap-feedback" data-roadmap-feedback aria-label="Phản hồi về lộ trình"><span>Gợi ý này hữu ích với bạn chứ?</span><button class="learner-btn learner-btn--outline" type="button" data-roadmap-feedback-value="helpful"><?= learner_icon('check', 16); ?> Hữu ích</button><button class="learner-btn learner-btn--outline" type="button" data-roadmap-feedback-value="not_helpful"><?= learner_icon('x', 16); ?> Chưa phù hợp</button><small data-roadmap-feedback-status role="status" aria-live="polite"></small></section>
                </div>
            </main>
        </div>
    </div>
    <script id="learner-session-boot" type="application/json"><?= json_encode(['csrfToken' => $GLOBALS['learner_page_context']['csrfToken'] ?? ''], JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-ai-roadmap.js"></script>
</body>
</html>
