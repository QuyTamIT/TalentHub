<?php
/**
 * TalentHub Enterprise - Internship Applicants Placeholder Route
 * 
 * Target Route: app/enterprise/internships/applicants.php?postId=...
 */

require_once __DIR__ . '/../includes/internships-data.php';

$enterpriseInfo = [
    'company_name' => 'FPT Software',
    'account_type' => 'Gói Premium',
    'logo_initials' => 'FPT',
    'new_matches_count' => 86,
    'total_talents' => 1247
];

$postId = isset($_GET['postId']) ? intval($_GET['postId']) : 1;
$post = getMockInternshipById($postId);

$pageTitle = $post ? ('Danh sách Ứng viên - ' . $post['title']) : 'Danh sách Ứng viên Thực tập';
$currentRoute = '/app/enterprise/internships/applicants.php';

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
    <meta name="description" content="Quản lý danh sách ứng viên thực tập nộp hồ sơ vào tin tuyển dụng Enterprise TalentHub.">
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
                    
                    <!-- Back Navigation Bar -->
                    <div class="ent-back-bar">
                        <a href="index.php" class="ent-back-link">
                            &larr; Quay lại Quản lý Tuyển thực tập
                        </a>
                    </div>

                    <?php if (!$post): ?>
                        <div class="ent-empty-state" style="margin-top: 2rem;">
                            <h3 class="ent-empty-state__title">Không tìm thấy tin tuyển dụng</h3>
                            <p class="ent-empty-state__desc">Tin tuyển dụng với mã số #<?= htmlspecialchars($postId); ?> không tồn tại.</p>
                            <a href="index.php" class="btn btn-primary">&larr; Quay lại Danh sách</a>
                        </div>
                    <?php else: ?>
                        <!-- Post Header Card -->
                        <div class="ent-section-box mb-4">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h2 class="ent-section-box__title" style="font-size: 1.25rem;">
                                            <?= htmlspecialchars($post['title']); ?>
                                        </h2>
                                        <span class="ent-status-pill ent-status-pill--<?= $post['status']; ?>">
                                            <span class="dot"></span>
                                            <?= htmlspecialchars($post['status_label']); ?>
                                        </span>
                                    </div>
                                    <p class="ent-section-box__subtitle">
                                        Lĩnh vực: <strong><?= htmlspecialchars($post['field']); ?></strong> &bull; Hạn ứng tuyển: <strong><?= htmlspecialchars($post['deadline']); ?></strong> &bull; Cần tuyển: <strong><?= htmlspecialchars($post['slots']); ?> chỉ tiêu</strong>
                                    </p>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="create.php?id=<?= $post['id']; ?>" class="btn btn-secondary btn-sm">Sửa tin</a>
                                    <span class="ent-applicant-count-badge" style="font-size: 0.875rem; padding: 0.4rem 0.8rem;">
                                        <?= $post['applicant_count']; ?> ứng viên đã nộp
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Applicant Module Placeholder Card -->
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
                                Danh sách Ứng viên Nộp hồ sơ (Mã tin #<?= $post['id']; ?>)
                            </h3>
                            <p class="ent-section-box__subtitle max-w-600 auto-x mb-4">
                                Hiện đang có <strong><?= $post['applicant_count']; ?> hồ sơ ứng viên</strong> đăng ký tham gia tuyển dụng vị trí này. Tính năng duyệt và đánh giá hồ sơ ứng viên trực tiếp sẽ sẵn sàng trong phiên bản tích hợp API tiếp theo.
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
                                    Quay lại Quản lý Tuyển thực tập
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

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
</body>
</html>
