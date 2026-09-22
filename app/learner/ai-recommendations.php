<?php
/** TalentHub Learner - AI Roadmap-first */
$learnerDeferTalentPassport = true;
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'AI gợi ý';
$currentRoute = '/app/learner/ai-recommendations.php';
$assetVersion = static function (string $relativePath): string {
    $absolutePath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
    return is_file($absolutePath) ? (string) filemtime($absolutePath) : '0';
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="description" content="Lộ trình phát triển 90 ngày do AI FTalentHub đề xuất từ dữ liệu bạn đã cho phép.">
    <title>AI gợi ý | FTalentHub</title>
    <link rel="stylesheet" href="<?= app_href('/assets/css/home.css'); ?>?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/home.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/global.css'); ?>?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/global.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/brand-component.css'); ?>?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/brand-component.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/polish.css'); ?>?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/polish.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/learner.css'); ?>?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/learner.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/learner-ai-recommendations.css'); ?>?v=<?= $assetVersion('assets/css/learner-ai-recommendations.css'); ?>">
</head>
<body class="learner-app learner-page-ai">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>
            <main class="learner-content learner-roadmap" id="main-content" data-ai-roadmap-page>
                <div class="learner-ai-shell">
                    <header class="learner-ai-bar">
                        <div class="learner-ai-bar__copy">
                            <p class="learner-ai-bar__eyebrow"><?= learner_icon('sparkles', 14); ?> AI gợi ý</p>
                            <h1>Lộ trình 90 ngày</h1>
                        </div>
                        <div class="learner-ai-bar__tools learner-roadmap__header-actions">
                            <label class="learner-roadmap-version learner-ai-bar__version">
                                <span>Phiên bản</span>
                                <select data-roadmap-version-select aria-label="Chọn phiên bản lộ trình"><option>Chưa có</option></select>
                            </label>
                            <span class="learner-roadmap__freshness learner-ai-bar__freshness" data-roadmap-freshness><?= learner_icon('check', 16); ?> Chưa có phân tích</span>
                            <button class="learner-btn learner-btn--primary" type="button" data-roadmap-generate="refresh"><?= learner_icon('activity', 17); ?> Tạo bản mới</button>
                        </div>
                    </header>

                    <p class="learner-visually-hidden" data-roadmap-status role="status" aria-live="polite" aria-atomic="true">Đang tải lộ trình.</p>

                    <section class="learner-card learner-roadmap-processing" data-roadmap-processing role="status" aria-live="polite" aria-atomic="false" hidden>
                        <div class="learner-roadmap-processing__heading">
                            <span class="learner-roadmap-processing__icon" aria-hidden="true"><?= learner_icon('sparkles', 24); ?></span>
                            <div>
                                <span class="learner-roadmap__eyebrow" data-roadmap-processing-label>AI ĐANG XỬ LÝ</span>
                                <h2 data-roadmap-processing-title>Đang chuẩn bị lộ trình của bạn</h2>
                                <p data-roadmap-processing-copy>FTalentHub đang tổng hợp dữ liệu đã được bạn cho phép.</p>
                            </div>
                            <div class="learner-roadmap-processing__meta">
                                <strong data-roadmap-processing-percent>8%</strong>
                            </div>
                        </div>
                        <div class="learner-roadmap-processing__bar" aria-hidden="true"><span data-roadmap-processing-bar></span></div>
                        <ol class="learner-roadmap-processing__steps" data-roadmap-processing-steps>
                            <li data-processing-step="0"><span>1</span><strong>Chuẩn bị dữ liệu năng lực</strong></li>
                            <li data-processing-step="1"><span>2</span><strong>AI đang phân tích</strong></li>
                            <li data-processing-step="2"><span>3</span><strong>Xây dựng lộ trình 90 ngày</strong></li>
                            <li data-processing-step="3"><span>4</span><strong>Kiểm tra và hoàn thiện</strong></li>
                        </ol>
                        <div class="learner-roadmap-processing__footer">
                            <p data-roadmap-processing-note>Bạn có thể tiếp tục xem lộ trình hiện tại trong lúc chờ.</p>
                            <button class="learner-btn learner-btn--outline" type="button" data-roadmap-processing-retry data-roadmap-retry hidden>Thử cập nhật lại</button>
                        </div>
                    </section>

                    <section class="learner-card learner-roadmap-state" data-roadmap-loading aria-label="AI đang tải lộ trình"><span class="learner-ai-loading__spinner" aria-hidden="true"></span><div><h2>Đang tải lộ trình của bạn...</h2><p>FTalentHub đang kiểm tra bản phân tích mới nhất.</p></div></section>
                    <section class="learner-card learner-roadmap-state" data-roadmap-not-generated hidden><span class="learner-roadmap-state__icon"><?= learner_icon('sparkles', 30); ?></span><div><h2>Sẵn sàng tạo lộ trình 90 ngày</h2><p>AI sẽ tổng hợp bốn kết quả đánh giá đã hoàn thành để đề xuất các bước phát triển có thể thực hiện.</p></div><button class="learner-btn learner-btn--primary" type="button" data-roadmap-generate="generate">Phân tích và tạo lộ trình</button></section>
                    <section class="learner-card learner-roadmap-state" data-roadmap-consent hidden><span class="learner-roadmap-state__icon"><?= learner_icon('info', 30); ?></span><div><h2>Cần quyền sử dụng kết quả đánh giá</h2><p>Chỉ dữ liệu bạn cho phép mới được gửi tới dịch vụ AI.</p></div><a class="learner-btn learner-btn--primary" href="profile.php">Quản lý quyền dữ liệu</a></section>
                    <div class="learner-roadmap-insufficient" data-roadmap-insufficient hidden>
                        <section class="learner-card learner-roadmap-state"><span class="learner-roadmap-state__icon"><?= learner_icon('clipboard', 30); ?></span><div><h2>Chưa đủ dữ liệu để tạo lộ trình</h2><p>Hãy hoàn thành đủ bộ đánh giá bắt buộc rồi quay lại đây.</p></div><a class="learner-btn learner-btn--primary" href="discover.php">Tiếp tục bài đánh giá</a></section>
                        <section class="learner-card learner-roadmap-radar-card learner-roadmap-radar-card--zero" aria-labelledby="roadmap-zero-talent-title"><h2 id="roadmap-zero-talent-title">Bản đồ năng khiếu</h2><div data-roadmap-zero-talent-map></div></section>
                    </div>
                    <section class="learner-card learner-roadmap-state" data-roadmap-pending hidden><span class="learner-ai-loading__spinner" aria-hidden="true"></span><div><h2>AI đang xây dựng lộ trình...</h2><p>Bạn có thể để trang mở hoặc quay lại sau.</p></div></section>
                    <section class="learner-card learner-roadmap-state learner-roadmap-state--error" data-roadmap-error hidden><span class="learner-roadmap-state__icon"><?= learner_icon('x', 30); ?></span><div><h2>Chưa thể tải lộ trình</h2><p>Dữ liệu đã lưu không bị ảnh hưởng. Vui lòng thử lại.</p></div><button class="learner-btn learner-btn--primary" type="button" data-roadmap-retry>Thử lại</button></section>

                    <div class="learner-roadmap__ready learner-ai-workspace" data-roadmap-ready hidden>
                        <p class="learner-roadmap-version__changes" data-roadmap-version-changes></p>

                        <aside class="learner-ai-workspace__brief" data-roadmap-summary aria-labelledby="roadmap-summary-title">
                            <div class="learner-ai-workspace__brief-main">
                                <span class="learner-ai-workspace__brief-kicker" data-roadmap-summary-label>Định hướng AI</span>
                                <h2 id="roadmap-summary-title" class="learner-visually-hidden">Định hướng của bạn</h2>
                                <p class="learner-ai-workspace__brief-text" data-roadmap-summary-text></p>
                                <div class="learner-ai-workspace__brief-meta">
                                    <button type="button" class="learner-ai-workspace__evidence-trigger" data-roadmap-evidence-total data-roadmap-evidence-open aria-haspopup="dialog" aria-controls="roadmap-evidence-modal">0 nguồn dữ liệu đã cho phép</button>
                                    <span class="learner-ai-workspace__confidence" data-roadmap-confidence></span>
                                </div>
                            </div>
                            <div class="learner-ai-workspace__brief-side" data-roadmap-direction aria-labelledby="roadmap-direction-title">
                                <strong id="roadmap-direction-title" data-roadmap-direction-label></strong>
                                <p data-roadmap-direction-rationale></p>
                                <div data-roadmap-direction-alternatives></div>
                            </div>
                        </aside>

                        <section class="learner-ai-workspace__desk learner-roadmap-plan" aria-labelledby="roadmap-plan-title">
                            <div class="learner-ai-workspace__desk-head">
                                <div>
                                    <h2 id="roadmap-plan-title">Đang làm gì tiếp theo</h2>
                                    <p>Chọn chặng bên trái — checklist nhiệm vụ hiện ở khu vực chính.</p>
                                </div>
                                <div class="learner-roadmap-plan__actions">
                                    <div class="learner-ai-workspace__progress" data-roadmap-overall-progress></div>
                                    <div class="learner-roadmap-plan__controls" aria-label="Điều khiển lộ trình">
                                        <button class="learner-btn learner-btn--outline learner-roadmap-edit" type="button" data-roadmap-edit hidden><?= learner_icon('edit', 17); ?> Chỉnh sửa</button>
                                    </div>
                                </div>
                            </div>
                            <div class="learner-ai-workspace__progress-track" aria-hidden="true">
                                <div class="learner-roadmap-progress"><span data-roadmap-progress-bar></span></div>
                            </div>
                            <section class="learner-roadmap-celebration" data-roadmap-celebration hidden aria-labelledby="roadmap-celebration-title"></section>
                            <div class="learner-roadmap-phases learner-ai-workspace__phases" data-roadmap-phases></div>
                        </section>

                        <details class="learner-ai-workspace__more" data-skill-gap>
                            <summary>
                                <span><?= learner_icon('compass', 18); ?></span>
                                <strong>Xem phân tích năng lực & hoạt động gợi ý</strong>
                                <em>Mở khi cần đối chiếu bản đồ kỹ năng / skill gap</em>
                            </summary>
                            <div class="learner-ai-workspace__more-body">
                                <section class="learner-roadmap-analysis" aria-labelledby="roadmap-analysis-title">
                                    <article class="learner-card learner-roadmap-radar-card" aria-labelledby="roadmap-analysis-title">
                                        <div class="learner-roadmap-card-heading">
                                            <span class="learner-roadmap-card-heading__icon"><?= learner_icon('compass', 20); ?></span>
                                            <div>
                                                <span class="learner-roadmap__eyebrow">Năng lực</span>
                                                <h2 id="roadmap-analysis-title">Bản đồ năng khiếu</h2>
                                            </div>
                                        </div>
                                        <div class="learner-roadmap-radar-body">
                                            <div class="learner-roadmap-radar-wrapper" data-roadmap-talent-map></div>
                                            <div class="learner-roadmap-radar-insights">
                                                <div class="learner-roadmap-radar-insight-group">
                                                    <h3 class="learner-roadmap-insight-subheading">
                                                        <span class="learner-radar-badge learner-radar-badge--strength"><?= learner_icon('check', 14); ?> Điểm mạnh</span>
                                                    </h3>
                                                    <div class="learner-roadmap-capability-list" data-roadmap-strengths></div>
                                                </div>
                                                <div class="learner-roadmap-radar-insight-group">
                                                    <h3 class="learner-roadmap-insight-subheading">
                                                        <span class="learner-radar-badge learner-radar-badge--potential"><?= learner_icon('sparkles', 14); ?> Tiềm năng</span>
                                                    </h3>
                                                    <div class="learner-roadmap-capability-list" data-roadmap-potential-paths></div>
                                                </div>
                                            </div>
                                        </div>
                                    </article>
                                    <article class="learner-card learner-skill-gap" aria-labelledby="skill-gap-title">
                                        <div class="learner-skill-gap__heading">
                                            <div>
                                                <span class="learner-roadmap__eyebrow">Skill gap</span>
                                                <h2 id="skill-gap-title">Khoảng cách kỹ năng</h2>
                                                <p>Đối chiếu với benchmark vị trí phù hợp nhất.</p>
                                            </div>
                                            <a class="learner-btn learner-btn--text" href="ecosystem.php?tab=enterprises">Cơ hội phù hợp</a>
                                        </div>
                                        <p class="learner-skill-gap__status" data-skill-gap-status role="status" aria-live="polite">Đang tải kết quả Job Matching gần nhất...</p>
                                        <div class="learner-skill-gap__content" data-skill-gap-content hidden>
                                            <div class="learner-skill-gap__target">
                                                <div class="learner-skill-gap__target-label">
                                                    <span class="learner-skill-gap__target-tag"><?= learner_icon('briefcase', 14); ?> Vị trí mục tiêu</span>
                                                </div>
                                                <strong data-skill-gap-target></strong>
                                            </div>
                                            <div class="learner-skill-gap__scores" data-skill-gap-scores></div>
                                            <div class="learner-skill-gap__columns">
                                                <section aria-labelledby="skill-gap-met-title">
                                                    <div class="learner-skill-gap__section-heading">
                                                        <h3 id="skill-gap-met-title"><span class="learner-indicator-dot learner-indicator-dot--success"></span> Đã đạt</h3>
                                                    </div>
                                                    <div class="learner-skill-gap__skills" data-skill-gap-met></div>
                                                </section>
                                                <section aria-labelledby="skill-gap-missing-title">
                                                    <div class="learner-skill-gap__section-heading">
                                                        <h3 id="skill-gap-missing-title"><span class="learner-indicator-dot learner-indicator-dot--warning"></span> Cần bù</h3>
                                                    </div>
                                                    <div class="learner-skill-gap__skills" data-skill-gap-missing></div>
                                                </section>
                                            </div>
                                        </div>
                                        <div class="learner-visually-hidden" aria-hidden="true">
                                            <div data-roadmap-improvements></div><div data-roadmap-trends></div>
                                            <div data-roadmap-growth-hypotheses></div>
                                            <button type="button" data-roadmap-analysis-toggle aria-expanded="false" tabindex="-1"></button><div data-roadmap-analysis-details hidden></div>
                                        </div>
                                    </article>
                                </section>
                                <section class="learner-card learner-roadmap-activities learner-skill-gap__activities-section" data-skill-gap-activities-section aria-labelledby="skill-gap-activities-title">
                                    <div class="learner-roadmap-activities__heading">
                                        <div class="learner-roadmap-card-heading">
                                            <span class="learner-roadmap-card-heading__icon"><?= learner_icon('sparkles', 20); ?></span>
                                            <div>
                                                <span class="learner-roadmap__eyebrow">Gợi ý</span>
                                                <h2 id="skill-gap-activities-title">Hoạt động đề xuất</h2>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="learner-skill-gap__activities" data-skill-gap-activities></div>
                                </section>
                            </div>
                        </details>

                        <div class="learner-visually-hidden" data-roadmap-insights hidden aria-hidden="true"></div>
                        <div class="learner-visually-hidden" aria-hidden="true">
                            <button type="button" data-roadmap-collapse-all></button>
                            <button type="button" data-roadmap-expand-all></button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <div class="learner-roadmap-editor" data-roadmap-editor role="dialog" aria-modal="true" aria-labelledby="roadmap-editor-title" aria-describedby="roadmap-editor-help" hidden>
        <div class="learner-roadmap-editor__backdrop" data-editor-close></div>
        <section class="learner-roadmap-editor__dialog">
            <header class="learner-roadmap-editor__header">
                <div>
                    <span class="learner-roadmap__eyebrow">LỘ TRÌNH CỦA BẠN</span>
                    <h2 id="roadmap-editor-title" data-roadmap-editor-title>Chỉnh sửa lộ trình 90 ngày</h2>
                    <p id="roadmap-editor-help">Chỉnh trực tiếp nội dung. AI sẽ giúp sửa chính tả, trình bày và làm rõ ý.</p>
                </div>
                <button class="learner-roadmap-editor__close" type="button" data-editor-close aria-label="Đóng trình chỉnh sửa"><?= learner_icon('x', 22); ?></button>
            </header>
            <nav class="learner-roadmap-editor__tabs" aria-label="Ba chặng của lộ trình" data-editor-step="edit">
                <button type="button" role="tab" aria-selected="true" data-editor-phase="1">Tháng 1 · Ngày 1–30</button>
                <button type="button" role="tab" aria-selected="false" data-editor-phase="2">Tháng 2 · Ngày 31–60</button>
                <button type="button" role="tab" aria-selected="false" data-editor-phase="3">Tháng 3 · Ngày 61–90</button>
            </nav>
            <div class="learner-roadmap-editor__preview-tabs" data-editor-step="preview" hidden>
                <button type="button" data-preview-source="ai_refined" class="is-active">Bản AI đã làm rõ</button>
                <button type="button" data-preview-source="learner_draft">Nội dung của tôi</button>
            </div>
            <main class="learner-roadmap-editor__body" data-roadmap-editor-body></main>
            <footer class="learner-roadmap-editor__footer">
                <p data-roadmap-editor-status role="status" aria-live="polite"></p>
                <div data-editor-step="edit">
                    <button class="learner-btn learner-btn--text" type="button" data-editor-close>Hủy</button>
                    <button class="learner-btn learner-btn--outline" type="button" data-editor-save>Lưu nội dung của tôi</button>
                    <button class="learner-btn learner-btn--primary" type="button" data-editor-refine><?= learner_icon('sparkles', 17); ?> AI tinh chỉnh & xem trước</button>
                </div>
                <div data-editor-step="preview" hidden>
                    <button class="learner-btn learner-btn--text" type="button" data-editor-back>Quay lại chỉnh sửa</button>
                    <button class="learner-btn learner-btn--primary" type="button" data-editor-apply>Áp dụng lộ trình</button>
                </div>
            </footer>
        </section>
    </div>
    <div class="learner-roadmap-complete-modal" data-roadmap-complete-modal role="dialog" aria-modal="true" aria-labelledby="roadmap-complete-modal-title" aria-describedby="roadmap-complete-modal-desc" hidden>
        <div class="learner-roadmap-complete-modal__backdrop" data-roadmap-complete-cancel></div>
        <div class="learner-roadmap-complete-modal__dialog">
            <button class="learner-roadmap-complete-modal__close" type="button" data-roadmap-complete-cancel aria-label="Đóng"><?= learner_icon('x', 20); ?></button>
            <div class="learner-roadmap-complete-modal__icon-wrap">
                <span class="learner-roadmap-complete-modal__icon"><?= learner_icon('trophy', 36); ?></span>
            </div>
            <div class="learner-roadmap-complete-modal__content">
                <h3 id="roadmap-complete-modal-title">Xác nhận hoàn tất lộ trình AI 90 ngày</h3>
                <p id="roadmap-complete-modal-desc">Các nhiệm vụ đã được đánh dấu hoàn thành. Bạn muốn mở bảng tổng kết lộ trình? Xác nhận này được lưu trên trình duyệt và không thay thế đánh giá hoặc chứng nhận của giảng viên.</p>
            </div>
            <div class="learner-roadmap-complete-modal__actions">
                <button class="learner-btn learner-btn--outline" type="button" data-roadmap-complete-cancel>Hủy bỏ</button>
                <button class="learner-btn learner-btn--primary" type="button" data-roadmap-complete-confirm><?= learner_icon('check', 16); ?> Xác nhận hoàn tất</button>
            </div>
        </div>
    </div>
    <div class="learner-modal learner-roadmap-evidence-modal" id="roadmap-evidence-modal" data-roadmap-evidence-modal role="dialog" aria-modal="true" aria-labelledby="roadmap-evidence-modal-title" hidden>
        <div class="learner-modal__backdrop" data-roadmap-evidence-close></div>
        <div class="learner-modal__dialog learner-modal__dialog--compact learner-roadmap-evidence-modal__dialog">
            <header class="learner-modal__header">
                <div>
                    <span class="learner-modal__eyebrow">Quyền dữ liệu AI</span>
                    <h2 id="roadmap-evidence-modal-title">Nguồn dữ liệu đã cho phép</h2>
                    <p data-roadmap-evidence-modal-summary>Các nguồn được dùng để tạo lộ trình hiện tại.</p>
                </div>
                <button class="learner-roadmap-editor__close" type="button" data-roadmap-evidence-close aria-label="Đóng"><?= learner_icon('x', 22); ?></button>
            </header>
            <div class="learner-roadmap-evidence-modal__body" data-roadmap-evidence-modal-body></div>
            <div class="learner-modal__actions">
                <a class="learner-btn learner-btn--outline" href="profile.php">Quản lý quyền dữ liệu</a>
                <button class="learner-btn learner-btn--primary" type="button" data-roadmap-evidence-close>Đã hiểu</button>
            </div>
        </div>
    </div>
    <script src="<?= app_href('/assets/js/learner-api.js'); ?>?v=<?= $assetVersion('assets/js/learner-api.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/learner.js'); ?>?v=<?= $assetVersion('assets/js/learner.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/learner-ai-roadmap-editor.js'); ?>?v=<?= $assetVersion('assets/js/learner-ai-roadmap-editor.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/learner-ai-roadmap.js'); ?>?v=<?= $assetVersion('assets/js/learner-ai-roadmap.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/learner-skill-gap.js'); ?>?v=<?= $assetVersion('assets/js/learner-skill-gap.js'); ?>"></script>
</body>
</html>
