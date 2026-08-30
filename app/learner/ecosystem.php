<?php
/** TalentHub Learner - Enterprise and school project hub */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/ecosystem-data.php';
require_once __DIR__ . '/includes/project-data.php';

$pageTitle = 'Hệ sinh thái & Dự án';
$currentRoute = '/app/learner/ecosystem.php';
$headerSearchLabel = 'Tìm doanh nghiệp hoặc dự án';
$headerSearchPlaceholder = 'Tìm doanh nghiệp, lĩnh vực, dự án...';
$initialTab = $_GET['tab'] ?? 'enterprises';
$allowedTabs = ['enterprises', 'opportunities'];
$initialTab = in_array($initialTab, $allowedTabs, true) ? $initialTab : 'enterprises';
$enterprises = learner_ecosystem_enterprises();
$projectLoadFailed = false;
try {
    $projects = learner_projects();
} catch (Throwable) {
    $projects = [];
    $projectLoadFailed = true;
}
$ecosystemFields = [];
foreach ($enterprises as $enterprise) {
    $industry = trim((string) ($enterprise['industry'] ?? ''));
    if ($industry !== '') {
        $ecosystemFields[$industry] = $industry;
    }
}
foreach ($projects as $project) {
    $category = trim((string) ($project['category_label'] ?? ''));
    if ($category !== '') {
        $ecosystemFields[$category] = $category;
    }
}
ksort($ecosystemFields, SORT_NATURAL | SORT_FLAG_CASE);
$ecosystemSource = learner_repository_factory()->source();
$isDatabaseSource = $ecosystemSource === 'database';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Khám phá doanh nghiệp và các dự án đang triển khai tại trường trên TalentHub.">
    <title>Hệ sinh thái &amp; Dự án | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/learner.css'); ?>">
