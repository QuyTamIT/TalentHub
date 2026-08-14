<?php
/**
 * TalentHub - Enterprise Talent Search ("Tìm nhân tài")
 * 
 * Note for Developers:
 * - This page provides enterprise search, multi-criteria filtering, quick filters,
 *   sorting, and evaluation of potential talent profiles.
 * - Mock data is provided via includes/talents-data.php and passed to frontend script.
 * - Privacy rules strictly enforced: NO personal email, phone numbers or sensitive data exposed.
 */

require_once __DIR__ . '/includes/talents-data.php';

$enterpriseInfo = [
    'company_name' => 'FPT Software',
    'account_type' => 'Gói Premium',
    'logo_initials' => 'FPT',
    'new_matches_count' => 86,
    'total_talents' => 1247
];

$pageTitle = 'Tìm nhân tài';
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
        'route' => '/app/enterprise/sponsorships',
        'icon' => 'award',
        'active' => false
    ],
    [
        'title' => 'Phân tích tuyển dụng',
        'route' => '/app/enterprise/analytics',
        'icon' => 'bar-chart',
        'active' => false
    ]
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tìm kiếm nhân tài, kết nối ứng viên tài năng dành cho Doanh nghiệp trên TalentHub Enterprise.">
    <title>Tìm nhân tài - Enterprise | TalentHub</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/enterprise.css">
