<?php
/**
 * TalentHub - Public Home Page
 * Entry point for public visitors (Students, Teachers, Schools, Enterprises).
 * Impeccable Craft Redesign (Editorial Hierarchy & Asymmetric Layouts)
 */
require_once __DIR__ . '/app/shared/BrandHeader.php';

// Core Modules Data
$modules = [
    [
        'id' => '01',
        'title' => 'Hồ sơ năng lực',
        'subtitle' => 'Talent Passport 360°',
        'description' => 'Lưu trữ và chứng nhận toàn bộ quá trình học tập, năng khiếu, giải thưởng và chứng chỉ của học sinh dưới dạng 360°.',
        'features' => ['Số hóa thành tích 360°', 'Xác thực chứng chỉ số', 'Chia sẻ hồ sơ linh hoạt'],
        'icon_type' => 'passport'
    ],
    [
        'id' => '02',
        'title' => 'Khám phá năng khiếu',
        'subtitle' => 'Talent Discovery',
        'description' => 'Bộ công cụ trắc nghiệm & bài đánh giá chuyên sâu giúp phát hiện sớm thế mạnh và xu hướng phát triển cá nhân.',
        'features' => ['Trắc nghiệm định hướng', 'Phân tích điểm mạnh/yếu', 'Gợi ý lộ trình cá nhân'],
        'icon_type' => 'compass'
    ],
    [
        'id' => '03',
        'title' => 'Hoạt động & sân chơi',
        'subtitle' => 'Activities & Events',
        'description' => 'Quản lý và tham gia các cuộc thi, câu lạc bộ, sự kiện ngoại khóa và phong trào tài năng phong phú.',
        'features' => ['Đăng ký sự kiện 1-click', 'Theo dõi lịch hoạt động', 'Ghi nhận tích điểm tài năng'],
        'icon_type' => 'trophy'
    ],
    [
        'id' => '04',
        'title' => 'Check-in QR Smart',
        'subtitle' => 'Smart QR Check-in',
        'description' => 'Điểm danh và xác thực tham gia sự kiện, hoạt động nhanh chóng qua mã QR Code chuẩn xác, bảo mật.',
        'features' => ['Điểm danh tức thì < 1s', 'Tự động lưu lịch sử', 'Chống gian lận chuyên nghiệp'],
        'icon_type' => 'qrcode'
    ],
    [
        'id' => '05',
        'title' => 'AI Analytics & Insights',
        'subtitle' => 'AI Intelligence',
        'description' => 'Trí tuệ nhân tạo phân tích lộ trình phát triển, đưa ra gợi ý học tập và dự báo tiềm năng đột phá.',
        'features' => ['Báo cáo xu hướng tiến bộ', 'Dự báo tiềm năng tương lai', 'Khuyên học tập thông minh'],
        'icon_type' => 'ai'
    ],
    [
        'id' => '06',
        'title' => 'Dashboard Nhà trường',
        'subtitle' => 'School Command Center',
        'description' => 'Báo cáo trực quan dành cho ban giám hiệu để theo dõi, thống kê hoạt động toàn trường minh bạch.',
        'features' => ['Thống kê phong trào toàn trường', 'Xuất báo cáo định kỳ', 'Quản lý phân quyền hệ thống'],
        'icon_type' => 'dashboard'
    ]
];

