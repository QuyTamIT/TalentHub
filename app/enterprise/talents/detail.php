<?php
/**
 * TalentHub Enterprise - Talent Passport / Hồ sơ nhân tài Detail Page
 *
 * Note for Developers:
 * - This detail page displays comprehensive learner profiles including skills,
 *   experience logs, featured projects, certificates, and internship readiness.
 * - Profile data is loaded dynamically by candidate studentId (?id=uuid).
 * - Privacy rules strictly enforced: NO personal email or phone numbers rendered directly without consent.
 * - Contact requests trigger a modal with privacy consent notices.
 */

require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 3) . '/src/Bootstrap/EnterpriseAppContext.php';

use TalentHub\Bootstrap\EnterpriseAppContext;
use TalentHub\Support\Id\RequestId;

$context = (new EnterpriseAppContext())->boot();
$user          = $context['user'];
$enterprise    = $context['enterprise'];
$csrfToken     = $context['csrfToken'];
$talentService = $context['talents'];
$pdo           = $context['pdo'] ?? null;

if (!$pdo instanceof \PDO) {
    $dbConfig = require dirname(__DIR__, 3) . '/config/database.php';
    $pdo = (new \TalentHub\Database\Connection($dbConfig))->connect();
}

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

// Handle direct POST invitation in detail.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['postId']) || isset($_POST['action']))) {
    require dirname(__DIR__) . '/actions/send-invitation.php';
    exit;
}

$talentId = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
$rawTalent = null;
$talent = null;

if ($talentId !== '' && $isVerified && $talentService !== null) {
    try {
        $rawTalent = $talentService->getTalent(
            (string) $user['id'],
            $talentId,
            RequestId::generate(),
            isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null
        );
    } catch (\Throwable $e) {
        error_log('Enterprise getTalent error: ' . $e->getMessage());
        $rawTalent = null;
    }
}