</head>
<body class="enterprise-dashboard">

    <!-- Layout Wrapper -->
    <div class="ent-layout">
        
        <!-- Sidebar Navigation Partial -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="ent-main-wrapper">
            
            <!-- Top Header Partial -->
            <?php include __DIR__ . '/includes/header.php'; ?>

            <!-- Page Body Content -->
            <main class="ent-body">
                <div class="container-fluid">
                    
                    <!-- Talent Search Intro Hero Banner -->
                    <div class="ent-talent-hero">
                        <div class="ent-talent-hero__left">
                            <div class="ent-talent-hero__title-row">
                                <span class="ent-talent-hero__icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                </span>
                                <h2 class="ent-talent-hero__title">Tìm kiếm & Đánh giá nhân tài</h2>
                            </div>
                            <p class="ent-talent-hero__desc">
                                Khám phá hồ sơ năng lực thực tế của học sinh, sinh viên từ các trường THPT, Cao đẳng và Đại học trên toàn quốc.
                            </p>
                        </div>
                        
                        <!-- Prominent Result Summary Card -->
                        <div class="ent-result-card" id="ent-total-badge">
                            <div class="ent-result-card__icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <div class="ent-result-card__content">
                                <span class="ent-result-card__number" id="ent-count-num"><?= count($mockTalents); ?></span>
                                <span class="ent-result-card__label">nhân tài phù hợp</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Filters & Main Search Toolbar -->
                    <div class="ent-search-toolbar">
                        <!-- Main Search Bar -->
                        <div class="ent-search-input-wrapper">
                            <svg class="ent-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" 
                                   id="talent-search-input" 
                                   class="ent-search-input" 
                                   placeholder="Nhập tên học sinh/sinh viên, kỹ năng (React, Python...), trường học, hoặc lĩnh vực..."
                                   aria-label="Tìm kiếm nhân tài">
                            <button type="button" class="ent-search-clear" id="talent-search-clear" aria-label="Xóa từ khóa tìm kiếm" style="display: none;">
                                &times;
                            </button>
                        </div>

                        <!-- Quick Filters Row -->
                        <div class="ent-quick-filters">
                            <span class="ent-quick-filters__label">Lọc nhanh:</span>
                            <button type="button" class="ent-quick-pill" data-quick-filter="ai_ml">
                                AI / Machine Learning
                            </button>
                            <button type="button" class="ent-quick-pill" data-quick-filter="coding">
                                Lập trình
                            </button>
                            <button type="button" class="ent-quick-pill" data-quick-filter="design">
                                Thiết kế
                            </button>
                            <button type="button" class="ent-quick-pill" data-quick-filter="marketing">
                                Marketing
                            </button>
                            <button type="button" class="ent-quick-pill" data-quick-filter="ready_now">
                                Sẵn sàng thực tập
                            </button>
                        </div>
                    </div>

                    <!-- Main Content Grid (Filters Column + Results Column) -->
                    <div class="ent-talent-grid">
                        
                        <!-- Filter Sidebar / Collapsible Panel -->
                        <aside class="ent-filter-card" id="ent-filter-card">
                            <div class="ent-filter-card__header">
                                <h3>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                    </svg>
                                    Bộ lọc tìm kiếm
                                </h3>
                                <button type="button" class="ent-filter-reset-btn" id="filter-reset-btn">
                                    Đặt lại
                                </button>
                            </div>

                            <form id="talent-filter-form" class="ent-filter-form" onsubmit="return false;">
                                <!-- Bậc học -->
                                <div class="ent-filter-group">
                                    <label for="filter-edu-level" class="ent-filter-label">Bậc học</label>
                                    <select id="filter-edu-level" class="ent-filter-select">
                                        <option value="">Tất cả bậc học</option>
                                        <option value="THCS">THCS</option>
                                        <option value="THPT">THPT</option>
                                        <option value="Cao đẳng">Cao đẳng</option>
                                        <option value="Đại học">Đại học</option>
                                    </select>
                                </div>

                                <!-- Trường học -->
                                <div class="ent-filter-group">
                                    <label for="filter-school" class="ent-filter-label">Trường học</label>
                                    <select id="filter-school" class="ent-filter-select">
                                        <option value="">Tất cả trường học</option>
                                        <?php foreach ($schoolsList as $sch): ?>
                                            <option value="<?= htmlspecialchars($sch); ?>"><?= htmlspecialchars($sch); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Khối / Năm học -->
                                <div class="ent-filter-group">
                                    <label for="filter-class-year" class="ent-filter-label">Khối / Năm học</label>
                                    <select id="filter-class-year" class="ent-filter-select">
                                        <option value="">Tất cả khối / năm</option>
                                        <option value="Lớp 9">Lớp 9</option>
                                        <option value="Lớp 11">Lớp 11</option>
                                        <option value="Lớp 12">Lớp 12</option>
                                        <option value="Năm 2">Năm 2</option>
                                        <option value="Năm 3">Năm 3</option>
                                        <option value="Năm 4">Năm 4</option>
                                        <option value="Đã tốt nghiệp">Đã tốt nghiệp</option>
                                    </select>
                                </div>

                                <!-- Lĩnh vực năng lực -->
                                <div class="ent-filter-group">
                                    <label for="filter-major-field" class="ent-filter-label">Lĩnh vực năng lực</label>
                                    <select id="filter-major-field" class="ent-filter-select">
                                        <option value="">Tất cả lĩnh vực</option>
                                        <?php foreach ($majorFieldsList as $mf): ?>
                                            <option value="<?= htmlspecialchars($mf); ?>"><?= htmlspecialchars($mf); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Điểm đánh giá -->
                                <div class="ent-filter-group">
                                    <label for="filter-match-score" class="ent-filter-label">Điểm đánh giá năng lực</label>
                                    <select id="filter-match-score" class="ent-filter-select">
                                        <option value="0">Tất cả mức điểm</option>
                                        <option value="90">Từ 90 điểm trở lên</option>
                                        <option value="80">Từ 80 điểm trở lên</option>
                                        <option value="70">Từ 70 điểm trở lên</option>
                                    </select>
                                </div>

                                <!-- Giờ trải nghiệm -->
                                <div class="ent-filter-group">
                                    <label for="filter-exp-hours" class="ent-filter-label">Giờ trải nghiệm thực án</label>
                                    <select id="filter-exp-hours" class="ent-filter-select">
                                        <option value="0">Tất cả số giờ</option>
                                        <option value="50">Từ 50h trở lên</option>
                                        <option value="100">Từ 100h trở lên</option>
                                        <option value="150">Từ 150h trở lên</option>
                                    </select>
                                </div>

                                <!-- Trạng thái thực tập -->
                                <div class="ent-filter-group">
                                    <label for="filter-readiness" class="ent-filter-label">Trạng thái thực tập</label>
                                    <select id="filter-readiness" class="ent-filter-select">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="ready_now">Sẵn sàng thực tập ngay</option>
                                        <option value="ready_1_3m">Sẵn sàng trong 1-3 tháng</option>
                                        <option value="not_ready">Chưa sẵn sàng</option>
                                    </select>
                                </div>

                                <!-- Kỹ năng phổ biến & Xem thêm -->
                                <div class="ent-filter-group">
                                    <label class="ent-filter-label">Kỹ năng phổ biến</label>
                                    <div class="ent-filter-checkboxes" id="popular-skills-container">
                                        <?php 
                                        $popularSkills = [
                                            'Python', 'JavaScript', 'AI / Machine Learning', 
                                            'Data Analysis', 'UI/UX', 'Digital Marketing', 
                                            'Communication', 'Leadership'
                                        ];
                                        foreach ($popularSkills as $s): 
                                        ?>
                                            <label class="ent-checkbox-label">
                                                <input type="checkbox" value="<?= htmlspecialchars($s); ?>" class="filter-skill-checkbox" data-skill-name="<?= htmlspecialchars($s); ?>">
                                                <span><?= htmlspecialchars($s); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <button type="button" class="ent-more-skills-btn" id="open-skills-modal-btn">
                                        + Xem thêm kỹ năng
                                    </button>
                                </div>

                                <!-- Container hiển thị thẻ kỹ năng đã chọn -->
                                <div class="ent-selected-skills-wrapper" id="selected-skills-wrapper" style="display: none;">
                                    <label class="ent-filter-label">Kỹ năng đã chọn (<span id="selected-skills-count">0</span>):</label>
                                    <div class="ent-selected-skill-tags" id="selected-skills-tags"></div>
                                </div>

                                <!-- Filter Buttons -->
                                <div class="ent-filter-actions">
                                    <button type="button" class="btn btn-primary btn-block" id="apply-filters-btn">
                                        Áp dụng bộ lọc
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-block" id="clear-filters-btn">
                                        Xóa bộ lọc
                                    </button>
                                </div>
                            </form>
                        </aside>

                        <!-- Results List Area -->
                        <div class="ent-results-column">
                            
                            <!-- Control Bar (Mobile Toggle + Sorting) -->
                            <div class="ent-results-control-bar">
                                <button type="button" class="btn btn-secondary ent-mobile-filter-btn" id="mobile-filter-toggle">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                    </svg>
                                    Bộ lọc (<span id="mobile-filter-count">0</span>)
                                </button>

                                <div class="ent-sort-wrapper">
                                    <label for="talent-sort-select" class="ent-sort-label">Sắp xếp:</label>
                                    <select id="talent-sort-select" class="ent-sort-select">
                                        <option value="matching">Điểm đánh giá cao nhất</option>
                                        <option value="score_desc">Điểm cao nhất</option>
                                        <option value="exp_desc">Nhiều giờ trải nghiệm nhất</option>
                                        <option value="latest">Mới cập nhật</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Talent Cards List -->
                            <div class="ent-talent-cards-wrapper" id="talent-cards-container">
                                <!-- Dynamically rendered by JavaScript -->
                            </div>

                            <!-- Empty State View (Hidden by default) -->
                            <div class="ent-empty-state" id="talent-empty-state" style="display: none;">
                                <div class="ent-empty-state__icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        <line x1="8" y1="11" x2="14" y2="11"></line>
                                    </svg>
                                </div>
                                <h3 class="ent-empty-state__title">Không tìm thấy nhân tài phù hợp</h3>
                                <p class="ent-empty-state__desc">
                                    Không có ứng viên nào đáp ứng toàn bộ các tiêu chí bộ lọc hiện tại. Thử mở rộng phạm vi tìm kiếm hoặc đặt lại các bộ lọc.
                                </p>
                                <button type="button" class="btn btn-primary" id="empty-reset-btn">
                                    Đặt lại bộ lọc
                                </button>
                            </div>

                            <!-- Pagination Container -->
                            <div class="ent-pagination-wrapper" id="talent-pagination" style="display: flex;">
                                <span class="ent-pagination-info" id="pagination-info">Trang 1 / 2</span>
                                <div class="ent-pagination-buttons" id="pagination-btns">
                                    <!-- Dynamic pagination controls -->
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Pass PHP Mock Data safely to JavaScript -->
    <script id="talents-mock-data" type="application/json">
        <?= json_encode($mockTalents, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    </script>

    <!-- Expanded Skills Selector Modal -->
    <div class="ent-skills-modal" id="skills-selector-modal" aria-hidden="true" style="display: none;">
        <div class="ent-skills-modal__backdrop" id="skills-modal-backdrop"></div>
        <div class="ent-skills-modal__dialog">
            <div class="ent-skills-modal__header">
                <div>
                    <h3 class="ent-skills-modal__title">Tất cả kỹ năng</h3>
                    <p class="ent-skills-modal__subtitle">Tìm kiếm và chọn kỹ năng để lọc ứng viên đáp ứng đủ các tiêu chí</p>
                </div>
                <button type="button" class="ent-skills-modal__close" id="close-skills-modal-btn" aria-label="Đóng">&times;</button>
            </div>
            
            <div class="ent-skills-modal__search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" id="skill-search-input" class="ent-skill-search-input" placeholder="Tìm kỹ năng...">
            </div>

            <div class="ent-skills-modal__body" id="skills-categories-container">
                <!-- Dynamically rendered categorized skill groups -->
            </div>

            <div class="ent-skills-modal__footer">
                <span class="ent-skills-modal__count">Đã chọn: <strong id="modal-selected-count">0</strong> kỹ năng</span>
                <button type="button" class="btn btn-primary" id="confirm-skills-btn">Áp dụng kỹ năng</button>
            </div>
        </div>
    </div>

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
    <script src="../../assets/js/enterprise.js"></script>
    <script src="../../assets/js/talent-search.js"></script>
</body>
</html>
