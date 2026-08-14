<?php
/**
 * TalentHub Enterprise - Create & Edit Internship Post Page
 * 
 * Reusable page for both Creating new internship posts and Editing existing posts.
 */

require_once __DIR__ . '/../includes/internships-data.php';

$enterpriseInfo = [
    'company_name' => 'FPT Software',
    'account_type' => 'Gói Premium',
    'logo_initials' => 'FPT',
    'new_matches_count' => 86,
    'total_talents' => 1247
];

$postId = isset($_GET['id']) ? intval($_GET['id']) : null;
$editingPost = $postId ? getMockInternshipById($postId) : null;

$isEdit = !empty($editingPost);
$pageTitle = $isEdit ? ('Chỉnh sửa: ' . $editingPost['title']) : 'Đăng tin tuyển dụng mới';
$currentRoute = '/app/enterprise/internships/create.php';

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

$popularSkills = ['React', 'Node.js', 'TypeScript', 'Python', 'PyTorch', 'Figma', 'UI/UX', 'SQL', 'PHP', 'Laravel', 'Docker', 'REST API', 'Marketing', 'Content Writing', 'Communication'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Đăng tin tuyển dụng thực tập doanh nghiệp trên TalentHub Enterprise.">
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
                <div class="container-fluid" style="max-width: 960px;">
                    
                    <!-- Back Link Bar -->
                    <div class="ent-back-bar">
                        <a href="index.php" class="ent-back-link">
                            &larr; Quay lại Danh sách Tin tuyển dụng
                        </a>
                    </div>

                    <!-- Page Form Header -->
                    <div class="ent-section-box mb-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h2 class="ent-section-box__title" style="font-size: 1.375rem;">
                                    <?= $isEdit ? 'Chỉnh sửa Tin tuyển dụng' : 'Tạo Tin tuyển dụng Thực tập Mới'; ?>
                                </h2>
                                <p class="ent-section-box__subtitle">
                                    <?= $isEdit ? ('Đang chỉnh sửa bài đăng ID #' . $editingPost['id']) : 'Nhập thông tin chi tiết để kết nối với các ứng viên phù hợp trên hệ thống TalentHub.'; ?>
                                </p>
                            </div>
                            <?php if ($isEdit): ?>
                                <span class="ent-status-pill ent-status-pill--<?= $editingPost['status']; ?>">
                                    <span class="dot"></span>
                                    <?= htmlspecialchars($editingPost['status_label']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Main Internship Form Card -->
                    <form id="internship-form" class="ent-internship-form" onsubmit="return false;">
                        <input type="hidden" id="form-post-id" value="<?= $isEdit ? $editingPost['id'] : ''; ?>">
                        
                        <!-- 1. General Info Section -->
                        <section class="ent-section-box mb-4">
                            <h3 class="ent-section-box__title mb-3" style="font-size: 1.0625rem;">1. Thông tin chung về vị trí tuyển dụng</h3>
                            
                            <div class="ent-form-grid">
                                <!-- Tiêu đề tuyển dụng -->
                                <div class="ent-form-group col-12">
                                    <label for="form-title" class="ent-form-label required">Tiêu đề tuyển dụng</label>
                                    <input type="text" 
                                           id="form-title" 
                                           class="ent-form-input" 
                                           placeholder="Ví dụ: Thực tập sinh Frontend Developer (React / TypeScript)"
                                           value="<?= $isEdit ? htmlspecialchars($editingPost['title']) : ''; ?>" 
                                           required>
                                </div>

                                <!-- Lĩnh vực -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-field" class="ent-form-label required">Lĩnh vực chuyên môn</label>
                                    <select id="form-field" class="ent-form-select" required>
                                        <option value="">-- Chọn lĩnh vực --</option>
                                        <?php 
                                        $fields = ['Công nghệ thông tin', 'AI / Machine Learning', 'Thiết kế UI/UX', 'Marketing Digital', 'Khoa học Dữ liệu', 'Kỹ thuật Phần mềm'];
                                        foreach ($fields as $f): 
                                            $selected = ($isEdit && $editingPost['field'] === $f) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($f); ?>" <?= $selected; ?>><?= htmlspecialchars($f); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Số lượng tuyển -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-slots" class="ent-form-label required">Số lượng cần tuyển</label>
                                    <input type="number" 
                                           id="form-slots" 
                                           class="ent-form-input" 
                                           min="1" 
                                           max="50" 
                                           placeholder="Ví dụ: 5"
                                           value="<?= $isEdit ? htmlspecialchars($editingPost['slots']) : '3'; ?>" 
                                           required>
                                </div>

                                <!-- Hình thức làm việc -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-work-type" class="ent-form-label">Hình thức làm việc</label>
                                    <select id="form-work-type" class="ent-form-select">
                                        <?php 
                                        $types = ['Full-time / Hybrid', 'Full-time / On-site', 'Bán thời gian / Remote', 'Linh hoạt'];
                                        foreach ($types as $t):
                                            $selected = ($isEdit && $editingPost['work_type'] === $t) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($t); ?>" <?= $selected; ?>><?= htmlspecialchars($t); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Thời gian thực tập -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-duration" class="ent-form-label">Thời gian thực tập</label>
                                    <select id="form-duration" class="ent-form-select">
                                        <?php 
                                        $durations = ['3 tháng', '6 tháng', '2 tháng', 'Linh hoạt theo trường'];
                                        foreach ($durations as $d):
                                            $selected = ($isEdit && $editingPost['duration'] === $d) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($d); ?>" <?= $selected; ?>><?= htmlspecialchars($d); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Trình độ đối tượng -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-edu-level" class="ent-form-label">Đối tượng / Trình độ yêu cầu</label>
                                    <select id="form-edu-level" class="ent-form-select">
                                        <?php 
                                        $edus = ['Đại học / Cao đẳng', 'Tất cả bậc học', 'Đại học', 'Cao đẳng', 'THPT / THCS'];
                                        foreach ($edus as $e):
                                            $selected = ($isEdit && $editingPost['education_level'] === $e) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($e); ?>" <?= $selected; ?>><?= htmlspecialchars($e); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Hạn ứng tuyển -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-deadline" class="ent-form-label required">Hạn nộp hồ sơ</label>
                                    <input type="date" 
                                           id="form-deadline" 
                                           class="ent-form-input" 
                                           value="<?= $isEdit ? htmlspecialchars($editingPost['deadline']) : '2026-09-15'; ?>" 
                                           required>
                                </div>
                            </div>
                        </section>

                        <!-- 2. Detailed Description & Skills Section -->
                        <section class="ent-section-box mb-4">
                            <h3 class="ent-section-box__title mb-3" style="font-size: 1.0625rem;">2. Mô tả công việc & Kỹ năng yêu cầu</h3>

                            <!-- Mô tả công việc -->
                            <div class="ent-form-group mb-4">
                                <label for="form-description" class="ent-form-label required">Mô tả chi tiết công việc</label>
                                <textarea id="form-description" 
                                          class="ent-form-textarea" 
                                          rows="5" 
                                          placeholder="Nhập mô tả nhiệm vụ, trách nhiệm chính của thực tập sinh trong quá trình làm việc..." 
                                          required><?= $isEdit ? htmlspecialchars($editingPost['description']) : ''; ?></textarea>
                            </div>

                            <!-- Kỹ năng yêu cầu Section -->
                            <div class="ent-form-group mb-5">
                                <div class="ent-field-header mb-3">
                                    <label class="ent-form-label required mb-1" style="font-size: 0.9375rem; font-weight: 600;">Kỹ năng yêu cầu</label>
                                    <p class="ent-form-help-text mb-0" style="font-size: 0.8125rem; color: var(--text-muted);">Chọn các kỹ năng cần thiết cho vị trí tuyển dụng.</p>
                                </div>

                                <div class="ent-skill-picker-card" id="skill-picker-container" data-initial-skills="<?= htmlspecialchars(json_encode($isEdit ? $editingPost['skills'] : [])); ?>">
                                    <!-- 1. Selected Skills Area (Compact, Natural Height) -->
                                    <div class="ent-skill-selected-area" id="selected-skills-area">
                                        <div class="ent-skill-area-header">
                                            <span class="ent-skill-area-title">
                                                Kỹ năng đã chọn (<span id="selected-skills-count">0</span>)
                                            </span>
                                            <button type="button" class="btn-clear-all-skills" id="btn-clear-skills" style="display: none;">
                                                Xóa tất cả
                                            </button>
                                        </div>
                                        <div class="ent-skill-tags-wrapper" id="form-selected-skills">
                                            <!-- Dynamically rendered selected skill tags -->
                                        </div>
                                    </div>

                                    <!-- 2. Technical Skills Section -->
                                    <div class="ent-skill-block">
                                        <div class="ent-skill-block-header">
                                            <h4 class="ent-skill-block-title">Kỹ năng chuyên môn</h4>
                                            <p class="ent-skill-block-subtitle" id="tech-skill-field-label">Gợi ý theo lĩnh vực: ...</p>
                                        </div>
                                        <div class="ent-chip-cloud" id="tech-skills-suggestions">
                                            <!-- Dynamically populated tech skill chips -->
                                        </div>
                                    </div>

                                    <!-- 3. Soft Skills Section -->
                                    <div class="ent-skill-block">
                                        <div class="ent-skill-block-header">
                                            <h4 class="ent-skill-block-title">Kỹ năng mềm</h4>
                                            <p class="ent-skill-block-subtitle">Có thể áp dụng cho mọi lĩnh vực</p>
                                        </div>
                                        <div class="ent-chip-cloud" id="soft-skills-suggestions">
                                            <!-- Static soft skill chips -->
                                        </div>
                                    </div>

                                    <!-- 4. Search & Custom Add Skill Input -->
                                    <div class="ent-skill-search-wrapper">
                                        <div class="ent-skill-search-box">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="11" cy="11" r="8"></circle>
                                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                            </svg>
                                            <input type="text" id="input-custom-skill" class="ent-skill-search-input" placeholder="Tìm hoặc thêm kỹ năng khác..." autocomplete="off">
                                            <button type="button" class="btn-add-custom-skill" id="btn-add-custom-skill">+ Thêm</button>
                                        </div>
                                        <div id="custom-skill-search-results" class="ent-skill-search-results" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quyền lợi -->
                            <div class="ent-form-group">
                                <label for="form-benefits" class="ent-form-label">Quyền lợi & Phụ cấp cho thực tập sinh</label>
                                <textarea id="form-benefits" 
                                          class="ent-form-textarea" 
                                          rows="3" 
                                          placeholder="Nhập mức trợ cấp, hỗ trợ con dấu thực tập, cơ hội lên chính thức, trang thiết bị làm việc..."><?= $isEdit ? htmlspecialchars($editingPost['benefits']) : ''; ?></textarea>
                            </div>
                        </section>

                        <!-- Form Actions Bar -->
                        <div class="ent-form-actions-bar">
                            <a href="index.php" class="btn btn-secondary">Hủy bỏ</a>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-secondary" id="btn-save-draft">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    <span>Lưu bản nháp</span>
                                </button>
                                <button type="button" class="btn btn-primary" id="btn-publish-post">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span><?= $isEdit ? 'Cập nhật tin tuyển dụng' : 'Đăng tuyển ngay'; ?></span>
                                </button>
                            </div>
                        </div>
                    </form>

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