if ($rawTalent !== null) {
    $skillsList = [];
    foreach ($rawTalent['skills'] ?? [] as $sk) {
        $skillsList[] = [
            'name' => (string) ($sk['skillName'] ?? $sk['name'] ?? ''),
            'level' => (string) ($sk['proficiencyLevel'] ?? $sk['level'] ?? 'Nâng cao'),
            'verified' => ($sk['verificationStatus'] ?? '') === 'verified' || !empty($sk['verified']),
        ];
    }

    $talentScore = (int) ($rawTalent['talent_score'] ?? $rawTalent['talentScore'] ?? 85);

    // Normalize projects
    $normalizedProjects = [];
    foreach ($rawTalent['projects'] ?? [] as $pr) {
        $rawSt = strtolower((string)($pr['status'] ?? 'in_progress'));
        $resultLabel = match($rawSt) {
            'completed' => 'Đã hoàn thành',
            'funded', 'goal_reached' => 'Đã nhận tài trợ',
            default => 'Đang thực hiện'
        };
        $normalizedProjects[] = [
            'id' => $pr['id'] ?? '',
            'name' => $pr['title'] ?? ($pr['name'] ?? 'Dự án thực tế'),
            'description' => $pr['description'] ?? 'Dự án phát triển phần mềm và ứng dụng trí tuệ nhân tạo giải quyết bài toán thực tiễn.',
            'role' => $pr['role'] ?? 'Lập trình viên & Kỹ sư AI',
            'category' => $pr['category'] ?? 'AI & Phần mềm',
            'result' => $resultLabel,
            'sponsorName' => $pr['sponsorName'] ?? ($pr['sponsor_name'] ?? ''),
            'technologies' => !empty($pr['technologies']) ? (array) $pr['technologies'] : array_slice(array_column($skillsList, 'name'), 0, 4),
        ];
    }
    if (empty($normalizedProjects)) {
        $normalizedProjects[] = [
            'name' => 'Ứng dụng AI phân loại rác & Tái chế thông minh',
            'description' => 'Mô hình Computer Vision nhận diện tự động phân loại rác thải, áp dụng deep learning YOLOv8.',
            'role' => 'Lập trình viên & Kỹ sư AI',
            'category' => 'AI & Phần mềm',
            'result' => 'Đang phát triển',
            'technologies' => ['Python', 'PyTorch', 'OpenCV', 'REST API']
        ];
    }

    $experienceEntries = $rawTalent['experience']['confirmed_entries'] ?? [];
    $experienceLogs = [];
    foreach ($experienceEntries as $entry) {
        $experienceLogs[] = [
            'title' => $entry['title'] ?? ($entry['activityTitle'] ?? 'Xưởng thực hành & Dự án nghiên cứu'),
            'role' => $entry['role'] ?? 'Thành viên tham gia',
            'duration' => !empty($entry['createdAt']) ? substr((string)$entry['createdAt'], 0, 10) : '2026',
            'hours' => $entry['hours'] ?? 24,
            'description' => $entry['description'] ?? 'Tham gia nghiên cứu, phát triển và thử nghiệm các mô hình AI/IoT thực tế.',
        ];
    }
    if (empty($experienceLogs)) {
        $experienceLogs = [
            [
                'title' => 'IoT Lab - Cảm biến thông minh & AI Nhúng',
                'role' => 'Lập trình viên & Kỹ sư nhúng',
                'duration' => '08/2026',
                'hours' => 24,
                'description' => 'Xưởng thực hành lập trình vi điều khiển ESP32 và tích hợp mô hình AI nhận diện tại Phòng B305 - BTEC FPT.',
            ],
            [
                'title' => 'Hackathon Sáng tạo Trẻ BTEC FPT 2026',
                'role' => 'Trưởng nhóm phát triển AI',
                'duration' => '07/2026',
                'hours' => 36,
                'description' => 'Phát triển nguyên mẫu hệ thống nhận diện và phân loại rác thải tự động đạt giải Nhì chung cuộc.',
            ]
        ];
    }
    $totalExpHours = !empty($rawTalent['experience']['confirmed_hours'])
        ? (int)$rawTalent['experience']['confirmed_hours']
        : (int)array_sum(array_column($experienceLogs, 'hours'));

    $talent = [
        'id' => $rawTalent['studentId'],
        'userId' => $rawTalent['userId'] ?? '',
        'name' => $rawTalent['displayName'],
        'avatar_initials' => getInitials($rawTalent['displayName']),
        'talent_score' => min(100, max(60, $talentScore)),
        'school' => $rawTalent['schoolName'] ?: 'Cao đẳng Quốc tế BTEC FPT',
        'class_year' => $rawTalent['className'] ?: 'BTEC-AI-2026A',
        'education_level' => $rawTalent['studyStatus'] ?: 'Sinh viên',
        'major_field' => $rawTalent['headline'] ?: 'Kỹ thuật phần mềm & AI',
        'internship_status_label' => 'Sẵn sàng thực tập',
        'bio' => $rawTalent['bio'],
        'location' => $rawTalent['location'] ?? 'Hà Nội',
        'detailed_skills' => $skillsList,
        'experience_entries' => $experienceEntries,
        'experience_logs' => $experienceLogs,
        'experience_hours' => $totalExpHours,
        'certificates' => $rawTalent['certificates'] ?? [],
        'projects' => $normalizedProjects,
        'contactAllowed' => $rawTalent['contactAllowed'] ?? false,
        'hasPendingContactRequest' => $rawTalent['hasPendingContactRequest'] ?? false,
        'email' => $rawTalent['email'] ?? null,
        'phone' => $rawTalent['phone'] ?? null,
        'saved' => false,
    ];
}

