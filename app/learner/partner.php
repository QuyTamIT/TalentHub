<?php
/** TalentHub Learner - Partner detail */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/ecosystem-data.php';

$pageTitle = 'Chi tiết đối tác';
$currentRoute = '/app/learner/ecosystem.php';
$partnerType = $_GET['type'] ?? '';
$partnerId = $_GET['id'] ?? '';
$partner = learner_ecosystem_partner($partnerType, $partnerId);
$isEnterprise = $partner && $partner['type'] === 'enterprise';
$partnerOpportunities = $partner && $isEnterprise
    ? learner_ecosystem_partner_opportunities((string) $partner['id'], true)
    : [];
$schoolActivities = $partner && !$isEnterprise
    ? learner_ecosystem_school_activities((string) $partner['id'])
    : [];
$isDatabaseSource = learner_repository_factory()->source() === 'database';
$partnerRecord = $partner ?? [];
$partnerVerificationStatus = (string) ($partner['verification_status'] ?? '');
$partnerHasDescription = learner_ecosystem_partner_has_value($partnerRecord, 'description');
$partnerHasLocation = learner_ecosystem_partner_has_value($partnerRecord, 'location');
$partnerHasIndustry = learner_ecosystem_partner_has_value($partnerRecord, 'industry');
$partnerHasSchoolType = learner_ecosystem_partner_has_value($partnerRecord, 'school_type');
$partnerHasSize = learner_ecosystem_partner_has_value($partnerRecord, 'size');
$partnerHasFounded = learner_ecosystem_partner_has_value($partnerRecord, 'founded');
$partnerHasOpportunityCount = learner_ecosystem_partner_has_value($partnerRecord, 'opportunity_count');
$partnerHasAddress = learner_ecosystem_partner_has_value($partnerRecord, 'address');
$partnerHasEmail = learner_ecosystem_partner_has_value($partnerRecord, 'email');
$partnerHasPhone = learner_ecosystem_partner_has_value($partnerRecord, 'phone');
$partnerHasLevel = learner_ecosystem_partner_has_value($partnerRecord, 'level');
$partnerHasAcademicYear = learner_ecosystem_partner_has_value($partnerRecord, 'academic_year');
$partnerHasStudentCount = learner_ecosystem_partner_has_value($partnerRecord, 'student_count');
$partnerHasTeacherCount = learner_ecosystem_partner_has_value($partnerRecord, 'teacher_count');
$partnerHighlights = learner_ecosystem_partner_list($partnerRecord, 'highlights');
$partnerPrograms = learner_ecosystem_partner_list($partnerRecord, 'programs');
$partnerFacilities = learner_ecosystem_partner_list($partnerRecord, 'facilities');
$partnerWebsiteUrl = learner_ecosystem_partner_has_value($partnerRecord, 'website')
    ? learner_ecosystem_http_url($partner['website'] ?? null)
    : null;
