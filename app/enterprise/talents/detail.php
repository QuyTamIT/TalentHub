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
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (isset($_POST['postId']) || isset($_POST['action']))) {
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
        // Primary path (DatabaseTalentPassportRepository) trả về snake_case keys qua KeyMapper::toSnake():
        //   levelScore → level_score, verificationStatus → verification_status, name → name
        // Fallback path (skillsWithDetailsForStudent) trả về camelCase keys trực tiếp từ PDO:
        //   skillName, levelScore, verificationStatus, level (computed CASE column)
        $rawLevelScore = $sk['level_score'] ?? $sk['levelScore'] ?? null;
        $levelLabel = match(true) {
            is_numeric($rawLevelScore) && (int)$rawLevelScore >= 85 => 'Nâng cao',
            is_numeric($rawLevelScore) && (int)$rawLevelScore >= 65 => 'Trung bình',
            is_numeric($rawLevelScore) => 'Cơ bản',
            default => (string) ($sk['proficiencyLevel'] ?? $sk['level'] ?? 'Nâng cao'),
        };
        $verifStatus = (string) ($sk['verification_status'] ?? $sk['verificationStatus'] ?? '');
        $skillsList[] = [
            'name'     => (string) ($sk['skillName'] ?? $sk['name'] ?? ''),
            'level'    => $levelLabel,
            'verified' => $verifStatus === 'verified' || !empty($sk['verified']),
        ];
    }

    $rawScore = $rawTalent['talent_score'] ?? $rawTalent['talentScore'] ?? null;
    $talentScore = is_numeric($rawScore) ? (int) $rawScore : 0;

    // Normalize projects
    $normalizedProjects = [];
    foreach ($rawTalent['projects'] ?? [] as $pr) {
        $rawSt = strtolower((string)($pr['status'] ?? 'in_progress'));
        $resultLabel = match($rawSt) {
            'completed' => 'Đã hoàn thành',
            'funded', 'goal_reached' => 'Đã nhận tài trợ',
            default => 'Đang thực hiện'
        };
        // Translate English role values stored in DB to Vietnamese display labels.
        // DB values: 'member', 'leader', 'mentor', 'advisor' — never hardcoded as skill.
        $rawRole = strtolower(trim((string) ($pr['role'] ?? '')));
        $roleLabel = match($rawRole) {
            'member'  => 'Thành viên',
            'leader'  => 'Trưởng nhóm',
            'mentor'  => 'Cố vấn',
            'advisor' => 'Cố vấn',
            ''        => 'Thành viên',
            default   => (string) ($pr['role'] ?? 'Thành viên'),
        };
        $normalizedProjects[] = [
            'id' => $pr['id'] ?? '',
            'name' => $pr['title'] ?? ($pr['name'] ?? 'Dự án'),
            'description' => (string) ($pr['description'] ?? ''),
            'role' => $roleLabel,
            'category' => (string) ($pr['category'] ?? ''),
            'result' => $resultLabel,
            'sponsorName' => $pr['sponsorName'] ?? ($pr['sponsor_name'] ?? ''),
            'technologies' => !empty($pr['technologies']) ? (array) $pr['technologies'] : [],
        ];
    }

    $experienceEntries = $rawTalent['experience']['confirmed_entries'] ?? [];
    $experienceLogs = [];
    foreach ($experienceEntries as $entry) {
        // Primary path (DatabaseTalentPassportRepository) trả về snake_case keys qua KeyMapper::toSnake():
        //   activityTitle → activity_title, confirmedAt → confirmed_at
        // Fallback path (experienceForStudent / student_experience_entries) trả về camelCase keys.
        $entryTitle = $entry['activity_title']  // primary path: snake_case
            ?? $entry['activityTitle']           // fallback camelCase
            ?? $entry['title']                   // student_experience_entries fallback
            ?? 'Hoạt động trải nghiệm';
        $entryDate = $entry['confirmed_at']      // primary path: snake_case
            ?? $entry['confirmedAt']             // fallback camelCase
            ?? $entry['createdAt']               // student_experience_entries
            ?? '';
        $experienceLogs[] = [
            'title'       => $entryTitle,
            'role'        => $entry['role'] ?? 'Thành viên tham gia',
            'duration'    => !empty($entryDate) ? substr((string)$entryDate, 0, 10) : '',
            'hours'       => (int) ($entry['hours'] ?? 0),
            'description' => (string) ($entry['description'] ?? ''),
        ];
    }

    $totalExpHours = isset($rawTalent['experience']['confirmed_hours'])
        ? (int) $rawTalent['experience']['confirmed_hours']
        : (int) array_sum(array_column($experienceLogs, 'hours'));

    $talent = [
        'id' => $rawTalent['studentId'],
        'userId' => $rawTalent['userId'] ?? '',
        'name' => $rawTalent['displayName'],
        'avatar_initials' => getInitials($rawTalent['displayName']),
        'talent_score' => $talentScore > 0 ? min(100, max(0, $talentScore)) : 0,
        'school' => !empty(trim((string)($rawTalent['schoolName'] ?? ''))) ? trim((string)$rawTalent['schoolName']) : 'Chưa cập nhật',
        'class_year' => !empty(trim((string)($rawTalent['className'] ?? ''))) ? trim((string)$rawTalent['className']) : 'Chưa cập nhật',
        'education_level' => !empty(trim((string)($rawTalent['studyStatus'] ?? ''))) ? trim((string)$rawTalent['studyStatus']) : 'Sinh viên',
        'major_field' => !empty(trim((string)($rawTalent['headline'] ?? ''))) ? trim((string)$rawTalent['headline']) : 'Chưa cập nhật',
        'internship_status_label' => 'Sẵn sàng thực tập',
        'bio' => trim((string)($rawTalent['bio'] ?? '')),
        'location' => !empty(trim((string)($rawTalent['location'] ?? ''))) ? trim((string)$rawTalent['location']) : 'Chưa cập nhật',
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

// Check if this enterprise has already invited this candidate
$hasInvited = false;
$invitedPostTitle = '';
if (!empty($enterprise['id']) && !empty($talent['id'])) {
    $invCheckStmt = $pdo->prepare("
        SELECT ia.id, ia.postId, ia.status, ip.title as postTitle
        FROM internship_applications ia
        JOIN internship_posts ip ON ip.id = ia.postId
        WHERE ip.enterpriseId = ? AND (ia.studentId = ? OR ia.studentId = ?) AND ia.status = 'invited'
        ORDER BY ia.updatedAt DESC
        LIMIT 1
    ");
    $invCheckStmt->execute([$enterprise['id'], $talent['id'], $talent['userId'] ?? '']);
    $existingInvite = $invCheckStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingInvite) {
        $hasInvited = true;
        $invitedPostTitle = (string) $existingInvite['postTitle'];
    }
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
    <link rel="stylesheet" href="<?= app_href('/assets/css/global.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/brand-component.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/polish.css'); ?>">
    <link rel="stylesheet" href="<?= app_href('/assets/css/enterprise.css'); ?>">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?= app_href('/assets/css/typeui-selects.css'); ?>">
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

                        <!-- ═══════════════════════════════════════════════════
                             TALENT PASSPORT — redesigned layout
                             Main: bio · experience · projects
                             Sidebar: score · readiness · skills · certs
                             ═══════════════════════════════════════════════════ -->
                        <div class="ent-passport-grid">

                            <!-- ── LEFT / MAIN COLUMN ──────────────────────── -->
                            <div class="ent-passport-main">

                                <!-- 1. PROFILE HEADER -->
                                <div class="ent-profile-header">
                                    <div class="ent-profile-header__avatar">
                                        <?= htmlspecialchars($talent['avatar_initials']); ?>
                                    </div>
                                    <div class="ent-profile-header__body">
                                        <div class="ent-profile-header__name-row">
                                            <h2 class="ent-profile-header__name"><?= htmlspecialchars($talent['name']); ?></h2>
                                            <span class="ent-passport-score-badge"><?= htmlspecialchars($talent['talent_score']); ?> điểm</span>
                                        </div>
                                        <p class="ent-profile-header__meta">
                                            <?= htmlspecialchars($talent['school']); ?>
                                            <?php if (!empty(trim($talent['class_year'])) && $talent['class_year'] !== 'Chưa cập nhật'): ?>
                                                <span class="ent-meta-dot">&bull;</span><?= htmlspecialchars($talent['class_year']); ?>
                                            <?php endif; ?>
                                            <?php if (!empty(trim($talent['education_level'])) && $talent['education_level'] !== 'Sinh viên'): ?>
                                                <span class="ent-meta-dot">&bull;</span><?= htmlspecialchars($talent['education_level']); ?>
                                            <?php endif; ?>
                                            <?php if ($talent['major_field'] !== 'Chưa cập nhật'): ?>
                                                <span class="ent-meta-dot">&bull;</span><?= htmlspecialchars($talent['major_field']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <div class="ent-profile-header__status-row">
                                            <span class="ent-passport-status-pill">
                                                <span class="status-dot"></span>
                                                <?= htmlspecialchars($talent['internship_status_label']); ?>
                                            </span>
                                            <?php if ($talent['experience_hours'] > 0): ?>
                                                <span class="ent-profile-header__exp-chip">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                    <?= htmlspecialchars($talent['experience_hours']); ?>h trải nghiệm
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ent-profile-header__actions">
                                        <button type="button"
                                                class="btn btn-secondary btn-sm ent-passport-save-btn <?= $talent['saved'] ? 'is-saved' : ''; ?>"
                                                id="detail-save-btn"
                                                data-talent-id="<?= htmlspecialchars($talent['id']); ?>">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="<?= $talent['saved'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
                                            <span class="btn-text"><?= $talent['saved'] ? 'Đã lưu' : 'Lưu hồ sơ'; ?></span>
                                        </button>
                                        <button type="button"
                                                class="btn <?= $hasInvited ? 'btn-success' : 'btn-primary'; ?> btn-sm"
                                                id="detail-invite-btn"
                                                onclick="openInviteModal()"
                                                <?= $hasInvited ? 'style="background: #059669; border-color: #059669;"' : ''; ?>>
                                            <?php if ($hasInvited): ?>
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                <span>Đã gửi lời mời</span>
                                            <?php else: ?>
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                                <span>Mời thực tập</span>
                                            <?php endif; ?>
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" id="detail-contact-btn">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                            Liên hệ
                                        </button>
                                    </div>
                                </div>

                                <!-- 2. BIO — chỉ hiện khi có data -->
                                <?php if (!empty($talent['bio'])): ?>
                                <div class="ent-profile-section">
                                    <h3 class="ent-profile-section__title">Giới thiệu</h3>
                                    <p class="ent-passport-bio-text"><?= nl2br(htmlspecialchars($talent['bio'])); ?></p>
                                </div>
                                <?php endif; ?>

                                <!-- 3. KINH NGHIỆM & HOẠT ĐỘNG -->
                                <div class="ent-profile-section">
                                    <div class="ent-profile-section__header">
                                        <h3 class="ent-profile-section__title">Kinh nghiệm & Hoạt động</h3>
                                        <?php if ($talent['experience_hours'] > 0): ?>
                                            <span class="ent-exp-hours-badge"><?= htmlspecialchars($talent['experience_hours']); ?>h</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($talent['experience_logs'])): ?>
                                        <div class="ent-passport-timeline">
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
                                                    <?php if (!empty($exp['description'])): ?>
                                                        <p class="ent-passport-timeline-item__desc"><?= htmlspecialchars($exp['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="ent-profile-empty-inline">Chưa có nhật ký hoạt động thực tế.</p>
                                    <?php endif; ?>
                                </div>

                                <!-- 4. DỰ ÁN NỔI BẬT -->
                                <?php if (!empty($talent['projects'])): ?>
                                <div class="ent-profile-section">
                                    <h3 class="ent-profile-section__title">Dự án</h3>
                                    <div class="ent-passport-projects-list">
                                        <?php foreach ($talent['projects'] as $proj): ?>
                                            <div class="ent-passport-project-card">
                                                <div class="ent-passport-project-card__header" style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
                                                    <h4 class="ent-passport-project-card__title" style="margin:0"><?= htmlspecialchars($proj['name']); ?></h4>
                                                    <div style="display:flex;align-items:center;gap:.5rem;">
                                                        <?php if (!empty($proj['sponsorName'])): ?>
                                                            <span class="ent-sponsor-chip">
                                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                                                <?= htmlspecialchars($proj['sponsorName']); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($proj['result'])): ?>
                                                            <span class="ent-project-result-badge"><?= htmlspecialchars($proj['result']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if (!empty($proj['description'])): ?>
                                                    <p class="ent-passport-project-card__desc"><?= htmlspecialchars($proj['description']); ?></p>
                                                <?php endif; ?>
                                                <div class="ent-passport-project-card__meta">
                                                    <span class="label">Vai trò:</span>
                                                    <span class="val font-medium"><?= htmlspecialchars($proj['role']); ?></span>
                                                </div>
                                                <?php if (!empty($proj['technologies'])): ?>
                                                    <div class="ent-passport-project-card__techs">
                                                        <?php foreach ($proj['technologies'] as $tech): ?>
                                                            <span class="skill-tag"><?= htmlspecialchars($tech); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div><!-- /.ent-passport-main -->

                            <!-- ── RIGHT / SIDEBAR COLUMN ──────────────────── -->
                            <aside class="ent-passport-sidebar">

                                <!-- READINESS SUMMARY -->
                                <div class="ent-sidebar-card">
                                    <div class="ent-sidebar-card__score-row">
                                        <span class="ent-sidebar-card__score-num"><?= htmlspecialchars($talent['talent_score']); ?></span>
                                        <span class="ent-sidebar-card__score-label">điểm năng lực</span>
                                    </div>
                                    <div class="ent-sidebar-card__row">
                                        <span class="ent-sidebar-card__key">Trạng thái</span>
                                        <span class="ent-passport-status-pill ent-passport-status-pill--sm">
                                            <span class="status-dot"></span>
                                            <?= htmlspecialchars($talent['readiness_summary']['status_label'] ?? $talent['internship_status_label']); ?>
                                        </span>
                                    </div>
                                    <?php $prefField = $talent['readiness_summary']['preferred_field'] ?? $talent['major_field']; ?>
                                    <?php if ($prefField && $prefField !== 'Chưa cập nhật'): ?>
                                    <div class="ent-sidebar-card__row">
                                        <span class="ent-sidebar-card__key">Vị trí mong muốn</span>
                                        <span class="ent-sidebar-card__val"><?= htmlspecialchars($prefField); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($talent['experience_hours'] > 0): ?>
                                    <div class="ent-sidebar-card__row">
                                        <span class="ent-sidebar-card__key">Giờ trải nghiệm</span>
                                        <span class="ent-sidebar-card__val ent-sidebar-card__val--accent"><?= htmlspecialchars($talent['experience_hours']); ?>h</span>
                                    </div>
                                    <?php endif; ?>
                                    <?php $strengths = $talent['readiness_summary']['strengths'] ?? []; ?>
                                    <?php if (!empty($strengths)): ?>
                                    <div class="ent-sidebar-card__strengths">
                                        <span class="ent-sidebar-card__key">Điểm mạnh</span>
                                        <ul class="ent-sidebar-card__strength-list">
                                            <?php foreach ($strengths as $st): ?>
                                                <li><?= htmlspecialchars($st); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- KỸ NĂNG -->
                                <div class="ent-sidebar-card">
                                    <div class="ent-sidebar-card__heading">
                                        <h3 class="ent-sidebar-card__title">Kỹ năng</h3>
                                        <?php if (!empty($talent['detailed_skills'])): ?>
                                            <span class="ent-sidebar-card__count"><?= count($talent['detailed_skills']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($talent['detailed_skills'])): ?>
                                        <div class="ent-skills-inline-list">
                                            <?php foreach ($talent['detailed_skills'] as $sk): ?>
                                                <div class="ent-skill-inline-item">
                                                    <span class="ent-skill-inline-item__name"><?= htmlspecialchars($sk['name']); ?></span>
                                                    <div class="ent-skill-inline-item__meta">
                                                        <span class="ent-skill-level"><?= htmlspecialchars($sk['level']); ?></span>
                                                        <?php if ($sk['verified']): ?>
                                                            <span class="ent-verified-badge" title="Đã xác thực">
                                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                                Xác thực
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="ent-unverified-badge">Tự đánh giá</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="ent-profile-empty-inline">Chưa có kỹ năng được ghi nhận.</p>
                                    <?php endif; ?>
                                </div>

                                <!-- CHỨNG CHỈ -->
                                <?php if (!empty($talent['certificates'])): ?>
                                <div class="ent-sidebar-card">
                                    <h3 class="ent-sidebar-card__title">Chứng chỉ & Thành tích</h3>
                                    <div class="ent-passport-certs-list">
                                        <?php foreach ($talent['certificates'] as $cert): ?>
                                            <div class="ent-passport-cert-row">
                                                <div class="ent-passport-cert-row__icon">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                                                </div>
                                                <div class="ent-passport-cert-row__info">
                                                    <h4 class="cert-name"><?= htmlspecialchars($cert['title'] ?? $cert['name'] ?? 'Chứng chỉ'); ?></h4>
                                                    <span class="cert-issuer"><?= htmlspecialchars($cert['issuingOrganization'] ?? $cert['issuer'] ?? ''); ?><?= !empty($cert['issueDate'] ?? $cert['issue_date']) ? ' &bull; ' . htmlspecialchars($cert['issueDate'] ?? $cert['issue_date']) : ''; ?></span>
                                                </div>
                                                <?php if (!empty($cert['verified']) || (($cert['verificationStatus'] ?? '') === 'verified')): ?>
                                                    <span class="ent-verified-badge">
                                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        Đã minh chứng
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- PRIVACY NOTICE -->
                                <div class="ent-privacy-card ent-sidebar-card ent-sidebar-card--muted">
                                    <div class="ent-privacy-card__icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                    </div>
                                    <p>Thông tin liên hệ cá nhân được ẩn theo tiêu chuẩn bảo mật TalentHub. Yêu cầu liên hệ sẽ chờ sự đồng ý của người học.</p>
                                </div>

                            </aside>

                        </div><!-- /.ent-passport-grid -->

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
                        <p class="ent-skills-modal__subtitle">Gửi đề xuất kết nối thực tập tới ứng viên <?= htmlspecialchars($talent['name']); ?></p>
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

                <div class="ent-skills-modal__header" style="background: #FFFDFB; border-bottom: 1px solid #F0E6DD; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h3 class="ent-skills-modal__title" style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #322014; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-coral)" stroke-width="2.5">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                            <span>Gửi Lời Mời Thực Tập</span>
                        </h3>
                        <p class="ent-skills-modal__subtitle" style="margin: 0.25rem 0 0; font-size: 0.85rem; color: #6B5548;">
                            Mời ứng viên <strong><?= htmlspecialchars($talent['name']); ?></strong> (<?= htmlspecialchars($talent['talent_score']); ?> điểm) vào đội ngũ <?= htmlspecialchars($enterpriseInfo['company_name']); ?>
                        </p>
                    </div>
                    <button type="button" class="ent-skills-modal__close" onclick="closeInviteModal()" style="border: none; background: transparent; font-size: 1.6rem; line-height: 1; cursor: pointer; color: #9E897D; padding: 0.2rem 0.5rem;">&times;</button>
                </div>

                <div style="padding: 1.5rem;">
                    <!-- Candidate Highlight Banner -->
                    <div style="background: #FFF7F2; border: 1px solid #F0E6DD; border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 38px; height: 38px; border-radius: 8px; background: #FFF0EB; color: #FF6B45; border: 1px solid #FFDACB; font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 0.875rem;">
                                <?= htmlspecialchars($talent['avatar_initials']); ?>
                            </div>
                            <div>
                                <div style="font-weight: 700; color: #322014; font-size: 0.95rem;"><?= htmlspecialchars($talent['name']); ?></div>
                                <div style="font-size: 0.75rem; color: #6B5548;"><?= htmlspecialchars($talent['major_field']); ?> • <?= htmlspecialchars($talent['school']); ?></div>
                            </div>
                        </div>
                        <div style="background: #DCFCE7; color: #15803D; font-weight: 800; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.85rem; border: 1px solid #BBF7D0;">
                            <?= htmlspecialchars($talent['talent_score']); ?> điểm
                        </div>
                    </div>

                    <!-- Job Post Selector -->
                    <div style="margin-bottom: 1.25rem;">
                        <label for="invitePostSelect" style="display: block; font-size: 0.875rem; font-weight: 700; color: #322014; margin-bottom: 0.4rem;">
                            Chọn vị trí thực tập đang mở <span style="color: #EF4444;">*</span>
                        </label>
                        <?php if (empty($activePosts)): ?>
                            <div style="background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem;">
                                Doanh nghiệp hiện chưa có tin tuyển thực tập nào đang mở. Vui lòng tạo tin tuyển dụng trước khi gửi lời mời.
                            </div>
                        <?php else: ?>
                            <select id="invitePostSelect" class="typeui-select" style="border: 1.5px solid #F0E6DD; width: 100%;">
                                <?php foreach ($activePosts as $post): 
                                    $isPreferred = (isset($_GET['postId']) && $_GET['postId'] === $post['id'])
                                        || (empty($_GET['postId']) && stripos($post['title'], 'Backend') !== false && stripos($talent['major_field'] ?? '', 'Backend') !== false);
                                ?>
                                    <option value="<?= htmlspecialchars($post['id']); ?>" <?= $isPreferred ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($post['title']); ?> (<?= htmlspecialchars($post['location'] ?? 'Toàn thời gian'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Short Message -->
                    <div style="margin-bottom: 1.25rem;">
                        <label for="inviteMessageInput" style="display: block; font-size: 0.875rem; font-weight: 700; color: #322014; margin-bottom: 0.4rem;">
                            Lời nhắn gửi tới ứng viên:
                        </label>
                        <textarea id="inviteMessageInput"
                                  rows="3"
                                  style="width: 100%; padding: 0.65rem 0.85rem; border: 1.5px solid #F0E6DD; border-radius: 8px; font-size: 0.875rem; color: #322014; resize: vertical;"
                                  placeholder="Ví dụ: Chào bạn <?= htmlspecialchars($talent['name']); ?>, <?= htmlspecialchars($enterpriseInfo['company_name']); ?> rất ấn tượng với hồ sơ năng lực và điểm đánh giá <?= htmlspecialchars($talent['talent_score']); ?> điểm của bạn. Trân trọng mời bạn tham gia thực tập..."></textarea>
                    </div>

                    <!-- Privacy / Notification Tip -->
                    <div style="background: #FFF9F5; border: 1px solid #FFE0D3; border-radius: 8px; padding: 0.75rem 1rem; font-size: 0.8125rem; color: #6B5548; display: flex; align-items: flex-start; gap: 0.5rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                        <span>Hệ thống sẽ lưu lời mời vào danh sách ứng tuyển thực tập và gửi thông báo trực tiếp đến tài khoản sinh viên trên TalentHub.</span>
                    </div>
                </div>

                <div style="background: #FFFDFB; border-top: 1px solid #F0E6DD; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeInviteModal()" style="font-weight: 600;">Hủy</button>
                    <button type="button" class="btn btn-primary" id="confirmSendInviteBtn" onclick="submitInternshipInvitation()" <?= empty($activePosts) ? 'disabled style="opacity:0.6; cursor:not-allowed; font-weight:700; padding:0.5rem 1.25rem;"' : 'style="font-weight: 700; padding: 0.5rem 1.25rem;"'; ?>>
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
            let toast = document.getElementById('ent-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'ent-toast';
                toast.className = 'ent-toast';
                toast.innerHTML = '<div class="ent-toast__content"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg><span class="ent-toast__message"></span></div>';
                document.body.appendChild(toast);
            }
            const msgEl = toast.querySelector('.ent-toast__message');
            if (msgEl) msgEl.textContent = msg;
            toast.classList.add('is-visible');
            setTimeout(() => { toast.classList.remove('is-visible'); }, 3500);
        }

        async function submitInternshipInvitation() {
            const postSelect = document.getElementById('invitePostSelect');
            const msgInput = document.getElementById('inviteMessageInput');
            const btn = document.getElementById('confirmSendInviteBtn');

            if (!postSelect || !postSelect.value) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Cảnh báo',
                        text: 'Vui lòng chọn một vị trí thực tập.',
                        confirmButtonColor: '#059669',
                        confirmButtonText: 'Đóng'
                    });
                } else {
                    alert('Vui lòng chọn một vị trí thực tập.');
                }
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
                const data = await res.json().catch(() => ({}));

                if (res.ok && data.success) {
                    closeInviteModal();
                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Thành công!',
                            text: data.message || 'Đã gửi lời mời thực tập thành công!',
                            confirmButtonColor: '#059669',
                            confirmButtonText: 'Đóng'
                        });
                    } else {
                        alert(data.message || 'Đã gửi lời mời thực tập thành công!');
                    }
                    const mainInviteBtn = document.getElementById('detail-invite-btn');
                    if (mainInviteBtn) {
                        mainInviteBtn.className = 'btn btn-success btn-sm';
                        mainInviteBtn.style.background = '#059669';
                        mainInviteBtn.style.borderColor = '#059669';
                        mainInviteBtn.innerHTML = `
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Đã gửi lời mời</span>
                        `;
                    }
                } else {
                    const errText = data.message || 'Không thể gửi lời mời lúc này.';
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Thông báo',
                            text: errText,
                            confirmButtonColor: '#059669',
                            confirmButtonText: 'Đóng'
                        });
                    } else {
                        alert(errText);
                    }
                }
            } catch (err) {
                console.error(err);
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Lỗi',
                        text: 'Lỗi kết nối tới máy chủ khi gửi lời mời.',
                        confirmButtonColor: '#059669',
                        confirmButtonText: 'Đóng'
                    });
                } else {
                    alert('Lỗi kết nối tới máy chủ khi gửi lời mời.');
                }
            } finally {
                btn.disabled = false;
                btn.textContent = 'Xác nhận gửi lời mời';
            }
        }
    </script>
</body>
</html>
