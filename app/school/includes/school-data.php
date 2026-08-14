<?php
/**
 * TalentHub School - Mock Data Provider
 *
 * Note for Developers:
 * - This file centralizes all mock data for the School Dashboard.
 * - Replace each array with real DB queries (joins across schools, classes,
 *   student_profiles, activities, experience_logs, badges) when API is ready.
 * - Field names follow the database schema in Database/Talenthub_DB.sql.
 */

// --------------------------------------------------------------------------
// 1. School Identity
// --------------------------------------------------------------------------
$schoolInfo = [
    'name' => 'THPT Nguyễn Du',
    'logo_initials' => 'ND',
    'principal' => 'Nguyễn Văn An',
    'account_type' => 'Gói Premium',
    'student_total' => 1247,
    'class_total' => 36,
    'grade_levels' => 3,
    'academic_year' => '2025 - 2026'
];

// --------------------------------------------------------------------------
// 2. Sidebar Navigation
// --------------------------------------------------------------------------
$sidebarNav = [
    [
        'title' => 'Tổng quan',
        'route' => '/app/school',
        'icon' => 'grid',
        'active' => true
    ],
    [
        'title' => 'Phân tích năng lực',
        'route' => '/app/school/analytics.php',
        'icon' => 'chart',
        'active' => false
    ],
    [
        'title' => 'Báo cáo',
        'route' => '/app/school/reports.php',
        'icon' => 'file-text',
        'active' => false
    ],
    [
        'title' => 'Lớp & Khối',
        'route' => '/app/school/classes.php',
        'icon' => 'users',
        'active' => false
    ]
];

// --------------------------------------------------------------------------
// 3. KPI Cards (Tổng quan)
// --------------------------------------------------------------------------
$schoolKpis = [
    [
        'id' => 'students',
        'label' => 'Tổng học sinh',
        'value' => '1,247',
        'change' => '+52 HS mới',
        'change_type' => 'positive',
        'icon' => 'users'
    ],
    [
        'id' => 'activities',
        'label' => 'Hoạt động tháng này',
        'value' => '24',
        'change' => '8 đang diễn ra',
        'change_type' => 'positive',
        'icon' => 'calendar'
    ],
    [
        'id' => 'avg_score',
        'label' => 'Điểm TB năng lực',
        'value' => '82.4',
        'change' => '+3.2 so với HK1',
        'change_type' => 'positive',
        'icon' => 'trending-up'
    ],
    [
        'id' => 'experience_hours',
        'label' => 'Giờ trải nghiệm',
        'value' => '6,340h',
        'change' => '+1,180h tháng này',
        'change_type' => 'positive',
        'icon' => 'clock'
    ]
];

// --------------------------------------------------------------------------
// 4. Talent Distribution (Phân bổ 5 lĩnh vực năng khiếu)
// --------------------------------------------------------------------------
$talentDistribution = [
    ['field' => 'Kỹ thuật',   'percent' => 32, 'students' => 399, 'color' => 'orange'],
    ['field' => 'Học thuật',  'percent' => 28, 'students' => 349, 'color' => 'blue'],
    ['field' => 'Nghệ thuật', 'percent' => 18, 'students' => 224, 'color' => 'purple'],
    ['field' => 'Kinh doanh', 'percent' => 14, 'students' => 175, 'color' => 'green'],
    ['field' => 'Thể thao',   'percent' =>  8, 'students' => 100, 'color' => 'amber']
];

// --------------------------------------------------------------------------
// 5. Grade Ranking (Bảng xếp hạng khối)
// --------------------------------------------------------------------------
$gradeRanking = [
    ['rank' => 1, 'grade' => 'Khối 12', 'hours' => 2340, 'avg_score' => 88, 'students' => 412],
    ['rank' => 2, 'grade' => 'Khối 11', 'hours' => 2180, 'avg_score' => 82, 'students' => 425],
    ['rank' => 3, 'grade' => 'Khối 10', 'hours' => 1820, 'avg_score' => 76, 'students' => 410]
];

