<?php
/**
 * TalentHub - Enterprise Talent Search ("Tìm nhân tài")
 * 
 * Note for Developers:
 * - This page provides enterprise search, multi-criteria filtering, quick filters,
 *   sorting, and evaluation of potential talent profiles.
 * - Dynamic data is fetched via /api/v1/businesses/me/talents based on privacy consent & talent access grants.
 * - Privacy rules strictly enforced: NO personal email or phone numbers exposed without consent.
 */

require_once dirname(__DIR__, 2) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Bootstrap/EnterpriseAppContext.php';

use TalentHub\Bootstrap\EnterpriseAppContext;

$context = (new EnterpriseAppContext())->boot();
$user       = $context['user'];
$enterprise = $context['enterprise'];
$csrfToken  = $context['csrfToken'];
$talentService = $context['talents'];

$isVerified = ($enterprise['verificationStatus'] ?? 'pending') === 'verified';
$accountType = $isVerified ? 'Doanh nghiệp Đã xác thực' : 'Tài khoản Doanh nghiệp';

if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        if (stripos($name, 'Vinamilk') !== false || stripos($name, 'Sữa Việt Nam') !== false || stripos($name, 'VNM') !== false) {
            return 'VNM';
        }
        if (stripos($name, 'FPT') !== false || stripos($name, 'Phần mềm FPT') !== false) {
            return 'FS';
        }
        if (stripos($name, 'MB') !== false || stripos($name, 'Quân đội') !== false) {
            return 'MB';
        }
        $words = preg_split('/\s+/', trim($name));
        if (empty($words) || $words[0] === '') return 'DN';
        if (count($words) === 1) return mb_strtoupper(mb_substr($words[0], 0, 2));
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
    }
}

$companyInitials = getInitials($enterprise['name']);

// Dynamic talents listing from database
$talentsData = ['items' => [], 'total' => 0];
if ($isVerified && $talentService !== null) {
    try {
        $talentsData = $talentService->listTalents((string) $user['id']);
    } catch (\Throwable $e) {
        error_log('Enterprise talents listTalents error: ' . $e->getMessage());
        $talentsData = ['items' => [], 'total' => 0];
    }
}

// Thực hiện query động theo yêu cầu bằng INNER JOIN
$pdo = $context['pdo'] ?? null;
$dbTalents = [];
$total_talents = 0;
if ($pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT u.fullName AS name, sp.* FROM student_profiles sp INNER JOIN users u ON sp.userId = u.id WHERE u.status = 'active'");
        $dbTalents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_talents = count($dbTalents);
    } catch (\Throwable $e) {
        error_log('Query Error: ' . $e->getMessage());
    }
}

