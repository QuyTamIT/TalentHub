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
$specialtiesService = $context['specialties'];
$permissions = $context['permissions'];
$session = $context['session'];

// Load specialties from DB catalog (global + enterprise's own)
$specialtiesList = $specialtiesService->listForUser($user['id']);
$specialtyOptions = array_map(fn($s) => [
    'id'   => $s['id'],
    'name' => $s['name'],
    'slug' => $s['slug'],
], $specialtiesList);

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

if (!function_exists('getSchoolCode')) {
    function getSchoolCode(string $name): string {
        $clean = trim($name);
        if (stripos($clean, 'BTEC') !== false) return 'BTEC';
        if (stripos($clean, 'FPT') !== false) return 'FPTU';
        if (stripos($clean, 'CTU') !== false || stripos($clean, 'Đại học Cần Thơ') !== false) return 'CTU';
        if (stripos($clean, 'UEH') !== false || stripos($clean, 'Kinh tế') !== false) return 'UEH';
        if (stripos($clean, 'Tây Đô') !== false) return 'DNTD';
        if (stripos($clean, 'Nam Cần Thơ') !== false) return 'DNC';
        if (stripos($clean, 'Công nghệ Cần Thơ') !== false) return 'CTUT';

        $words = preg_split('/\s+/u', $clean);
        if (empty($words) || $words[0] === '') return 'TH';
        if (count($words) === 1) return mb_strtoupper(mb_substr($words[0], 0, 4));
        $acronym = '';
        foreach ($words as $w) {
            if (mb_strlen($w) > 0 && !in_array(mb_strtolower($w), ['và', 'các', 'của', 'ở', 'tại', 'trường'], true)) {
                $acronym .= mb_substr($w, 0, 1);
            }
        }
        return mb_strtoupper(mb_substr($acronym, 0, 5));
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

$internshipService = $context['internships'];
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
            throw new \InvalidArgumentException('Vui lòng nhập tiêu đề vị trí thực tập.');
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
        $audience = trim((string) ($_POST['audience'] ?? 'public'));
        $targetSchoolIds = isset($_POST['targetSchoolIds']) && is_array($_POST['targetSchoolIds'])
            ? array_values(array_unique(array_filter(array_map('strval', $_POST['targetSchoolIds']))))
            : [];

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
            'audience'       => in_array($audience, ['public', 'partner_schools'], true) ? $audience : 'public',
            'targetSchoolIds'=> $targetSchoolIds,
        ];

        if ($postId) {
            $savedPost = $internshipService->updatePost((string) $user['id'], $postId, $payload);
            if ($action === 'publish' && ($savedPost['status'] ?? '') === 'draft') {
                $savedPost = $internshipService->publish((string) $user['id'], $postId, 'draft');
            }
            $_SESSION['flash_message'] = 'Đã cập nhật tin tuyển thực tập "' . htmlspecialchars($title) . '" thành công!';
        } else {
            $savedPost = $internshipService->createPost((string) $user['id'], $payload);
            if ($action === 'publish' && isset($savedPost['id'])) {
                $savedPost = $internshipService->publish((string) $user['id'], (string) $savedPost['id'], 'draft');
            }
            $_SESSION['flash_message'] = ($action === 'publish') 
                ? 'Đã phát hành tin tuyển thực tập "' . htmlspecialchars($title) . '" thành công!' 
                : 'Đã lưu bản nháp tin tuyển thực tập thành công!';
        }

        $targetUrl = function_exists('app_href') ? app_href('/app/enterprise/internships/index.php') : 'index.php';
        header('Location: ' . $targetUrl);
        exit;
    } catch (ApiException $e) {
        $errorMessage = $e->getMessage();
        error_log('create.php ApiException: ' . $e->getMessage() . ' (' . $e->errorCode . ')');
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage() ?: 'Không thể xử lý yêu cầu. Vui lòng thử lại sau.';
        error_log('create.php Throwable: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
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

$postAudience = $editingPost ? ($editingPost['audience'] ?? 'public') : 'public';
$selectedTargetSchoolIds = $editingPost ? ($editingPost['targetSchoolIds'] ?? []) : [];

$rawApprovedSchools = [];
try {
    $rawApprovedSchools = $internshipService->listApprovedPartnerSchools();
} catch (\Throwable) {
    $rawApprovedSchools = [];
}

$approvedPartners = [];
foreach ($rawApprovedSchools as $schoolRow) {
    $approvedPartners[] = [
        'id'      => (string) $schoolRow['id'],
        'name'    => (string) $schoolRow['name'],
        'code'    => getSchoolCode((string) $schoolRow['name']),
        'level'   => !empty($schoolRow['level']) ? (string) $schoolRow['level'] : 'Đại học / Cao đẳng',
        'logoUrl' => $schoolRow['logoUrl'] ?? null,
    ];
}

$isEdit = !empty($editingPost);
$pageTitle = $isEdit ? ('Chỉnh sửa: ' . $editingPost['title']) : 'Đăng tin tuyển thực tập mới';
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
    <meta name="color-scheme" content="light">
    <meta name="description" content="Đăng tin tuyển thực tập doanh nghiệp trên TalentHub Enterprise.">
    <title><?= htmlspecialchars($pageTitle); ?> | TalentHub Enterprise</title>
    <meta name="description" content="Đăng tin tuyển thực tập doanh nghiệp trên FTalentHub Enterprise.">
    <title><?= htmlspecialchars($pageTitle); ?> | FTalentHub Enterprise</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/global.css">
    <link rel="stylesheet" href="../../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../../assets/css/polish.css">
    <link rel="stylesheet" href="../../../assets/css/enterprise.css">
    <link rel="stylesheet" href="../../../assets/css/typeui-selects.css">
    <link rel="stylesheet" href="../../../assets/css/enterprise-internship-create.css">
</head>
<body class="enterprise-dashboard" data-post-status="<?= htmlspecialchars((string) ($editingPost['status'] ?? '')); ?>">
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
                <div class="container-fluid ent-create-container">
                    
                    <!-- Back Link Bar -->
                    <div class="ent-back-bar">
                        <a href="<?= function_exists('app_href') ? app_href('/app/enterprise/internships/index.php') : 'index.php'; ?>" class="ent-back-link">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            <span>Quay lại danh sách Tin tuyển thực tập</span>
                        </a>
                    </div>

                    <?php if ($errorMessage): ?>
                        <div class="ent-alert ent-alert--danger mb-4" style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 1rem 1.25rem; border-radius: 8px; font-weight: 500;">
                            <strong>Đã xảy ra lỗi:</strong> <?= htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Page Form Header Banner -->
                    <div class="ent-create-header-card mb-4">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="ent-create-header__tag">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                    </svg>
                                    <span>Tuyển dụng thực tập</span>
                                </div>
                                <h2 class="ent-create-header__title">
                                    <?= $isEdit ? 'Chỉnh sửa Tin tuyển thực tập' : 'Tạo Tin tuyển thực tập Mới'; ?>
                                </h2>
                                <p class="ent-create-header__desc">
                                    <?= $isEdit ? ('Đang chỉnh sửa bài đăng ID #' . htmlspecialchars((string) $editingPost['id'])) : 'Nhập thông tin chi tiết để kết nối với các ứng viên phù hợp trên hệ thống FTalentHub.'; ?>
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
                        <section class="ent-create-section mb-4">
                            <div class="ent-create-section__header">
                                <h3 class="ent-create-section__title">1. Thông tin chung về vị trí thực tập</h3>
                                <p class="ent-create-section__subtitle">Thông tin cơ bản về vị trí, số lượng và thời gian thực tập của doanh nghiệp</p>
                            </div>
                            
                            <div class="ent-create-form-grid">
                                <!-- Tiêu đề thực tập -->
                                <div class="ent-create-form-group ent-col-12">
                                    <label for="form-title" class="ent-create-label required">Tiêu đề vị trí thực tập</label>
                                    <input type="text" 
                                           id="form-title" 
                                           name="title"
                                           class="ent-create-input" 
                                           placeholder="Ví dụ: Thực tập sinh Frontend Developer (React / TypeScript)"
                                           value="<?= $isEdit ? htmlspecialchars((string) $editingPost['title']) : ''; ?>" 
                                           required>
                                </div>

                                <!-- Lĩnh vực -->
                                <div class="ent-create-form-group ent-col-8">
                                    <label for="form-field" class="ent-create-label required">
                                        Lĩnh vực / Chuyên môn
                                    </label>
                                    <div class="ent-field-with-add">
                                        <select id="form-field" name="field" class="ent-create-select typeui-select" required>
                                            <option value="">-- Chọn lĩnh vực --</option>
                                            <?php foreach ($specialtyOptions as $s): ?>
                                                <?php $selected = ($isEdit && ($editingPost['field'] ?? '') === $s['name']) ? 'selected' : ''; ?>
                                                <option value="<?= htmlspecialchars($s['name']); ?>" <?= $selected; ?>>
                                                    <?= htmlspecialchars($s['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="btn-add-specialty" class="ent-btn-add-inline" title="+ Thêm lĩnh vực mới" aria-label="Thêm lĩnh vực mới">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Số lượng tuyển -->
                                <div class="ent-create-form-group ent-col-4">
                                    <label for="form-slots" class="ent-create-label required">Số lượng cần tuyển (Chỉ tiêu)</label>
                                    <input type="number" 
                                           id="form-slots" 
                                           name="slots"
                                           class="ent-create-input" 
                                           min="1" 
                                           max="100" 
                                           placeholder="Ví dụ: 5"
                                           value="<?= $isEdit ? htmlspecialchars((string) $editingPost['slots']) : '3'; ?>" 
                                           required>
                                </div>

                                <!-- Địa điểm làm việc -->
                                <div class="ent-create-form-group ent-col-12">
                                    <label for="form-location" class="ent-create-label required">Địa điểm làm việc</label>
                                    <select id="form-location" name="location" class="ent-create-select typeui-select" required>
                                        <option value="">-- Chọn địa điểm --</option>
                                        <?php 
                                        $provinces = [
                                            'Hà Nội', 'TP Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ',
                                            'An Giang', 'Bà Rịa - Vũng Tàu', 'Bắc Giang', 'Bắc Kạn', 'Bạc Liêu',
                                            'Bắc Ninh', 'Bến Tre', 'Bình Định', 'Bình Dương', 'Bình Phước',
                                            'Bình Thuận', 'Cà Mau', 'Cao Bằng', 'Đắk Lắk', 'Đắk Nông',
                                            'Điện Biên', 'Đồng Nai', 'Đồng Tháp', 'Gia Lai', 'Hà Giang',
                                            'Hà Nam', 'Hà Tĩnh', 'Hải Dương', 'Hậu Giang', 'Hòa Bình',
                                            'Hưng Yên', 'Khánh Hòa', 'Kiên Giang', 'Kon Tum', 'Lai Châu',
                                            'Lâm Đồng', 'Lạng Sơn', 'Lào Cai', 'Long An', 'Nam Định',
                                            'Nghệ An', 'Ninh Bình', 'Ninh Thuận', 'Phú Thọ', 'Quảng Bình',
                                            'Quảng Nam', 'Quảng Ngãi', 'Quảng Ninh', 'Quảng Trị', 'Sóc Trăng',
                                            'Sơn La', 'Tây Ninh', 'Thái Bình', 'Thái Nguyên', 'Thanh Hóa',
                                            'Thừa Thiên Huế', 'Tiền Giang', 'Trà Vinh', 'Tuyên Quang', 'Vĩnh Long',
                                            'Vĩnh Phúc', 'Yên Bái', 'Phú Yên', 'Làm việc từ xa (Remote)'
                                        ];
                                        foreach ($provinces as $prov) {
                                            $selected = ($isEdit && ($editingPost['location'] ?? '') === $prov) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($prov) . '" ' . $selected . '>' . htmlspecialchars($prov) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>

                                <!-- Hình thức làm việc -->
                                <div class="ent-create-form-group ent-col-6">
                                    <label for="form-work-type" class="ent-create-label">Hình thức làm việc</label>
                                    <select id="form-work-type" name="workType" class="ent-create-select typeui-select">
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
                                <div class="ent-create-form-group ent-col-6">
                                    <label for="form-duration" class="ent-create-label">Thời gian thực tập</label>
                                    <select id="form-duration" name="duration" class="ent-create-select typeui-select">
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
                                <div class="ent-create-form-group ent-col-6">
                                    <label for="form-edu-level" class="ent-create-label">Đối tượng / Trình độ yêu cầu</label>
                                    <select id="form-edu-level" name="educationLevel" class="ent-create-select typeui-select">
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
                                <div class="ent-create-form-group ent-col-6">
                                    <label for="form-deadline" class="ent-create-label required">Hạn chót nhận hồ sơ</label>
                                    <input type="date" 
                                           id="form-deadline" 
                                           name="deadline"
                                           class="ent-create-input" 
                                           value="<?= $isEdit ? htmlspecialchars(date('Y-m-d', strtotime((string) $editingPost['deadline']))) : date('Y-m-d', strtotime('+30 days')); ?>" 
                                           required>
                                </div>
                            </div>
                        </section>

                        <!-- 2. Detailed Description & Skills Section -->
                        <section class="ent-create-section mb-4">
                            <div class="ent-create-section__header">
                                <h3 class="ent-create-section__title">2. Mô tả công việc & Kỹ năng yêu cầu</h3>
                                <p class="ent-create-section__subtitle">Mô tả chi tiết nhiệm vụ và các tiêu chuẩn kỹ năng cho ứng viên</p>
                            </div>

                            <!-- Mô tả công việc -->
                            <div class="ent-create-form-group mb-4">
                                <label for="form-description" class="ent-create-label required">Mô tả chi tiết công việc</label>
                                <textarea id="form-description" 
                                          name="description"
                                          class="ent-create-textarea ent-create-textarea--desc" 
                                          rows="5" 
                                          placeholder="Nhập mô tả nhiệm vụ, trách nhiệm chính của thực tập sinh trong quá trình làm việc..." 
                                          required><?= $isEdit ? htmlspecialchars((string) $editingPost['description']) : ''; ?></textarea>
                            </div>

                            <!-- Kỹ năng yêu cầu Section -->
                            <div class="ent-create-form-group mb-4">
                                <div class="d-flex flex-column gap-1 mb-2">
                                    <label class="ent-create-label required">Yêu cầu kỹ năng (Tags)</label>
                                    <p class="ent-create-section__subtitle">Chọn hoặc nhập các kỹ năng cần thiết cho vị trí thực tập.</p>
                                </div>

                                <div class="ent-skill-unified-wrapper" id="skill-picker-container" data-initial-skills="<?= htmlspecialchars(json_encode($isEdit ? $editingPost['skills'] : [])); ?>">
                                    <!-- 1. Selected Skills Area -->
                                    <div class="ent-selected-skills-box" id="selected-skills-area">
                                        <div class="ent-selected-skills-header">
                                            <span class="ent-selected-skills-title">
                                                Kỹ năng đã chọn <span class="ent-selected-badge" id="selected-skills-count">0</span>
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
                                    <div class="ent-skill-group-section">
                                        <div class="ent-skill-group-header">
                                            <h4 class="ent-skill-group-title">Kỹ năng chuyên môn</h4>
                                            <p class="ent-skill-group-desc" id="tech-skill-field-label">Gợi ý theo lĩnh vực: ...</p>
                                        </div>
                                        <div class="ent-chip-cloud" id="tech-skills-suggestions">
                                            <!-- Dynamically populated tech skill chips -->
                                        </div>
                                    </div>

                                    <!-- 3. Soft Skills Section -->
                                    <div class="ent-skill-group-section">
                                        <div class="ent-skill-group-header">
                                            <h4 class="ent-skill-group-title">Kỹ năng mềm</h4>
                                            <p class="ent-skill-group-desc">Có thể áp dụng cho mọi lĩnh vực</p>
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
                            <div class="ent-create-form-group">
                                <label for="form-benefits" class="ent-create-label">Quyền lợi & Mức phụ cấp / Lương</label>
                                <textarea id="form-benefits" 
                                          name="benefits"
                                          class="ent-create-textarea ent-create-textarea--benefits" 
                                          rows="3" 
                                          placeholder="Ví dụ: Hỗ trợ phụ cấp 3.000.000 - 5.000.000 VNĐ/tháng, hỗ trợ dấu thực tập tốt nghiệp, cơ hội trở thành nhân viên chính thức..."><?= $isEdit ? htmlspecialchars((string) ($editingPost['benefits'] ?? '')) : ''; ?></textarea>
                            </div>
                        </section>

                        <!-- 3. Audience & Partner Schools Targeting Section -->
                        <section class="ent-create-section mb-4">
                            <div class="ent-create-section__header">
                                <h3 class="ent-create-section__title">3. Phạm vi tuyển thực tập & Đối tượng hướng đích</h3>
                                <p class="ent-create-section__subtitle">Chọn đối tượng sinh viên có thể xem và nộp hồ sơ ứng tuyển vị trí này.</p>
                            </div>

                            <div class="ent-create-form-group mb-3">
                                <div class="ent-radio-cards-grid">
                                    <label class="ent-radio-card <?= $postAudience === 'public' ? 'border-primary' : ''; ?>" for="audience-public">
                                        <input type="radio" name="audience" value="public" id="audience-public" <?= $postAudience === 'public' ? 'checked' : ''; ?>>
                                        <div class="ent-radio-card-content">
                                            <strong class="ent-radio-card-title">Công khai toàn hệ thống (Public)</strong>
                                            <p class="ent-radio-card-desc">
                                                Tất cả học sinh, sinh viên trên FTalentHub đều có thể tìm thấy và nộp hồ sơ.
                                            </p>
                                        </div>
                                    </label>

                                    <label class="ent-radio-card <?= $postAudience === 'partner_schools' ? 'border-primary' : ''; ?>" for="audience-partner-schools">
                                        <input type="radio" name="audience" value="partner_schools" id="audience-partner-schools" <?= $postAudience === 'partner_schools' ? 'checked' : ''; ?>>
                                        <div class="ent-radio-card-content">
                                            <strong class="ent-radio-card-title">Chỉ dành cho Trường đối tác (Partner Schools)</strong>
                                            <p class="ent-radio-card-desc">
                                                Chỉ sinh viên thuộc các trường đại học/cao đẳng đã ký kết hợp tác được chọn mới thấy tin.
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Target Schools Picker Area -->
                            <div id="target-schools-container" class="ent-target-schools-box mt-3" style="<?= $postAudience === 'partner_schools' ? 'display:block;' : 'display:none;'; ?>">
                                <!-- Header & Summary -->
                                <div class="ent-schools-header">
                                    <label class="ent-schools-header-title">Danh sách Trường đối tác áp dụng</label>
                                    <div class="ent-schools-count-badge">
                                        Đã chọn: <span id="target-schools-count"><?= count($selectedTargetSchoolIds); ?></span> trường
                                    </div>
                                </div>

                                <?php if (empty($approvedPartners)): ?>
                                    <div class="alert alert-warning mb-0 py-2 small" style="border-radius: 8px;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-1 inline-block">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="12" y1="8" x2="12" y2="12"></line>
                                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                        </svg>
                                        Hiện chưa có trường đối tác nào được phê duyệt hoạt động trên hệ thống.
                                    </div>
                                <?php else: ?>
                                    <div class="ent-schools-list">
                                        <!-- Select All Item -->
                                        <label class="ent-school-item ent-school-item--all">
                                            <input type="checkbox" id="selectAllSchools" class="form-check-input mt-0" style="width: 1.15rem; height: 1.15rem; accent-color: #E04058; cursor: pointer;">
                                            <strong style="font-size: 0.875rem; color: #E04058;">Tất cả các trường đối tác</strong>
                                        </label>

                                        <!-- School Items -->
                                        <?php foreach ($approvedPartners as $school):
                                            $checked = in_array((string) $school['id'], array_map('strval', $selectedTargetSchoolIds), true) ? 'checked' : '';
                                        ?>
                                            <label class="ent-school-item">
                                                <input type="checkbox" name="targetSchoolIds[]" value="<?= htmlspecialchars((string) $school['id']); ?>" <?= $checked; ?> class="target-school-checkbox form-check-input mt-0" style="width: 1.15rem; height: 1.15rem; accent-color: #E04058; cursor: pointer;">
                                                <div style="display: flex; align-items: center; justify-content: space-between; flex-grow: 1;">
                                                    <span style="font-weight: 600; font-size: 0.875rem; color: #1E293B;"><?= htmlspecialchars((string) $school['name']); ?></span>
                                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                        <?php if (!empty($school['code'])): ?>
                                                            <span style="background: #FFF0EB; color: #E04058; border: 1px solid rgba(224, 64, 88, 0.2); padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px;"><?= htmlspecialchars((string) $school['code']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($school['level'])): ?>
                                                            <span style="color: #64748B; font-size: 0.75rem; font-weight: 500;"><?= htmlspecialchars((string) $school['level']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    // 1. Target Schools Toggle Logic
                                    const audienceRadios = document.querySelectorAll('input[name="audience"]');
                                    const targetContainer = document.getElementById('target-schools-container');
                                    const radioCards = document.querySelectorAll('.ent-radio-card');
                                    
                                    audienceRadios.forEach(radio => {
                                        radio.addEventListener('change', function() {
                                            // Toggle visibility
                                            if (this.value === 'partner_schools') {
                                                targetContainer.style.display = 'block';
                                            } else {
                                                targetContainer.style.display = 'none';
                                            }
                                            
                                            // Update styling of the radio cards
                                            radioCards.forEach(card => {
                                                card.classList.remove('border-primary');
                                                if (card.querySelector('input').checked) {
                                                    card.classList.add('border-primary');
                                                }
                                            });
                                        });
                                    });

                                    // 2. Select All Schools Logic
                                    const selectAllBtn = document.getElementById('selectAllSchools');
                                    const checkboxes = document.querySelectorAll('.target-school-checkbox');
                                    const countDisplay = document.getElementById('target-schools-count');
                                    
                                    if (selectAllBtn && checkboxes.length > 0) {
                                        const updateState = () => {
                                            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
                                            if (countDisplay) countDisplay.textContent = checkedCount;
                                            selectAllBtn.checked = checkedCount === checkboxes.length;
                                        };
                                        
                                        selectAllBtn.addEventListener('change', function(e) {
                                            const isChecked = e.target.checked;
                                            checkboxes.forEach(cb => cb.checked = isChecked);
                                            updateState();
                                        });
                                        
                                        checkboxes.forEach(cb => cb.addEventListener('change', updateState));
                                        updateState();
                                    }
                                });
                            </script>
                        </section>

                        <!-- Form Actions Bar -->
                        <div class="ent-create-actions-bar">
                            <a href="<?= function_exists('app_href') ? app_href('/app/enterprise/internships/index.php') : 'index.php'; ?>" class="ent-action-btn ent-action-btn--tertiary">
                                Hủy bỏ
                            </a>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="ent-action-btn ent-action-btn--secondary" id="btn-save-draft">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    <span>Lưu bản nháp</span>
                                </button>
                                <button type="button" class="ent-action-btn ent-action-btn--primary" id="btn-publish-post">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span><?= $isEdit ? 'Cập nhật tin tuyển thực tập' : 'Đăng tuyển ngay'; ?></span>
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

    <!-- Modal: Thêm lĩnh vực mới -->
    <div class="ent-modal" id="modal-add-specialty" role="dialog" aria-modal="true" aria-labelledby="modal-add-specialty-title" aria-hidden="true">
        <div class="ent-modal__backdrop"></div>
        <div class="ent-modal__dialog ent-modal__dialog--sm" role="document">
            <div class="ent-modal__header">
                <div class="ent-modal__title-group">
                    <h2 class="ent-modal__title" id="modal-add-specialty-title">Thêm lĩnh vực mới</h2>
                    <p class="ent-modal__subtitle">Lĩnh vực mới sẽ được thêm vào danh sách chuyên môn của doanh nghiệp và dùng ngay cho tin tuyển dụng.</p>
                </div>
                <button type="button" class="ent-modal__close" id="modal-add-specialty-close" aria-label="Đóng cửa sổ">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="ent-modal__body">
                <div class="ent-modal__field">
                    <label for="specialty-name" class="ent-modal__label required">Tên lĩnh vực <span class="ent-modal__hint">(tối thiểu 2 ký tự, tối đa 150)</span></label>
                    <input type="text" id="specialty-name" class="ent-modal__input" placeholder="Ví dụ: Kỹ sư DevOps, Quản lý Dự án, Thiết kế Đồ họa..." maxlength="150" autocomplete="off">
                    <span class="ent-modal__error" id="specialty-name-error" role="alert"></span>
                </div>

                <div class="ent-modal__field">
                    <label for="specialty-category" class="ent-modal__label">Danh mục <span class="ent-modal__hint">(tùy chọn)</span></label>
                    <input type="text" id="specialty-category" class="ent-modal__input" placeholder="Ví dụ: technology, design, business, finance..." maxlength="60" autocomplete="off">
                    <span class="ent-modal__field-help">Dùng để phân loại lĩnh vực. Nếu bỏ trống sẽ mặc định là "general".</span>
                </div>

                <div class="ent-modal__field">
                    <label for="specialty-description" class="ent-modal__label">Mô tả <span class="ent-modal__hint">(tùy chọn)</span></label>
                    <textarea id="specialty-description" class="ent-modal__input ent-modal__textarea" placeholder="Mô tả ngắn về lĩnh vực (tối đa 500 ký tự)..." maxlength="500" rows="3"></textarea>
                    <span class="ent-modal__char-count"><span id="specialty-desc-count">0</span> / 500</span>
                </div>
            </div>
            <div class="ent-modal__footer">
                <button type="button" class="ent-modal__btn ent-modal__btn--secondary" id="modal-add-specialty-cancel">Hủy</button>
                <button type="button" class="ent-modal__btn ent-modal__btn--primary" id="modal-add-specialty-submit">
                    <span class="ent-modal__btn-spinner" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                        </svg>
                    </span>
                    <span class="ent-modal__btn-label">Thêm lĩnh vực</span>
                </button>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <!-- JavaScript Assets -->
    <script id="enterprise-session-boot" type="application/json"><?= json_encode(['csrfToken' => $context['csrfToken'], 'apiBase' => app_href('/api/v1')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?></script>
    <script src="<?= app_href('/assets/js/enterprise.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/internship-management.js'); ?>"></script>

    <!-- Specialty Add Modal — inline JS (page-specific) -->
    <script>
    (function () {
        var sessionBoot = JSON.parse(
            document.getElementById('enterprise-session-boot').textContent ||
            document.querySelector('script[data-csrf]')?.textContent || '{}'
        );
        var csrfToken = sessionBoot.csrfToken || '';
        var apiBase   = sessionBoot.apiBase   || '/api/v1';

        /* ---------- DOM refs ---------- */
        var modal        = document.getElementById('modal-add-specialty');
        var btnAdd       = document.getElementById('btn-add-specialty');
        var btnClose     = document.getElementById('modal-add-specialty-close');
        var btnCancel    = document.getElementById('modal-add-specialty-cancel');
        var btnSubmit    = document.getElementById('modal-add-specialty-submit');
        var nameInput    = document.getElementById('specialty-name');
        var nameError    = document.getElementById('specialty-name-error');
        var catInput     = document.getElementById('specialty-category');
        var descInput    = document.getElementById('specialty-description');
        var descCount    = document.getElementById('specialty-desc-count');
        var selectField  = document.getElementById('form-field');
        var toast        = document.getElementById('ent-toast');
        var toastMsg     = toast ? toast.querySelector('.ent-toast__message') : null;

        /* ---------- Helpers ---------- */
        function showToast(msg, type) {
            if (!toast || !toastMsg) return;
            toastMsg.textContent = msg;
            toast.className = 'ent-toast ent-toast--' + (type || 'info');
            toast.classList.add('ent-toast--show');
            clearTimeout(showToast._t);
            showToast._t = setTimeout(function () {
                toast.classList.remove('ent-toast--show');
            }, 4000);
        }

        function setSubmitLoading(loading) {
            if (!btnSubmit) return;
            var label  = btnSubmit.querySelector('.ent-modal__btn-label');
            var spinner = btnSubmit.querySelector('.ent-modal__btn-spinner');
            btnSubmit.disabled = loading;
            if (label)   label.style.display   = loading ? 'none' : 'inline';
            if (spinner) spinner.style.display = loading ? 'inline-flex' : 'none';
        }

        function clearErrors() {
            if (nameInput) nameInput.classList.remove('ent-modal__input--error');
            if (nameError) nameError.textContent = '';
        }

        function showError(input, errorEl, msg) {
            if (input)   input.classList.add('ent-modal__input--error');
            if (errorEl) errorEl.textContent = msg;
        }

        /* ---------- Modal open / close ---------- */
        function openModal() {
            clearErrors();
            if (nameInput)  nameInput.value  = '';
            if (catInput)   catInput.value   = '';
            if (descInput)  descInput.value  = '';
            if (descCount)  descCount.textContent = '0';
            if (modal) {
                modal.setAttribute('aria-hidden', 'false');
                modal.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                setTimeout(function () { if (nameInput) nameInput.focus(); }, 50);
            }
        }

        function closeModal() {
            if (modal) {
                modal.setAttribute('aria-hidden', 'true');
                modal.classList.remove('is-open');
                document.body.style.overflow = '';
            }
        }

        /* ---------- Desc character counter ---------- */
        if (descInput && descCount) {
            descInput.addEventListener('input', function () {
                descCount.textContent = descInput.value.length;
            });
        }

        /* ---------- Name validation ---------- */
        if (nameInput) {
            nameInput.addEventListener('input', function () {
                nameInput.classList.remove('ent-modal__input--error');
                if (nameError) nameError.textContent = '';
            });
        }

        /* ---------- Event listeners ---------- */
        if (btnAdd)   btnAdd.addEventListener('click',   openModal);
        if (btnClose) btnClose.addEventListener('click', closeModal);
        if (btnCancel) btnCancel.addEventListener('click', closeModal);

        // Close on backdrop click
        if (modal) {
            var backdrop = modal.querySelector('.ent-modal__backdrop');
            if (backdrop) {
                backdrop.addEventListener('click', closeModal);
            }
        }

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        /* ---------- Submit ---------- */
        if (btnSubmit) {
            btnSubmit.addEventListener('click', async function () {
                clearErrors();
                var name = nameInput ? nameInput.value.trim() : '';
                var cat  = catInput  ? catInput.value.trim()  : '';
                var desc = descInput ? descInput.value.trim() : '';

                if (name.length < 2) {
                    showError(nameInput, nameError, 'Tên lĩnh vực phải có tối thiểu 2 ký tự.');
                    if (nameInput) nameInput.focus();
                    return;
                }
                if (name.length > 150) {
                    showError(nameInput, nameError, 'Tên lĩnh vực không được vượt quá 150 ký tự.');
                    if (nameInput) nameInput.focus();
                    return;
                }

                setSubmitLoading(true);
                try {
                    var res = await fetch(apiBase + '/businesses/me/internship-specialties', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Accept':       'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            name:        name,
                            category:    cat,
                            description: desc
                        })
                    });

                    var json = await res.json().catch(function () { return null; });

                    // API contract: success => { data, meta }, error => { error: { code, message }, meta }
                    // There is no top-level `success` flag — see src/Http/JsonResponse.php.
                    if (!res.ok || !json || !json.data) {
                        var msg = (json && json.error && json.error.message)
                            ? json.error.message
                            : 'Không thể tạo lĩnh vực. Vui lòng thử lại.';
                        showError(nameInput, nameError, msg);
                        return;
                    }

                    // Success — close modal, add option to select, auto-select it
                    var newItem = json.data.item;
                    closeModal();
                    if (newItem && newItem.name && selectField) {
                        // Reuse an existing option if the same specialty is already listed,
                        // otherwise append. option.value MUST be the exact specialty name
                        // because the form submits `field` as that name.
                        var existing = null;
                        for (var i = 0; i < selectField.options.length; i++) {
                            if (selectField.options[i].value === newItem.name) {
                                existing = selectField.options[i];
                                break;
                            }
                        }
                        if (!existing) {
                            existing = document.createElement('option');
                            existing.value = newItem.name;
                            existing.textContent = newItem.name + ' (mới)';
                            selectField.appendChild(existing);
                        }
                        existing.selected = true;
                        showToast('Đã thêm "' + newItem.name + '" vào danh sách lĩnh vực.', 'success');
                    } else {
                        // Fallback: reload the page so select is re-rendered from DB
                        showToast('Đã thêm lĩnh vực mới!', 'success');
                        setTimeout(function () { location.reload(); }, 1000);
                    }
                } catch (err) {
                    showError(nameInput, nameError, 'Lỗi kết nối. Vui lòng kiểm tra mạng và thử lại.');
                } finally {
                    setSubmitLoading(false);
                }
            });
        }
    })();
    </script>
</body>
</html>
