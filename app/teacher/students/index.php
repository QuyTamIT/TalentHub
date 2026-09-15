<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\PortalGuard;
use TalentHub\Database\Connection;
use TalentHub\Rbac\RoleCodes;

date_default_timezone_set('Asia/Ho_Chi_Minh');

$user = PortalGuard::requireRole(RoleCodes::TEACHER, '/app/teacher/students/index.php');
$session = new SessionManager(array_merge(
    require dirname(__DIR__, 3) . '/config/session.php',
    ['name' => SessionManager::SESSION_TEACHER]
));
$session->start();

function teacher_students_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function teacher_student_initials(string $fullName): string
{
    $cleanName = preg_replace('/^(Thầy|Cô|Gv\.|GV|Ths\.|TS\.|ThS\.)\s+/iu', '', $fullName);
    $cleanName = trim((string) $cleanName) ?: $fullName;
    $parts = preg_split('/\s+/u', trim($cleanName)) ?: [];
    if (count($parts) === 0 || empty($parts[0])) {
        return 'HV';
    }
    if (count($parts) === 1) {
        return mb_strtoupper(mb_substr($parts[0], 0, min(2, mb_strlen($parts[0]))));
    }
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
}

/**
 * Xác định Năng khiếu chính từ dữ liệu kỹ năng/năng lực thực tế của học viên trong khu vực Student → Hồ sơ năng lực.
 * Tuyệt đối không suy đoán từ điểm đánh giá giáo viên (Chuyên môn, Sáng tạo...), không lấy từ tên hoạt động hay mock.
 */
