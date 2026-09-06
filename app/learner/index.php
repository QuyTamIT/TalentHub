<?php
/** TalentHub Learner - Overview */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/activity-data.php';

$pageTitle = 'Tổng quan';
$currentRoute = '/app/learner/index.php';
$dashboardSkills = array_slice($skills, 0, 4);
$onboarding = $GLOBALS['learner_page_context']['onboarding'] ?? ['required' => false];
$onboardingPending = ($onboarding['required'] ?? false) === true
    && ($onboarding['status'] ?? '') === 'pending';
$dashboardUpcomingActivities = array_slice(learner_activity_catalog(), 0, 3);
$dashboardActivityDateTime = static function (mixed $value): array {
    $rawValue = trim((string) $value);
    if ($rawValue === '') {
        return ['date' => '--/--', 'time' => 'Chưa cập nhật'];
    }
    try {
        $date = new DateTimeImmutable($rawValue, new DateTimeZone('UTC'));
        return ['date' => $date->format('d/m'), 'time' => $date->format('H:i')];
    } catch (Throwable) {
        return ['date' => '--/--', 'time' => 'Chưa cập nhật'];
    }
};
$dashboardAssessmentCompleted = max(0, (int) ($schoolCredentialData['completed_test_count'] ?? 0));
$dashboardAssessmentRequired = max(1, (int) ($schoolCredentialData['required_test_count'] ?? 4));
$dashboardAssessmentUnavailable = ($schoolCredentialError ?? false) === true;
$dashboardAnalysisCompleted = ($schoolCredentialData['analysis_completed'] ?? false) === true;
$dashboardAnalysisReady = ($schoolCredentialData['ready'] ?? false) === true;
$dashboardJourneyHref = (!$dashboardAssessmentUnavailable && ($dashboardAnalysisCompleted || $dashboardAnalysisReady))
    ? 'ai-recommendations.php'
    : 'discover.php';
$dashboardJourneyLabel = match (true) {
    $dashboardAssessmentUnavailable => 'Xem các bài đánh giá',
    $dashboardAnalysisCompleted => 'Xem lộ trình AI',
    $dashboardAnalysisReady => 'Tạo lộ trình AI',
    default => 'Tiếp tục đánh giá',
};
$dashboardKpiMap = [];
foreach ($dashboardKpis as $dashboardKpi) {
    $dashboardKpiId = (string) ($dashboardKpi['id'] ?? '');
    if ($dashboardKpiId !== '') {
        $dashboardKpiMap[$dashboardKpiId] = $dashboardKpi;
    }
}
$dashboardExperienceValue = trim((string) ($dashboardKpiMap['experience']['value'] ?? '0h'));
$dashboardExperienceHours = trim((string) preg_replace('/\s*h\s*$/i', '', $dashboardExperienceValue));
if ($dashboardExperienceHours === '') {
    $dashboardExperienceHours = '0';
}
$dashboardRoadmapAnalysis = is_array($schoolCredentialData['roadmap_analysis'] ?? null)
    ? $schoolCredentialData['roadmap_analysis']
    : null;
