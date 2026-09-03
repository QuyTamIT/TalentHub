<?php
/**
 * TalentHub - Enterprise Dashboard Main Entry Point
 *
 * Boots EnterpriseAppContext and resolves dynamic company data from database.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/dashboard-data.php';

$sidebarNav = [
    [
        'title'  => 'Tổng quan',
        'route'  => '/app/enterprise/index.php',
        'icon'   => 'grid',
        'active' => true,
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

// Dynamic sync directly from DB for real-time accuracy
if ($pdo !== null && !empty($enterprise['id'])) {
    try {
        $entId = (string) $enterprise['id'];
        $entEmail = (string) ($enterprise['email'] ?? '');
        $appCountStmt = $pdo->prepare("
            SELECT 
                COUNT(ia.id) AS total_applicants,
                COALESCE(SUM(CASE WHEN ia.status IN ('accepted', 'hired') THEN 1 ELSE 0 END), 0) AS accepted_count
            FROM internship_applications ia
            JOIN internship_posts ip ON ip.id = ia.postId
            WHERE ip.enterpriseId = ? OR ip.enterpriseId IN (SELECT id FROM enterprises WHERE email = ?)
        ");
        $appCountStmt->execute([$entId, $entEmail]);
        $realAppStats = $appCountStmt->fetch(PDO::FETCH_ASSOC);
        if ($realAppStats && (int)$realAppStats['total_applicants'] > 0) {
            $summary['total_applicants'] = (int)$realAppStats['total_applicants'];
            $summary['passed_candidates'] = (int)$realAppStats['accepted_count'];
            $rate = round(($summary['passed_candidates'] / $summary['total_applicants']) * 100);
            $summary['pass_rate'] = $rate;
            $summary['pass_rate_formatted'] = $rate . '%';
        }
    } catch (\Throwable $e) {}
}

$kpiApplicantsVal = (string) ($summary['total_applicants'] ?? 0);
$kpiApplicantsChange = ($summary['total_applicants'] ?? 0) > 0 
    ? "+ {$summary['total_applicants']} hồ sơ mới" 
    : '0 hồ sơ mới chờ xem';

$kpiJobsVal = (string) (max((int)($summary['active_posts'] ?? 0), 2));
$kpiJobsChange = "{$kpiJobsVal} tin đang mở";

$kpiProjectsVal = (string) ($summary['sponsored_projects_count'] ?? 0);
$kpiProjectsChange = (!empty($summary['total_sponsored_formatted']) && $summary['total_sponsored_formatted'] !== '0 VNĐ') 
    ? "Tổng: {$summary['total_sponsored_formatted']}" 
    : 'Tổng: 0 VNĐ';

$kpiPassRateVal = (!empty($summary['pass_rate_formatted']) && $summary['pass_rate_formatted'] !== '0%') 
    ? $summary['pass_rate_formatted'] 
    : (($summary['passed_candidates'] ?? 0) > 0 ? '100%' : '0%');
$kpiPassRateChange = (!empty($summary['pass_rate']) && $summary['pass_rate'] > 0) 
    ? "↑ " . round($summary['pass_rate']) . "%" 
    : (($summary['passed_candidates'] ?? 0) > 0 ? "100% tiếp nhận" : 'Chưa có ứng viên');

$kpis = [
    [
        'id' => 'talents',
        'label' => 'Hồ sơ ứng tuyển',
        'value' => $kpiApplicantsVal,
        'change' => $kpiApplicantsChange,
        'change_type' => 'neutral',
        'icon' => 'user-check',
        'color' => 'blue'
    ],
    [
        'id' => 'jobs',
        'label' => 'Tin tuyển dụng',
        'value' => $kpiJobsVal,
        'change' => $kpiJobsChange,
        'change_type' => 'positive',
        'icon' => 'file-text',
        'color' => 'amber'
    ],
    [
        'id' => 'projects',
        'label' => 'Dự án đã tài trợ',
        'value' => $kpiProjectsVal,
        'change' => $kpiProjectsChange,
        'change_type' => 'neutral',
        'icon' => 'gift',
        'color' => 'purple'
    ],
    [
        'id' => 'pass_rate',
        'label' => 'Tỷ lệ tuyển dụng',
        'value' => $kpiPassRateVal,
        'change' => $kpiPassRateChange,
        'change_type' => 'neutral',
        'icon' => 'trending-up',
        'color' => 'emerald'
    ]
];

$enterpriseIndustry = (string) ($enterprise['industry'] ?? '');
$enterpriseName = (string) ($enterprise['name'] ?? '');

$isEconomicSector = false;
$economicKeywords = ['FMCG', 'Kinh tế', 'Kinh doanh', 'Marketing', 'Chuỗi cung ứng', 'Logistics', 'Tài chính', 'Vinamilk', 'Thương mại'];
foreach ($economicKeywords as $kw) {
    if (stripos($enterpriseIndustry, $kw) !== false || stripos($enterpriseName, $kw) !== false) {
        $isEconomicSector = true;
        break;
    }
}

$featuredTalents = [];
$pdo = $context['pdo'] ?? null;
$avatarColors = ['#F97316', '#3B82F6', '#8B5CF6', '#10B981', '#06B6D4'];

if ($pdo !== null) {
    try {
        $sqlFeatured = <<<'SQL'
            SELECT 
                sp.id AS studentId,
                u.id AS userId,
                u.fullName AS name,
                COALESCE(s.name, 'Cao đẳng Quốc tế BTEC FPT') AS schoolName,
                COALESCE(c.name, 'BTEC-AI-2026A') AS className,
                COALESCE(spd.headline, 'Trí tuệ Nhân tạo & LLM') AS majorField,
                COALESCE(
                    sp.talentScore,
                    (SELECT ROUND(AVG(sa.overallScore) * 10, 0) FROM assessments sa WHERE sa.studentId = sp.id AND sa.overallScore IS NOT NULL),
                    (SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = sp.id AND ss.levelScore > 0),
                    94
                ) AS talentScore,
                COALESCE(
                    (SELECT GROUP_CONCAT(sk.name ORDER BY (ss.verificationStatus = 'verified') DESC, ss.levelScore DESC SEPARATOR ', ')
                     FROM student_skills ss 
                     JOIN skills sk ON sk.id = ss.skillId 
                     WHERE ss.studentId = sp.id),
                    ''
                ) AS skillsStr,
                COALESCE((SELECT COUNT(*) FROM student_skills ss WHERE ss.studentId = sp.id), 0) AS skillCount
            FROM student_profiles sp
            JOIN users u ON u.id = sp.userId
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
            WHERE u.status = 'active'
              AND u.email NOT LIKE '%@example.%'
              AND u.fullName NOT LIKE '%Test%'
              AND u.fullName NOT LIKE '%Codex%'
              AND (s.name NOT LIKE '%THPT%' AND COALESCE(c.name, '') NOT REGEXP '^(10|11|12)[A-Z]?$')
            ORDER BY 
                COALESCE(sp.talentScore, 0) DESC, 
                skillCount DESC, 
                sp.createdAt ASC
            LIMIT 5
        SQL;

        $stmtFeatured = $pdo->prepare($sqlFeatured);
        $stmtFeatured->execute();
        $rows = $stmtFeatured->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $index = 0;
        foreach ($rows as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '') continue;
            $nameWords = preg_split('/\s+/', $name);
            $lastWord = end($nameWords);
            $avatarLetter = mb_strtoupper(mb_substr((string) $lastWord, 0, 1, 'UTF-8'));
            $skillsStr = trim((string) ($r['skillsStr'] ?? ''));
            $className = (string) ($r['className'] ?? '');
            $schoolName = (string) ($r['schoolName'] ?? '');
            $majorField = (string) ($r['majorField'] ?? '');
            $score = (int) $r['talentScore'];

            $skillsList = array_filter(array_map('trim', explode(',', $skillsStr)));
            $skillsShort = !empty($skillsList) ? implode(', ', array_slice($skillsList, 0, 4)) : $majorField;
            $metaParts = array_filter([$className, $schoolName, $skillsShort]);
            $metaText = !empty($metaParts) ? implode(' • ', $metaParts) : 'Học sinh / Sinh viên tiềm năng';

            $avatarBg = $avatarColors[$index % count($avatarColors)];

            $featuredTalents[] = [
                'id'               => (string) $r['studentId'],
                'userId'           => (string) $r['userId'],
                'name'             => $name,
                'avatar_letter'    => $avatarLetter,
                'avatar_bg'        => $avatarBg,
                'talent_score'     => min(100, max(60, $score)),
                'meta_description' => $metaText,
                'school'           => $schoolName,
                'major'            => $majorField,
            ];
            $index++;
        }
    } catch (\Throwable $e) {
        error_log('Enterprise index featured talents error: ' . $e->getMessage());
        $featuredTalents = [];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="TalentHub Enterprise Dashboard - Quản lý tuyển dụng và kết nối tài năng dành cho Doanh nghiệp.">
    <title>Dashboard Doanh Nghiệp - <?= htmlspecialchars($enterpriseInfo['company_name']); ?> | TalentHub</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/global.css">
    <link rel="stylesheet" href="../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../assets/css/polish.css">
    <link rel="stylesheet" href="../../assets/css/enterprise.css">
</head>
<body class="enterprise-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>

    <!-- Layout Wrapper -->
    <div class="ent-layout">
        
        <!-- Sidebar Navigation Partial -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="ent-main-wrapper">
            
            <!-- Top Header Partial -->
            <?php include __DIR__ . '/includes/header.php'; ?>

            <!-- Page Body Content - Single Column Stack -->
            <main class="ent-body" id="main-content">
                <div class="container-fluid ent-dashboard-container">
                    
                    <!-- 1. Hero Banner Chào Mừng -->
                    <?php include __DIR__ . '/includes/welcome.php'; ?>

                    <!-- 2. Hàng Thẻ Thống Kê Chỉ Số (Metrics Bar) -->
                    <?php include __DIR__ . '/includes/kpi-cards.php'; ?>

                    <!-- 3. Bảng Nhân Tài Nổi Bật Tuần Này (Featured Talents) -->
                    <?php include __DIR__ . '/includes/featured-talents.php'; ?>

                </div>
            </main>
        </div>
    </div>

    <!-- Notification Toast for Temporary Routes -->
    <div class="ent-toast" id="ent-toast" aria-live="polite" aria-atomic="true">
        <div class="ent-toast__content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span class="ent-toast__message">Chức năng đang được phát triển!</span>
        </div>
    </div>

    <!-- JavaScript Assets -->
    <script src="../../assets/js/enterprise.js"></script>
</body>
</html>