function teacher_resolve_student_primary_skill(PDO $pdo, string $studentId): ?string
{
    // 1. Kỹ năng thực tế từ student_skills trong Hồ sơ năng lực Student
    try {
        $stmt = $pdo->prepare("
            SELECT s.name
            FROM student_skills ss
            JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = :sid AND s.status = 'active'
            ORDER BY (ss.verificationStatus = 'verified') DESC, ss.levelScore DESC
            LIMIT 1
        ");
        $stmt->execute(['sid' => $studentId]);
        $name = $stmt->fetchColumn();
        if (!empty($name)) {
            return (string) $name;
        }
    } catch (Throwable) {}

    // 2. Kỹ năng dự án / thực tập từ learner_portfolio_skills
    try {
        $stmt = $pdo->prepare("
            SELECT s.name
            FROM learner_portfolio_skills ps
            JOIN skills s ON s.id = ps.skillId AND s.status = 'active'
            LEFT JOIN project_submissions r ON r.id = ps.reportId AND ps.kind = 'project'
            LEFT JOIN learner_internship_reports ir ON ir.id = ps.reportId AND ps.kind = 'internship'
            WHERE (r.studentId = :sid1 OR ir.studentId = :sid2)
            LIMIT 1
        ");
        $stmt->execute(['sid1' => $studentId, 'sid2' => $studentId]);
        $name = $stmt->fetchColumn();
        if (!empty($name)) {
            return (string) $name;
        }
    } catch (Throwable) {}

    // 3. Kỹ năng thực tế từ Hồ sơ năng lực (talent_map trong active roadmap của học viên)
    try {
        $stmt = $pdo->prepare("
            SELECT insightsJson
            FROM learner_ai_roadmaps
            WHERE studentId = :sid AND status = 'active'
            ORDER BY versionNumber DESC, createdAt DESC
            LIMIT 1
        ");
        $stmt->execute(['sid' => $studentId]);
        $insightsJson = $stmt->fetchColumn();
        if (!empty($insightsJson)) {
            $decoded = json_decode((string) $insightsJson, true);
            $talentMap = $decoded['__ai_extended']['talent_map'] ?? ($decoded['talent_map'] ?? []);
            if (is_array($talentMap) && !empty($talentMap)) {
                usort($talentMap, static fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
                $top = $talentMap[0]['field'] ?? ($talentMap[0]['name'] ?? null);
                if (!empty($top)) {
                    return (string) $top;
                }
            }
        }
    } catch (Throwable) {}

    return null;
}

$error = null;
$search = trim((string) ($_GET['search'] ?? ''));
$sort = trim((string) ($_GET['sort'] ?? ''));
$dir = strtolower(trim((string) ($_GET['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';

$teacherInfo = [
    'full_name' => $user['fullName'] ?? 'Giáo viên TalentHub',
    'role_label' => 'Giáo viên / Hướng dẫn viên',
    'school_name' => '',
    'avatar_initials' => 'GV',
    'notification_count' => 0,
];

$rows = [];
$totalStudents = 0;

try {
    $config = require dirname(__DIR__, 3) . '/config/database.php';
    $pdo = (new Connection($config))->connect();

    // 1. Resolve Teacher Profile & School
    $stmtTeacher = $pdo->prepare("
        SELECT tp.id, tp.userId, tp.schoolId, tp.isSchoolAdmin, s.name as schoolName, u.fullName
        FROM teacher_profiles tp
        LEFT JOIN schools s ON s.id = tp.schoolId
        LEFT JOIN users u ON u.id = tp.userId
        WHERE tp.userId = :uid
        LIMIT 1
    ");
    $stmtTeacher->execute(['uid' => (string) $user['id']]);
    $teacher = $stmtTeacher->fetch(PDO::FETCH_ASSOC) ?: [];

    $teacherId = (string) ($teacher['id'] ?? '');
    $schoolId = (string) ($teacher['schoolId'] ?? '');
    $schoolName = (string) ($teacher['schoolName'] ?? '');
    $resolvedName = trim((string) ($user['fullName'] ?? ($teacher['fullName'] ?? 'Giáo viên TalentHub')));

    $teacherInfo['full_name'] = $resolvedName;
    $teacherInfo['school_name'] = $schoolName;
    $teacherInfo['avatar_initials'] = teacher_student_initials($resolvedName);
    if (!empty($teacher['isSchoolAdmin'])) {
        $teacherInfo['role_label'] = 'Giáo viên / Quản trị trường';
    }

    // 2. Build Scope Condition: students within teacher's scope
    $scopeClauses = [];
    $queryParams = [];

    if ($schoolId !== '') {
        $scopeClauses[] = "c.schoolId = :schoolId";
        $queryParams['schoolId'] = $schoolId;
    }
    if ($teacherId !== '') {
        $scopeClauses[] = "sp.id IN (
            SELECT ar.studentId 
            FROM activity_registrations ar 
            JOIN activities a ON a.id = ar.activityId 
            WHERE a.createdByTeacherId = :actTeacherId
        )";
        $queryParams['actTeacherId'] = $teacherId;

        $scopeClauses[] = "sp.classId IN (
            SELECT tca.classId 
            FROM teacher_class_assignments tca 
            WHERE tca.teacherId = :tcaTeacherId AND tca.status = 'active'
        )";
        $queryParams['tcaTeacherId'] = $teacherId;
    }

    $whereScope = !empty($scopeClauses) ? '(' . implode(' OR ', $scopeClauses) . ')' : '1=0';

    // 3. Count total students in scope (before search filtering)
    $sqlCount = "
        SELECT COUNT(DISTINCT sp.id)
        FROM student_profiles sp
        JOIN users u ON u.id = sp.userId
        LEFT JOIN classes c ON c.id = sp.classId
        WHERE sp.studyStatus = 'active'
          AND u.status = 'active'
          AND {$whereScope}
    ";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($queryParams);
    $totalStudents = (int) $stmtCount->fetchColumn();

    $metrics = [
        'managed_classes' => $schoolId !== '' ? 1 : 0,
        'total_students' => $totalStudents,
    ];

    // 4. Query students with 6 required columns
    $whereFilter = $whereScope;
    if ($search !== '') {
        $whereFilter .= " AND (u.fullName LIKE :qName OR c.name LIKE :qClass)";
        $queryParams['qName'] = '%' . $search . '%';
        $queryParams['qClass'] = '%' . $search . '%';
    }

    $orderBy = "ORDER BY u.fullName ASC";
    if ($sort === 'talentScore') {
        $orderBy = $dir === 'asc'
            ? "ORDER BY (sp.talentScore IS NULL) ASC, sp.talentScore ASC, u.fullName ASC"
            : "ORDER BY (sp.talentScore IS NULL) ASC, sp.talentScore DESC, u.fullName ASC";
    }

    $sql = "
        SELECT 
            sp.id AS studentId,
            u.fullName,
            u.email,
            c.name AS className,
            sp.talentScore,
            COALESCE(
                (SELECT SUM(el.hours) 
                 FROM experience_logs el 
                 WHERE el.studentId = sp.id AND el.status = 'confirmed'),
                0
            ) AS experienceHours,
            (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'name', b.name,
                        'code', b.code,
                        'iconUrl', COALESCE(b.iconUrl, '')
                    )
                )
                FROM student_badges sb
                JOIN badges b ON b.id = sb.badgeId
                WHERE sb.studentId = sp.id
            ) AS badgesJson
        FROM student_profiles sp
        JOIN users u ON u.id = sp.userId
        LEFT JOIN classes c ON c.id = sp.classId
        WHERE sp.studyStatus = 'active'
          AND u.status = 'active'
          AND {$whereFilter}
        {$orderBy}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format rows for display
    foreach ($rawRows as $r) {
        $badges = [];
        if (!empty($r['badgesJson'])) {
            $decoded = json_decode((string) $r['badgesJson'], true);
            if (is_array($decoded)) {
                $badges = $decoded;
            }
        }

        // Năng khiếu chính từ Hồ sơ năng lực Student
        $primarySkill = teacher_resolve_student_primary_skill($pdo, (string) $r['studentId']);

        $rows[] = [
            'studentId' => $r['studentId'],
            'fullName' => $r['fullName'],
            'email' => $r['email'],
            'avatarInitials' => teacher_student_initials((string) $r['fullName']),
            'className' => !empty($r['className']) ? $r['className'] : null,
            'talentScore' => $r['talentScore'] !== null ? (float) $r['talentScore'] : null,
            'experienceHours' => (float) ($r['experienceHours'] ?? 0),
            'badges' => $badges,
            'primarySkill' => $primarySkill,
        ];
    }
} catch (Throwable $e) {
    error_log('Teacher students error: ' . $e->getMessage());
    $error = 'Không thể tải danh sách học viên lúc này. Vui lòng thử lại sau.';
}

$pageTitle = 'Học viên của tôi';
$currentRoute = 'students';
$todayLabel = date('d/m/Y');

$teacherSidebarHomeHref = '/index.php';
$teacherSidebarRoleHref = '/role-selection.php';
$sidebarNav = [
    ['title' => 'Tổng quan', 'route' => 'index.php', 'href' => '/app/teacher/index.php', 'icon' => 'grid', 'active' => false],
    ['title' => 'Sân chơi của tôi', 'route' => 'playgrounds', 'icon' => 'trophy', 'active' => false],
    ['title' => 'Chấm điểm', 'route' => 'assessments', 'icon' => 'clipboard-check', 'active' => false],
    ['title' => 'Học viên', 'route' => 'students', 'href' => '/app/teacher/students/index.php', 'icon' => 'users', 'active' => true],
];

// Helper URL for sorting talentScore
$nextDir = ($sort === 'talentScore' && $dir === 'desc') ? 'asc' : 'desc';
$sortUrlParams = [];
if ($search !== '') {
    $sortUrlParams['search'] = $search;
}
$sortUrlParams['sort'] = 'talentScore';
$sortUrlParams['dir'] = $nextDir;
$talentScoreSortUrl = './index.php?' . http_build_query($sortUrlParams);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Danh sách học viên đang theo dõi thuộc phạm vi quản lý của giáo viên trên TalentHub.">
    <title>Học viên của tôi | TalentHub</title>

    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/global.css">
    <link rel="stylesheet" href="../../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../../assets/css/polish.css">
    <link rel="stylesheet" href="../../../assets/css/teacher.css">
    <link rel="stylesheet" href="../../../assets/css/typeui-selects.css">
</head>
<body class="teacher-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
    <div class="teacher-layout">
        <?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>

        <div class="teacher-main-wrapper">
            <?php require dirname(__DIR__) . '/includes/header.php'; ?>

            <main class="teacher-body" id="main-content">
                <div class="teacher-container">
                    <!-- Section Header -->
                    <section class="teacher-welcome">
                        <div class="teacher-welcome__content">
                            <div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; flex-wrap: wrap;">
                                    <span class="teacher-chip teacher-chip--primary">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px; vertical-align: -2px;">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="9" cy="7" r="4"></circle>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                        </svg>
                                        <?= number_format($totalStudents); ?> học viên đang theo dõi
                                    </span>
                                    <?php if (!empty($teacherInfo['school_name'])): ?>
                                        <span class="teacher-chip"><?= teacher_students_escape($teacherInfo['school_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <h2 class="teacher-welcome__title">Học viên của tôi</h2>
                                <p class="teacher-welcome__description">
                                    Quản lý và theo dõi học viên tham gia các sân chơi, hoạt động và lớp học do bạn phụ trách.
                                </p>
                            </div>
                            <div class="teacher-welcome__meta">
                                <span class="teacher-chip"><?= teacher_students_escape($teacherInfo['role_label']); ?></span>
                                <span class="teacher-chip"><?= teacher_students_escape($todayLabel); ?></span>
                            </div>
                        </div>
                    </section>

                    <!-- Main Content Panel: Search & 6-column Table -->
                    <section class="teacher-section-box teacher-students-panel">
                        <div class="teacher-section-box__header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem;">
                            <div>
                                <h3 class="teacher-section-box__title" style="margin: 0; font-size: 1.125rem;">Danh sách học viên</h3>
                                <p class="teacher-section-box__subtitle" style="margin: 0.25rem 0 0; color: var(--text-secondary); font-size: 0.875rem;">
                                    <?php if ($search !== ''): ?>
                                        Tìm thấy <?= number_format(count($rows)); ?> kết quả cho từ khóa "<strong><?= teacher_students_escape($search); ?></strong>"
                                    <?php else: ?>
                                        Hiển thị <?= number_format(count($rows)); ?> học viên trong phạm vi theo dõi
                                    <?php endif; ?>
                                </p>
                            </div>

                            <!-- Single Search Form: "Tìm theo tên, lớp..." -->
                            <form method="get" action="./index.php" style="display: flex; align-items: center; gap: 0.5rem; max-width: 360px; width: 100%;">
                                <div style="position: relative; flex: 1;">
                                    <span style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; display: flex; align-items: center;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                        </svg>
                                    </span>
                                    <input 
                                        type="search" 
                                        name="search" 
                                        value="<?= teacher_students_escape($search); ?>" 
                                        placeholder="Tìm theo tên, lớp..." 
                                        aria-label="Tìm kiếm theo tên hoặc lớp"
                                        style="width: 100%; height: 2.375rem; padding: 0.375rem 0.75rem 0.375rem 2.25rem; border: 1px solid var(--border); border-radius: var(--radius-md, 8px); background-color: var(--surface); color: var(--text-primary); font-size: 0.875rem;"
                                    >
                                </div>
                                <?php if ($sort !== ''): ?>
                                    <input type="hidden" name="sort" value="<?= teacher_students_escape($sort); ?>">
                                    <input type="hidden" name="dir" value="<?= teacher_students_escape($dir); ?>">
                                <?php endif; ?>
                                <button type="submit" class="btn btn-sm btn-primary" style="height: 2.375rem; padding: 0 0.875rem; white-space: nowrap; border-radius: var(--radius-md, 8px);">
                                    Tìm
                                </button>
                                <?php if ($search !== ''): ?>
                                    <a href="./index.php<?= $sort !== '' ? '?sort=' . urlencode($sort) . '&dir=' . urlencode($dir) : ''; ?>" class="btn btn-sm btn-outline" style="height: 2.375rem; padding: 0 0.75rem; white-space: nowrap; border-radius: var(--radius-md, 8px);">
                                        Xoá
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <?php if ($error !== null): ?>
                            <div class="teacher-students-alert" role="alert"><?= teacher_students_escape($error); ?></div>
                        <?php elseif ($rows === []): ?>
                            <div class="teacher-empty-state" style="padding: 3rem 1.5rem; text-align: center;">
                                <div class="teacher-empty-state__icon" aria-hidden="true" style="margin: 0 auto 1rem; width: 3.25rem; height: 3.25rem; border-radius: 50%; background: var(--background); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                </div>
                                <h4 class="teacher-empty-state__title" style="font-size: 1.125rem; font-weight: 600; color: var(--text-primary); margin: 0 0 0.5rem;">
                                    <?= $search !== '' ? 'Không tìm thấy học viên phù hợp' : 'Chưa có học viên trong phạm vi theo dõi'; ?>
                                </h4>
                                <p class="teacher-empty-state__desc" style="color: var(--text-secondary); font-size: 0.875rem; margin: 0 0 1.25rem;">
                                    <?= $search !== '' 
                                        ? 'Không có học viên nào khớp với từ khóa "' . teacher_students_escape($search) . '". Bạn có thể thử tìm kiếm với từ khóa khác.' 
                                        : 'Dữ liệu học viên sẽ xuất hiện khi có học viên trong lớp hoặc tham gia hoạt động do bạn phụ trách.'; ?>
                                </p>
                                <?php if ($search !== ''): ?>
                                    <a href="./index.php" class="btn btn-sm btn-outline">Xem tất cả học viên</a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Exactly 6 Columns Table -->
                            <div class="teacher-students-table-wrap">
                                <table class="teacher-students-table">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 220px;">Học viên</th>
                                            <th style="min-width: 100px;">Lớp</th>
                                            <th style="min-width: 140px;">
                                                <a href="<?= teacher_students_escape($talentScoreSortUrl); ?>" 
                                                   style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;"
                                                   title="Bấm để sắp xếp theo điểm năng lực">
                                                    <span>Điểm năng lực</span>
                                                    <span style="font-size: 0.75rem; opacity: <?= $sort === 'talentScore' ? '1' : '0.4'; ?>;">
                                                        <?= $sort === 'talentScore' ? ($dir === 'asc' ? '▲' : '▼') : '↕'; ?>
                                                    </span>
                                                </a>
                                            </th>
                                            <th style="min-width: 130px;">Giờ trải nghiệm</th>
                                            <th style="min-width: 120px;">Huy hiệu</th>
                                            <th style="min-width: 180px;">Năng khiếu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $row): ?>
                                            <tr>
                                                <!-- 1. Học viên -->
                                                <td data-label="Học viên">
                                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                        <div style="width: 2.375rem; height: 2.375rem; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, #ea580c 100%); color: #fff; font-weight: 600; display: flex; align-items: center; justify-content: center; font-size: 0.8125rem; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                            <?= teacher_students_escape($row['avatarInitials']); ?>
                                                        </div>
                                                        <div>
                                                            <strong style="color: var(--text-primary); font-size: 0.9375rem; display: block; line-height: 1.3;">
                                                                <?= teacher_students_escape($row['fullName']); ?>
                                                            </strong>
                                                            <span style="color: var(--text-secondary); font-size: 0.8125rem; display: block; margin-top: 0.125rem;">
                                                                <?= teacher_students_escape($row['email']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- 2. Lớp -->
                                                <td data-label="Lớp">
                                                    <?php if (!empty($row['className'])): ?>
                                                        <span style="display: inline-flex; align-items: center; font-weight: 600; font-size: 0.875rem; color: var(--text-primary); background: var(--background); padding: 0.25rem 0.625rem; border-radius: var(--radius-sm, 6px); border: 1px solid var(--border);">
                                                            <?= teacher_students_escape($row['className']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="teacher-students-empty" style="color: var(--text-muted); font-size: 0.875rem;">Chưa có</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- 3. Điểm năng lực -->
                                                <td data-label="Điểm năng lực">
                                                    <?php if ($row['talentScore'] !== null): ?>
                                                        <div style="display: inline-flex; align-items: baseline; gap: 0.25rem;">
                                                            <span style="font-weight: 700; font-size: 1.0625rem; color: var(--color-success, #16a34a);">
                                                                <?= number_format((float) $row['talentScore'], 0); ?>
                                                            </span>
                                                            <span style="font-size: 0.75rem; color: var(--text-muted);">/ 100</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="teacher-students-empty" style="color: var(--text-muted); font-size: 0.875rem;">Chưa có</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- 4. Giờ trải nghiệm -->
                                                <td data-label="Giờ trải nghiệm">
                                                    <?php if ((float) $row['experienceHours'] > 0): ?>
                                                        <span style="font-weight: 600; font-size: 0.875rem; color: var(--text-primary);">
                                                            <?= number_format((float) $row['experienceHours'], 1); ?> giờ
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="teacher-students-empty" style="color: var(--text-muted); font-size: 0.875rem;">Chưa có</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- 5. Huy hiệu: CHỈ HIỂN THỊ LOGO/ICON CỦA HUY HIỆU, KHÔNG HIỂN THỊ TÊN TEXT DÀI -->
                                                <td data-label="Huy hiệu">
                                                    <?php if (!empty($row['badges'])): ?>
                                                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                                            <?php foreach ($row['badges'] as $b): ?>
                                                                <?php if (!empty($b['iconUrl'])): ?>
                                                                    <img src="<?= teacher_students_escape($b['iconUrl']); ?>" 
                                                                         alt="<?= teacher_students_escape($b['name']); ?>" 
                                                                         title="<?= teacher_students_escape($b['name']); ?>" 
                                                                         style="width: 2.25rem; height: 2.25rem; object-fit: contain; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                                <?php else: ?>
                                                                    <span class="teacher-badge-emblem" 
                                                                          title="<?= teacher_students_escape($b['name']); ?>" 
                                                                          aria-label="<?= teacher_students_escape($b['name']); ?>" 
                                                                          style="display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 50%; background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%); color: #D97706; border: 1.5px solid #F59E0B; box-shadow: 0 2px 4px rgba(217, 119, 6, 0.15); cursor: default;">
                                                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1">
                                                                            <circle cx="12" cy="8" r="6"></circle>
                                                                            <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11" fill="#D97706"></path>
                                                                        </svg>
                                                                    </span>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="teacher-students-empty" style="color: var(--text-muted); font-size: 0.875rem;">Chưa có</span>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- 6. Năng khiếu: CHỈ HIỂN THỊ NĂNG KHIẾU CHÍNH TỪ HỒ SƠ NĂNG LỰC STUDENT -->
                                                <td data-label="Năng khiếu">
                                                    <?php if (!empty($row['primarySkill'])): ?>
                                                        <span class="teacher-status-pill teacher-status-pill--info" style="font-size: 0.8125rem; padding: 0.3rem 0.65rem; display: inline-flex; align-items: center; gap: 0.35rem; font-weight: 500;">
                                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                                            </svg>
                                                            <?= teacher_students_escape($row['primarySkill']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="teacher-students-empty" style="color: var(--text-muted); font-size: 0.875rem;">Chưa có</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script src="../../../assets/js/teacher.js"></script>
</body>
</html>
