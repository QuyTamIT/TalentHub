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
function getSponsorshipMetrics() {
    return [
        'total_sponsored_amount' => 245000000, // 245,000,000 VNĐ
        'total_sponsored_formatted' => '245,000,000 VNĐ',
        'total_projects_sponsored' => 6,
        'total_learners_supported' => 34,
        'active_sponsorships_count' => 4,
        'completed_milestones_count' => 12
    ];
}

/**
 * Get list of available filter options
 */
function getSponsorshipFilterOptions() {
    return [
        'categories' => [
            'all' => 'Tất cả lĩnh vực',
            'AI & Machine Learning' => 'AI & Machine Learning',
            'EdTech & Giáo dục' => 'EdTech & Giáo dục',
            'IoT & Phần cứng' => 'IoT & Phần cứng',
            'Green Tech & Môi trường' => 'Green Tech & Môi trường',
            'Fintech & Thương mại số' => 'Fintech & Thương mại số',
            'Y tế & Chăm sóc sức khỏe' => 'Y tế & Chăm sóc sức khỏe'
        ],
        'schools' => [
            'all' => 'Tất cả các trường',
            'ĐH Bách Khoa Hà Nội' => 'ĐH Bách Khoa Hà Nội',
            'ĐH Quốc Gia TP.HCM' => 'ĐH Quốc Gia TP.HCM',
            'ĐH FPT' => 'ĐH FPT',
            'ĐH Sư Phạm Kỹ Thuật' => 'ĐH Sư Phạm Kỹ Thuật',
            'Trường THPT Chuyên KHTN' => 'Trường THPT Chuyên KHTN'
        ],
        'target_ranges' => [
            'all' => 'Mọi mức tài trợ',
            'under_50m' => 'Dưới 50 triệu',
            '50m_100m' => '50 - 100 triệu',
            'above_100m' => 'Trên 100 triệu'
        ],
        'statuses' => [
            'all' => 'Tất cả trạng thái',
            'calling' => 'Đang gọi tài trợ',
            'near_completion' => 'Sắp đạt mục tiêu (≥ 80%)',
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
            'id' => 'proj-001',
            'title' => 'Hệ thống AI nhận diện hành vi học tập & Gợi ý lộ trình 360°',
            'school_id' => 'sch-001',
            'school_name' => 'ĐH Bách Khoa Hà Nội',
            'school_badge' => 'HUST',
            'category' => 'AI & Machine Learning',
            'status' => 'calling',
            'status_label' => 'Đang gọi tài trợ',
            'raised_amount' => 65000000,
            'target_amount' => 80000000,
            'percentage' => 81,
            'members_count' => 5,
            'description' => 'Ứng dụng Computer Vision & Deep Learning hỗ trợ đo lường mức độ tập trung, phát hiện sớm thế mạnh năng khiếu và gợi ý phương pháp học tập cá nhân hóa.',
            'problem_statement' => 'Phương pháp đánh giá học sinh truyền thống phụ thuộc vào điểm số kiểm tra, thiếu công cụ đo lường mức độ tiếp thu và tư duy logic thời gian thực. Học sinh thường mất phương hướng khi chọn ngành học.',
            'solution' => 'Hệ thống tích hợp camera AI thế hệ mới kết hợp thuật toán NLP phân tích biểu đồ tư duy, tự động tạo hồ sơ năng lực 360° và dự báo xác suất thành công với các ngành nghề tương lai.',
            'team_leader' => [
                'name' => 'Nguyễn Văn An',
                'role' => 'Project Lead & AI Engineer',
                'school' => 'Sinh viên K67 - Khoa CNTT ĐH Bách Khoa',
                'avatar_initial' => 'AN'
            ],
            'team_members' => [
                ['name' => 'Trần Thu Hà', 'role' => 'Frontend Developer', 'skills' => ['React', 'TypeScript', 'Tailwind']],
                ['name' => 'Lê Hoàng Nam', 'role' => 'Data Scientist', 'skills' => ['Python', 'PyTorch', 'YOLOv8']],
                ['name' => 'Phạm Quốc Bảo', 'role' => 'Backend & Cloud', 'skills' => ['Node.js', 'Docker', 'AWS']],
                ['name' => 'Đỗ Minh Anh', 'role' => 'UI/UX Designer', 'skills' => ['Figma', 'User Research']]
            ],
            'milestones' => [
                ['phase' => 'Cột mốc 1', 'title' => 'Nghiên cứu thuật toán & Thu thập dữ liệu mẫu (1,000 mẫu)', 'date' => '03/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Cột mốc 2', 'title' => 'Xây dựng mô hình AI Prototype & Đánh giá độ chính xác (92.4%)', 'date' => '05/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Cột mốc 3', 'title' => 'Thử nghiệm Beta tại 3 trường THPT Chuyên tại Hà Nội', 'date' => '08/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai'],
                ['phase' => 'Cột mốc 4', 'title' => 'Hoàn thiện hệ thống Dashboard & Thương mại hóa', 'date' => '11/2026', 'status' => 'planned', 'status_label' => 'Kế hoạch']
            ],
            'expected_use_of_funds' => [
                ['category' => 'Thuê Cloud GPU & Hạ tầng AI', 'percentage' => 40, 'amount' => '32,000,000 VNĐ'],
                ['category' => 'Thử nghiệm sản phẩm thực tế', 'percentage' => 30, 'amount' => '24,000,000 VNĐ'],
                ['category' => 'Mua thiết bị phần cứng bổ trợ', 'percentage' => 20, 'amount' => '16,000,000 VNĐ'],
                ['category' => 'Truyền thông & Bản quyền', 'percentage' => 10, 'amount' => '8,000,000 VNĐ']
            ],
            'evidence_links' => [
                ['type' => 'deck', 'title' => 'Pitch Deck Dự án AI Education 2026 (PDF)', 'tag' => 'Tài liệu Pitch Deck'],
                ['type' => 'demo', 'title' => 'Video Demo Sản phẩm thực tế trên Youtube', 'tag' => 'Demo sản phẩm'],
                ['type' => 'award', 'title' => 'Giải Nhất Cuộc thi Hackathon Sáng tạo Sinh viên 2026', 'tag' => 'Giải thưởng & Chứng nhận']
            ],
            'sponsors_count' => 3
        ],
        [
            'id' => 'proj-002',
            'title' => 'Thiết bị IoT Smart Check-in & Điểm danh khuôn mặt bảo mật',
            'school_id' => 'sch-002',
            'school_name' => 'ĐH Quốc Gia TP.HCM',
            'school_badge' => 'VNU-HCM',
            'category' => 'IoT & Phần cứng',
            'status' => 'calling',
            'status_label' => 'Đang gọi tài trợ',
            'raised_amount' => 42000000,
            'target_amount' => 50000000,
            'percentage' => 84,
            'members_count' => 4,
            'description' => 'Thiết bị điểm danh thông minh tốc độ < 0.5s kết hợp cảm biến sinh học, chống gian lận và đồng bộ tức thì lên đám mây trường học.',
            'problem_statement' => 'Các sự kiện ngoại khóa và giờ học hiện tại vẫn kiểm soát thủ công, tốn nhiều thời gian và dễ xảy ra tình trạng điểm danh hộ.',
            'solution' => 'Sử dụng vi điều khiển ESP32-CAM tích hợp chip mã hóa phần cứng, nhận diện sinh học và gửi dữ liệu tức thì qua mạng 4G/Wi-Fi.',
            'team_leader' => [
                'name' => 'Phùng Minh Đức',
                'role' => 'Hardware & Embedded Lead',
                'school' => 'ĐH Bách Khoa TP.HCM',
                'avatar_initial' => 'ĐƯ'
            ],
            'team_members' => [
                ['name' => 'Ngô Bảo Trâm', 'role' => 'Firmware Developer', 'skills' => ['C/C++', 'ESP32', 'RTOS']],
                ['name' => 'Đặng Tuấn Anh', 'role' => 'Cloud Engineer', 'skills' => ['Golang', 'MQTT', 'Redis']],
                ['name' => 'Hoàng Khánh Linh', 'role' => 'Product Designer', 'skills' => ['CAD/CAM', '3D Printing']]
            ],
            'milestones' => [
                ['phase' => 'Cột mốc 1', 'title' => 'Thiết kế bo mạch PCB & In vỏ 3D thử nghiệm', 'date' => '02/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Cột mốc 2', 'title' => 'Tích hợp giao thức MQTT & Kiểm thử đường truyền 4G', 'date' => '04/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Cột mốc 3', 'title' => 'Sản xuất 10 thiết bị thử nghiệm tại 2 cơ sở trường', 'date' => '07/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai']
            ],
            'expected_use_of_funds' => [
                ['category' => 'Gia công bo mạch PCB & Linh kiện', 'percentage' => 50, 'amount' => '25,000,000 VNĐ'],
                ['category' => 'Chi phí máy chủ MQTT & Đám mây', 'percentage' => 30, 'amount' => '15,000,000 VNĐ'],
                ['category' => 'Kiểm định chất lượng & Chứng nhận', 'percentage' => 20, 'amount' => '10,000,000 VNĐ']
            ],
            'evidence_links' => [
                ['type' => 'deck', 'title' => 'Bản vẽ kỹ thuật & Sơ đồ nguyên lý PCB (PDF)', 'tag' => 'Tài liệu kỹ thuật'],
                ['type' => 'demo', 'title' => 'Video chạy thử thực tế thiết bị Smart Check-in', 'tag' => 'Demo trực quan']
            ],
            'sponsors_count' => 2
        ],
        [
            'id' => 'proj-003',
            'title' => 'Nền tảng Thực tế ảo (VR/AR) Mô phỏng Phòng thí nghiệm Hóa học',
            'school_id' => 'sch-003',
            'school_name' => 'ĐH FPT',
            'school_badge' => 'FPTU',
            'category' => 'EdTech & Giáo dục',
            'status' => 'near_completion',
            'status_label' => 'Sắp đạt mục tiêu',
            'raised_amount' => 115000000,
            'target_amount' => 120000000,
            'percentage' => 96,
            'members_count' => 6,
            'description' => 'Phòng thí nghiệm 3D tương tác VR giúp học sinh THPT thực hành các phản ứng hóa học nguy hiểm một cách an toàn và trực quan.',
            'problem_statement' => 'Nhiều trường phổ thông thiếu hóa chất và thiết bị thí nghiệm tiêu chuẩn do lo ngại nguy cơ cháy nổ và độc hại.',
            'solution' => 'Mô phỏng chân thực các phản ứng hóa học bằng engine Unity/Unreal Engine, cho phép học sinh thao tác bằng kính VR Meta Quest hoặc smartphone.',
            'team_leader' => [
                'name' => 'Vũ Hoàng Duy',
                'role' => 'VR/AR Lead Developer',
                'school' => 'ĐH FPT Hà Nội',
                'avatar_initial' => 'DU'
            ],
            'team_members' => [
                ['name' => 'Lê Thanh Thảo', 'role' => '3D Artist', 'skills' => ['Blender', 'Substance Painter']],
                ['name' => 'Nguyễn Đức Thắng', 'role' => 'Unity Developer', 'skills' => ['C#', 'Unity VR SDK']],
                ['name' => 'Bùi Hoàng Oanh', 'role' => 'Chemistry Advisor', 'skills' => ['Chuyên môn Hóa học']],
                ['name' => 'Đào Việt Phương', 'role' => 'Sound Engineer', 'skills' => ['Spatial Audio']],
                ['name' => 'Trần Minh Quân', 'role' => 'QA Tester', 'skills' => ['User Testing']]
            ],
            'milestones' => [
                ['phase' => 'Cột mốc 1', 'title' => 'Xây dựng kho 50 mô hình 3D dụng cụ thí nghiệm', 'date' => '01/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Cột mốc 2', 'title' => 'Lập trình hiệu ứng phản ứng hóa học VR', 'date' => '04/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Cột mốc 3', 'title' => 'Đưa vào giảng dạy thí điểm tại 5 trường đối tác', 'date' => '08/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai']
            ],
            'expected_use_of_funds' => [
                ['category' => 'Mua kính VR thử nghiệm & Phần mềm', 'percentage' => 45, 'amount' => '54,000,000 VNĐ'],
                ['category' => 'Thuê Họa sĩ 3D nâng cấp đồ họa', 'percentage' => 35, 'amount' => '42,000,000 VNĐ'],
                ['category' => 'Tổ chức Workshop trải nghiệm', 'percentage' => 20, 'amount' => '24,000,000 VNĐ']
            ],
            'evidence_links' => [
                ['type' => 'demo', 'title' => 'Trải nghiệm ứng dụng VR Lab 3D trên Web/Kính Meta', 'tag' => 'Bản dùng thử VR']
            ],
            'sponsors_count' => 4
        ],
        [
            'id' => 'proj-004',
            'title' => 'Ứng dụng Nông nghiệp Xanh phân tích sức khỏe cây trồng bằng Drone AI',
            'school_id' => 'sch-004',
            'school_name' => 'ĐH Sư Phạm Kỹ Thuật',
            'school_badge' => 'HCMUTE',
            'category' => 'Green Tech & Môi trường',
            'status' => 'calling',
            'status_label' => 'Đang gọi tài trợ',
            'raised_amount' => 35000000,
            'target_amount' => 70000000,
            'percentage' => 50,
            'members_count' => 4,
            'description' => 'Sử dụng Drone trang bị camera phổ quang chụp ảnh đồng ruộng, AI tự động quét sâu bệnh và đưa ra bản đồ phun thuốc tiết kiệm 40% chi phí.',
            'problem_statement' => 'Nông dân lãng phí phân bón và thuốc bảo vệ thực vật do phun đại trà, vừa gây ô nhiễm môi trường vừa làm giảm năng suất.',
            'solution' => 'Chụp ảnh đa phổ từ trên cao, phân tích chỉ số NDVI để phát hiện vùng cây trồng bị dịch bệnh trước khi nhìn thấy bằng mắt thường.',
            'team_leader' => [
                'name' => 'Lương Văn Thành',
                'role' => 'Drone & Automation Engineer',
                'school' => 'ĐH Sư Phạm Kỹ Thuật TP.HCM',
                'avatar_initial' => 'TH'
            ],
            'team_members' => [
                ['name' => 'Nguyễn Thị Mai', 'role' => 'AI Image Analytics', 'skills' => ['OpenCV', 'Python']],
                ['name' => 'Trần Văn Vinh', 'role' => 'Mobile App Developer', 'skills' => ['Flutter', 'Firebase']],
                ['name' => 'Lê Huỳnh Đức', 'role' => 'Agronomist', 'skills' => ['Nông nghiệp học']]
            ],
            'milestones' => [
                ['phase' => 'Cột mốc 1', 'title' => 'Chế tạo khung Drone & Hệ thống điều khiển bay', 'date' => '03/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Cột mốc 2', 'title' => 'Huấn luyện AI nhận dạng 15 loại sâu bệnh phổ biến', 'date' => '06/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai']
            ],
            'expected_use_of_funds' => [
                ['category' => 'Mua Cảm biến Multispectral & Ống kính', 'percentage' => 60, 'amount' => '42,000,000 VNĐ'],
                ['category' => 'Chi phí thử nghiệm thực địa nông trại', 'percentage' => 40, 'amount' => '28,000,000 VNĐ']
            ],
            'evidence_links' => [
                ['type' => 'deck', 'title' => 'Báo cáo thử nghiệm thực địa GreenAgri 2026', 'tag' => 'Báo cáo thử nghiệm']
            ],
            'sponsors_count' => 1
        ],
        [
            'id' => 'proj-005',
            'title' => 'Ví Điện tử Tài chính Học đường & Quản lý Điểm thưởng Tài năng',
            'school_id' => 'sch-003',
            'school_name' => 'ĐH FPT',
            'school_badge' => 'FPTU',
            'category' => 'Fintech & Thương mại số',
            'status' => 'completed',
            'status_label' => 'Đã đạt mục tiêu',
            'raised_amount' => 90000000,
            'target_amount' => 90000000,
            'percentage' => 100,
            'members_count' => 5,
            'description' => 'Giải pháp Fintech thu nhỏ giúp sinh viên quản lý ngân sách cá nhân, nhận điểm thưởng từ các cuộc thi và đổi quà sinh thái.',
            'problem_statement' => 'Học sinh sinh viên thiếu kỹ năng quản lý tài chính đầu đời và khó tiếp cận các chương trình học bổng doanh nghiệp minh bạch.',
            'solution' => 'Hệ thống Token thưởng tích hợp thanh toán mã QR căng-tin, tự động phân bổ ngân sách theo quy tắc 50/30/20.',
            'team_leader' => [
                'name' => 'Trịnh Quốc Tuấn',
                'role' => 'Fintech Product Lead',
                'school' => 'ĐH FPT',
                'avatar_initial' => 'TU'
            ],
            'team_members' => [
                ['name' => 'Nguyễn Ngọc Anh', 'role' => 'Smart Contract Dev', 'skills' => ['Solidity', 'Web3']],
                ['name' => 'Phạm Như Quỳnh', 'role' => 'UX Researcher', 'skills' => ['User Testing']],
                ['name' => 'Hoàng Tuấn Kiệt', 'role' => 'Mobile Dev', 'skills' => ['React Native']],
                ['name' => 'Trần Nhật Minh', 'role' => 'Security Audit', 'skills' => ['Cybersecurity']]
            ],
            'milestones' => [
                ['phase' => 'Cột mốc 1', 'title' => 'Phát triển Smart Contract & App iOS/Android', 'date' => '01/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Cột mốc 2', 'title' => 'Triển khai chính thức tại Căng-tin trường ĐH FPT', 'date' => '05/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành']
            ],
            'expected_use_of_funds' => [
                ['category' => 'Kiểm toán an ninh bảo mật Smart Contract', 'percentage' => 50, 'amount' => '45,000,000 VNĐ'],
                ['category' => 'Ngân sách cashback thúc đẩy người dùng', 'percentage' => 50, 'amount' => '45,000,000 VNĐ']
            ],
            'evidence_links' => [
                ['type' => 'award', 'title' => 'Chứng nhận TOP 5 Fintech Student Challenge 2026', 'tag' => 'Chứng nhận giải thưởng']
            ],
            'sponsors_count' => 3
        ],
        [
            'id' => 'proj-006',
            'title' => 'Thiết bị Hỗ trợ Người khiếm thị Nhận diện Vật cản bằng AI LiDAR',
            'school_id' => 'sch-005',
            'school_name' => 'Trường THPT Chuyên KHTN',
            'school_badge' => 'HUS High',
            'category' => 'Y tế & Chăm sóc sức khỏe',
            'status' => 'calling',
            'status_label' => 'Đang gọi tài trợ',
            'raised_amount' => 28000000,
            'target_amount' => 45000000,
            'percentage' => 62,
            'members_count' => 3,
            'description' => 'Kính thông minh đeo mắt kết hợp cảm biến siêu âm LiDAR và tai nghe dẫn truyền qua xương, giúp người khiếm thị di chuyển an toàn.',
            'problem_statement' => 'Gậy gập truyền thống chỉ phát hiện vật cản dưới đất, không bảo vệ được phần thân trên và đầu người khiếm thị.',
            'solution' => 'Kính AI phản hồi bằng âm thanh 3D thời gian thực, thông báo chính xác khoảng cách và loại vật cản phía trước.',
            'team_leader' => [
                'name' => 'Lê Minh Khôi',
                'role' => 'Hardware Innovator',
                'school' => 'Học sinh Chuyên Vật lý KHTN',
                'avatar_initial' => 'KH'
            ],
            'team_members' => [
                ['name' => 'Nguyễn Hải Đăng', 'role' => 'AI Algorithm', 'skills' => ['Raspberry Pi', 'Python']],
                ['name' => 'Trần Hà My', 'role' => 'Testing & User Feedback', 'skills' => ['Community Care']]
            ],
            'milestones' => [
                ['phase' => 'Cột mốc 1', 'title' => 'Thử nghiệm cảm biến LiDAR trên Raspberry Pi Zero', 'date' => '04/2026', 'status' => 'completed', 'status_label' => 'Đã hoàn thành'],
                ['phase' => 'Cột mốc 2', 'title' => 'Thử nghiệm thực tế với 20 hội viên Hội người khiếm thị', 'date' => '07/2026', 'status' => 'in_progress', 'status_label' => 'Đang triển khai']
            ],
            'expected_use_of_funds' => [
                ['category' => 'Linh kiện LiDAR & Tai nghe Bone Conduction', 'percentage' => 70, 'amount' => '31,500,000 VNĐ'],
                ['category' => 'Tặng 10 kính thử nghiệm miễn phí cho người khiếm thị', 'percentage' => 30, 'amount' => '13,500,000 VNĐ']
            ],
            'evidence_links' => [
                ['type' => 'award', 'title' => 'Giải Đặc Biệt Cuộc thi ISEF Quốc gia 2026', 'tag' => 'Giải thưởng ISEF']
            ],
            'sponsors_count' => 2
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
            'id' => 'spon-101',
            'project_id' => 'proj-001',
            'project_title' => 'Hệ thống AI nhận diện hành vi học tập & Gợi ý lộ trình 360°',
            'school_name' => 'ĐH Bách Khoa Hà Nội',
            'category' => 'AI & Machine Learning',
            'sponsored_amount' => 30000000,
            'sponsored_amount_formatted' => '30,000,000 VNĐ',
            'total_raised' => 65000000,
            'target_amount' => 80000000,
            'percentage' => 81,
            'status' => 'disbursed',
            'status_label' => 'Đã giải ngân đợt 1',
            'status_badge_class' => 'badge-success',
            'sponsored_date' => '12/04/2026',
            'learners_supported' => 5,
            'latest_update' => [
                'date' => '10/08/2026',
                'title' => 'Hoàn thành thử nghiệm mô hình AI trên 500 học sinh thử nghiệm',
                'author' => 'Nguyễn Văn An (Leader)',
                'summary' => 'Mô hình đạt độ chính xác 92.4% trên dữ liệu học sinh THPT Chuyên Hà Nội - KHTN. Đang chuẩn bị đóng gói bản Beta và nghiệm thu đợt 2.'
            ],
            'next_milestone' => 'Nghiệm thu thử nghiệm Beta & Báo cáo kết quả đợt 2 (30/08/2026)',
            'benefits_unlocked' => [
                'Đặt logo FPT Software trên slide & sản phẩm demo',
                'Quyền ưu tiên tiếp cận & nhận 5 thực tập sinh xuất sắc',
                'Lịch trao đổi Mentor 1:1 hàng tuần cùng Tech Lead FPT'
            ]
        ],
        [
            'id' => 'spon-102',
            'project_id' => 'proj-003',
            'project_title' => 'Nền tảng Thực tế ảo (VR/AR) Mô phỏng Phòng thí nghiệm Hóa học',
            'school_name' => 'ĐH FPT',
            'category' => 'EdTech & Giáo dục',
            'sponsored_amount' => 50000000,
            'sponsored_amount_formatted' => '50,000,000 VNĐ',
            'total_raised' => 115000000,
            'target_amount' => 120000000,
            'percentage' => 96,
            'status' => 'committed',
            'status_label' => 'Đang cam kết & Chờ giải ngân đợt 2',
            'status_badge_class' => 'badge-warning',
            'sponsored_date' => '02/06/2026',
            'learners_supported' => 6,
            'latest_update' => [
                'date' => '05/08/2026',
                'title' => 'Hoàn thành tích hợp 20 bài thí nghiệm Hóa học lớp 11 & 12',
                'author' => 'Vũ Hoàng Duy (Lead)',
                'summary' => 'Nhóm đã demo thành công với Hội đồng Khoa Công nghệ ĐH FPT. Đang kiểm thử hiệu năng trên kính VR Meta Quest 3.'
            ],
            'next_milestone' => 'Giải ngân đợt 2 theo tiến độ cột mốc 3 (15/09/2026)',
            'benefits_unlocked' => [
                'Tài trợ không gian Lab VR mang thương hiệu FPT Software',
                'Nhận báo cáo tiến độ độc quyền hàng tháng'
            ]
        ],
        [
            'id' => 'spon-103',
            'project_id' => 'proj-005',
            'project_title' => 'Ví Điện tử Tài chính Học đường & Quản lý Điểm thưởng Tài năng',
            'school_name' => 'ĐH FPT',
            'category' => 'Fintech & Thương mại số',
            'sponsored_amount' => 45000000,
            'sponsored_amount_formatted' => '45,000,000 VNĐ',
            'total_raised' => 90000000,
            'target_amount' => 90000000,
            'percentage' => 100,
            'status' => 'completed',
            'status_label' => 'Đã nghiệm thu hoàn thành',
            'status_badge_class' => 'badge-info',
            'sponsored_date' => '15/01/2026',
            'learners_supported' => 5,
            'latest_update' => [
                'date' => '20/07/2026',
                'title' => 'Báo cáo tổng kết nghiệm thu & Tuyển dụng 2 thành viên vào FPT Software',
                'author' => 'Trịnh Quốc Tuấn (Lead)',
                'summary' => 'Dự án đã nghiệm thu thành công 100%. 2 thành viên nhóm đã chính thức nhận offer Thực tập sinh Backend tại FPT Software.'
            ],
            'next_milestone' => 'Dự án đã nghiệm thu xuất sắc 100%',
            'benefits_unlocked' => [
                'Tuyển dụng thành công 2 tài năng trẻ xuất sắc',
                'Nhận báo cáo nghiên cứu khả thi ứng dụng Fintech'
            ]
        ],
        [
            'id' => 'spon-104',
            'project_id' => 'proj-002',
            'project_title' => 'Thiết bị IoT Smart Check-in & Điểm danh khuôn mặt bảo mật',
            'school_name' => 'ĐH Quốc Gia TP.HCM',
            'category' => 'IoT & Phần cứng',
            'sponsored_amount' => 20000000,
            'sponsored_amount_formatted' => '20,000,000 VNĐ',
            'total_raised' => 42000000,
            'target_amount' => 50000000,
            'percentage' => 84,
            'status' => 'disbursed',
            'status_label' => 'Đã giải ngân đợt 1',
            'status_badge_class' => 'badge-success',
            'sponsored_date' => '18/05/2026',
            'learners_supported' => 4,
            'latest_update' => [
                'date' => '01/08/2026',
                'title' => 'Sản xuất thành công 10 thiết bị Smart Check-in phiên bản v2',
                'author' => 'Phùng Minh Đức (Lead)',
                'summary' => 'Tốc độ nhận diện sinh học đạt 0.38s. Đang lắp đặt thử nghiệm tại sảnh chính Văn phòng FPT Software TP.HCM.'
            ],
            'next_milestone' => 'Đánh giá độ ổn định thiết bị sau 30 ngày thử nghiệm (05/09/2026)',
            'benefits_unlocked' => [
                'Thử nghiệm thiết bị tại văn phòng FPT Software',
                'Đặt hàng tính năng riêng cho doanh nghiệp'
            ]
        ]
    ];
}
