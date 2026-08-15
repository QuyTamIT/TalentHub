<?php
/**
 * TalentHub Enterprise - Internship Applicants Mock Data Provider
 * 
 * Note for Developers:
 * - This mock dataset simulates data from tables: `internship_applications`, 
 *   `application_status_history`, `student_profiles`, and `student_skills`.
 * - Prepares field structure for future database integration:
 *   - `id`: internship_applications.id
 *   - `post_id`: internship_applications.internship_id
 *   - `student_id`: student_profiles.id (maps directly to Talent Passport /app/enterprise/talents/detail.php?id=...)
 *   - `status`: internship_applications.status (new | reviewing | interviewing | accepted | rejected)
 *   - `reviewer_note`: internship_applications.reviewer_note
 *   - `match_score`: percentage match for THIS specific job post (rendered as "% phù hợp")
 */

$mockApplicantsByPost = [
    // Post #1: Thực tập sinh Frontend Developer (React / TypeScript)
    1 => [
        [
            'id' => 101,
            'post_id' => 1,
            'student_id' => 1,
            'name' => 'Nguyễn Văn An',
            'avatar_initials' => 'AN',
            'school' => 'Đại học Bách Khoa Hà Nội',
            'class_code' => 'ĐTVT01 - K63',
            'education_level' => 'Đại học (Năm 4)',
            'location' => 'Hà Nội',
            'experience_hours' => 120,
            'applied_at' => '2026-08-10 09:30',
            'match_score' => 95,
            'status' => 'new',
            'status_label' => 'Mới',
            'main_skills' => ['React', 'TypeScript', 'HTML/CSS', 'Git', 'REST API'],
            'matching_skills' => ['React', 'TypeScript', 'HTML/CSS', 'Git', 'REST API'],
            'missing_requirements' => [],
            'reviewer_note' => '',
            'reviewer_id' => null,
            'resume_file' => 'CV_NguyenVanAn_Frontend.pdf'
        ],
        [
            'id' => 102,
            'post_id' => 1,
            'student_id' => 3,
            'name' => 'Trần Minh Đức',
            'avatar_initials' => 'MĐ',
            'school' => 'Đại học FPT',
            'class_code' => 'SE1601 - K16',
            'education_level' => 'Đại học (Năm 4)',
            'location' => 'Hà Nội',
            'experience_hours' => 150,
            'applied_at' => '2026-08-09 14:15',
            'match_score' => 88,
            'status' => 'reviewing',
            'status_label' => 'Đang xem xét',
            'main_skills' => ['React', 'TypeScript', 'Vue.js', 'Git', 'REST API'],
            'matching_skills' => ['React', 'Git', 'REST API'],
            'missing_requirements' => ['Kinh nghiệm nâng cao với TypeScript'],
            'reviewer_note' => 'Ứng viên có nền tảng Web chắc chắn. Cần kiểm tra thêm khả năng làm việc với TypeScript.',
            'reviewer_id' => 1,
            'resume_file' => 'CV_TranMinhDuc_WebDev.pdf'
        ],
        [
            'id' => 103,
            'post_id' => 1,
            'student_id' => 5,
            'name' => 'Vũ Mai Phương',
            'avatar_initials' => 'MP',
            'school' => 'Đại học Công nghệ - ĐHQGHN',
            'class_code' => 'CNTT1 - K65',
            'education_level' => 'Đại học (Năm 3)',
            'location' => 'Hà Nội',
            'experience_hours' => 110,
            'applied_at' => '2026-08-07 11:20',
            'match_score' => 92,
            'status' => 'interviewing',
            'status_label' => 'Phỏng vấn',
            'main_skills' => ['React', 'TypeScript', 'UI/UX Design', 'Figma', 'HTML/CSS'],
            'matching_skills' => ['React', 'TypeScript', 'HTML/CSS'],
            'missing_requirements' => ['Chưa có chứng chỉ Git nâng cao'],
            'reviewer_note' => 'Đã hẹn lịch phỏng vấn chuyên môn 14:00 ngày 16/08/2026 qua Google Meet.',
            'reviewer_id' => 1,
            'resume_file' => 'CV_VuMaiPhuong_FE_UI.pdf'
        ],
        [
            'id' => 104,
            'post_id' => 1,
            'student_id' => 2,
            'name' => 'Lê Thị Bích Ngọc',
            'avatar_initials' => 'BN',
            'school' => 'Đại học Quốc Gia TP.HCM',
            'class_code' => 'KHDL02 - K21',
            'education_level' => 'Đại học (Năm 3)',
            'location' => 'TP. Hồ Chí Minh',
            'experience_hours' => 95,
            'applied_at' => '2026-08-05 16:45',
            'match_score' => 94,
            'status' => 'accepted',
            'status_label' => 'Đã nhận',
            'main_skills' => ['React', 'TypeScript', 'JavaScript', 'Git', 'REST API'],
            'matching_skills' => ['React', 'TypeScript', 'Git', 'REST API'],
            'missing_requirements' => [],
            'reviewer_note' => 'Đạt xuất sắc bài test kỹ thuật và phỏng vấn. Nhận thực tập đợt 1 bắt đầu từ 01/09/2026.',
            'reviewer_id' => 1,
            'resume_file' => 'CV_LeThiBichNgoc_React.pdf'
        ],
        [
            'id' => 105,
            'post_id' => 1,
            'student_id' => 4,
            'name' => 'Phạm Hoàng Nam',
            'avatar_initials' => 'HN',
            'school' => 'THPT chuyên Hà Nội - Amsterdam',
            'class_code' => 'Chuyên Tin 12',
            'education_level' => 'THPT',
            'location' => 'Hà Nội',
            'experience_hours' => 80,
            'applied_at' => '2026-08-03 10:00',
            'match_score' => 68,
            'status' => 'rejected',
            'status_label' => 'Từ chối',
            'main_skills' => ['C++', 'Python', 'Algorithm', 'HTML/CSS'],
            'matching_skills' => ['HTML/CSS'],
            'missing_requirements' => ['Chưa đáp ứng yêu cầu kinh nghiệm React & TypeScript'],
            'reviewer_note' => 'Tư duy thuật toán tốt nhưng thiếu kinh nghiệm thực chiến với React/TypeScript.',
            'reviewer_id' => 1,
            'resume_file' => 'CV_PhamHoangNam.pdf'
        ]
    ],

    // Post #2: Thực tập sinh AI Research & Data Science 2026
    2 => [
        [
            'id' => 201,
            'post_id' => 2,
            'student_id' => 2,
            'name' => 'Lê Thị Bích Ngọc',
            'avatar_initials' => 'BN',
            'school' => 'Đại học Quốc Gia TP.HCM',
            'class_code' => 'KHDL02 - K21',
            'education_level' => 'Đại học (Năm 3)',
            'location' => 'TP. Hồ Chí Minh',
            'experience_hours' => 95,
            'applied_at' => '2026-08-11 08:45',
            'match_score' => 96,
            'status' => 'reviewing',
            'status_label' => 'Đang xem xét',
            'main_skills' => ['Python', 'PyTorch', 'SQL', 'Data Analytics', 'TensorFlow'],
            'matching_skills' => ['Python', 'PyTorch', 'SQL', 'Data Analytics', 'TensorFlow'],
            'missing_requirements' => [],
            'reviewer_note' => 'Hồ sơ phù hợp tuyệt đối với yêu cầu AI Research. Chuẩn bị mời phỏng vấn với AI Lead.',
            'reviewer_id' => 1,
            'resume_file' => 'CV_LeThiBichNgoc_AI.pdf'
        ],
        [
            'id' => 202,
            'post_id' => 2,
            'student_id' => 4,
            'name' => 'Phạm Hoàng Nam',
            'avatar_initials' => 'HN',
            'school' => 'THPT chuyên Hà Nội - Amsterdam',
            'class_code' => 'Chuyên Tin 12',
            'education_level' => 'THPT',
            'location' => 'Hà Nội',
            'experience_hours' => 80,
            'applied_at' => '2026-08-08 15:30',
            'match_score' => 90,
            'status' => 'new',
            'status_label' => 'Mới',
            'main_skills' => ['Python', 'PyTorch', 'C++', 'Algorithmic AI', 'Mathematics'],
            'matching_skills' => ['Python', 'PyTorch'],
            'missing_requirements' => ['Cần bổ sung kinh nghiệm xử lý SQL cơ sở dữ liệu lớn'],
            'reviewer_note' => '',
            'reviewer_id' => null,
            'resume_file' => 'CV_PhamHoangNam_AI.pdf'
        ],
        [
            'id' => 203,
            'post_id' => 2,
            'student_id' => 1,
            'name' => 'Nguyễn Văn An',
            'avatar_initials' => 'AN',
            'school' => 'Đại học Bách Khoa Hà Nội',
            'class_code' => 'ĐTVT01 - K63',
            'education_level' => 'Đại học (Năm 4)',
            'location' => 'Hà Nội',
            'experience_hours' => 120,
            'applied_at' => '2026-08-06 10:15',
            'match_score' => 75,
            'status' => 'interviewing',
            'status_label' => 'Phỏng vấn',
            'main_skills' => ['Python', 'SQL', 'React', 'Node.js'],
            'matching_skills' => ['Python', 'SQL'],
            'missing_requirements' => ['Thiếu kinh nghiệm chuyên sâu PyTorch / TensorFlow'],
            'reviewer_note' => 'Ứng viên thiên về Web, nhưng có kiến thức nền tảng Python & SQL tốt.',
            'reviewer_id' => 1,
            'resume_file' => 'CV_NguyenVanAn_AI.pdf'
        ]
    ],

    // Post #3: Thực tập sinh Lập trình Backend (PHP / Laravel / Node.js)
    3 => [
        [
            'id' => 301,
            'post_id' => 3,
            'student_id' => 3,
            'name' => 'Trần Minh Đức',
            'avatar_initials' => 'MĐ',
            'school' => 'Đại học FPT',
            'class_code' => 'SE1601 - K16',
            'education_level' => 'Đại học (Năm 4)',
            'location' => 'Hà Nội',
            'experience_hours' => 150,
            'applied_at' => '2026-08-09 10:00',
            'match_score' => 96,
            'status' => 'accepted',
            'status_label' => 'Đã nhận',
            'main_skills' => ['PHP', 'Laravel', 'Node.js', 'MySQL', 'Docker'],
            'matching_skills' => ['PHP', 'Laravel', 'Node.js', 'MySQL', 'Docker'],
            'missing_requirements' => [],
            'reviewer_note' => 'Đã có chứng chỉ Laravel và kinh nghiệm làm dự án thực tế 150 giờ. Trúng tuyển đợt 1.',
            'reviewer_id' => 1,
            'resume_file' => 'CV_TranMinhDuc_Backend.pdf'
        ],
        [
            'id' => 302,
            'post_id' => 3,
            'student_id' => 1,
            'name' => 'Nguyễn Văn An',
            'avatar_initials' => 'AN',
            'school' => 'Đại học Bách Khoa Hà Nội',
            'class_code' => 'ĐTVT01 - K63',
            'education_level' => 'Đại học (Năm 4)',
            'location' => 'Hà Nội',
            'experience_hours' => 120,
            'applied_at' => '2026-08-07 14:20',
            'match_score' => 86,
            'status' => 'reviewing',
            'status_label' => 'Đang xem xét',
            'main_skills' => ['Node.js', 'REST API', 'MySQL', 'React', 'TypeScript'],
            'matching_skills' => ['Node.js', 'MySQL'],
            'missing_requirements' => ['Cần trau dồi thêm về PHP & Laravel'],
            'reviewer_note' => 'Đang cân nhắc chuyển sang vị trí Full-Stack hoặc Backend Node.js.',
            'reviewer_id' => 1,
            'resume_file' => 'CV_NguyenVanAn_NodeJS.pdf'
        ]
    ],

    // Post #4: Thực tập sinh Thiết kế UI/UX & Product Design (Draft post - 0 applicants)
    4 => [],

    // Post #5: Thực tập sinh Digital Marketing & Content (Closed post - 1 applicant)
    5 => [
        [
            'id' => 501,
            'post_id' => 5,
            'student_id' => 5,
            'name' => 'Vũ Mai Phương',
            'avatar_initials' => 'MP',
            'school' => 'Đại học Công nghệ - ĐHQGHN',
            'class_code' => 'CNTT1 - K65',
            'education_level' => 'Đại học (Năm 3)',
            'location' => 'Hà Nội',
            'experience_hours' => 110,
            'applied_at' => '2026-07-20 09:00',
            'match_score' => 85,
            'status' => 'accepted',
            'status_label' => 'Đã nhận',
            'main_skills' => ['Content Writing', 'UI/UX Design', 'Social Media', 'Communication'],
            'matching_skills' => ['Content Writing', 'Social Media', 'Communication'],
            'missing_requirements' => [],
            'reviewer_note' => 'Đã hoàn thành thủ tục tiếp nhận thực tập.',
            'reviewer_id' => 1,
            'resume_file' => 'CV_VuMaiPhuong_Marketing.pdf'
        ]
    ]
];

/**
 * Fetch mock applicants for a given internship post ID
 */
function getMockApplicantsByPostId($postId) {
    global $mockApplicantsByPost;
    $postId = intval($postId);
    return isset($mockApplicantsByPost[$postId]) ? $mockApplicantsByPost[$postId] : [];
}

/**
 * Compute counts for pipeline status tabs for a given post
 */
function getApplicantPipelineCounts($postId) {
    $applicants = getMockApplicantsByPostId($postId);
    $counts = [
        'all' => count($applicants),
        'new' => 0,
        'reviewing' => 0,
        'interviewing' => 0,
        'accepted' => 0,
        'rejected' => 0
    ];

    foreach ($applicants as $app) {
        $st = $app['status'];
        if (isset($counts[$st])) {
            $counts[$st]++;
        }
    }

    return $counts;
}
