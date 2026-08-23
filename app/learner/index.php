<?php
/** TalentHub Learner - Overview */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Tổng quan';
$currentRoute = '/app/learner/index.php';
$dashboardSkills = array_slice($skills, 0, 4);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tổng quan hành trình phát triển năng lực của <?= learner_escape($student['name']); ?> trên TalentHub.">
    <title>Tổng quan Học sinh | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-overview" data-learner-source="<?= ($isDatabaseMode ?? false) ? 'database' : 'mock'; ?>">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <section class="learner-welcome" aria-labelledby="welcome-title">
                    <div class="learner-welcome__content">
                        <p class="learner-welcome__streak">
                            <span aria-hidden="true"><?= learner_icon('flame', 19); ?></span>
                            Chuỗi <?= learner_escape($student['streak_days']); ?> ngày liên tiếp
                        </p>
                        <h1 id="welcome-title">Chào mừng trở lại, <?= learner_escape($student['name']); ?> <span aria-hidden="true">👋</span></h1>
                        <?php if ($isDatabaseMode ?? false): ?>
                            <p>Bạn đã ghi nhận <?= learner_escape($dashboardKpis[2]['value'] ?? '0h'); ?> giờ trải nghiệm xác thực trên hệ thống.</p>
                        <?php else: ?>
                            <p>Bạn đã hoàn thành <?= learner_escape($student['experience_hours']); ?> giờ trải nghiệm tháng này — vượt 28% so với tháng trước.</p>
                        <?php endif; ?>
                        <div class="learner-welcome__actions">
                            <a class="learner-btn learner-btn--primary" href="./activities.php">
                                Khám phá hoạt động <?= learner_icon('arrow-right', 18); ?>
                            </a>
                            <a class="learner-btn learner-btn--secondary" href="discover.php">Làm test năng khiếu</a>
                        </div>
                    </div>

                    <div class="learner-welcome__visual" aria-hidden="true">
                        <svg viewBox="0 0 430 235" role="presentation">
                            <rect x="310" y="22" width="92" height="146" rx="8" fill="var(--surface)" stroke="var(--border)"/>
                            <path d="M356 22v146M310 87h92" stroke="var(--border)"/>
                            <circle cx="205" cy="60" r="39" fill="var(--primary-light)"/>
                            <path d="M182 55c2-25 45-30 51 0l-4 21h-42l-5-21Z" fill="var(--text-primary)"/>
                            <circle cx="207" cy="67" r="25" fill="var(--primary-light)"/>
                            <path d="M198 67h2M216 67h2" stroke="var(--text-primary)" stroke-width="3" stroke-linecap="round"/>
                            <path d="M201 78c5 4 10 4 15 0" fill="none" stroke="var(--primary)" stroke-width="2" stroke-linecap="round"/>
                            <path d="M158 134c4-36 26-53 50-53 29 0 56 23 60 58v58H151l7-63Z" fill="var(--primary)"/>
                            <path d="M168 116c-28-20-36-37-25-47 8-7 18 6 25 19" fill="none" stroke="var(--primary)" stroke-width="15" stroke-linecap="round"/>
                            <path d="M141 68c-7-13-5-24 1-29M146 67c0-14 5-24 10-27M151 70c5-12 12-19 18-20" fill="none" stroke="var(--text-primary)" stroke-width="3" stroke-linecap="round"/>
                            <rect x="75" y="151" width="128" height="67" rx="7" fill="var(--text-secondary)"/>
                            <rect x="86" y="160" width="106" height="49" rx="3" fill="var(--background)"/>
                            <path d="m139 176 4 8 9 1-7 6 2 9-8-5-8 5 2-9-7-6 9-1 4-8Z" fill="var(--border)"/>
                            <rect x="54" y="218" width="331" height="10" rx="5" fill="var(--primary-light)"/>
                            <path d="M31 211h53v7H31z" fill="var(--warning)"/>
                            <path d="M337 187h62v31h-62z" fill="var(--secondary)"/>
                            <path d="M330 181h69v8h-69zM343 171h55v8h-55z" fill="var(--primary)"/>
                            <path d="M45 198c-4-24 3-40 18-48 3 22-1 38-18 48Z" fill="var(--accent)"/>
                            <path d="M45 198c-19-12-25-28-19-46 19 12 26 27 19 46Z" fill="var(--success)" opacity=".7"/>
                            <rect x="35" y="197" width="23" height="21" rx="4" fill="var(--primary-light)" stroke="var(--primary)"/>
                        </svg>
                    </div>
                </section>

                <section class="learner-kpi-grid" aria-label="Chỉ số tổng quan">
                    <?php foreach ($dashboardKpis as $kpi): ?>
                        <article class="learner-card learner-kpi-card">
                            <div class="learner-kpi-card__top">
                                <span class="learner-icon-tile learner-icon-tile--secondary"><?= learner_icon($kpi['icon'], 22); ?></span>
                                <span class="learner-kpi-card__change"><?= learner_escape($kpi['change']); ?></span>
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
                                        <span class="learner-skill-row__name"><?= learner_escape($skill['short_name'] ?? $skill['name']); ?></span>
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
                            <h2 id="ai-title">Gợi ý AI chưa có dữ liệu</h2>
                            <p>Kết quả chỉ hiển thị khi dữ liệu đã được xác minh và chính sách hiển thị AI cho phép.</p>
                            <a class="learner-btn learner-btn--outline" href="ai-recommendations.php">Xem trạng thái AI <?= learner_icon('arrow-right', 17); ?></a>
                        <?php else: ?>
                            <h2 id="ai-title">Năng khiếu nổi bật: IoT &amp; Drone</h2>
                            <p>Khuyến nghị tham gia nhóm nghiên cứu tự động hóa và cuộc thi sáng tạo kỹ thuật trong tháng tới.</p>
                            <a class="learner-btn learner-btn--outline" href="discover.php">Xem phân tích đầy đủ <?= learner_icon('arrow-right', 17); ?></a>
                        <?php endif; ?>
                    </aside>
                </div>

                <section class="learner-card learner-activities" aria-labelledby="activities-title">
                    <div class="learner-section-heading">
                        <h2 id="activities-title"><?= ($isDatabaseMode ?? false) ? 'Hoạt động đã xác nhận' : 'Hoạt động sắp diễn ra'; ?></h2>
                        <a href="./activities.php">Tất cả hoạt động</a>
                    </div>
                    <div class="learner-activity-grid">
                        <?php if ($activities === []): ?>
                            <p class="learner-empty-state">Chưa có hoạt động đã xác nhận.</p>
                        <?php endif; ?>
                        <?php foreach ($activities as $activity): ?>
                            <article class="learner-activity-card">
                                <span class="learner-badge learner-badge--<?= learner_escape($activity['tone']); ?>"><?= learner_escape($activity['category']); ?></span>
                                <h3><?= learner_escape($activity['title']); ?></h3>
                                <p><?= learner_escape($activity['time']); ?> <span aria-hidden="true">•</span> <?= learner_escape($activity['location']); ?></p>
                                <a class="learner-btn learner-btn--outline learner-btn--block" href="activities.php">Xem chi tiết</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
