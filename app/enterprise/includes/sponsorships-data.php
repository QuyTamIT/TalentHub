<?php
/**
 * TalentHub Enterprise - Project Sponsorships Mock Data Provider
 * 
 * Data structures designed for future mapping to database tables:
 * - `projects`: Core student/school innovation projects seeking sponsorship
 * - `project_members`: Student/Learner team members attached to projects
 * - `project_sponsorships`: Enterprise sponsorship transactions and commitments
 * - `sponsorship_status_history`: Tracking milestone progress and disbursement history
 */

/**
 * Get overall sponsorship summary metrics for Enterprise dashboard
 */
/**
 * Get overall sponsorship summary metrics for Enterprise dashboard
 */
function getSponsorshipMetrics() {
    return [
        'total_sponsored_amount' => 70000000, // 70,000,000 VNĐ
        'total_sponsored_formatted' => '70.000.000 VNĐ',
        'total_projects_sponsored' => 3,
        'total_learners_supported' => 10,
        'active_sponsorships_count' => 3,
        'completed_milestones_count' => 8
    ];
}

/**
 * Get list of available filter options
 */
function getSponsorshipFilterOptions() {
    return [
        'categories' => [
            'all' => 'Tất cả lĩnh vực',
            'AI & Phần mềm' => 'AI & Phần mềm',
            'Đồ họa 3D & Đa phương tiện' => 'Đồ họa 3D & Đa phương tiện',
            'Kinh tế số & Thương mại điện tử' => 'Kinh tế số & Thương mại điện tử'
        ],
        'schools' => [
            'all' => 'Tất cả các trường',
            'Cao đẳng Quốc tế BTEC FPT' => 'Cao đẳng Quốc tế BTEC FPT',
            'Đại học FPT' => 'Đại học FPT',
            'Đại học Cần Thơ' => 'Đại học Cần Thơ'
        ],
        'target_ranges' => [
            'all' => 'Mọi mức tài trợ',
            'under_50m' => 'Dưới 50 triệu',
            '50m_100m' => '50 - 100 triệu',
            'above_100m' => 'Trên 100 triệu'
        ],
        'statuses' => [
            'all' => 'Tất cả trạng thái',
            'in_progress' => 'Đang thực hiện & Kêu gọi',
            'calling' => 'Đang gọi tài trợ',
            'completed' => 'Đã đạt mục tiêu (100%)'
        ]
    ];
}

/**
 * Get master mock list of discoverable innovation projects
 */
