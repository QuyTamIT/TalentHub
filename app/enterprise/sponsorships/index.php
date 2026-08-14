<?php
/**
 * TalentHub Enterprise - Project Sponsorships ("Tài trợ dự án") Module
 * 
 * Production-Grade SaaS Interface for Enterprises to discover, support,
 * fund, and track innovative student & university research projects.
 */

require_once __DIR__ . '/../includes/sponsorships-data.php';

$enterpriseInfo = [
    'company_name' => 'FPT Software',
    'account_type' => 'Gói Premium',
    'logo_initials' => 'FPT',
    'new_matches_count' => 86,
    'total_talents' => 1247
];

$pageTitle = 'Tài trợ dự án';
$currentRoute = '/app/enterprise/sponsorships/';

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
        'active' => false
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
        'active' => true
    ],
    [
        'title' => 'Phân tích tuyển dụng',
        'route' => '/app/enterprise/analytics',
        'icon' => 'bar-chart',
        'active' => false
    ]
];

$metrics = getSponsorshipMetrics();
$filterOptions = getSponsorshipFilterOptions();
$projects = getMockProjects();
$mySponsorships = getMySponsorships();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Nền tảng tài trợ và đồng hành cùng các dự án đổi mới sáng tạo, tài năng trẻ từ học sinh, sinh viên và các trường học đối tác - TalentHub Enterprise.">
    <title><?= htmlspecialchars($pageTitle); ?> | TalentHub Enterprise</title>

    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/enterprise.css">
    <link rel="stylesheet" href="../../../assets/css/enterprise-sponsorships.css">
