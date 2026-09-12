<?php
/**
 * TalentHub Enterprise - Project Sponsorships ("Tài trợ dự án") Module
 * 
 * Redesigned Innovation Funding Hub & Crowdfunding CSR Dashboard:
 * - Part 1: Clean Impact Header & Mini-Bar Funding Fund Summary
 * - Part 2: Streamlined 1-Row Search & Category Filter Pills Bar
 * - Part 3: Rich Project Showcase Grid (340px minmax, squared cards, progress bars)
 * - Part 4: Interactive Project Detail & Sponsorship Commitment Modals
 */

require_once dirname(__DIR__, 3) . '/bin/bootstrap.php';
require_once dirname(__DIR__, 3) . '/src/Bootstrap/EnterpriseAppContext.php';
require_once __DIR__ . '/../includes/sponsorships-data.php';

use TalentHub\Bootstrap\EnterpriseAppContext;

$context = (new EnterpriseAppContext())->boot();
$user       = $context['user'];
$enterprise = $context['enterprise'];
$workflowService = $context['workflows'];

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

$pageTitle = 'Tài trợ dự án';
$currentRoute = '/app/enterprise/sponsorships/';

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
        'active' => false,
    ],
    [
        'title'  => 'Tài trợ dự án',
        'route'  => '/app/enterprise/sponsorships/',
        'icon'   => 'award',
        'active' => true,
    ],
    [
        'title'  => 'Hồ sơ doanh nghiệp',
        'route'  => '/app/enterprise/profile.php',
        'icon'   => 'building',
        'active' => false,
    ],
];

// Fetch real database projects and sponsorships
$db = $context['pdo'];
$sql = "
    SELECT p.*, 
           s.name AS schoolName,
           s.level AS schoolLevel,
           COALESCE(ps_sub.total_sponsored, 0) as total_sponsored,
           COALESCE(pm_sub.member_count, 0) as member_count
    FROM projects p
    LEFT JOIN schools s ON p.schoolId = s.id
    LEFT JOIN (
        SELECT projectId, SUM(amount) as total_sponsored
        FROM project_sponsorships
        WHERE status = 'paid'
        GROUP BY projectId
    ) ps_sub ON p.id = ps_sub.projectId
    LEFT JOIN (
        SELECT projectId, COUNT(DISTINCT studentId) as member_count
        FROM project_members
        GROUP BY projectId
    ) pm_sub ON p.id = pm_sub.projectId
";
$stmt = $db->prepare($sql);
$stmt->execute();
$dbProjects = $stmt->fetchAll(\PDO::FETCH_ASSOC);

$projectIds = array_column($dbProjects, 'id');
$allMembers = [];
if (!empty($projectIds)) {
    $inStr = implode(',', array_fill(0, count($projectIds), '?'));
    $sqlMembers = "
        SELECT pm.projectId, u.fullName, u.email, pm.role
        FROM project_members pm
        INNER JOIN student_profiles sp ON pm.studentId = sp.id
        INNER JOIN users u ON sp.userId = u.id
        WHERE pm.projectId IN ($inStr)
    ";
    $stmtMembers = $db->prepare($sqlMembers);
    $stmtMembers->execute($projectIds);
    foreach ($stmtMembers->fetchAll(\PDO::FETCH_ASSOC) as $m) {
        $allMembers[$m['projectId']][] = $m;
    }
}

// Fetch mentor teachers for all projects that have mentorTeacherId
$mentorTeacherIds = array_filter(array_unique(array_column($dbProjects, 'mentorTeacherId')));
$allMentors = [];
if (!empty($mentorTeacherIds)) {
    $inMentor = implode(',', array_fill(0, count($mentorTeacherIds), '?'));
    $sqlMentors = "
        SELECT tp.id AS teacherProfileId, u.fullName, tp.specialization
        FROM teacher_profiles tp
        INNER JOIN users u ON tp.userId = u.id
        WHERE tp.id IN ($inMentor)
    ";
    $stmtMentors = $db->prepare($sqlMentors);
    $stmtMentors->execute(array_values($mentorTeacherIds));
    foreach ($stmtMentors->fetchAll(\PDO::FETCH_ASSOC) as $mt) {
        $allMentors[$mt['teacherProfileId']] = $mt;
    }
}

// Map internal role values to user-friendly Vietnamese labels
$roleLabels = [
    'leader' => 'Trưởng nhóm',
    'member' => 'Thành viên',
    'contributor' => 'Cộng tác viên',
    'reviewer' => 'Phản biện',
];
function translateRole(string $role, array $roleLabels): string {
    return $roleLabels[$role] ?? $role;
}

$dbSponsorships = $workflowService->sponsorships((string) $user['id']);

$projectDetails = [];