// --------------------------------------------------------------------------
// 6. Top 5 Classes
// --------------------------------------------------------------------------
$topClasses = [
    ['rank' => 1, 'class' => '12A1', 'major' => 'Chuyên Tin',  'score' => 94, 'students' => 38, 'grade' => 12],
    ['rank' => 2, 'class' => '11A2', 'major' => 'Chuyên Lý',   'score' => 92, 'students' => 36, 'grade' => 11],
    ['rank' => 3, 'class' => '12B3', 'major' => 'Chuyên Hoá',  'score' => 90, 'students' => 40, 'grade' => 12],
    ['rank' => 4, 'class' => '11A1', 'major' => 'Chuyên Toán', 'score' => 88, 'students' => 37, 'grade' => 11],
    ['rank' => 5, 'class' => '10C1', 'major' => 'Chuyên Anh',  'score' => 86, 'students' => 35, 'grade' => 10]
];

// --------------------------------------------------------------------------
// 7. Recent Activities (Hoạt động gần đây)
// --------------------------------------------------------------------------
$recentActivities = [
    [
        'title' => 'Cuộc thi Tin học trẻ cấp trường vừa hoàn thành',
        'time'  => '10 phút trước',
        'type'  => 'event'
    ],
    [
        'title' => 'Đã duyệt 12 hồ sơ năng lực học sinh khối 12',
        'time'  => '2 giờ trước',
        'type'  => 'approval'
    ],
    [
        'title' => 'Cập nhật kết quả điểm danh QR hoạt động ngoại khóa tháng 8',
        'time'  => 'Hôm qua',
        'type'  => 'edit'
    ],
    [
        'title' => 'Hoàn tất báo cáo tổng kết hoạt động Đoàn trường quý 2/2026',
        'time'  => '2 ngày trước',
        'type'  => 'report'
    ]
];

// --------------------------------------------------------------------------
// 8. Pending Actions (Việc cần xử lý)
// --------------------------------------------------------------------------
$pendingActions = [
    [
        'title'       => '5 báo cáo chờ ký duyệt',
        'subtitle'    => 'Báo cáo hoạt động tuần từ các CLB và Đoàn trường',
        'type'        => 'urgent',
        'action_label'=> 'Xem danh sách',
        'route'       => '/app/school/reports.php'
    ],
    [
        'title'       => '2 hoạt động sắp hết hạn đăng ký',
        'subtitle'    => 'Cuộc thi Robotics 2026 và Trại sáng tạo khoa học',
        'type'        => 'warning',
        'action_label'=> 'Xem chi tiết',
        'route'       => '#'
    ],
    [
        'title'       => '14 hồ sơ năng lực cần xác minh',
        'subtitle'    => 'Hồ sơ đăng ký chứng chỉ từ giáo viên chủ nhiệm',
        'type'        => 'info',
        'action_label'=> 'Phê duyệt',
        'route'       => '#'
    ],
    [
        'title'       => '3 yêu cầu từ doanh nghiệp chưa phản hồi',
        'subtitle'    => 'Đề nghị tài trợ sân chơi năng khiếu Công nghệ 2026',
        'type'        => 'neutral',
        'action_label'=> 'Trả lời ngay',
        'route'       => '#'
    ]
];

// --------------------------------------------------------------------------
// 9. Analytics — Talent scores by field x grade (for grouped bar chart)
// --------------------------------------------------------------------------
$talentByFieldGrade = [
    'Kỹ thuật'   => ['10' => 68, '11' => 78, '12' => 92],
    'Học thuật'  => ['10' => 72, '11' => 80, '12' => 88],
    'Nghệ thuật' => ['10' => 76, '11' => 82, '12' => 85],
    'Kinh doanh' => ['10' => 70, '11' => 75, '12' => 81],
    'Thể thao'   => ['10' => 74, '11' => 79, '12' => 83]
];

