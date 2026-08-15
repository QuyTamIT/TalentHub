<?php
/**
 * TalentHub Enterprise - Internship Applicants Management Route
 * 
 * Route: app/enterprise/internships/applicants.php?postId=...
 */

require_once __DIR__ . '/../includes/internships-data.php';
require_once __DIR__ . '/../includes/applicants-data.php';
require_once __DIR__ . '/../includes/talents-data.php';

$enterpriseInfo = [
    'company_name' => 'FPT Software',
    'account_type' => 'Gói Premium',
    'logo_initials' => 'FPT',
    'new_matches_count' => 86,
    'total_talents' => 1247
];

$postId = isset($_GET['postId']) ? intval($_GET['postId']) : 1;
$post = getMockInternshipById($postId);
$applicants = getMockApplicantsByPostId($postId);
$pipelineCounts = getApplicantPipelineCounts($postId);

$pageTitle = 'Quản lý ứng viên';
$currentRoute = '/app/enterprise/internships/';

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
        'active' => true
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
    <meta name="description" content="Quản lý và duyệt danh sách ứng viên thực tập nộp hồ sơ vào tin tuyển dụng Enterprise TalentHub.">
    <title><?= htmlspecialchars($pageTitle); ?> - <?= $post ? htmlspecialchars($post['title']) : 'TalentHub Enterprise'; ?> | TalentHub Enterprise</title>
    
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
                    
                    <!-- Back Breadcrumb Bar -->
                    <div class="ent-back-bar">
                        <a href="index.php" class="ent-back-link">
                            &larr; Quay lại Tuyển thực tập
                        </a>
                    </div>

                    <!-- Clean H1 Page Header -->
                    <h1 class="ent-page-title">Quản lý ứng viên</h1>

                    <?php if (!$post): ?>
                        <!-- Empty State: Invalid Post ID -->
                        <div class="ent-empty-state" style="margin-top: 2rem;">
                            <div class="ent-empty-state__icon">
                                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="1.5">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                            </div>
                            <h3 class="ent-empty-state__title">Không tìm thấy tin tuyển dụng</h3>
                            <p class="ent-empty-state__desc">Tin tuyển dụng với mã số #<?= htmlspecialchars($postId); ?> không tồn tại hoặc đã bị gỡ khỏi hệ thống.</p>
                            <a href="index.php" class="btn btn-primary">&larr; Quay lại Tuyển thực tập</a>
                        </div>

                    <?php else: ?>

                        <!-- 1. Internship Context Header Card -->
                        <div class="ent-applicant-context-card">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h2 class="ent-applicant-context-card__title">
                                            <?= htmlspecialchars($post['title']); ?>
                                        </h2>
                                        <span class="ent-status-pill ent-status-pill--<?= $post['status']; ?>">
                                            <span class="dot"></span>
                                            <?= htmlspecialchars($post['status_label']); ?>
                                        </span>
                                    </div>
                                    <div class="ent-applicant-context-card__meta">
                                        <?= htmlspecialchars($post['field']); ?> &bull; 
                                        <?= htmlspecialchars($post['work_type']); ?> &bull; 
                                        Hạn nộp <?= htmlspecialchars($post['deadline']); ?> &bull; 
                                        Chỉ tiêu <?= htmlspecialchars($post['slots']); ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="create.php?id=<?= $post['id']; ?>" class="btn btn-secondary btn-sm" title="Chỉnh sửa tin đăng">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                        Sửa tin
                                    </a>
                                    <span class="ent-applicant-count-badge">
                                        <?= count($applicants); ?> ứng viên đã nộp
                                    </span>
                                </div>
                            </div>
                        </div>

                        <?php if (count($applicants) === 0): ?>
                            <!-- Empty State: Job Post Has 0 Applicants -->
                            <div class="ent-section-box text-center py-5">
                                <div class="ent-empty-state__icon mb-3">
                                    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="1.5">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                                <h3 class="ent-section-box__title mb-2">
                                    Chưa có ứng viên nộp hồ sơ
                                </h3>
                                <p class="ent-section-box__subtitle max-w-600 auto-x mb-4">
                                    Hiện tại chưa có ứng viên nào gửi hồ sơ đăng ký cho vị trí <strong>"<?= htmlspecialchars($post['title']); ?>"</strong>. Bạn có thể sử dụng công cụ Tìm kiếm nhân tài chủ động để kết nối với các ứng viên phù hợp.
                                </p>
                                <div class="d-flex align-items-center justify-content-center gap-2">
                                    <a href="/app/enterprise/talents.php" class="btn btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                        <span>Tìm kiếm ứng viên tự động</span>
                                    </a>
                                    <a href="index.php" class="btn btn-secondary">
                                        Quay lại Tuyển thực tập
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>

                            <!-- 2. Applicant Pipeline Status Filter Tabs -->
                            <div class="ent-pipeline-tabs-wrapper">
                                <ul class="ent-pipeline-nav" role="tablist">
                                    <li>
                                        <button type="button" class="ent-pipeline-tab is-active" data-status-filter="all">
                                            <span>Tất cả</span>
                                            <span class="ent-pipeline-tab__count"><?= $pipelineCounts['all']; ?></span>
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="ent-pipeline-tab" data-status-filter="new">
                                            <span>Mới</span>
                                            <span class="ent-pipeline-tab__count"><?= $pipelineCounts['new']; ?></span>
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="ent-pipeline-tab" data-status-filter="reviewing">
                                            <span>Đang xem xét</span>
                                            <span class="ent-pipeline-tab__count"><?= $pipelineCounts['reviewing']; ?></span>
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="ent-pipeline-tab" data-status-filter="interviewing">
                                            <span>Phỏng vấn</span>
                                            <span class="ent-pipeline-tab__count"><?= $pipelineCounts['interviewing']; ?></span>
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="ent-pipeline-tab" data-status-filter="accepted">
                                            <span>Đã nhận</span>
                                            <span class="ent-pipeline-tab__count"><?= $pipelineCounts['accepted']; ?></span>
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="ent-pipeline-tab" data-status-filter="rejected">
                                            <span>Từ chối</span>
                                            <span class="ent-pipeline-tab__count"><?= $pipelineCounts['rejected']; ?></span>
                                        </button>
                                    </li>
                                </ul>
                            </div>

                            <!-- 3. Search / Filter / Sort Toolbar -->
                            <div class="ent-search-toolbar">
                                <div class="ent-internship-filter-row">
                                    <!-- Keyword Search Input -->
                                    <div class="ent-search-input-wrapper">
                                        <svg class="ent-search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                        <input type="text" 
                                               id="applicant-search-input" 
                                               class="ent-search-input" 
                                               placeholder="Tìm kiếm ứng viên theo tên, trường, lớp hoặc kỹ năng..."
                                               aria-label="Tìm kiếm ứng viên">
                                        <button type="button" class="ent-search-clear" id="applicant-search-clear" aria-label="Xóa tìm kiếm" style="display: none;">&times;</button>
                                    </div>

                                    <!-- Status Filter Dropdown -->
                                    <div class="ent-filter-select-wrapper">
                                        <select id="filter-app-status-select" class="ent-filter-select">
                                            <option value="">Tất cả trạng thái</option>
                                            <option value="new">Mới</option>
                                            <option value="reviewing">Đang xem xét</option>
                                            <option value="interviewing">Phỏng vấn</option>
                                            <option value="accepted">Đã nhận</option>
                                            <option value="rejected">Từ chối</option>
                                        </select>
                                    </div>

                                    <!-- Score Match Filter -->
                                    <div class="ent-filter-select-wrapper">
                                        <select id="filter-score-select" class="ent-filter-select">
                                            <option value="all">Tất cả độ phù hợp</option>
                                            <option value="90_plus">&ge; 90% phù hợp</option>
                                            <option value="80_89">80% - 89% phù hợp</option>
                                            <option value="under_80">&lt; 80% phù hợp</option>
                                        </select>
                                    </div>

                                    <!-- Sort Dropdown -->
                                    <div class="ent-filter-select-wrapper">
                                        <select id="sort-applicant-select" class="ent-filter-select">
                                            <option value="score_desc">% phù hợp cao nhất</option>
                                            <option value="date_desc">Mới ứng tuyển</option>
                                            <option value="date_asc">Cũ nhất</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. Desktop Table View (>= 768px) -->
                            <div class="ent-section-box p-0 overflow-hidden mb-4 ent-desktop-table-container">
                                <div class="table-responsive">
                                    <table class="ent-applicant-table" id="applicants-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 26%; min-width: 250px;">Ứng viên</th>
                                                <th style="width: 23%;">Kỹ năng chính</th>
                                                <th style="width: 120px;">Ngày ứng tuyển</th>
                                                <th style="width: 130px; text-align: center;">Độ phù hợp</th>
                                                <th style="width: 120px;">Trạng thái</th>
                                                <th style="width: 170px; text-align: right;">Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody id="applicants-tbody">
                                            <!-- Dynamically populated via applicant-management.js -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 5. Mobile Cards View (< 768px) -->
                            <div class="ent-mobile-cards-container mb-4" id="applicants-mobile-cards">
                                <!-- Dynamically populated via applicant-management.js -->
                            </div>

                            <!-- Empty Filter Results -->
                            <div class="ent-empty-state" id="applicants-empty-state" style="display: none; padding: 3rem 1.5rem;">
                                <div class="ent-empty-state__icon">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                </div>
                                <h3 class="ent-empty-state__title">Không tìm thấy ứng viên phù hợp</h3>
                                <p class="ent-empty-state__desc">Không có ứng viên nào khớp với từ khóa tìm kiếm hoặc bộ lọc hiện tại.</p>
                                <button type="button" class="btn btn-secondary" id="reset-applicant-filter-btn">Đặt lại bộ lọc</button>
                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <!-- 6. ATS Recruiter Candidate Detail Drawer -->
    <div class="ent-drawer-backdrop" id="ent-drawer-backdrop">
        <div class="ent-drawer-panel ats-candidate-drawer" role="dialog" aria-labelledby="drawer-app-name" aria-modal="true">
            
            <!-- Recruiter Profile Header -->
            <div class="ats-drawer-header">
                <div class="ats-drawer-profile">
                    <div class="ats-drawer-avatar" id="drawer-app-avatar">AN</div>
                    <div class="ats-drawer-info">
                        <div class="ats-drawer-top-row">
                            <h3 class="ats-drawer-name" id="drawer-app-name">Chi tiết Ứng viên</h3>
                            <div id="drawer-score-badge" class="ats-drawer-score-slot"></div>
                        </div>
                        <div class="ats-drawer-position" id="drawer-app-position">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                            <span id="drawer-app-position-title"><?= htmlspecialchars($post ? $post['title'] : 'Thực tập sinh'); ?></span>
                        </div>
                        <div class="ats-drawer-submeta" id="drawer-app-school">
                            <span id="drawer-app-school-text"></span>
                            <span class="ats-meta-divider" id="drawer-app-loc-divider">&bull;</span>
                            <span id="drawer-app-location-text">Hà Nội</span>
                        </div>
                    </div>
                </div>

                <div class="ats-drawer-header-actions">
                    <div class="ats-header-links">
                        <a href="#" id="btn-drawer-passport" class="ats-action-btn ats-action-btn--passport" target="_blank" title="Xem hồ sơ Talent Passport đầy đủ">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                <circle cx="9" cy="10" r="2"></circle>
                                <line x1="15" y1="8" x2="17" y2="8"></line>
                                <line x1="15" y1="12" x2="17" y2="12"></line>
                            </svg>
                            <span>Talent Passport</span>
                        </a>
                        <button type="button" id="btn-drawer-cv" class="ats-action-btn ats-action-btn--cv" title="Xem bản CV đính kèm">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                            </svg>
                            <span>Xem CV</span>
                        </button>
                    </div>
                    <button type="button" class="ent-drawer-close ats-close-btn" id="ent-drawer-close" aria-label="Đóng bảng chi tiết">&times;</button>
                </div>
            </div>

            <!-- Drawer Scrollable Content -->
            <div class="ent-drawer-body ats-drawer-body">
                
                <!-- 1. Candidate Snapshot Bar -->
                <div class="ats-snapshot-bar" aria-label="Thông tin tổng quan ứng viên">
                    <div class="ats-snapshot-item">
                        <span class="ats-snapshot-label">Ngày nộp</span>
                        <span class="ats-snapshot-value" id="drawer-app-date">-</span>
                    </div>
                    <div class="ats-snapshot-divider"></div>
                    <div class="ats-snapshot-item">
                        <span class="ats-snapshot-label">Kinh nghiệm</span>
                        <span class="ats-snapshot-value" id="drawer-snapshot-exp">120h thực án</span>
                    </div>
                    <div class="ats-snapshot-divider"></div>
                    <div class="ats-snapshot-item">
                        <span class="ats-snapshot-label">Độ phù hợp</span>
                        <span class="ats-snapshot-value ats-snapshot-value--score" id="drawer-snapshot-score">95%</span>
                    </div>
                    <div class="ats-snapshot-divider"></div>
                    <div class="ats-snapshot-item">
                        <span class="ats-snapshot-label">Trạng thái</span>
                        <div class="ats-snapshot-value" id="drawer-snapshot-status">
                            <span class="ent-app-status-pill ent-app-status-pill--new">
                                <span class="dot"></span>
                                Mới
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 2. Role Fit Analysis Section -->
                <section class="ats-section">
                    <div class="ats-section-header">
                        <div class="d-flex align-items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-secondary" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <h4 class="ats-section-title">Độ phù hợp với vị trí</h4>
                        </div>
                        <span class="ats-fit-score-text" id="drawer-fit-percentage">95% phù hợp</span>
                    </div>

                    <!-- Progress Indicator -->
                    <div class="ats-fit-progress-wrapper" role="progressbar" aria-valuenow="95" aria-valuemin="0" aria-valuemax="100" id="drawer-fit-progress-aria">
                        <div class="ats-fit-progress-bar">
                            <div class="ats-fit-progress-fill" id="drawer-fit-progress-fill" style="width: 95%;"></div>
                        </div>
                    </div>

                    <!-- Concise Fit Summary Sentence -->
                    <p class="ats-fit-summary-text" id="drawer-fit-summary">
                        Hồ sơ đáp ứng các kỹ năng trọng tâm theo yêu cầu của tin tuyển dụng.
                    </p>

                    <!-- Skills Categorization -->
                    <div class="ats-fit-skills-grid">
                        <div class="ats-skills-group">
                            <span class="ats-skills-group__label">Kỹ năng đáp ứng</span>
                            <div class="d-flex flex-wrap gap-1" id="drawer-matching-skills">
                                <!-- Populated dynamically -->
                            </div>
                        </div>

                        <div class="ats-skills-group">
                            <span class="ats-skills-group__label">Cần lưu ý / Bổ sung</span>
                            <div class="ats-missing-list" id="drawer-missing-reqs">
                                <!-- Populated dynamically -->
                            </div>
                        </div>
                    </div>
                </section>

                <div class="ats-section-divider"></div>

                <!-- 3. Recruitment Pipeline Status Selector -->
                <section class="ats-section">
                    <div class="ats-section-header mb-2">
                        <h4 class="ats-section-title">Quy trình tuyển dụng</h4>
                        <span class="ats-section-hint">Chọn bước xử lý tiếp theo</span>
                    </div>

                    <!-- Linear Positive Pipeline Stepper -->
                    <div class="ats-pipeline-stepper" role="radiogroup" aria-label="Quy trình tuyển dụng">
                        <button type="button" class="ats-pipeline-step is-active" data-status="new" role="radio" aria-checked="true">
                            <span class="ats-pipeline-step__num">1</span>
                            <span class="ats-pipeline-step__label">Mới</span>
                        </button>
                        <div class="ats-pipeline-connector"></div>
                        <button type="button" class="ats-pipeline-step" data-status="reviewing" role="radio" aria-checked="false">
                            <span class="ats-pipeline-step__num">2</span>
                            <span class="ats-pipeline-step__label">Đang xem xét</span>
                        </button>
                        <div class="ats-pipeline-connector"></div>
                        <button type="button" class="ats-pipeline-step" data-status="interviewing" role="radio" aria-checked="false">
                            <span class="ats-pipeline-step__num">3</span>
                            <span class="ats-pipeline-step__label">Phỏng vấn</span>
                        </button>
                        <div class="ats-pipeline-connector"></div>
                        <button type="button" class="ats-pipeline-step ats-pipeline-step--success" data-status="accepted" role="radio" aria-checked="false">
                            <span class="ats-pipeline-step__num">4</span>
                            <span class="ats-pipeline-step__label">Đã nhận</span>
                        </button>
                    </div>

                    <!-- Distinct Negative Reject Action -->
                    <div class="ats-reject-row">
                        <button type="button" class="ats-reject-btn" id="btn-status-reject" data-status="rejected" role="radio" aria-checked="false">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                            <span>Từ chối hồ sơ ứng viên</span>
                        </button>
                        <span class="ats-reject-caption" id="ats-reject-caption-text">Chuyển hồ sơ sang nhóm không tiếp tục tuyển dụng</span>
                    </div>

                    <!-- Hidden inputs for backward-compatible form sync -->
                    <div style="display: none;" id="ats-hidden-radios">
                        <input type="radio" name="drawer_status" value="new" checked>
                        <input type="radio" name="drawer_status" value="reviewing">
                        <input type="radio" name="drawer_status" value="interviewing">
                        <input type="radio" name="drawer_status" value="accepted">
                        <input type="radio" name="drawer_status" value="rejected">
                    </div>
                </section>

                <div class="ats-section-divider"></div>

                <!-- 4. Internal Recruiter Review Note -->
                <section class="ats-section">
                    <div class="ats-section-header mb-1">
                        <label for="drawer-reviewer-note" class="ats-section-title">Ghi chú nội bộ</label>
                        <span class="ats-internal-badge">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Nội bộ
                        </span>
                    </div>
                    <p class="ats-field-helper">Ghi chú đánh giá chuyên môn, trao đổi phỏng vấn hoặc lưu ý nội bộ. Ứng viên không thể nhìn thấy nội dung này.</p>
                    <textarea id="drawer-reviewer-note" 
                              class="ats-note-textarea" 
                              rows="3" 
                              placeholder="Nhập ghi chú đánh giá, nhận xét phỏng vấn hoặc lý do tiếp nhận / từ chối..."></textarea>
                </section>

            </div>

            <!-- Sticky Recruiter Drawer Footer -->
            <div class="ent-drawer-footer ats-drawer-footer">
                <button type="button" class="ats-footer-btn ats-footer-btn--secondary" id="ent-drawer-cancel">Hủy</button>
                <button type="button" class="ats-footer-btn ats-footer-btn--primary" id="btn-save-review">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>Lưu đánh giá</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 7. ATS Recruiter CV Viewer Modal -->
    <div class="ent-cv-modal ats-cv-modal" id="ent-cv-modal" role="dialog" aria-labelledby="cv-modal-student-name" aria-modal="true">
        <div class="ent-cv-modal-content ats-cv-dialog">
            <!-- Modal Pinned Header -->
            <div class="ats-cv-header">
                <div class="ats-cv-header__main">
                    <div class="ats-cv-header__title-row">
                        <span class="ats-cv-badge">Hồ sơ ứng viên</span>
                        <h3 class="ats-cv-candidate-name" id="cv-modal-student-name">Nguyễn Văn An</h3>
                    </div>
                    <div class="ats-cv-header__meta">
                        <span id="cv-modal-position-title"><?= htmlspecialchars($post ? $post['title'] : 'Thực tập sinh'); ?></span>
                        <span class="ats-meta-divider">&bull;</span>
                        <span id="cv-modal-applied-time">Nộp ngày 10/08/2026</span>
                    </div>
                </div>

                <div class="ats-cv-header__actions">
                    <a href="#" id="btn-cv-modal-passport" class="ats-action-btn ats-action-btn--passport" target="_blank" title="Xem Talent Passport">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                            <circle cx="9" cy="10" r="2"></circle>
                            <line x1="15" y1="8" x2="17" y2="8"></line>
                            <line x1="15" y1="12" x2="17" y2="12"></line>
                        </svg>
                        <span>Talent Passport</span>
                    </a>
                    <button type="button" class="ats-action-btn ats-action-btn--download" id="btn-download-cv-file" title="Tải xuống bản PDF">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <span>Tải CV</span>
                    </button>
                    <button type="button" class="ats-close-btn" id="ent-cv-modal-close" aria-label="Đóng CV">&times;</button>
                </div>
            </div>

            <!-- Recruiter Context Banner (Separates ATS evaluation metadata from CV paper) -->
            <div class="ats-cv-recruiter-bar" id="cv-modal-recruiter-bar">
                <div class="ats-cv-recruiter-item">
                    <span class="ats-cv-recruiter-label">Độ tương thích</span>
                    <span class="ats-cv-recruiter-value" id="cv-modal-match-score">95% phù hợp</span>
                </div>
                <div class="ats-cv-recruiter-divider"></div>
                <div class="ats-cv-recruiter-item">
                    <span class="ats-cv-recruiter-label">Trạng thái hồ sơ</span>
                    <div id="cv-modal-status-pill">
                        <span class="ent-app-status-pill ent-app-status-pill--new">
                            <span class="dot"></span> Mới
                        </span>
                    </div>
                </div>
                <div class="ats-cv-recruiter-divider"></div>
                <div class="ats-cv-recruiter-item">
                    <span class="ats-cv-recruiter-label">Hồ sơ đính kèm</span>
                    <span class="ats-cv-recruiter-value ats-cv-filename" id="cv-modal-filename">CV_NguyenVanAn_Frontend.pdf</span>
                </div>
            </div>

            <!-- Modal Scrollable Body (The Resume Sheet) -->
            <div class="ent-cv-modal-body ats-cv-body" id="cv-modal-content-body">
                <!-- Dynamically populated authentic Resume Paper layout -->
            </div>

            <!-- Modal Footer -->
            <div class="ent-drawer-footer ats-cv-footer">
                <div class="ats-cv-footer__meta">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>Hồ sơ đã được xác thực danh tính bởi TalentHub</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="ats-footer-btn ats-footer-btn--secondary" id="btn-cv-close-bottom">Đóng</button>
                    <button type="button" class="ats-footer-btn ats-footer-btn--primary" id="btn-cv-open-review">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        <span>Đánh giá ứng viên</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Toast -->
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

    <!-- Raw JSON Mock Data Pass-through -->
    <script id="applicants-raw-data" type="application/json" data-post-id="<?= $postId; ?>">
        <?= json_encode($applicants, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    </script>
    <script id="talents-raw-data" type="application/json">
        <?= isset($mockTalents) ? json_encode($mockTalents, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]'; ?>
    </script>

    <!-- JavaScript Assets -->
    <script src="../../../assets/js/enterprise.js"></script>
    <script src="../../../assets/js/applicant-management.js"></script>
</body>
</html>
