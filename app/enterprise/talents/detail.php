<?php
/**
 * TalentHub Enterprise - Talent Passport / Hồ sơ nhân tài Detail Page
 * 
 * Note for Developers:
 * - This detail page displays comprehensive learner profiles including skills,
 *   experience logs, featured projects, certificates, and internship readiness.
 * - Profile data is loaded dynamically by candidate ID (?id=1, ?id=2, etc.).
 * - Privacy rules strictly enforced: NO personal email or phone numbers rendered directly.
 * - Contact requests trigger a modal with privacy consent notices.
 */

require_once __DIR__ . '/../includes/talents-data.php';

$enterpriseInfo = [
    'company_name' => 'FPT Software',
    'account_type' => 'Gói Premium',
    'logo_initials' => 'FPT',
    'new_matches_count' => 86,
    'total_talents' => 1247
];

$talentId = isset($_GET['id']) ? intval($_GET['id']) : 1;
$talent = getMockTalentById($talentId);

$pageTitle = $talent ? ('Hồ sơ nhân tài - ' . $talent['name']) : 'Không tìm thấy hồ sơ';
$currentRoute = '/app/enterprise/talents.php';

$sidebarNav = [
    [
        'title' => 'Tổng quan',
        'route' => '/app/enterprise',
        'icon' => 'grid',
        'active' => false
    ],
    [
        'title' => 'Tìm nhân tài',
        'route' => '/app/enterprise/talents.php',
        'icon' => 'search-users',
        'active' => true
    ],
    [
        'title' => 'Tuyển thực tập',
        'route' => '/app/enterprise/internships/',
        'icon' => 'briefcase',
        'active' => false
    ],
    [
        'title' => 'Tài trợ dự án',
        'route' => '/app/enterprise/sponsorships/',
        'icon' => 'award',
        'active' => false
    ],
    [
        'title' => 'Phân tích tuyển dụng',
        'route' => '/app/enterprise/analytics.php',
        'icon' => 'bar-chart-2',
        'active' => false
    ]
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Talent Passport - Hồ sơ năng lực chi tiết của ứng viên trên TalentHub Enterprise.">
    <title><?= htmlspecialchars($pageTitle); ?> | TalentHub Enterprise</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/enterprise.css">
</head>
<body class="enterprise-dashboard">

    <!-- Layout Wrapper -->
    <div class="ent-layout">
        
        <!-- Sidebar Navigation Partial -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="ent-main-wrapper">
            
            <!-- Top Header Partial -->
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <!-- Page Body Content -->
            <main class="ent-body">
                <div class="container-fluid">
                    
                    <!-- Back Link Navigation -->
                    <div class="ent-back-bar">
                        <a href="../talents.php" class="ent-back-link" data-route="/app/enterprise/talents.php">
                            &larr; Quay lại Tìm nhân tài
                        </a>
                    </div>

                    <?php if (!$talent): ?>
                        <!-- Invalid Candidate ID Error State -->
                        <div class="ent-empty-state" style="margin-top: 2rem;">
                            <div class="ent-empty-state__icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                            </div>
                            <h3 class="ent-empty-state__title">Không tìm thấy hồ sơ nhân tài</h3>
                            <p class="ent-empty-state__desc">
                                Hồ sơ ứng viên với mã mã số #<?= htmlspecialchars($talentId); ?> không tồn tại hoặc đã bị xóa khỏi hệ thống.
                            </p>
                            <a href="../talents.php" class="btn btn-primary">
                                &larr; Quay lại Tìm nhân tài
                            </a>
                        </div>
                    <?php else: ?>
                        
                        <!-- Talent Passport Main Detail Layout (2 Columns) -->
                        <div class="ent-passport-grid">
                            
                            <!-- Left / Main Column (Overview, Bio, Skills, Experience, Projects, Certificates) -->
                            <div class="ent-passport-main">
                                
                                <!-- 1. Profile Overview Header Card -->
                                <section class="ent-section-box ent-passport-overview-card">
                                    <div class="ent-passport-overview__top">
                                        <div class="ent-passport-overview__avatar">
                                            <?= htmlspecialchars($talent['avatar_initials']); ?>
                                        </div>
                                        <div class="ent-passport-overview__info">
                                            <div class="ent-passport-overview__title-row">
                                                <h2 class="ent-passport-overview__name"><?= htmlspecialchars($talent['name']); ?></h2>
                                                <span class="ent-passport-score-badge">
                                                    <?= htmlspecialchars($talent['talent_score']); ?> điểm
                                                </span>
                                            </div>

                                            <div class="ent-passport-overview__meta">
                                                <span><?= htmlspecialchars($talent['school']); ?></span>
                                                <span class="dot">&bull;</span>
                                                <span><?= htmlspecialchars($talent['class_year']); ?></span>
                                                <span class="dot">&bull;</span>
                                                <span><?= htmlspecialchars($talent['education_level']); ?></span>
                                            </div>

                                            <div class="ent-passport-overview__sub">
                                                <span class="label">Lĩnh vực năng lực:</span>
                                                <span class="val font-semibold"><?= htmlspecialchars($talent['major_field']); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ent-passport-overview__actions-bar">
                                        <div class="ent-passport-status-pill">
                                            <span class="status-dot"></span>
                                            <?= htmlspecialchars($talent['internship_status_label']); ?>
                                        </div>

                                        <div class="ent-passport-btn-group">
                                            <button type="button" 
                                                    class="btn btn-secondary btn-sm ent-passport-save-btn <?= $talent['saved'] ? 'is-saved' : ''; ?>" 
                                                    id="detail-save-btn" 
                                                    data-talent-id="<?= htmlspecialchars($talent['id']); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= $talent['saved'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                                </svg>
                                                <span class="btn-text"><?= $talent['saved'] ? 'Đã lưu hồ sơ' : 'Lưu hồ sơ'; ?></span>
                                            </button>

                                            <button type="button" 
                                                    class="btn btn-primary btn-sm" 
                                                    id="detail-contact-btn">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                    <polyline points="22,6 12,13 2,6"></polyline>
                                                </svg>
                                                Liên hệ
                                            </button>
                                        </div>
                                    </div>
                                </section>

                                <!-- 2. Giới thiệu (Learner Bio & Orientation) -->
                                <section class="ent-section-box">
                                    <h3 class="ent-section-box__title">Giới thiệu bản thân & Định hướng</h3>
                                    <p class="ent-passport-bio-text">
                                        <?= htmlspecialchars($talent['bio'] ?? 'Học sinh / Sinh viên năng động, ham học hỏi và luôn chủ động trau dồi kỹ năng thực tế thông qua các dự án và sảnh chơi công nghệ.'); ?>
                                    </p>
                                </section>

                                <!-- 3. Kỹ năng (Detailed Skills Grid) -->
                                <section class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <h3 class="ent-section-box__title">Năng lực & Kỹ năng</h3>
                                        <span class="ent-section-box__count"><?= count($talent['detailed_skills'] ?? []); ?> kỹ năng</span>
                                    </div>

                                    <div class="ent-passport-skills-grid">
                                        <?php if (!empty($talent['detailed_skills'])): ?>
                                            <?php foreach ($talent['detailed_skills'] as $sk): ?>
                                                <div class="ent-passport-skill-card">
                                                    <div class="ent-passport-skill-card__name">
                                                        <?= htmlspecialchars($sk['name']); ?>
                                                    </div>
                                                    <div class="ent-passport-skill-card__footer">
                                                        <span class="ent-skill-level"><?= htmlspecialchars($sk['level']); ?></span>
                                                        <?php if ($sk['verified']): ?>
                                                            <span class="ent-verified-badge" title="Đã được giáo viên / nhà trường xác thực">
                                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                                </svg>
                                                                Đã xác thực
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="ent-unverified-badge">Tự đánh giá</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <!-- 4. Kinh nghiệm & Hoạt động (Experience Logs Timeline) -->
                                <section class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <div>
                                            <h3 class="ent-section-box__title">Kinh nghiệm & Hoạt động thực án</h3>
                                            <p class="ent-section-box__subtitle">Nhật ký giờ trải nghiệm thực tế được lưu vết tự động</p>
                                        </div>
                                        <span class="ent-exp-hours-badge">
                                            Tổng: <?= htmlspecialchars($talent['experience_hours']); ?>h trải nghiệm
                                        </span>
                                    </div>

                                    <div class="ent-passport-timeline">
                                        <?php if (!empty($talent['experience_logs'])): ?>
                                            <?php foreach ($talent['experience_logs'] as $exp): ?>
                                                <div class="ent-passport-timeline-item">
                                                    <div class="ent-passport-timeline-item__indicator"></div>
                                                    <div class="ent-passport-timeline-item__header">
                                                        <h4 class="ent-passport-timeline-item__title"><?= htmlspecialchars($exp['title']); ?></h4>
                                                        <span class="ent-passport-timeline-item__duration"><?= htmlspecialchars($exp['duration']); ?></span>
                                                    </div>
                                                    <div class="ent-passport-timeline-item__meta">
                                                        <span class="role font-medium">Vai trò: <?= htmlspecialchars($exp['role']); ?></span>
                                                        <span class="dot">&bull;</span>
                                                        <span class="hours text-primary"><?= htmlspecialchars($exp['hours']); ?> giờ thực án</span>
                                                    </div>
                                                    <p class="ent-passport-timeline-item__desc"><?= htmlspecialchars($exp['description']); ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <!-- 5. Dự án nổi bật (Featured Projects) -->
                                <section class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <h3 class="ent-section-box__title">Dự án nổi bật</h3>
                                    </div>

                                    <div class="ent-passport-projects-list">
                                        <?php if (!empty($talent['projects'])): ?>
                                            <?php foreach ($talent['projects'] as $proj): ?>
                                                <div class="ent-passport-project-card">
                                                    <div class="ent-passport-project-card__header">
                                                        <h4 class="ent-passport-project-card__title"><?= htmlspecialchars($proj['name']); ?></h4>
                                                        <?php if (!empty($proj['result'])): ?>
                                                            <span class="ent-project-result-badge"><?= htmlspecialchars($proj['result']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="ent-passport-project-card__desc"><?= htmlspecialchars($proj['description']); ?></p>
                                                    <div class="ent-passport-project-card__meta">
                                                        <span class="label">Vai trò:</span>
                                                        <span class="val font-medium"><?= htmlspecialchars($proj['role']); ?></span>
                                                    </div>
                                                    <div class="ent-passport-project-card__techs">
                                                        <?php foreach ($proj['technologies'] as $tech): ?>
                                                            <span class="skill-tag"><?= htmlspecialchars($tech); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <!-- 6. Chứng chỉ & Thành tích -->
                                <section class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <h3 class="ent-section-box__title">Chứng chỉ & Thành tích</h3>
                                    </div>

                                    <div class="ent-passport-certs-list">
                                        <?php if (!empty($talent['certificates'])): ?>
                                            <?php foreach ($talent['certificates'] as $cert): ?>
                                                <div class="ent-passport-cert-row">
                                                    <div class="ent-passport-cert-row__icon">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="12" cy="8" r="7"></circle>
                                                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                                        </svg>
                                                    </div>
                                                    <div class="ent-passport-cert-row__info">
                                                        <h4 class="cert-name"><?= htmlspecialchars($cert['name']); ?></h4>
                                                        <span class="cert-issuer"><?= htmlspecialchars($cert['issuer']); ?> &bull; <?= htmlspecialchars($cert['issue_date']); ?></span>
                                                    </div>
                                                    <?php if ($cert['verified']): ?>
                                                        <span class="ent-verified-badge">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                                <polyline points="20 6 9 17 4 12"></polyline>
                                                            </svg>
                                                            Đã minh chứng
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>

                            </div>

                            <!-- Right Column Sidebar (Readiness Summary & Privacy Card) -->
                            <aside class="ent-passport-sidebar">
                                
                                <!-- 7. Internship Readiness Summary Widget -->
                                <div class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <h3 class="ent-section-box__title">Tóm tắt Mức độ Sẵn sàng</h3>
                                    </div>

                                    <div class="ent-readiness-widget">
                                        <div class="ent-readiness-widget__status">
                                            <span class="status-label">Trạng thái tuyển dụng:</span>
                                            <span class="status-value text-accent font-bold">
                                                ● <?= htmlspecialchars($talent['readiness_summary']['status_label'] ?? $talent['internship_status_label']); ?>
                                            </span>
                                        </div>

                                        <div class="ent-readiness-widget__field">
                                            <span class="label">Vị trí mong muốn:</span>
                                            <span class="val font-semibold"><?= htmlspecialchars($talent['readiness_summary']['preferred_field'] ?? $talent['major_field']); ?></span>
                                        </div>

                                        <div class="ent-readiness-widget__strengths">
                                            <span class="label">Điểm mạnh nổi bật:</span>
                                            <ul>
                                                <?php foreach (($talent['readiness_summary']['strengths'] ?? ['Tư duy kỹ thuật tốt', 'Kỹ năng thực hành cao']) as $st): ?>
                                                    <li>&bull; <?= htmlspecialchars($st); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>

                                        <div class="ent-readiness-widget__exp">
                                            <span class="label">Tổng giờ trải nghiệm:</span>
                                            <span class="val font-bold text-primary"><?= htmlspecialchars($talent['readiness_summary']['total_exp_hours'] ?? ($talent['experience_hours'] . 'h thực án')); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Privacy Protection Notice Card -->
                                <div class="ent-section-box ent-privacy-card">
                                    <div class="ent-privacy-card__icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                    </div>
                                    <h4>Quyền riêng tư được bảo vệ</h4>
                                    <p>
                                        Thông tin liên hệ cá nhân (Số điện thoại, Email) của người học được ẩn theo tiêu chuẩn bảo mật TalentHub. Khi gửi yêu cầu liên hệ, thông báo sẽ được gửi tới người học để nhận sự chấp thuận.
                                    </p>
                                </div>

                            </aside>

                        </div>

                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <!-- Section 8: Contact Request Modal -->
    <?php if ($talent): ?>
        <div class="ent-skills-modal" id="contact-modal" aria-hidden="true" style="display: none;">
            <div class="ent-skills-modal__backdrop" id="contact-modal-backdrop"></div>
            <div class="ent-skills-modal__dialog" style="max-width: 520px;">
                <div class="ent-skills-modal__header">
                    <div>
                        <h3 class="ent-skills-modal__title">Gửi yêu cầu liên hệ</h3>
                        <p class="ent-skills-modal__subtitle">Gửi đề xuất kết nối tuyển dụng tới ứng viên <?= htmlspecialchars($talent['name']); ?></p>
                    </div>
                    <button type="button" class="ent-skills-modal__close" id="close-contact-modal-btn" aria-label="Đóng">&times;</button>
                </div>

                <div class="ent-contact-modal__body">
                    <!-- Privacy Notice Banner -->
                    <div class="ent-contact-privacy-note">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span>Thông tin liên hệ chỉ được chia sẻ khi người học đồng ý.</span>
                    </div>

                    <div class="ent-contact-form-group">
                        <label for="contact-message-input" class="ent-filter-label">Lời nhắn từ doanh nghiệp (tùy chọn):</label>
                        <textarea id="contact-message-input" 
                                  class="ent-contact-textarea" 
                                  rows="4" 
                                  placeholder="Ví dụ: Chào bạn, FPT Software ấn tượng với hồ sơ năng lực của bạn và muốn mời bạn tham gia buổi phỏng vấn thực tập vị trí <?= htmlspecialchars($talent['major_field']); ?>..."></textarea>
                    </div>
                </div>

                <div class="ent-skills-modal__footer">
                    <button type="button" class="btn btn-secondary" id="cancel-contact-btn">Hủy</button>
                    <button type="button" class="btn btn-primary" id="submit-contact-btn" data-talent-name="<?= htmlspecialchars($talent['name']); ?>">Gửi yêu cầu</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Shared Notification Toast -->
    <div class="ent-toast" id="ent-toast" aria-live="polite" aria-atomic="true">
        <div class="ent-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span class="ent-toast__message">Thông báo hệ thống</span>
        </div>
    </div>

    <!-- JavaScript Assets -->
    <script src="../../../assets/js/enterprise.js"></script>
    <script src="../../../assets/js/talent-detail.js"></script>
</body>
</html>
