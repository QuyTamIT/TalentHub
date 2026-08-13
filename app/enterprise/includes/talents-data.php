<?php
/**
 * TalentHub Enterprise - Talent Mock Data Provider
 * 
 * Note for Developers:
 * - This mock data simulates join results from student_profiles, users, schools,
 *   classes, skills, student_skills, experience_logs, projects, certificates,
 *   privacy_consents, and contact_requests.
 * - Do NOT expose personal email or phone numbers per privacy guidelines.
 * - When database/API is integrated, replace this file with SQL queries or service layer.
 */

$mockTalents = [
    [
        'id' => 1,
        'name' => 'Nguyễn Văn An',
        'avatar_initials' => 'AN',
        'school' => 'Đại học Bách Khoa Hà Nội',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 4',
        'major_field' => 'Công nghệ Thông tin',
        'talent_score' => 95,
        'match_score' => 95,
        'experience_hours' => 120,
        'internship_status' => 'ready_now',
        'internship_status_label' => 'Sẵn sàng thực tập ngay',
        'skills' => ['React', 'Node.js', 'TypeScript', 'UI/UX', 'Communication', 'REST API'],
        'updated_at' => '2026-08-10',
        'saved' => true,
        'bio' => 'Sinh viên năm cuối ngành CNTT ĐH Bách Khoa Hà Nội với định hướng phát triển trở thành Full-Stack Web Developer. Đã thực hiện nhiều dự án thực tế trên nền tảng React & Node.js, có kinh nghiệm làm việc nhóm Agile/Scrum và tối ưu trải nghiệm người dùng.',
        'detailed_skills' => [
            ['name' => 'React', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'Node.js', 'level' => 'Trung cấp', 'verified' => true],
            ['name' => 'TypeScript', 'level' => 'Trung cấp', 'verified' => true],
            ['name' => 'UI/UX Design', 'level' => 'Trung cấp', 'verified' => false],
            ['name' => 'Communication', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'RESTful API', 'level' => 'Nâng cao', 'verified' => true]
        ],
        'experience_logs' => [
            [
                'title' => 'Thực án Hệ thống Quản lý Học tập TalentHub',
                'role' => 'Trưởng nhóm Frontend',
                'duration' => '03/2026 - 06/2026',
                'hours' => 70,
                'description' => 'Xây dựng giao diện người dùng responsive bằng React & TypeScript, tối ưu tốc độ tải trang và kết nối API real-time.'
            ],
            [
                'title' => 'Dự án Mã nguồn mở BK Code Club',
                'role' => 'Lập trình viên Web',
                'duration' => '10/2025 - 02/2026',
                'hours' => 50,
                'description' => 'Phát triển các module đăng ký và chấm bài tự động cho CLB Lập trình Bách Khoa Hà Nội.'
            ]
        ],
        'projects' => [
            [
                'name' => 'Hệ thống Quản lý Thực tập Sinh Enterprise',
                'description' => 'Nền tảng giúp Doanh nghiệp kết nối và theo dõi tiến độ thực tập của sinh viên CNTT.',
                'role' => 'Full-Stack Developer',
                'technologies' => ['React', 'Node.js', 'PostgreSQL', 'TailwindCSS'],
                'result' => 'Giải Nhất Dự án Sáng tạo Sinh viên BK 2026'
            ],
            [
                'name' => 'Ứng dụng Điểm danh Sinh viên qua Nhận diện Khuôn mặt',
                'description' => 'Hệ thống tự động điểm danh tự động tích hợp webcam và mô hình AI nhận diện.',
                'role' => 'Frontend & Integration Lead',
                'technologies' => ['TypeScript', 'Python', 'OpenCV', 'REST API'],
                'result' => 'Đã triển khai thử nghiệm cho 5 lớp học'
            ]
        ],
        'certificates' => [
            ['name' => 'Meta Front-End Developer Professional Certificate', 'issuer' => 'Coursera / Meta', 'issue_date' => '04/2026', 'verified' => true],
            ['name' => 'AWS Certified Cloud Practitioner', 'issuer' => 'Amazon Web Services', 'issue_date' => '01/2026', 'verified' => true],
            ['name' => 'IELTS Academic 7.5', 'issuer' => 'IDP Education', 'issue_date' => '09/2025', 'verified' => true]
        ],
        'readiness_summary' => [
            'status_label' => 'Sẵn sàng thực tập toàn thời gian / bán thời gian ngay',
            'strengths' => ['Tư duy logic tốt', 'Thành thạo React & Node.js', 'Kỹ năng giao tiếp và làm việc nhóm tốt'],
            'preferred_field' => 'Lập trình Web / Frontend / Full-Stack',
            'total_exp_hours' => '120h thực án thực tế'
        ]
    ],
    [
        'id' => 2,
        'name' => 'Lê Thị Bích Ngọc',
        'avatar_initials' => 'BN',
        'school' => 'Đại học Quốc Gia TP.HCM',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 3',
        'major_field' => 'Khoa học Dữ liệu & AI',
        'talent_score' => 92,
        'match_score' => 92,
        'experience_hours' => 95,
        'internship_status' => 'ready_now',
        'internship_status_label' => 'Sẵn sàng thực tập ngay',
        'skills' => ['Python', 'PyTorch', 'SQL', 'Data Analytics', 'Leadership', 'TensorFlow'],
        'updated_at' => '2026-08-11',
        'saved' => false,
        'bio' => 'Sinh viên năm 3 chuyên ngành Khoa học Dữ liệu ĐHQG TP.HCM. Đam mê phân tích dữ liệu lớn và xây dựng mô hình Học máy áp dụng trong tài chính và bán lẻ. Đã có kinh nghiệm xử lý tập dữ liệu thực tế và viết báo cáo phân tích trực quan.',
        'detailed_skills' => [
            ['name' => 'Python', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'PyTorch', 'level' => 'Trung cấp', 'verified' => true],
            ['name' => 'SQL', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'Data Analytics', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'Leadership', 'level' => 'Trung cấp', 'verified' => false],
            ['name' => 'TensorFlow', 'level' => 'Cơ bản', 'verified' => true]
        ],
        'experience_logs' => [
            [
                'title' => 'Thực án Phân tích Hành vi Khách hàng Thương mại Điện tử',
                'role' => 'Data Analyst Lead',
                'duration' => '01/2026 - 05/2026',
                'hours' => 60,
                'description' => 'Trích xuất và làm sạch 500k bản ghi giao dịch, xây dựng mô hình dự báo tỷ lệ rời bỏ khách hàng (Churn Rate).'
            ],
            [
                'title' => 'Nghiên cứu Mô hình Xử lý Ngôn ngữ Tự nhiên tiếng Việt',
                'role' => 'Nghiên cứu sinh sinh viên',
                'duration' => '09/2025 - 12/2025',
                'hours' => 35,
                'description' => 'Huấn luyện mô hình Phân loại Cảm xúc ý kiến người dùng ứng dụng mobile.'
            ]
        ],
        'projects' => [
            [
                'name' => 'Dashboard Phân tích Xu hướng Thị trường Bán lẻ',
                'description' => 'Báo cáo trực quan hóa dữ liệu kinh doanh real-time kết nối SQL Server và PowerBI.',
                'role' => 'Data Analyst',
                'technologies' => ['Python', 'Pandas', 'SQL', 'PowerBI'],
                'result' => 'Đạt Đánh giá Xếp loại Xuất sắc'
            ]
        ],
        'certificates' => [
            ['name' => 'Google Data Analytics Professional Certificate', 'issuer' => 'Coursera / Google', 'issue_date' => '03/2026', 'verified' => true],
            ['name' => 'IBM AI Engineering Professional Certificate', 'issuer' => 'IBM', 'issue_date' => '11/2025', 'verified' => true]
        ],
        'readiness_summary' => [
            'status_label' => 'Sẵn sàng thực tập ngay từ T8/2026',
            'strengths' => ['Thành thạo Python & SQL', 'Tư duy phân tích số liệu nhạy bén', 'Khả năng trực quan hóa dữ liệu'],
            'preferred_field' => 'Data Analyst / Junior AI Engineer',
            'total_exp_hours' => '95h thực án thực tế'
        ]
    ],
    [
        'id' => 3,
        'name' => 'Trần Minh Đức',
        'avatar_initials' => 'MĐ',
        'school' => 'Đại học FPT',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 4',
        'major_field' => 'Kỹ thuật Phần mềm',
        'talent_score' => 88,
        'match_score' => 88,
        'experience_hours' => 150,
        'internship_status' => 'ready_1_3m',
        'internship_status_label' => 'Sẵn sàng từ T9/2026',
        'skills' => ['PHP', 'Laravel', 'MySQL', 'Docker', 'Vue.js', 'Communication'],
        'updated_at' => '2026-08-09',
        'saved' => true,
        'bio' => 'Sinh viên chuyên ngành Kỹ thuật Phần mềm ĐH FPT. Có 150 giờ trải nghiệm thực án phát triển ứng dụng web doanh nghiệp sử dụng PHP Laravel và MySQL. Đam mê thiết kế kiến trúc hệ thống và triển khai ứng dụng đám mây.',
        'detailed_skills' => [
            ['name' => 'PHP', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'Laravel Framework', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'MySQL', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'Docker', 'level' => 'Trung cấp', 'verified' => false],
            ['name' => 'Vue.js', 'level' => 'Trung cấp', 'verified' => true],
            ['name' => 'Communication', 'level' => 'Nâng cao', 'verified' => true]
        ],
        'experience_logs' => [
            [
                'title' => 'Dự án Cổng Thông tin Tuyển dụng FPT Enterprise',
                'role' => 'Backend Developer',
                'duration' => '02/2026 - 06/2026',
                'hours' => 90,
                'description' => 'Thiết kế cơ sở dữ liệu MySQL 20+ bảng, xây dựng RESTful API và đóng gói container bằng Docker.'
            ],
            [
                'title' => 'Thực án Hệ thống Đặt lịch Phòng khám Trực tuyến',
                'role' => 'Full-Stack Developer',
                'duration' => '09/2025 - 01/2026',
                'hours' => 60,
                'description' => 'Xây dựng giao diện Vue.js kết hợp PHP Laravel backend cho dịch vụ y tế.'
            ]
        ],
        'projects' => [
            [
                'name' => 'Hệ thống Quản lý Kho Hàng và Đơn hàng SaaS',
                'description' => 'Phần mềm quản lý xuất nhập tồn kho đa chi nhánh dành cho doanh nghiệp SME.',
                'role' => 'Backend Lead',
                'technologies' => ['PHP 8', 'Laravel', 'MySQL', 'Redis', 'Docker'],
                'result' => 'Đồ án tốt nghiệp đạt 9.2/10'
            ]
        ],
        'certificates' => [
            ['name' => 'Laravel Certified Developer', 'issuer' => 'Laravel LLC', 'issue_date' => '02/2026', 'verified' => true],
            ['name' => 'Docker Certified Associate (DCA)', 'issuer' => 'Docker Inc.', 'issue_date' => '10/2025', 'verified' => true]
        ],
        'readiness_summary' => [
            'status_label' => 'Sẵn sàng thực tập từ tháng 9/2026',
            'strengths' => ['Kinh nghiệm Laravel & Docker phong phú', 'Kỹ năng thiết kế DB chuẩn hóa', 'Tác phong làm việc chuyên nghiệp'],
            'preferred_field' => 'Backend PHP/Laravel Developer',
            'total_exp_hours' => '150h thực án thực tế'
        ]
    ],
    [
        'id' => 4,
        'name' => 'Phạm Hoàng Nam',
        'avatar_initials' => 'HN',
        'school' => 'THPT chuyên Hà Nội - Amsterdam',
        'education_level' => 'THPT',
        'class_year' => 'Lớp 12',
        'major_field' => 'Công nghệ Thông tin',
        'talent_score' => 89,
        'match_score' => 89,
        'experience_hours' => 80,
        'internship_status' => 'ready_1_3m',
        'internship_status_label' => 'Sẵn sàng từ T10/2026',
        'skills' => ['C++', 'Python', 'Giải thuật', 'AI/ML', 'Leadership'],
        'updated_at' => '2026-08-12',
        'saved' => false,
        'bio' => 'Học sinh chuyên Tin THPT Chuyên Hà Nội - Amsterdam. Đạt giải Học sinh giỏi Quốc gia môn Tin học. Có nền tảng thuật toán vững chắc, tư duy giải quyết vấn đề xuất sắc và đam mê nghiên cứu Trí tuệ nhân tạo.',
        'detailed_skills' => [
            ['name' => 'C++', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'Python', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'Cấu trúc dữ liệu & Giải thuật', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'AI / Machine Learning', 'level' => 'Trung cấp', 'verified' => true],
            ['name' => 'Leadership', 'level' => 'Trung cấp', 'verified' => false]
        ],
        'experience_logs' => [
            [
                'title' => 'Đội tuyển HSG Quốc gia Môn Tin học',
                'role' => 'Thành viên đội tuyển',
                'duration' => '09/2025 - 03/2026',
                'hours' => 80,
                'description' => 'Luyện tập các thuật toán nâng cao, đồ thị, quy hoạch động và toán tin.'
            ]
        ],
        'projects' => [
            [
                'name' => 'Phần mềm Tự động Phân loại Đề thi Olympic Tin học',
                'description' => 'Công cụ phân tích cấu trúc bài toán và chấm điểm tự động cho các kỳ thi HSG.',
                'role' => 'Tác giả chính',
                'technologies' => ['C++', 'Python', 'Algorithm design'],
                'result' => 'Giải Nhì Tin học Trẻ Toàn quốc 2025'
            ]
        ],
        'certificates' => [
            ['name' => 'Giải Nhì Kỳ thi Học sinh giỏi Quốc gia Môn Tin học', 'issuer' => 'Bộ Giáo dục và Đào tạo', 'issue_date' => '03/2026', 'verified' => true]
        ],
        'readiness_summary' => [
            'status_label' => 'Sẵn sàng tham gia dự án / thực tập từ T10/2026',
            'strengths' => ['Tư duy thuật toán thượng thừa', 'Thành thạo C++ & Python', 'Khả năng học hỏi công nghệ mới cực nhanh'],
            'preferred_field' => 'Lập trình Thuật toán / AI Research Intern',
            'total_exp_hours' => '80h thực án thuật toán'
        ]
    ],
    [
        'id' => 5,
        'name' => 'Vũ Mai Phương',
        'avatar_initials' => 'MP',
        'school' => 'Đại học Công nghệ - ĐHQGHN',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 3',
        'major_field' => 'Thiết kế Đồ họa & UI/UX',
        'talent_score' => 88,
        'match_score' => 88,
        'experience_hours' => 110,
        'internship_status' => 'ready_now',
        'internship_status_label' => 'Sẵn sàng thực tập ngay',
        'skills' => ['Figma', 'UI/UX Design', 'Photoshop', 'Prototyping', 'User Research', 'Communication'],
        'updated_at' => '2026-08-07',
        'saved' => false,
        'bio' => 'Sinh viên Thiết kế Đồ họa & UI/UX với gout thẩm mỹ hiện đại, tinh tế. Đã thực hiện thiết kế Design System và giao diện ứng dụng di động cho 3 dự án khởi nghiệp công nghệ.',
        'detailed_skills' => [
            ['name' => 'Figma', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'UI/UX Design', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'Adobe Photoshop', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'Prototyping & Wireframing', 'level' => 'Nâng cao', 'verified' => true],
            ['name' => 'User Research', 'level' => 'Trung cấp', 'verified' => false],
            ['name' => 'Communication', 'level' => 'Nâng cao', 'verified' => true]
        ],
        'experience_logs' => [
            [
                'title' => 'Thiết kế Bộ nhận diện và App Mobile Y tế EduMed',
                'role' => 'Lead UI/UX Designer',
                'duration' => '11/2025 - 04/2026',
                'hours' => 110,
                'description' => 'Xây dựng Design System 50+ components và thử nghiệm người dùng trực tiếp trên 100 đối tượng.'
            ]
        ],
        'projects' => [
            [
                'name' => 'Design System TalentHub Enterprise UI Kit',
                'description' => 'Bộ thư viện giao diện chuẩn doanh nghiệp bao gồm tokens, components và quy chuẩn tương tác.',
                'role' => 'UI/UX Designer',
                'technologies' => ['Figma', 'Design System', 'Prototyping'],
                'result' => 'Được chọn làm bộ mẫu thiết kế chính thức'
            ]
        ],
        'certificates' => [
            ['name' => 'Google UX Design Professional Certificate', 'issuer' => 'Coursera / Google', 'issue_date' => '01/2026', 'verified' => true]
        ],
        'readiness_summary' => [
            'status_label' => 'Sẵn sàng thực tập ngay',
            'strengths' => ['Thành thạo Figma & Design Systems', 'Tư duy thiết kế lấy người dùng làm trung tâm', 'Kỹ năng trình bày concept sản phẩm tốt'],
            'preferred_field' => 'UI/UX Designer / Product Designer',
            'total_exp_hours' => '110h thiết kế trải nghiệm'
        ]
    ]
];

// Provide helper to find candidate by ID (or default fallback to candidate 1)
function getMockTalentById($id) {
    global $mockTalents;
    $id = intval($id);
    foreach ($mockTalents as $talent) {
        if ($talent['id'] === $id) {
            return $talent;
        }
    }
    return null;
}
