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
    ['label' => 'AI gợi ý', 'route' => '/app/learner/ai-suggestions.php', 'icon' => 'sparkles', 'implemented' => false],
    ['label' => 'Huy hiệu', 'route' => '/app/learner/badges.php', 'icon' => 'award', 'implemented' => false],
    ['label' => 'Thống kê', 'route' => '/app/learner/statistics.php', 'icon' => 'chart', 'implemented' => false],
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