// Target Audiences Data
$audiences = [
    [
        'role' => 'student',
        'title' => 'Học sinh / Sinh viên',
        'subtitle' => 'Khai phá tiềm năng & Định hướng tương lai',
        'icon_class' => 'student',
        'badge' => 'Dành cho thế hệ trẻ',
        'description' => 'Xây dựng Hồ sơ Năng lực 360°, tham gia các sân chơi tài năng uy tín và mở rộng cơ hội học bổng & việc làm từ các doanh nghiệp hàng đầu.',
        'benefits' => [
            'Lưu giữ trọn vẹn lịch sử thành tích và chứng chỉ số',
            'Nhận phân tích và gợi ý phát triển từ công cụ AI',
            'Tiếp cận cơ hội học bổng và kết nối nhà tuyển dụng sớm'
        ],
        'cta_text' => 'Bắt đầu tạo Hồ sơ Năng lực'
    ],
    [
        'role' => 'teacher',
        'title' => 'Giáo viên / Cố vấn',
        'subtitle' => 'Đồng hành & Quản lý phát triển học viên',
        'icon_class' => 'teacher',
        'badge' => 'Dành cho nhà giáo',
        'description' => 'Quản lý lớp học và các câu lạc bộ tài năng, theo dõi tiến bộ khoa học, ghi nhận thành tích và lập kế hoạch giảng dạy cá nhân hóa.',
        'benefits' => [
            'Theo dõi sát sao tiến độ phát triển của từng học viên',
            'Đánh giá năng khiếu và kỹ năng dựa trên dữ liệu thực tế',
            'Tiết kiệm thời gian lập báo cáo và quản lý danh sách'
        ],
        'cta_text' => 'Khám phá công cụ Quản lý Lớp'
    ],
    [
        'role' => 'school',
        'title' => 'Nhà trường',
        'subtitle' => 'Số hóa quản lý & Nâng cao uy tín giáo dục',
        'icon_class' => 'school',
        'badge' => 'Dành cho Ban Giám hiệu',
        'description' => 'Số hóa công tác quản lý hoạt động ngoại khóa, tự động hóa điểm danh QR, tổng hợp báo cáo minh bạch và khẳng định chất lượng đào tạo.',
        'benefits' => [
            'Quản lý tập trung toàn bộ phong trào ngoại khóa & CLB',
            'Tự động hóa điểm danh QR Code nhanh chóng và chuẩn xác',
            'Tổng hợp báo cáo số liệu hỗ trợ công tác kiểm định chất lượng'
        ],
        'cta_text' => 'Đăng ký Giải pháp cho Nhà trường'
    ],
    [
        'role' => 'enterprise',
        'title' => 'Doanh nghiệp',
        'subtitle' => 'Kết nối tài năng trẻ & Tuyển dụng sớm',
        'icon_class' => 'enterprise',
        'badge' => 'Dành cho Nhà tuyển dụng',
        'description' => 'Tiếp cận nguồn nhân lực tài năng trẻ ngay từ sớm, tài trợ các cuộc thi/sân chơi phát triển và đánh giá ứng viên qua dữ liệu thực tế.',
        'benefits' => [
            'Tiếp cận và thu hút nhân tài phù hợp ngay từ ghế nhà trường',
            'Đồng hành xây dựng thương hiệu tuyển dụng qua các sân chơi',
            'Đánh giá năng lực ứng viên chính xác qua hồ sơ 360°'
        ],
        'cta_text' => 'Truy cập Enterprise Dashboard'
    ]
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="./assets/images/logo.svg" type="image/svg+xml">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub - Nền tảng phát triển và kết nối năng khiếu hàng đầu dành cho Học sinh, Giáo viên, Nhà trường và Doanh nghiệp.">
    <title>TalentHub | Nền tảng phát triển và kết nối năng khiếu</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/brand-component.css">
    <link rel="stylesheet" href="assets/css/polish.css">
</head>
<body class="landing-page">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>

    <!-- ====================================================================
         1. Header & Navigation Section
         ==================================================================== -->
    <header class="site-header" id="site-header">
        <div class="container site-header__container">
            <!-- Brand Logo -->
            <?php renderBrandHeader('#hero', 'Nền tảng phát triển năng khiếu', 'Trang chủ FTalentHub', 'site-header__brand learner-brand'); if (false): ?>
                <span class="learner-brand__mark" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>
                    </svg>
                </span>
                <div class="learner-brand__text">
                    <span class="learner-brand__name">FTalent<span>Hub</span></span>
                    <span class="learner-brand__subtitle">Nền tảng phát triển năng khiếu</span>
                </div>
            <?php endif; ?>

            <!-- Navigation Links (Desktop) -->
            <nav class="site-nav" aria-label="Điều hướng chính">
                <a href="#hero" class="site-nav__link">Về TalentHub</a>
                <a href="#statistics" class="site-nav__link">Thống kê</a>
                <a href="#modules" class="site-nav__link">Tính năng (8 mô-đun)</a>
                <a href="#audiences" class="site-nav__link">Đối tượng</a>
            </nav>

            <!-- Header Actions -->
            <div class="site-header__actions">
                <a href="login.php" class="btn btn-secondary site-header__login-btn" data-cta="login">
                    Đăng nhập
                </a>
                
                <a href="./role-selection.php" class="btn btn-primary site-header__app-btn">
                    Đăng ký
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>

                <!-- Mobile Hamburger Toggle -->
                <button class="site-header__mobile-toggle" id="mobile-toggle-btn" aria-label="Mở menu điều hướng" aria-controls="mobile-menu" aria-expanded="false">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12" class="hamburger-line line-top"></line>
                        <line x1="3" y1="6" x2="21" y2="6" class="hamburger-line line-mid"></line>
                        <line x1="3" y1="18" x2="21" y2="18" class="hamburger-line line-bot"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Drawer Overlay -->
        <div class="mobile-menu" id="mobile-menu" aria-hidden="true">
            <nav class="mobile-menu__nav" aria-label="Điều hướng di động">
                <a href="#hero" class="mobile-menu__link">Về TalentHub</a>
                <a href="#statistics" class="mobile-menu__link">Thống kê</a>
                <a href="#modules" class="mobile-menu__link">Tính năng (8 mô-đun)</a>
                <a href="#audiences" class="mobile-menu__link">Đối tượng</a>
                
                <div class="mobile-menu__actions">
                    <a href="login.php" class="btn btn-secondary mobile-menu__btn" data-cta="login">Đăng nhập</a>
                    <a href="./role-selection.php" class="btn btn-primary mobile-menu__btn">
                        Đăng ký
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <main id="main-content">
        <!-- ================================================================
             2. Hero Section (Editorial 2-Column + Product Window Canvas)
             ================================================================ -->
        <section class="hero-section" id="hero">
            <div class="container">
                <div class="hero-grid">
                    <!-- Left Content -->
                    <div class="hero-content">
                        <div class="hero-badge">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M12 8v4l3 3"></path>
                            </svg>
                            ✨ Nền tảng Đột phá 2026
                        </div>
                        <h1 class="hero-title">
                            Khám phá năng khiếu – <span class="hero-title-highlight">Bứt phá tương lai</span>
                        </h1>
                        <p class="hero-description">
                            TalentHub giúp học sinh ghi nhận hồ sơ năng lực 360°, kết nối nhà trường, giáo viên và doanh nghiệp nhằm định hướng và tối ưu hóa tiềm năng của thế hệ trẻ.
                        </p>
                        <div class="hero-cta-group">
                            <a href="./login.php" class="btn btn-primary" data-cta="app">
                                Trải nghiệm ngay
                                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </a>
                            <a href="#modules" class="btn btn-secondary">
                                Xem 8 module
                                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 9l-7 7-7-7"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Right Showcase UI Window Frame -->
                    <div class="hero-visual">
                        <div class="hero-window-frame">
                            <!-- Window Control Bar -->
                            <div class="window-bar">
                                <div class="window-dots">
                                    <span class="dot red"></span>
                                    <span class="dot yellow"></span>
                                    <span class="dot green"></span>
                                </div>
                                <div class="window-address-bar">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    talenthub.vn/passport/TH-882910
                                </div>
                                <span class="window-tag">Live UI</span>
                            </div>

                            <!-- Showcase Content Inside Window -->
                            <div class="window-content">
                                <div class="hero-preview-header">
                                    <div class="user-profile-preview">
                                        <div class="avatar-placeholder">TH</div>
                                        <div class="user-info">
                                            <h4>Hồ sơ Học viên Tài năng</h4>
                                            <p>Mã ID: #TH-882910</p>
                                        </div>
                                    </div>
                                    <div class="status-chip">
                                        <span class="status-dot"></span> Đã xác thực AI
                                    </div>
                                </div>

                                <div class="talent-metric-grid">
                                    <div class="metric-box">
                                        <label>Chỉ số Năng khiếu</label>
                                        <div class="val accent">98.5 / 100</div>
                                    </div>
                                    <div class="metric-box">
                                        <label>Hoạt động & Giải thưởng</label>
                                        <div class="val">24 Huy chương</div>
                                    </div>
                                    <div class="metric-box">
                                        <label>Điểm danh QR</label>
                                        <div class="val">100% Chính xác</div>
                                    </div>
                                    <div class="metric-box">
                                        <label>Kết nối Doanh nghiệp</label>
                                        <div class="val accent">Top 5% Xuất sắc</div>
                                    </div>
                                </div>

                                <div class="hero-progress-box">
                                    <div class="hero-progress-info">
                                        <span>Lộ trình Học bổng Doanh nghiệp</span>
                                        <span class="hero-progress-percent">85% Hoàn thành</span>
                                    </div>
                                    <div class="hero-progress-track">
                                        <div class="hero-progress-fill"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================================================================
             3. Platform Statistics Section (#statistics - Editorial Split Layout)
             ================================================================ -->
        <section class="stats-section section-padding" id="statistics">
            <div class="container">
                <div class="stats-split-container">
                    <!-- Left Editorial Intro -->
                    <div class="stats-editorial-left">
                        <span class="section-tag">Thống kê Nền tảng</span>
                        <h2 class="section-title">Những Con Số Ấn Tượng</h2>
                        <p class="section-description">
                            Minh chứng thực tế cho quy mô kết nối và giá trị mà TalentHub mang lại cho cộng đồng giáo dục.
                        </p>
                    </div>

                    <!-- Right High-Contrast Metric Numbers -->
                    <div class="stats-strip">
                        <div class="stat-col">
                            <div class="stat-number" data-target="50000" data-suffix="+">50,000+</div>
                            <div class="stat-label">Học sinh / Sinh viên</div>
                            <div class="stat-sub">Đã tạo Passport 360°</div>
                        </div>

                        <div class="stat-col">
                            <div class="stat-number" data-target="150" data-suffix="+">150+</div>
                            <div class="stat-label">Nhà trường đồng hành</div>
                            <div class="stat-sub">THPT, Cao đẳng & ĐH</div>
                        </div>

                        <div class="stat-col">
                            <div class="stat-number" data-target="500" data-suffix="+">500+</div>
                            <div class="stat-label">Hoạt động / Tháng</div>
                            <div class="stat-sub">Sự kiện & Sân chơi</div>
                        </div>

                        <div class="stat-col">
                            <div class="stat-number" data-target="80" data-suffix="+">80+</div>
                            <div class="stat-label">Doanh nghiệp liên kết</div>
                            <div class="stat-sub">Tuyển dụng & Tài trợ</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================================================================
             4. Core Modules Section (#modules - Asymmetric Bento Grid)
             ================================================================ -->
        <section class="modules-section section-padding" id="modules">
            <div class="container">
                <div class="section-header">
                    <span class="section-tag">Tính năng Cốt lõi</span>
                    <h2 class="section-title">Hệ thống 8 mô-đun trọng tâm</h2>
                    <p class="section-description">
                        Giải pháp toàn diện số hóa lộ trình phát triển tài năng. Hiện tại 6/8 module đã sẵn sàng phục vụ.
                    </p>
                </div>

                <!-- Bento Grid Layout -->
                <div class="bento-grid">
                    <?php foreach ($modules as $index => $mod): 
                        $bentoClass = 'bento-card';
                        if ($mod['id'] === '01') $bentoClass .= ' bento-card--hero';
                        if ($mod['id'] === '04') $bentoClass .= ' bento-card--highlight';
                        if ($mod['id'] === '05') $bentoClass .= ' bento-card--ai';
                        if ($mod['id'] === '06') $bentoClass .= ' bento-card--wide';
                    ?>
                        <article class="<?= $bentoClass; ?>">
                            <div class="module-header">
                                <div class="module-icon-box">
                                    <?php if ($mod['icon_type'] === 'passport'): ?>
                                        <svg class="module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    <?php elseif ($mod['icon_type'] === 'compass'): ?>
                                        <svg class="module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"></polygon>
                                        </svg>
                                    <?php elseif ($mod['icon_type'] === 'trophy'): ?>
                                        <svg class="module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                                            <path d="M4 22h16"></path>
                                            <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path>
                                            <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path>
                                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path>
                                        </svg>
                                    <?php elseif ($mod['icon_type'] === 'qrcode'): ?>
                                        <svg class="module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="3" width="7" height="7"></rect>
                                            <rect x="14" y="14" width="7" height="7"></rect>
                                            <rect x="3" y="14" width="7" height="7"></rect>
                                        </svg>
                                    <?php elseif ($mod['icon_type'] === 'ai'): ?>
                                        <svg class="module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                                            <rect x="9" y="9" width="6" height="6"></rect>
                                            <line x1="9" y1="1" x2="9" y2="4"></line>
                                            <line x1="15" y1="1" x2="15" y2="4"></line>
                                            <line x1="9" y1="20" x2="9" y2="23"></line>
                                            <line x1="15" y1="20" x2="15" y2="23"></line>
                                            <line x1="20" y1="9" x2="23" y2="9"></line>
                                            <line x1="20" y1="15" x2="23" y2="15"></line>
                                            <line x1="1" y1="9" x2="4" y2="9"></line>
                                            <line x1="1" y1="15" x2="4" y2="15"></line>
                                        </svg>
                                    <?php else: ?>
                                        <svg class="module-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                                            <path d="M3 9h18"></path>
                                            <path d="M9 21V9"></path>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                                <span class="module-tag">Mô-đun <?= htmlspecialchars($mod['id']); ?></span>
                            </div>

                            <div class="bento-card-body">
                                <h3 class="module-title"><?= htmlspecialchars($mod['title']); ?></h3>
                                <div class="module-subtitle"><?= htmlspecialchars($mod['subtitle']); ?></div>
                                <p class="module-description"><?= htmlspecialchars($mod['description']); ?></p>

                                <div class="module-features">
                                    <?php foreach ($mod['features'] as $feat): ?>
                                        <div class="module-feature-item">
                                            <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            <span><?= htmlspecialchars($feat); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <a href="./login.php" class="module-footer-link">
                                Trải nghiệm module
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </article>
                    <?php endforeach; ?>

                    <!-- Expansion Banner for Modules 7 & 8 -->
                    <div class="modules-expansion-banner">
                        <div class="expansion-info">
                            <h4>
                                ✨ Mô-đun 07 & 08
                                <span class="expansion-badge">Sắp ra mắt</span>
                            </h4>
                            <p>
                                Đội ngũ TalentHub đang phát triển và hoàn thiện 2 module tiếp theo nhằm mở rộng thêm khả năng định hướng sự nghiệp và kết nối quỹ tài trợ chuyên sâu.
                            </p>
                        </div>
                        <a href="./role-selection.php" class="btn btn-primary expansion-btn" data-cta="register">
                            Đăng ký nhận thông báo
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================================================================
             5. Target Audiences Section (#audiences - Interactive Role Tabs)
             ================================================================ -->
        <section class="audiences-section section-padding" id="audiences">
            <div class="container">
                <div class="section-header">
                    <span class="section-tag">Đối tượng sử dụng</span>
                    <h2 class="section-title">Giải pháp cho mọi đối tượng</h2>
                    <p class="section-description">
                        TalentHub thiết kế hệ sinh thái chuyên biệt mang lại giá trị thiết thực và kết nối hiệu quả 4 nhóm người dùng.
                    </p>
                </div>

                <!-- Interactive Audience Role Showcase Panel -->
                <div class="audience-showcase">
                    <!-- Left Vertical Tab Selector -->
                    <div class="audience-tabs" role="tablist" aria-label="Danh mục đối tượng">
                        <?php foreach ($audiences as $idx => $aud): ?>
                            <button class="audience-tab-btn <?= $idx === 0 ? 'is-active' : ''; ?>"
                                    id="audience-tab-<?= htmlspecialchars($aud['role']); ?>"
                                    type="button"
                                    data-target="role-panel-<?= htmlspecialchars($aud['role']); ?>"
                                    role="tab"
                                    aria-controls="role-panel-<?= htmlspecialchars($aud['role']); ?>"
                                    aria-selected="<?= $idx === 0 ? 'true' : 'false'; ?>"
                                    tabindex="<?= $idx === 0 ? '0' : '-1'; ?>">
                                <span class="audience-icon <?= htmlspecialchars($aud['icon_class']); ?>">
                                    <?php if ($aud['role'] === 'student'): ?>
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                                    <?php elseif ($aud['role'] === 'teacher'): ?>
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                                    <?php elseif ($aud['role'] === 'school'): ?>
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"></path><path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path><path d="M9 10h2v2H9zM13 10h2v2h-2zM9 14h2v2H9zM13 14h2v2h-2z"></path></svg>
                                    <?php else: ?>
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                    <?php endif; ?>
                                </span>
                                <span class="tab-label">
                                    <span class="tab-title"><?= htmlspecialchars($aud['title']); ?></span>
                                    <span class="tab-subtitle"><?= htmlspecialchars($aud['subtitle']); ?></span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Right Dynamic Audience Panel -->
                    <div class="audience-panels">
                        <?php foreach ($audiences as $idx => $aud): ?>
                            <article class="audience-panel <?= $idx === 0 ? 'is-active' : ''; ?>" 
                                     id="role-panel-<?= htmlspecialchars($aud['role']); ?>"
                                     role="tabpanel"
                                     aria-labelledby="audience-tab-<?= htmlspecialchars($aud['role']); ?>"
                                     tabindex="0"
                                     <?= $idx === 0 ? '' : 'hidden'; ?>>
                                <div class="panel-header">
                                    <span class="panel-badge"><?= htmlspecialchars($aud['badge']); ?></span>
                                    <h3 class="panel-title"><?= htmlspecialchars($aud['title']); ?></h3>
                                    <p class="panel-description"><?= htmlspecialchars($aud['description']); ?></p>
                                </div>

                                <div class="panel-benefits">
                                    <div class="benefits-title">Lợi ích vượt trội dành cho bạn:</div>
                                    <ul>
                                        <?php foreach ($aud['benefits'] as $benefit): ?>
                                            <li class="benefit-row">
                                                <svg class="benefit-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                <span><?= htmlspecialchars($benefit); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <div class="panel-footer">
                                    <a href="./role-selection.php" class="btn btn-primary" data-cta="register">
                                        <?= htmlspecialchars($aud['cta_text']); ?>
                                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- ================================================================
             6. Final CTA Section (#app / #cta)
             ================================================================ -->
        <section class="cta-section" id="app">
            <div class="container">
                <div class="cta-box">
                    <h2 class="cta-title">Sẵn sàng bứt phá cùng TalentHub?</h2>
                    <p class="cta-description">
                        Gia nhập nền tảng ngay hôm nay để khai phá tiềm năng, xây dựng hồ sơ năng lực 360° và kết nối hàng ngàn cơ hội phát triển đột phá.
                    </p>
                    <div class="cta-buttons">
                        <a href="./login.php" class="btn btn-white" data-cta="app">
                            Trải nghiệm ngay
                            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <a href="#contact" class="btn btn-outline-white">
                            Liên hệ tư vấn
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ====================================================================
         7. Footer Section
         ==================================================================== -->
    <footer class="footer" id="contact">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand Info -->
                <div class="footer-brand">
                    <?php renderBrandHeader('#hero', 'Nền tảng phát triển năng khiếu', 'Trang chủ FTalentHub', 'brand-logo learner-brand'); if (false): ?>
                        <span class="learner-brand__mark" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>
                            </svg>
                        </span>
                        <div class="learner-brand__text">
                            <span class="learner-brand__name">FTalent<span>Hub</span></span>
                            <span class="learner-brand__subtitle">Nền tảng phát triển năng khiếu</span>
                        </div>
                    <?php endif; ?>
                    <p>
                        Nền tảng phát triển và kết nối năng khiếu hàng đầu dành cho Học sinh, Giáo viên, Nhà trường và Doanh nghiệp.
                    </p>
                </div>

                <!-- Column 1: Links -->
                <div>
                    <h4 class="footer-title">Khám phá</h4>
                    <ul class="footer-links">
                        <li><a href="#hero">Về TalentHub</a></li>
                        <li><a href="#statistics">Thống kê nền tảng</a></li>
                        <li><a href="#modules">8 mô-đun hệ thống</a></li>
                        <li><a href="#audiences">Đối tượng người dùng</a></li>
                    </ul>
                </div>

                <!-- Column 2: Legal & Support -->
                <div>
                    <h4 class="footer-title">Chính sách & hỗ trợ</h4>
                    <ul class="footer-links">
                        <li><a href="#">Điều khoản sử dụng</a></li>
                        <li><a href="#">Chính sách bảo mật</a></li>
                        <li><a href="#">Hướng dẫn sử dụng</a></li>
                        <li><a href="#">Câu hỏi thường gặp</a></li>
                    </ul>
                </div>

                <!-- Column 3: Contact Info -->
                <div>
                    <h4 class="footer-title">Thông tin liên hệ</h4>
                    <ul class="footer-links">
                        <li>Email: contact@talenthub.vn</li>
                        <li>Hotline: 1900 8899</li>
                        <li>Địa chỉ: Hà Nội & TP. Hồ Chí Minh</li>
                        <li>Thời gian: 8:00 - 17:30 (Thứ 2 - Thứ 6)</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y'); ?> TalentHub. Tất cả quyền được bảo lưu.</p>
                <p>Thiết kế dành riêng cho hệ sinh thái giáo dục và phát triển tài năng.</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript Assets -->
    <script src="assets/js/home.js"></script>
</body>
</html>