$partnerTypeLabel = $isEnterprise
    ? 'Doanh nghiệp'
    : ($partnerHasSchoolType ? trim((string) $partner['school_type']) : 'Trường học');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Chi tiết đối tác trong hệ sinh thái TalentHub dành cho học sinh, sinh viên.">
    <title><?= learner_escape($partner['name'] ?? 'Không tìm thấy đối tác'); ?> | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-partner" data-partner-page>
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <nav class="learner-breadcrumbs" aria-label="Đường dẫn">
                    <a href="ecosystem.php?tab=<?= $isEnterprise ? 'enterprises' : 'schools'; ?>">Hệ sinh thái &amp; Cơ hội</a>
                    <span aria-hidden="true">/</span>
                    <span><?= learner_escape($partner['name'] ?? 'Không tìm thấy'); ?></span>
                </nav>

                <?php if (!$partner): ?>
                    <section class="learner-card learner-not-found" aria-labelledby="partner-not-found-title">
                        <span><?= learner_icon('building', 34); ?></span>
                        <h1 id="partner-not-found-title">Không tìm thấy đối tác</h1>
                        <p>Liên kết có thể đã thay đổi hoặc đối tác chưa được công bố.</p>
                        <a class="learner-btn learner-btn--primary" href="ecosystem.php">Quay lại hệ sinh thái</a>
                    </section>
                <?php else: ?>
                    <section class="learner-partner-hero learner-card">
                        <span class="learner-partner-logo learner-partner-logo--hero <?= $isEnterprise ? 'learner-partner-logo--enterprise' : 'learner-partner-logo--school'; ?>"><?= learner_escape($partner['logo_text']); ?></span>
                        <div class="learner-partner-hero__content">
                            <div class="learner-partner-hero__badges">
                                <span class="learner-badge <?= $isEnterprise ? 'learner-badge--primary' : 'learner-badge--secondary'; ?>"><?= learner_escape($partnerTypeLabel); ?></span>
                                <?php if ($isEnterprise && (($partner['verified'] ?? false) || in_array($partnerVerificationStatus, ['verified', 'approved'], true))): ?>
                                    <span class="learner-verified-pill"><?= learner_icon('check', 14); ?> <?= $partnerVerificationStatus === 'approved' ? 'Đã phê duyệt' : 'Đã xác minh'; ?></span>
                                <?php endif; ?>
                            </div>
                            <h1><?= learner_escape($partner['name']); ?></h1>
                            <?php if ($partnerHasDescription): ?><p><?= learner_escape(trim((string) $partner['description'])); ?></p><?php endif; ?>
                            <div class="learner-meta-list learner-meta-list--inline">
                                <?php if ($partnerHasLocation): ?>
                                    <span><?= learner_icon('map-pin', 17); ?> <?= learner_escape(trim((string) $partner['location'])); ?></span>
                                <?php endif; ?>
                                <?php $partnerHasCategory = $isEnterprise ? $partnerHasIndustry : $partnerHasSchoolType; ?>
                                <?php if ($partnerHasCategory): ?>
                                    <?php $partnerCategory = trim((string) ($isEnterprise ? $partner['industry'] : $partner['school_type'])); ?>
                                    <span><?= learner_icon($isEnterprise ? 'briefcase' : 'graduation-cap', 17); ?> <?= learner_escape($partnerCategory); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="learner-partner-hero__actions">
                            <a class="learner-btn learner-btn--primary" href="#partner-opportunities">Xem cơ hội <?= learner_icon('arrow-right', 17); ?></a>
                            <?php if ($partnerWebsiteUrl !== null): ?>
                                <a class="learner-btn learner-btn--outline" href="<?= learner_escape($partnerWebsiteUrl); ?>" target="_blank" rel="noopener noreferrer">Website <?= learner_icon('external-link', 16); ?></a>
                            <?php endif; ?>
                        </div>
                    </section>

                    <div class="learner-partner-layout">
                        <div class="learner-partner-main">
                            <section class="learner-card learner-content-section" aria-labelledby="partner-about-title">
                                <div class="learner-section-heading">
                                    <h2 id="partner-about-title">Giới thiệu <?= learner_escape($isEnterprise ? 'doanh nghiệp' : 'trường học'); ?></h2>
                                </div>
                                <?php if ($partnerHasDescription): ?><p><?= learner_escape(trim((string) $partner['description'])); ?></p><?php endif; ?>
                                <?php if ($isEnterprise): ?>
                                    <?php if ($partnerHasSize || $partnerHasFounded || $partnerHasOpportunityCount): ?>
                                        <div class="learner-fact-grid">
                                        <?php if ($partnerHasSize): ?>
                                            <div><span>Quy mô</span><strong><?= learner_escape(trim((string) $partner['size'])); ?></strong></div>
                                        <?php endif; ?>
                                        <?php if ($partnerHasFounded): ?>
                                            <div><span>Thành lập</span><strong><?= learner_escape(trim((string) $partner['founded'])); ?></strong></div>
                                        <?php endif; ?>
                                        <?php if ($partnerHasOpportunityCount): ?><div><span>Vị trí đang mở</span><strong><?= learner_escape($partner['opportunity_count']); ?></strong></div><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($partnerHighlights !== []): ?>
                                        <h3>Điểm nổi bật</h3>
                                        <ul class="learner-check-list">
                                            <?php foreach ($partnerHighlights as $highlight): ?>
                                                <li><?= learner_icon('check', 16); ?> <?= learner_escape($highlight); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($partnerHasLevel || $partnerHasAcademicYear || $partnerHasStudentCount || $partnerHasTeacherCount): ?>
                                        <div class="learner-fact-grid">
                                            <?php if ($partnerHasLevel): ?><div><span>Cấp học</span><strong><?= learner_escape(trim((string) $partner['level'])); ?></strong></div><?php endif; ?>
                                            <?php if ($partnerHasAcademicYear): ?><div><span>Năm học</span><strong><?= learner_escape(trim((string) $partner['academic_year'])); ?></strong></div><?php endif; ?>
                                            <?php if ($partnerHasStudentCount): ?><div><span>Học sinh</span><strong><?= learner_escape((string) $partner['student_count']); ?></strong></div><?php endif; ?>
                                            <?php if ($partnerHasTeacherCount): ?><div><span>Giáo viên</span><strong><?= learner_escape((string) $partner['teacher_count']); ?></strong></div><?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($partnerPrograms !== []): ?>
                                        <h3>Ngành đào tạo nổi bật</h3>
                                        <div class="learner-program-grid">
                                            <?php foreach ($partnerPrograms as $program): ?>
                                                <span><?= learner_icon('book', 17); ?> <?= learner_escape($program); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($partnerFacilities !== []): ?>
                                        <h3>Cơ sở vật chất</h3>
                                        <ul class="learner-check-list">
                                            <?php foreach ($partnerFacilities as $facility): ?>
                                                <li><?= learner_icon('check', 16); ?> <?= learner_escape($facility); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </section>

                            <section class="learner-content-section" id="partner-opportunities" aria-labelledby="partner-opportunities-title">
                                <div class="learner-section-heading">
                                    <div>
                                        <h2 id="partner-opportunities-title"><?= learner_escape($isEnterprise ? 'Vị trí đang tuyển' : 'Chương trình & sự kiện'); ?></h2>
                                        <p><?= count($isEnterprise ? $partnerOpportunities : $schoolActivities); ?> cơ hội đang mở</p>
                                    </div>
                                    <a href="ecosystem.php?tab=opportunities">Xem tất cả</a>
                                </div>
                                <div class="learner-opportunity-list">
                                    <?php foreach ($partnerOpportunities as $opportunity): ?>
                                        <article class="learner-opportunity-row learner-card">
                                            <span class="learner-icon-tile <?= $isEnterprise ? 'learner-icon-tile--primary' : 'learner-icon-tile--secondary'; ?>"><?= learner_icon($isEnterprise ? 'briefcase' : 'graduation-cap', 21); ?></span>
                                            <div>
                                                <span class="learner-status-dot learner-status-dot--active"><?= learner_escape($opportunity['status_label']); ?></span>
                                                <h3><?= learner_escape($opportunity['title']); ?></h3>
                                                <p><?= learner_escape($opportunity['work_type']); ?> · <?= learner_escape($opportunity['location']); ?> · Hạn <?= learner_escape((new DateTimeImmutable($opportunity['deadline']))->format('d/m/Y')); ?></p>
                                                <div class="learner-chip-list">
                                                    <?php foreach (array_slice($opportunity['skills'], 0, 4) as $skill): ?><span><?= learner_escape($skill); ?></span><?php endforeach; ?>
                                                </div>
                                            </div>
                                            <a class="learner-btn learner-btn--outline" href="opportunity.php?type=<?= learner_escape($opportunity['type']); ?>&amp;id=<?= learner_escape($opportunity['id']); ?>">Chi tiết <?= learner_icon('arrow-right', 16); ?></a>
                                        </article>
                                    <?php endforeach; ?>
                                    <?php foreach ($schoolActivities as $activity): ?>
                                        <article class="learner-opportunity-row learner-card" data-ecosystem-item-type="school-activity">
                                            <span class="learner-icon-tile learner-icon-tile--secondary"><?= learner_icon('graduation-cap', 21); ?></span>
                                            <div>
                                                <span class="learner-status-dot learner-status-dot--active"><?= learner_escape(($activity['status'] ?? '') === 'ongoing' ? 'Đang diễn ra' : 'Đã công bố'); ?></span>
                                                <h3><?= learner_escape($activity['title']); ?></h3>
                                                <p><?= learner_escape($activity['category']); ?> · <?= learner_escape((new DateTimeImmutable((string) $activity['start_at']))->format('d/m/Y H:i')); ?></p>
                                            </div>
                                            <a class="learner-btn learner-btn--outline" href="activity-detail.php?id=<?= learner_escape($activity['id']); ?>">Chi tiết <?= learner_icon('arrow-right', 16); ?></a>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        </div>

                        <aside class="learner-card learner-partner-contact" aria-labelledby="partner-contact-title">
                            <h2 id="partner-contact-title">Thông tin liên hệ</h2>
                            <dl>
                                <?php if ($partnerHasAddress): ?>
                                    <div><dt><?= learner_icon('map-pin', 18); ?> Địa chỉ</dt><dd><?= learner_escape(trim((string) $partner['address'])); ?></dd></div>
                                <?php endif; ?>
                                <?php if ($partnerHasEmail): ?>
                                    <?php $partnerEmail = trim((string) $partner['email']); ?>
                                    <div><dt><?= learner_icon('mail', 18); ?> Email</dt><dd><a href="mailto:<?= learner_escape($partnerEmail); ?>"><?= learner_escape($partnerEmail); ?></a></dd></div>
                                <?php endif; ?>
                                <?php if ($partnerHasPhone): ?>
                                    <?php $partnerPhone = trim((string) $partner['phone']); ?>
                                    <div><dt><?= learner_icon('phone', 18); ?> Điện thoại</dt><dd><a href="tel:<?= learner_escape(preg_replace('/\s+/', '', $partnerPhone)); ?>"><?= learner_escape($partnerPhone); ?></a></dd></div>
                                <?php endif; ?>
                                <?php if ($partnerWebsiteUrl !== null): ?>
                                    <div><dt><?= learner_icon('globe', 18); ?> Website</dt><dd><a href="<?= learner_escape($partnerWebsiteUrl); ?>" target="_blank" rel="noopener noreferrer">Truy cập website</a></dd></div>
                                <?php endif; ?>
                            </dl>
                            <div class="learner-data-note">
                                <?= learner_icon('info', 17); ?>
                                <p><?= learner_escape($isDatabaseSource ? 'Thông tin được đọc trực tiếp từ dữ liệu TalentHub hiện có.' : 'Thông tin đang hiển thị theo nguồn dữ liệu được cấu hình cho môi trường này.'); ?></p>
                            </div>
                        </aside>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