function getMockProjects() {
    return [
        [
            'id' => '50000000-0000-4000-8000-000000000001',
            'title' => 'Ứng dụng AI phân loại rác & Tái chế thông minh',
            'school_id' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783',
            'school_name' => 'Cao đẳng Quốc tế BTEC FPT',
            'school_badge' => 'BTEC FPT',
            'category' => 'AI & Phần mềm',
            'status' => 'in_progress',
            'status_label' => 'Tiềm năng cao (80%)',
            'raised_amount' => 20000000,
            'target_amount' => 25000000,
            'percentage' => 80,
            'members_count' => 3,
            'description' => 'Hệ thống Computer Vision và AI Edge tích hợp thùng rác thông minh tự động nhận diện và phân loại rác hữu cơ, rác tái chế và rác vô cơ với độ chính xác trên 95%.',
            'problem_statement' => 'Tình trạng rác thải sinh hoạt và rác tái chế bị vứt lẫn lộn gây khó khăn lớn cho công tác xử lý và làm giảm 70% giá trị tái chế nguyên liệu.',
            'solution' => 'Sử dụng camera AI nhận diện thời gian thực kết hợp vi xử lý Raspberry Pi/Jetson Nano và hệ thống cánh lật phân loại tự động vào 3 ngăn.',
            'team_leader' => [
                'name' => 'Trần Minh Đức',
                'role' => 'Trưởng nhóm AI & Embedded Systems',
                'school' => 'Cao đẳng Quốc tế BTEC FPT',
                'avatar_initial' => 'TĐ'
            ],
            'team_members' => [
                ['name' => 'Trần Minh Đức', 'role' => 'Trưởng nhóm AI & Embedded Systems', 'skills' => ['Python', 'YOLOv8', 'Edge AI']],
                ['name' => 'Võ Đức Anh', 'role' => 'Frontend & Mobile Developer', 'skills' => ['Flutter', 'ReactJS', 'REST API']],
                ['name' => 'Nguyễn Văn An', 'role' => 'Fullstack & Cloud Backend', 'skills' => ['Node.js', 'PostgreSQL', 'Docker']]
            ],
            'milestones' => [
                ['phase' => 'Giai đoạn 1', 'title' => 'Huấn luyện mô hình YOLOv8 trên 15.000 ảnh rác thải', 'date' => '08/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Giai đoạn 2', 'title' => 'Chế tạo và thử nghiệm thùng rác thông minh tại Campus BTEC', 'date' => '10/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai'],
                ['phase' => 'Giai đoạn 3', 'title' => 'Thương mại hóa và lắp đặt thử nghiệm tại các tòa nhà văn phòng FPT', 'date' => '12/2026', 'status' => 'planned', 'status_label' => 'Kế hoạch']
            ],
            'expected_use_of_funds' => [
                ['category' => 'Module AI Camera & Vi xử lý Edge (Jetson Nano)', 'percentage' => 50, 'amount' => '12.500.000 VNĐ'],
                ['category' => 'Cơ khí khung thùng rác & Động cơ Servo công nghiệp', 'percentage' => 30, 'amount' => '7.500.000 VNĐ'],
                ['category' => 'Học bổng & Hỗ trợ sinh viên nghiên cứu', 'percentage' => 20, 'amount' => '5.000.000 VNĐ']
            ],
            'sponsors_count' => 1
        ],
        [
            'id' => '50000000-0000-4000-8000-000000000002',
            'title' => 'Game Giáo dục 3D: Hành trình Khám phá Di sản Lịch sử',
            'school_id' => '22000000-b512-4ede-852b-f4a508f3e837',
            'school_name' => 'Đại học FPT',
            'school_badge' => 'Đại học FPT',
            'category' => 'Đồ họa 3D & Đa phương tiện',
            'status' => 'in_progress',
            'status_label' => 'Đã đạt mục tiêu (100%)',
            'raised_amount' => 35000000,
            'target_amount' => 35000000,
            'percentage' => 100,
            'members_count' => 4,
            'description' => 'Trò chơi nhập vai 3D tái hiện các di tích lịch sử và văn hóa Việt Nam trên nền tảng Unity/Unreal Engine, giúp học sinh trải nghiệm học sử trực quan, hấp dẫn.',
            'problem_statement' => 'Kiến thức lịch sử truyền thống trong sách giáo khoa khó tạo hứng thú cho học sinh gen Z và thiếu trải nghiệm không gian thực tế.',
            'solution' => 'Xây dựng tựa game nhập vai 3D trên Unity với đồ họa chân thực, tái hiện các trận đánh lịch sử và sự kiện hào hùng của dân tộc Việt Nam.',
            'team_leader' => [
                'name' => 'Hoàng Nhật Quang',
                'role' => 'Trưởng nhóm & Game Designer',
                'school' => 'Đại học FPT',
                'avatar_initial' => 'HQ'
            ],
            'team_members' => [
                ['name' => 'Hoàng Nhật Quang', 'role' => 'Trưởng nhóm & Game Designer', 'skills' => ['Unity', 'C#', 'Game Design']],
                ['name' => 'Phạm Đức Duy', 'role' => '3D Environment Artist', 'skills' => ['Blender', 'Substance Painter']],
                ['name' => 'Bùi Thảo Nguyên', 'role' => 'Character & VFX Designer', 'skills' => ['ZBrush', 'VFX Graph']],
                ['name' => 'Trần Gia Bảo', 'role' => 'Audio & Unity Developer', 'skills' => ['FMOD', 'C#', 'Shader Graph']]
            ],
            'milestones' => [
                ['phase' => 'Giai đoạn 1', 'title' => 'Dựng hình 3D bối cảnh lịch sử & Xây dựng cốt truyện', 'date' => '08/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Giai đoạn 2', 'title' => 'Lập trình logic gameplay & Thử nghiệm Alpha test 500 sinh viên', 'date' => '10/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Giai đoạn 3', 'title' => 'Phát hành miễn phí cho học sinh THPT trên toàn quốc', 'date' => '12/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành']
            ],
            'expected_use_of_funds' => [
                ['category' => 'Bản quyền Asset 3D & Plugin Đồ họa Unity Pro', 'percentage' => 50, 'amount' => '17.500.000 VNĐ'],
                ['category' => 'Server Cloud Multiplayer & Hosting', 'percentage' => 30, 'amount' => '10.500.000 VNĐ'],
                ['category' => 'Phần thưởng & Khuyến khích nhóm phát triển', 'percentage' => 20, 'amount' => '7.000.000 VNĐ']
            ],
            'sponsors_count' => 1
        ],
        [
            'id' => '50000000-0000-4000-8000-000000000003',
            'title' => 'Nền tảng Sàn kết nối Nông sản số & Truy xuất nguồn gốc',
            'school_id' => '23000000-0000-4000-8000-000000000001',
            'school_name' => 'Đại học Cần Thơ',
            'school_badge' => 'Đại học Cần Thơ',
            'category' => 'Kinh tế số & Thương mại điện tử',
            'status' => 'in_progress',
            'status_label' => 'Đang gọi vốn (50%)',
            'raised_amount' => 15000000,
            'target_amount' => 30000000,
            'percentage' => 50,
            'members_count' => 3,
            'description' => 'Sàn thương mại điện tử kết nối nông sản OCOP Đồng bằng Sông Cửu Long, tích hợp tem QR truy xuất nguồn gốc nông trại và thanh toán trực tuyến an toàn.',
            'problem_statement' => 'Nông sản OCOP của các hợp tác xã thanh niên miền Tây gặp khó khăn trong tiếp cận thị trường lớn và chứng minh nguồn gốc sạch.',
            'solution' => 'Xây dựng sàn thương mại điện tử chuyên biệt tích hợp công nghệ QR Code mã hóa Blockchain, minh bạch từ khâu trồng trọt đến tay người tiêu dùng.',
            'team_leader' => [
                'name' => 'Lê Hoàng Nam',
                'role' => 'Trưởng nhóm & Backend Architect',
                'school' => 'Đại học Cần Thơ',
                'avatar_initial' => 'HN'
            ],
            'team_members' => [
                ['name' => 'Lê Hoàng Nam', 'role' => 'Trưởng nhóm & Backend Architect', 'skills' => ['Laravel', 'MySQL', 'System Design']],
                ['name' => 'Đỗ Quang Minh', 'role' => 'Fullstack & QR Code Developer', 'skills' => ['Vue.js', 'Node.js', 'QR Code']],
                ['name' => 'Lê Minh Châu', 'role' => 'Business Analyst & UI/UX', 'skills' => ['Figma', 'Market Research', 'Agile']]
            ],
            'milestones' => [
                ['phase' => 'Giai đoạn 1', 'title' => 'Xây dựng kiến trúc hệ thống & Cơ sở dữ liệu phân tán', 'date' => '08/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Giai đoạn 2', 'title' => 'Tích hợp cổng thanh toán trực tuyến & Hệ thống in tem QR', 'date' => '10/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai'],
                ['phase' => 'Giai đoạn 3', 'title' => 'Liên kết 30+ Hợp tác xã thanh niên Cần Thơ & Đồng Tháp', 'date' => '12/2026', 'status' => 'planned', 'status_label' => 'Kế hoạch']
            ],
            'expected_use_of_funds' => [
                ['category' => 'Hạ tầng Cloud Server & Thiết bị in mã QR chuyên dụng', 'percentage' => 50, 'amount' => '15.000.000 VNĐ'],
                ['category' => 'Tập huấn kỹ thuật cho các HTX & Marketing quảng bá', 'percentage' => 30, 'amount' => '9.000.000 VNĐ'],
                ['category' => 'Hỗ trợ nhóm sinh viên thực địa', 'percentage' => 20, 'amount' => '6.000.000 VNĐ']
            ],
            'sponsors_count' => 1
        ]
    ];
}

