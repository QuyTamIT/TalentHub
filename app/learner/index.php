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
$dashboardOpenActivities = ($isDatabaseMode ?? false)
    ? array_slice(learner_activity_catalog(), 0, 3)
    : $activities;
$dashboardConfirmedActivities = ($isDatabaseMode ?? false) ? $activities : [];
$dashboardActivityDate = static function (mixed $value): string {
    try {
        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))->format('d/m/Y');
    } catch (Throwable) {
        return 'Chưa cập nhật';
    }
};
$dashboardActivityCover = static function (mixed $value): string {
    $cover = trim((string) $value);
    if ($cover === '' || str_contains($cover, '..')) {
        return 'assets/activities/illustrations/hero-detail.svg';
    }
    return preg_match('#\A(?:/app/learner/)?assets/activities/[a-z0-9/_-]+\.(?:webp|png|jpe?g|svg)\z#i', $cover) === 1
        ? $cover
        : 'assets/activities/illustrations/hero-detail.svg';
};
$dashboardActivityCategory = static function (array $activity): string {
    foreach (['display_category', 'filter_category'] as $field) {
        $label = trim((string) ($activity[$field] ?? ''));
        if ($label !== '') return $label;
    }
    $canonical = trim((string) ($activity['canonical_category'] ?? $activity['category'] ?? ''));
    return learner_activity_category_label($canonical);
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
$dashboardExperienceValue = trim((string) ($dashboardKpis[2]['value'] ?? '0h'));
$dashboardExperienceHours = trim((string) preg_replace('/\s*h\s*$/i', '', $dashboardExperienceValue));
if ($dashboardExperienceHours === '') {
    $dashboardExperienceHours = '0';
}
$dashboardAiTalentMap = is_array($aiCapabilityProfile['talent_map'] ?? null) ? $aiCapabilityProfile['talent_map'] : [];
$dashboardAiStrengths = is_array($aiCapabilityProfile['strengths'] ?? null) ? $aiCapabilityProfile['strengths'] : [];
$dashboardAiImprovements = is_array($aiCapabilityProfile['improvements'] ?? null) ? $aiCapabilityProfile['improvements'] : [];
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
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-overview" data-learner-source="<?= ($isDatabaseMode ?? false) ? 'database' : 'mock'; ?>">
    <div class="learner-layout"<?= $onboardingPending ? ' inert aria-hidden="true"' : ''; ?>>
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <?php if (is_array($aiCapabilityProfile)): ?>
                    <section class="learner-card learner-dashboard-ai-profile" aria-label="Tóm tắt AI hồ sơ năng lực">
                        <h2>AI gợi ý từ Talent Passport</h2>
                        <p><?= learner_escape(($aiCapabilityProfile['status'] ?? '') === 'stale_model' ? 'Đang dùng bản phân tích AI gần nhất.' : 'Hồ sơ AI đã cập nhật theo dữ liệu mới nhất.'); ?></p>
                        <div class="learner-dashboard-ai-profile__grid">
                            <div data-dashboard-ai-talent-map><strong>Bản đồ năng khiếu</strong>
                                <?php foreach ($dashboardAiTalentMap as $field => $entry): ?>
                                    <?php $label = is_array($entry) ? (string) ($entry['field'] ?? $field) : (string) $field; $score = is_array($entry) ? ($entry['score'] ?? 0) : $entry; ?>
                                    <span><?= learner_escape($label); ?>: <?= max(0, min(100, (int) $score)); ?>%</span>
                                <?php endforeach; ?>
                            </div>
                            <div data-dashboard-ai-strengths><strong>Điểm mạnh mới nhất</strong>
                                <?php foreach (array_slice($dashboardAiStrengths, 0, 2) as $strength): ?>
                                    <span title="Nhận định có nguồn bằng chứng trong Talent Passport"><?= learner_escape($strength['text'] ?? $strength['label'] ?? 'Đang cập nhật'); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div data-dashboard-ai-improvements><strong>Ưu tiên cải thiện</strong>
                                <?php foreach (array_slice($dashboardAiImprovements, 0, 2) as $improvement): ?>
                                    <span title="Nhận định có nguồn bằng chứng trong Talent Passport"><?= learner_escape($improvement['text'] ?? $improvement['label'] ?? 'Đang cập nhật'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <a href="profile.php#ai-capability-profile-title">Xem điểm mạnh, điểm cần cải thiện và nguồn bằng chứng</a>
                    </section>
                <?php endif; ?>
                <section class="learner-welcome" aria-labelledby="welcome-title" data-dashboard-journey>
                    <div class="learner-welcome__content">
                        <p class="learner-welcome__streak">
                            <span aria-hidden="true"><?= learner_icon('flame', 19); ?></span>
                            Chuỗi <?= learner_escape($student['streak_days']); ?> ngày liên tiếp
                        </p>
                        <h1 id="welcome-title">Chào mừng trở lại, <?= learner_escape($student['name']); ?> <span aria-hidden="true">👋</span></h1>
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

                <section class="learner-kpi-grid" aria-label="Chỉ số tổng quan">
                    <?php foreach ($dashboardKpis as $kpi): ?>
                        <article class="learner-card learner-kpi-card">
                            <div class="learner-kpi-card__top">
                                <span class="learner-icon-tile learner-icon-tile--secondary"><?= learner_icon($kpi['icon'], 22); ?></span>
                                <?php if ($isDatabaseMode ?? false): ?>
                                    <span class="learner-kpi-card__verified"><?= learner_icon('check', 13); ?> Đã xác thực</span>
                                <?php else: ?>
                                    <span class="learner-kpi-card__change"><?= learner_escape($kpi['change']); ?></span>
                                <?php endif; ?>
                            </div>
                            <strong><?= learner_escape($kpi['value']); ?></strong>
                            <p><?= learner_escape($kpi['label']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </section>
                <?php if (($isDatabaseMode ?? false) && ($phase9DashboardError ?? false)): ?>
                    <p class="learner-empty-state" role="status" data-dashboard-badge-error>
                        Dữ liệu huy hiệu tạm thời chưa thể tải; các chỉ số trải nghiệm còn lại vẫn lấy từ hồ sơ đã xác nhận.
                    </p>
                <?php endif; ?>

                <div class="learner-dashboard-grid">
                    <section class="learner-card learner-skills-card" aria-labelledby="skills-title">
                        <div class="learner-section-heading">
                            <h2 id="skills-title">Hồ sơ kỹ năng</h2>
                            <a href="profile.php">Xem tất cả</a>
                        </div>
                        <div class="learner-skill-list">
                            <?php if (empty($dashboardSkills)): ?>
                                <p class="learner-empty-state">Chưa có dữ liệu kỹ năng.</p>
                            <?php else: ?>
                                <?php foreach ($dashboardSkills as $skill): ?>
                                    <?php $skillScoreClamped = max(0, min(100, (int) ($skill['score'] ?? 0))); ?>
                                    <div class="learner-skill-row">
                                        <span class="learner-skill-row__icon learner-tone--<?= learner_escape($skill['tone']); ?>"><?= learner_icon($skill['icon'], 16); ?></span>
                                        <div class="learner-skill-row__name">
                                            <strong><?= learner_escape($skill['short_name'] ?? $skill['name']); ?></strong>
                                            <span class="learner-skill-row__meta">
                                                <?= learner_escape($skill['level'] ?? 'Đang cập nhật'); ?>
                                                <?php if ($skill['verified'] ?? false): ?>
                                                    <span class="learner-skill-row__verified"><?= learner_icon('check', 11); ?> Đã xác thực</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="learner-progress" role="progressbar" aria-label="<?= learner_escape($skill['name']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $skillScoreClamped; ?>">
                                            <span class="learner-progress--<?= learner_escape($skill['tone']); ?>" style="--learner-progress: <?= $skillScoreClamped; ?>%;"></span>
                                        </div>
                                        <span class="learner-skill-row__score"><?= $skillScoreClamped; ?>/100</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <aside class="learner-card learner-ai-card" aria-labelledby="ai-title">
                        <div class="learner-ai-card__eyebrow">
                            <span class="learner-icon-tile learner-icon-tile--secondary"><?= learner_icon('bot', 22); ?></span>
                            <span>AI gợi ý cho bạn</span>
                        </div>
                        <?php if ($isDatabaseMode ?? false): ?>
                            <?php if ($dashboardAssessmentUnavailable): ?>
                                <h2 id="ai-title">Gợi ý AI tạm thời chưa tải được</h2>
                                <p>Hệ thống chưa thể đọc trạng thái bài đánh giá. Tiến độ của bạn không bị thay đổi; vui lòng thử lại sau.</p>
                                <a class="learner-btn learner-btn--outline" href="discover.php">Xem các bài đánh giá <?= learner_icon('arrow-right', 17); ?></a>
                            <?php elseif ($schoolCredentialData['analysis_completed'] ?? false): ?>
                                <h2 id="ai-title">Đã có lộ trình và thành tích phù hợp</h2>
                                <p>AI đã đối chiếu bốn bài đánh giá với bộ huy hiệu, chứng chỉ chính thức của trường.</p>
                                <a class="learner-btn learner-btn--outline" href="ai-recommendations.php">Xem gợi ý của AI <?= learner_icon('arrow-right', 17); ?></a>
                            <?php elseif ($schoolCredentialData['ready'] ?? false): ?>
                                <h2 id="ai-title">Bạn đã đủ dữ liệu để AI phân tích</h2>
                                <p>Bốn bài đánh giá đã hoàn thành. Hãy tạo lộ trình để xem gợi ý cá nhân hóa.</p>
                                <a class="learner-btn learner-btn--outline" href="ai-recommendations.php">Tạo lộ trình AI <?= learner_icon('arrow-right', 17); ?></a>
                            <?php else: ?>
                                <h2 id="ai-title">Hoàn thành bộ 4 bài đánh giá</h2>
                                <p>Đã hoàn thành <?= learner_escape($schoolCredentialData['completed_test_count'] ?? 0); ?>/4 bài. Kết quả sẽ mở khóa gợi ý thành tích của trường.</p>
                                <a class="learner-btn learner-btn--outline" href="discover.php">Tiếp tục đánh giá <?= learner_icon('arrow-right', 17); ?></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <h2 id="ai-title">Năng khiếu nổi bật: IoT &amp; Drone</h2>
                            <p>Khuyến nghị tham gia nhóm nghiên cứu tự động hóa và cuộc thi sáng tạo kỹ thuật trong tháng tới.</p>
                            <a class="learner-btn learner-btn--outline" href="discover.php">Xem phân tích đầy đủ <?= learner_icon('arrow-right', 17); ?></a>
                        <?php endif; ?>
                    </aside>
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

                <section class="learner-card learner-activities" aria-labelledby="activities-title">
                    <div class="learner-section-heading">
                        <h2 id="activities-title"><?= ($isDatabaseMode ?? false) ? 'Hoạt động đang mở cho bạn' : 'Hoạt động sắp diễn ra'; ?></h2>
                        <a href="./activities.php">Tất cả hoạt động</a>
                    </div>
                    <div class="learner-activity-grid">
                        <?php if ($dashboardOpenActivities === []): ?>
                            <p class="learner-empty-state"><?= ($isDatabaseMode ?? false) ? 'Chưa có hoạt động đang mở phù hợp.' : 'Chưa có hoạt động sắp diễn ra.'; ?></p>
                        <?php endif; ?>
                        <?php foreach ($dashboardOpenActivities as $activity): ?>
                            <?php
                            $activityId = (string) ($activity['route_id'] ?? $activity['id'] ?? '');
                            $activityCover = $dashboardActivityCover($activity['cover_image_url'] ?? '');
                            $activityCategory = $dashboardActivityCategory($activity);
                            $activityStart = ($isDatabaseMode ?? false)
                                ? $dashboardActivityDate($activity['start_at'] ?? null)
                                : (string) ($activity['time'] ?? 'Chưa cập nhật');
                            ?>
                            <article class="learner-activity-card">
                                <?php if ($isDatabaseMode ?? false): ?>
                                    <img class="learner-activity-card__cover" src="<?= learner_escape($activityCover); ?>" alt="<?= learner_escape($activity['cover_image_alt'] ?? $activity['title'] ?? 'Ảnh hoạt động'); ?>" loading="lazy" width="480" height="270">
                                <?php endif; ?>
                                <span class="learner-badge learner-badge--<?= learner_escape($activity['tone'] ?? 'neutral'); ?>"><?= learner_escape($activityCategory); ?></span>
                                <h3><?= learner_escape($activity['title'] ?? 'Hoạt động TalentHub'); ?></h3>
                                <p><?= learner_escape($activityStart); ?> <span aria-hidden="true">•</span> <?= learner_escape($activity['location'] ?? 'Chưa cập nhật'); ?></p>
                                <a class="learner-btn learner-btn--outline learner-btn--block" href="<?= ($isDatabaseMode ?? false) ? 'activity-detail.php?id=' . rawurlencode($activityId) : 'activities.php'; ?>">Xem chi tiết</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php if ($isDatabaseMode ?? false): ?>
                    <section class="learner-card learner-activities" aria-labelledby="confirmed-activities-title">
                        <div class="learner-section-heading">
                            <h2 id="confirmed-activities-title">Hoạt động đã xác nhận</h2>
                            <a href="activity-history.php">Xem lịch sử</a>
                        </div>
                        <div class="learner-activity-grid">
                            <?php if ($dashboardConfirmedActivities === []): ?>
                                <p class="learner-empty-state">Chưa có hoạt động đã xác nhận.</p>
                            <?php endif; ?>
                            <?php foreach ($dashboardConfirmedActivities as $activity): ?>
                                <?php
                                $confirmedCover = $dashboardActivityCover($activity['cover_image_url'] ?? '');
                                $confirmedAlt = trim((string) ($activity['cover_image_alt'] ?? '')) ?: 'Ảnh hoạt động ' . (string) ($activity['title'] ?? 'đã xác nhận');
                                $confirmedDate = $dashboardActivityDate($activity['start_at'] ?? null);
                                $confirmedLocation = trim((string) ($activity['location'] ?? '')) ?: 'Chưa cập nhật';
                                ?>
                                <article class="learner-activity-card">
                                    <img class="learner-activity-card__cover" src="<?= learner_escape($confirmedCover); ?>" alt="<?= learner_escape($confirmedAlt); ?>" loading="lazy" width="480" height="270">
                                    <span class="learner-badge learner-badge--<?= learner_escape($activity['tone'] ?? 'neutral'); ?>"><?= learner_escape($dashboardActivityCategory($activity)); ?></span>
                                    <h3><?= learner_escape($activity['title'] ?? 'Hoạt động đã xác nhận'); ?></h3>
                                    <p><?= learner_escape($confirmedDate); ?> <span aria-hidden="true">•</span> <?= learner_escape($confirmedLocation); ?></p>
                                    <a class="learner-btn learner-btn--outline learner-btn--block" href="activity-detail.php?id=<?= rawurlencode((string) ($activity['id'] ?? '')); ?>">Xem chi tiết</a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>
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
