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
$hasApplied = !empty($opportunity['application_id']);
$studentName = !empty($student['name']) ? $student['name'] : 'Học viên';
$studentInitials = !empty($student['initials']) ? $student['initials'] : (mb_strtoupper(mb_substr($studentName, 0, 1)));
$studentAvatarUrl = !empty($student['avatar_url']) ? $student['avatar_url'] : (!empty($student['avatarUrl']) ? $student['avatarUrl'] : null);
$studentSchool = !empty($student['school']) ? $student['school'] : 'Chưa cập nhật trường';
$studentClass = !empty($student['class']) ? $student['class'] : 'Chưa cập nhật lớp';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Chi tiết cơ hội học tập và nghề nghiệp dành cho học sinh, sinh viên trên TalentHub.">
    <title><?= learner_escape($opportunity['title'] ?? 'Không tìm thấy cơ hội'); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
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

                        <aside class="learner-card learner-apply-card" aria-labelledby="apply-card-title" data-apply-card>
                            <?php if ($hasApplied): ?>
                                <span class="learner-apply-card__icon" style="background: #ecfdf5; color: #16a34a;"><?= learner_icon('check-circle', 28); ?></span>
                                <h2 id="apply-card-title">Bạn đã nộp hồ sơ</h2>
                                <p>Đơn ứng tuyển của bạn đã được chuyển tới <?= learner_escape($opportunity['partner_name']); ?>. Bạn có thể theo dõi tiến độ xét duyệt tại Hồ sơ ứng tuyển.</p>
                                <a class="learner-btn learner-btn--primary learner-btn--block" href="ecosystem.php?view=applications#applications-tracker-title"><?= learner_icon('file-text', 17); ?> Xem hồ sơ ứng tuyển</a>
                                <div class="learner-apply-card__deadline"><span>Hạn đăng ký</span><strong><?= learner_escape($deadlineLabel); ?></strong></div>
                                <p class="learner-apply-card__privacy"><?= learner_icon('info', 15); ?> Trạng thái: <strong>Đang chờ xét duyệt</strong></p>
                            <?php else: ?>
                                <span class="learner-apply-card__icon"><?= learner_icon('file-text', 24); ?></span>
                                <h2 id="apply-card-title"><?= learner_escape($canApply ? 'Sẵn sàng ứng tuyển?' : 'Cơ hội đã đóng'); ?></h2>
                                <p><?= learner_escape($canApply ? 'Dùng hồ sơ TalentHub hiện tại và gửi lời nhắn ngắn tới đối tác.' : 'Bạn vẫn có thể xem thông tin, nhưng không thể gửi hồ sơ mới.'); ?></p>
                                <button class="learner-btn learner-btn--primary learner-btn--block" type="button" data-open-modal="learner-application-modal" <?= !$canApply ? 'disabled' : ''; ?>><?= learner_icon('send', 17); ?> Ứng tuyển ngay</button>
                                <button class="learner-btn learner-btn--outline learner-btn--block" type="button" disabled title="Tính năng lưu cơ hội chưa khả dụng"><?= learner_icon('bookmark', 17); ?> Lưu cơ hội — chưa hỗ trợ</button>
                                <div class="learner-apply-card__deadline"><span>Hạn đăng ký</span><strong><?= learner_escape($deadlineLabel); ?></strong></div>
                                <p class="learner-apply-card__privacy"><?= learner_icon('info', 15); ?> Chỉ thông tin trong hồ sơ ứng tuyển được chia sẻ với đối tác.</p>
                            <?php endif; ?>
                        </aside>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php if ($opportunity && $canApply): ?>
        <div class="learner-modal" id="learner-application-modal" hidden>
            <button class="learner-modal__backdrop" type="button" data-close-modal aria-label="Đóng biểu mẫu ứng tuyển"></button>
            <section class="learner-modal__dialog learner-application-modal" role="dialog" aria-modal="true" aria-labelledby="application-modal-title" style="max-width: 580px;">
                <div class="learner-modal__header" style="border-bottom: 1px solid var(--border); padding-bottom: 14px;">
                    <div>
                        <span class="learner-modal__eyebrow" style="color: var(--primary); font-weight: 700; font-size: 0.76rem; letter-spacing: 0.04em;">ỨNG TUYỂN VỊ TRÍ THỰC TẬP</span>
                        <h2 id="application-modal-title" style="margin: 4px 0 2px; font-size: 1.22rem;"><?= learner_escape($opportunity['title']); ?></h2>
                        <p style="color: var(--text-secondary); font-size: 0.84rem; margin: 0;"><?= learner_escape($opportunity['partner_name']); ?> · <?= learner_escape($opportunity['location']); ?></p>
                    </div>
                    <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng"><?= learner_icon('x', 21); ?></button>
                </div>

                <form data-application-form novalidate style="margin-top: 16px;">
                    <!-- Candidate Profile Card with Real Profile Avatar -->
                    <div class="learner-profile-preview" style="display: flex; align-items: center; gap: 14px; padding: 14px 16px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid var(--border); border-radius: 12px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                        <div class="learner-modal-avatar" style="width: 52px; height: 52px; border-radius: 50%; overflow: hidden; flex-shrink: 0; border: 2.5px solid var(--primary); box-shadow: 0 3px 10px rgba(249, 115, 22, 0.25); background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 1.15rem;">
                            <?php if (!empty($studentAvatarUrl)): ?>
                                <img src="<?= learner_escape($studentAvatarUrl); ?>" alt="<?= learner_escape($studentName); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                            <?php else: ?>
                                <span><?= learner_escape($studentInitials); ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <strong style="font-size: 1rem; color: var(--text-primary); font-weight: 700;"><?= learner_escape($studentName); ?></strong>
                                <span class="learner-verified-pill" style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 999px; background: #ecfdf5; color: #059669; font-size: 0.72rem; font-weight: 600;"><?= learner_icon('check', 13); ?> Hồ sơ TalentHub</span>
                            </div>
                            <span style="display: block; color: var(--text-secondary); font-size: 0.8rem; margin-top: 3px;"><?= learner_escape($studentClass . ' · ' . $studentSchool); ?></span>
                        </div>
                    </div>

                    <!-- Quick Facts Strip -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 14px; padding: 12px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; font-size: 0.76rem;">
                        <div>
                            <span style="color: var(--text-secondary); display: block;">Hình thức</span>
                            <strong style="color: var(--text-primary);"><?= learner_escape($opportunity['work_type'] ?? 'Toàn thời gian'); ?></strong>
                        </div>
                        <div>
                            <span style="color: var(--text-secondary); display: block;">Thời lượng</span>
                            <strong style="color: var(--text-primary);"><?= learner_escape($opportunity['duration'] ?? '3 - 6 tháng'); ?></strong>
                        </div>
                        <div>
                            <span style="color: var(--text-secondary); display: block;">Chỉ tiêu</span>
                            <strong style="color: var(--text-primary);"><?= learner_escape($opportunity['slots'] ?? 1); ?> vị trí</strong>
                        </div>
                    </div>

                    <!-- Message Field -->
                    <div class="learner-form-field" style="margin-top: 14px;">
                        <label for="application-message" style="display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 0.82rem;">
                            <span>Lời nhắn gửi tới nhà tuyển dụng</span>
                            <span style="color: var(--text-secondary); font-size: 0.72rem; font-weight: 400;">Không bắt buộc</span>
                        </label>
                        <textarea id="application-message" name="message" rows="4" maxlength="500" placeholder="Giới thiệu ngắn về mục tiêu thực tập, thế mạnh kỹ năng và lý do bạn muốn đồng hành cùng <?= learner_escape($opportunity['partner_name']); ?>..." data-application-message style="margin-top: 6px; border-radius: 8px; font-size: 0.84rem;"></textarea>
                        <small style="color: var(--text-secondary); font-size: 0.72rem; text-align: right; display: block; margin-top: 4px;"><span data-application-message-count>0</span>/500 ký tự</small>
                    </div>

                    <!-- Consent Field -->
                    <label class="learner-consent-field" style="display: flex; align-items: flex-start; gap: 10px; margin-top: 12px; padding: 12px; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; cursor: pointer;">
                        <input type="checkbox" name="consent" value="yes" checked data-application-consent style="margin-top: 3px; accent-color: var(--primary); width: 17px; height: 17px; flex-shrink: 0;">
                        <span style="color: #92400e; font-size: 0.76rem; line-height: 1.5;">Tôi đồng ý chia sẻ hồ sơ năng lực TalentHub và thông tin liên hệ với <strong><?= learner_escape($opportunity['partner_name']); ?></strong> để phục vụ quá trình xét duyệt ứng tuyển.</span>
                    </label>

                    <p class="learner-form-error" role="alert" tabindex="-1" hidden data-application-error style="margin-top: 10px; padding: 8px 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #dc2626; font-size: 0.78rem;"></p>

                    <div class="learner-modal__actions" style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Hủy</button>
                        <button class="learner-btn learner-btn--primary" type="submit" data-application-submit>
                            <span class="btn-spinner" hidden style="margin-right: 6px; animation: learner-spin 0.8s linear infinite; display: inline-block;"><?= learner_icon('loader', 16); ?></span>
                            <span class="btn-icon"><?= learner_icon('send', 17); ?></span>
                            <span class="btn-text">Xác nhận ứng tuyển</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>
    <?php endif; ?>

    <!-- Success Confirmation Modal -->
    <div class="learner-modal" id="learner-application-success-modal" hidden>
        <button class="learner-modal__backdrop" type="button" data-close-modal aria-label="Đóng"></button>
        <section class="learner-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="success-modal-title" style="max-width: 520px; text-align: center; padding: 32px 24px;">
            <div style="width: 68px; height: 68px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; box-shadow: 0 4px 14px rgba(22, 163, 74, 0.2);">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h2 id="success-modal-title" style="font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">Xin ứng tuyển thành công!</h2>
            <p style="color: var(--text-secondary); font-size: 0.88rem; line-height: 1.6; margin-bottom: 20px;">
                Đơn của bạn đã được gửi cho bên doanh nghiệp <strong><?= learner_escape($opportunity['partner_name'] ?? ''); ?></strong> thành công. Nhà tuyển dụng sẽ xem xét hồ sơ và liên hệ với bạn trong thời gian sớm nhất.
            </p>
            <div style="background: var(--background); border: 1px solid var(--border); border-radius: 10px; padding: 14px; text-align: left; margin-bottom: 24px; font-size: 0.82rem;">
                <div style="margin-bottom: 6px;"><span style="color: var(--text-secondary);">Vị trí ứng tuyển:</span> <strong style="color: var(--text-primary);"><?= learner_escape($opportunity['title'] ?? ''); ?></strong></div>
                <div style="margin-bottom: 6px;"><span style="color: var(--text-secondary);">Doanh nghiệp tiếp nhận:</span> <strong style="color: var(--text-primary);"><?= learner_escape($opportunity['partner_name'] ?? ''); ?></strong></div>
                <div><span style="color: var(--text-secondary);">Trạng thái hồ sơ:</span> <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; background: #eff6ff; color: #2563eb; font-weight: 600; font-size: 0.75rem;">Đã nộp · Chờ xét duyệt</span></div>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <a href="ecosystem.php?view=applications#applications-tracker-title" class="learner-btn learner-btn--primary" style="flex: 1;">
                    <?= learner_icon('file-text', 17); ?> Xem hồ sơ ứng tuyển của bạn
                </a>
                <button type="button" class="learner-btn learner-btn--secondary" data-close-modal style="flex: 0 0 90px;">Đóng</button>
            </div>
        </section>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