</head>
<body class="enterprise-dashboard">

    <!-- Layout Wrapper -->
    <div class="ent-layout">
        
        <!-- Sidebar Partial -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="ent-main-wrapper">
            
            <!-- Top Header Partial -->
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <!-- Page Body Content -->
            <main class="ent-body">
                <div class="container-fluid">
                
                <!-- Page Title Header Row -->
                <div class="ent-page-header" style="margin-bottom: 1.5rem;">
                    <div>
                        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem;">
                            Doanh nghiệp • Ươm mầm tài năng
                        </div>
                        <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.35rem; letter-spacing: -0.02em;">
                            <?= htmlspecialchars($pageTitle); ?>
                        </h1>
                        <p style="font-size: 0.9375rem; color: var(--text-secondary);">
                            Đồng hành, đầu tư và ươm mầm các dự án sáng tạo, đề tài nghiên cứu từ học sinh, sinh viên và trường học.
                        </p>
                    </div>
                </div>

                <!-- 1. Summary Metrics Strip -->
                <div class="spon-metrics-grid">
                    <div class="spon-metric-card">
                        <div class="spon-metric-icon orange">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="6" x2="12" y2="18"></line>
                                <line x1="6" y1="12" x2="18" y2="12"></line>
                            </svg>
                        </div>
                        <div class="spon-metric-info">
                            <span class="spon-metric-label">Tổng số tiền đã tài trợ</span>
                            <span class="spon-metric-value"><?= $metrics['total_sponsored_formatted']; ?></span>
                            <span class="spon-metric-sub">Đã giải ngân & Cam kết đồng hành</span>
                        </div>
                    </div>

                    <div class="spon-metric-card">
                        <div class="spon-metric-icon blue">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                        </div>
                        <div class="spon-metric-info">
                            <span class="spon-metric-label">Số dự án đã tài trợ</span>
                            <span class="spon-metric-value"><?= $metrics['total_projects_sponsored']; ?> dự án</span>
                            <span class="spon-metric-sub"><?= $metrics['active_sponsorships_count']; ?> dự án đang trong tiến độ</span>
                        </div>
                    </div>

                    <div class="spon-metric-card">
                        <div class="spon-metric-icon green">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <div class="spon-metric-info">
                            <span class="spon-metric-label">Số người học được hỗ trợ</span>
                            <span class="spon-metric-value"><?= $metrics['total_learners_supported']; ?> tài năng</span>
                            <span class="spon-metric-sub">Học sinh, sinh viên & Nghiên cứu sinh</span>
                        </div>
                    </div>
                </div>

                <!-- 2. Main Tabs Bar -->
                <div class="spon-tabs-container">
                    <div class="spon-tabs-nav">
                        <button class="spon-tab-btn is-active" data-tab="discover">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <span>Khám phá dự án</span>
                            <span class="spon-tab-count"><?= count($projects); ?></span>
                        </button>

                        <button class="spon-tab-btn" data-tab="my-sponsorships">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Đã tài trợ</span>
                            <span class="spon-tab-count"><?= count($mySponsorships); ?></span>
                        </button>
                    </div>

                    <div style="font-size: 0.8125rem; color: var(--text-secondary); font-weight: 500;">
                        ✨ Kết nối trực tiếp đội ngũ nghiên cứu & Trường học
                    </div>
                </div>

                <!-- 3. TAB 1: Khám phá dự án -->
                <div class="spon-tab-pane" id="tab-discover" style="display: block;">
                    
                    <!-- Search & Multi-criteria Filter Bar -->
                    <div class="spon-filter-card">
                        <div class="spon-filter-grid">
                            
                            <!-- Search Input -->
                            <div class="spon-search-box">
                                <svg class="spon-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                <input type="text" id="spon-search-input" class="spon-input" placeholder="Tìm theo tên dự án, từ khóa...">
                            </div>

                            <!-- Lĩnh vực Filter -->
                            <div>
                                <select id="spon-category-select" class="spon-select" aria-label="Lĩnh vực">
                                    <?php foreach ($filterOptions['categories'] as $key => $label): ?>
                                        <option value="<?= htmlspecialchars($key); ?>"><?= htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Trường Filter -->
                            <div>
                                <select id="spon-school-select" class="spon-select" aria-label="Trường học">
                                    <?php foreach ($filterOptions['schools'] as $key => $label): ?>
                                        <option value="<?= htmlspecialchars($key); ?>"><?= htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Mức tài trợ Filter -->
                            <div>
                                <select id="spon-range-select" class="spon-select" aria-label="Mức tài trợ">
                                    <?php foreach ($filterOptions['target_ranges'] as $key => $label): ?>
                                        <option value="<?= htmlspecialchars($key); ?>"><?= htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Trạng thái Filter -->
                            <div>
                                <select id="spon-status-select" class="spon-select" aria-label="Trạng thái">
                                    <?php foreach ($filterOptions['statuses'] as $key => $label): ?>
                                        <option value="<?= htmlspecialchars($key); ?>"><?= htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                        </div>

                        <div class="spon-filter-actions">
                            <div class="spon-active-tags">
                                <span style="color: var(--text-secondary); font-weight: 600;">Bộ lọc đang chọn:</span>
                                <span class="spon-tag-pill">Tất cả dự án đổi mới sáng tạo</span>
                            </div>
                            <button id="spon-reset-filters" class="spon-reset-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 4v6h-6"></path>
                                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                </svg>
                                <span>Đặt lại bộ lọc</span>
                            </button>
                        </div>
                    </div>

                    <!-- Discover Projects Cards Grid -->
                    <div class="spon-projects-grid" id="spon-projects-container">
                        <?php foreach ($projects as $proj): ?>
                            <article class="spon-project-card" 
                                     data-title="<?= htmlspecialchars($proj['title']); ?>"
                                     data-category="<?= htmlspecialchars($proj['category']); ?>"
                                     data-school="<?= htmlspecialchars($proj['school_name']); ?>"
                                     data-status="<?= htmlspecialchars($proj['status']); ?>"
                                     data-target="<?= $proj['target_amount']; ?>">
                                
                                <div>
                                    <div class="spon-card-top">
                                        <span class="spon-category-badge"><?= htmlspecialchars($proj['category']); ?></span>
                                        <span class="spon-school-badge">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                                            </svg>
                                            <?= htmlspecialchars($proj['school_badge']); ?>
                                        </span>
                                    </div>

                                    <h3 class="spon-project-title"><?= htmlspecialchars($proj['title']); ?></h3>
                                    <p class="spon-project-desc"><?= htmlspecialchars($proj['description']); ?></p>

                                    <div class="spon-project-meta-row">
                                        <div class="spon-meta-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                            </svg>
                                            <span><?= $proj['members_count']; ?> thành viên</span>
                                        </div>
                                        <span>•</span>
                                        <div class="spon-meta-item">
                                            <span><?= htmlspecialchars($proj['school_name']); ?></span>
                                        </div>
                                    </div>

                                    <!-- Funding Progress Visualization -->
                                    <div class="spon-progress-box">
                                        <div class="spon-progress-header">
                                            <span>Đã gọi tài trợ</span>
                                            <div>
                                                <span class="spon-progress-raised"><?= number_format($proj['raised_amount'], 0, ',', '.'); ?> đ</span>
                                                <span class="spon-progress-target">/ <?= number_format($proj['target_amount'], 0, ',', '.'); ?> đ</span>
                                            </div>
                                        </div>

                                        <div class="spon-progress-track">
                                            <div class="spon-progress-fill" style="width: <?= min(100, $proj['percentage']); ?>%;"></div>
                                        </div>

                                        <div class="spon-progress-footer">
                                            <span class="spon-percent-tag">Tiến độ: <?= $proj['percentage']; ?>%</span>
                                            <span style="color: var(--text-muted);"><?= $proj['sponsors_count']; ?> nhà tài trợ đồng hành</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions Footer -->
                                <div class="spon-card-actions">
                                    <button class="btn btn-secondary btn-view-detail" data-project-id="<?= $proj['id']; ?>">
                                        Xem chi tiết
                                    </button>
                                    <button class="btn btn-primary btn-sponsor-now" data-project-id="<?= $proj['id']; ?>">
                                        Tài trợ ngay
                                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M5 12h14M12 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Empty state -->
                    <div id="spon-projects-empty" style="display: none; text-align: center; padding: 4rem 1rem; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-xl);">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="color: var(--text-muted); margin-bottom: 1rem;">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <h4 style="font-size: 1.125rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">Không tìm thấy dự án phù hợp</h4>
                        <p style="font-size: 0.875rem; color: var(--text-secondary); max-width: 420px; margin: 0 auto 1.5rem auto;">
                            Vui lòng thử điều chỉnh lại từ khóa tìm kiếm hoặc chọn lại tiêu chí trong các bộ lọc.
                        </p>
                    </div>

                </div>

                <!-- 4. TAB 2: Đã tài trợ -->
                <div class="spon-tab-pane" id="tab-my-sponsorships" style="display: none;">
                    <div class="spon-my-list">
                        <?php foreach ($mySponsorships as $item): ?>
                            <article class="spon-my-card">
                                
                                <!-- Info Column -->
                                <div class="spon-my-info">
                                    <div class="spon-my-title-row">
                                        <span class="spon-status-badge <?= $item['status_badge_class']; ?>">
                                            <?= htmlspecialchars($item['status_label']); ?>
                                        </span>
                                        <span style="font-size: 0.8125rem; color: var(--text-muted);">
                                            Tài trợ ngày <?= $item['sponsored_date']; ?>
                                        </span>
                                    </div>

                                    <h3 class="spon-my-title"><?= htmlspecialchars($item['project_title']); ?></h3>

                                    <div style="font-size: 0.84375rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem;">
                                        <strong><?= htmlspecialchars($item['school_name']); ?></strong>
                                        <span>•</span>
                                        <span><?= htmlspecialchars($item['category']); ?></span>
                                    </div>

                                    <!-- Latest Update Box -->
                                    <div class="spon-update-box">
                                        <div class="spon-update-header">
                                            <span>📢 CẬP NHẬT MỚI NHẤT (<?= $item['latest_update']['date']; ?>)</span>
                                            <span><?= htmlspecialchars($item['latest_update']['author']); ?></span>
                                        </div>
                                        <div class="spon-update-title"><?= htmlspecialchars($item['latest_update']['title']); ?></div>
                                        <div class="spon-update-summary"><?= htmlspecialchars($item['latest_update']['summary']); ?></div>
                                    </div>
                                </div>

                                <!-- Funding & Progress Stats Column -->
                                <div class="spon-my-stats">
                                    <div class="spon-stat-item">
                                        <label>Số tiền đã tài trợ</label>
                                        <div class="val"><?= number_format($item['sponsored_amount'], 0, ',', '.'); ?> VNĐ</div>
                                    </div>

                                    <div class="spon-stat-item">
                                        <label>Tiến độ gọi vốn dự án</label>
                                        <div style="font-size: 0.9375rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem;">
                                            <?= number_format($item['total_raised'], 0, ',', '.'); ?> / <?= number_format($item['target_amount'], 0, ',', '.'); ?> đ (<?= $item['percentage']; ?>%)
                                        </div>
                                        <div class="spon-progress-track" style="height: 6px;">
                                            <div class="spon-progress-fill" style="width: <?= min(100, $item['percentage']); ?>%;"></div>
                                        </div>
                                    </div>

                                    <div style="font-size: 0.78125rem; color: var(--text-secondary);">
                                        🎯 Cột mốc tiếp theo: <strong><?= htmlspecialchars($item['next_milestone']); ?></strong>
                                    </div>
                                </div>

                                <!-- Action Column -->
                                <div class="spon-my-actions">
                                    <button class="btn btn-secondary btn-track-progress" data-sponsorship-id="<?= $item['id']; ?>" style="white-space: nowrap;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                        </svg>
                                        Theo dõi tiến độ
                                    </button>
                                    <button class="btn btn-primary btn-view-detail" data-project-id="<?= $item['project_id']; ?>">
                                        Chi tiết dự án
                                    </button>
                                </div>

                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>

                    </div>
                </div>
            </main>
        </div>

    <!-- ====================================================================
         5. Project Detail View Modal Component
         ==================================================================== -->
    <div class="spon-modal-overlay" id="project-detail-modal" aria-hidden="true">
        <div class="spon-modal-dialog">
            
            <!-- Modal Header -->
            <div class="spon-modal-header">
                <div class="spon-modal-header-info">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="spon-category-badge" id="modal-category-badge">AI & Machine Learning</span>
                        <span class="spon-school-badge" id="modal-school-badge">HUST • ĐH Bách Khoa Hà Nội</span>
                        <span class="spon-status-badge badge-success" id="modal-status-badge">Đang gọi tài trợ</span>
                    </div>
                    <h3 class="spon-modal-title" id="modal-project-title">Tên dự án chi tiết</h3>
                </div>
                <button class="spon-modal-close" id="close-detail-modal" aria-label="Đóng modal">&times;</button>
            </div>

            <!-- Modal Body -->
            <div class="spon-modal-body">
                
                <!-- Section 1: Overview & Problem - Solution -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        Tổng quan & Vấn đề - Giải pháp
                    </h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                        <div style="background: var(--background); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.125rem;">
                            <h5 style="font-size: 0.875rem; font-weight: 700; color: #DC2626; margin-bottom: 0.5rem; text-transform: uppercase;">⚠️ Vấn đề thực tế</h5>
                            <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;" id="modal-problem-desc">Nội dung vấn đề...</p>
                        </div>
                        <div style="background: var(--background); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.125rem;">
                            <h5 style="font-size: 0.875rem; font-weight: 700; color: var(--accent); margin-bottom: 0.5rem; text-transform: uppercase;">💡 Giải pháp đột phá</h5>
                            <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;" id="modal-solution-desc">Nội dung giải pháp...</p>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Team Members -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                        Đội ngũ thực hiện dự án
                    </h4>
                    
                    <!-- Leader Card -->
                    <div style="background: linear-gradient(135deg, #FFFFFF 0%, #FFF7ED 100%); border: 1px solid rgba(249,115,22,0.3); border-radius: var(--radius-lg); padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div class="spon-avatar" id="modal-leader-avatar">AN</div>
                        <div>
                            <div style="font-size: 0.75rem; font-weight: 700; color: var(--primary); text-transform: uppercase;">PROJECT LEADER</div>
                            <h5 style="font-size: 1rem; font-weight: 800; color: var(--text-primary);" id="modal-leader-name">Nguyễn Văn An</h5>
                            <p style="font-size: 0.8125rem; color: var(--text-secondary);" id="modal-leader-role">Project Lead & AI Engineer</p>
                        </div>
                    </div>

                    <!-- Members List -->
                    <div class="spon-team-grid" id="modal-team-members">
                        <!-- Dynamic JS Rendering -->
                    </div>
                </div>

                <!-- Section 3: Milestones Timeline -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        Cột mốc phát triển & Lộ trình
                    </h4>
                    <div class="spon-timeline" id="modal-milestones-timeline">
                        <!-- Dynamic JS Rendering -->
                    </div>
                </div>

                <!-- Section 4: Expected Use of Funds -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        Kế hoạch sử dụng nguồn vốn tài trợ
                    </h4>
                    <div style="background: var(--background); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1.25rem;" id="modal-fund-allocation">
                        <!-- Dynamic JS Rendering -->
                    </div>
                </div>

                <!-- Section 5: Evidence & Demo Placeholders -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        Tài liệu Pitch Deck & Demo sản phẩm
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.125rem; background: var(--background); border: 1px solid var(--border); border-radius: var(--radius-md);">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 4px; background: #EFF6FF; color: #2563EB;">PDF</span>
                                <span style="font-size: 0.875rem; font-weight: 600; color: var(--text-primary);">Pitch Deck Dự án Đổi mới Sáng tạo 2026</span>
                            </div>
                            <a href="#" onclick="alert('Đang mở file Pitch Deck tài liệu dự án...'); return false;" style="font-size: 0.8125rem; font-weight: 700; color: var(--primary);">Xem tài liệu &rarr;</a>
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.125rem; background: var(--background); border: 1px solid var(--border); border-radius: var(--radius-md);">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 4px; background: #FFF7ED; color: #F97316;">VIDEO</span>
                                <span style="font-size: 0.875rem; font-weight: 600; color: var(--text-primary);">Video Demo Thực tế trên Youtube/Drive</span>
                            </div>
                            <a href="#" onclick="alert('Mở video demo trải nghiệm sản phẩm...'); return false;" style="font-size: 0.8125rem; font-weight: 700; color: var(--primary);">Xem video &rarr;</a>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="spon-modal-footer">
                <div style="font-size: 0.84375rem; color: var(--text-secondary);">
                    🤝 Tài trợ bởi <strong>FPT Software Enterprise Network</strong>
                </div>
                <button class="btn btn-primary" id="modal-sponsor-cta">
                    Tài trợ dự án này ngay
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </button>
            </div>

        </div>
    </div>

    <!-- ====================================================================
         6. Sponsorship Form Modal Component
         ==================================================================== -->
    <div class="spon-modal-overlay" id="sponsorship-form-modal" aria-hidden="true">
        <form class="spon-modal-dialog" id="sponsorship-active-form" style="max-width: 580px;">
            
            <div class="spon-modal-header">
                <div class="spon-modal-header-info">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--primary); text-transform: uppercase;">TÀI TRỢ & ĐỒNG HÀNH</div>
                    <h3 class="spon-modal-title" id="form-project-title">Tên dự án chọn tài trợ</h3>
                    <div style="font-size: 0.8125rem; color: var(--text-secondary);" id="form-target-info">Thông tin mục tiêu tài trợ...</div>
                </div>
                <button type="button" class="spon-modal-close" id="close-sponsorship-modal" aria-label="Đóng modal">&times;</button>
            </div>

            <div class="spon-modal-body">
                
                <!-- Amount Input -->
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">
                        Nhập số tiền tài trợ (VNĐ) <span style="color: #DC2626;">*</span>
                    </label>
                    <div style="position: relative;">
                        <input type="text" id="spon-amount-input" class="spon-input" value="10,000,000" style="padding-right: 3.5rem; font-size: 1.125rem; font-weight: 800; color: var(--primary);">
                        <span style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted);">VNĐ</span>
                    </div>
                </div>

                <!-- Quick Preset Amounts -->
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.8125rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem;">
                        Gợi ý chọn nhanh hạn mức tài trợ:
                    </label>
                    <div class="spon-preset-grid">
                        <button type="button" class="spon-preset-btn" data-amount="5000000">5.000.000đ</button>
                        <button type="button" class="spon-preset-btn is-selected" data-amount="10000000">10.000.000đ</button>
                        <button type="button" class="spon-preset-btn" data-amount="20000000">20.000.000đ</button>
                        <button type="button" class="spon-preset-btn" data-amount="50000000">50.000.000đ</button>
                    </div>
                </div>

                <!-- Note / Message -->
                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-size: 0.875rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem;">
                        Lời nhắn / Yêu cầu đồng hành gửi nhóm dự án (Tùy chọn)
                    </label>
                    <textarea class="spon-input" rows="3" placeholder="VD: FPT Software mong muốn đồng hành và hướng dẫn nhóm phát triển thử nghiệm mô hình thực tế..."></textarea>
                </div>

                <!-- Value Proposition / Partner Benefits Box -->
                <div class="spon-benefits-box">
                    <h5>✨ Quyền lợi Doanh nghiệp Tài trợ thu nhận:</h5>
                    <div class="spon-benefit-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 0.15rem;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Hiển thị thương hiệu Doanh nghiệp trên toàn bộ tài liệu & sản phẩm nghiên cứu.</span>
                    </div>
                    <div class="spon-benefit-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 0.15rem;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Quyền ưu tiên tiếp cận và đề xuất tuyển dụng thực tập sinh trực tiếp với thành viên nhóm.</span>
                    </div>
                    <div class="spon-benefit-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2.5" style="flex-shrink: 0; margin-top: 0.15rem;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Nhận báo cáo tiến độ và nghiệm thu cột mốc chi tiết định kỳ hàng tháng.</span>
                    </div>
                </div>

            </div>

            <div class="spon-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('sponsorship-form-modal').classList.remove('is-open'); document.body.style.overflow = '';">Hủy bỏ</button>
                <button type="submit" class="btn btn-primary" id="btn-submit-sponsorship">
                    Xác nhận tài trợ ngay
                </button>
            </div>

        </form>
    </div>

    <!-- ====================================================================
         7. Progress Update Modal Component ("Theo dõi tiến độ")
         ==================================================================== -->
    <div class="spon-modal-overlay" id="progress-detail-modal" aria-hidden="true">
        <div class="spon-modal-dialog" style="max-width: 640px;">
            <div class="spon-modal-header">
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--accent); text-transform: uppercase;">NHẬT KÝ TIẾN ĐỘ & BÁO CÁO NGHIỆM THU</div>
                    <h3 class="spon-modal-title" id="prog-modal-title">Tên dự án</h3>
                    <div style="font-size: 0.8125rem; color: var(--text-secondary);" id="prog-modal-school">Trường học...</div>
                </div>
                <button class="spon-modal-close" id="close-progress-modal" aria-label="Đóng modal">&times;</button>
            </div>

            <div class="spon-modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; background: var(--background); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 1rem;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Số tiền doanh nghiệp tài trợ</div>
                        <div style="font-size: 1.125rem; font-weight: 800; color: var(--primary);" id="prog-modal-amount">30.000.000 VNĐ</div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Trạng thái tài trợ</div>
                        <div style="font-size: 0.9375rem; font-weight: 700; color: var(--accent);" id="prog-modal-status">Đã giải ngân</div>
                    </div>
                </div>

                <div style="border-left: 2px solid var(--primary); padding-left: 1rem; margin-bottom: 1.5rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted);" id="prog-modal-update-date">10/08/2026</div>
                    <h4 style="font-size: 1rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.25rem;" id="prog-modal-update-title">Tiêu đề cập nhật</h4>
                    <div style="font-size: 0.8125rem; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem;" id="prog-modal-update-author">Bởi: Leader</div>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); line-height: 1.6;" id="prog-modal-update-summary">Nội dung báo cáo...</p>
                </div>
            </div>

            <div class="spon-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('progress-detail-modal').classList.remove('is-open'); document.body.style.overflow = '';">Đóng cửa sổ</button>
            </div>
        </div>
    </div>

    <!-- Pass JSON Data to Client-side JavaScript -->
    <script>
        window.ENTERPRISE_PROJECTS = <?= json_encode($projects); ?>;
        window.ENTERPRISE_SPONSORSHIPS = <?= json_encode($mySponsorships); ?>;
    </script>
    <script src="../../../assets/js/enterprise-sponsorships.js"></script>
</body>
</html>