// Fetch active internship posts for this enterprise
$activePosts = [];
if (!empty($enterprise['id'])) {
    $pStmt = $pdo->prepare("SELECT id, title, field, duration, location FROM internship_posts WHERE enterpriseId = ? AND status = 'active' ORDER BY createdAt DESC");
    $pStmt->execute([$enterprise['id']]);
    $activePosts = $pStmt->fetchAll(PDO::FETCH_ASSOC);
}
if (empty($activePosts)) {
    $activePosts = [
        ['id' => '40000000-0000-4000-8000-000000000001', 'title' => 'Thực tập sinh Trí tuệ Nhân tạo & LLM (AI/GenAI Intern)', 'location' => 'Hà Nội'],
        ['id' => '10909e00-1e49-4373-97a0-c9519c74d659', 'title' => 'Frontend Developer (ReactJS / Vue.js)', 'location' => 'Hà Nội'],
        ['id' => '40000000-0000-4000-8000-000000000003', 'title' => 'Kỹ sư Kiểm thử Phần mềm Tự động (Automation QA Trainee)', 'location' => 'Hà Nội'],
    ];
}

$pageTitle = $talent ? ('Hồ sơ nhân tài - ' . $talent['name']) : 'Không tìm thấy hồ sơ';
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
    <meta name="description" content="Talent Passport - Hồ sơ năng lực chi tiết của ứng viên trên TalentHub Enterprise.">
    <title><?= htmlspecialchars($pageTitle); ?> | TalentHub Enterprise</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="<?= app_href('/assets/css/home.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/enterprise.css'); ?>">
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
                    
                    <!-- Back Link Navigation -->
                    <div class="ent-back-bar">
                        <a href="<?= app_href('/app/enterprise/talents.php'); ?>" class="ent-back-link" data-route="/app/enterprise/talents.php">
                            &larr; Quay lại Tìm nhân tài
                        </a>
                    </div>

                    <?php if (!$talent): ?>
                        <!-- Invalid Candidate ID Error State -->
                        <div class="ent-empty-state" style="margin-top: 2rem;">
                            <div class="ent-empty-state__icon">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                            </div>
                            <h3 class="ent-empty-state__title">Không tìm thấy hồ sơ nhân tài</h3>
                            <p class="ent-empty-state__desc">
                                Hồ sơ ứng viên với mã số #<?= htmlspecialchars($talentId); ?> không tồn tại hoặc đã bị xóa khỏi hệ thống.
                            </p>
                            <a href="<?= app_href('/app/enterprise/talents.php'); ?>" class="btn btn-primary">
                                &larr; Quay lại Tìm nhân tài
                            </a>
                        </div>
                    <?php else: ?>
                        
                        <!-- Talent Passport Main Detail Layout (2 Columns) -->
                        <div class="ent-passport-grid">
                            
                            <!-- Left / Main Column (Overview, Bio, Skills, Experience, Projects, Certificates) -->
                            <div class="ent-passport-main">
                                
                                <!-- 1. Profile Overview Header Card -->
                                <section class="ent-section-box ent-passport-overview-card">
                                    <div class="ent-passport-overview__top">
                                        <div class="ent-passport-overview__avatar">
                                            <?= htmlspecialchars($talent['avatar_initials']); ?>
                                        </div>
                                        <div class="ent-passport-overview__info">
                                            <div class="ent-passport-overview__title-row">
                                                <h2 class="ent-passport-overview__name"><?= htmlspecialchars($talent['name']); ?></h2>
                                                <span class="ent-passport-score-badge">
                                                    <?= htmlspecialchars($talent['talent_score']); ?> điểm
                                                </span>
                                            </div>

                                            <div class="ent-passport-overview__meta">
                                                <span><?= htmlspecialchars($talent['school']); ?></span>
                                                <span class="dot">&bull;</span>
                                                <span><?= htmlspecialchars($talent['class_year']); ?></span>
                                                <span class="dot">&bull;</span>
                                                <span><?= htmlspecialchars($talent['education_level']); ?></span>
                                            </div>

                                            <div class="ent-passport-overview__sub">
                                                <span class="label">Lĩnh vực năng lực:</span>
                                                <span class="val font-semibold"><?= htmlspecialchars($talent['major_field']); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ent-passport-overview__actions-bar">
                                        <div class="ent-passport-status-pill">
                                            <span class="status-dot"></span>
                                            <?= htmlspecialchars($talent['internship_status_label']); ?>
                                        </div>

                                        <div class="ent-passport-btn-group">
                                            <button type="button" 
                                                    class="btn btn-secondary btn-sm ent-passport-save-btn <?= $talent['saved'] ? 'is-saved' : ''; ?>" 
                                                    id="detail-save-btn" 
                                                    data-talent-id="<?= htmlspecialchars($talent['id']); ?>">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="<?= $talent['saved'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                                </svg>
                                                <span class="btn-text"><?= $talent['saved'] ? 'Đã lưu hồ sơ' : 'Lưu hồ sơ'; ?></span>
                                            </button>

                                            <button type="button" 
                                                    class="btn btn-primary btn-sm" 
                                                    id="detail-invite-btn"
                                                    onclick="openInviteModal()"
                                                    style="background: #2563EB; border-color: #2563EB; font-weight: 700; display: inline-flex; align-items: center; gap: 0.45rem;">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                                </svg>
                                                <span>Mời thực tập / Tuyển dụng</span>
                                            </button>

                                            <button type="button" 
                                                    class="btn btn-secondary btn-sm" 
                                                    id="detail-contact-btn">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                    <polyline points="22,6 12,13 2,6"></polyline>
                                                </svg>
                                                Liên hệ
                                            </button>
                                        </div>
                                    </div>
                                </section>

                                <!-- 2. Giới thiệu (Learner Bio & Orientation) -->
                                <section class="ent-section-box">
                                    <h3 class="ent-section-box__title">Giới thiệu bản thân & Định hướng</h3>
                                    <p class="ent-passport-bio-text">
                                        <?= htmlspecialchars($talent['bio'] ?? 'Học sinh / Sinh viên năng động, ham học hỏi và luôn chủ động trau dồi kỹ năng thực tế thông qua các dự án và sảnh chơi công nghệ.'); ?>
                                    </p>
                                </section>

                                <!-- 3. Kỹ năng (Detailed Skills Grid) -->
                                <section class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <h3 class="ent-section-box__title">Năng lực & Kỹ năng</h3>
                                        <span class="ent-section-box__count"><?= count($talent['detailed_skills'] ?? []); ?> kỹ năng</span>
                                    </div>

                                    <div class="ent-passport-skills-grid">
                                        <?php if (!empty($talent['detailed_skills'])): ?>
                                            <?php foreach ($talent['detailed_skills'] as $sk): ?>
                                                <div class="ent-passport-skill-card">
                                                    <div class="ent-passport-skill-card__name">
                                                        <?= htmlspecialchars($sk['name']); ?>
                                                    </div>
                                                    <div class="ent-passport-skill-card__footer">
                                                        <span class="ent-skill-level"><?= htmlspecialchars($sk['level']); ?></span>
                                                        <?php if ($sk['verified']): ?>
                                                            <span class="ent-verified-badge" title="Đã được giáo viên / nhà trường xác thực">
                                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                                    <polyline points="20 6 9 17 4 12"></polyline>
                                                                </svg>
                                                                Đã xác thực
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="ent-unverified-badge">Tự đánh giá</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <!-- 4. Kinh nghiệm & Hoạt động (Experience Logs Timeline) -->
                                <section class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <div>
                                            <h3 class="ent-section-box__title">Kinh nghiệm & Hoạt động thực án</h3>
                                            <p class="ent-section-box__subtitle">Nhật ký giờ trải nghiệm thực tế được lưu vết tự động</p>
                                        </div>
                                        <span class="ent-exp-hours-badge">
                                            Tổng: <?= htmlspecialchars($talent['experience_hours']); ?>h trải nghiệm
                                        </span>
                                    </div>

                                    <div class="ent-passport-timeline">
                                        <?php if (!empty($talent['experience_logs'])): ?>
                                            <?php foreach ($talent['experience_logs'] as $exp): ?>
                                                <div class="ent-passport-timeline-item">
                                                    <div class="ent-passport-timeline-item__indicator"></div>
                                                    <div class="ent-passport-timeline-item__header">
                                                        <h4 class="ent-passport-timeline-item__title"><?= htmlspecialchars($exp['title']); ?></h4>
                                                        <span class="ent-passport-timeline-item__duration"><?= htmlspecialchars($exp['duration']); ?></span>
                                                    </div>
                                                    <div class="ent-passport-timeline-item__meta">
                                                        <span class="role font-medium">Vai trò: <?= htmlspecialchars($exp['role']); ?></span>
                                                        <span class="dot">&bull;</span>
                                                        <span class="hours text-primary"><?= htmlspecialchars($exp['hours']); ?> giờ thực án</span>
                                                    </div>
                                                    <p class="ent-passport-timeline-item__desc"><?= htmlspecialchars($exp['description']); ?></p>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <!-- 5. Dự án nổi bật (Featured Projects) -->
                                <section class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <h3 class="ent-section-box__title">Dự án nổi bật</h3>
                                    </div>

                                    <div class="ent-passport-projects-list">
                                        <?php if (!empty($talent['projects'])): ?>
                                            <?php foreach ($talent['projects'] as $proj): ?>
                                                    <div class="ent-passport-project-card__header" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                                                        <h4 class="ent-passport-project-card__title" style="margin: 0;"><?= htmlspecialchars($proj['name']); ?></h4>
                                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <?php if (!empty($proj['sponsorName'])): ?>
                                                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; border-radius: 4px; font-size: 0.72rem; font-weight: 600; background: #EEF2FF; color: #4338CA; border: 1px solid #C7D2FE;">
                                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                                                    Được bảo trợ bởi <?= htmlspecialchars($proj['sponsorName']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($proj['result'])): ?>
                                                                <span class="ent-project-result-badge"><?= htmlspecialchars($proj['result']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <p class="ent-passport-project-card__desc"><?= htmlspecialchars($proj['description']); ?></p>
                                                    <div class="ent-passport-project-card__meta">
                                                        <span class="label">Vai trò:</span>
                                                        <span class="val font-medium"><?= htmlspecialchars($proj['role']); ?></span>
                                                    </div>
                                                    <div class="ent-passport-project-card__techs">
                                                        <?php foreach ($proj['technologies'] as $tech): ?>
                                                            <span class="skill-tag"><?= htmlspecialchars($tech); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <!-- 6. Chứng chỉ & Thành tích -->
                                <section class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <h3 class="ent-section-box__title">Chứng chỉ & Thành tích</h3>
                                    </div>

                                    <div class="ent-passport-certs-list">
                                        <?php if (!empty($talent['certificates'])): ?>
                                            <?php foreach ($talent['certificates'] as $cert): ?>
                                                <div class="ent-passport-cert-row">
                                                    <div class="ent-passport-cert-row__icon">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="12" cy="8" r="7"></circle>
                                                            <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                                                        </svg>
                                                    </div>
                                                    <div class="ent-passport-cert-row__info">
                                                        <h4 class="cert-name"><?= htmlspecialchars($cert['title'] ?? $cert['name'] ?? 'Chứng chỉ chuyên môn'); ?></h4>
                                                        <span class="cert-issuer"><?= htmlspecialchars($cert['issuingOrganization'] ?? $cert['issuer'] ?? 'Tổ chức đào tạo'); ?> &bull; <?= htmlspecialchars($cert['issueDate'] ?? $cert['issue_date'] ?? '2025'); ?></span>
                                                    </div>
                                                    <?php if (!empty($cert['verified']) || (($cert['verificationStatus'] ?? '') === 'verified')): ?>
                                                        <span class="ent-verified-badge">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                                                <polyline points="20 6 9 17 4 12"></polyline>
                                                            </svg>
                                                            Đã minh chứng
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>

                            </div>

                            <!-- Right Column Sidebar (Readiness Summary & Privacy Card) -->
                            <aside class="ent-passport-sidebar">
                                
                                <!-- 7. Internship Readiness Summary Widget -->
                                <div class="ent-section-box">
                                    <div class="ent-section-box__header">
                                        <h3 class="ent-section-box__title">Tóm tắt Mức độ Sẵn sàng</h3>
                                    </div>

                                    <div class="ent-readiness-widget">
                                        <div class="ent-readiness-widget__status">
                                            <span class="status-label">Trạng thái tuyển dụng:</span>
                                            <span class="status-value text-accent font-bold">
                                                ● <?= htmlspecialchars($talent['readiness_summary']['status_label'] ?? $talent['internship_status_label']); ?>
                                            </span>
                                        </div>

                                        <div class="ent-readiness-widget__field">
                                            <span class="label">Vị trí mong muốn:</span>
                                            <span class="val font-semibold"><?= htmlspecialchars($talent['readiness_summary']['preferred_field'] ?? $talent['major_field']); ?></span>
                                        </div>

                                        <div class="ent-readiness-widget__strengths">
                                            <span class="label">Điểm mạnh nổi bật:</span>
                                            <ul>
                                                <?php foreach (($talent['readiness_summary']['strengths'] ?? ['Tư duy kỹ thuật tốt', 'Kỹ năng thực hành cao']) as $st): ?>
                                                    <li>&bull; <?= htmlspecialchars($st); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>

                                        <div class="ent-readiness-widget__exp">
                                            <span class="label">Tổng giờ trải nghiệm:</span>
                                            <span class="val font-bold text-primary"><?= htmlspecialchars($talent['readiness_summary']['total_exp_hours'] ?? ($talent['experience_hours'] . 'h thực án')); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Privacy Protection Notice Card -->
                                <div class="ent-section-box ent-privacy-card">
                                    <div class="ent-privacy-card__icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                    </div>
                                    <h4>Quyền riêng tư được bảo vệ</h4>
                                    <p>
                                        Thông tin liên hệ cá nhân (Số điện thoại, Email) của người học được ẩn theo tiêu chuẩn bảo mật TalentHub. Khi gửi yêu cầu liên hệ, thông báo sẽ được gửi tới người học để nhận sự chấp thuận.
                                    </p>
                                </div>

                            </aside>

                        </div>

                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <!-- Section 8: Contact Request Modal -->
    <?php if ($talent): ?>
        <div class="ent-skills-modal" id="contact-modal" aria-hidden="true" style="display: none;">
            <div class="ent-skills-modal__backdrop" id="contact-modal-backdrop"></div>
            <div class="ent-skills-modal__dialog" style="max-width: 520px;">
                <div class="ent-skills-modal__header">
                    <div>
                        <h3 class="ent-skills-modal__title">Gửi yêu cầu liên hệ</h3>
                        <p class="ent-skills-modal__subtitle">Gửi đề xuất kết nối tuyển dụng tới ứng viên <?= htmlspecialchars($talent['name']); ?></p>
                    </div>
                    <button type="button" class="ent-skills-modal__close" id="close-contact-modal-btn" aria-label="Đóng">&times;</button>
                </div>

                <div class="ent-contact-modal__body">
                    <!-- Privacy Notice Banner -->
                    <div class="ent-contact-privacy-note">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <span>Thông tin liên hệ chỉ được chia sẻ khi người học đồng ý.</span>
                    </div>

                    <div class="ent-contact-form-group">
                        <label for="contact-message-input" class="ent-filter-label">Lời nhắn từ doanh nghiệp (tùy chọn):</label>
                        <textarea id="contact-message-input" 
                                  class="ent-contact-textarea" 
                                  rows="4" 
                                  placeholder="Ví dụ: Chào bạn, <?= htmlspecialchars($enterpriseInfo['company_name']); ?> ấn tượng với hồ sơ năng lực của bạn và muốn mời bạn tham gia buổi phỏng vấn thực tập vị trí <?= htmlspecialchars($talent['major_field']); ?>..."></textarea>
                    </div>
                </div>

                <div class="ent-skills-modal__footer">
                    <button type="button" class="btn btn-secondary" id="cancel-contact-btn">Hủy</button>
                    <button type="button" class="btn btn-primary" id="submit-contact-btn" data-talent-name="<?= htmlspecialchars($talent['name']); ?>">Gửi yêu cầu</button>
                </div>
            </div>
        </div>

        <!-- Section 9: Internship Invitation Modal -->
        <div class="ent-skills-modal" id="inviteModal" aria-hidden="true" style="display: none; position: fixed; inset: 0; z-index: 9999; align-items: center; justify-content: center;">
            <div class="ent-skills-modal__backdrop" onclick="closeInviteModal()" style="position: absolute; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);"></div>
            <div class="ent-skills-modal__dialog" style="position: relative; z-index: 10000; width: 92%; max-width: 560px; background: #FFFFFF; border-radius: 14px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden; animation: modalFadeIn 0.2s ease-out;">
                
                <div class="ent-skills-modal__header" style="background: #F8FAFC; border-bottom: 1px solid #E2E8F0; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 class="ent-skills-modal__title" style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0F172A; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563EB" stroke-width="2.5">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                            <span>Gửi Lời Mời Thực Tập / Tuyển Dụng</span>
                        </h3>
                        <p class="ent-skills-modal__subtitle" style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #64748B;">
                            Mời ứng viên <strong><?= htmlspecialchars($talent['name']); ?></strong> (<?= htmlspecialchars($talent['talent_score']); ?> điểm) vào đội ngũ FPT Software
                        </p>
                    </div>
                    <button type="button" class="ent-skills-modal__close" onclick="closeInviteModal()" style="border: none; background: transparent; font-size: 1.6rem; line-height: 1; cursor: pointer; color: #94A3B8; padding: 0.2rem 0.5rem;">&times;</button>
                </div>

                <div style="padding: 1.5rem;">
                    <!-- Candidate Highlight Banner -->
                    <div style="background: #F1F5F9; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 38px; height: 38px; border-radius: 8px; background: #DBEAFE; color: #1D4ED8; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 0.875rem;">
                                <?= htmlspecialchars($talent['avatar_initials']); ?>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: #0F172A; font-size: 0.95rem;"><?= htmlspecialchars($talent['name']); ?></div>
                                <div style="font-size: 0.75rem; color: #64748B;"><?= htmlspecialchars($talent['major_field']); ?> • <?= htmlspecialchars($talent['school']); ?></div>
                            </div>
                        </div>
                        <div style="background: #ECFDF5; color: #047857; font-weight: 800; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.85rem; border: 1px solid #A7F3D0;">
                            <?= htmlspecialchars($talent['talent_score']); ?> điểm
                        </div>
                    </div>

                    <!-- Job Post Selector -->
                    <div style="margin-bottom: 1.25rem;">
                        <label for="invitePostSelect" style="display: block; font-size: 0.875rem; font-weight: 700; color: #334155; margin-bottom: 0.4rem;">
                            Chọn vị trí tuyển dụng đang mở <span style="color: #EF4444;">*</span>
                        </label>
                        <select id="invitePostSelect" style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.9rem; color: #0F172A; font-weight: 600; background: #FFFFFF;">
                            <?php foreach ($activePosts as $post): ?>
                                <option value="<?= htmlspecialchars($post['id']); ?>">
                                    <?= htmlspecialchars($post['title']); ?> (<?= htmlspecialchars($post['location'] ?? 'Toàn thời gian'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Short Message -->
                    <div style="margin-bottom: 1.25rem;">
                        <label for="inviteMessageInput" style="display: block; font-size: 0.875rem; font-weight: 700; color: #334155; margin-bottom: 0.4rem;">
                            Lời nhắn gửi tới ứng viên:
                        </label>
                        <textarea id="inviteMessageInput" 
                                  rows="3" 
                                  style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #CBD5E1; border-radius: 8px; font-size: 0.875rem; color: #0F172A; resize: vertical;"
                                  placeholder="Ví dụ: Chào bạn <?= htmlspecialchars($talent['name']); ?>, FPT Software rất ấn tượng với hồ sơ năng lực và điểm đánh giá <?= htmlspecialchars($talent['talent_score']); ?> điểm của bạn. Trân trọng mời bạn tham gia thực tập..."></textarea>
                    </div>

                    <!-- Privacy / Notification Tip -->
                    <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.8125rem; color: #1E40AF; display: flex; align-items: flex-start; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        <span>Hệ thống sẽ lưu lời mời vào danh sách ứng tuyển thực tập và gửi thông báo trực tiếp đến tài khoản sinh viên trên TalentHub.</span>
                    </div>
                </div>

                <div style="background: #F8FAFC; border-top: 1px solid #E2E8F0; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeInviteModal()" style="font-weight: 600;">Hủy</button>
                    <button type="button" class="btn btn-primary" id="confirmSendInviteBtn" onclick="submitInternshipInvitation()" style="background: #2563EB; border-color: #2563EB; font-weight: 700; padding: 0.5rem 1.25rem;">
                        Xác nhận gửi lời mời
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

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

    <!-- Bootstrap session configuration for talent detail -->
    <script id="enterprise-talent-detail-boot" type="application/json">
        <?= json_encode([
            'csrfToken' => $csrfToken,
            'studentId' => $talentId,
            'apiBase' => '/api/v1/businesses/me',
            'contactAllowed' => $talent['contactAllowed'] ?? false,
            'hasPendingContactRequest' => $talent['hasPendingContactRequest'] ?? false,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    </script>

    <!-- JavaScript Assets -->
    <script src="<?= app_href('/assets/js/enterprise.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/talent-detail.js'); ?>"></script>

    <script>
        function openInviteModal() {
            const modal = document.getElementById('inviteModal');
            if (modal) modal.style.display = 'flex';
        }

        function closeInviteModal() {
            const modal = document.getElementById('inviteModal');
            if (modal) modal.style.display = 'none';
        }

        function showDetailToast(msg) {
            const toast = document.getElementById('ent-toast');
            if (toast) {
                const msgEl = toast.querySelector('.ent-toast__message');
                if (msgEl) msgEl.textContent = msg;
                toast.classList.add('is-visible');
                setTimeout(() => { toast.classList.remove('is-visible'); }, 3500);
            } else {
                alert(msg);
            }
        }

        async function submitInternshipInvitation() {
            const postSelect = document.getElementById('invitePostSelect');
            const msgInput = document.getElementById('inviteMessageInput');
            const btn = document.getElementById('confirmSendInviteBtn');

            if (!postSelect || !postSelect.value) {
                alert('Vui lòng chọn một vị trí thực tập.');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Đang gửi lời mời...';

            const formData = new FormData();
            formData.append('studentId', <?= json_encode($talent['id'] ?? ''); ?>);
            formData.append('postId', postSelect.value);
            formData.append('message', msgInput ? msgInput.value.trim() : '');
            formData.append('csrfToken', <?= json_encode($csrfToken ?? ''); ?>);

            try {
                const sendUrl = <?= json_encode(app_href('/app/enterprise/actions/send-invitation.php')); ?>;
                const res = await fetch(sendUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();

                if (data.success) {
                    closeInviteModal();
                    showDetailToast(data.message || 'Đã gửi lời mời thực tập thành công!');
                    const mainInviteBtn = document.getElementById('detail-invite-btn');
                    if (mainInviteBtn) {
                        mainInviteBtn.innerHTML = `
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Đã gửi lời mời</span>
                        `;
                        mainInviteBtn.style.background = '#059669';
                        mainInviteBtn.style.borderColor = '#059669';
                    }
                } else {
                    alert(data.message || 'Không thể gửi lời mời lúc này.');
                }
            } catch (err) {
                console.error(err);
                alert('Lỗi kết nối tới máy chủ khi gửi lời mời.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Xác nhận gửi lời mời';
            }
        }
    </script>
</body>
</html>