// Top 20 students by talent score (for Analytics page detail table)
$topStudents = [
    ['id' => 1, 'name' => 'Nguyễn Văn An',     'class' => '12A1', 'major' => 'Chuyên Tin',  'talent_score' => 96, 'primary_field' => 'Kỹ thuật',   'hours' => 142],
    ['id' => 2, 'name' => 'Trần Thị Bảo Ngọc', 'class' => '12A1', 'major' => 'Chuyên Tin',  'talent_score' => 94, 'primary_field' => 'Kỹ thuật',   'hours' => 138],
    ['id' => 3, 'name' => 'Lê Quang Minh',      'class' => '11A2', 'major' => 'Chuyên Lý',   'talent_score' => 93, 'primary_field' => 'Học thuật',  'hours' => 128],
    ['id' => 4, 'name' => 'Phạm Hoàng Nam',     'class' => '12B3', 'major' => 'Chuyên Hoá',  'talent_score' => 92, 'primary_field' => 'Học thuật',  'hours' => 124],
    ['id' => 5, 'name' => 'Đỗ Quang Huy',       'class' => '12A1', 'major' => 'Chuyên Tin',  'talent_score' => 91, 'primary_field' => 'Kỹ thuật',   'hours' => 130],
    ['id' => 6, 'name' => 'Vũ Mai Phương',      'class' => '11A2', 'major' => 'Chuyên Lý',   'talent_score' => 90, 'primary_field' => 'Nghệ thuật', 'hours' => 118],
    ['id' => 7, 'name' => 'Hoàng Kim Liên',     'class' => '12B3', 'major' => 'Chuyên Hoá',  'talent_score' => 89, 'primary_field' => 'Kinh doanh', 'hours' => 122],
    ['id' => 8, 'name' => 'Ngô Tấn Phát',       'class' => '11A1', 'major' => 'Chuyên Toán', 'talent_score' => 88, 'primary_field' => 'Kỹ thuật',   'hours' => 110],
    ['id' => 9, 'name' => 'Bùi Thanh Hà',       'class' => '11A1', 'major' => 'Chuyên Toán', 'talent_score' => 87, 'primary_field' => 'Kỹ thuật',   'hours' => 108],
    ['id' => 10,'name' => 'Dương Quốc Bảo',     'class' => '12A1', 'major' => 'Chuyên Tin',  'talent_score' => 87, 'primary_field' => 'Kỹ thuật',   'hours' => 134],
    ['id' => 11,'name' => 'Đinh Ngọc Anh',      'class' => '10C1', 'major' => 'Chuyên Anh',  'talent_score' => 85, 'primary_field' => 'Nghệ thuật', 'hours' => 96],
    ['id' => 12,'name' => 'Trịnh Thành Long',   'class' => '12B3', 'major' => 'Chuyên Hoá',  'talent_score' => 85, 'primary_field' => 'Học thuật',  'hours' => 112],
    ['id' => 13,'name' => 'Nguyễn Hà Trang',    'class' => '10C1', 'major' => 'Chuyên Anh',  'talent_score' => 84, 'primary_field' => 'Nghệ thuật', 'hours' => 92],
    ['id' => 14,'name' => 'Phan Thanh Tùng',    'class' => '11A2', 'major' => 'Chuyên Lý',   'talent_score' => 83, 'primary_field' => 'Thể thao',   'hours' => 105],
    ['id' => 15,'name' => 'Lý Thanh Huyền',     'class' => '12B3', 'major' => 'Chuyên Hoá',  'talent_score' => 83, 'primary_field' => 'Học thuật',  'hours' => 100],
    ['id' => 16,'name' => 'Đào Minh Châu',      'class' => '11A1', 'major' => 'Chuyên Toán', 'talent_score' => 82, 'primary_field' => 'Kinh doanh', 'hours' => 98],
    ['id' => 17,'name' => 'Tô Khánh Linh',      'class' => '10C1', 'major' => 'Chuyên Anh',  'talent_score' => 81, 'primary_field' => 'Nghệ thuật', 'hours' => 88],
    ['id' => 18,'name' => 'Võ Hồng Sơn',        'class' => '12A1', 'major' => 'Chuyên Tin',  'talent_score' => 80, 'primary_field' => 'Thể thao',   'hours' => 115],
    ['id' => 19,'name' => 'Bùi Gia Khang',      'class' => '11A2', 'major' => 'Chuyên Lý',   'talent_score' => 79, 'primary_field' => 'Kỹ thuật',   'hours' => 90],
    ['id' => 20,'name' => 'Trương Mỹ Linh',     'class' => '12B3', 'major' => 'Chuyên Hoá',  'talent_score' => 78, 'primary_field' => 'Kinh doanh', 'hours' => 95]
];