$dashboardAiProfile = is_array($aiCapabilityProfile) ? $aiCapabilityProfile : $dashboardRoadmapAnalysis;
$dashboardAiStrengths = is_array($dashboardAiProfile['strengths'] ?? null) ? $dashboardAiProfile['strengths'] : [];
$dashboardAiImprovements = is_array($dashboardAiProfile['improvements'] ?? null) ? $dashboardAiProfile['improvements'] : [];
$dashboardAiText = static function (mixed $item): string {
    if (is_string($item)) {
        return trim($item);
    }
    if (!is_array($item)) {
        return '';
    }
    foreach (['text', 'label', 'title'] as $field) {
        $value = trim((string) ($item[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
};
$dashboardAiStrengthLabels = array_values(array_filter(array_map($dashboardAiText, array_slice($dashboardAiStrengths, 0, 2))));
$dashboardAiImprovementLabels = array_values(array_filter(array_map($dashboardAiText, array_slice($dashboardAiImprovements, 0, 2))));
$dashboardAiTrendSignals = is_array($dashboardAiProfile['trend_signals'] ?? null) ? $dashboardAiProfile['trend_signals'] : [];
$dashboardAiTrendLabel = $dashboardAiText($dashboardAiTrendSignals[0] ?? null);
$dashboardAiSummary = trim((string) ($dashboardAiProfile['executive_summary'] ?? ''));
if ($dashboardAiSummary === '') {
    $dashboardAiSummary = $dashboardAiTrendLabel !== ''
        ? $dashboardAiTrendLabel
        : (($dashboardAiProfile['status'] ?? '') === 'stale_model'
            ? 'Đang dùng bản phân tích AI gần nhất.'
            : 'Hồ sơ AI đã cập nhật theo dữ liệu năng lực mới nhất.');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tổng quan hành trình phát triển năng lực của <?= learner_escape($student['name']); ?> trên TalentHub.">
    <title>Tổng quan Học sinh | TalentHub</title>
    <meta name="csrf-token" content="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '')); ?>">
    <meta name="csrfToken" content="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '')); ?>">
    <link rel="stylesheet" href="../../assets/css/home.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/home.css'); ?>">
    <link rel="stylesheet" href="../../assets/css/learner.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/learner.css'); ?>">
</head>
<body class="learner-app learner-page-overview" data-learner-source="<?= ($isDatabaseMode ?? false) ? 'database' : 'mock'; ?>">
    <div class="learner-layout"<?= $onboardingPending ? ' inert aria-hidden="true"' : ''; ?>>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <section class="learner-welcome" aria-labelledby="welcome-title" data-dashboard-journey>
                    <div class="learner-welcome__content">
                        <p class="learner-welcome__streak">
                            <span aria-hidden="true"><?= learner_icon('flame', 19); ?></span>
                            Chuỗi <?= learner_escape($student['streak_days']); ?> ngày liên tiếp
                        </p>
                        <h1 id="welcome-title">Chào mừng trở lại, <span class="learner-welcome__name"><?= learner_escape($student['name']); ?>&nbsp;<span aria-hidden="true">👋</span></span></h1>
                        <p>Bạn đã ghi nhận <?= learner_escape($dashboardExperienceHours); ?> giờ trải nghiệm xác thực trên hệ thống.</p>
                        <?php if ($dashboardAssessmentUnavailable): ?>
                            <p class="learner-welcome__status learner-welcome__status--unavailable">
                                Dữ liệu đánh giá tạm thời chưa tải được
                            </p>
                        <?php else: ?>
                            <p class="learner-welcome__status">
                                <span aria-hidden="true"><?= learner_icon('check', 15); ?></span>
                                <?= learner_escape($dashboardAssessmentCompleted); ?>/<?= learner_escape($dashboardAssessmentRequired); ?> bài đánh giá đã hoàn thành
                            </p>
                        <?php endif; ?>
                        <div class="learner-welcome__actions">
                            <a class="learner-btn learner-btn--primary" href="./activities.php">
                                Khám phá hoạt động <?= learner_icon('arrow-right', 18); ?>
                            </a>
                            <a class="learner-btn learner-btn--secondary" href="<?= learner_escape($dashboardJourneyHref); ?>">
                                <?= learner_escape($dashboardJourneyLabel); ?> <?= learner_icon('arrow-right', 18); ?>
                            </a>
                        </div>
                    </div>

                    <div class="learner-welcome__visual" aria-hidden="true">
                        <img class="learner-welcome__image" src="../../assets/images/learner/learner-journey-hero-v3.png" alt="" width="1448" height="1086" loading="eager" decoding="async">
                    </div>
                </section>

                <section class="learner-progress-kpis" aria-label="Chỉ số tiến bộ" data-dashboard-kpis>
                    <?php foreach ($dashboardKpis as $kpi): ?>
                        <article class="learner-card learner-progress-kpi learner-progress-kpi--<?= learner_escape($kpi['tone'] ?? 'primary'); ?>">
                            <span class="learner-progress-kpi__icon"><?= learner_icon((string) $kpi['icon'], 22); ?></span>
                            <div>
                                <p><?= learner_escape($kpi['label']); ?></p>
                                <strong><?= learner_escape($kpi['value']); ?></strong>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
                <?php if (($isDatabaseMode ?? false) && ($phase9DashboardError ?? false)): ?>
                    <p class="learner-empty-state" role="status" data-dashboard-badge-error>
                        Dữ liệu huy hiệu tạm thời chưa thể tải; các chỉ số trải nghiệm còn lại vẫn lấy từ hồ sơ đã xác nhận.
                    </p>
                <?php endif; ?>

                <div class="learner-progress-dashboard-grid">
                    <section class="learner-card learner-progress-skills" aria-labelledby="skills-title" data-dashboard-skills>
                        <div class="learner-section-heading learner-section-heading--stacked-copy">
                            <div>
                                <h2 id="skills-title">Hồ sơ kỹ năng</h2>
                                <p><?= ($dashboardSkillsFromAssessment ?? false)
                                    ? 'Tổng hợp từ 4 bài đánh giá và phân tích AI gần nhất'
                                    : 'Theo dõi mức độ thành thạo của bạn'; ?></p>
                            </div>
                            <a href="profile.php">Xem tất cả</a>
                        </div>
                        <?php if ($dashboardSkills === []): ?>
                            <div class="learner-progress-empty">
                                <?= learner_icon('sparkles', 22); ?>
                                <div>
                                    <strong>Chưa có dữ liệu kỹ năng</strong>
                                    <p>Hoàn thành bài đánh giá để bắt đầu xây dựng hồ sơ.</p>
                                </div>
                                <a href="discover.php">Bắt đầu đánh giá</a>
                            </div>
                        <?php else: ?>
                            <div class="learner-progress-skill-list">
                                <?php foreach ($dashboardSkills as $skill): ?>
                                    <?php $skillScoreClamped = max(0, min(100, (int) ($skill['score'] ?? 0))); ?>
                                    <div class="learner-progress-skill">
                                        <div class="learner-progress-skill__heading">
                                            <strong><?= learner_escape($skill['name']); ?></strong>
                                            <span><?= learner_escape($skill['level'] ?? 'Đang cập nhật'); ?></span>
                                            <b><?= $skillScoreClamped; ?>/100</b>
                                            <?php if ($skill['verified'] ?? false): ?>
                                                <i title="Đã xác thực"><?= learner_icon('check', 12); ?></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="learner-progress" role="progressbar" aria-label="<?= learner_escape($skill['name']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $skillScoreClamped; ?>">
                                            <span class="learner-progress--<?= learner_escape($skill['tone']); ?>" style="--learner-progress: <?= $skillScoreClamped; ?>%;"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <aside class="learner-card learner-progress-ai" aria-labelledby="ai-title" data-dashboard-ai-summary>
                        <div class="learner-progress-ai__title">
                            <?= learner_icon('sparkles', 20); ?>
                            <h2 id="ai-title">AI tóm tắt tiến độ</h2>
                        </div>
                        <?php if (is_array($dashboardAiProfile)): ?>
                            <p><?= learner_escape($dashboardAiSummary); ?></p>
                            <section data-ai-strengths>
                                <strong>Điểm mạnh</strong>
                                <?php if ($dashboardAiStrengthLabels === []): ?><span>Chưa có nhận định mới</span><?php endif; ?>
                                <?php foreach ($dashboardAiStrengthLabels as $label): ?><span><?= learner_escape($label); ?></span><?php endforeach; ?>
                            </section>
                            <section data-ai-improvements>
                                <strong>Nên cải thiện</strong>
                                <?php if ($dashboardAiImprovementLabels === []): ?><span>Chưa có ưu tiên mới</span><?php endif; ?>
                                <?php foreach ($dashboardAiImprovementLabels as $label): ?><span><?= learner_escape($label); ?></span><?php endforeach; ?>
                            </section>
                            <?php if ($dashboardAiTrendLabel !== ''): ?>
                                <div class="learner-progress-ai__trend" data-ai-trend><?= learner_icon('chart', 16); ?><?= learner_escape($dashboardAiTrendLabel); ?></div>
                            <?php endif; ?>
                            <a href="ai-recommendations.php">Xem phân tích đầy đủ <?= learner_icon('arrow-right', 15); ?></a>
                        <?php elseif ($dashboardAssessmentUnavailable): ?>
                            <p>Gợi ý AI tạm thời chưa tải được. Tiến độ của bạn không bị thay đổi; vui lòng thử lại sau.</p>
                            <a href="discover.php">Xem các bài đánh giá <?= learner_icon('arrow-right', 15); ?></a>
                        <?php elseif ($dashboardAnalysisCompleted): ?>
                            <p>Phân tích đã hoàn thành nhưng hồ sơ AI tạm thời chưa tải được.</p>
                            <a href="ai-recommendations.php">Tải lại phân tích <?= learner_icon('arrow-right', 15); ?></a>
                        <?php elseif ($dashboardAnalysisReady): ?>
                            <p>Bốn bài đánh giá đã hoàn thành. Hãy tạo lộ trình để xem gợi ý cá nhân hóa.</p>
                            <a href="ai-recommendations.php">Tạo lộ trình AI <?= learner_icon('arrow-right', 15); ?></a>
                        <?php else: ?>
                            <p>Đã hoàn thành <?= learner_escape($dashboardAssessmentCompleted); ?>/<?= learner_escape($dashboardAssessmentRequired); ?> bài đánh giá. Hoàn thành bộ bài để mở khóa gợi ý AI.</p>
                            <a href="discover.php">Tiếp tục đánh giá <?= learner_icon('arrow-right', 15); ?></a>
                        <?php endif; ?>
                    </aside>
                <section class="learner-card learner-progress-activities" aria-labelledby="activities-title" data-dashboard-upcoming-activities>
                    <div class="learner-section-heading">
                        <h2 id="activities-title">Hoạt động sắp diễn ra</h2>
                        <a href="activities.php">Tất cả hoạt động</a>
                    </div>
                    <?php if ($dashboardUpcomingActivities === []): ?>
                        <div class="learner-progress-empty">
                            <?= learner_icon('calendar', 22); ?>
                            <div>
                                <strong>Chưa có hoạt động sắp diễn ra</strong>
                                <p>Khám phá danh sách hoạt động phù hợp với bạn.</p>
                            </div>
                            <a href="activities.php">Khám phá hoạt động</a>
                        </div>
                    <?php else: ?>
                        <div class="learner-progress-activity-list">
                            <?php foreach ($dashboardUpcomingActivities as $activity): ?>
                                <?php
                                $activityId = (string) ($activity['route_id'] ?? $activity['id'] ?? '');
                                $activityWhen = $dashboardActivityDateTime($activity['start_at'] ?? null);
                                $activityLocation = trim((string) ($activity['location'] ?? '')) ?: 'Chưa cập nhật';
                                ?>
                                <article class="learner-progress-activity">
                                    <time datetime="<?= learner_escape($activity['start_at'] ?? ''); ?>">
                                        <strong><?= learner_escape($activityWhen['date']); ?></strong>
                                        <span><?= learner_escape($activityWhen['time']); ?></span>
                                    </time>
                                    <div>
                                        <h3><?= learner_escape($activity['title'] ?? 'Hoạt động TalentHub'); ?></h3>
                                        <p><?= learner_icon('map-pin', 14); ?><?= learner_escape($activityLocation); ?></p>
                                        <a href="activity-detail.php?id=<?= rawurlencode($activityId); ?>">Xem chi tiết <?= learner_icon('arrow-right', 14); ?></a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                </div>

                <section class="learner-card learner-school-credential-section" aria-labelledby="dashboard-school-credential-title">
                    <div class="learner-school-credential-heading">
                        <div>
                            <span class="learner-school-credential-heading__eyebrow"><?= learner_icon('graduation-cap', 17); ?> Thành tích do trường cấp</span>
                            <h2 id="dashboard-school-credential-title">Huy hiệu &amp; chứng chỉ dành cho bạn</h2>
                            <p>
                                <?php if ($dashboardAssessmentUnavailable): ?>
                                    Trạng thái thành tích tạm thời chưa tải được; tiến độ đánh giá của bạn không bị thay đổi.
                                <?php elseif ($schoolCredentialData['ready'] ?? false): ?>
                                    <?= ($schoolCredentialData['analysis_completed'] ?? false) ? 'AI đã xếp hạng theo bốn bài test, năng lực và kỹ năng của bạn.' : 'Bạn đã đủ bốn bài test; hoàn thành phân tích AI để có lộ trình phát triển.'; ?>
                                <?php else: ?>
                                    Hoàn thành <?= learner_escape($schoolCredentialData['completed_test_count'] ?? 0); ?>/4 bài test để mở khóa mức độ phù hợp.
                                <?php endif; ?>
                            </p>
                        </div>
                        <a href="badges.php">Xem toàn bộ <?= learner_icon('arrow-right', 16); ?></a>
                    </div>
                    <?php if ($schoolCredentialError ?? false): ?>
                        <p class="learner-empty-state">Dữ liệu thành tích của trường tạm thời chưa tải được.</p>
                    <?php else: ?>
                        <?php
                        $credentialItems = $schoolCredentialData['featured'] ?? [];
                        $credentialCompact = true;
                        include __DIR__ . '/includes/school-credential-grid.php';
                        unset($credentialItems, $credentialCompact);
                        ?>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <?php if ($onboardingPending): ?>
    <div class="learner-onboarding" data-onboarding-dialog>
        <div class="learner-onboarding__backdrop" aria-hidden="true"></div>
        <section
            class="learner-onboarding__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="onboarding-title"
            aria-describedby="onboarding-description"
            tabindex="-1"
        >
            <span class="learner-onboarding__eyebrow">Bước bắt buộc cho tài khoản mới</span>
            <h2 id="onboarding-title">Hoàn thành đánh giá ban đầu</h2>
            <p id="onboarding-description">Hoàn thành bốn bài đánh giá để TalentHub hiểu sở thích, năng khiếu và cá nhân hóa lộ trình phát triển của bạn.</p>
            <ul class="learner-onboarding__tests" aria-label="Bốn bài đánh giá bắt buộc">
                <li>Holland</li>
                <li>MBTI</li>
                <li>DISC</li>
                <li>Đa trí thông minh</li>
            </ul>
            <p class="learner-onboarding__save-note">Tiến độ được tự động lưu để bạn tiếp tục trong lần đăng nhập sau.</p>
            <div class="learner-onboarding__actions">
                <form method="post" action="<?= learner_escape(app_href('/app/learner/onboarding.php')); ?>">
                    <input type="hidden" name="csrfToken" value="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '')); ?>">
                    <input type="hidden" name="csrf_token" value="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '')); ?>">
                    <button class="learner-btn learner-btn--primary" type="submit" name="action" value="accept">Đồng ý và bắt đầu</button>
                </form>
                <form method="post" action="<?= learner_escape(app_href('/app/learner/onboarding.php')); ?>">
                    <input type="hidden" name="csrfToken" value="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '')); ?>">
                    <input type="hidden" name="csrf_token" value="<?= learner_escape($GLOBALS['learner_page_context']['csrfToken'] ?? ($_SESSION['csrfToken'] ?? $_SESSION['csrf_token'] ?? '')); ?>">
                    <button class="learner-btn learner-btn--danger" type="submit" name="action" value="decline">Từ chối và đăng xuất</button>
                </form>
            </div>
        </section>
    </div>
    <?php endif; ?>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <?php if ($onboardingPending): ?>
    <script src="../../assets/js/learner-onboarding.js"></script>
    <?php endif; ?>
</body>
</html>
