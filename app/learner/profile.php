<?php
/** TalentHub Learner - Competency profile */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';

$pageTitle = 'Hồ sơ năng lực';
$currentRoute = '/app/learner/profile.php';
$shareUrl = 'http://localhost/TalentHub/app/learner/profile.php?student=nguyen-van-a';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Hồ sơ năng lực đã xác minh của Nguyễn Văn A trên TalentHub.">
    <title>Hồ sơ năng lực | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-profile">
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
                        <div class="learner-profile-skills__grid">
                            <?php foreach ($skills as $skill): ?>
                                <article class="learner-profile-skill">
                                    <div class="learner-profile-skill__heading">
                                        <span><?= learner_escape($skill['name']); ?></span>
                                        <strong><?= learner_escape($skill['score']); ?></strong>
                                        <span class="learner-skill-level learner-skill-level--<?= learner_escape($skill['level'] === 'Trung bình' ? 'warning' : 'success'); ?>"><?= learner_escape($skill['level']); ?></span>
                                    </div>
                                    <div class="learner-progress" role="progressbar" aria-label="<?= learner_escape($skill['name']); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= learner_escape($skill['score']); ?>">
                                        <span class="learner-progress--secondary" style="--learner-progress: <?= learner_escape($skill['score']); ?>%;"></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="learner-card learner-certificates" aria-labelledby="certificates-title">
                        <div class="learner-section-heading learner-section-heading--icon">
                            <span class="learner-section-heading__icon"><?= learner_icon('award', 22); ?></span>
                            <h2 id="certificates-title">Chứng chỉ</h2>
                        </div>
                        <div class="learner-certificate-list">
                            <?php foreach ($certificates as $certificate): ?>
                                <article class="learner-certificate">
                                    <span class="learner-certificate__icon"><?= learner_icon('award', 20); ?></span>
                                    <div>
                                        <h3><?= learner_escape($certificate['name']); ?></h3>
                                        <p><?= learner_escape($certificate['issuer']); ?> <span aria-hidden="true">•</span> <?= learner_escape($certificate['year']); ?></p>
                                    </div>
                                    <?php if ($certificate['verified']): ?>
                                        <span class="learner-verified-badge">Đã xác minh</span>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>

                <section class="learner-card learner-projects" aria-labelledby="projects-title">
                    <div class="learner-section-heading learner-section-heading--icon">
                        <span class="learner-section-heading__icon"><?= learner_icon('briefcase', 22); ?></span>
                        <h2 id="projects-title">Dự án đã tham gia</h2>
                    </div>
                    <div class="learner-project-grid">
                        <?php foreach ($projects as $project): ?>
                            <article class="learner-project-card">
                                <div>
                                    <h3><?= learner_escape($project['name']); ?></h3>
                                    <p><?= learner_escape($project['description']); ?></p>
                                </div>
                                <div class="learner-project-card__badges">
                                    <span class="learner-badge learner-badge--<?= learner_escape($project['tone']); ?>"><?= learner_escape($project['role']); ?></span>
                                    <span class="learner-badge learner-badge--<?= learner_escape($project['tone']); ?>"><?= learner_escape($project['status']); ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>

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
                        <span>Họ và tên</span>
                        <input id="learner-field-name" name="name" type="text" value="<?= learner_escape($student['name']); ?>" aria-describedby="learner-error-name" required>
                        <small class="learner-field__error" id="learner-error-name" data-error-for="name" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Lớp</span>
                        <input id="learner-field-class" name="class" type="text" value="<?= learner_escape($student['class']); ?>" aria-describedby="learner-error-class" required>
                        <small class="learner-field__error" id="learner-error-class" data-error-for="class" role="alert"></small>
                    </label>
                    <label class="learner-field learner-field--wide">
                        <span>Trường</span>
                        <input id="learner-field-school" name="school" type="text" value="<?= learner_escape($student['school']); ?>" aria-describedby="learner-error-school" required>
                        <small class="learner-field__error" id="learner-error-school" data-error-for="school" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Email</span>
                        <input id="learner-field-email" name="email" type="email" value="<?= learner_escape($student['email']); ?>" aria-describedby="learner-error-email" required>
                        <small class="learner-field__error" id="learner-error-email" data-error-for="email" role="alert"></small>
                    </label>
                    <label class="learner-field">
                        <span>Địa điểm</span>
                        <input id="learner-field-location" name="location" type="text" value="<?= learner_escape($student['location']); ?>" aria-describedby="learner-error-location" required>
                        <small class="learner-field__error" id="learner-error-location" data-error-for="location" role="alert"></small>
                    </label>
                </div>
                <div class="learner-modal__actions">
                    <button class="learner-btn learner-btn--secondary" type="button" data-close-modal>Hủy</button>
                    <button class="learner-btn learner-btn--primary" type="submit">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>

    <div class="learner-modal" id="learner-share-modal" role="dialog" aria-modal="true" aria-labelledby="learner-share-title" hidden>
        <div class="learner-modal__backdrop" data-close-modal></div>
        <div class="learner-modal__dialog learner-modal__dialog--compact" tabindex="-1">
            <div class="learner-modal__header">
                <div>
                    <h2 id="learner-share-title">Chia sẻ hồ sơ</h2>
                    <p>Gửi liên kết công khai này cho giáo viên hoặc nhà tuyển dụng.</p>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng cửa sổ chia sẻ"><?= learner_icon('x', 22); ?></button>
            </div>
            <label class="learner-field" for="learner-share-link">
                <span>Liên kết hồ sơ</span>
                <span class="learner-copy-field">
                    <input id="learner-share-link" type="text" value="<?= learner_escape($shareUrl); ?>" readonly>
                    <button class="learner-btn learner-btn--primary" type="button" data-copy-profile><?= learner_icon('copy', 17); ?> Sao chép</button>
                </span>
            </label>
        </div>
    </div>

    <script src="../../assets/js/learner-api.js"></script>
    <script src="../../assets/js/learner.js"></script>
</body>
</html>