$projects = [];
foreach ($dbProjects as $p) {
    $pId = (string) $p['id'];
    $raised = (float) ($p['total_sponsored'] ?? 0);
    $target = (float) ($p['fundingGoal'] ?? 0);
    $pct = $target > 0 ? (int) min(100, round(($raised / $target) * 100)) : 0;
    $cat = (string) ($p['category'] ?? 'Công nghệ & Đổi mới sáng tạo');
    $schName = !empty($p['schoolName']) ? (string) $p['schoolName'] : 'Chưa liên kết trường';
    $schCode = !empty($p['schoolLevel']) ? (string) $p['schoolLevel'] : '';

    $members = $allMembers[$pId] ?? [];

    // Mentor teacher (from projects.mentorTeacherId)
    $mentorId = $p['mentorTeacherId'] ?? null;
    $mentor = $mentorId ? ($allMentors[$mentorId] ?? null) : null;
    $teamLeader = $mentor ? [
        'name' => (string) $mentor['fullName'],
        'role' => 'Giảng viên hướng dẫn' . (!empty($mentor['specialization']) ? ' — ' . $mentor['specialization'] : ''),
        'school' => $schName,
        'avatar_initial' => mb_strtoupper(mb_substr($mentor['fullName'], 0, 2)),
    ] : [
        'name' => 'Chưa có giảng viên hướng dẫn',
        'role' => 'Giảng viên hướng dẫn',
        'school' => $schName,
        'avatar_initial' => 'GV',
    ];

    $extra = $projectDetails[$pId] ?? [
        'problem_statement' => (string) ($p['description'] ?? 'Giải quyết bài toán thực tiễn từ doanh nghiệp và xã hội.'),
        'solution' => 'Giải pháp công nghệ kết hợp nghiên cứu thực tiễn do sinh viên và giảng viên hướng dẫn triển khai.',
    ];

    $projects[] = [
        'id' => $pId,
        'title' => (string) $p['title'],
        'school_id' => (string) ($p['schoolId'] ?? ''),
        'school_name' => $schName,
        'school_badge' => $schCode,
        'category' => $cat,
        'status' => (string) ($p['status'] ?? 'in_progress'),
        'status_label' => $pct >= 100 ? 'Đã đạt mục tiêu' : ($pct >= 80 ? 'Tiềm năng cao' : 'Đang gọi vốn'),
        'raised_amount' => $raised,
        'target_amount' => $target,
        'percentage' => $pct,
        'member_count' => (int) ($p['member_count'] ?? 0),
        'description' => (string) ($p['description'] ?? 'Dự án nghiên cứu và phát triển giải pháp thực tiễn từ giảng đường.'),
        'problem_statement' => $extra['problem_statement'],
        'solution' => $extra['solution'],
        'team_leader' => $teamLeader,
        'team_members' => array_map(static function($m) use ($roleLabels): array {
            return [
                'name' => (string) $m['fullName'],
                'role' => translateRole((string) $m['role'], $roleLabels),
            ];
        }, $members),
        'project_schedule' => [
            'start_at' => $p['startAt'] ?? null,
            'end_at' => $p['endAt'] ?? null,
            'status' => $p['status'] ?? null,
            'status_label' => match ($p['status'] ?? null) {
                'draft' => 'Bản nháp',
                'in_progress' => 'Đang thực hiện',
                'completed' => 'Đã hoàn thành',
                'archived' => 'Đã lưu trữ',
                default => $p['status'] ?? null,
            },
        ],
        // The project schema has no phase records; overall dates are not milestones.
        'milestones' => [],
        'funding_plan' => [
            'goal' => $p['fundingGoal'] ?? null,
            'received_amount' => $p['total_sponsored'],
            'status' => $p['fundingStatus'] ?? null,
            'status_label' => match ($p['fundingStatus'] ?? null) {
                'not_required' => 'Không cần tài trợ',
                'open' => 'Đang gọi tài trợ',
                'goal_reached' => 'Đã đạt mục tiêu',
                default => $p['fundingStatus'] ?? null,
            },
        ],
        // fundingGoal is a target, not an itemized expense plan. No allocation store exists.
        'expected_use_of_funds' => [],
    ];
}

$displayProjects = $projects;

$mySponsorships = [];
$totalSponsoredAmount = 0.0;    // Tổng đã thanh toán (payment_orders.paymentStatus = paid)
$totalPledgedAmount   = 0.0;    // Tổng cam kết chưa thanh toán (pledged / pending_payment)
$activeSponsorshipsCount = 0;