// --------------------------------------------------------------------------
// 10. Reports (Danh sách báo cáo)
// --------------------------------------------------------------------------
$reports = [
    ['id' => 1, 'title' => 'Báo cáo năng lực Q2/2026',                 'date' => '15/06/2026', 'format' => 'PDF',  'size' => '2.4MB', 'category' => 'Năng lực',   'academic_year' => '2025-2026'],
    ['id' => 2, 'title' => 'Tổng kết hoạt động tháng 5',                'date' => '01/06/2026', 'format' => 'PDF',  'size' => '1.8MB', 'category' => 'Hoạt động',  'academic_year' => '2025-2026'],
    ['id' => 3, 'title' => 'Phân tích năng khiếu khối 11',              'date' => '20/05/2026', 'format' => 'XLSX', 'size' => '980KB', 'category' => 'Năng lực',   'academic_year' => '2025-2026'],
    ['id' => 4, 'title' => 'Báo cáo huy hiệu học sinh 2025-2026',       'date' => '10/05/2026', 'format' => 'PDF',  'size' => '3.1MB', 'category' => 'Huy hiệu',   'academic_year' => '2025-2026'],
    ['id' => 5, 'title' => 'Tổng kết hoạt động Đoàn trường quý 1/2026', 'date' => '08/04/2026', 'format' => 'PDF',  'size' => '2.0MB', 'category' => 'Hoạt động',  'academic_year' => '2025-2026'],
    ['id' => 6, 'title' => 'Báo cáo năng lực Q1/2026',                  'date' => '15/03/2026', 'format' => 'PDF',  'size' => '2.6MB', 'category' => 'Năng lực',   'academic_year' => '2025-2026'],
    ['id' => 7, 'title' => 'Báo cáo tổng kết năm học 2024-2025',         'date' => '30/05/2025', 'format' => 'PDF',  'size' => '4.2MB', 'category' => 'Tổng hợp',   'academic_year' => '2024-2025'],
    ['id' => 8, 'title' => 'Phân tích năng khiếu khối 12 niên khóa cũ', 'date' => '20/05/2025', 'format' => 'XLSX', 'size' => '1.1MB', 'category' => 'Năng lực',   'academic_year' => '2024-2025']
];

// Unique values for filter dropdowns
$reportCategories = array_values(array_unique(array_column($reports, 'category')));
sort($reportCategories);

$academicYears = array_values(array_unique(array_column($reports, 'academic_year')));
rsort($academicYears);