</head>
<body class="learner-app learner-page-ecosystem" data-ecosystem-page data-initial-tab="<?= learner_escape($initialTab); ?>">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <section class="learner-ecosystem-hero" aria-labelledby="ecosystem-title">
                    <div>
                        <span class="learner-eyebrow">Kết nối tương lai</span>
                        <h1 id="ecosystem-title">Hệ sinh thái &amp; Dự án</h1>
                        <p>Khám phá doanh nghiệp và những dự án đang được triển khai tại trường của bạn.</p>
                    </div>
                </section>

                <nav class="learner-ecosystem-tabs" aria-label="Nội dung hệ sinh thái" role="tablist">
                    <button class="learner-ecosystem-tab" id="tab-enterprises" type="button" role="tab" aria-controls="panel-enterprises" aria-selected="<?= $initialTab === 'enterprises' ? 'true' : 'false'; ?>" data-ecosystem-tab="enterprises">
                        <?= learner_icon('briefcase', 19); ?> Doanh nghiệp
                    </button>
                    <button class="learner-ecosystem-tab" id="tab-opportunities" type="button" role="tab" aria-controls="panel-opportunities" aria-selected="<?= $initialTab === 'opportunities' ? 'true' : 'false'; ?>" data-ecosystem-tab="opportunities">
                        <?= learner_icon('sparkles', 19); ?> Dự án
                        <span class="learner-count-badge"><?= count($projects); ?></span>
                    </button>
                </nav>

                <section class="learner-ecosystem-toolbar learner-card" aria-label="Bộ lọc tìm kiếm">
                    <div class="learner-ecosystem-search">
                        <?= learner_icon('search', 19); ?>
                        <label class="learner-visually-hidden" for="ecosystem-local-search">Tìm trong hệ sinh thái</label>
                        <input id="ecosystem-local-search" type="search" placeholder="Nhập tên dự án, trường hoặc lĩnh vực..." data-ecosystem-search>
                    </div>
                    <label class="learner-select-control">
                        <span class="learner-visually-hidden">Lọc theo lĩnh vực</span>
                        <?= learner_icon('filter', 18); ?>
                        <select data-ecosystem-filter="field">
                            <option value="all">Tất cả lĩnh vực</option>
                            <?php foreach ($ecosystemFields as $field): ?>
                                <option value="<?= learner_escape($field); ?>"><?= learner_escape($field); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="learner-btn learner-btn--primary learner-opportunity-ai-trigger" type="button" data-opportunity-ai-trigger <?= $initialTab !== 'opportunities' ? 'hidden' : ''; ?>>
                        <?= learner_icon('sparkles', 18); ?> AI gợi ý dự án phù hợp
                    </button>
                </section>

                <section id="panel-enterprises" class="learner-ecosystem-panel" role="tabpanel" aria-labelledby="tab-enterprises" <?= $initialTab !== 'enterprises' ? 'hidden' : ''; ?> data-ecosystem-panel="enterprises">
                    <div class="learner-section-heading learner-ecosystem-panel__heading">
                        <div>
                            <h2>Doanh nghiệp nổi bật</h2>
                            <p>Tìm hiểu các doanh nghiệp đang đồng hành cùng hệ sinh thái giáo dục.</p>
                        </div>
                        <span><?= count($enterprises); ?> doanh nghiệp</span>
                    </div>
                    <div class="learner-partner-grid" data-ecosystem-results>
                        <?php foreach ($enterprises as $enterprise): ?>
                            <?php $enterpriseVerificationStatus = (string) ($enterprise['verification_status'] ?? ''); ?>
                            <?php $enterpriseIndustry = learner_ecosystem_partner_has_value($enterprise, 'industry') ? trim((string) $enterprise['industry']) : ''; ?>
                            <?php $enterpriseLocation = learner_ecosystem_partner_has_value($enterprise, 'location') ? trim((string) $enterprise['location']) : ''; ?>
                            <?php $enterpriseHasDescription = learner_ecosystem_partner_has_value($enterprise, 'description'); ?>
                            <?php $enterpriseSearch = implode(' ', array_filter([(string) $enterprise['name'], $enterpriseIndustry, $enterpriseLocation])); ?>
                            <article class="learner-partner-card learner-card" data-ecosystem-item data-search="<?= learner_escape($enterpriseSearch); ?>" data-field="<?= learner_escape($enterpriseIndustry); ?>" data-location="<?= learner_escape($enterpriseLocation); ?>">
                                <div class="learner-partner-card__header">
                                    <span class="learner-partner-logo learner-partner-logo--enterprise"><?= learner_escape($enterprise['logo_text']); ?></span>
                                    <?php if ($enterprise['verified'] || in_array($enterpriseVerificationStatus, ['verified', 'approved'], true)): ?>
                                        <span class="learner-verified-pill"><?= learner_icon('check', 14); ?> <?= $enterpriseVerificationStatus === 'approved' ? 'Đã phê duyệt' : 'Đã xác minh'; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="learner-partner-card__body">
                                    <?php if ($enterpriseIndustry !== ''): ?><p class="learner-card-kicker"><?= learner_escape($enterpriseIndustry); ?></p><?php endif; ?>
                                    <h3><?= learner_escape($enterprise['name']); ?></h3>
                                    <?php if ($enterpriseHasDescription): ?><p><?= learner_escape(trim((string) $enterprise['description'])); ?></p><?php endif; ?>
                                </div>
                                <?php if ($enterpriseIndustry !== '' || $enterpriseLocation !== ''): ?>
                                    <div class="learner-meta-list">
                                        <?php if ($enterpriseIndustry !== ''): ?><span><?= learner_icon('briefcase', 16); ?> <?= learner_escape($enterpriseIndustry); ?></span><?php endif; ?>
                                        <?php if ($enterpriseLocation !== ''): ?><span><?= learner_icon('map-pin', 16); ?> <?= learner_escape($enterpriseLocation); ?></span><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="learner-partner-card__footer">
                                    <a class="learner-btn learner-btn--outline" href="partner.php?type=enterprise&amp;id=<?= learner_escape($enterprise['id']); ?>">Xem doanh nghiệp <?= learner_icon('arrow-right', 16); ?></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="learner-empty-state learner-card" <?= $enterprises !== [] ? 'hidden' : ''; ?> data-ecosystem-empty>
                        <span class="learner-empty-state__icon"><?= learner_icon('search', 24); ?></span>
                        <?php if ($isDatabaseSource && $enterprises === []): ?>
                            <h2>Chưa có doanh nghiệp đã xác minh</h2>
                            <p>Danh sách chỉ hiển thị doanh nghiệp đang hoạt động đã được xác minh hoặc phê duyệt.</p>
                        <?php else: ?>
                            <h2>Chưa tìm thấy doanh nghiệp phù hợp</h2>
                            <p>Thử thay đổi từ khóa hoặc bộ lọc để xem thêm kết quả.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section id="panel-opportunities" class="learner-ecosystem-panel" role="tabpanel" aria-labelledby="tab-opportunities" <?= $initialTab !== 'opportunities' ? 'hidden' : ''; ?> data-ecosystem-panel="opportunities">
                    <section class="learner-opportunity-ai learner-card" data-opportunity-matches aria-labelledby="opportunity-ai-title">
                        <header class="learner-opportunity-ai__header">
                            <span class="learner-opportunity-ai__icon" aria-hidden="true"><?= learner_icon('sparkles', 22); ?></span>
                            <div>
                                <h2 id="opportunity-ai-title">Top 3 dự án AI đề xuất cho bạn</h2>
                                <p>Gemini đối chiếu hồ sơ năng lực và điểm đánh giá của bạn với các dự án thật trên TalentHub.</p>
                            </div>
                            <p class="learner-opportunity-ai__status" data-opportunity-ai-status role="status" aria-live="polite">Sẵn sàng phân tích</p>
                        </header>

                        <div class="learner-opportunity-ai__message" data-opportunity-ai-not-generated>
                            <?= learner_icon('sparkles', 20); ?>
                            <div><strong>Khám phá dự án phù hợp với năng lực hiện tại</strong><span>AI chỉ sử dụng dữ liệu bạn đã cho phép và giải thích riêng cho từng dự án.</span></div>
                        </div>
                        <div class="learner-opportunity-ai__loading" data-opportunity-ai-loading hidden aria-hidden="true">
                            <div class="learner-opportunity-ai__progress-box" data-opportunity-ai-progress-box>
                                <div class="learner-opportunity-ai__progress-header">
                                    <div class="learner-opportunity-ai__progress-info">
                                        <span class="learner-opportunity-ai__progress-spinner" aria-hidden="true"></span>
                                        <span class="learner-opportunity-ai__progress-text" data-opportunity-ai-progress-text>Đang chuẩn bị dữ liệu hồ sơ và dự án...</span>
                                    </div>
                                    <span class="learner-opportunity-ai__progress-pct" data-opportunity-ai-progress-pct>15%</span>
                                </div>
                                <div class="learner-opportunity-ai__progress-track" role="progressbar" aria-valuenow="15" aria-valuemin="0" aria-valuemax="100">
                                    <div class="learner-opportunity-ai__progress-fill" data-opportunity-ai-progress-bar style="width: 15%;"></div>
                                </div>
                                <div class="learner-opportunity-ai__progress-stages" data-opportunity-ai-progress-stages>
                                    <span class="is-active" data-stage="1">1. Quét hồ sơ</span>
                                    <span data-stage="2">2. Lọc dự án</span>
                                    <span data-stage="3">3. Gemini đối chiếu</span>
                                    <span data-stage="4">4. Xếp hạng Top 3</span>
                                </div>
                            </div>
                            <?php for ($skeletonIndex = 0; $skeletonIndex < 3; $skeletonIndex++): ?>
                                <span></span>
                            <?php endfor; ?>
                        </div>
                        <div class="learner-opportunity-ai__message learner-opportunity-ai__message--warning" data-opportunity-ai-consent hidden>
                            <?= learner_icon('shield-check', 20); ?>
                            <div><strong>Cần sự đồng ý của bạn</strong><span>Cập nhật quyền sử dụng dữ liệu AI trong hồ sơ năng lực để tiếp tục.</span></div>
                            <a class="learner-btn learner-btn--outline" href="profile.php">Mở hồ sơ năng lực</a>
                        </div>
                        <div class="learner-opportunity-ai__message learner-opportunity-ai__message--warning" data-opportunity-ai-insufficient hidden>
                            <?= learner_icon('info', 20); ?>
                            <div><strong>Chưa đủ dữ liệu để phân tích</strong><span>Bổ sung kỹ năng hoặc hoàn thành đánh giá năng lực để nhận kết quả chính xác hơn.</span></div>
                            <a class="learner-btn learner-btn--outline" href="profile.php">Bổ sung hồ sơ</a>
                        </div>
                        <div class="learner-opportunity-ai__message learner-opportunity-ai__message--warning" data-opportunity-ai-catalog-insufficient hidden>
                            <?= learner_icon('info', 20); ?>
                            <div><strong>Chưa đủ dự án đang mở</strong><span>Gemini sẽ phân tích ngay cả khi chỉ có một hoặc hai dự án để bạn biết mức độ phù hợp.</span></div>
                        </div>
                        <div class="learner-opportunity-ai__message learner-opportunity-ai__message--warning" data-opportunity-ai-low-fit hidden>
                            <?= learner_icon('info', 20); ?>
                            <div><strong>Dự án gần phù hợp</strong><span>Danh sách dưới đây có điểm 40–59. Gemini nêu rõ kỹ năng, điều kiện còn thiếu và bước cải thiện cho từng dự án.</span></div>
                        </div>
                        <div class="learner-opportunity-ai__message learner-opportunity-ai__message--warning learner-opportunity-ai__analysis-panel" data-opportunity-ai-no-fit hidden>
                            <?= learner_icon('info', 20); ?>
                            <div class="learner-opportunity-ai__analysis-body">
                                <div class="learner-opportunity-ai__analysis-heading">
                                    <span class="learner-opportunity-ai__gemini-badge"><?= learner_icon('sparkles', 14); ?> Gemini phân tích hồ sơ của bạn</span>
                                    <strong data-opportunity-ai-analysis-headline>Chưa có dự án đủ phù hợp</strong>
                                    <p data-opportunity-ai-analysis-explanation>Các dự án hiện tại chưa phù hợp với hồ sơ của bạn.</p>
                                </div>
                            </div>
                        </div>
                        <div class="learner-opportunity-ai__message learner-opportunity-ai__message--error" data-opportunity-ai-error hidden>
                            <?= learner_icon('info', 20); ?>
                            <div><strong>Phân tích tạm thời chưa khả dụng</strong><span>Hãy thử lại bằng nút AI gợi ý dự án phù hợp ở phía trên.</span></div>
                        </div>
                        <div data-opportunity-ai-results hidden>
                            <div class="learner-opportunity-ai-list" data-opportunity-ai-list></div>
                        </div>
                    </section>

                    <div class="learner-section-heading learner-ecosystem-panel__heading learner-opportunity-list-heading">
                        <div>
                            <h2>Tất cả dự án đang triển khai</h2>
                            <p>Chỉ hiển thị dự án thuộc trường của bạn và đang trong quá trình thực hiện.</p>
                        </div>
                        <span><strong data-ecosystem-result-count><?= count($projects); ?></strong> dự án</span>
                    </div>

                    <?php if ($projectLoadFailed): ?>
                        <div class="learner-empty-state learner-card learner-project-error" data-ecosystem-error>
                            <span class="learner-empty-state__icon"><?= learner_icon('info', 24); ?></span>
                            <h2>Không thể tải danh sách dự án</h2>
                            <p>Dữ liệu dự án đang tạm thời gián đoạn. Vui lòng thử lại.</p>
                            <button class="learner-btn learner-btn--outline" type="button" onclick="location.reload()">Thử lại</button>
                        </div>
                    <?php else: ?>
                        <div class="learner-project-grid" data-ecosystem-results>
                            <?php foreach ($projects as $project): ?>
                                <?php $projectSearch = implode(' ', array_filter([$project['name'] ?? '', $project['school_name'] ?? '', $project['category_label'] ?? '', $project['short_description'] ?? ''])); ?>
                                <article class="learner-project-card learner-card" data-ecosystem-item data-ecosystem-item-type="project" data-search="<?= learner_escape($projectSearch); ?>" data-field="<?= learner_escape($project['category_label']); ?>">
                                    <div class="learner-project-card__top">
                                        <span class="learner-project-card__type">Dự án</span>
                                        <span class="learner-badge learner-badge--secondary">Dự án trường</span>
                                        <span class="learner-status-dot learner-status-dot--active"><?= learner_escape($project['status_label']); ?></span>
                                    </div>
                                    <p class="learner-card-kicker"><?= learner_escape($project['category_label']); ?></p>
                                    <h3><?= learner_escape($project['name']); ?></h3>
                                    <p class="learner-project-card__school"><?= learner_icon('building', 16); ?> <?= learner_escape($project['school_name']); ?></p>
                                    <?php if (trim((string) ($project['short_description'] ?? '')) !== ''): ?>
                                        <p class="learner-project-card__description"><?= learner_escape($project['short_description']); ?></p>
                                    <?php endif; ?>
                                    <div class="learner-project-card__facts">
                                        <span><?= learner_icon('users', 16); ?><strong><?= learner_escape($project['members_count']); ?></strong> thành viên</span>
                                        <span><?= learner_icon('clock', 16); ?><strong><?= learner_escape($project['end_at_label'] !== '' ? $project['end_at_label'] : 'Chưa cập nhật'); ?></strong> ngày kết thúc</span>
                                    </div>
                                    <a class="learner-btn learner-btn--primary learner-btn--block" href="project.php?id=<?= learner_escape($project['id']); ?>">Xem chi tiết dự án <?= learner_icon('arrow-right', 16); ?></a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                        <div class="learner-empty-state learner-card" <?= $projects !== [] ? 'hidden' : ''; ?> data-ecosystem-empty>
                            <span class="learner-empty-state__icon"><?= learner_icon('search', 24); ?></span>
                            <div <?= $projects !== [] ? 'hidden' : ''; ?> data-empty-source>
                                <h2>Chưa có dự án đang triển khai</h2>
                                <p>Dự án sẽ xuất hiện khi trường của bạn bắt đầu triển khai dự án mới.</p>
                            </div>
                            <div <?= $projects === [] ? 'hidden' : ''; ?> data-empty-filter>
                                <h2>Chưa tìm thấy dự án phù hợp</h2>
                                <p>Thử thay đổi từ khóa hoặc lĩnh vực để xem thêm kết quả.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/learner-api.js'); ?>"></script>
    <script src="../../assets/js/learner.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/learner.js'); ?>"></script>
    <script src="../../assets/js/learner-opportunity-matches.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/learner-opportunity-matches.js'); ?>"></script>
</body>
</html>
