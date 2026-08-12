<?php
/**
 * TalentHub Learner mock data.
 *
 * Replace these arrays with a repository or API response when a backend is
 * introduced. Keep presentation concerns out of this file.
 */

if (!function_exists('learner_escape')) {
    function learner_escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$student = [
    'name' => 'Nguyễn Văn A',
    'initials' => 'A',
    'class' => 'Lớp 11A2',
    'school' => 'Trường THPT Nguyễn Du',
    'email' => 'a.nguyen@school.edu.vn',
    'location' => 'Hà Nội',
    'verified' => true,
    'streak_days' => 7,
    'experience_hours' => 64,
];
$learnerNav = [
    ['label' => 'Tổng quan', 'route' => '/app/learner/index.php', 'icon' => 'grid', 'implemented' => true],
    ['label' => 'Hồ sơ năng lực', 'route' => '/app/learner/profile.php', 'icon' => 'user', 'implemented' => true],
    ['label' => 'Khám phá năng khiếu', 'route' => '/app/learner/discover.php', 'icon' => 'compass', 'implemented' => true],
    ['label' => 'Hoạt động', 'route' => '/app/learner/activities.php', 'icon' => 'calendar', 'implemented' => true],
    ['label' => 'Check-in QR', 'route' => '/app/learner/checkin.php', 'icon' => 'qr', 'implemented' => true],
    ['label' => 'Đánh giá', 'route' => '/app/learner/evaluation.php', 'icon' => 'clipboard', 'implemented' => true],
    ['label' => 'AI gợi ý', 'route' => '/app/learner/ai-recommendations.php', 'icon' => 'sparkles', 'implemented' => true],
    ['label' => 'Huy hiệu', 'route' => '/app/learner/badges.php', 'icon' => 'award', 'implemented' => true],
    ['label' => 'Thống kê', 'route' => '/app/learner/statistics.php', 'icon' => 'chart', 'implemented' => true],
];

$level = [
    'name' => 'Innovator',
    'number' => 2,
    'progress' => 64,
    'target' => 100,
    'next_level' => 'Expert',
];

$dashboardKpis = [
    ['label' => 'Điểm năng lực', 'value' => '92', 'change' => '+8', 'icon' => 'star'],
    ['label' => 'Huy hiệu đạt được', 'value' => '12', 'change' => '+2', 'icon' => 'trophy'],
    ['label' => 'Giờ trải nghiệm', 'value' => '64h', 'change' => '+18h', 'icon' => 'clock'],
    ['label' => 'Xếp hạng lớp', 'value' => '#7', 'change' => '↑3', 'icon' => 'chart'],
];

$profileKpis = [
    ['label' => 'Điểm năng lực', 'value' => '92'],
    ['label' => 'Huy hiệu', 'value' => '12'],
    ['label' => 'Dự án', 'value' => '8'],
];

$skills = [
    ['name' => 'IoT', 'score' => 85, 'level' => 'Tốt', 'tone' => 'primary', 'icon' => 'sparkles'],
    ['name' => 'Lập trình Python', 'short_name' => 'Lập trình', 'score' => 90, 'level' => 'Rất tốt', 'tone' => 'secondary', 'icon' => 'trophy'],
    ['name' => 'Làm việc nhóm', 'score' => 88, 'level' => 'Tốt', 'tone' => 'success', 'icon' => 'users'],
    ['name' => 'Thiết kế UI', 'score' => 70, 'level' => 'Tốt', 'tone' => 'secondary', 'icon' => 'palette'],
    ['name' => 'Thuyết trình', 'score' => 72, 'level' => 'Trung bình', 'tone' => 'warning', 'icon' => 'trophy'],
    ['name' => 'Tiếng Anh', 'score' => 80, 'level' => 'Tốt', 'tone' => 'secondary', 'icon' => 'message-circle'],
];

$activityCategories = ['Tất cả', 'Kỹ thuật', 'Kinh doanh', 'Sáng tạo', 'Cộng đồng'];

$activityCatalog = [
    ['id' => 'iot-lab', 'category' => 'Kỹ thuật', 'filter_category' => 'Kỹ thuật', 'tone' => 'primary', 'title' => 'IoT Lab — Cảm biến thông minh', 'time' => 'Th 6, 14:00', 'location' => 'Phòng B305', 'participants' => 38, 'capacity' => 50],
    ['id' => 'drone-workshop', 'category' => 'Sáng tạo', 'filter_category' => 'Sáng tạo', 'tone' => 'secondary', 'title' => 'Drone Workshop', 'time' => 'CN, 09:00', 'location' => 'Sân vận động', 'participants' => 18, 'capacity' => 20],
    ['id' => 'startup-pitch', 'category' => 'Kinh doanh', 'filter_category' => 'Kinh doanh', 'tone' => 'success', 'title' => 'Startup Club — Pitch Night', 'time' => 'Th 7, 18:30', 'location' => 'Hall A', 'participants' => 12, 'capacity' => 30],
    ['id' => 'ai-bootcamp', 'category' => 'Công nghệ', 'filter_category' => 'Kỹ thuật', 'tone' => 'primary', 'title' => 'AI Bootcamp', 'time' => 'T2, 09:00', 'location' => 'Phòng IT', 'participants' => 25, 'capacity' => 40],
    ['id' => 'design-thinking', 'category' => 'Sáng tạo', 'filter_category' => 'Sáng tạo', 'tone' => 'secondary', 'title' => 'Design Thinking Lab', 'time' => 'T4, 15:00', 'location' => 'Studio C', 'participants' => 9, 'capacity' => 25],
    ['id' => 'charity-marathon', 'category' => 'Cộng đồng', 'filter_category' => 'Cộng đồng', 'tone' => 'success', 'title' => 'Marathon từ thiện', 'time' => 'CN, 06:00', 'location' => 'Hồ Tây', 'participants' => 67, 'capacity' => 100],
];

$activities = array_slice($activityCatalog, 0, 3);

$checkinHistory = [
    ['activity' => 'IoT Lab', 'time' => 'Hôm nay, 14:02', 'location' => 'Phòng B305', 'hours' => 2, 'confirmed' => true],
    ['activity' => 'Startup Club', 'time' => 'Hôm qua, 18:35', 'location' => 'Hall A', 'hours' => 1.5, 'confirmed' => true],
    ['activity' => 'Drone Workshop', 'time' => '12/06, 09:10', 'location' => 'Sân vận động', 'hours' => 3, 'confirmed' => true],
    ['activity' => 'AI Bootcamp', 'time' => '10/06, 09:05', 'location' => 'Phòng IT', 'hours' => 2, 'confirmed' => true],
];

$defaultEvaluationTerm = '2025-2026-2';
$evaluationTerms = [
    '2025-2026-2' => [
        'label' => 'Học kỳ II · 2025–2026',
        'status' => 'Đã công bố',
        'evaluation' => [
            'criteria' => [
                ['name' => 'Chuyên môn', 'score' => 36, 'max' => 40, 'tone' => 'primary'],
                ['name' => 'Sáng tạo', 'score' => 17, 'max' => 20, 'tone' => 'secondary'],
                ['name' => 'Kỷ luật', 'score' => 19, 'max' => 20, 'tone' => 'secondary'],
                ['name' => 'Làm việc nhóm', 'score' => 18, 'max' => 20, 'tone' => 'primary'],
            ],
            'total' => 90,
            'classification' => 'Xuất sắc',
            'ranking' => 'Top 12% học sinh khối 11',
            'comment' => 'A thể hiện khả năng tư duy hệ thống tốt, chủ động dẫn dắt nhóm trong dự án Smart Garden. Cần luyện thêm kỹ năng thuyết trình trước đám đông.',
            'reviewer' => 'Cô Lê Thị Hương, IoT Lab',
        ],
    ],
    '2025-2026-1' => [
        'label' => 'Học kỳ I · 2025–2026',
        'status' => 'Đã công bố',
        'evaluation' => [
            'criteria' => [
                ['name' => 'Chuyên môn', 'score' => 33, 'max' => 40, 'tone' => 'primary'],
                ['name' => 'Sáng tạo', 'score' => 16, 'max' => 20, 'tone' => 'secondary'],
                ['name' => 'Kỷ luật', 'score' => 18, 'max' => 20, 'tone' => 'secondary'],
                ['name' => 'Làm việc nhóm', 'score' => 17, 'max' => 20, 'tone' => 'primary'],
            ],
            'total' => 84,
            'classification' => 'Tốt',
            'ranking' => 'Top 20% học sinh khối 11',
            'comment' => 'A có nền tảng chuyên môn tốt và phối hợp nhóm tích cực. Hãy tiếp tục tăng tính chủ động trong phần trình bày.',
            'reviewer' => 'Thầy Trần Minh Anh, CLB Công nghệ',
        ],
    ],
    '2024-2025-2' => [
        'label' => 'Học kỳ II · 2024–2025',
        'status' => 'Chưa có dữ liệu',
        'evaluation' => null,
    ],
];

$certificates = [
    ['name' => 'Google IT Automation', 'issuer' => 'Coursera', 'year' => '2025', 'verified' => true],
    ['name' => 'IELTS 7.5', 'issuer' => 'British Council', 'year' => '2024', 'verified' => true],
    ['name' => 'Cisco IoT Fundamentals', 'issuer' => 'Cisco', 'year' => '2025', 'verified' => true],
];

$projects = [
    [
        'name' => 'Smart Garden IoT',
        'description' => 'Hệ thống tưới tự động dùng ESP32 + cảm biến độ ẩm.',
        'role' => 'Trưởng nhóm',
        'status' => 'Đã hoàn thành',
        'tone' => 'success',
    ],
    [
        'name' => 'EduTalent Hackathon 2025',
        'description' => 'Top 5 toàn quốc – ứng dụng quản lý hoạt động học sinh.',
        'role' => 'Lập trình viên',
        'status' => 'Đang triển khai',
        'tone' => 'warning',
    ],
];

$assessments = [
    ['id' => 'holland', 'name' => 'Holland', 'description' => 'Khám phá định hướng nghề nghiệp', 'icon' => 'compass', 'tone' => 'primary', 'state' => 'result', 'progress' => 100],
    ['id' => 'mbti', 'name' => 'MBTI', 'description' => '16 loại nhân cách', 'icon' => 'user', 'tone' => 'secondary', 'state' => 'result', 'progress' => 100],
    ['id' => 'disc', 'name' => 'DISC', 'description' => 'Hành vi & phong cách giao tiếp', 'icon' => 'users', 'tone' => 'success', 'state' => 'start', 'progress' => 0],
    ['id' => 'multiple-intelligence', 'name' => 'Đa trí thông minh', 'description' => '8 dạng trí thông minh', 'icon' => 'brain', 'tone' => 'warning', 'state' => 'continue', 'progress' => 65],
];

$assessmentResults = [
    'holland' => 'Nhóm nổi bật: Kỹ thuật, Nghiên cứu và Sáng tạo.',
    'mbti' => 'Kiểu tính cách nổi bật: ENTP – Người nhìn xa.',
    'disc' => 'Bài đánh giá gồm 24 câu hỏi, mất khoảng 8 phút.',
    'multiple-intelligence' => 'Bạn đã hoàn thành 65% bài đánh giá.',
];

$radarScores = [
    ['label' => 'Logic', 'score' => 72, 'icon' => 'brain', 'tone' => 'secondary'],
    ['label' => 'Sáng tạo', 'score' => 66, 'icon' => 'lightbulb', 'tone' => 'success'],
    ['label' => 'Vận động', 'score' => 58, 'icon' => 'activity', 'tone' => 'success'],
    ['label' => 'Giao tiếp', 'score' => 78, 'icon' => 'message-circle', 'tone' => 'secondary'],
    ['label' => 'Âm nhạc', 'score' => 62, 'icon' => 'music', 'tone' => 'warning'],
    ['label' => 'Tự nhiên', 'score' => 70, 'icon' => 'leaf', 'tone' => 'success'],
];

$careerDirections = [
    ['label' => 'Kỹ thuật', 'score' => 40, 'icon' => 'sparkles', 'tone' => 'secondary'],
    ['label' => 'Kinh doanh', 'score' => 30, 'icon' => 'briefcase', 'tone' => 'success'],
    ['label' => 'Học thuật', 'score' => 20, 'icon' => 'graduation-cap', 'tone' => 'secondary'],
    ['label' => 'Nghệ thuật', 'score' => 10, 'icon' => 'palette', 'tone' => 'warning'],
];

$aiRecommendation = [
    'sufficient' => true,
    'updated_at' => '12/08/2026',
    'summary' => 'Bạn có năng lực nổi bật về IoT và Drone. Khuyến nghị tham gia nhóm nghiên cứu tự động hóa và cuộc thi sáng tạo kỹ thuật.',
    'groups' => [
        [
            'title' => 'Điểm mạnh',
            'tone' => 'success',
            'icon' => 'chart',
            'items' => ['Tư duy logic, lập trình', 'Làm việc nhóm & dẫn dắt', 'Học nhanh công nghệ mới'],
        ],
        [
            'title' => 'Cần cải thiện',
            'tone' => 'primary',
            'icon' => 'activity',
            'items' => ['Thuyết trình trước đám đông', 'Quản lý thời gian dài hạn', 'Tiếng Anh học thuật'],
        ],
        [
            'title' => 'Khả năng phát triển',
            'tone' => 'secondary',
            'icon' => 'compass',
            'items' => ['Kỹ sư tự động hóa', 'Nghiên cứu AI/Robotics', 'Khởi nghiệp deep-tech'],
        ],
    ],
    'roadmap' => [
        ['month' => 'Tháng 1', 'action' => 'Hoàn thành khóa Arduino nâng cao và tham gia IoT Lab hằng tuần.'],
        ['month' => 'Tháng 2', 'action' => 'Đăng ký cuộc thi sáng tạo kỹ thuật cấp thành phố với đề tài tự động hóa nông nghiệp.'],
        ['month' => 'Tháng 3', 'action' => 'Mentor 2 dự án nhóm và viết blog kỹ thuật để rèn khả năng truyền đạt.'],
    ],
    'insufficient_title' => 'Chưa đủ dữ liệu để AI phân tích',
    'insufficient_copy' => 'Hãy hoàn thiện hồ sơ kỹ năng, bài đánh giá năng khiếu và ít nhất một hoạt động trải nghiệm.',
    'disclaimer' => 'Gợi ý từ AI chỉ mang tính định hướng, không thay thế đánh giá chuyên môn từ giáo viên và cố vấn.',
];

$learnerLevels = [
    ['id' => 'explorer', 'name' => 'Explorer', 'number' => 3, 'hours' => 10, 'state' => 'achieved', 'status' => 'Đã đạt'],
    ['id' => 'innovator', 'name' => 'Innovator', 'number' => 2, 'hours' => 64, 'target' => 100, 'state' => 'current', 'status' => 'Hiện tại'],
    ['id' => 'expert', 'name' => 'Expert', 'number' => 1, 'hours' => 36, 'state' => 'next', 'status' => 'Còn 36 giờ'],
    ['id' => 'master', 'name' => 'Master', 'number' => 0, 'hours' => 200, 'state' => 'locked', 'status' => '200 giờ'],
];

$learnerBadgeFilters = [
    ['id' => 'all', 'label' => 'Tất cả'],
    ['id' => 'achieved', 'label' => 'Đã đạt'],
    ['id' => 'in_progress', 'label' => 'Đang tiến hành'],
    ['id' => 'locked', 'label' => 'Chưa đạt'],
];

$learnerBadges = [
    ['id' => 'explorer', 'name' => 'Người khám phá', 'description' => 'Tham gia 3 hoạt động khám phá năng khiếu', 'icon' => 'compass', 'status' => 'achieved', 'status_label' => 'Đã đạt', 'current' => 3, 'target' => 3],
    ['id' => 'creator', 'name' => 'Nhà sáng tạo', 'description' => 'Hoàn thành 1 dự án sáng tạo', 'icon' => 'lightbulb', 'status' => 'in_progress', 'status_label' => 'Đang tiến hành', 'current' => 1, 'target' => 2],
    ['id' => 'team-player', 'name' => 'Đồng đội xuất sắc', 'description' => 'Hợp tác trong 3 hoạt động nhóm', 'icon' => 'users', 'status' => 'achieved', 'status_label' => 'Đã đạt', 'current' => 3, 'target' => 3],
    ['id' => 'young-leader', 'name' => 'Thủ lĩnh trẻ', 'description' => 'Đảm nhận vai trò trưởng nhóm 2 lần', 'icon' => 'trophy', 'status' => 'in_progress', 'status_label' => 'Đang tiến hành', 'current' => 1, 'target' => 2],
    ['id' => 'iot-expert', 'name' => 'Chuyên gia IoT', 'description' => 'Hoàn thành 2 khóa học liên quan đến IoT', 'icon' => 'bot', 'status' => 'locked', 'status_label' => 'Chưa đạt', 'current' => 0, 'target' => 2],
    ['id' => 'community', 'name' => 'Vì cộng đồng', 'description' => 'Tham gia 1 hoạt động tình nguyện', 'icon' => 'leaf', 'status' => 'locked', 'status_label' => 'Chưa đạt', 'current' => 0, 'target' => 1],
];

$defaultStatisticsPeriod = 'six-months';
$learnerStatisticsPeriods = [
    'six-months' => [
        'label' => '6 tháng gần nhất',
        'kpis' => [
            ['id' => 'hours', 'label' => 'Giờ trải nghiệm', 'value' => 64, 'suffix' => 'giờ', 'change' => '↑ 18% so với 6 tháng trước', 'icon' => 'clock', 'tone' => 'primary'],
            ['id' => 'badges', 'label' => 'Huy hiệu', 'value' => 12, 'suffix' => 'huy hiệu', 'change' => '↑ 2 huy hiệu mới', 'icon' => 'award', 'tone' => 'secondary'],
            ['id' => 'completed', 'label' => 'Hoạt động hoàn thành', 'value' => 8, 'suffix' => 'hoạt động', 'change' => '↑ 3 hoạt động', 'icon' => 'check', 'tone' => 'success'],
            ['id' => 'competency', 'label' => 'Điểm năng lực', 'value' => 92, 'suffix' => 'điểm năng lực', 'change' => '↑ 12 điểm', 'icon' => 'star', 'tone' => 'warning'],
        ],
        'experience' => ['labels' => ['T1', 'T2', 'T3', 'T4', 'T5', 'T6'], 'hours' => [6, 8, 12, 10, 18, 10], 'comparison' => [2, 3, 5, 5, 9, 6]],
        'fields' => [
            ['label' => 'Kỹ thuật', 'hours' => 32, 'percentage' => 50, 'tone' => 'primary'],
            ['label' => 'Học thuật', 'hours' => 14, 'percentage' => 22, 'tone' => 'secondary'],
            ['label' => 'Kinh doanh', 'hours' => 8, 'percentage' => 13, 'tone' => 'warning'],
            ['label' => 'Nghệ thuật', 'hours' => 6, 'percentage' => 9, 'tone' => 'accent'],
            ['label' => 'Cộng đồng', 'hours' => 4, 'percentage' => 6, 'tone' => 'neutral'],
        ],
        'skills' => [
            ['name' => 'IoT', 'score' => 86, 'level' => 'Level 4 · Nâng cao', 'icon' => 'bot', 'tone' => 'primary'],
            ['name' => 'Lập trình', 'score' => 72, 'level' => 'Level 3 · Trung cấp', 'icon' => 'activity', 'tone' => 'secondary'],
            ['name' => 'Làm việc nhóm', 'score' => 68, 'level' => 'Level 3 · Trung cấp', 'icon' => 'users', 'tone' => 'success'],
            ['name' => 'Thuyết trình', 'score' => 54, 'level' => 'Level 2 · Cơ bản', 'icon' => 'message-circle', 'tone' => 'warning'],
        ],
        'activities' => [
            ['id' => 'registered', 'label' => 'Đăng ký', 'value' => 14, 'change' => '↑ 3 so với 6 tháng trước', 'icon' => 'calendar', 'tone' => 'primary'],
            ['id' => 'checked-in', 'label' => 'Đã check-in', 'value' => 11, 'change' => '↑ 2 so với 6 tháng trước', 'icon' => 'clipboard', 'tone' => 'secondary'],
            ['id' => 'completed', 'label' => 'Hoàn thành', 'value' => 8, 'change' => '↑ 3 so với 6 tháng trước', 'icon' => 'check', 'tone' => 'success'],
            ['id' => 'cancelled', 'label' => 'Đã hủy', 'value' => 2, 'change' => '↓ 1 so với 6 tháng trước', 'icon' => 'x', 'tone' => 'danger'],
        ],
    ],
    'three-months' => [
        'label' => '3 tháng gần nhất',
        'kpis' => [
            ['id' => 'hours', 'label' => 'Giờ trải nghiệm', 'value' => 40, 'suffix' => 'giờ', 'change' => '↑ 12% so với 3 tháng trước', 'icon' => 'clock', 'tone' => 'primary'],
            ['id' => 'badges', 'label' => 'Huy hiệu', 'value' => 7, 'suffix' => 'huy hiệu', 'change' => '↑ 1 huy hiệu mới', 'icon' => 'award', 'tone' => 'secondary'],
            ['id' => 'completed', 'label' => 'Hoạt động hoàn thành', 'value' => 5, 'suffix' => 'hoạt động', 'change' => '↑ 2 hoạt động', 'icon' => 'check', 'tone' => 'success'],
            ['id' => 'competency', 'label' => 'Điểm năng lực', 'value' => 88, 'suffix' => 'điểm năng lực', 'change' => '↑ 8 điểm', 'icon' => 'star', 'tone' => 'warning'],
        ],
        'experience' => ['labels' => ['T4', 'T5', 'T6'], 'hours' => [12, 18, 10], 'comparison' => [5, 9, 6]],
        'fields' => [
            ['label' => 'Kỹ thuật', 'hours' => 20, 'percentage' => 50, 'tone' => 'primary'],
            ['label' => 'Học thuật', 'hours' => 9, 'percentage' => 23, 'tone' => 'secondary'],
            ['label' => 'Kinh doanh', 'hours' => 5, 'percentage' => 12, 'tone' => 'warning'],
            ['label' => 'Nghệ thuật', 'hours' => 4, 'percentage' => 10, 'tone' => 'accent'],
            ['label' => 'Cộng đồng', 'hours' => 2, 'percentage' => 5, 'tone' => 'neutral'],
        ],
        'skills' => [
            ['name' => 'IoT', 'score' => 86, 'level' => 'Level 4 · Nâng cao', 'icon' => 'bot', 'tone' => 'primary'],
            ['name' => 'Lập trình', 'score' => 72, 'level' => 'Level 3 · Trung cấp', 'icon' => 'activity', 'tone' => 'secondary'],
            ['name' => 'Làm việc nhóm', 'score' => 68, 'level' => 'Level 3 · Trung cấp', 'icon' => 'users', 'tone' => 'success'],
            ['name' => 'Thuyết trình', 'score' => 54, 'level' => 'Level 2 · Cơ bản', 'icon' => 'message-circle', 'tone' => 'warning'],
        ],
        'activities' => [
            ['id' => 'registered', 'label' => 'Đăng ký', 'value' => 8, 'change' => '↑ 2 so với kỳ trước', 'icon' => 'calendar', 'tone' => 'primary'],
            ['id' => 'checked-in', 'label' => 'Đã check-in', 'value' => 7, 'change' => '↑ 2 so với kỳ trước', 'icon' => 'clipboard', 'tone' => 'secondary'],
            ['id' => 'completed', 'label' => 'Hoàn thành', 'value' => 5, 'change' => '↑ 2 so với kỳ trước', 'icon' => 'check', 'tone' => 'success'],
            ['id' => 'cancelled', 'label' => 'Đã hủy', 'value' => 1, 'change' => '↓ 1 so với kỳ trước', 'icon' => 'x', 'tone' => 'danger'],
        ],
    ],
    'twelve-months' => [
        'label' => '12 tháng gần nhất',
        'kpis' => [
            ['id' => 'hours', 'label' => 'Giờ trải nghiệm', 'value' => 112, 'suffix' => 'giờ', 'change' => '↑ 24% so với năm trước', 'icon' => 'clock', 'tone' => 'primary'],
            ['id' => 'badges', 'label' => 'Huy hiệu', 'value' => 18, 'suffix' => 'huy hiệu', 'change' => '↑ 6 huy hiệu mới', 'icon' => 'award', 'tone' => 'secondary'],
            ['id' => 'completed', 'label' => 'Hoạt động hoàn thành', 'value' => 15, 'suffix' => 'hoạt động', 'change' => '↑ 7 hoạt động', 'icon' => 'check', 'tone' => 'success'],
            ['id' => 'competency', 'label' => 'Điểm năng lực', 'value' => 92, 'suffix' => 'điểm năng lực', 'change' => '↑ 15 điểm', 'icon' => 'star', 'tone' => 'warning'],
        ],
        'experience' => ['labels' => ['T7', 'T8', 'T9', 'T10', 'T11', 'T12', 'T1', 'T2', 'T3', 'T4', 'T5', 'T6'], 'hours' => [8, 9, 10, 7, 8, 9, 6, 8, 12, 10, 15, 10], 'comparison' => [4, 5, 6, 5, 6, 6, 4, 5, 7, 7, 10, 7]],
        'fields' => [
            ['label' => 'Kỹ thuật', 'hours' => 56, 'percentage' => 50, 'tone' => 'primary'],
            ['label' => 'Học thuật', 'hours' => 24, 'percentage' => 21, 'tone' => 'secondary'],
            ['label' => 'Kinh doanh', 'hours' => 14, 'percentage' => 13, 'tone' => 'warning'],
            ['label' => 'Nghệ thuật', 'hours' => 10, 'percentage' => 9, 'tone' => 'accent'],
            ['label' => 'Cộng đồng', 'hours' => 8, 'percentage' => 7, 'tone' => 'neutral'],
        ],
        'skills' => [
            ['name' => 'IoT', 'score' => 86, 'level' => 'Level 4 · Nâng cao', 'icon' => 'bot', 'tone' => 'primary'],
            ['name' => 'Lập trình', 'score' => 72, 'level' => 'Level 3 · Trung cấp', 'icon' => 'activity', 'tone' => 'secondary'],
            ['name' => 'Làm việc nhóm', 'score' => 68, 'level' => 'Level 3 · Trung cấp', 'icon' => 'users', 'tone' => 'success'],
            ['name' => 'Thuyết trình', 'score' => 54, 'level' => 'Level 2 · Cơ bản', 'icon' => 'message-circle', 'tone' => 'warning'],
        ],
        'activities' => [
            ['id' => 'registered', 'label' => 'Đăng ký', 'value' => 24, 'change' => '↑ 8 so với năm trước', 'icon' => 'calendar', 'tone' => 'primary'],
            ['id' => 'checked-in', 'label' => 'Đã check-in', 'value' => 20, 'change' => '↑ 7 so với năm trước', 'icon' => 'clipboard', 'tone' => 'secondary'],
            ['id' => 'completed', 'label' => 'Hoàn thành', 'value' => 15, 'change' => '↑ 7 so với năm trước', 'icon' => 'check', 'tone' => 'success'],
            ['id' => 'cancelled', 'label' => 'Đã hủy', 'value' => 3, 'change' => '↓ 2 so với năm trước', 'icon' => 'x', 'tone' => 'danger'],
        ],
    ],
];
