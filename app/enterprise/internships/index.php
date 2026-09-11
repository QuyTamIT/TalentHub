<?php
/**
 * TalentHub Enterprise - Internship Management ("Tuyển thực tập") Main Page
 * 
 * Rebuilt Figma-Grade SaaS Dashboard:
 * - Part 1: Clean Page Header + Single "+" Icon CTA Button
 * - Part 2: Restored Single-Row Search & Filter Toolbar
 * - Part 3: 3-Column / Auto-Fill Responsive Grid of Squared, High-Depth Job Cards
 * - Part 4: Clean Artistic Empty State
 */

require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 3) . '/src/Bootstrap/EnterpriseAppContext.php';
require_once __DIR__ . '/../includes/internships-data.php';

use TalentHub\Bootstrap\EnterpriseAppContext;

$context = (new EnterpriseAppContext())->boot();
$user       = $context['user'];
$enterprise = $context['enterprise'];
$context['permissions']->require((string) $user['id'], 'internship_post.read_own_business');

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

if (!function_exists('format_vietnamese_date')) {
    function format_vietnamese_date(?string $rawDate): string {
        if ($rawDate === null || trim($rawDate) === '' || $rawDate === '0000-00-00 00:00:00') {
            return 'Không giới hạn';
        }
        $rawDate = trim($rawDate);
        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $rawDate)) {
            return $rawDate;
        }
        try {
            $dt = new DateTime($rawDate);
            return $dt->format('d/m/Y');
        } catch (\Throwable $e) {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $rawDate, $matches)) {
                return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
            }
            return $rawDate;
        }
    }
}

$companyInitials = getInitials($enterprise['name']);
$isVerified = ($enterprise['verificationStatus'] ?? 'pending') === 'verified';
$accountType = $isVerified ? 'Doanh nghiệp Đã xác thực' : 'Tài khoản Doanh nghiệp';

$enterpriseInfo = [
    'id'                => $enterprise['id'],
    'company_name'      => $enterprise['name'],
    'account_type'      => $accountType,
    'logo_initials'     => $companyInitials,
    'logo_url'          => $enterprise['logoUrl'] ?? null,
    'new_matches_count' => 0,
    'total_talents'     => 0,
];

// $pageTitle = 'Tuyển thực tập sinh';
$currentRoute = '/app/enterprise/internships/';

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
        'active' => false,
    ],
    [
        'title'  => 'Tuyển thực tập',
        'route'  => '/app/enterprise/internships/',
        'icon'   => 'briefcase',
        'active' => true,
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

$statusLabels = ['draft' => 'Bản nháp', 'active' => 'Đang tuyển', 'closed' => 'Đã đóng', 'cancelled' => 'Đã hủy'];
$postRows = [];
$pdo = $context['pdo'] ?? null;

if ($pdo) {
    try {
        $entId = $enterprise['id'];
        $sql = "SELECT p.*, COUNT(DISTINCT a.studentId) AS applicantCount 
                FROM internship_posts p 
                LEFT JOIN internship_applications a ON p.id = a.postId 
                WHERE p.enterpriseId = :eid 
                GROUP BY p.id 
                ORDER BY p.createdAt DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['eid' => $entId]);
        $postRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        error_log('Error fetching internship posts: ' . $e->getMessage());
    }
}
$posts = array_map(static function (array $post) use ($statusLabels): array {
    return $post + [
        'status_label' => $statusLabels[$post['status']] ?? ($post['status'] === 'active' ? 'Đang tuyển' : $post['status']),
        'work_type' => $post['workType'] ?? 'Toàn thời gian',
        'location_clean' => $post['location'] ?? 'Toàn quốc / Hybrid',
        'duration_clean' => !empty($post['duration']) ? $post['duration'] : '3 - 6 tháng',
        'created_at_formatted' => format_vietnamese_date($post['createdAt'] ?? ''),
        'deadline_formatted' => format_vietnamese_date($post['deadline'] ?? ''),
        'applicant_count' => (int) ($post['applicantCount'] ?? 0),
    ];
}, $postRows);

