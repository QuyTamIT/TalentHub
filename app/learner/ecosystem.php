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
$allowedLifecycleFilters = ['all', 'recruiting', 'active', 'completed'];
$initialLifecycleFilter = (string) ($_GET['filter'] ?? 'all');
$initialLifecycleFilter = in_array($initialLifecycleFilter, $allowedLifecycleFilters, true) ? $initialLifecycleFilter : 'all';
$enterprises = learner_ecosystem_enterprises($student['school_id'] ?? null);
$projectLoadFailed = false;
try {
    $projects = learner_projects();
} catch (Throwable) {
    $projects = [];
    $projectLoadFailed = true;
}
$applicationSummary = [
    'total' => 0,
    'submitted' => 0,
    'reviewing' => 0,
    'interview' => 0,
    'accepted' => 0,
    'declined' => 0,
    'withdrawn' => 0,
    'items' => [],
];
try {
    $applicationSummary = learner_ecosystem_my_applications_summary();
} catch (Throwable) {
    // Resilient fallback if applications cannot be loaded
}
$myApplications = $applicationSummary['items'];
$myApplicationsCount = $applicationSummary['total'];
$ecosystemSource = learner_repository_factory()->source();
$isDatabaseSource = $ecosystemSource === 'database';

$completedEnterprises = [];
foreach ($myApplications as $app) {
    if (in_array($app['status'] ?? '', ['completed', 'accepted'], true)) {
        if (!empty($app['partner_name'])) {
            $completedEnterprises[mb_strtolower(trim((string) $app['partner_name']))] = true;
        }
    }
}
$currentStudentId = learner_current_student_id();
if ($currentStudentId !== '') {
    if ($isDatabaseSource) {
        try {
            $config = require dirname(__DIR__, 2) . '/config/database.php';
            $db = (new \TalentHub\Database\Connection($config))->connect();
            $stmt = $db->prepare(<<<'SQL'
                SELECT DISTINCT e.id, e.name
                FROM internship_applications a
                JOIN internship_posts ip ON ip.id = a.postId
                JOIN enterprises e ON e.id = ip.enterpriseId
                JOIN learner_internship_reports r ON r.applicationId = a.id AND r.studentId = a.studentId
                WHERE a.studentId = :studentId AND r.status = 'verified' AND r.stage = 'completed'
            SQL);
            $stmt->execute(['studentId' => $currentStudentId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $completedEnterprises[(string) $row['id']] = true;
                $completedEnterprises[mb_strtolower(trim((string) $row['name']))] = true;
            }
        } catch (Throwable) {
            // Safe fallback
        }
    }
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Khám phá doanh nghiệp và các dự án đang triển khai tại trường trên TalentHub.">
    <title>Hệ sinh thái &amp; Dự án | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/home.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/global.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/global.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/brand-component.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/brand-component.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/polish.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/polish.css'); ?>">
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
                        <?= learner_icon('briefcase', 19); ?> Doanh nghiệp &amp; cơ hội
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
                        <select data-ecosystem-filter="status" aria-label="Lọc trạng thái tham gia">
                            <option value="all" <?= $initialLifecycleFilter === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                            <option value="recruiting" <?= $initialLifecycleFilter === 'recruiting' ? 'selected' : ''; ?>>Đang mở</option>
                            <option value="active" <?= $initialLifecycleFilter === 'active' ? 'selected' : ''; ?>>Đang tham gia</option>
                            <option value="completed" <?= $initialLifecycleFilter === 'completed' ? 'selected' : ''; ?>>Đã hoàn thành</option>
                        </select>
                    </label>
                    <button class="learner-btn learner-btn--primary learner-opportunity-ai-trigger" type="button" data-opportunity-ai-trigger <?= $initialTab !== 'opportunities' ? 'hidden' : ''; ?>>
                        <?= learner_icon('sparkles', 18); ?> AI gợi ý dự án phù hợp
                    </button>
                    <button class="learner-btn learner-btn--primary learner-job-ai-trigger" type="button" data-job-ai-trigger <?= $initialTab !== 'enterprises' ? 'hidden' : ''; ?>>
                        <?= learner_icon('sparkles', 18); ?> AI gợi ý việc làm phù hợp
                    </button>
                </section>

                <section id="panel-enterprises" class="learner-ecosystem-panel" role="tabpanel" aria-labelledby="tab-enterprises" <?= $initialTab !== 'enterprises' ? 'hidden' : ''; ?> data-ecosystem-panel="enterprises">
                    <!-- Bổ sung Vấn đề #06: Banner/Drawer Quản lý & Theo dõi Đơn ứng tuyển Thực tập -->
                    <section class="learner-card learner-applications-tracker" data-applications-tracker data-has-applications="<?= $myApplicationsCount > 0 ? 'true' : 'false'; ?>" aria-labelledby="applications-tracker-title">
                        <header class="learner-applications-tracker__header">
                            <div class="learner-applications-tracker__title-group">
                                <span class="learner-applications-tracker__icon" aria-hidden="true"><?= learner_icon('briefcase', 20); ?></span>
                                <div>
                                    <h2 id="applications-tracker-title">Hồ sơ ứng tuyển của bạn</h2>
                                    <p>Theo dõi tiến trình xét duyệt và lịch phỏng vấn tại các doanh nghiệp đối tác.</p>
                                </div>
                            </div>
                            <div class="learner-applications-tracker__header-actions">
                                <?php if ($myApplicationsCount > 0): ?>
                                    <?php $isTrackerExpanded = ($myApplicationsCount > 0); ?>
                                    <span class="learner-applications-tracker__count-pill">
                                        <?= learner_icon('briefcase', 14); ?> <?= $myApplicationsCount; ?> hồ sơ ứng tuyển
                                    </span>
                                    <button class="learner-applications-tracker__toggle" type="button" data-tracker-toggle aria-expanded="<?= $isTrackerExpanded ? 'true' : 'false'; ?>" aria-controls="applications-tracker-body">
                                        <span data-toggle-label><?= $isTrackerExpanded ? 'Thu gọn' : 'Xem danh sách'; ?></span>
                                        <span data-toggle-icon aria-hidden="true"<?= $isTrackerExpanded ? ' style="transform: rotate(180deg);"' : ''; ?>><?= learner_icon('chevron-down', 15); ?></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </header>

                        <?php if ($myApplicationsCount === 0): ?>
                            <div class="learner-applications-tracker__empty">
                                <p><?= learner_icon('info', 16); ?> Bạn chưa nộp hồ sơ ứng tuyển vị trí nào. Hãy khám phá danh sách doanh nghiệp và cơ hội bên dưới để ứng tuyển!</p>
                            </div>
                        <?php else: ?>
                            <div class="learner-applications-tracker__summary">
                                <?php if (($applicationSummary['submitted'] ?? 0) > 0): ?>
                                    <span class="learner-status-chip is-reviewing"><?= learner_icon('send', 13); ?> Chờ tiếp nhận: <strong><?= $applicationSummary['submitted']; ?></strong></span>
                                <?php endif; ?>
                                <?php if (($applicationSummary['reviewing'] ?? 0) > 0): ?>
                                    <span class="learner-status-chip is-reviewing"><?= learner_icon('clock', 13); ?> Đang xem xét: <strong><?= $applicationSummary['reviewing']; ?></strong></span>
                                <?php endif; ?>
                                <?php if (($applicationSummary['interview'] ?? 0) > 0): ?>
                                    <span class="learner-status-chip is-interview"><?= learner_icon('calendar', 13); ?> Phỏng vấn: <strong><?= $applicationSummary['interview']; ?></strong></span>
                                <?php endif; ?>
                                <?php if (($applicationSummary['accepted'] ?? 0) > 0): ?>
                                    <span class="learner-status-chip is-accepted"><?= learner_icon('check', 13); ?> Trúng tuyển: <strong><?= $applicationSummary['accepted']; ?></strong></span>
                                <?php endif; ?>
                                <?php if (($applicationSummary['declined'] ?? 0) > 0): ?>
                                    <span class="learner-status-chip is-declined"><?= learner_icon('info', 13); ?> Chưa phù hợp: <strong><?= $applicationSummary['declined']; ?></strong></span>
                                <?php endif; ?>
                                <?php if (($applicationSummary['withdrawn'] ?? 0) > 0): ?>
                                    <span class="learner-status-chip is-withdrawn"><?= learner_icon('x', 13); ?> Đã rút: <strong><?= $applicationSummary['withdrawn']; ?></strong></span>
                                <?php endif; ?>
                            </div>

                            <div class="learner-applications-tracker__body" id="applications-tracker-body" data-tracker-body<?= !empty($isTrackerExpanded) ? '' : ' hidden'; ?>>
                                <div class="learner-applications-tracker__list">
                                    <?php foreach ($myApplications as $app): ?>
                                        <?php
                                        $statusNote = '';
                                        $appStatus = (string) ($app['status'] ?? 'submitted');
                                        if (in_array($appStatus, ['submitted', 'applied'], true)) {
                                            $statusNote = 'Hồ sơ đã được chuyển tới bộ phận tuyển dụng doanh nghiệp. Doanh nghiệp thường xem xét và phản hồi trong 3 - 5 ngày làm việc.';
                                        } elseif ($appStatus === 'reviewing') {
                                            $statusNote = 'Doanh nghiệp đang xem xét năng lực và dự án mẫu trong hồ sơ của bạn.';
                                        } elseif ($appStatus === 'interview') {
                                            $statusNote = 'Chúc mừng! Bạn đã được chọn vào vòng phỏng vấn. Hãy kiểm tra thông báo và email để nắm lịch chi tiết.';
                                        } elseif (in_array($appStatus, ['accepted', 'hired'], true)) {
                                            $statusNote = 'Chúc mừng bạn đã trúng tuyển thực tập! Nhà tuyển dụng đã duyệt tiếp nhận và sẽ sớm liên hệ hướng dẫn nhận việc.';
                                        } elseif ($appStatus === 'declined') {
                                            $statusNote = 'Rất tiếc hồ sơ chưa phù hợp trong đợt này. Bạn có thể trau dồi thêm và ứng tuyển vị trí khác.';
                                        } elseif ($appStatus === 'withdrawn') {
                                            $statusNote = 'Bạn đã chủ động rút hồ sơ khỏi vị trí tuyển dụng này.';
                                        }
                                        ?>
                                        <article class="learner-application-card" data-app-card data-app-id="<?= learner_escape($app['id']); ?>">
                                            <div class="learner-application-card__header">
                                                <div class="learner-application-card__identity">
                                                    <div class="learner-application-card__avatar" aria-hidden="true">
                                                        <?= learner_escape($app['partner_initials'] ?? 'DN'); ?>
                                                    </div>
                                                    <div class="learner-application-card__details">
                                                        <div class="learner-application-card__meta-top">
                                                            <span class="learner-status-badge is-<?= learner_escape($app['status']); ?>" data-app-status-badge>
                                                                <span class="dot" aria-hidden="true"></span>
                                                                <?= learner_escape($app['status_label']); ?>
                                                            </span>
                                                            <span class="learner-work-type-badge">Thực tập</span>
                                                        </div>
                                                        <h3 class="learner-application-card__title">
                                                            <a href="opportunity.php?type=<?= learner_escape($app['opportunity_type']); ?>&amp;id=<?= learner_escape($app['opportunity_id']); ?>">
                                                                <?= learner_escape($app['title']); ?>
                                                            </a>
                                                        </h3>
                                                        <div class="learner-application-card__submeta">
                                                            <span class="learner-application-card__partner-name">
                                                                <?= learner_icon('building', 14); ?>
                                                                <?= learner_escape($app['partner_name']); ?>
                                                            </span>
                                                            <span>·</span>
                                                            <span class="learner-application-card__date">
                                                                Nộp lúc <?= learner_escape($app['submitted_at_formatted'] ?? $app['submitted_at'] ?? 'Chưa xác định'); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="learner-application-card__actions">
                                                    <a class="learner-btn learner-btn--view-app" href="opportunity.php?type=<?= learner_escape($app['opportunity_type']); ?>&amp;id=<?= learner_escape($app['opportunity_id']); ?>">
                                                        Chi tiết vị trí <?= learner_icon('arrow-right', 14); ?>
                                                    </a>
                                                    <?php if (!empty($app['can_withdraw'])): ?>
                                                        <button class="learner-btn--danger-outline" type="button" data-withdraw-btn data-withdraw-id="<?= learner_escape($app['id']); ?>">
                                                            Rút hồ sơ
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="learner-app-stepper" aria-label="Tiến trình xét duyệt">
                                                <div class="learner-app-stepper__track">
                                                    <?php
                                                    $steps = !empty($app['pipeline']) ? $app['pipeline'] : [];
                                                    foreach ($steps as $idx => $step):
                                                        $sState = $step['state'] ?? 'upcoming';
                                                    ?>
                                                        <div class="learner-app-step is-<?= learner_escape($sState); ?>" data-step-id="<?= learner_escape($step['id']); ?>">
                                                            <div class="learner-app-step__node" aria-hidden="true">
                                                                <?php if ($sState === 'complete'): ?>
                                                                    <?= learner_icon('check', 16); ?>
                                                                <?php elseif ($sState === 'declined' || $sState === 'withdrawn'): ?>
                                                                    <?= learner_icon('x', 16); ?>
                                                                <?php elseif ($sState === 'current'): ?>
                                                                    <?= learner_icon('clock', 15); ?>
                                                                <?php else: ?>
                                                                    <?= $idx + 1; ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="learner-app-step__content">
                                                                <span class="learner-app-step__title"><?= learner_escape($step['label']); ?></span>
                                                                <span class="learner-app-step__desc"><?= learner_escape($step['desc']); ?></span>
                                                                <?php if (!empty($step['date'])): ?>
                                                                    <time class="learner-app-step__time"><?= learner_escape($step['date']); ?></time>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($app['message'])): ?>
                                                <div class="learner-app-message-preview">
                                                    <span class="learner-app-message-label"><?= learner_icon('mail', 14); ?> Lời nhắn gửi kèm của bạn:</span>
                                                    <p class="learner-app-message-text">“<?= learner_escape($app['message']); ?>”</p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($statusNote)): ?>
                                                <div class="learner-app-status-note">
                                                    <?= learner_icon('info', 16); ?>
                                                    <div><?= learner_escape($statusNote); ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="learner-job-ai learner-card" data-job-matches aria-labelledby="job-ai-title">
                        <header class="learner-job-ai__header">
                            <span class="learner-job-ai__icon" aria-hidden="true"><?= learner_icon('sparkles', 22); ?></span>
                            <div><span class="learner-eyebrow">AI JOB MATCHING</span><h2 id="job-ai-title">Vị trí phù hợp với hồ sơ của bạn</h2><p>Gemini giải thích kết quả từ điểm 40/35/25 và dữ liệu bạn đã cho phép.</p></div>
                            <div class="learner-job-ai__header-actions">
                                <p class="learner-job-ai__status" data-job-ai-status role="status" aria-live="polite">Sẵn sàng phân tích</p>
                                <button class="learner-job-ai__collapse" type="button" data-job-ai-collapse aria-expanded="true" aria-controls="job-ai-body">Thu gọn</button>
                            </div>
                        </header>
                        <div class="learner-job-ai__body" id="job-ai-body" data-job-ai-body>
                            <div class="learner-job-ai__progress" data-job-ai-progress hidden>
                                <div class="learner-job-ai__progress-heading"><span data-job-ai-progress-text>Đang quét hồ sơ...</span><strong data-job-ai-progress-pct>8%</strong></div>
                                <div class="learner-job-ai__progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="8"><span data-job-ai-progress-bar style="width:8%"></span></div>
                                <div class="learner-job-ai__progress-stages" data-job-ai-progress-stages><span>1. Quét hồ sơ</span><span>2. Lọc vị trí</span><span>3. Gemini phân tích</span><span>4. Xếp hạng</span></div>
                            </div>
                            <div class="learner-job-ai__message" data-job-ai-not-generated><strong>Khám phá cơ hội ứng tuyển phù hợp</strong><span>Bấm nút AI phía trên để đối chiếu hồ sơ với các vị trí đang tuyển thật.</span></div>
                            <div class="learner-job-ai__message is-warning" data-job-ai-consent-required hidden><strong>Cần quyền sử dụng dữ liệu AI</strong><span>Hãy cập nhật quyền dữ liệu trong hồ sơ năng lực trước khi phân tích.</span><a class="learner-btn learner-btn--outline" href="profile.php">Quản lý quyền</a></div>
                            <div class="learner-job-ai__message is-warning" data-job-ai-insufficient-data hidden><strong>Chưa đủ dữ liệu benchmark</strong><span>Bổ sung kỹ năng và hoàn thành đánh giá để hệ thống chấm mức độ phù hợp.</span><a class="learner-btn learner-btn--outline" href="profile.php">Bổ sung hồ sơ</a></div>
                            <div class="learner-job-ai__message is-warning" data-job-ai-catalog-insufficient hidden><strong>Chưa có vị trí đang mở phù hợp phạm vi</strong><span>Danh sách sẽ được cập nhật khi doanh nghiệp công bố cơ hội mới.</span></div>
                            <div class="learner-job-ai__message is-warning" data-job-ai-no-matches hidden><strong>Chưa có vị trí đạt ngưỡng 40 điểm</strong><span>Chưa thể tạo phân tích vị trí gần ngưỡng. Hãy bổ sung hồ sơ rồi thử lại.</span><a class="learner-btn learner-btn--outline" href="profile.php">Bổ sung hồ sơ</a></div>
                            <div class="learner-job-ai__near-match" data-job-ai-near-match hidden></div>
                            <div class="learner-job-ai__message is-error" data-job-ai-source-error hidden><strong>Phân tích tạm thời chưa khả dụng</strong><span>Kết quả cũ không bị mất. Bạn có thể bấm lại nút AI để thử lại.</span></div>
                            <div class="learner-job-ai__list" data-job-ai-list hidden></div>
                        </div>
                    </section>
                    <div class="learner-section-heading learner-ecosystem-panel__heading">
                        <div>
                            <h2>Doanh nghiệp &amp; cơ hội</h2>
                            <p>Các doanh nghiệp đang liên kết và đồng hành cùng <?= learner_escape($student['school'] ?? 'trường của bạn'); ?>.</p>
                        </div>
                        <span><?= count($enterprises); ?> doanh nghiệp</span>
                    </div>
                    <div class="learner-partner-grid" data-ecosystem-results>
                        <?php foreach ($enterprises as $enterprise): ?>
                            <?php $enterpriseVerificationStatus = (string) ($enterprise['verification_status'] ?? ''); ?>
                            <?php $enterpriseIndustry = learner_ecosystem_partner_has_value($enterprise, 'industry') ? trim((string) $enterprise['industry']) : ''; ?>
                            <?php $enterpriseLocation = learner_ecosystem_partner_has_value($enterprise, 'location') ? trim((string) $enterprise['location']) : ''; ?>
                            <?php $enterpriseSearch = implode(' ', array_filter([(string) $enterprise['name'], $enterpriseIndustry, $enterpriseLocation])); ?>
                            <?php
                            $entId = (string) ($enterprise['id'] ?? '');
                            $entName = mb_strtolower(trim((string) ($enterprise['name'] ?? '')));
                            $isCompletedEnt = isset($completedEnterprises[$entId]) || isset($completedEnterprises[$entName]);
                            $entStatuses = ['recruiting'];
                            if ($isCompletedEnt) {
                                $entStatuses[] = 'completed';
                            }
                            $entStatusAttr = implode(' ', $entStatuses);
                            ?>
                            <article class="learner-partner-card learner-card" data-ecosystem-item data-search="<?= learner_escape($enterpriseSearch); ?>" data-field="<?= learner_escape($enterpriseIndustry); ?>" data-location="<?= learner_escape($enterpriseLocation); ?>" data-status="<?= learner_escape($entStatusAttr); ?>">
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
                            <p>Hiện chưa có doanh nghiệp nào được xác nhận liên kết chính thức với trường của bạn.</p>
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
                            <div class="learner-opportunity-ai__header-actions">
                                <p class="learner-opportunity-ai__status" data-opportunity-ai-status role="status" aria-live="polite">Sẵn sàng phân tích</p>
                                <button class="learner-opportunity-ai__collapse" type="button" data-opportunity-ai-collapse aria-expanded="true" aria-controls="opportunity-ai-body">Thu gọn</button>
                            </div>
                        </header>

                        <div class="learner-opportunity-ai__body" id="opportunity-ai-body" data-opportunity-ai-body>
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
                                <?php $projectSearch = implode(' ', array_filter([$project['title'] ?? '', $project['school_name'] ?? '', $project['category_label'] ?? '', $project['description'] ?? ''])); ?>
                                <?php
                                $projectStatusAttr = (($project['status'] ?? '') === 'completed' || ($project['membership_status'] ?? '') === 'completed')
                                    ? 'completed'
                                    : (learner_escape($project['membership_status'] ?? 'recruiting'));
                                ?>
                                <article class="learner-project-card learner-card" data-ecosystem-item data-ecosystem-item-type="project" data-search="<?= learner_escape($projectSearch); ?>" data-field="<?= learner_escape($project['category_label']); ?>" data-status="<?= learner_escape($projectStatusAttr); ?>">
                                    <div class="learner-project-card__top">
                                        <span class="learner-badge learner-badge--secondary">Dự án</span>
                                        <?php if (($project['membership_status'] ?? '') === 'pending'): ?>
                                            <span class="learner-badge" style="background:#FEF3C7; color:#92400E; border:1px solid #FCD34D;">⏳ Đang chờ duyệt</span>
                                        <?php elseif (($project['membership_status'] ?? '') === 'active'): ?>
                                            <span class="learner-badge" style="background:#D1FAE5; color:#065F46; border:1px solid #A7F3D0;">✓ Đã tham gia</span>
                                        <?php endif; ?>
                                        <span class="learner-status-dot learner-status-dot--active"><?= learner_escape($project['status_label']); ?></span>
                                    </div>
                                    <p class="learner-card-kicker"><?= learner_escape($project['category_label']); ?></p>
                                    <h3><?= learner_escape($project['title']); ?></h3>
                                    <p class="learner-project-card__school"><?= learner_icon('building', 16); ?> <?= learner_escape($project['school_name']); ?></p>
                                    <?php if (trim((string) ($project['description'] ?? '')) !== ''): ?>
                                        <p class="learner-project-card__description"><?= learner_escape($project['description']); ?></p>
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
    <script src="../../assets/js/learner-job-matches.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/learner-job-matches.js'); ?>"></script>
    <script src="../../assets/js/learner-applications-tracker.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/learner-applications-tracker.js'); ?>"></script>
</body>
</html>