// --------------------------------------------------------------------------
// 11. Classes by Grade (for Classes page)
// --------------------------------------------------------------------------
$classesByGrade = [
    12 => [
        ['name' => '12A1', 'major' => 'Chuyên Tin',  'students' => 38, 'avg_score' => 94, 'hours' => 286],
        ['name' => '12A2', 'major' => 'Chuyên Toán', 'students' => 36, 'avg_score' => 85, 'hours' => 242],
        ['name' => '12B1', 'major' => 'Chuyên Lý',   'students' => 37, 'avg_score' => 83, 'hours' => 228],
        ['name' => '12B2', 'major' => 'Chuyên Hoá',  'students' => 35, 'avg_score' => 82, 'hours' => 218],
        ['name' => '12B3', 'major' => 'Chuyên Hoá',  'students' => 40, 'avg_score' => 90, 'hours' => 264],
        ['name' => '12C1', 'major' => 'Chuyên Anh',  'students' => 34, 'avg_score' => 80, 'hours' => 198],
        ['name' => '12C2', 'major' => 'Chuyên Văn',  'students' => 33, 'avg_score' => 78, 'hours' => 184],
        ['name' => '12D1', 'major' => 'Cơ bản',      'students' => 36, 'avg_score' => 74, 'hours' => 168],
        ['name' => '12D2', 'major' => 'Cơ bản',      'students' => 35, 'avg_score' => 72, 'hours' => 162],
        ['name' => '12D3', 'major' => 'Cơ bản',      'students' => 36, 'avg_score' => 73, 'hours' => 165],
        ['name' => '12D4', 'major' => 'Cơ bản',      'students' => 35, 'avg_score' => 75, 'hours' => 170],
        ['name' => '12D5', 'major' => 'Cơ bản',      'students' => 37, 'avg_score' => 76, 'hours' => 175]
    ],
    11 => [
        ['name' => '11A1', 'major' => 'Chuyên Toán', 'students' => 37, 'avg_score' => 88, 'hours' => 252],
        ['name' => '11A2', 'major' => 'Chuyên Lý',   'students' => 36, 'avg_score' => 92, 'hours' => 278],
        ['name' => '11A3', 'major' => 'Chuyên Tin',  'students' => 38, 'avg_score' => 87, 'hours' => 246],
        ['name' => '11B1', 'major' => 'Chuyên Hoá',  'students' => 35, 'avg_score' => 82, 'hours' => 218],
        ['name' => '11B2', 'major' => 'Chuyên Sinh', 'students' => 36, 'avg_score' => 81, 'hours' => 210],
        ['name' => '11B3', 'major' => 'Chuyên Sinh', 'students' => 34, 'avg_score' => 79, 'hours' => 196],
        ['name' => '11C1', 'major' => 'Chuyên Anh',  'students' => 37, 'avg_score' => 83, 'hours' => 224],
        ['name' => '11C2', 'major' => 'Chuyên Văn',  'students' => 35, 'avg_score' => 80, 'hours' => 204],
        ['name' => '11C3', 'major' => 'Chuyên Sử',   'students' => 34, 'avg_score' => 78, 'hours' => 192],
        ['name' => '11D1', 'major' => 'Cơ bản',      'students' => 36, 'avg_score' => 73, 'hours' => 162],
        ['name' => '11D2', 'major' => 'Cơ bản',      'students' => 35, 'avg_score' => 72, 'hours' => 158],
        ['name' => '11D3', 'major' => 'Cơ bản',      'students' => 36, 'avg_score' => 71, 'hours' => 154]
    ],
    10 => [
        ['name' => '10A1', 'major' => 'Chuyên Toán', 'students' => 36, 'avg_score' => 84, 'hours' => 232],
        ['name' => '10A2', 'major' => 'Chuyên Lý',   'students' => 37, 'avg_score' => 82, 'hours' => 220],
        ['name' => '10B1', 'major' => 'Chuyên Hoá',  'students' => 35, 'avg_score' => 80, 'hours' => 208],
        ['name' => '10B2', 'major' => 'Chuyên Sinh', 'students' => 34, 'avg_score' => 78, 'hours' => 196],
        ['name' => '10C1', 'major' => 'Chuyên Anh',  'students' => 35, 'avg_score' => 86, 'hours' => 240],
        ['name' => '10C2', 'major' => 'Chuyên Văn',  'students' => 36, 'avg_score' => 81, 'hours' => 212],
        ['name' => '10C3', 'major' => 'Chuyên Sử',   'students' => 34, 'avg_score' => 76, 'hours' => 184],
        ['name' => '10D1', 'major' => 'Cơ bản',      'students' => 36, 'avg_score' => 70, 'hours' => 148],
        ['name' => '10D2', 'major' => 'Cơ bản',      'students' => 35, 'avg_score' => 69, 'hours' => 144],
        ['name' => '10D3', 'major' => 'Cơ bản',      'students' => 36, 'avg_score' => 71, 'hours' => 150],
        ['name' => '10D4', 'major' => 'Cơ bản',      'students' => 35, 'avg_score' => 72, 'hours' => 156],
        ['name' => '10D5', 'major' => 'Cơ bản',      'students' => 35, 'avg_score' => 73, 'hours' => 158]
    ]
];