$metrics = ['total' => count($posts), 'active' => 0, 'draft' => 0, 'closed' => 0, 'total_applicants' => 0];
foreach ($posts as $post) {
    if (isset($metrics[$post['status']])) { $metrics[$post['status']]++; }
    $metrics['total_applicants'] += (int) $post['applicant_count'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Quản lý tin tuyển dụng thực tập doanh nghiệp trên TalentHub Enterprise.">
    <title>Tuyển thực tập sinh - Enterprise | TalentHub</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/global.css">
    <link rel="stylesheet" href="../../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../../assets/css/polish.css">
    <link rel="stylesheet" href="../../../assets/css/enterprise.css?v=<?= filemtime(dirname(__DIR__, 3) . '/assets/css/enterprise.css'); ?>">
    <link rel="stylesheet" href="../../../assets/css/typeui-selects.css">
</head>
<body class="enterprise-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>

    <!-- Layout Wrapper -->
    <div class="ent-layout">
        
        <!-- Sidebar Navigation Partial -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="ent-main-wrapper">
            
            <!-- Top Header Partial -->
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <!-- Page Body Content -->
            <main class="ent-body" id="main-content">
                <div class="container-fluid">
                    
                    <?php if (!empty($_SESSION['flash_message'])): ?>
                        <div class="ent-alert ent-alert--success mb-4" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 0.875rem 1.25rem; border-radius: 8px; font-weight: 500; display: flex; align-items: center; justify-content: space-between;">
                            <span><?= htmlspecialchars($_SESSION['flash_message']); ?></span>
                            <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #166534; line-height: 1;">&times;</button>
                        </div>
                        <?php unset($_SESSION['flash_message']); ?>
                    <?php endif; ?>

                    <!-- PHẦN 1: HEADER & NÚT CTA (Không bị lặp dấu +) -->
                    <div class="ent-page-header">
                        <div class="ent-page-header__left">
                            <h1 class="ent-page-header__title">
                                Tuyển thực tập sinh
                            </h1>
                            <p class="ent-page-header__subtitle">
                                <?= count($posts); ?> tin đăng &bull; <?= $metrics['total_applicants']; ?> ứng viên đang xét duyệt
                            </p>
                        </div>
                        <div class="ent-page-header__actions">
                            <a href="<?= function_exists('app_href') ? app_href('/app/enterprise/internships/create.php') : 'create.php'; ?>" 
                               class="ent-btn-create-post" 
                               id="btn-create-internship">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                <span>Đăng tin mới</span>
                            </a>
                        </div>
                    </div>

                    <!-- PHẦN 2: THANH TÌM KIẾM & BỘ LỌC (SEARCH & FILTER TOOLBAR) -->
                    <div class="ent-search-toolbar">
                        <div class="ent-internship-filter-row">
                            <!-- Ô Tìm kiếm -->
                            <div class="ent-search-input-wrapper">
                                <svg class="ent-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                <input type="text" 
                                       id="internship-search-input" 
                                       class="ent-search-input" 
                                       placeholder="Tìm theo tiêu đề vị trí tuyển dụng (Frontend, AI, Backend...)"
                                       aria-label="Tìm kiếm tin tuyển dụng">
                                <button type="button" class="ent-search-clear" id="internship-search-clear" aria-label="Xóa tìm kiếm">&times;</button>
                            </div>

                            <!-- Lọc Trạng thái -->
                            <div class="ent-filter-select-wrapper ent-filter-select-wrapper--status">
                                <select id="filter-status-select" class="ent-filter-select typeui-select typeui-select--compact" aria-label="Lọc theo trạng thái">
                                    <option value="">Tất cả trạng thái</option>
                                    <option value="active">Đang tuyển</option>
                                    <option value="draft">Bản nháp</option>
                                    <option value="closed">Đã đóng</option>
                                </select>
                            </div>

                            <!-- Lọc Lĩnh vực -->
                            <div class="ent-filter-select-wrapper ent-filter-select-wrapper--field">
                                <select id="filter-field-select" class="ent-filter-select typeui-select typeui-select--compact" aria-label="Lọc theo lĩnh vực">
                                    <option value="">Tất cả lĩnh vực</option>
                                    <option value="Công nghệ thông tin">Công nghệ thông tin</option>
                                    <option value="AI / Machine Learning">AI / Machine Learning</option>
                                    <option value="Thiết kế UI/UX">Thiết kế UI/UX</option>
                                    <option value="Marketing Digital">Marketing Digital</option>
                                </select>
                            </div>

                            <!-- Sắp xếp -->
                            <div class="ent-filter-select-wrapper ent-filter-select-wrapper--sort">
                                <select id="sort-select" class="ent-filter-select typeui-select typeui-select--compact" aria-label="Sắp xếp danh sách">
                                    <option value="newest">Mới nhất</option>
                                    <option value="deadline">Sắp hết hạn</option>
                                    <option value="applicants">Số ứng viên</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- PHẦN 3: LƯỚI CARD CÔNG VIỆC VUÔNG VẮN, GIÀU THÔNG TIN (Grid 3 Cột / 2 Cột) -->
                    <div class="ent-job-grid" id="internship-cards-container" style="<?= empty($posts) ? 'display: none;' : ''; ?>">
                        <?php if (!empty($posts)): ?>
                            <?php foreach ($posts as $post): 
                                $postId = (string) $post['id'];
                                $field = (string) ($post['field'] ?? 'Công nghệ thông tin');
                                $workType = (string) ($post['work_type'] ?? 'Toàn thời gian');
                                $locationClean = (string) ($post['location_clean'] ?? 'Toàn quốc / Hybrid');
                                $durationClean = (string) ($post['duration_clean'] ?? '3 - 6 tháng');
                                $deadlineClean = (string) ($post['deadline_formatted'] ?? 'Không giới hạn');
                                $applicantCount = (int) ($post['applicant_count'] ?? 0);
                                $status = (string) ($post['status'] ?? 'draft');
                                $statusLabel = (string) ($post['status_label'] ?? 'Đang tuyển');

                                $detailUrl = function_exists('app_href') ? app_href('/app/enterprise/internships/create.php?id=' . urlencode($postId)) : ('create.php?id=' . urlencode($postId));
                                $applicantsUrl = function_exists('app_href') ? app_href('/app/enterprise/internships/applicants.php?postId=' . urlencode($postId)) : ('applicants.php?postId=' . urlencode($postId));
                            ?>
                                <!-- Thẻ Card Tin Tuyển Dụng Độc Lập Có Chiều Sâu -->
                                <article class="ent-job-card-box" 
                                         data-post-id="<?= htmlspecialchars($postId); ?>" 
                                         data-status="<?= htmlspecialchars($status); ?>" 
                                         data-field="<?= htmlspecialchars($field); ?>"
                                         data-title="<?= htmlspecialchars(mb_strtolower($post['title'])); ?>"
                                         data-deadline="<?= htmlspecialchars($post['deadline'] ?? ''); ?>"
                                         style="background: #FFFFFF; border: 1px solid #F0E6DD; border-radius: 16px; padding: 22px; box-shadow: 0 4px 16px -2px rgba(50, 32, 20, 0.04); display: flex; flex-direction: column; justify-content: space-between; gap: 16px; box-sizing: border-box;">
                                    
                                    <!-- Header Card: Icon Lĩnh Vực Nhỏ + Badge Trạng Thái -->
                                    <div class="ent-job-card-box__header" style="display: flex; align-items: center; justify-content: space-between; gap: 12px;">
                                        <div class="ent-job-card-box__field-icon" style="width: 36px; height: 36px; border-radius: 10px; background-color: #FFF0EB; color: #FF6B45; border: 1px solid #FFDACB; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" aria-hidden="true">
                                            <?php if (stripos($field, 'AI') !== false || stripos($field, 'Machine') !== false): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                                </svg>
                                            <?php elseif (stripos($field, 'UI') !== false || stripos($field, 'Thiết kế') !== false): ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path>
                                                    <path d="M2 12h20"></path>
                                                </svg>
                                            <?php else: ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                                </svg>
                                            <?php endif; ?>
                                        </div>

                                        <span class="ent-status-pill-wrapper">
                                            <?php if ($status === 'active'): ?>
                                                <span class="ent-status-pill ent-status-pill--active" style="background: #DCFCE7; color: #15803D; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px;">
                                                    <span class="dot" style="width: 6px; height: 6px; border-radius: 50%; background: #16A34A;"></span>
                                                    <span><?= htmlspecialchars($statusLabel); ?></span>
                                                </span>
                                            <?php elseif ($status === 'draft'): ?>
                                                <span class="ent-status-pill ent-status-pill--draft" style="background: #FEF3C7; color: #B45309; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px;">
                                                    <span class="dot" style="width: 6px; height: 6px; border-radius: 50%; background: #F59E0B;"></span>
                                                    <span><?= htmlspecialchars($statusLabel); ?></span>
                                                </span>
                                            <?php else: ?>
                                                <span class="ent-status-pill ent-status-pill--closed" style="background: #FEE2E2; color: #B91C1C; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px; display: inline-flex; align-items: center; gap: 5px;">
                                                    <span class="dot" style="width: 6px; height: 6px; border-radius: 50%; background: #EF4444;"></span>
                                                    <span><?= htmlspecialchars($statusLabel); ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <!-- Body Card: Tiêu đề, Meta Info & Khối Đếm Hồ Sơ -->
                                    <div class="ent-job-card-box__body" style="display: flex; flex-direction: column; gap: 12px; flex: 1;">
                                        <a href="<?= htmlspecialchars($detailUrl); ?>" class="ent-job-card-box__title" style="font-size: 16px; font-weight: 700; color: #322014; text-decoration: none; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 44px;">
                                            <?= htmlspecialchars($post['title']); ?>
                                        </a>

                                        <!-- Meta Info List -->
                                        <div class="ent-job-card-box__meta-list" style="display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: #6B5548;">
                                            <div class="ent-job-card-box__meta-item" style="display: inline-flex; align-items: center; gap: 6px;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #9E897D;">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                                    <circle cx="12" cy="10" r="3"></circle>
                                                </svg>
                                                <span><?= htmlspecialchars($locationClean); ?></span>
                                            </div>

                                            <div class="ent-job-card-box__meta-item" style="display: inline-flex; align-items: center; gap: 6px;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #9E897D;">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <polyline points="12 6 12 12 16 14"></polyline>
                                                </svg>
                                                <span><?= htmlspecialchars($durationClean); ?> &bull; <?= htmlspecialchars($workType); ?></span>
                                            </div>

                                            <div class="ent-job-card-box__meta-item" data-meta="deadline" style="display: inline-flex; align-items: center; gap: 6px;">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #9E897D;">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                                </svg>
                                                <span>Hạn nộp: <strong><?= htmlspecialchars($deadlineClean); ?></strong></span>
                                            </div>
                                        </div>

                                        <!-- Badge Đếm Hồ Sơ Nhỏ Gọn -->
                                        <a href="<?= htmlspecialchars($applicantsUrl); ?>" 
                                           class="ent-job-card-box__applicants-badge"
                                           title="Xem danh sách ứng viên đã nộp hồ sơ"
                                           style="background-color: #FFF0EB; border: 1px solid #FFDACB; border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 600; color: #FF6B45; display: inline-flex; align-items: center; gap: 6px; width: fit-content; text-decoration: none;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                            </svg>
                                            <span><strong class="ent-applicant-num"><?= $applicantCount; ?></strong> hồ sơ đã nộp</span>
                                        </a>
                                    </div>

                                    <!-- Footer Card: 2 Nút Bấm 50/50 -->
                                    <div class="ent-job-card-box__footer" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; padding-top: 14px; border-top: 1px solid #F1F5F9; margin-top: auto;">
                                        <a href="<?= htmlspecialchars($applicantsUrl); ?>" 
                                           class="ent-btn-box-view">
                                            Xem ứng viên
                                        </a>
                                        <a href="<?= htmlspecialchars($detailUrl); ?>" 
                                           class="ent-btn-box-edit">
                                            Chỉnh sửa
                                        </a>
                                    </div>

                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- PHẦN 4: TRẠNG THÁI TRỐNG SẠCH SẼ (Empty State) -->
                    <div class="ent-internship-empty-state" id="internships-empty-state" style="<?= empty($posts) ? 'display: flex;' : 'display: none;'; ?>">
                        <div class="ent-internship-empty-state__graphic" aria-hidden="true">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                            </svg>
                        </div>
                        <h2 class="ent-internship-empty-state__title">
                            <?= empty($posts) ? 'Chưa có tin tuyển dụng nào được tạo' : 'Không tìm thấy tin tuyển dụng'; ?>
                        </h2>
                        <p class="ent-internship-empty-state__desc">
                            <?= empty($posts) 
                                ? 'Hãy đăng tin tuyển dụng thực tập đầu tiên để kết nối ngay với hàng ngàn sinh viên tài năng từ các trường đối tác trên toàn quốc.' 
                                : 'Không có tin tuyển dụng nào khớp với từ khóa tìm kiếm hoặc bộ lọc hiện tại của bạn.'; ?>
                        </p>
                        
                        <?php if (empty($posts)): ?>
                            <a href="<?= function_exists('app_href') ? app_href('/app/enterprise/internships/create.php') : 'create.php'; ?>" 
                               class="ent-btn-create-post">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                <span>Đăng tin tuyển dụng đầu tiên</span>
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-sm" id="reset-search-btn">
                                Đặt lại bộ lọc
                            </button>
                        <?php endif; ?>
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
    <script id="enterprise-session-boot" type="application/json"><?= json_encode(['csrfToken' => $context['csrfToken'], 'apiBase' => app_href('/api/v1')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?></script>
    <script src="<?= app_href('/assets/js/enterprise.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/internship-management.js'); ?>"></script>
</body>
</html>