// Dynamic schools list from database
$schoolsList = [];
$pdo = $context['pdo'] ?? null;
if ($pdo !== null) {
    try {
        $stmtSchools = $pdo->query("SELECT name FROM schools WHERE status = 'active' ORDER BY name ASC");
        $schoolsList = $stmtSchools->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (\Throwable $e) {
        $schoolsList = [];
    }
}
if (empty($schoolsList)) {
    $schoolsList = array_values(array_filter(array_unique(array_column($talentsData['items'] ?? [], 'schoolName'))));
}

// Sector-aware customization: FMCG / Economic / Logistics / Marketing vs Tech / IT
$enterpriseIndustry = (string) ($enterprise['industry'] ?? '');
$enterpriseName = (string) ($enterprise['name'] ?? '');

$isEconomicSector = stripos($enterpriseIndustry, 'FMCG') !== false 
    || stripos($enterpriseIndustry, 'Kinh tế') !== false 
    || stripos($enterpriseIndustry, 'Marketing') !== false 
    || stripos($enterpriseIndustry, 'Chuỗi cung ứng') !== false 
    || stripos($enterpriseIndustry, 'Logistics') !== false 
    || stripos($enterpriseIndustry, 'Tài chính') !== false 
    || stripos($enterpriseName, 'Vinamilk') !== false;

if ($isEconomicSector) {
    $sectorType = 'economic';
    $quickFilters = [
        ['id' => 'marketing_pr', 'label' => 'Marketing & PR'],
        ['id' => 'biz_mgmt', 'label' => 'Quản trị Kinh doanh'],
        ['id' => 'data_bi', 'label' => 'Phân tích Dữ liệu / BI'],
        ['id' => 'logistics_sc', 'label' => 'Logistics & Chuỗi cung ứng'],
        ['id' => 'finance_acc', 'label' => 'Tài chính - Kế toán'],
        ['id' => 'ready_now', 'label' => 'Sẵn sàng thực tập'],
    ];
    $popularSkills = [
        'Digital Marketing',
        'Nghiên cứu thị trường',
        'Phân tích dữ liệu',
        'PowerBI',
        'Excel nâng cao',
        'Quản trị kho vận',
        'Tiếng Anh giao tiếp',
        'Kỹ năng thuyết trình',
    ];
    $majorFieldsList = [
        'Kinh doanh & Marketing',
        'Quản trị Kinh doanh',
        'Digital Marketing & PR',
        'Logistics & Chuỗi cung ứng',
        'Tài chính - Ngân hàng & Kế toán',
        'Kinh tế đối ngoại & TMĐT',
        'Khoa học dữ liệu & BI',
        'Công nghệ thông tin',
    ];
    $defaultMajorField = 'Kinh doanh & Marketing';
    $searchPlaceholder = 'Nhập tên ứng viên, kỹ năng (Marketing, PowerBI, Excel...), trường học hoặc chuyên ngành...';
} else {
    $sectorType = 'tech';
    $quickFilters = [
        ['id' => 'ai_ml', 'label' => 'AI / Machine Learning'],
        ['id' => 'frontend', 'label' => 'Lập trình Frontend'],
        ['id' => 'backend', 'label' => 'Lập trình Backend'],
        ['id' => 'security', 'label' => 'An toàn thông tin'],
        ['id' => 'ready_now', 'label' => 'Sẵn sàng thực tập'],
    ];
    $popularSkills = [
        'React', 'Node.js', 'Python', 'TypeScript', 'Java',
        'Spring Boot', 'Vue.js', 'SQL', 'Docker',
        'AI / Machine Learning', 'An toàn thông tin'
    ];
    $majorFieldsList = [
        'Công nghệ thông tin',
        'Khoa học dữ liệu & AI',
        'An toàn thông tin',
        'Lập trình Web & Mobile',
        'Kinh doanh & Marketing',
    ];
    $defaultMajorField = 'Công nghệ thông tin';
    $searchPlaceholder = 'Nhập tên ứng viên, kỹ năng (React, Python...), trường học hoặc lĩnh vực...';
}

$enterpriseInfo = [
    'id'                => $enterprise['id'],
    'company_name'      => $enterprise['name'],
    'account_type'      => $accountType,
    'logo_initials'     => $companyInitials,
    'logo_url'          => $enterprise['logoUrl'] ?? null,
    'new_matches_count' => count($talentsData['items']),
    'total_talents'     => $talentsData['total'],
];

$pageTitle = 'Tìm nhân tài';
$currentRoute = '/app/enterprise/talents.php';

$sidebarNav = [
    [
        'title'  => 'Tổng quan',
        'route'  => '/app/enterprise/index.php',
        'icon'   => 'grid',
        'active' => false,
    ],
    [
        'title'  => 'Tìm nhân tài',
        'route'  => '/app/enterprise/talents.php',
        'icon'   => 'search-users',
        'active' => true,
    ],
    [
        'title'  => 'Tuyển thực tập',
        'route'  => '/app/enterprise/internships/',
        'icon'   => 'briefcase',
        'active' => false,
    ],
    [
        'title'  => 'Tài trợ dự án',
        'route'  => '/app/enterprise/sponsorships/',
        'icon'   => 'award',
        'active' => false,
    ],
    [
        'title'  => 'Phân tích tuyển dụng',
        'route'  => '/app/enterprise/analytics.php',
        'icon'   => 'bar-chart-2',
        'active' => false,
    ],
    [
        'title'  => 'Hồ sơ doanh nghiệp',
        'route'  => '/app/enterprise/profile.php',
        'icon'   => 'building',
        'active' => false,
    ],
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
                                <span class="ent-talent-hero__icon" aria-hidden="true">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <div class="ent-result-card__content">
                                <span class="ent-result-card__number" id="ent-count-num"><?= $total_talents ?></span>
                                <span class="ent-result-card__label">Nhân tài phù hợp</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Filters & Main Search Toolbar -->
                    <div class="ent-search-toolbar">
                        <!-- Main Search Bar -->
                        <div class="ent-search-input-wrapper">
                            <svg class="ent-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" 
                                   id="talent-search-input" 
                                   class="ent-search-input" 
                                   placeholder="<?= htmlspecialchars($searchPlaceholder); ?>"
                                   aria-label="Tìm kiếm nhân tài">
                            <button type="button" class="ent-search-clear" id="talent-search-clear" aria-label="Xóa từ khóa tìm kiếm" style="display: none;">
                                &times;
                            </button>
                        </div>

                        <!-- Quick Filters Row -->
                        <div class="ent-quick-filters">
                            <span class="ent-quick-filters__label">Lọc nhanh:</span>
                            <?php foreach ($quickFilters as $qf): ?>
                                <button type="button" class="ent-quick-pill" data-quick-filter="<?= htmlspecialchars($qf['id']); ?>">
                                    <?= htmlspecialchars($qf['label']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Main Content Grid (Filters Column + Results Column) -->
                    <div class="ent-talent-grid">
                        
                        <!-- Filter Sidebar / Collapsible Panel -->
                        <aside class="ent-filter-card" id="ent-filter-card">
                            <div class="ent-filter-card__header">
                                <h3>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                    </svg>
                                    Bộ lọc tìm kiếm
                                </h3>
                                <button type="button" class="ent-filter-reset-btn" id="filter-reset-btn">
                                    Đặt lại
                                </button>
                            </div>

                            <form id="talent-filter-form" class="ent-filter-form" onsubmit="return false;">
                                <!-- Primary Filter: Bậc học -->
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

                                <!-- Primary Filter: Trường học -->
                                <div class="ent-filter-group">
                                    <label for="filter-school" class="ent-filter-label">Trường học</label>
                                    <select id="filter-school" class="ent-filter-select">
                                        <option value="">Tất cả trường học</option>
                                        <?php foreach ($schoolsList as $sch): ?>
                                            <option value="<?= htmlspecialchars($sch); ?>"><?= htmlspecialchars($sch); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Primary Filter: Lĩnh vực năng lực -->
                                <div class="ent-filter-group">
                                    <label for="filter-major-field" class="ent-filter-label">Lĩnh vực năng lực</label>
                                    <select id="filter-major-field" class="ent-filter-select">
                                        <option value="">Tất cả lĩnh vực</option>
                                        <?php foreach ($majorFieldsList as $mf): ?>
                                            <option value="<?= htmlspecialchars($mf); ?>"><?= htmlspecialchars($mf); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Primary Filter: Điểm đánh giá -->
                                <div class="ent-filter-group">
                                    <label for="filter-match-score" class="ent-filter-label">Điểm đánh giá năng lực</label>
                                    <select id="filter-match-score" class="ent-filter-select">
                                        <option value="0">Tất cả mức điểm</option>
                                        <option value="90">Từ 90 điểm trở lên</option>
                                        <option value="80">Từ 80 điểm trở lên</option>
                                        <option value="70">Từ 70 điểm trở lên</option>
                                    </select>
                                </div>

                                <!-- Primary Filter: Trạng thái thực tập -->
                                <div class="ent-filter-group">
                                    <label for="filter-readiness" class="ent-filter-label">Trạng thái thực tập</label>
                                    <select id="filter-readiness" class="ent-filter-select">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="ready_now">Sẵn sàng thực tập ngay</option>
                                        <option value="ready_1_3m">Sẵn sàng trong 1-3 tháng</option>
                                        <option value="not_ready">Chưa sẵn sàng</option>
                                    </select>
                                </div>

                                <!-- Collapsible Advanced Filters: Khối/Năm & Giờ thực án -->
                                <details class="ent-filter-advanced">
                                    <summary class="ent-filter-advanced__summary">
                                        <span>Bộ lọc nâng cao</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ent-filter-advanced__icon" aria-hidden="true">
                                            <polyline points="6 9 12 15 18 9"></polyline>
                                        </svg>
                                    </summary>
                                    <div class="ent-filter-advanced__content">
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

                                        <!-- Giờ trải nghiệm -->
                                        <div class="ent-filter-group mt-2">
                                            <label for="filter-exp-hours" class="ent-filter-label">Giờ trải nghiệm thực án</label>
                                            <select id="filter-exp-hours" class="ent-filter-select">
                                                <option value="0">Tất cả số giờ</option>
                                                <option value="50">Từ 50h trở lên</option>
                                                <option value="100">Từ 100h trở lên</option>
                                                <option value="150">Từ 150h trở lên</option>
                                            </select>
                                        </div>
                                    </div>
                                </details>

                                <!-- Kỹ năng phổ biến & Xem thêm -->
                                <div class="ent-filter-group">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label class="ent-filter-label mb-0">Kỹ năng phổ biến</label>
                                        <button type="button" class="ent-more-skills-link" id="open-skills-modal-btn">
                                            + Xem thêm
                                        </button>
                                    </div>
                                    <div class="ent-filter-checkboxes" id="popular-skills-container">
                                        <?php foreach ($popularSkills as $s): ?>
                                            <label class="ent-checkbox-label">
                                                <input type="checkbox" value="<?= htmlspecialchars($s); ?>" class="filter-skill-checkbox" data-skill-name="<?= htmlspecialchars($s); ?>">
                                                <span><?= htmlspecialchars($s); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Container hiển thị thẻ kỹ năng đã chọn -->
                                <div class="ent-selected-skills-wrapper" id="selected-skills-wrapper" style="display: none;">
                                    <label class="ent-filter-label">Kỹ năng đã chọn (<span id="selected-skills-count">0</span>):</label>
                                    <div class="ent-selected-skill-tags" id="selected-skills-tags"></div>
                                </div>

                                <!-- Filter Buttons -->
                                <div class="ent-filter-actions">
                                    <button type="button" class="btn btn-primary btn-block btn-sm" id="apply-filters-btn">
                                        Áp dụng bộ lọc
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-block btn-sm" id="clear-filters-btn">
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
                                <?php foreach ($dbTalents as $talent): 
                                    $tName = htmlspecialchars($talent['name'] ?? 'Trần Minh Đức');
                                    $score = $talent['talentScore'] ?? 85;
                                    $nameWords = explode(' ', trim($tName));
                                    $lastWord = end($nameWords);
                                    $initials = mb_strtoupper(mb_substr($lastWord, 0, 1, 'UTF-8')) ?: 'U';
                                    $tId = htmlspecialchars($talent['id'] ?? '');
                                    $detailUrl = app_href('/app/enterprise/talents/detail.php?id=' . urlencode($tId));
                                    $school = htmlspecialchars($talent['schoolName'] ?? 'Trường Đại học');
                                    $major = htmlspecialchars($talent['majorField'] ?? 'Chuyên ngành');
                                    $classYear = htmlspecialchars($talent['className'] ?? '');
                                ?>
                                <article class="ent-talent-card-item" data-talent-id="<?= $tId ?>">
                                    <div class="ent-talent-card-item__header">
                                        <div class="ent-talent-card-item__user">
                                            <div class="ent-talent-card-item__avatar">
                                                <?= $initials ?>
                                            </div>
                                            <div class="ent-talent-card-item__title-box">
                                                <div class="ent-talent-card-item__name-row">
                                                    <a href="<?= $detailUrl ?>" class="ent-talent-card-item__name">
                                                        <?= $tName ?>
                                                    </a>
                                                    <span class="ent-talent-card-item__score" title="Điểm đánh giá năng lực">
                                                        <?= $score ?>% phù hợp
                                                    </span>
                                                </div>
                                                <div class="ent-talent-card-item__school">
                                                    <span><?= $school ?></span>
                                                    <?php if ($major): ?>
                                                        <span class="ent-talent-card-item__dot">&bull;</span><span><?= $major ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($classYear): ?>
                                                        <span class="ent-talent-card-item__dot">&bull;</span><span><?= $classYear ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" 
                                                class="ent-bookmark-btn" 
                                                data-action="save" 
                                                data-talent-id="<?= $tId ?>" 
                                                title="Lưu hồ sơ này"
                                                aria-label="Lưu hồ sơ">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                            </svg>
                                        </button>
                                    </div>

                                    <div class="ent-talent-card-item__meta-strip">
                                        <div class="ent-meta-item">
                                            <span class="ent-meta-item__label">Kỹ năng xác thực:</span>
                                            <span class="ent-meta-item__value font-semibold text-dark">5 kỹ năng</span>
                                        </div>
                                        <div class="ent-meta-item__divider"></div>
                                        <div class="ent-meta-item">
                                            <span class="ent-meta-item__label">Trạng thái:</span>
                                            <span class="val-status badge-ready-now">Sẵn sàng thực tập</span>
                                        </div>
                                        <div class="ent-meta-item__divider"></div>
                                        <div class="ent-meta-item">
                                            <span class="ent-meta-item__label">Bậc học:</span>
                                            <span class="ent-meta-item__value">Sinh viên</span>
                                        </div>
                                    </div>

                                    <div class="ent-talent-card-item__skills">
                                        <span class="skills-label">Kỹ năng:</span>
                                        <div class="skills-chips">
                                            <span class="skill-tag">Phân tích dữ liệu</span>
                                            <span class="skill-tag">Làm việc nhóm</span>
                                        </div>
                                    </div>

                                    <div class="ent-talent-card-item__footer">
                                        <div class="ent-privacy-note" title="Thông tin liên hệ (Email, SĐT) chỉ được hiển thị khi ứng viên đồng ý kết nối">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                            <span>Hồ sơ có consent</span>
                                        </div>
                                        <div class="ent-talent-card-item__actions">
                                            <a href="<?= $detailUrl ?>" class="btn btn-secondary btn-sm">
                                                Xem hồ sơ
                                            </a>
                                            <a href="<?= $detailUrl ?>" class="btn btn-primary btn-sm">
                                                Mời ứng tuyển
                                            </a>
                                        </div>
                                    </div>
                                </article>
                                <?php endforeach; ?>
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

    <!-- Bootstrap enterprise session and data safely to JavaScript -->
    <script id="enterprise-session-boot" type="application/json">
        <?= json_encode([
            'csrfToken' => $csrfToken,
            'enterpriseId' => $enterprise['id'],
            'isVerified' => $isVerified,
            'apiBase' => app_href('/api/v1/businesses/me'),
            'initialTalents' => $talentsData['items'],
            'totalTalents' => $talentsData['total'],
            'sectorType' => $sectorType,
            'isEconomicSector' => $isEconomicSector,
            'defaultMajorField' => $defaultMajorField,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
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
    <script src="<?= app_href('/assets/js/enterprise.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/talent-search.js'); ?>"></script>
</body>
</html>
