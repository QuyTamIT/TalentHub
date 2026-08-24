<?php
/** TalentHub Learner - Aptitude discovery */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Khám phá năng khiếu';
$currentRoute = '/app/learner/discover.php';
$onboarding = $GLOBALS['learner_page_context']['onboarding'] ?? ['required' => false];
$onboardingLabels = [
    'holland' => 'Holland',
    'mbti' => 'MBTI',
    'disc' => 'DISC',
    'multiple_intelligence' => 'Đa trí thông minh',
];
$onboardingStateLabels = [
    'completed' => 'Đã hoàn thành',
    'next' => 'Tiếp theo',
    'in_progress' => 'Đang làm',
    'locked' => 'Chưa mở',
];

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
                <?php if (($onboarding['required'] ?? false) === true): ?>
                <section class="learner-card learner-onboarding-progress" data-onboarding-progress aria-labelledby="onboarding-progress-title">
                    <div class="learner-onboarding-progress__heading">
                        <div>
                            <span class="learner-onboarding-progress__eyebrow">Đánh giá ban đầu bắt buộc</span>
                            <h1 id="onboarding-progress-title">Tiến độ của bạn</h1>
                            <p>
                                Đã hoàn thành
                                <strong><?= learner_escape($onboarding['completed_count'] ?? 0); ?>/<?= learner_escape($onboarding['required_count'] ?? 4); ?></strong>
                                bài đánh giá. Mỗi câu trả lời được tự động lưu.
                            </p>
                        </div>
                        <span class="learner-onboarding-progress__count" aria-label="Số bài đã hoàn thành">
                            <?= learner_escape($onboarding['completed_count'] ?? 0); ?>/<?= learner_escape($onboarding['required_count'] ?? 4); ?>
                        </span>
                    </div>

                    <?php if (($onboarding['status'] ?? '') === 'completed' && ($_GET['onboarding'] ?? '') === 'completed'): ?>
                    <div class="learner-onboarding-progress__success" role="status">
                        <strong>Bạn đã hoàn thành đủ 4/4 bài đánh giá.</strong>
                        <span>TalentHub đã mở toàn bộ không gian sinh viên cho tài khoản của bạn.</span>
                        <a class="learner-btn learner-btn--primary" href="<?= learner_escape(app_href('/app/learner/index.php')); ?>">Về tổng quan</a>
                    </div>
                    <?php endif; ?>

                    <ol class="learner-onboarding-progress__list">
                        <?php foreach (($onboarding['items'] ?? []) as $position => $item): ?>
                            <?php
                            $code = is_string($item['code'] ?? null) ? $item['code'] : '';
                            $state = is_string($item['state'] ?? null) ? $item['state'] : 'locked';
                            $label = $onboardingLabels[$code] ?? 'Bài đánh giá';
                            $stateLabel = $onboardingStateLabels[$state] ?? 'Chưa mở';
                            $isAvailable = in_array($state, ['completed', 'next', 'in_progress'], true);
                            $itemUrl = $state === 'completed'
                                ? '/app/learner/assessment-result.php?code=' . rawurlencode($code)
                                : '/app/learner/assessment.php?code=' . rawurlencode($code);
                            ?>
                            <li class="learner-onboarding-progress__item is-<?= learner_escape($state); ?>">
                                <span class="learner-onboarding-progress__position" aria-hidden="true"><?= learner_escape($position + 1); ?></span>
                                <span class="learner-onboarding-progress__copy">
                                    <strong><?= learner_escape($label); ?></strong>
                                    <small><?= learner_escape($stateLabel); ?></small>
                                </span>
                                <?php if ($isAvailable): ?>
                                    <a class="learner-btn learner-btn--outline" href="<?= learner_escape(app_href($itemUrl)); ?>">
                                        <?= $state === 'completed' ? 'Xem kết quả' : 'Tiếp tục'; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="learner-onboarding-progress__locked" aria-label="Bài đánh giá chưa mở">Khóa</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>

                    <?php if (($onboarding['status'] ?? '') === 'accepted'): ?>
                    <a class="learner-onboarding-progress__logout" href="<?= learner_escape(app_href('/logout.php')); ?>">Đăng xuất và tiếp tục sau</a>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <section class="learner-discover-hero" aria-labelledby="learner-discover-page-title">
                    <div class="learner-discover-hero__copy">
                        <span class="learner-discover-hero__eyebrow">Hiểu bản thân hơn</span>
                        <h1 id="learner-discover-page-title">Khám phá năng khiếu</h1>
                        <p>Bộ 4 bài đánh giá giúp bạn hiểu bản thân và định hướng tương lai.</p>
                    </div>
                    <div class="learner-discover-progress" aria-label="Tiến độ khám phá năng khiếu">
                        <div class="learner-discover-progress__item">
                            <span class="learner-discover-progress__icon learner-discover-progress__icon--success" aria-hidden="true"><?= learner_icon('check', 20); ?></span>
                            <div>
                                <span>Đã hoàn thành</span>
                                <strong data-discovery-completed-count>0/4 bài đánh giá</strong>
                            </div>
                        </div>
                        <div class="learner-discover-progress__item">
                            <span class="learner-discover-progress__icon" aria-hidden="true"><?= learner_icon('calendar', 20); ?></span>
                            <div>
                                <span>Cập nhật gần nhất</span>
                                <strong data-discovery-latest-date>Chưa có dữ liệu</strong>
                            </div>
                        </div>
                        <div class="learner-progress learner-discover-progress__bar" role="progressbar" aria-label="Tỷ lệ hoàn thành bài đánh giá" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-discovery-progress>
                            <span data-discovery-progress-bar style="--learner-progress: 0%;"></span>
                        </div>
                    </div>
                </section>

                <section class="learner-assessment-grid" aria-label="Các bài đánh giá năng khiếu" data-assessment-catalog data-catalog-endpoint="/app/learner/api/v1/assessments.php">
                    <div class="learner-card learner-assessment-state" data-catalog-loading>
                        <span class="learner-assessment-spinner" aria-hidden="true"></span>
                        <p>Đang tải danh mục bài đánh giá...</p>
                    </div>
                    <div class="learner-card learner-empty-catalog" data-empty-catalog hidden>
                        <p>Chưa có phiên bản được duyệt. Vui lòng quay lại sau.</p>
                    </div>
                    <div class="learner-card learner-assessment-state learner-assessment-state--error" data-catalog-error role="alert" hidden>
                        <?= learner_icon('alert-triangle', 22); ?>
                        <div>
                            <p>Không thể tải danh mục bài đánh giá từ máy chủ.</p>
                            <button class="learner-btn learner-btn--outline" type="button" data-catalog-retry>Thử tải lại</button>
                        </div>
                    </div>
                    <div class="learner-card learner-assessment-state" data-catalog-band-confirmation role="group" aria-labelledby="catalog-band-title" hidden>
                        <?= learner_icon('graduation-cap', 22); ?>
                        <div>
                            <h2 id="catalog-band-title">Chọn cấp học của bạn</h2>
                            <p>Hãy xác nhận cấp học để tải đúng bộ bài đánh giá.</p>
                            <span id="catalog-band-options-title" class="learner-visually-hidden">Cấp học hiện tại</span>
                            <div class="learner-band-options" role="radiogroup" aria-labelledby="catalog-band-options-title">
                                <label class="learner-band-option">
                                    <input type="radio" name="catalog_education_band" value="middle">
                                    <span><strong>Trung học cơ sở</strong> (Lớp 6 – 9)</span>
                                </label>
                                <label class="learner-band-option">
                                    <input type="radio" name="catalog_education_band" value="high">
                                    <span><strong>Trung học phổ thông</strong> (Lớp 10 – 12)</span>
                                </label>
                                <label class="learner-band-option">
                                    <input type="radio" name="catalog_education_band" value="college">
                                    <span><strong>Đại học / Cao đẳng</strong></span>
                                </label>
                            </div>
                            <p class="learner-form-error" role="alert" data-catalog-band-error hidden>Vui lòng chọn một cấp học để tiếp tục.</p>
                            <button class="learner-btn learner-btn--primary" type="button" data-catalog-band-confirm>Xác nhận cấp học</button>
                        </div>
                    </div>
                    <div class="learner-card learner-assessment-state" data-catalog-history-warning role="status" hidden>
                        <?= learner_icon('info', 22); ?>
                        <div>
                            <p>Danh mục vẫn dùng được, nhưng kết quả trước đây tạm thời chưa tải được.</p>
                            <button class="learner-btn learner-btn--outline" type="button" data-catalog-history-retry>Thử tải lại kết quả</button>
                        </div>
                    </div>
                    <div class="learner-assessment-grid__cards" data-catalog-cards></div>
                </section>

                <div class="learner-discovery-grid">
                    <section class="learner-card learner-radar-card" aria-labelledby="radar-title">
                        <div class="learner-discover-section-heading">
                            <h2 id="radar-title">Bản đồ năng khiếu</h2>
                            <span><i aria-hidden="true"></i> Điểm nổi trội</span>
                        </div>

                        <div class="learner-discovery-talent-list" data-discovery-talents aria-label="Điểm đa trí thông minh" aria-live="polite"></div>
                    </section>

                    <section class="learner-card learner-directions" aria-labelledby="directions-title">
                        <div class="learner-directions__heading">
                            <span>Kết quả tổng hợp</span>
                            <h2 id="directions-title">Định hướng của bạn</h2>
                        </div>
                        <div class="learner-direction-list" data-discovery-career aria-live="polite"></div>
                        <a class="learner-btn learner-btn--primary learner-directions__action" href="ecosystem.php">Khám phá cơ hội phù hợp <?= learner_icon('arrow-right', 17); ?></a>
                    </section>
                </div>

                <section class="learner-discover-next" aria-labelledby="discover-next-title">
                    <div class="learner-discover-next__heading">
                        <h2 id="discover-next-title">Gợi ý bước tiếp theo</h2>
                        <p>Tiếp tục phát triển từ kết quả đánh giá đã lưu của bạn.</p>
                    </div>
                    <div class="learner-discover-next__grid">
                        <a class="learner-card learner-discover-next__card" href="activities.php">
                            <span class="learner-discover-next__icon learner-discover-next__icon--blue" aria-hidden="true"><?= learner_icon('users', 22); ?></span>
                            <span><strong>Tham gia hoạt động</strong><small>Rèn luyện kỹ năng qua trải nghiệm thực tế.</small></span>
                            <?= learner_icon('arrow-right', 19); ?>
                        </a>
                        <a class="learner-card learner-discover-next__card" href="ecosystem.php">
                            <span class="learner-discover-next__icon" aria-hidden="true"><?= learner_icon('briefcase', 22); ?></span>
                            <span><strong>Khám phá ngành học</strong><small>Xem trường học và cơ hội phù hợp.</small></span>
                            <?= learner_icon('arrow-right', 19); ?>
                        </a>
                        <a class="learner-card learner-discover-next__card" href="profile.php">
                            <span class="learner-discover-next__icon learner-discover-next__icon--green" aria-hidden="true"><?= learner_icon('user', 22); ?></span>
                            <span><strong>Hoàn thiện hồ sơ</strong><small>Bổ sung điểm mạnh vào hồ sơ năng lực.</small></span>
                            <?= learner_icon('arrow-right', 19); ?>
                        </a>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script id="learner-session-boot" type="application/json"><?= json_encode(['csrfToken' => $GLOBALS['learner_page_context']['csrfToken'] ?? ''], JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
    <script src="../../assets/js/learner-assessment.js"></script>
</body>
</html>