foreach ($dbSponsorships as $s) {
    $amount        = (float) ($s['amount'] ?? 0);
    $status        = (string) ($s['status'] ?? 'pledged');
    $paymentStatus = (string) ($s['paymentStatus'] ?? '');

    // Đã thanh toán: project_sponsorships.status='paid' VÀ payment_orders.paymentStatus='paid'
    $isPaid = ($status === 'paid' && $paymentStatus === 'paid');

    if ($isPaid) {
        $totalSponsoredAmount += $amount;
        $activeSponsorshipsCount++;
    } elseif (in_array($status, ['pledged', 'pending_payment'], true)) {
        $totalPledgedAmount += $amount;
    }

    // Label phân biệt rõ cam kết vs thanh toán
    $statusLabel = match (true) {
        $isPaid                         => 'Đã thanh toán',
        $status === 'pending_payment'   => 'Chờ thanh toán',
        $status === 'pledged'           => 'Cam kết — chưa thanh toán',
        $status === 'cancelled'         => 'Đã hủy',
        default                         => $status,
    };

    // Label trạng thái thanh toán riêng để hiển thị cột riêng
    $paymentLabel = match (true) {
        $paymentStatus === 'paid'    => 'Đã thanh toán',
        $paymentStatus === 'pending' => 'Chờ thanh toán',
        $paymentStatus === 'failed'  => 'Thanh toán thất bại',
        $status === 'pledged'        => 'Chưa có lệnh thanh toán',
        $status === 'cancelled'      => 'Đã hủy',
        default                      => 'Chưa thanh toán',
    };

    $mySponsorships[] = [
        'id'                        => (string) $s['id'],
        'project_id'                => (string) $s['projectId'],
        'project_title'             => (string) ($s['projectTitle'] ?? 'Dự án'),
        'school_name'               => (string) ($s['schoolName'] ?? 'Chưa liên kết trường'),
        'category'                  => (string) ($s['projectCategory'] ?? 'Đổi mới sáng tạo'),
        'sponsored_amount'          => $amount,
        'sponsored_amount_formatted'=> number_format($amount, 0, ',', '.') . ' VNĐ',
        'status'                    => $status,
        'status_label'              => $statusLabel,
        'payment_status'            => $paymentStatus,
        'payment_label'             => $paymentLabel,
        'is_paid'                   => $isPaid,
        'pledged_date'              => substr((string) ($s['createdAt'] ?? ''), 0, 10),
        'paid_at'                   => $isPaid ? substr((string) ($s['paidAt'] ?? ''), 0, 10) : '',
        'payment_order_id'          => (string) ($s['paymentOrderId'] ?? ''),
        'latest_update'             => [
            'date'    => substr((string) ($s['createdAt'] ?? ''), 0, 10),
            'title'   => $isPaid ? 'Thanh toán đã xác nhận' : 'Cam kết tài trợ đã ghi nhận',
            'author'  => (string) ($s['schoolName'] ?? ''),
            'summary' => $isPaid
                ? 'Khoản thanh toán đã được xác nhận và ghi nhận vào dự án.'
                : 'Cam kết tài trợ đã được ghi nhận, đang chờ xử lý thanh toán.',
        ],
    ];
}

$openProjectsCount    = count($displayProjects);
$totalTalentsCount    = array_sum(array_column($displayProjects, 'member_count'));
$totalCapitalMobilized = array_sum(array_column($displayProjects, 'raised_amount')); // chỉ paid

// Hiển thị: số tiền thực đã thanh toán vào các dự án (raised = paid only)
$totalBudgetDisplay   = number_format($totalCapitalMobilized, 0, ',', '.') . ' VNĐ';
$totalPledgedDisplay  = $totalPledgedAmount > 0
    ? number_format($totalPledgedAmount, 0, ',', '.') . ' VNĐ'
    : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sàn ươm mầm sáng tạo và tài trợ các dự án nghiên cứu đột phá từ học sinh, sinh viên - TalentHub Enterprise.">
    <title>Tài trợ Dự án & Ươm mầm Sáng tạo - Enterprise | TalentHub</title>

    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../../assets/css/home.css">
    <link rel="stylesheet" href="../../../assets/css/global.css">
    <link rel="stylesheet" href="../../../assets/css/brand-component.css">
    <link rel="stylesheet" href="../../../assets/css/polish.css">
    <link rel="stylesheet" href="../../../assets/css/enterprise.css?v=<?= filemtime(dirname(__DIR__, 3) . '/assets/css/enterprise.css'); ?>">
    <link rel="stylesheet" href="../../../assets/css/enterprise-sponsorships.css?v=<?= filemtime(dirname(__DIR__, 3) . '/assets/css/enterprise-sponsorships.css'); ?>">
