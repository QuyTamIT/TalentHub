<?php
/** TalentHub Learner - Competency profile */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Hồ sơ năng lực';
$currentRoute = '/app/learner/profile.php';
$shareUrl = ($isDatabaseMode ?? false) ? '' : 'http://localhost/TalentHub/app/learner/profile.php?student=nguyen-van-a';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Hồ sơ năng lực đã xác minh của <?= learner_escape($student['name']); ?> trên TalentHub.">
    <title>Hồ sơ năng lực | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-profile" data-learner-source="<?= ($isDatabaseMode ?? false) ? 'database' : 'mock'; ?>">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <?php
                $learnerPageBanner = [
                    'id' => 'learner-profile-page-title',
                    'eyebrow' => 'Hành trình phát triển',
                    'title' => 'Hồ sơ năng lực',
                    'description' => 'Theo dõi những năng lực, thành tích và trải nghiệm tạo nên hồ sơ của bạn.',
                    'icon' => 'user',
                ];
                include __DIR__ . '/includes/page-banner.php';
                ?>
                <section class="learner-card learner-profile-hero" aria-labelledby="profile-name">
                    <div class="learner-profile-hero__top">
                        <div class="learner-profile-identity">
                            <div class="learner-profile-avatar" aria-hidden="true"><?= learner_escape($student['initials']); ?></div>
                            <div class="learner-profile-identity__content">
                                <div class="learner-profile-identity__name-row">
                                    <h2 id="profile-name" data-profile-name><?= learner_escape($student['name']); ?></h2>
                                    <?php if ($student['verified']): ?>
                                        <span class="learner-verified-badge"><?= learner_icon('check', 15); ?> Đã xác minh</span>
                                    <?php endif; ?>
                                </div>
                                <p class="learner-profile-school"><span data-profile-class><?= learner_escape($student['class']); ?></span> <span aria-hidden="true">•</span> <span data-profile-school><?= learner_escape($student['school']); ?></span></p>
                                <div class="learner-profile-contact">
                                    <span><?= learner_icon('mail', 17); ?> <span data-profile-email><?= learner_escape($student['email']); ?></span></span>
                                    <span><?= learner_icon('map-pin', 17); ?> <span data-profile-location><?= learner_escape($student['location']); ?></span></span>
                                </div>
                            </div>
                        </div>

                        <div class="learner-profile-actions">
                            <button class="learner-btn learner-btn--outline" type="button" data-open-modal="learner-share-modal">
                                <?= learner_icon('share', 18); ?> Chia sẻ hồ sơ
                            </button>
                            <button class="learner-btn learner-btn--primary" type="button" data-open-modal="learner-edit-modal">
                                <?= learner_icon('edit', 18); ?> Chỉnh sửa
                            </button>
                        </div>
                    </div>

                    <div class="learner-profile-kpis" aria-label="Chỉ số hồ sơ">
                        <?php foreach ($profileKpis as $kpi): ?>
                            <article class="learner-profile-kpi">
                                <strong><?= learner_escape($kpi['value']); ?></strong>
                                <div>
                                    <span><?= learner_escape($kpi['label']); ?></span>
                                    <small>Cập nhật <?= learner_icon('check', 12); ?></small>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="learner-profile-grid">
                    <section class="learner-card learner-profile-skills" aria-labelledby="profile-skills-title">
                        <div class="learner-section-heading learner-section-heading--icon">
                            <span class="learner-section-heading__icon"><?= learner_icon('book', 22); ?></span>
                            <h2 id="profile-skills-title">Kỹ năng</h2>
                        </div>
                        <?php if (empty($skills)): ?>
                            <p class="learner-empty-state">Chưa có dữ liệu kỹ năng.</p>
                        <?php else: ?>
                            <div class="learner-profile-skills__grid">
                                <?php foreach ($skills as $skill):
                                    $skillScoreClamped = max(0, min(100, (int) round((float) ($skill['score'] ?? 0))));
                                ?>
                                    <article class="learner-skill-bar">
                                        <div class="learner-skill-bar__header">
                                            <span><?= learner_escape($skill['name']); ?></span>
                                            <strong><?= $skillScoreClamped; ?>/100</strong>
                                        </div>
                                        <div class="learner-progress" role="progressbar" aria-valuenow="<?= $skillScoreClamped; ?>" aria-valuemin="0" aria-valuemax="100">
                                            <span class="learner-progress--secondary" style="--learner-progress: <?= $skillScoreClamped; ?>%;"></span>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="learner-card learner-certificates" aria-labelledby="certificates-title">
                        <div class="learner-section-heading learner-section-heading--icon" style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="learner-section-heading__icon"><?= learner_icon('award', 22); ?></span>
                                <h2 id="certificates-title">Chứng chỉ</h2>
                            </div>
                            <button class="learner-btn learner-btn--outline" type="button" data-open-modal="learner-certificate-modal" style="font-size: 0.875rem; padding: 0.35rem 0.75rem;">
                                + Thêm chứng chỉ
                            </button>
                        </div>
                        <?php if (empty($certificates)): ?>
                            <div class="learner-empty-state">
                                <p>Chưa có chứng chỉ nào được ghi nhận.</p>
                            </div>
                        <?php else: ?>
                            <div class="learner-certificate-list">
                                <?php foreach ($certificates as $certificate): ?>
                                    <article class="learner-certificate">
                                        <span class="learner-certificate__icon"><?= learner_icon('award', 20); ?></span>
                                        <div>
                                            <h3><?= learner_escape($certificate['name'] ?? $certificate['title'] ?? ''); ?></h3>
                                            <p><?= learner_escape($certificate['issuer'] ?? $certificate['issuing_organization'] ?? ''); ?> <span aria-hidden="true">•</span> <?= learner_escape($certificate['year'] ?? $certificate['issue_date'] ?? ''); ?></p>
                                        </div>
                                        <?php if (!empty($certificate['verified']) || ($certificate['verification_status'] ?? '') === 'verified'): ?>
                                            <span class="learner-verified-badge">Đã xác minh</span>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <section class="learner-card learner-projects" aria-labelledby="projects-title">
                    <div class="learner-section-heading learner-section-heading--icon">
                        <span class="learner-section-heading__icon"><?= learner_icon('briefcase', 22); ?></span>
                        <h2 id="projects-title">Dự án đã tham gia</h2>
                    </div>
                    <?php if (empty($projects)): ?>
                        <div class="learner-empty-state">
                            <p>Chưa có dự án nào được ghi nhận.</p>
                        </div>
                    <?php else: ?>
                        <div class="learner-project-grid">
                            <?php foreach ($projects as $project): ?>
                                <article class="learner-project-card">
                                    <div>
                                        <h3><?= learner_escape($project['name'] ?? $project['title'] ?? ''); ?></h3>
                                        <p><?= learner_escape($project['description'] ?? ''); ?></p>
                                    </div>
                                    <div class="learner-project-card__badges">
                                        <span class="learner-badge learner-badge--<?= learner_escape($project['tone'] ?? 'primary'); ?>"><?= learner_escape($project['role'] ?? 'Thành viên'); ?></span>
                                        <span class="learner-badge learner-badge--<?= learner_escape($project['tone'] ?? 'primary'); ?>"><?= learner_escape($project['status'] ?? 'Đang tiến hành'); ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="learner-modal" id="learner-edit-modal" role="dialog" aria-modal="true" aria-labelledby="learner-edit-title" hidden>
        <div class="learner-modal__backdrop" data-close-modal></div>
        <div class="learner-modal__dialog" tabindex="-1">
            <div class="learner-modal__header">
                <div>
                    <h2 id="learner-edit-title">Chỉnh sửa hồ sơ</h2>
                    <p>Cập nhật thông tin hiển thị trên hồ sơ năng lực.</p>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng cửa sổ chỉnh sửa"><?= learner_icon('x', 22); ?></button>
            </div>
            <form class="learner-form" id="learner-profile-form" novalidate>
                <div class="learner-form__grid">
                    <label class="learner-field">
                        <span>Họ và tên *</span>
                        <input id="learner-field-name" name="fullName" type="text" value="<?= learner_escape($student['name']); ?>" aria-describedby="learner-error-name" required>
                        <small class="learner-field__error" id="learner-error-name" data-error-for="fullName" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Ngày sinh</span>
                        <input id="learner-field-dob" name="dateOfBirth" type="date" value="<?= learner_escape($student['dateOfBirth'] ?? ''); ?>">
                        <small class="learner-field__error" data-error-for="dateOfBirth" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Số điện thoại</span>
                        <input id="learner-field-phone" name="phone" type="tel" value="<?= learner_escape($student['phone'] ?? ''); ?>">
                        <small class="learner-field__error" data-error-for="phone" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Địa điểm</span>
                        <input id="learner-field-location" name="location" type="text" value="<?= learner_escape($student['location']); ?>">
                        <small class="learner-field__error" id="learner-error-location" data-error-for="location" role="alert"></small>
                    </label>
                    <label class="learner-field learner-field--wide">
                        <span>Chức danh / Headline</span>
                        <input id="learner-field-headline" name="headline" type="text" value="<?= learner_escape($student['headline'] ?? ''); ?>" placeholder="Ví dụ: Học sinh chuyên Tin, Đam mê AI">
                        <small class="learner-field__error" data-error-for="headline" role="alert"></small>
                    </label>
                    <label class="learner-field learner-field--wide">
                        <span>Giới thiệu bản thân (Bio)</span>
                        <textarea id="learner-field-bio" name="bio" rows="3" placeholder="Chia sẻ mục tiêu học tập và định hướng của bạn..."><?= learner_escape($student['bio'] ?? ''); ?></textarea>
                        <small class="learner-field__error" data-error-for="bio" role="alert"></small>
                    </label>
                </div>
                <div class="learner-modal__actions">
                    <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Hủy</button>
                    <button class="learner-btn learner-btn--primary" type="submit">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Share Profile Modal -->
    <div class="learner-modal" id="learner-share-modal" role="dialog" aria-modal="true" aria-labelledby="learner-share-title" hidden>
        <div class="learner-modal__backdrop" data-close-modal></div>
        <div class="learner-modal__dialog" tabindex="-1">
            <div class="learner-modal__header">
                <div>
                    <h2 id="learner-share-title">Chia sẻ hồ sơ năng lực</h2>
                    <p>Chọn các thông tin bạn đồng ý chia sẻ và thời hạn của liên kết.</p>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng cửa sổ chia sẻ"><?= learner_icon('x', 22); ?></button>
            </div>
            <form id="learner-share-form">
                <fieldset style="border: none; padding: 0; margin-bottom: 1rem;">
                    <legend style="font-weight: 600; margin-bottom: 0.5rem;">Thông tin cho phép chia sẻ:</legend>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <label><input type="checkbox" name="sharedFields[]" value="fullName" checked disabled> Họ và tên (cơ bản)</label>
                        <label><input type="checkbox" name="sharedFields[]" value="headline" checked> Chức danh / Headline</label>
                        <label><input type="checkbox" name="sharedFields[]" value="bio" checked> Giới thiệu bản thân</label>
                        <label><input type="checkbox" name="sharedFields[]" value="location" checked> Địa điểm</label>
                        <label><input type="checkbox" name="sharedFields[]" value="school" checked> Trường học</label>
                        <label><input type="checkbox" name="sharedFields[]" value="class" checked> Lớp học</label>
                        <label><input type="checkbox" name="sharedFields[]" value="skills" checked> Kỹ năng</label>
                        <label><input type="checkbox" name="sharedFields[]" value="certificates" checked> Chứng chỉ</label>
                        <label><input type="checkbox" name="sharedFields[]" value="projects" checked> Dự án</label>
                        <label><input type="checkbox" name="sharedFields[]" value="experience" checked> Trải nghiệm & Giờ hoạt động</label>
                        <label><input type="checkbox" name="sharedFields[]" value="email"> Email (nhạy cảm)</label>
                        <label><input type="checkbox" name="sharedFields[]" value="phone"> Số điện thoại (nhạy cảm)</label>
                    </div>
                </fieldset>

                <label class="learner-field" style="margin-bottom: 1rem;">
                    <span>Thời hạn chia sẻ:</span>
                    <select name="expiresInDays" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 0.375rem; width: 100%;">
                        <option value="7">7 ngày</option>
                        <option value="14">14 ngày</option>
                        <option value="30" selected>30 ngày</option>
                        <option value="90">90 ngày</option>
                    </select>
                </label>

                <div class="learner-modal__actions" style="margin-bottom: 1rem;">
                    <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Hủy</button>
                    <button class="learner-btn learner-btn--primary" type="submit">Tạo liên kết chia sẻ</button>
                </div>
            </form>

            <div id="learner-share-result" style="display: none; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                <label class="learner-field" for="learner-share-link">
                    <span>Liên kết chia sẻ đã tạo (chỉ hiển thị một lần):</span>
                    <span class="learner-copy-field">
                        <input id="learner-share-link" type="text" readonly>
                        <button class="learner-btn learner-btn--primary" type="button" data-copy-profile><?= learner_icon('copy', 17); ?> Sao chép</button>
                    </span>
                </label>
            </div>
        </div>
    </div>

    <!-- Certificate Modal -->
    <div class="learner-modal" id="learner-certificate-modal" role="dialog" aria-modal="true" aria-labelledby="learner-certificate-title" hidden>
        <div class="learner-modal__backdrop" data-close-modal></div>
        <div class="learner-modal__dialog" tabindex="-1">
            <div class="learner-modal__header">
                <div>
                    <h2 id="learner-certificate-title">Thêm chứng chỉ</h2>
                    <p>Khai báo chứng chỉ của bạn để lưu vào hồ sơ năng lực.</p>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng cửa sổ thêm chứng chỉ"><?= learner_icon('x', 22); ?></button>
            </div>
            <form class="learner-form" id="learner-certificate-form" novalidate>
                <div class="learner-form__grid">
                    <label class="learner-field learner-field--wide">
                        <span>Tên chứng chỉ / Chứng nhận *</span>
                        <input id="cert-field-title" name="title" type="text" required placeholder="Ví dụ: Chứng chỉ Tin học văn phòng, AWS Certified Practitioner">
                        <small class="learner-field__error" data-error-for="title" role="alert"></small>
                    </label>
                    <label class="learner-field learner-field--wide">
                        <span>Tổ chức cấp *</span>
                        <input id="cert-field-org" name="issuingOrganization" type="text" required placeholder="Ví dụ: British Council, Amazon Web Services">
                        <small class="learner-field__error" data-error-for="issuingOrganization" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Ngày cấp *</span>
                        <input id="cert-field-issue-date" name="issueDate" type="date" required>
                        <small class="learner-field__error" data-error-for="issueDate" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Ngày hết hạn (nếu có)</span>
                        <input id="cert-field-expiry-date" name="expiryDate" type="date">
                        <small class="learner-field__error" data-error-for="expiryDate" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Mã chứng chỉ (Credential ID)</span>
                        <input id="cert-field-cred-id" name="credentialId" type="text">
                        <small class="learner-field__error" data-error-for="credentialId" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Đường dẫn xác minh (URL)</span>
                        <input id="cert-field-cred-url" name="credentialUrl" type="url" placeholder="https://...">
                        <small class="learner-field__error" data-error-for="credentialUrl" role="alert"></small>
                    </label>
                </div>
                <div class="learner-modal__actions">
                    <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Hủy</button>
                    <button class="learner-btn learner-btn--primary" type="submit">Lưu chứng chỉ</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
