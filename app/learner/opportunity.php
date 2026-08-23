<?php
/** TalentHub Learner - Opportunity detail and application */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/ecosystem-data.php';

$pageTitle = 'Chi tiết cơ hội';
$currentRoute = '/app/learner/ecosystem.php';
$opportunityType = $_GET['type'] ?? '';
$opportunityId = $_GET['id'] ?? '';
$opportunity = learner_ecosystem_opportunity($opportunityType, $opportunityId);
$partner = $opportunity ? learner_ecosystem_partner($opportunity['partner_type'], $opportunity['partner_id']) : null;
$canApply = $opportunity ? learner_ecosystem_can_apply($opportunity) : false;
$deadlineLabel = $opportunity ? (new DateTimeImmutable($opportunity['deadline']))->format('d/m/Y') : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Chi tiết cơ hội học tập và nghề nghiệp dành cho học sinh, sinh viên trên TalentHub.">
    <title><?= learner_escape($opportunity['title'] ?? 'Không tìm thấy cơ hội'); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-opportunity" data-opportunity-page data-opportunity-id="<?= learner_escape((string) ($opportunity['id'] ?? '')); ?>">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <nav class="learner-breadcrumbs" aria-label="Đường dẫn">
                    <a href="ecosystem.php">Hệ sinh thái &amp; Cơ hội</a>
                    <span aria-hidden="true">/</span>
                    <a href="ecosystem.php?tab=opportunities">Cơ hội</a>
                    <span aria-hidden="true">/</span>
                    <span><?= learner_escape($opportunity['title'] ?? 'Không tìm thấy'); ?></span>
                </nav>

                <?php if (!$opportunity): ?>
                    <section class="learner-card learner-not-found" aria-labelledby="opportunity-not-found-title">
                        <span><?= learner_icon('briefcase', 34); ?></span>
                        <h1 id="opportunity-not-found-title">Không tìm thấy cơ hội</h1>
                        <p>Cơ hội có thể đã được gỡ hoặc liên kết không còn hiệu lực.</p>
                        <a class="learner-btn learner-btn--primary" href="ecosystem.php?tab=opportunities">Xem các cơ hội khác</a>
                    </section>
                <?php else: ?>
                    <section class="learner-opportunity-hero learner-card">
                        <div class="learner-opportunity-hero__top">
                            <span class="learner-partner-logo <?= $opportunity['partner_type'] === 'enterprise' ? 'learner-partner-logo--enterprise' : 'learner-partner-logo--school'; ?>"><?= learner_escape($partner['logo_text'] ?? 'TH'); ?></span>
                            <div>
                                <div class="learner-opportunity-hero__badges">
                                    <span class="learner-badge <?= $opportunity['partner_type'] === 'enterprise' ? 'learner-badge--primary' : 'learner-badge--secondary'; ?>"><?= learner_escape($opportunity['partner_type'] === 'enterprise' ? 'Thực tập doanh nghiệp' : 'Cơ hội trường học'); ?></span>
                                    <span class="learner-status-dot learner-status-dot--<?= learner_escape($opportunity['status']); ?>"><?= learner_escape($opportunity['status_label']); ?></span>
                                </div>
                                <h1><?= learner_escape($opportunity['title']); ?></h1>
                                <a href="partner.php?type=<?= learner_escape($opportunity['partner_type']); ?>&amp;id=<?= learner_escape($opportunity['partner_id']); ?>"><?= learner_escape($opportunity['partner_name']); ?> <?= learner_icon('external-link', 14); ?></a>
                            </div>
                        </div>
                        <div class="learner-opportunity-facts">
                            <div><?= learner_icon('map-pin', 19); ?><span>Địa điểm<strong><?= learner_escape($opportunity['location']); ?></strong></span></div>
                            <div><?= learner_icon('clock', 19); ?><span>Thời lượng<strong><?= learner_escape($opportunity['duration']); ?></strong></span></div>
                            <div><?= learner_icon('users', 19); ?><span>Số lượng<strong><?= learner_escape($opportunity['slots']); ?> vị trí</strong></span></div>
                            <div><?= learner_icon('calendar', 19); ?><span>Hạn đăng ký<strong><?= learner_escape($deadlineLabel); ?></strong></span></div>
                        </div>
                    </section>

                    <div class="learner-opportunity-layout">
                        <div class="learner-opportunity-main">
                            <section class="learner-card learner-content-section" aria-labelledby="opportunity-description-title">
                                <h2 id="opportunity-description-title">Mô tả cơ hội</h2>
                                <p><?= learner_escape($opportunity['description']); ?></p>

                                <h2>Yêu cầu</h2>
                                <ul class="learner-check-list">
                                    <?php foreach ($opportunity['requirements'] as $requirement): ?>
                                        <li><?= learner_icon('check', 16); ?> <?= learner_escape($requirement); ?></li>
                                    <?php endforeach; ?>
                                </ul>

                                <h2>Kỹ năng &amp; nội dung liên quan</h2>
                                <div class="learner-chip-list learner-chip-list--large">
                                    <?php foreach ($opportunity['skills'] as $skill): ?><span><?= learner_escape($skill); ?></span><?php endforeach; ?>
                                </div>

                                <h2>Quyền lợi</h2>
                                <div class="learner-benefit-box"><?= learner_icon('sparkles', 21); ?><p><?= learner_escape($opportunity['benefits']); ?></p></div>
                            </section>
                        </div>

                        <aside class="learner-card learner-apply-card" aria-labelledby="apply-card-title">
                            <span class="learner-apply-card__icon"><?= learner_icon('file-text', 24); ?></span>
                            <h2 id="apply-card-title"><?= learner_escape($canApply ? 'Sẵn sàng ứng tuyển?' : 'Cơ hội đã đóng'); ?></h2>
                            <p><?= learner_escape($canApply ? 'Dùng hồ sơ TalentHub hiện tại và gửi lời nhắn ngắn tới đối tác.' : 'Bạn vẫn có thể xem thông tin, nhưng không thể gửi hồ sơ mới.'); ?></p>
                            <button class="learner-btn learner-btn--primary learner-btn--block" type="button" data-open-modal="learner-application-modal" <?= !$canApply ? 'disabled' : ''; ?>><?= learner_icon('send', 17); ?> Ứng tuyển ngay</button>
                            <button class="learner-btn learner-btn--outline learner-btn--block" type="button" disabled title="Tính năng lưu cơ hội chưa khả dụng"><?= learner_icon('bookmark', 17); ?> Lưu cơ hội — chưa hỗ trợ</button>
                            <div class="learner-apply-card__deadline"><span>Hạn đăng ký</span><strong><?= learner_escape($deadlineLabel); ?></strong></div>
                            <p class="learner-apply-card__privacy"><?= learner_icon('info', 15); ?> Chỉ thông tin trong hồ sơ ứng tuyển được chia sẻ với đối tác.</p>
                        </aside>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php if ($opportunity && $canApply): ?>
        <div class="learner-modal" id="learner-application-modal" hidden>
            <button class="learner-modal__backdrop" type="button" data-close-modal aria-label="Đóng biểu mẫu ứng tuyển"></button>
            <section class="learner-modal__dialog learner-application-modal" role="dialog" aria-modal="true" aria-labelledby="application-modal-title">
                <div class="learner-modal__header">
                    <div>
                        <span class="learner-modal__eyebrow">Ứng tuyển cơ hội</span>
                        <h2 id="application-modal-title"><?= learner_escape($opportunity['title']); ?></h2>
                        <p><?= learner_escape($opportunity['partner_name']); ?></p>
                    </div>
                    <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng"><?= learner_icon('x', 21); ?></button>
                </div>

                <form data-application-form novalidate>
                    <div class="learner-profile-preview">
                        <span class="learner-avatar"><?= learner_escape($student['initials']); ?></span>
                        <div><strong><?= learner_escape($student['name']); ?></strong><span><?= learner_escape($student['class'] . ' · ' . $student['school']); ?></span></div>
                        <span class="learner-verified-pill"><?= learner_icon('check', 14); ?> Hồ sơ đã xác minh</span>
                    </div>
                    <div class="learner-form-field">
                        <label for="application-message">Lời nhắn tới đối tác <span>Không bắt buộc</span></label>
                        <textarea id="application-message" name="message" rows="5" maxlength="500" placeholder="Giới thiệu ngắn về động lực và điểm phù hợp của bạn..." data-application-message></textarea>
                        <small><span data-application-message-count>0</span>/500 ký tự</small>
                    </div>
                    <label class="learner-consent-field">
                        <input type="checkbox" name="consent" value="yes" data-application-consent>
                        <span>Tôi đồng ý chia sẻ hồ sơ TalentHub và thông tin liên hệ với <?= learner_escape($opportunity['partner_name']); ?> để phục vụ quá trình xét duyệt.</span>
                    </label>
                    <p class="learner-form-error" role="alert" tabindex="-1" hidden data-application-error></p>
                    <div class="learner-data-note">
                        <?= learner_icon('info', 17); ?>
                        <p>Hệ thống chỉ gửi ảnh chụp hồ sơ tối thiểu sau khi bạn xác nhận đồng ý.</p>
                    </div>
                    <div class="learner-modal__actions">
                        <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Hủy</button>
                        <button class="learner-btn learner-btn--primary" type="submit"><?= learner_icon('send', 17); ?> Xác nhận ứng tuyển</button>
                    </div>
                </form>
            </section>
        </div>
    <?php endif; ?>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
