<?php
/**
 * TalentHub Enterprise - Internship Management ("Tuyển thực tập") Main Page
 * 
 * Features:
 * - Summary metrics (Total, Active, Draft, Closed)
 * - Search by title, filter by status & field, sort
 * - Internship list table with quick actions (View Applicants, Edit, Close/Reopen/Publish)
 * - Primary CTA for "+ Đăng tin mới"
 */

require_once __DIR__ . '/../includes/internships-data.php';

$enterpriseInfo = [
    'company_name' => 'FPT Software',
    'account_type' => 'Gói Premium',
    'logo_initials' => 'FPT',
    'new_matches_count' => 86,
    'total_talents' => 1247
];

$pageTitle = 'Tuyển thực tập';
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
        'route' => '/app/enterprise/analytics',
        'icon' => 'bar-chart',
        'active' => false
    ]
];

$posts = getMockInternships();
$metrics = getInternshipMetrics();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Quản lý tin tuyển dụng thực tập doanh nghiệp trên TalentHub Enterprise.">
    <title>Tuyển thực tập - Enterprise | TalentHub</title>
    
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
                    
                    <!-- Enterprise Internship Hero Banner -->
                    <div class="ent-internship-hero">
                        <div class="ent-internship-hero__content">
                            <div class="ent-internship-hero__badge">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                                <span>Khu vực Tuyển dụng & Thống kê</span>
                            </div>
                            <h2 class="ent-internship-hero__title">Quản lý Tuyển dụng Thực tập</h2>
                            <p class="ent-internship-hero__desc">
                                Đăng tin tuyển dụng thực tập sinh, theo dõi số lượng ứng viên và tiếp nhận hồ sơ từ các trường đối tác trên toàn quốc.
                            </p>
                        </div>
                        <div class="ent-internship-hero__action">
                            <a href="create.php" class="btn btn-primary ent-btn-create-hero" id="btn-create-internship">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                <span>Đăng tin mới</span>
                            </a>
                        </div>
                    </div>

                    <!-- Summary Metrics Cards Grid -->
                    <div class="ent-internship-metrics-grid">
                        <div class="ent-metric-card" data-metric="total">
                            <div class="ent-metric-card__header">
                                <span class="label">Tổng số tin</span>
                                <span class="ent-metric-card__icon text-secondary">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                    </svg>
                                </span>
                            </div>
                            <div class="ent-metric-card__value" id="metric-total"><?= $metrics['total']; ?></div>
                            <div class="ent-metric-card__footer">Tất cả tin tuyển dụng</div>
                        </div>

                        <div class="ent-metric-card ent-metric-card--active" data-metric="active">
                            <div class="ent-metric-card__header">
                                <span class="label">Đang tuyển</span>
                                <span class="ent-metric-card__icon text-accent">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                </span>
                            </div>
                            <div class="ent-metric-card__value text-accent" id="metric-active"><?= $metrics['active']; ?></div>
                            <div class="ent-metric-card__footer">Đang hiển thị cho người học</div>
                        </div>

                        <div class="ent-metric-card" data-metric="draft">
                            <div class="ent-metric-card__header">
                                <span class="label">Bản nháp</span>
                                <span class="ent-metric-card__icon text-muted">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </span>
                            </div>
                            <div class="ent-metric-card__value" id="metric-draft"><?= $metrics['draft']; ?></div>
                            <div class="ent-metric-card__footer">Chưa phát hành</div>
                        </div>

                        <div class="ent-metric-card" data-metric="closed">
                            <div class="ent-metric-card__header">
                                <span class="label">Đã đóng</span>
                                <span class="ent-metric-card__icon text-muted">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                    </svg>
                                </span>
                            </div>
                            <div class="ent-metric-card__value" id="metric-closed"><?= $metrics['closed']; ?></div>
                            <div class="ent-metric-card__footer">Đã tạm ngưng / hoàn tất đợt tuyển</div>
                        </div>
                    </div>

                    <!-- Search & Filter Controls Toolbar -->
                    <div class="ent-search-toolbar">
                        <div class="ent-internship-filter-row">
                            <!-- Title Search Input -->
                            <div class="ent-search-input-wrapper flex-1">
                                <svg class="ent-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                <input type="text" 
                                       id="internship-search-input" 
                                       class="ent-search-input" 
                                       placeholder="Tìm kiếm theo tiêu đề vị trí tuyển dụng (Frontend, AI, Backend...)"
                                       aria-label="Tìm kiếm tin tuyển dụng">
                                <button type="button" class="ent-search-clear" id="internship-search-clear" aria-label="Xóa tìm kiếm" style="display: none;">&times;</button>
                            </div>

                            <!-- Status Filter -->
                            <div class="ent-filter-select-wrapper">
                                <select id="filter-status-select" class="ent-filter-select">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="active">Đang tuyển</option>
                                    <option value="draft">Bản nháp</option>
                                    <option value="closed">Đã đóng</option>
                                </select>
                            </div>

                            <!-- Field Filter -->
                            <div class="ent-filter-select-wrapper">
                                <select id="filter-field-select" class="ent-filter-select">
                                    <option value="">Tất cả lĩnh vực</option>
                                    <option value="Công nghệ thông tin">Công nghệ thông tin</option>
                                    <option value="AI / Machine Learning">AI / Machine Learning</option>
                                    <option value="Thiết kế UI/UX">Thiết kế UI/UX</option>
                                    <option value="Marketing Digital">Marketing Digital</option>
                                </select>
                            </div>

                            <!-- Sort Select -->
                            <div class="ent-filter-select-wrapper">
                                <select id="sort-select" class="ent-filter-select">
                                    <option value="newest">Mới nhất</option>
                                    <option value="deadline">Sắp hết hạn ứng tuyển</option>
                                    <option value="applicants">Số ứng viên nhiều nhất</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Internship Posts Management Table -->
                    <div class="ent-section-box p-0 overflow-hidden">
                        <div class="table-responsive">
                            <table class="ent-table" id="internship-posts-table">
                                <thead>
                                    <tr>
                                        <th style="min-width: 260px;">Vị trí tuyển dụng</th>
                                        <th style="width: 120px;">Trạng thái</th>
                                        <th style="width: 110px;">Ngày đăng</th>
                                        <th style="width: 110px;">Hạn nộp</th>
                                        <th style="width: 90px; text-align: center;">Số lượng</th>
                                        <th style="width: 120px; text-align: center;">Ứng viên</th>
                                        <th style="width: 160px; text-align: right;">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody id="internship-tbody">
                                    <?php foreach ($posts as $post): ?>
                                        <tr data-post-id="<?= $post['id']; ?>" 
                                            data-status="<?= $post['status']; ?>" 
                                            data-field="<?= htmlspecialchars($post['field']); ?>"
                                            data-title="<?= htmlspecialchars(mb_strtolower($post['title'])); ?>">
                                            
                                            <td>
                                                <a href="create.php?id=<?= $post['id']; ?>" class="ent-post-title-link">
                                                    <?= htmlspecialchars($post['title']); ?>
                                                </a>
                                                <div class="ent-post-submeta">
                                                    <span><?= htmlspecialchars($post['field']); ?></span>
                                                    <span class="dot">&bull;</span>
                                                    <span><?= htmlspecialchars($post['work_type']); ?></span>
                                                </div>
                                            </td>

                                            <td>
                                                <?php if ($post['status'] === 'active'): ?>
                                                    <span class="ent-status-pill ent-status-pill--active">
                                                        <span class="dot"></span>
                                                        <?= htmlspecialchars($post['status_label']); ?>
                                                    </span>
                                                <?php elseif ($post['status'] === 'draft'): ?>
                                                    <span class="ent-status-pill ent-status-pill--draft">
                                                        <span class="dot"></span>
                                                        <?= htmlspecialchars($post['status_label']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="ent-status-pill ent-status-pill--closed">
                                                        <span class="dot"></span>
                                                        <?= htmlspecialchars($post['status_label']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <span class="text-secondary"><?= htmlspecialchars($post['created_at']); ?></span>
                                            </td>

                                            <td>
                                                <span class="text-primary font-medium"><?= htmlspecialchars($post['deadline']); ?></span>
                                            </td>

                                            <td class="text-center">
                                                <span class="font-medium text-primary"><?= htmlspecialchars($post['slots']); ?></span>
                                            </td>

                                            <td class="text-center">
                                                <span class="ent-applicant-count-text">
                                                    <?= htmlspecialchars($post['applicant_count']); ?> ứng viên
                                                </span>
                                            </td>

                                            <td class="text-right">
                                                <div class="ent-table-actions">
                                                    <a href="applicants.php?postId=<?= $post['id']; ?>" 
                                                       class="btn btn-secondary btn-sm ent-btn-view-applicants" 
                                                       title="Xem danh sách ứng viên">
                                                        Xem ứng viên
                                                    </a>
                                                    <div class="ent-dropdown">
                                                        <button type="button" class="btn btn-secondary btn-sm ent-dropdown-toggle" aria-label="Tùy chọn thao tác">
                                                            &ctdot;
                                                        </button>
                                                        <div class="ent-dropdown-menu">
                                                            <a href="create.php?id=<?= $post['id']; ?>" class="ent-dropdown-item">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                                </svg>
                                                                Sửa
                                                            </a>
                                                            <?php if ($post['status'] === 'draft'): ?>
                                                                <button type="button" class="ent-dropdown-item action-change-status" data-post-id="<?= $post['id']; ?>" data-target-status="active">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                                    Đăng tuyển
                                                                </button>
                                                            <?php elseif ($post['status'] === 'active'): ?>
                                                                <button type="button" class="ent-dropdown-item action-change-status" data-post-id="<?= $post['id']; ?>" data-target-status="closed">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line></svg>
                                                                    Đóng tin
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" class="ent-dropdown-item action-change-status" data-post-id="<?= $post['id']; ?>" data-target-status="active">
                                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"></path><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                                                                    Mở lại
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty State Container -->
                        <div class="ent-empty-state" id="internships-empty-state" style="display: none; padding: 3rem 1.5rem;">
                            <div class="ent-empty-state__icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                </svg>
                            </div>
                            <h3 class="ent-empty-state__title">Không tìm thấy tin tuyển dụng</h3>
                            <p class="ent-empty-state__desc">Không có tin tuyển dụng nào phù hợp với từ khóa hoặc bộ lọc của bạn.</p>
                            <button type="button" class="btn btn-secondary" id="reset-search-btn">Đặt lại bộ lọc</button>
                        </div>
                    </div>

                </div>
            </main>
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

    <!-- JavaScript Assets -->
    <script src="../../../assets/js/enterprise.js"></script>
    <script src="../../../assets/js/internship-management.js"></script>
</body>
</html>
