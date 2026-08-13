<?php
/**
 * TalentHub Enterprise - Internship Mock Data Provider
 * 
 * Note for Developers:
 * - This mock data structure is prepared for future mapping to database tables:
 *   `internship_posts`, `internship_requirements`, and `internship_applications`.
 * - When database/API integration is ready, replace this array with database fetch functions.
 */

$mockInternships = [
    [
        'id' => 1,
        'title' => 'Thực tập sinh Frontend Developer (React / TypeScript)',
        'field' => 'Công nghệ thông tin',
        'status' => 'active',
        'status_label' => 'Đang tuyển',
        'created_at' => '2026-08-01',
        'deadline' => '2026-08-30',
        'slots' => 5,
        'applicant_count' => 18,
        'work_type' => 'Full-time / Hybrid',
        'duration' => '3 tháng',
        'education_level' => 'Đại học / Cao đẳng',
        'description' => 'Tham gia cùng đội ngũ Frontend FPT Software phát triển các giao diện sản phẩm SaaS enterprise bằng React.js, TypeScript và CSS Modules. Được hướng dẫn trực tiếp từ các Senior Tech Lead.',
        'skills' => ['React', 'TypeScript', 'HTML/CSS', 'Git', 'REST API'],
        'benefits' => 'Trợ cấp thực tập 5.000.000 - 8.000.000 VNĐ/tháng. Hỗ trợ con dấu báo cáo thực tập. Cơ hội chuyển chính thức sau khi tốt nghiệp.'
    ],
    [
        'id' => 2,
        'title' => 'Thực tập sinh AI Research & Data Science 2026',
        'field' => 'AI / Machine Learning',
        'status' => 'active',
        'status_label' => 'Đang tuyển',
        'created_at' => '2026-08-05',
        'deadline' => '2026-08-20',
        'slots' => 3,
        'applicant_count' => 12,
        'work_type' => 'Full-time',
        'duration' => '6 tháng',
        'education_level' => 'Đại học',
        'description' => 'Tham gia nghiên cứu và xây dựng mô hình Học máy, xử lý ngôn ngữ tự nhiên (NLP) và Thị giác máy tính (Computer Vision) phục vụ cho dự án AI Enterprise.',
        'skills' => ['Python', 'PyTorch', 'SQL', 'Data Analytics', 'TensorFlow'],
        'benefits' => 'Trợ cấp thực tập 8.000.000 - 12.000.000 VNĐ/tháng. Tham gia công bố bài báo khoa học cùng giảng viên và chuyên gia AI.'
    ],
    [
        'id' => 3,
        'title' => 'Thực tập sinh Lập trình Backend (PHP / Laravel / Node.js)',
        'field' => 'Công nghệ thông tin',
        'status' => 'active',
        'status_label' => 'Đang tuyển',
        'created_at' => '2026-07-25',
        'deadline' => '2026-08-25',
        'slots' => 4,
        'applicant_count' => 14,
        'work_type' => 'Full-time / On-site',
        'duration' => '3 tháng',
        'education_level' => 'Đại học / Cao đẳng',
        'description' => 'Thiết kế RESTful APIs, quản lý cơ sở dữ liệu MySQL/PostgreSQL và tối ưu truy vấn hệ thống Backend cho các dịch vụ quy mô lớn.',
        'skills' => ['PHP', 'Laravel', 'Node.js', 'MySQL', 'Docker'],
        'benefits' => 'Trợ cấp 6.000.000 VNĐ/tháng. Phụ cấp ăn trưa tại FPT Tower. Cấp máy tính xách tay cấu hình cao.'
    ],
    [
        'id' => 4,
        'title' => 'Thực tập sinh Thiết kế UI/UX & Product Design',
        'field' => 'Thiết kế UI/UX',
        'status' => 'draft',
        'status_label' => 'Bản nháp',
        'created_at' => '2026-08-10',
        'deadline' => '2026-09-15',
        'slots' => 2,
        'applicant_count' => 0,
        'work_type' => 'Bán thời gian / Remote',
        'duration' => '3 tháng',
        'education_level' => 'Tất cả bậc học',
        'description' => 'Thiết kế Wireframe, UI Kit và Prototype giao diện người dùng trên Figma cho các hệ thống ứng dụng quản trị doanh nghiệp.',
        'skills' => ['Figma', 'UI/UX Design', 'Wireframing', 'Prototyping'],
        'benefits' => 'Thời gian linh hoạt phù hợp lịch học. Trợ cấp 4.000.000 - 6.000.000 VNĐ/tháng.'
    ],
    [
        'id' => 5,
        'title' => 'Thực tập sinh Digital Marketing & Content TalentHub',
        'field' => 'Marketing Digital',
        'status' => 'closed',
        'status_label' => 'Đã đóng',
        'created_at' => '2026-06-15',
        'deadline' => '2026-07-30',
        'slots' => 3,
        'applicant_count' => 25,
        'work_type' => 'Full-time',
        'duration' => '3 tháng',
        'education_level' => 'Đại học / Cao đẳng',
        'description' => 'Xây dựng nội dung truyền thông tuyển dụng thương hiệu FPT Software trên các kênh mạng xã hội và quản lý chiến dịch kết nối tài năng sinh viên.',
        'skills' => ['Marketing', 'Content Writing', 'Social Media', 'SEO', 'Communication'],
        'benefits' => 'Đã tuyển đủ số lượng ứng viên đợt 1 năm 2026.'
    ]
];

/**
 * Fetch all mock internship posts
 */
function getMockInternships() {
    global $mockInternships;
    return $mockInternships;
}

/**
 * Fetch a single internship post by ID
 */
function getMockInternshipById($id) {
    global $mockInternships;
    foreach ($mockInternships as $post) {
        if ($post['id'] === intval($id)) {
            return $post;
        }
    }
    return null;
}

/**
 * Compute metric totals
 */
function getInternshipMetrics() {
    global $mockInternships;
    $total = count($mockInternships);
    $active = 0;
    $draft = 0;
    $closed = 0;

    foreach ($mockInternships as $post) {
        if ($post['status'] === 'active') $active++;
        elseif ($post['status'] === 'draft') $draft++;
        elseif ($post['status'] === 'closed') $closed++;
    }

    return [
        'total' => $total,
        'active' => $active,
        'draft' => $draft,
        'closed' => $closed
    ];
}