/**
 * Get mock project details by ID
 */
function getMockProjectById($id) {
    $projects = getMockProjects();
    foreach ($projects as $proj) {
        if ($proj['id'] === $id) {
            return $proj;
        }
    }
    return $projects[0];
}

/**
 * Get list of active sponsorships made by the enterprise (for "Đã tài trợ" tab)
 */
function getMySponsorships() {
    return [
        [
            'id' => '51000000-0000-4000-8000-000000000001',
            'project_id' => '50000000-0000-4000-8000-000000000001',
            'project_title' => 'Ứng dụng AI phân loại rác & Tái chế thông minh',
            'school_name' => 'Cao đẳng Quốc tế BTEC FPT',
            'category' => 'AI & Phần mềm',
            'sponsored_amount' => 20000000,
            'sponsored_amount_formatted' => '20.000.000 VNĐ',
            'total_raised' => 20000000,
            'target_amount' => 25000000,
            'percentage' => 80,
            'status' => 'paid',
            'status_label' => 'Đã giải ngân đợt 1',
            'status_badge_class' => 'badge-success',
            'sponsored_date' => '01/08/2026',
            'learners_supported' => 3,
            'latest_update' => [
                'date' => '15/08/2026',
                'title' => 'Hoàn thành huấn luyện mô hình YOLOv8 trên 15.000 ảnh rác thải',
                'author' => 'Trần Minh Đức (Leader)',
                'summary' => 'Mô hình AI đạt độ chính xác nhận diện 96.2% trên các loại rác nhựa, kim loại và rác hữu cơ. Đang hoàn thiện bo mạch kết nối cánh gạt phân loại.'
            ],
            'next_milestone' => 'Lắp đặt thử nghiệm thùng rác thông minh tại Campus BTEC FPT (15/09/2026)',
            'benefits_unlocked' => [
                'Đặt logo FPT Software trên thân máy & tài liệu công bố khoa học',
                'Quyền ưu tiên tuyển dụng 3 sinh viên tài năng vào FPT Software',
                'Cố vấn kỹ thuật 1:1 cùng chuyên gia AI Lab FPT Software'
            ]
        ]
    ];
}