</head>
<body class="enterprise-dashboard">
    <a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>

    <!-- Layout Wrapper -->
    <div class="ent-layout">
        
        <!-- Sidebar Partial -->
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

                    <!-- PHẦN 1: HERO BANNER (Sunset Gradient Theme) -->
                    <div class="ent-hero-banner" style="margin-bottom: 24px; padding: 32px 36px;">
                        
                        <!-- CỘT TRÁI: Thông điệp & Giá trị thương hiệu -->
                        <div class="ent-hero-banner__content" style="flex: 1.2; min-width: 280px;">
                            <div class="ent-hero-banner__tag">
                                <span>🌱</span>
                                <span>QUỸ ƯƠM MẦM ĐỔI MỚI SÁNG TẠO</span>
                            </div>
                            <h1 class="ent-hero-banner__title" style="font-size: 24px; margin: 0 0 8px 0;">
                                Chung tay bảo trợ &amp; tiếp sức tài năng nghiên cứu trẻ
                            </h1>
                            <p class="ent-hero-banner__subtitle" style="font-size: 14.5px; margin: 0; max-width: 540px;">
                                Đồng hành cùng học sinh, sinh viên hiện thực hóa các giải pháp công nghệ ứng dụng vào thực tiễn.
                            </p>
                        </div>

                        <!-- CỘT PHẢI: Hộp thống kê ngân sách nền Frosted Glass -->
                        <div style="background: rgba(255, 255, 255, 0.18); border: 1.5px solid rgba(255, 255, 255, 0.45); border-radius: 16px; padding: 18px 24px; display: flex; flex-direction: column; gap: 4px; min-width: 260px; box-sizing: border-box; backdrop-filter: blur(8px); color: #FFFFFF; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);">
                            <span style="font-size: 11px; font-weight: 700; color: rgba(255, 255, 255, 0.9); text-transform: uppercase; letter-spacing: 0.05em;">
                                TỔNG ĐÃ THANH TOÁN VÀO DỰ ÁN
                            </span>
                            <div style="font-size: 28px; font-weight: 800; color: #FFFFFF; line-height: 1.15; margin: 2px 0 4px 0; letter-spacing: -0.01em; text-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                                <?= htmlspecialchars($totalBudgetDisplay); ?>
                            </div>
                            <?php if ($totalPledgedDisplay !== null): ?>
                            <div style="font-size: 11.5px; font-weight: 600; color: rgba(255, 255, 255, 0.82); margin-bottom: 4px;">
                                + <?= htmlspecialchars($totalPledgedDisplay); ?> cam kết — chưa thanh toán
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: rgba(255, 255, 255, 0.94);">
                                <span style="display: inline-flex; align-items: center; gap: 4px;">📁 <?= (int)$openProjectsCount; ?> Đề án bảo trợ</span>
                                <span>&bull;</span>
                                <span style="display: inline-flex; align-items: center; gap: 4px;">👥 <?= (int)$totalTalentsCount; ?> Tài năng trẻ</span>
                            </div>
                        </div>

                    </div>

                    <!-- PHẦN 2: THANH LỌC TỐI GIẢN (Streamlined 1-Row Filter Bar) -->
                    <div class="ent-filter-toolbar" style="background-color: #FFFFFF; border: 1px solid var(--border); border-radius: 14px; padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; box-shadow: var(--shadow-soft);">
                        <!-- Bên trái: Search box -->
                        <div class="ent-search-input-wrapper" style="position: relative; display: flex; align-items: center; min-width: 260px; flex: 1;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="position: absolute; left: 12px; color: #94A3B8; pointer-events: none;">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <input type="text" 
                                   id="spon-search-input" 
                                   class="spon-input" 
                                   placeholder="Tìm tên dự án, từ khóa, tên trường..."
                                   aria-label="Tìm kiếm dự án nghiên cứu"
                                   style="width: 100%; height: 38px; padding: 0 14px 0 38px; background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 13px; color: #0F172A; outline: none; box-sizing: border-box;">
                        </div>

                        <!-- Bên phải: Filter Pills Danh mục nhanh -->
                        <div class="spon-filter-pills" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <button type="button" class="spon-pill-btn is-active" data-cat="all">
                                Tất cả (<?= (int)$openProjectsCount; ?>)
                            </button>
                            <?php 
                            $projectCategories = array_values(array_unique(array_filter(array_map(static fn($pr) => trim((string)($pr['category'] ?? '')), $displayProjects))));
                            foreach ($projectCategories as $catName): 
                            ?>
                                <button type="button" class="spon-pill-btn" data-cat="<?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- PHẦN 3: LƯỚI DỰ ÁN KÊU GỌI TÀI TRỢ (Project Showcase Grid) -->
                    <div class="spon-projects-grid" id="spon-projects-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; width: 100%; margin-bottom: 24px;">
                        <?php 
                        if (!function_exists('formatMillions')) {
                            function formatMillions($amount) {
                                if ($amount >= 1000000) {
                                    $m = $amount / 1000000;
                                    return ($m == (int)$m ? (int)$m : round($m, 1)) . ' triệu';
                                }
                                return number_format($amount, 0, ',', '.');
                            }
                        }
                        
                        foreach ($displayProjects as $project): 
                            $total_sponsored = $project['raised_amount'];
                            $fundingGoal = $project['target_amount'];
                            $percent = $fundingGoal > 0 ? round(($total_sponsored / $fundingGoal) * 100) : 0;
                            $progress_width = min($percent, 100);
                            $progressText = formatMillions($total_sponsored) . ' / ' . formatMillions($fundingGoal) . ($fundingGoal >= 1000000 ? ' VNĐ' : '');
                        ?>
                            <!-- Thẻ Project Card Độc Lập -->
                            <article class="spon-project-card" 
                                     data-project-id="<?= htmlspecialchars($project['id']); ?>" 
                                     data-title="<?= htmlspecialchars($project['title']); ?>" 
                                     data-category="<?= htmlspecialchars($project['category']); ?>" 
                                     data-school="<?= htmlspecialchars($project['school_name']); ?>"
                                     data-status="<?= htmlspecialchars($project['status']); ?>"
                                     data-target="<?= htmlspecialchars((string)$project['target_amount']); ?>"
                                     style="background: #FFFFFF; border: 1px solid #F0E6DD; border-radius: 16px; padding: 22px; box-shadow: 0 4px 16px -2px rgba(50, 32, 20, 0.04); display: flex; flex-direction: column; justify-content: space-between; gap: 16px; transition: transform 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">
                                
                                <!-- Hàng 1: Tiêu đề đề tài + Badge trạng thái -->
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                                    <h3 style="font-size: 16px; font-weight: 700; color: #322014; margin: 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 44px; flex: 1;">
                                        <?= htmlspecialchars($project['title']); ?>
                                    </h3>
                                    <span style="background: #FFF0EB; color: #E04058; border: 1px solid #FFDACB; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; white-space: nowrap; flex-shrink: 0;">
                                        <?= htmlspecialchars($project['status_label'] ?? 'Đang gọi vốn'); ?>
                                    </span>
                                </div>

                                <!-- Hàng 2: Trường THPT / Đại học chủ quản • Số thành viên -->
                                <div style="font-size: 13px; color: #6B5548; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                                        🏫 <?= htmlspecialchars($project['school_name']); ?>
                                    </span>
                                    <span>&bull;</span>
                                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                                        👥 <?= $project['member_count'] ?? 0 ?> thành viên
                                    </span>
                                </div>

                                <!-- Hàng 3: Thanh tiến độ tài trợ (chỉ tính khoản đã thanh toán) -->
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <div style="font-size: 11px; font-weight: 600; color: #9A7B6E; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 2px;">
                                        Đã thanh toán / Mục tiêu
                                    </div>
                                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px;">
                                        <span style="color: #322014; font-weight: 500;"><?= $progressText; ?></span>
                                        <span style="color: var(--primary-coral); font-weight: 700;"><?= $percent; ?>%</span>
                                    </div>
                                    <div style="width: 100%; height: 8px; background-color: #F0E6DD; border-radius: 999px; overflow: hidden;">
                                        <div style="width: <?= $progress_width ?>%; height: 100%; background: var(--primary-gradient); border-radius: 999px; transition: width 0.4s ease;"></div>
                                    </div>
                                </div>

                                <!-- Hàng 4: Cụm nút hành động -->
                                <div style="display: flex; flex-direction: column; gap: 8px; margin-top: auto; padding-top: 14px; border-top: 1px solid #F0E6DD;">
                                    <?php if ($total_sponsored >= $fundingGoal && $fundingGoal > 0): ?>
                                        <button type="button" 
                                                style="width: 100%; min-height: 44px; background-color: #F0E6DD; color: #A8988C; border: none; font-size: 14px; font-weight: 600; padding: 10px 18px; border-radius: 999px; cursor: not-allowed; text-align: center; transition: all 0.2s ease;"
                                                disabled>
                                            Đã đủ ngân sách
                                        </button>
                                    <?php else: ?>
                                        <button type="button" 
                                                class="btn-sponsor-now" 
                                                data-project-id="<?= htmlspecialchars($project['id']); ?>" 
                                                style="width: 100%; min-height: 44px; background: var(--primary-gradient); color: #FFFFFF; border: none; font-size: 14px; font-weight: 600; padding: 10px 18px; border-radius: 999px; cursor: pointer; text-align: center; box-shadow: 0 4px 14px rgba(248, 63, 112, 0.25); transition: all 0.2s ease;">
                                            Tài trợ ngay
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="btn-view-detail" 
                                            data-project-id="<?= htmlspecialchars($project['id']); ?>" 
                                            style="min-height: 44px; background: none; border: none; font-size: 13px; font-weight: 600; color: #6B5548; cursor: pointer; text-align: center; padding: 10px 4px; transition: color 0.15s ease;">
                                        Chi tiết đề án &amp; Đội ngũ &rarr;
                                    </button>
                                </div>

                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Empty State khi tìm kiếm không ra kết quả -->
                    <div id="spon-projects-empty" style="display: none; background: #FFFFFF; border: 1px solid #F0E6DD; border-radius: 16px; padding: 48px 24px; text-align: center; flex-direction: column; align-items: center; gap: 12px; margin-bottom: 24px; width: 100%;">
                        <div style="width: 64px; height: 64px; border-radius: 16px; background-color: #FFF0EB; border: 1px solid #FFDACB; display: flex; align-items: center; justify-content: center; color: #FF6B45; font-size: 28px; margin-bottom: 4px;">
                            🔍
                        </div>
                        <h3 style="font-size: 18px; font-weight: 700; color: #322014; margin: 0;">
                            Không tìm thấy dự án phù hợp
                        </h3>
                        <p style="font-size: 14px; color: #6B5548; max-width: 440px; margin: 0 0 8px 0; line-height: 1.5;">
                            Thử điều chỉnh từ khóa tìm kiếm hoặc chọn danh mục khác để khám phá các đề tài sáng tạo của học sinh sinh viên.
                        </p>
                        <button type="button" id="spon-reset-filters" style="background: #FFFFFF; border: 1.5px solid #F0E6DD; padding: 8px 20px; border-radius: 999px; font-size: 13px; font-weight: 600; color: #322014; cursor: pointer;">
                            Đặt lại bộ lọc
                        </button>
                    </div>

                    <!-- PHẦN 4: BẢNG LỊCH SỬ TÀI TRỢ CỦA DOANH NGHIỆP -->
                    <?php if (!empty($mySponsorships)): ?>
                    <div style="background: #FFFFFF; border: 1px solid #F0E6DD; border-radius: 16px; padding: 24px 28px; box-shadow: 0 4px 16px -2px rgba(50,32,20,0.04); margin-bottom: 24px; width: 100%; box-sizing: border-box;">
                        <h2 style="font-size: 16px; font-weight: 700; color: #322014; margin: 0 0 4px 0;">
                            Lịch sử tài trợ của doanh nghiệp
                        </h2>
                        <p style="font-size: 13px; color: #9A7B6E; margin: 0 0 20px 0;">
                            Mỗi dòng thể hiện một cam kết tài trợ riêng biệt. Cột <strong>Thanh toán</strong> phản ánh trạng thái thanh toán thực tế.
                        </p>
                        <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                            <table style="width: 100%; min-width: 640px; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="border-bottom: 2px solid #F0E6DD;">
                            <th style="text-align: left; padding: 8px 12px; font-weight: 700; color: #6B5548; white-space: nowrap;">Dự án</th>
                            <th style="text-align: right; padding: 8px 12px; font-weight: 700; color: #6B5548; white-space: nowrap;">Số tiền cam kết</th>
                            <th style="text-align: center; padding: 8px 12px; font-weight: 700; color: #6B5548; white-space: nowrap;">Cam kết</th>
                            <th style="text-align: center; padding: 8px 12px; font-weight: 700; color: #6B5548; white-space: nowrap;">Thanh toán</th>
                            <th style="text-align: center; padding: 8px 12px; font-weight: 700; color: #6B5548; white-space: nowrap;">Ngày cam kết</th>
                            <th style="text-align: center; padding: 8px 12px; font-weight: 700; color: #6B5548; white-space: nowrap;">Ngày thanh toán</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mySponsorships as $spon): ?>
                        <?php
                            // Màu badge cam kết
                            [$pledgeBg, $pledgeColor, $pledgeBorder] = match ($spon['status']) {
                                'paid'            => ['#F0FDF4', '#166534', '#BBF7D0'],
                                'pending_payment' => ['#FFFBEB', '#92400E', '#FDE68A'],
                                'pledged'         => ['#EFF6FF', '#1D4ED8', '#BFDBFE'],
                                'cancelled'       => ['#F8FAFC', '#64748B', '#E2E8F0'],
                                default           => ['#F8FAFC', '#64748B', '#E2E8F0'],
                            };
                            // Màu badge thanh toán
                            [$payBg, $payColor, $payBorder] = match ($spon['payment_status']) {
                                'paid'    => ['#F0FDF4', '#166534', '#BBF7D0'],
                                'pending' => ['#FFFBEB', '#92400E', '#FDE68A'],
                                'failed'  => ['#FFF1F2', '#9F1239', '#FECDD3'],
                                default   => ['#F8FAFC', '#64748B', '#E2E8F0'],
                            };
                        ?>
                        <tr style="border-bottom: 1px solid #F9F3EF;">
                            <td style="padding: 10px 12px; color: #322014; font-weight: 600;">
                                <?= htmlspecialchars($spon['project_title']); ?>
                                <div style="font-size: 11.5px; font-weight: 400; color: #9A7B6E; margin-top: 2px;">
                                    <?= htmlspecialchars($spon['school_name']); ?>
                                </div>
                            </td>
                            <td style="padding: 10px 12px; text-align: right; font-weight: 700; color: #322014; white-space: nowrap;">
                                <?= htmlspecialchars($spon['sponsored_amount_formatted']); ?>
                            </td>
                            <td style="padding: 10px 12px; text-align: center;">
                                <span style="display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; background: <?= $pledgeBg ?>; color: <?= $pledgeColor ?>; border: 1px solid <?= $pledgeBorder ?>; white-space: nowrap;">
                                    <?= htmlspecialchars($spon['status_label']); ?>
                                </span>
                            </td>
                            <td style="padding: 10px 12px; text-align: center;">
                                <span style="display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; background: <?= $payBg ?>; color: <?= $payColor ?>; border: 1px solid <?= $payBorder ?>; white-space: nowrap;">
                                    <?= htmlspecialchars($spon['payment_label']); ?>
                                </span>
                            </td>
                            <td style="padding: 10px 12px; text-align: center; color: #6B5548; white-space: nowrap;">
                                <?= htmlspecialchars($spon['pledged_date']); ?>
                            </td>
                            <td style="padding: 10px 12px; text-align: center; color: #6B5548; white-space: nowrap;">
                                <?= $spon['paid_at'] !== '' ? htmlspecialchars($spon['paid_at']) : '—'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="border-top: 2px solid #F0E6DD; background: #FAFAF9;">
                            <td style="padding: 10px 12px; font-weight: 700; color: #322014;">Tổng cộng</td>
                            <td style="padding: 10px 12px; text-align: right; font-weight: 800; color: #322014; white-space: nowrap;">
                                <?= number_format(array_sum(array_column($mySponsorships, 'sponsored_amount')), 0, ',', '.'); ?> VNĐ
                            </td>
                            <td colspan="4" style="padding: 10px 12px; font-size: 12px; color: #9A7B6E;">
                                <?= $activeSponsorshipsCount; ?> khoản đã thanh toán
                                <?php if ($totalPledgedAmount > 0): ?>
                                &bull; <?= number_format($totalPledgedAmount, 0, ',', '.'); ?> VNĐ cam kết chưa thanh toán
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tfoot>
                            </table>
                        </div><!-- /overflow-x:auto -->
                    </div><!-- /card -->
                    <?php endif; ?>

                </div><!-- /container-fluid -->
            </main>
        </div><!-- /ent-main-wrapper -->
    </div><!-- /ent-layout -->
    <div class="spon-modal" id="project-detail-modal" style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(15, 23, 42, 0.6) !important; backdrop-filter: blur(4px) !important; -webkit-backdrop-filter: blur(4px) !important; display: none; align-items: center; justify-content: center; z-index: 99999 !important; padding: 1.5rem; box-sizing: border-box;" aria-hidden="true" role="dialog">
        <div class="spon-modal-dialog">
            <div class="spon-modal-header">
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <span class="spon-school-badge" id="modal-school-badge">THPT Chuyên • KHTN</span>
                    <span class="spon-category-badge" id="modal-category-badge">IoT &amp; Phần cứng</span>
                    <span class="spon-tag-pill" id="modal-status-badge">Đang gọi vốn</span>
                </div>
                <button type="button" class="spon-modal-close" id="close-detail-modal" aria-label="Đóng">&times;</button>
            </div>

            <div class="spon-modal-body">
                <h3 class="spon-modal-title" id="modal-project-title">Smart Garden IoT - Hệ Thống Vườn Thông Minh Tự Động</h3>

                <!-- Problem & Solution Block -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">Bài toán thực tiễn &amp; Giải pháp đột phá</h4>
                    <p class="spon-detail-text" id="modal-problem-desc" style="margin-bottom: 0.75rem;">
                        Tình trạng lãng phí nguồn nước và thiếu hụt nhân lực chăm sóc cây trồng nông nghiệp tại các đô thị và nhà kính.
                    </p>
                    <div style="background-color: var(--primary-light); border-left: 3px solid var(--primary); padding: 0.875rem 1rem; border-radius: 0 8px 8px 0; font-size: 0.875rem; color: #9A3412;" id="modal-solution-desc">
                        Sử dụng mạng lưới cảm biến IoT kết hợp vi điều khiển ESP32 và máy học phân tích độ ẩm đất để tối ưu hóa 40% lượng nước tưới.
                    </div>
                </div>

                <!-- Mentor & Project Members -->
                <div class="spon-detail-section spon-team-section">
                    <h4 class="spon-section-heading">Nhóm tác giả &amp; Người hướng dẫn</h4>
                    <div class="spon-team-layout">
                        <section class="spon-team-group" aria-labelledby="modal-mentor-heading">
                            <h5 class="spon-team-group-heading" id="modal-mentor-heading">Người hướng dẫn</h5>
                            <div class="spon-team-card">
                                <div class="spon-avatar" id="modal-leader-avatar"></div>
                                <div class="spon-team-info">
                                    <h6 id="modal-leader-name"></h6>
                                    <p id="modal-leader-role"></p>
                                </div>
                            </div>
                        </section>

                        <section class="spon-team-group" aria-labelledby="modal-members-heading">
                            <h5 class="spon-team-group-heading" id="modal-members-heading">Thành viên dự án</h5>
                            <div class="spon-team-grid" id="modal-team-members"><!-- Dynamic team members list --></div>
                            <p class="spon-team-empty">Chưa có thành viên dự án.</p>
                        </section>
                    </div>
                </div>

                <!-- Milestones Timeline -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">Lộ trình nghiên cứu &amp; Nghiệm thu</h4>
                    <dl class="spon-detail-facts" id="modal-project-schedule"></dl>
                    <div class="spon-timeline" id="modal-milestones-timeline">
                        <!-- Dynamic timeline items -->
                    </div>
                </div>

                <!-- Expected Use of Funds -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">Kế hoạch tài trợ</h4>
                    <dl class="spon-detail-facts" id="modal-funding-summary"></dl>
                    <div id="modal-fund-allocation">
                        <!-- Dynamic fund allocation bars -->
                    </div>
                </div>
            </div>

            <div class="spon-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('project-detail-modal')?.classList.remove('is-open'); document.getElementById('project-detail-modal').style.display='none'; document.body.style.overflow = '';">Đóng</button>
                <button type="button" class="btn btn-primary" id="modal-sponsor-cta" style="border-radius: 999px; padding: 0.65rem 1.5rem;">
                    🌱 Đồng ý Tài trợ dự án này
                </button>
            </div>
        </div>
    </div>

    <!-- ---------------------------------------------------------------------- -->
    <!-- 5. Sponsorship Form Modal                                              -->
    <!-- ---------------------------------------------------------------------- -->
    <div class="spon-modal" id="sponsorship-form-modal" style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(15, 23, 42, 0.6) !important; backdrop-filter: blur(4px) !important; -webkit-backdrop-filter: blur(4px) !important; display: none; align-items: center; justify-content: center; z-index: 99999 !important; padding: 1.5rem; box-sizing: border-box;" aria-hidden="true" role="dialog">
        <div class="spon-modal-dialog" style="max-width: 540px;">
            <div class="spon-modal-header">
                <div>
                    <h4 style="font-size: 1.125rem; font-weight: 700; margin: 0; color: var(--text-primary);">Tài trợ Dự án Nghiên cứu</h4>
                    <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0.2rem 0 0 0;" id="form-project-title">Smart Garden IoT</p>
                </div>
                <button type="button" class="spon-modal-close" id="close-sponsorship-modal" aria-label="Đóng">&times;</button>
            </div>

            <form id="sponsorship-active-form">
                <div class="spon-modal-body">
                    <div class="spon-form-target-box" id="form-target-info" style="margin-bottom: 1.25rem;">
                        <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 0.25rem;">Kinh phí còn cần gọi:</div>
                        <div style="font-size: 1.25rem; font-weight: 800; color: var(--primary);" id="form-needed-amount">12.000.000 VNĐ</div>
                    </div>

                    <style>
                        /* Custom Range Slider Styles */
                        .spon-custom-range {
                            -webkit-appearance: none;
                            appearance: none;
                            width: 100%;
                            height: 6px;
                            background: #E2E8F0;
                            border-radius: 999px;
                            outline: none;
                            margin: 10px 0;
                            padding: 0;
                        }
                        .spon-custom-range::-webkit-slider-thumb {
                            -webkit-appearance: none;
                            appearance: none;
                            width: 20px;
                            height: 20px;
                            border-radius: 50%;
                            background: var(--primary-coral, #F83F70);
                            cursor: pointer;
                            border: 2px solid #FFFFFF;
                            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
                        }
                        .spon-custom-range::-moz-range-thumb {
                            width: 20px;
                            height: 20px;
                            border-radius: 50%;
                            background: var(--primary-coral, #F83F70);
                            cursor: pointer;
                            border: 2px solid #FFFFFF;
                            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
                        }
                    </style>
                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label for="spon-amount-input" class="form-label" style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                            <span>Mức tài trợ (VNĐ) <span style="color: #DC2626;">*</span></span>
                            <span id="spon-amount-display" style="font-size: 1.125rem; color: var(--primary); font-weight: 800;">0 VNĐ</span>
                        </label>
                        <input type="range" id="spon-amount-input" class="spon-custom-range" min="0" max="0" step="500000" value="0">
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">
                            <span>0 VNĐ</span>
                            <span id="spon-amount-max-label">0 VNĐ</span>
                        </div>
                        <div id="spon-amount-error" style="display: none; color: #DC2626; font-size: 0.75rem; margin-top: 0.5rem; font-weight: 500;">
                            ⚠️ Vui lòng chọn số tiền lớn hơn 0 VNĐ
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="spon-note-input" class="form-label" style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">Lời nhắn hoặc cam kết đồng hành từ Doanh nghiệp</label>
                        <textarea id="spon-note-input" class="spon-input" rows="3" placeholder="Ví dụ: Chúng tôi muốn đồng hành hỗ trợ phòng lab và cơ hội thực tập cho nhóm..."></textarea>
                    </div>

                    <div style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 0.875rem; font-size: 0.78125rem; color: var(--text-secondary); line-height: 1.4;">
                        🔒 <strong>Chính sách minh bạch:</strong> Khoản tài trợ sẽ được xác nhận qua hợp đồng bảo trợ CSR và giải ngân theo từng cột mốc nghiệm thu của nhà trường.
                    </div>
                </div>

                <div class="spon-modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('sponsorship-form-modal')?.classList.remove('is-open'); document.getElementById('sponsorship-form-modal').style.display='none'; document.body.style.overflow = '';">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-sponsorship" style="border-radius: 999px; padding: 0.65rem 1.5rem;">
                        Xác nhận Cam kết Tài trợ
                    </button>
                </div>
            </form>
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

    <!-- JavaScript Data Boot & Module Controller -->
    <script>
        window.ENTERPRISE_PROJECTS = <?= json_encode($displayProjects, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        window.ENTERPRISE_SPONSORSHIPS = <?= json_encode($mySponsorships, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script id="enterprise-session-boot" type="application/json"><?= json_encode(['csrfToken' => $context['csrfToken'], 'apiBase' => app_href('/api/v1')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?></script>
    <script src="<?= app_href('/assets/js/enterprise.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/enterprise-sponsorships.js'); ?>"></script>
</body>
</html>
