<?php
/**
 * TalentHub Enterprise - Create & Edit Internship Post Page
 * 
 * Production-grade recruitment post creation and management page.
 * Supports direct PHP POST submissions and interactive AJAX workflows with full database persistence.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 3) . '/src/Bootstrap/EnterpriseAppContext.php';
require_once __DIR__ . '/../includes/internships-data.php';

use TalentHub\Bootstrap\EnterpriseAppContext;
use TalentHub\Http\ApiException;

$context = (new EnterpriseAppContext())->boot();
$user       = $context['user'];
$enterprise = $context['enterprise'];
$internshipService = $context['internships'];
$permissions = $context['permissions'];
$session = $context['session'];

if (!function_exists('getInitials')) {
    function getInitials(string $name): string {
        $words = preg_split('/\s+/', trim($name));
        if (empty($words) || $words[0] === '') return 'DN';
        if (count($words) === 1) return mb_strtoupper(mb_substr($words[0], 0, 2));
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
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
    'new_matches_count' => 86,
    'total_talents'     => 1247,
];

$postId = isset($_GET['id']) ? trim((string) $_GET['id']) : null;
$permissions->require((string) $user['id'], $postId ? 'internship_post.update_own_business' : 'internship_post.create_own_business');

$errorMessage = null;
$successMessage = null;

// Handle Direct PHP Form POST Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_internship'])) {
    try {
        $csrfToken = (string) ($_POST['csrfToken'] ?? '');
        $session->assertCsrf($csrfToken);

        $title = trim((string) ($_POST['title'] ?? ''));
        $field = trim((string) ($_POST['field'] ?? ''));
        $slots = (int) ($_POST['slots'] ?? 1);
        $location = trim((string) ($_POST['location'] ?? ''));
        $workType = trim((string) ($_POST['workType'] ?? 'Full-time / Hybrid'));
        $duration = trim((string) ($_POST['duration'] ?? '3 tháng'));
        $educationLevel = trim((string) ($_POST['educationLevel'] ?? 'Đại học / Cao đẳng'));
        $deadlineInput = trim((string) ($_POST['deadline'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $benefits = trim((string) ($_POST['benefits'] ?? ''));
        $action = trim((string) ($_POST['action'] ?? 'publish')); // 'draft' or 'publish'

        // Parse skills from raw POST
        $rawSkills = $_POST['skills'] ?? [];
        if (is_string($rawSkills)) {
            $skillsDecoded = json_decode($rawSkills, true);
            $skillsArray = is_array($skillsDecoded) ? $skillsDecoded : array_filter(array_map('trim', explode(',', $rawSkills)));
        } elseif (is_array($rawSkills)) {
            $skillsArray = array_values(array_filter(array_map('strval', $rawSkills)));
        } else {
            $skillsArray = [];
        }

        // Validate required fields
        if ($title === '') {
            throw new \InvalidArgumentException('Vui lòng nhập tiêu đề vị trí tuyển dụng.');
        }
        if ($field === '') {
            throw new \InvalidArgumentException('Vui lòng chọn lĩnh vực chuyên môn.');
        }
        if ($slots < 1) {
            throw new \InvalidArgumentException('Số lượng cần tuyển phải từ 1 trở lên.');
        }
        if ($location === '') {
            throw new \InvalidArgumentException('Vui lòng nhập địa điểm làm việc.');
        }
        if ($deadlineInput === '') {
            throw new \InvalidArgumentException('Vui lòng chọn hạn chót nhận hồ sơ.');
        }
        if ($description === '') {
            throw new \InvalidArgumentException('Vui lòng nhập mô tả chi tiết công việc.');
        }
        if (empty($skillsArray)) {
            throw new \InvalidArgumentException('Vui lòng chọn ít nhất 1 kỹ năng yêu cầu.');
        }

        $deadlineFormatted = $deadlineInput . ' 23:59:59.000000';

        $payload = [
            'title'          => $title,
            'field'          => $field,
            'slots'          => $slots,
            'location'       => $location,
            'workType'       => $workType,
            'duration'       => $duration,
            'educationLevel' => $educationLevel,
            'deadline'       => $deadlineFormatted,
            'description'    => $description,
            'benefits'       => $benefits,
            'skills'         => $skillsArray,
            'requirements'   => [],
        ];

        if ($postId) {
            $savedPost = $internshipService->updatePost((string) $user['id'], $postId, $payload);
            if ($action === 'publish' && ($savedPost['status'] ?? '') === 'draft') {
                $savedPost = $internshipService->publish((string) $user['id'], $postId, 'draft');
            }
            $_SESSION['flash_message'] = 'Đã cập nhật tin tuyển dụng "' . htmlspecialchars($title) . '" thành công!';
        } else {
            $savedPost = $internshipService->createPost((string) $user['id'], $payload);
            if ($action === 'publish' && isset($savedPost['id'])) {
                $savedPost = $internshipService->publish((string) $user['id'], (string) $savedPost['id'], 'draft');
            }
            $_SESSION['flash_message'] = ($action === 'publish') 
                ? 'Đã phát hành tin tuyển dụng "' . htmlspecialchars($title) . '" thành công!' 
                : 'Đã lưu bản nháp tin tuyển dụng thành công!';
        }

        $targetUrl = function_exists('app_href') ? app_href('/app/enterprise/internships/index.php') : 'index.php';
        header('Location: ' . $targetUrl);
        exit;
    } catch (ApiException $e) {
        $errorMessage = $e->getMessage();
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

// Load existing post if editing
$editingPost = $postId ? $internshipService->post((string) $user['id'], $postId) : null;
if ($editingPost) {
    $decodedSkills = json_decode((string) ($editingPost['skillsJson'] ?? '[]'), true);
    $editingPost += [
        'status_label' => ['draft' => 'Bản nháp', 'active' => 'Đang tuyển', 'closed' => 'Đã đóng', 'cancelled' => 'Đã hủy'][$editingPost['status']] ?? $editingPost['status'],
        'work_type' => $editingPost['workType'] ?? 'Full-time / Hybrid',
        'education_level' => $editingPost['educationLevel'] ?? 'Đại học / Cao đẳng',
        'skills' => array_map(static fn ($skill): array => ['name' => (string) $skill, 'category' => 'Yêu cầu', 'type' => 'required'], is_array($decodedSkills) ? $decodedSkills : []),
    ];
}

$isEdit = !empty($editingPost);
$pageTitle = $isEdit ? ('Chỉnh sửa: ' . $editingPost['title']) : 'Đăng tin tuyển dụng mới';
$currentRoute = '/app/enterprise/internships/create.php';

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
<body class="enterprise-dashboard" data-post-status="<?= htmlspecialchars((string) ($editingPost['status'] ?? '')); ?>">

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
                        <a href="<?= function_exists('app_href') ? app_href('/app/enterprise/internships/index.php') : 'index.php'; ?>" class="ent-back-link">
                            &larr; Quay lại Danh sách Tin tuyển dụng
                        </a>
                    </div>

                    <?php if ($errorMessage): ?>
                        <div class="ent-alert ent-alert--danger mb-4" style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 1rem 1.25rem; border-radius: 8px; font-weight: 500;">
                            <strong>Đã xảy ra lỗi:</strong> <?= htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Page Form Header -->
                    <div class="ent-section-box mb-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h2 class="ent-section-box__title">
                                    <?= $isEdit ? 'Chỉnh sửa Tin tuyển dụng' : 'Tạo Tin tuyển dụng Thực tập Mới'; ?>
                                </h2>
                                <p class="ent-section-box__subtitle">
                                    <?= $isEdit ? ('Đang chỉnh sửa bài đăng ID #' . htmlspecialchars((string) $editingPost['id'])) : 'Nhập thông tin chi tiết để kết nối với các ứng viên phù hợp trên hệ thống TalentHub.'; ?>
                                </p>
                            </div>
                            <?php if ($isEdit): ?>
                                <span class="ent-status-pill ent-status-pill--<?= htmlspecialchars((string) $editingPost['status']); ?>">
                                    <span class="dot"></span>
                                    <?= htmlspecialchars((string) $editingPost['status_label']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Main Internship Form -->
                    <form id="internship-form" class="ent-internship-form" method="POST" action="">
                        <input type="hidden" name="submit_internship" value="1">
                        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($context['csrfToken']); ?>">
                        <input type="hidden" id="form-post-id" name="postId" value="<?= $isEdit ? htmlspecialchars((string) $editingPost['id']) : ''; ?>">
                        <input type="hidden" id="form-action" name="action" value="publish">
                        <input type="hidden" id="form-skills-json" name="skills" value="<?= htmlspecialchars(json_encode($isEdit ? array_column($editingPost['skills'], 'name') : [])); ?>">
                        
                        <!-- 1. General Info Section -->
                        <section class="ent-section-box mb-4">
                            <h3 class="ent-section-box__title mb-3" style="font-size: 1.0625rem;">1. Thông tin chung về vị trí tuyển dụng</h3>
                            
                            <div class="ent-form-grid">
                                <!-- Tiêu đề tuyển dụng -->
                                <div class="ent-form-group col-12">
                                    <label for="form-title" class="ent-form-label required">Tiêu đề vị trí tuyển dụng</label>
                                    <input type="text" 
                                           id="form-title" 
                                           name="title"
                                           class="ent-form-input" 
                                           placeholder="Ví dụ: Thực tập sinh Frontend Developer (React / TypeScript)"
                                           value="<?= $isEdit ? htmlspecialchars((string) $editingPost['title']) : ''; ?>" 
                                           required>
                                </div>

                                <!-- Lĩnh vực -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-field" class="ent-form-label required">Lĩnh vực / Chuyên môn</label>
                                    <select id="form-field" name="field" class="ent-form-select" required>
                                        <option value="">-- Chọn lĩnh vực --</option>
                                        <?php 
                                        $fields = ['Công nghệ thông tin', 'AI / Machine Learning', 'Thiết kế UI/UX', 'Marketing Digital', 'Khoa học Dữ liệu', 'Kỹ thuật Phần mềm'];
                                        foreach ($fields as $f): 
                                            $selected = ($isEdit && ($editingPost['field'] ?? '') === $f) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($f); ?>" <?= $selected; ?>><?= htmlspecialchars($f); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Số lượng tuyển -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-slots" class="ent-form-label required">Số lượng cần tuyển (Chỉ tiêu)</label>
                                    <input type="number" 
                                           id="form-slots" 
                                           name="slots"
                                           class="ent-form-input" 
                                           min="1" 
                                           max="100" 
                                           placeholder="Ví dụ: 5"
                                           value="<?= $isEdit ? htmlspecialchars((string) $editingPost['slots']) : '3'; ?>" 
                                           required>
                                </div>

                                <!-- Địa điểm làm việc -->
                                <div class="ent-form-group col-12">
                                    <label for="form-location" class="ent-form-label required">Địa điểm làm việc</label>
                                    <input type="text"
                                           id="form-location"
                                           name="location"
                                           class="ent-form-input"
                                           maxlength="255"
                                           placeholder="Ví dụ: Tòa nhà FPT, Khu CNC Hòa Lạc, Hà Nội hoặc Làm việc từ xa (Remote)"
                                           value="<?= $isEdit ? htmlspecialchars((string) $editingPost['location']) : 'Hà Nội'; ?>"
                                           required>
                                </div>

                                <!-- Hình thức làm việc -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-work-type" class="ent-form-label">Hình thức làm việc</label>
                                    <select id="form-work-type" name="workType" class="ent-form-select">
                                        <?php 
                                        $types = ['Full-time / Hybrid', 'Full-time / On-site', 'Bán thời gian / Remote', 'Linh hoạt'];
                                        foreach ($types as $t):
                                            $selected = ($isEdit && ($editingPost['work_type'] ?? '') === $t) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($t); ?>" <?= $selected; ?>><?= htmlspecialchars($t); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Thời gian thực tập -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-duration" class="ent-form-label">Thời gian thực tập</label>
                                    <select id="form-duration" name="duration" class="ent-form-select">
                                        <?php 
                                        $durations = ['3 tháng', '6 tháng', '2 tháng', 'Linh hoạt theo trường'];
                                        foreach ($durations as $d):
                                            $selected = ($isEdit && ($editingPost['duration'] ?? '') === $d) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($d); ?>" <?= $selected; ?>><?= htmlspecialchars($d); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Trình độ đối tượng -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-edu-level" class="ent-form-label">Đối tượng / Trình độ yêu cầu</label>
                                    <select id="form-edu-level" name="educationLevel" class="ent-form-select">
                                        <?php 
                                        $edus = ['Đại học / Cao đẳng', 'Tất cả bậc học', 'Đại học', 'Cao đẳng', 'THPT / THCS'];
                                        foreach ($edus as $e):
                                            $selected = ($isEdit && ($editingPost['education_level'] ?? '') === $e) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($e); ?>" <?= $selected; ?>><?= htmlspecialchars($e); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Hạn ứng tuyển -->
                                <div class="ent-form-group col-md-6">
                                    <label for="form-deadline" class="ent-form-label required">Hạn chót nhận hồ sơ</label>
                                    <input type="date" 
                                           id="form-deadline" 
                                           name="deadline"
                                           class="ent-form-input" 
                                           value="<?= $isEdit ? htmlspecialchars(date('Y-m-d', strtotime((string) $editingPost['deadline']))) : date('Y-m-d', strtotime('+30 days')); ?>" 
                                           required>
                                </div>
                            </div>
                        </section>

                        <!-- 2. Detailed Description & Skills Section -->
                        <section class="ent-section-box mb-4">
                            <h3 class="ent-section-box__title mb-3">2. Mô tả công việc & Kỹ năng yêu cầu</h3>

                            <!-- Mô tả công việc -->
                            <div class="ent-form-group mb-4">
                                <label for="form-description" class="ent-form-label required">Mô tả chi tiết công việc</label>
                                <textarea id="form-description" 
                                          name="description"
                                          class="ent-form-textarea" 
                                          rows="5" 
                                          placeholder="Nhập mô tả nhiệm vụ, trách nhiệm chính của thực tập sinh trong quá trình làm việc..." 
                                          required><?= $isEdit ? htmlspecialchars((string) $editingPost['description']) : ''; ?></textarea>
                            </div>

                            <!-- Kỹ năng yêu cầu Section -->
                            <div class="ent-form-group mb-5">
                                <div class="ent-field-header mb-3">
                                    <label class="ent-form-label required mb-1">Yêu cầu kỹ năng (Tags)</label>
                                    <p class="ent-form-help-text mb-0">Chọn hoặc nhập các kỹ năng cần thiết cho vị trí tuyển dụng.</p>
                                </div>

                                <div class="ent-skill-picker-card" id="skill-picker-container" data-initial-skills="<?= htmlspecialchars(json_encode($isEdit ? $editingPost['skills'] : [])); ?>">
                                    <!-- 1. Selected Skills Area -->
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

                            <!-- Quyền lợi & Phụ cấp -->
                            <div class="ent-form-group">
                                <label for="form-benefits" class="ent-form-label">Quyền lợi & Mức phụ cấp / Lương</label>
                                <textarea id="form-benefits" 
                                          name="benefits"
                                          class="ent-form-textarea" 
                                          rows="3" 
                                          placeholder="Ví dụ: Hỗ trợ phụ cấp 3.000.000 - 5.000.000 VNĐ/tháng, hỗ trợ dấu thực tập tốt nghiệp, cơ hội trở thành nhân viên chính thức..."><?= $isEdit ? htmlspecialchars((string) ($editingPost['benefits'] ?? '')) : ''; ?></textarea>
                            </div>
                        </section>

                        <!-- Form Actions Bar -->
                        <div class="ent-form-actions-bar">
                            <a href="<?= function_exists('app_href') ? app_href('/app/enterprise/internships/index.php') : 'index.php'; ?>" class="btn btn-secondary">Hủy bỏ</a>
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
    <script id="enterprise-session-boot" type="application/json"><?= json_encode(['csrfToken' => $context['csrfToken'], 'apiBase' => '/api/v1'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?></script>
    <script src="../../../assets/js/enterprise.js"></script>
    <script src="../../../assets/js/internship-management.js"></script>
</body>
</html>
