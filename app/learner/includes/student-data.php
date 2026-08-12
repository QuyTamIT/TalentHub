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
    ['label' => 'Hoạt động', 'route' => '/app/learner/activities.php', 'icon' => 'calendar', 'implemented' => false],
    ['label' => 'Check-in QR', 'route' => '/app/learner/check-in.php', 'icon' => 'qr', 'implemented' => false],
    ['label' => 'Đánh giá', 'route' => '/app/learner/evaluations.php', 'icon' => 'clipboard', 'implemented' => false],
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

$activities = [
    ['id' => 'iot-lab', 'category' => 'Kỹ thuật', 'tone' => 'primary', 'title' => 'IoT Lab — Cảm biến thông minh', 'time' => 'Th 6, 14:00', 'location' => 'Phòng B305'],
    ['id' => 'startup-pitch', 'category' => 'Kinh doanh', 'tone' => 'secondary', 'title' => 'Startup Pitch Night', 'time' => 'Th 7, 18:30', 'location' => 'Hall A'],
    ['id' => 'drone-workshop', 'category' => 'Sáng tạo', 'tone' => 'success', 'title' => 'Drone Workshop', 'time' => 'CN, 09:00', 'location' => 'Sân vận động'],
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
