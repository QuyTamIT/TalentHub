<?php
/**
 * TalentHub Enterprise - Internship Applicants Management Route
 * 
 * Route: app/enterprise/internships/applicants.php?postId=...
 */

require_once __DIR__ . '/../includes/internships-data.php';
require_once __DIR__ . '/../includes/applicants-data.php';

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
                                <h3 class="ent-section-box__title mb-2" style="font-size: 1.25rem;">
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
                                                <th style="width: 25%;">Ứng viên</th>
                                                <th style="width: 24%;">Kỹ năng chính</th>
                                                <th style="width: 12%;">Ngày ứng tuyển</th>
                                                <th style="width: 12%; text-align: center;">Độ phù hợp</th>
                                                <th style="width: 11%;">Trạng thái</th>
                                                <th style="width: 16%; text-align: right;">Thao tác</th>
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

    <!-- 6. Side Drawer Panel for Applicant Detail & Review -->
    <div class="ent-drawer-backdrop" id="ent-drawer-backdrop">
        <div class="ent-drawer-panel" role="dialog" aria-labelledby="drawer-app-name">
            <div class="ent-drawer-header">
                <div>
                    <h3 class="ent-drawer-header__title" id="drawer-app-name">Chi tiết Ứng viên</h3>
                    <div class="text-secondary" style="font-size: 0.8125rem;" id="drawer-app-school"></div>
                </div>
                <button type="button" class="ent-drawer-close" id="ent-drawer-close" aria-label="Đóng">&times;</button>
            </div>

            <div class="ent-drawer-body">
                <!-- Applicant Summary & Score -->
                <div class="d-flex align-items-center justify-content-between p-3 mb-4 rounded" style="background-color: #F8FAFC; border: 1px solid #E2E8F0;">
                    <div>
                        <div class="text-muted" style="font-size: 0.75rem;">Ngày nộp hồ sơ</div>
                        <div class="font-medium text-dark" id="drawer-app-date" style="font-size: 0.875rem;"></div>
                    </div>
                    <div id="drawer-score-badge"></div>
                </div>

                <!-- Action Links -->
                <div class="d-flex align-items-center gap-2 mb-4">
                    <a href="#" id="btn-drawer-passport" class="btn btn-secondary btn-sm flex-1 text-center" target="_blank">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                            <circle cx="9" cy="10" r="2"></circle>
                            <line x1="15" y1="8" x2="17" y2="8"></line>
                            <line x1="15" y1="12" x2="17" y2="12"></line>
                        </svg>
                        <span>Xem Talent Passport</span>
                    </a>
                    <button type="button" id="btn-drawer-cv" class="btn btn-secondary btn-sm flex-1">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        <span>Xem CV</span>
                    </button>
                </div>

                <!-- Matching Skills Analysis -->
                <div class="mb-4">
                    <h4 class="font-semibold text-dark mb-2" style="font-size: 0.9375rem;">Kỹ năng đáp ứng</h4>
                    <div class="d-flex flex-wrap gap-1 mb-3" id="drawer-matching-skills"></div>

                    <h4 class="font-semibold text-dark mb-2" style="font-size: 0.9375rem;">Điểm cần lưu ý / Yêu cầu còn thiếu</h4>
                    <div id="drawer-missing-reqs"></div>
                </div>

                <hr style="border-color: #E2E8F0; margin: 1.5rem 0;">

                <!-- Review & Status Update Form -->
                <div>
                    <h4 class="font-bold text-dark mb-1" style="font-size: 1rem;">Cập nhật trạng thái ứng tuyển</h4>
                    <p class="text-secondary mb-3" style="font-size: 0.8125rem;">Thay đổi trạng thái hồ sơ và ghi chú đánh giá của Doanh nghiệp.</p>

                    <div class="ent-status-select-grid">
                        <label class="ent-status-radio-card">
                            <input type="radio" name="drawer_status" value="new">
                            <span>🔵 Mới</span>
                        </label>
                        <label class="ent-status-radio-card">
                            <input type="radio" name="drawer_status" value="reviewing">
                            <span>🟡 Đang xem xét</span>
                        </label>
                        <label class="ent-status-radio-card">
                            <input type="radio" name="drawer_status" value="interviewing">
                            <span>🟣 Phỏng vấn</span>
                        </label>
                        <label class="ent-status-radio-card">
                            <input type="radio" name="drawer_status" value="accepted">
                            <span>🟢 Đã nhận</span>
                        </label>
                        <label class="ent-status-radio-card" style="grid-column: span 2;">
                            <input type="radio" name="drawer_status" value="rejected">
                            <span>🔴 Từ chối</span>
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="drawer-reviewer-note" class="form-label font-medium text-dark" style="font-size: 0.875rem;">
                            Ghi chú của người đánh giá (Reviewer Note)
                        </label>
                        <textarea id="drawer-reviewer-note" 
                                  class="ent-contact-textarea" 
                                  rows="3" 
                                  placeholder="Nhập ghi chú đánh giá chuyên môn, nhận xét phỏng vấn hoặc lý do tiếp nhận/từ chối..."></textarea>
                    </div>
                </div>
            </div>

            <div class="ent-drawer-footer">
                <button type="button" class="btn btn-secondary" id="ent-drawer-cancel">Hủy</button>
                <button type="button" class="btn btn-primary" id="btn-save-review">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <span>Lưu đánh giá</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 7. Lightbox Modal for CV View -->
    <div class="ent-cv-modal" id="ent-cv-modal">
        <div class="ent-cv-modal-content">
            <div class="ent-cv-modal-header">
                <div>
                    <h3 class="font-bold text-dark mb-0" style="font-size: 1.1rem;">
                        Xem CV Ứng viên: <span id="cv-modal-student-name" class="text-primary"></span>
                    </h3>
                </div>
                <button type="button" class="ent-drawer-close" id="ent-cv-modal-close" aria-label="Đóng">&times;</button>
            </div>

            <div class="ent-cv-modal-body" id="cv-modal-content-body">
                <!-- Dynamically populated CV layout -->
            </div>

            <div class="ent-drawer-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('ent-cv-modal').classList.remove('is-open')">Đóng</button>
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

    <!-- JavaScript Assets -->
    <script src="../../../assets/js/enterprise.js"></script>
    <script src="../../../assets/js/applicant-management.js"></script>
</body>
</html>