// Top 10 students per class (sample for 12A1 + 11A2)
$classTopStudents = [
    '12A1' => [
        ['name' => 'Nguyễn Văn An',     'talent_score' => 96, 'primary_field' => 'Kỹ thuật'],
        ['name' => 'Trần Thị Bảo Ngọc', 'talent_score' => 94, 'primary_field' => 'Kỹ thuật'],
        ['name' => 'Đỗ Quang Huy',       'talent_score' => 91, 'primary_field' => 'Kỹ thuật'],
        ['name' => 'Dương Quốc Bảo',     'talent_score' => 87, 'primary_field' => 'Kỹ thuật'],
        ['name' => 'Võ Hồng Sơn',        'talent_score' => 80, 'primary_field' => 'Thể thao'],
        ['name' => 'Lý Mỹ Duyên',       'talent_score' => 79, 'primary_field' => 'Nghệ thuật'],
        ['name' => 'Phan Đức Anh',       'talent_score' => 78, 'primary_field' => 'Học thuật'],
        ['name' => 'Trần Ngọc Hà',       'talent_score' => 77, 'primary_field' => 'Kinh doanh'],
        ['name' => 'Lê Hoàng Phúc',      'talent_score' => 75, 'primary_field' => 'Thể thao'],
        ['name' => 'Ngô Bảo Trâm',       'talent_score' => 74, 'primary_field' => 'Nghệ thuật']
    ],
    '11A2' => [
        ['name' => 'Lê Quang Minh',      'talent_score' => 93, 'primary_field' => 'Học thuật'],
        ['name' => 'Vũ Mai Phương',      'talent_score' => 90, 'primary_field' => 'Nghệ thuật'],
        ['name' => 'Phan Thanh Tùng',    'talent_score' => 83, 'primary_field' => 'Thể thao'],
        ['name' => 'Bùi Gia Khang',      'talent_score' => 79, 'primary_field' => 'Kỹ thuật'],
        ['name' => 'Đặng Thùy Linh',     'talent_score' => 78, 'primary_field' => 'Học thuật'],
        ['name' => 'Tạ Quang Vinh',      'talent_score' => 77, 'primary_field' => 'Kinh doanh'],
        ['name' => 'Nguyễn Diệu Linh',   'talent_score' => 76, 'primary_field' => 'Nghệ thuật'],
        ['name' => 'Hồ Minh Quân',       'talent_score' => 75, 'primary_field' => 'Kỹ thuật'],
        ['name' => 'Phạm Khánh Vy',      'talent_score' => 74, 'primary_field' => 'Kinh doanh'],
        ['name' => 'Đào Thanh Tùng',     'talent_score' => 72, 'primary_field' => 'Thể thao']
    ],
    '12B3' => [
        ['name' => 'Phạm Hoàng Nam',     'talent_score' => 92, 'primary_field' => 'Học thuật'],
        ['name' => 'Hoàng Kim Liên',     'talent_score' => 89, 'primary_field' => 'Kinh doanh'],
        ['name' => 'Trịnh Thành Long',   'talent_score' => 85, 'primary_field' => 'Học thuật'],
        ['name' => 'Lý Thanh Huyền',     'talent_score' => 83, 'primary_field' => 'Học thuật'],
        ['name' => 'Trương Mỹ Linh',     'talent_score' => 78, 'primary_field' => 'Kinh doanh'],
        ['name' => 'Đỗ Khánh Linh',      'talent_score' => 77, 'primary_field' => 'Nghệ thuật'],
        ['name' => 'Ngô Quang Huy',      'talent_score' => 76, 'primary_field' => 'Kỹ thuật'],
        ['name' => 'Vũ Thanh Hằng',      'talent_score' => 75, 'primary_field' => 'Nghệ thuật'],
        ['name' => 'Trịnh Bảo Long',     'talent_score' => 73, 'primary_field' => 'Thể thao'],
        ['name' => 'Phan Hồng Nhung',    'talent_score' => 72, 'primary_field' => 'Kinh doanh']
    ]
];
