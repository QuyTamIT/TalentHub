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
    'new_matches_count' => 86,
    'total_talents'     => 1247,
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

// Fetch real database projects and sponsorships
$projectsQuery = \TalentHub\Http\CollectionQuery::fromRequest(
    new \TalentHub\Http\Request('GET', '/api/v1/projects', [], '', [], ['limit' => '100']),
    ['createdAt', 'title', 'fundingGoal']
);
$dbProjects = $workflowService->projects($projectsQuery);
$dbSponsorships = $workflowService->sponsorships((string) $enterprise['id']);

$projectDetails = [
    '50000000-0000-4000-8000-000000000001' => [
        'problem_statement' => 'Tình trạng lãng phí nguồn nước và thiếu hụt nhân lực giám sát cây trồng nông nghiệp tại các đô thị và mô hình nhà kính công nghệ cao.',
        'solution' => 'Sử dụng mạng lưới cảm biến IoT kết hợp vi điều khiển ESP32 và máy học phân tích độ ẩm đất để tối ưu hóa 40% lượng nước tưới và tự động hóa chu trình chăm sóc.',
        'milestones' => [
            ['phase' => 'Giai đoạn 1', 'title' => 'Nghiên cứu kiến trúc cảm biến & Lập trình vi điều khiển ESP32', 'date' => '07/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
            ['phase' => 'Giai đoạn 2', 'title' => 'Thử nghiệm hệ thống tưới tự động tại Vườn thực nghiệm BTEC Cần Thơ', 'date' => '08/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai'],
            ['phase' => 'Giai đoạn 3', 'title' => 'Triển khai thử nghiệm quy mô 5 nhà kính và đóng gói sản phẩm', 'date' => '11/2026', 'status' => 'planned', 'status_label' => 'Kế hoạch']
        ],
        'expected_use_of_funds' => [
            ['category' => 'Module Cảm biến IoT & Vi điều khiển ESP32', 'amount' => '25.000.000 VNĐ', 'percentage' => 50],
            ['category' => 'Hệ thống bơm van tự động & Hạ tầng Cloud Server', 'amount' => '15.000.000 VNĐ', 'percentage' => 30],
            ['category' => 'Học bổng & Hỗ trợ nhóm sinh viên nghiên cứu', 'amount' => '10.000.000 VNĐ', 'percentage' => 20]
        ]
    ],
    '50000000-0000-4000-8000-000000000002' => [
        'problem_statement' => 'Tình trạng rác thải sinh hoạt và rác tái chế bị vứt lẫn lộn gây khó khăn lớn cho công tác xử lý và làm giảm 70% giá trị tái chế nguyên liệu.',
        'solution' => 'Sử dụng camera AI nhận diện thời gian thực kết hợp mô hình Computer Vision YOLOv8 trên vi xử lý Jetson Nano và hệ thống cánh lật phân loại tự động vào 3 ngăn.',
        'milestones' => [
            ['phase' => 'Giai đoạn 1', 'title' => 'Huấn luyện mô hình YOLOv8 trên 15.000 ảnh rác thải', 'date' => '07/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
            ['phase' => 'Giai đoạn 2', 'title' => 'Chế tạo và thử nghiệm thùng rác thông minh tại Campus BTEC', 'date' => '08/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai'],
            ['phase' => 'Giai đoạn 3', 'title' => 'Thương mại hóa và lắp đặt thử nghiệm tại các tòa nhà văn phòng FPT', 'date' => '11/2026', 'status' => 'planned', 'status_label' => 'Kế hoạch']
        ],
        'expected_use_of_funds' => [
            ['category' => 'Module AI Camera & Vi xử lý Edge (Jetson Nano)', 'amount' => '15.000.000 VNĐ', 'percentage' => 50],
            ['category' => 'Cơ khí khung thùng rác & Động cơ Servo công nghiệp', 'amount' => '9.000.000 VNĐ', 'percentage' => 30],
            ['category' => 'Học bổng & Hỗ trợ sinh viên nghiên cứu', 'amount' => '6.000.000 VNĐ', 'percentage' => 20]
        ]
    ],
    '50000000-0000-4000-8000-000000000003' => [
        'problem_statement' => 'Áp lực quá tải tại các bệnh viện tuyến dưới và nguy cơ bỏ sót các tổn thương phổi giai đoạn đầu trên phim chụp X-quang lồng ngực.',
        'solution' => 'Ứng dụng mô hình Deep Learning phân tích ảnh chụp X-quang lồng ngực hỗ trợ bác sĩ phát hiện sớm tổn thương phổi và các bệnh lý hô hấp với độ chính xác >94%.',
        'milestones' => [
            ['phase' => 'Giai đoạn 1', 'title' => 'Xây dựng tập dữ liệu chuẩn hóa 20.000 phim X-quang', 'date' => '06/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
            ['phase' => 'Giai đoạn 2', 'title' => 'Huấn luyện mô hình Deep Learning DenseNet/ResNet và thử nghiệm lab', 'date' => '08/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai'],
            ['phase' => 'Giai đoạn 3', 'title' => 'Tích hợp phần mềm hỗ trợ chẩn đoán cho các phòng khám thực nghiệm', 'date' => '12/2026', 'status' => 'planned', 'status_label' => 'Kế hoạch']
        ],
        'expected_use_of_funds' => [
            ['category' => 'Hạ tầng GPU Cloud & Server huấn luyện AI', 'amount' => '20.000.000 VNĐ', 'percentage' => 50],
            ['category' => 'Hợp tác chuyên gia y tế cố vấn & Đánh giá mô hình', 'amount' => '12.000.000 VNĐ', 'percentage' => 30],
            ['category' => 'Học bổng & Khen thưởng nhóm nghiên cứu', 'amount' => '8.000.000 VNĐ', 'percentage' => 20]
        ]
    ]
];

$projects = [];
foreach ($dbProjects as $p) {
    $pId = (string) $p['id'];
    $raised = (float) ($p['raisedAmount'] ?? 0);
    $target = (float) ($p['fundingGoal'] ?? 0);
    $pct = $target > 0 ? (int) min(100, round(($raised / $target) * 100)) : 0;
    $cat = (string) ($p['category'] ?? 'Công nghệ & Đổi mới sáng tạo');
    $schName = (string) ($p['schoolName'] ?? 'Đại học đối tác');
    $schCode = (string) ($p['schoolLevel'] ?? $p['schoolCode'] ?? 'Đại học');

    $members = $p['members'] ?? [];
    $teamLeader = !empty($members) ? [
        'name' => (string) $members[0]['name'],
        'role' => (string) $members[0]['role'],
        'school' => $schName,
        'avatar_initial' => mb_strtoupper(mb_substr($members[0]['name'], 0, 2)),
    ] : [
        'name' => 'Nhóm sinh viên nghiên cứu',
        'role' => 'Trưởng nhóm đề án',
        'school' => $schName,
        'avatar_initial' => 'SV',
    ];

    $extra = $projectDetails[$pId] ?? [
        'problem_statement' => (string) ($p['description'] ?? 'Giải quyết bài toán thực tiễn từ doanh nghiệp và xã hội.'),
        'solution' => 'Giải pháp công nghệ kết hợp nghiên cứu thực tiễn do sinh viên và giảng viên hướng dẫn triển khai.',
        'milestones' => [
            ['phase' => 'Giai đoạn 1', 'title' => 'Nghiên cứu & Thiết kế', 'date' => '08/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
            ['phase' => 'Giai đoạn 2', 'title' => 'Thử nghiệm & Đánh giá', 'date' => '10/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai'],
            ['phase' => 'Giai đoạn 3', 'title' => 'Nghiệm thu & Ứng dụng', 'date' => '12/2026', 'status' => 'planned', 'status_label' => 'Kế hoạch']
        ],
        'expected_use_of_funds' => [
            ['category' => 'Trang thiết bị & Linh kiện', 'amount' => number_format($target * 0.5, 0, ',', '.') . ' VNĐ', 'percentage' => 50],
            ['category' => 'Thử nghiệm & Thu thập dữ liệu', 'amount' => number_format($target * 0.3, 0, ',', '.') . ' VNĐ', 'percentage' => 30],
            ['category' => 'Học bổng & Hỗ trợ sinh viên', 'amount' => number_format($target * 0.2, 0, ',', '.') . ' VNĐ', 'percentage' => 20]
        ]
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
        'members_count' => (int) ($p['membersCount'] ?? count($members)),
        'description' => (string) ($p['description'] ?? 'Dự án nghiên cứu và phát triển giải pháp thực tiễn từ giảng đường.'),
        'problem_statement' => $extra['problem_statement'],
        'solution' => $extra['solution'],
        'team_leader' => $teamLeader,
        'team_members' => array_map(static fn($m): array => [
            'name' => (string) $m['name'],
            'role' => (string) $m['role'],
            'skills' => ['AI / ML', 'Phần mềm', 'Thực hành', 'Nghiên cứu']
        ], $members),
        'milestones' => $extra['milestones'],
        'expected_use_of_funds' => $extra['expected_use_of_funds'],
    ];
}

$displayProjects = $projects;

$mySponsorships = [];
$totalSponsoredAmount = 0.0;
$activeSponsorshipsCount = 0;

foreach ($dbSponsorships as $s) {
    $amount = (float) ($s['amount'] ?? 0);
    $status = (string) ($s['status'] ?? 'pledged');
    $paymentStatus = (string) ($s['paymentStatus'] ?? 'pending');

    if ($status === 'paid' || $paymentStatus === 'paid') {
        $totalSponsoredAmount += $amount;
        $activeSponsorshipsCount++;
    }

    $statusLabel = match ($status) {
        'paid' => 'Đã giải ngân',
        'pending_payment' => 'Chờ thanh toán',
        'pledged' => 'Đã cam kết',
        'cancelled' => 'Đã hủy',
        default => $status,
    };

    $mySponsorships[] = [
        'id' => (string) $s['id'],
        'project_id' => (string) $s['projectId'],
        'project_title' => (string) ($s['projectTitle'] ?? 'Dự án'),
        'school_name' => (string) ($s['schoolName'] ?? 'Trường đối tác'),
        'category' => (string) ($s['projectCategory'] ?? 'Đổi mới sáng tạo'),
        'sponsored_amount' => $amount,
        'sponsored_amount_formatted' => number_format($amount, 0, ',', '.') . ' VNĐ',
        'status' => $status,
        'status_label' => $statusLabel,
        'pledged_date' => substr((string) ($s['createdAt'] ?? ''), 0, 10),
        'payment_status' => $paymentStatus,
        'payment_order_id' => (string) ($s['paymentOrderId'] ?? ''),
        'paid_at' => (string) ($s['paidAt'] ?? ''),
    ];
}

$openProjectsCount = count($displayProjects);
$totalTalentsCount = array_sum(array_column($displayProjects, 'members_count'));
$totalCapitalMobilized = array_sum(array_column($displayProjects, 'raised_amount'));

$totalBudgetDisplay = number_format($totalCapitalMobilized, 0, ',', '.') . ' VNĐ';
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
    <link rel="stylesheet" href="../../../assets/css/enterprise.css">
    <link rel="stylesheet" href="../../../assets/css/enterprise-sponsorships.css">
</head>
<body class="enterprise-dashboard">

    <!-- Layout Wrapper -->
    <div class="ent-layout">

        <!-- Sidebar Partial -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content Wrapper -->
        <div class="ent-main-wrapper">

            <!-- Top Header Partial -->
            <?php include __DIR__ . '/../includes/header.php'; ?>

            <!-- Page Body Content -->
            <main class="ent-body">
                <div class="container-fluid">

                    <?php if (!empty($_SESSION['flash_message'])): ?>
                        <div class="ent-alert ent-alert--success mb-4" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 0.875rem 1.25rem; border-radius: 8px; font-weight: 500; display: flex; align-items: center; justify-content: space-between;">
                            <span><?= htmlspecialchars($_SESSION['flash_message']); ?></span>
                            <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #166534; line-height: 1;">&times;</button>
                        </div>
                        <?php unset($_SESSION['flash_message']); ?>
                    <?php endif; ?>

                    <!-- PHẦN 1: HERO BANNER LIGHT THEME (Nền Trắng - Viền Cam Tinh Tế) -->
                    <div class="ent-hero-banner-light" style="background: #FFFFFF; border: 1px solid #E2E8F0; border-left: 4px solid #F97316; border-radius: 16px; padding: 28px 32px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04); display: flex; align-items: center; justify-content: space-between; gap: 28px; flex-wrap: wrap; margin-bottom: 24px; box-sizing: border-box;">

                        <!-- CỘT TRÁI: Thông điệp & Giá trị thương hiệu -->
                        <div style="flex: 1.2; min-width: 280px;">
                            <div style="display: inline-flex; align-items: center; gap: 6px; background: #FFF7ED; color: #EA580C; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 10px; border: 1px solid rgba(249, 115, 22, 0.2);">
                                <span>🌱</span>
                                <span>QUỸ ƯƠM MẦM ĐỔI MỚI SÁNG TẠO</span>
                            </div>
                            <h1 style="font-size: 22px; font-weight: 700; color: #0F172A; line-height: 1.35; margin: 0 0 6px 0;">
                                Chung tay bảo trợ &amp; tiếp sức tài năng nghiên cứu trẻ
                            </h1>
                            <p style="font-size: 14px; color: #64748B; line-height: 1.5; margin: 0; max-width: 520px;">
                                Đồng hành cùng học sinh, sinh viên hiện thực hóa các giải pháp công nghệ ứng dụng vào thực tiễn.
                            </p>
                        </div>

                        <!-- CỘT PHẢI: Hộp thống kê ngân sách nền kem sáng -->
                        <div style="background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 12px; padding: 16px 24px; display: flex; flex-direction: column; gap: 4px; min-width: 260px; box-sizing: border-box;">
                            <span style="font-size: 11px; font-weight: 700; color: #9A3412; text-transform: uppercase; letter-spacing: 0.04em;">
                                TỔNG NGÂN SÁCH ĐÃ CAM KẾT
                            </span>
                            <div style="font-size: 28px; font-weight: 800; color: #EA580C; line-height: 1.15; margin: 2px 0 6px 0; letter-spacing: -0.01em;">
                                <?= htmlspecialchars($totalBudgetDisplay); ?>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: #9A3412;">
                                <span style="display: inline-flex; align-items: center; gap: 4px;">📁 <?= (int)$openProjectsCount; ?> Đề án bảo trợ</span>
                                <span>&bull;</span>
                                <span style="display: inline-flex; align-items: center; gap: 4px;">👥 <?= (int)$totalTalentsCount; ?> Tài năng trẻ</span>
                            </div>
                        </div>

                    </div>

                    <!-- PHẦN 2: THANH LỌC TỐI GIẢN (Streamlined 1-Row Filter Bar) -->
                    <div class="ent-filter-toolbar" style="background-color: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 14px; padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);">
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
                            <button type="button" class="spon-pill-btn is-active" data-cat="all" style="padding: 7px 14px; border-radius: 999px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #F97316; background: #F97316; color: #FFFFFF; transition: all 0.15s ease;">
                                Tất cả (3)
                            </button>
                            <button type="button" class="spon-pill-btn" data-cat="IoT & AI Nhúng" style="padding: 7px 14px; border-radius: 999px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #E2E8F0; background: #F8FAFC; color: #475569; transition: all 0.15s ease;">
                                IoT &amp; AI Nhúng
                            </button>
                            <button type="button" class="spon-pill-btn" data-cat="Trí tuệ nhân tạo & Thị giác máy tính" style="padding: 7px 14px; border-radius: 999px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #E2E8F0; background: #F8FAFC; color: #475569; transition: all 0.15s ease;">
                                Trí tuệ nhân tạo &amp; CV
                            </button>
                            <button type="button" class="spon-pill-btn" data-cat="AI Y tế & Chuyển đổi số" style="padding: 7px 14px; border-radius: 999px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1px solid #E2E8F0; background: #F8FAFC; color: #475569; transition: all 0.15s ease;">
                                AI Y tế &amp; Chuyển đổi số
                            </button>
                        </div>
                    </div>

                    <!-- PHẦN 3: LƯỚI DỰ ÁN KÊU GỌI TÀI TRỢ (Project Showcase Grid) -->
                    <div class="spon-projects-grid" id="spon-projects-container" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; width: 100%; margin-bottom: 24px;">
                        <?php foreach ($displayProjects as $project):
                            $raisedMillions = round($project['raised_amount'] / 1000000, 1);
                            $targetMillions = round($project['target_amount'] / 1000000, 1);
                            $progressText = ($raisedMillions == (int)$raisedMillions ? (int)$raisedMillions : $raisedMillions) . ' triệu / ' . ($targetMillions == (int)$targetMillions ? (int)$targetMillions : $targetMillions) . ' triệu VNĐ';
                            $pct = (int) $project['percentage'];
                        ?>
                            <!-- Thẻ Project Card Độc Lập -->
                            <article class="spon-project-card"
                                     data-project-id="<?= htmlspecialchars($project['id']); ?>"
                                     data-title="<?= htmlspecialchars($project['title']); ?>"
                                     data-category="<?= htmlspecialchars($project['category']); ?>"
                                     data-school="<?= htmlspecialchars($project['school_name']); ?>"
                                     data-status="<?= htmlspecialchars($project['status']); ?>"
                                     data-target="<?= htmlspecialchars((string)$project['target_amount']); ?>"
                                     style="background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 22px; box-shadow: 0 4px 16px -2px rgba(15, 23, 42, 0.04); display: flex; flex-direction: column; justify-content: space-between; gap: 16px; transition: transform 0.2s ease, box-shadow 0.2s ease; box-sizing: border-box;">

                                <!-- Hàng 1: Tiêu đề đề tài + Badge trạng thái -->
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
                                    <h3 style="font-size: 16px; font-weight: 700; color: #0F172A; margin: 0; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 44px; flex: 1;">
                                        <?= htmlspecialchars($project['title']); ?>
                                    </h3>
                                    <span style="background: #FFF7ED; color: #EA580C; border: 1px solid rgba(249, 115, 22, 0.2); font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; white-space: nowrap; flex-shrink: 0;">
                                        <?= htmlspecialchars($project['status_label'] ?? 'Đang gọi vốn'); ?>
                                    </span>
                                </div>

                                <!-- Hàng 2: Trường THPT / Đại học chủ quản • Số thành viên -->
                                <div style="font-size: 13px; color: #64748B; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                                        🏫 <?= htmlspecialchars($project['school_name']); ?>
                                    </span>
                                    <span>&bull;</span>
                                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                                        👥 <?= (int)$project['members_count']; ?> thành viên
                                    </span>
                                </div>

                                <!-- Hàng 3: Thanh tiến độ tài trợ -->
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px;">
                                        <span style="color: #334155; font-weight: 500;"><?= $progressText; ?></span>
                                        <span style="color: #F97316; font-weight: 700;"><?= $pct; ?>%</span>
                                    </div>
                                    <div style="width: 100%; height: 8px; background-color: #F1F5F9; border-radius: 999px; overflow: hidden;">
                                        <div style="width: <?= min(100, $pct); ?>%; height: 100%; background: linear-gradient(90deg, #F97316 0%, #EA580C 100%); border-radius: 999px; transition: width 0.4s ease;"></div>
                                    </div>
                                </div>

                                <!-- Hàng 4: Cụm nút hành động -->
                                <div style="display: flex; flex-direction: column; gap: 8px; margin-top: auto; padding-top: 14px; border-top: 1px solid #F1F5F9;">
                                    <button type="button"
                                            class="btn-sponsor-now"
                                            data-project-id="<?= htmlspecialchars($project['id']); ?>"
                                            style="width: 100%; background-color: #F97316; color: #FFFFFF; border: none; font-size: 14px; font-weight: 600; padding: 10px 18px; border-radius: 999px; cursor: pointer; text-align: center; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.25); transition: all 0.2s ease;">
                                        Tài trợ ngay
                                    </button>
                                    <button type="button"
                                            class="btn-view-detail"
                                            data-project-id="<?= htmlspecialchars($project['id']); ?>"
                                            style="background: none; border: none; font-size: 13px; font-weight: 500; color: #64748B; cursor: pointer; text-align: center; padding: 4px; transition: color 0.15s ease;">
                                        Chi tiết đề án &amp; Đội ngũ &rarr;
                                    </button>
                                </div>

                            </article>
                        <?php endforeach; ?>
                    </div>

                    <!-- Empty State khi tìm kiếm không ra kết quả -->
                    <div id="spon-projects-empty" style="display: none; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 16px; padding: 48px 24px; text-align: center; flex-direction: column; align-items: center; gap: 12px; margin-bottom: 24px; width: 100%;">
                        <div style="width: 64px; height: 64px; border-radius: 16px; background-color: #FFF7ED; border: 1px solid rgba(249, 115, 22, 0.2); display: flex; align-items: center; justify-content: center; color: #EA580C; font-size: 28px; margin-bottom: 4px;">
                            🔍
                        </div>
                        <h3 style="font-size: 18px; font-weight: 700; color: #0F172A; margin: 0;">
                            Không tìm thấy dự án phù hợp
                        </h3>
                        <p style="font-size: 14px; color: #64748B; max-width: 440px; margin: 0 0 8px 0; line-height: 1.5;">
                            Thử điều chỉnh từ khóa tìm kiếm hoặc chọn danh mục khác để khám phá các đề tài sáng tạo của học sinh sinh viên.
                        </p>
                        <button type="button" id="spon-reset-filters" style="background: #F8FAFC; border: 1px solid #E2E8F0; padding: 8px 20px; border-radius: 999px; font-size: 13px; font-weight: 600; color: #0F172A; cursor: pointer;">
                            Đặt lại bộ lọc
                        </button>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- ---------------------------------------------------------------------- -->
    <!-- 4. Project Detail Modal                                               -->
    <!-- ---------------------------------------------------------------------- -->
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

                <!-- Team Leader & Research Team -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">Nhóm tác giả &amp; Người hướng dẫn</h4>
                    <div class="spon-leader-highlight">
                        <div class="spon-avatar" id="modal-leader-avatar">NL</div>
                        <div>
                            <h5 style="margin: 0; font-size: 0.9375rem; font-weight: 700; color: var(--text-primary);" id="modal-leader-name">Nguyễn Hoàng Long</h5>
                            <p style="margin: 0; font-size: 0.8125rem; color: var(--text-secondary);" id="modal-leader-role">Trưởng nhóm IoT (THPT Chuyên KHTN)</p>
                        </div>
                    </div>

                    <div class="spon-team-grid" id="modal-team-members">
                        <!-- Dynamic team members list -->
                    </div>
                </div>

                <!-- Milestones Timeline -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">Lộ trình nghiên cứu &amp; Nghiệm thu</h4>
                    <div class="spon-timeline" id="modal-milestones-timeline">
                        <!-- Dynamic timeline items -->
                    </div>
                </div>

                <!-- Expected Use of Funds -->
                <div class="spon-detail-section">
                    <h4 class="spon-section-heading">Kế hoạch phân bổ nguồn kinh phí tài trợ</h4>
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

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label for="spon-amount-input" class="form-label" style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">Số tiền tài trợ (VNĐ) <span style="color: #DC2626;">*</span></label>
                        <input type="number" id="spon-amount-input" class="spon-input" placeholder="Ví dụ: 10000000" min="500000" step="500000" required style="padding-left: 1rem;">
                    </div>

                    <div style="margin-bottom: 1.25rem;">
                        <span style="font-size: 0.78125rem; color: var(--text-muted); display: block; margin-bottom: 0.4rem;">Gợi ý mức tài trợ phổ biến:</span>
                        <div class="spon-preset-row">
                            <button type="button" class="spon-preset-btn" data-val="5000000">5 triệu</button>
                            <button type="button" class="spon-preset-btn" data-val="10000000">10 triệu</button>
                            <button type="button" class="spon-preset-btn" data-val="20000000">20 triệu</button>
                            <button type="button" class="spon-preset-btn" data-val="50000000">50 triệu</button>
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
    </script>
    <script id="enterprise-session-boot" type="application/json"><?= json_encode(['csrfToken' => $context['csrfToken'], 'apiBase' => app_href('/api/v1')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES); ?></script>
    <script src="<?= app_href('/assets/js/enterprise.js'); ?>"></script>
    <script src="<?= app_href('/assets/js/enterprise-sponsorships.js'); ?>"></script>
</body>
</html>
