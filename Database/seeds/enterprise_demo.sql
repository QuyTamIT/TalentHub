-- ============================================================================
-- TalentHub - Enterprise & Opportunities Dataset (Demo & Production Seed)
-- Compatible with MySQL 8+ / MariaDB (Laragon, HeidiSQL, CLI)
-- 6 Enterprises, 21 Internship Posts, 4 Projects, 4 Sponsorships, 8 Partnerships
-- All enterprise demo accounts password: Talenthub@123
-- Hash: $2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W
-- ============================================================================

USE `talenthub`;

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. SEED 6 DIVERSE ENTERPRISES
-- ----------------------------------------------------------------------------
INSERT INTO `enterprises` (
    `id`, `name`, `status`, `logoUrl`, `industry`, `companySize`, `foundedYear`,
    `description`, `email`, `phone`, `website`, `taxCode`, `address`, `verificationStatus`, `createdAt`, `updatedAt`
) VALUES
(
    '10000000-0000-4000-8000-000000000003',
    'FPT Software',
    'active',
    '/assets/images/fpt-software-logo.svg',
    'Công nghệ thông tin & Dịch vụ phần mềm',
    '10,000+ nhân viên',
    1999,
    'FPT Software là công ty công nghệ và dịch vụ CNTT hàng đầu thế giới có trụ sở chính tại Việt Nam, tiên phong trong chuyển đổi số, AI và đào tạo phát triển tài năng trẻ.',
    'business@test.talenthub.local',
    '024 7300 7575',
    'https://fptsoftware.com',
    '0101234567',
    'Tòa nhà FPT, Phố Duy Tân, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Hà Nội',
    'verified',
    NOW(6),
    NOW(6)
),
(
    '32000000-0000-4000-8000-000000000002',
    'VNG Corporation',
    'active',
    '/assets/images/vng-logo.svg',
    'Công nghệ, Game & Giải trí số',
    '3,000+ nhân viên',
    2004,
    'VNG là kỳ lân công nghệ đầu tiên của Việt Nam, dẫn đầu trong lĩnh vực phát triển Game số, nền tảng giao tiếp Zalo, giải pháp đám mây và thanh toán điện tử.',
    'vng.careers@talenthub.local',
    '028 3962 3888',
    'https://vng.com.vn',
    '0303815871',
    'VNG Campus, Đường số 13, Khu chế xuất Tân Thuận, Quận 7, TP. Hồ Chí Minh',
    'verified',
    NOW(6),
    NOW(6)
),
(
    '32000000-0000-4000-8000-000000000003',
    'Shopee Vietnam (Sea Group)',
    'active',
    '/assets/images/shopee-logo.svg',
    'Thương mại điện tử & Chuỗi cung ứng',
    '5,000+ nhân viên',
    2015,
    'Shopee là nền tảng thương mại điện tử hàng đầu tại Đông Nam Á và Đài Loan, cung cấp trải nghiệm mua sắm trực tuyến liền mạch, an toàn và tối ưu bằng công nghệ phân tích dữ liệu lớn.',
    'shopee.careers@talenthub.local',
    '028 7308 1221',
    'https://careers.shopee.vn',
    '0313490089',
    'Tầng 17, Tòa nhà Saigon Centre Tower 2, 67 Lê Lợi, Bến Nghé, Quận 1, TP. Hồ Chí Minh',
    'verified',
    NOW(6),
    NOW(6)
),
(
    '32000000-0000-4000-8000-000000000004',
    'Ngân hàng TMCP Kỹ thương Việt Nam (Techcombank)',
    'active',
    '/assets/images/techcombank-logo.svg',
    'Tài chính, Ngân hàng & Công nghệ tài chính (Fintech)',
    '12,000+ nhân viên',
    1993,
    'Techcombank là một trong những ngân hàng cổ phần lớn nhất Việt Nam, tiên phong trong hành trình chuyển đổi số toàn diện với ngân hàng số Techcombank Mobile và giải pháp tài chính vượt trội.',
    'techcombank.careers@talenthub.local',
    '024 3944 6368',
    'https://techcombank.com',
    '0100230800',
    'Số 6 Quang Trung, Phường Trần Hưng Đạo, Quận Hoàn Kiếm, Hà Nội',
    'verified',
    NOW(6),
    NOW(6)
),
(
    '32000000-0000-4000-8000-000000000005',
    'Công ty An ninh mạng Viettel (Viettel Cyber Security)',
    'active',
    '/assets/images/viettel-cyber-logo.svg',
    'An toàn thông tin & Viễn thông',
    '1,000+ nhân viên',
    2014,
    'Viettel Cyber Security là đơn vị chuyên trách an ninh mạng hàng đầu Việt Nam thuộc Tập đoàn Công nghiệp - Viễn thông Quân đội Viettel, bảo vệ hạ tầng trọng yếu quốc gia và doanh nghiệp lớn.',
    'viettel.cyber@talenthub.local',
    '024 6666 8888',
    'https://viettelcybersecurity.com',
    '0100109106',
    'Tòa nhà Viettel, Số 1 Trần Hữu Dực, Phường Mỹ Đình 2, Quận Nam Từ Liêm, Hà Nội',
    'verified',
    NOW(6),
    NOW(6)
),
(
    '32000000-0000-4000-8000-000000000006',
    'Tập đoàn Truyền thông & Quảng cáo Dentsu Vietnam',
    'active',
    '/assets/images/dentsu-logo.svg',
    'Truyền thông, Quảng cáo & Sáng tạo nội dung',
    '600+ nhân viên',
    2003,
    'Dentsu Vietnam trực thuộc mạng lưới Dentsu Quốc tế, cung cấp các giải pháp marketing số tích hợp, sáng tạo nội dung truyền thông, thiết kế thương hiệu và chiến lược tăng trưởng khách hàng.',
    'dentsu.careers@talenthub.local',
    '028 3821 9000',
    'https://dentsu.com',
    '0302821415',
    'Tầng 16, Tòa nhà Vincom Center, 72 Lê Thánh Tôn, Bến Nghé, Quận 1, TP. Hồ Chí Minh',
    'verified',
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `status` = 'active',
    `logoUrl` = VALUES(`logoUrl`),
    `industry` = VALUES(`industry`),
    `companySize` = VALUES(`companySize`),
    `description` = VALUES(`description`),
    `email` = VALUES(`email`),
    `phone` = VALUES(`phone`),
    `website` = VALUES(`website`),
    `taxCode` = VALUES(`taxCode`),
    `address` = VALUES(`address`),
    `verificationStatus` = 'verified',
    `updatedAt` = NOW(6);

-- ----------------------------------------------------------------------------
-- 2. SEED ENTERPRISE USERS (Password: Talenthub@123)
-- ----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `roleId`, `email`, `passwordHash`, `fullName`, `status`, `createdAt`, `updatedAt`)
VALUES
(
    '10000000-0000-4000-8000-000000000014',
    '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    'business@test.talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'FPT Software Talent Team',
    'active',
    NOW(6),
    NOW(6)
),
(
    '31000000-0000-4000-8000-000000000012',
    '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    'vng.careers@talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'VNG Talent Acquisition',
    'active',
    NOW(6),
    NOW(6)
),
(
    '31000000-0000-4000-8000-000000000013',
    '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    'shopee.careers@talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'Shopee HR Campus Team',
    'active',
    NOW(6),
    NOW(6)
),
(
    '31000000-0000-4000-8000-000000000014',
    '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    'techcombank.careers@talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'Techcombank Talent Hub',
    'active',
    NOW(6),
    NOW(6)
),
(
    '31000000-0000-4000-8000-000000000015',
    '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    'viettel.cyber@talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'Viettel Cyber Security HR',
    'active',
    NOW(6),
    NOW(6)
),
(
    '31000000-0000-4000-8000-000000000016',
    '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    'dentsu.careers@talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'Dentsu Talent & People',
    'active',
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE
    `roleId` = '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    `passwordHash` = VALUES(`passwordHash`),
    `fullName` = VALUES(`fullName`),
    `status` = 'active',
    `updatedAt` = NOW(6);

-- ----------------------------------------------------------------------------
-- 3. SEED ENTERPRISE MEMBERSHIPS
-- ----------------------------------------------------------------------------
INSERT INTO `enterprise_members` (`id`, `enterpriseId`, `userId`, `memberRole`, `createdAt`, `updatedAt`)
VALUES
(
    '10000000-0000-4000-8000-000000000024',
    '10000000-0000-4000-8000-000000000003',
    '10000000-0000-4000-8000-000000000014',
    'admin',
    NOW(6),
    NOW(6)
),
(
    '33000000-0000-4000-8000-000000000012',
    '32000000-0000-4000-8000-000000000002',
    '31000000-0000-4000-8000-000000000012',
    'admin',
    NOW(6),
    NOW(6)
),
(
    '33000000-0000-4000-8000-000000000013',
    '32000000-0000-4000-8000-000000000003',
    '31000000-0000-4000-8000-000000000013',
    'admin',
    NOW(6),
    NOW(6)
),
(
    '33000000-0000-4000-8000-000000000014',
    '32000000-0000-4000-8000-000000000004',
    '31000000-0000-4000-8000-000000000014',
    'admin',
    NOW(6),
    NOW(6)
),
(
    '33000000-0000-4000-8000-000000000015',
    '32000000-0000-4000-8000-000000000005',
    '31000000-0000-4000-8000-000000000015',
    'admin',
    NOW(6),
    NOW(6)
),
(
    '33000000-0000-4000-8000-000000000016',
    '32000000-0000-4000-8000-000000000006',
    '31000000-0000-4000-8000-000000000016',
    'admin',
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE
    `enterpriseId` = VALUES(`enterpriseId`),
    `memberRole` = 'admin',
    `updatedAt` = NOW(6);

-- ----------------------------------------------------------------------------
-- 4. SEED 21 INTERNSHIP POSTS (CƠ HỘI TUYỂN DỤNG & THỰC TẬP)
-- ----------------------------------------------------------------------------
INSERT INTO `internship_posts` (
    `id`, `enterpriseId`, `title`, `field`, `status`, `audience`, `location`,
    `workType`, `duration`, `educationLevel`, `description`, `benefits`,
    `skillsJson`, `requirementsJson`, `slots`, `deadline`, `createdAt`, `updatedAt`
) VALUES
-- [FPT Software - 4 Posts]
(
    '40000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000003',
    'Thực tập sinh Trí tuệ Nhân tạo & LLM (AI/GenAI Intern)',
    'it_software',
    'active',
    'public',
    'Hà Nội & TP. Hồ Chí Minh',
    'full_time',
    '4-6 tháng',
    'Đại học / Cao đẳng',
    'Tham gia nghiên cứu và phát triển các mô hình AI/LLM, ứng dụng xử lý ngôn ngữ tự nhiên (NLP) và Computer Vision vào các dự án chuyển đổi số doanh nghiệp toàn cầu.',
    'Trợ cấp hàng tháng từ 6.000.000 - 10.000.000 VNĐ; Cố vấn 1-1 từ AI Solution Architect; Cơ hội chuyển thẳng thành nhân viên chính thức.',
    '["Python", "PyTorch", "Generative AI", "NLP", "Machine Learning"]',
    '["Sinh viên năm 3-4 chuyên ngành CNTT, Khoa học Máy tính hoặc Trí tuệ nhân tạo", "GPA từ 3.0/4.0 trở lên", "Có tư duy logic và khả năng đọc hiểu tài liệu tiếng Anh tốt"]',
    5,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000002',
    '10000000-0000-4000-8000-000000000003',
    'Thực tập sinh Web Fullstack (NodeJS / ReactJS / Python)',
    'it_software',
    'active',
    'public',
    'Đà Nẵng & Cần Thơ',
    'full_time',
    '3-5 tháng',
    'Đại học / Cao đẳng',
    'Xây dựng các module giao diện người dùng và API RESTful cho hệ thống quản lý tài năng và dịch vụ số, phối hợp trong mô hình Scrum/Agile.',
    'Phụ cấp thực tập hấp dẫn; Đào tạo chuẩn dự án quốc tế CMMI L5; Hỗ trợ dấu thực tập tốt nghiệp và OJT.',
    '["JavaScript", "React", "NodeJS", "SQL", "Git"]',
    '["Nắm vững lập trình hướng đối tượng và cơ sở dữ liệu quan hệ", "Đã từng làm ít nhất 1 đồ án web bằng React hoặc Node/PHP"]',
    8,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000003',
    '10000000-0000-4000-8000-000000000003',
    'Kỹ sư Kiểm thử Phần mềm Tự động (Automation QA Trainee)',
    'it_software',
    'active',
    'public',
    'Hà Nội',
    'hybrid',
    '3-4 tháng',
    'Đại học / Cao đẳng',
    'Viết kịch bản kiểm thử tự động với Selenium, Playwright hoặc Cypress; kiểm thử hiệu năng và bảo mật hệ thống.',
    'Trợ cấp thực tập 5.000.000 - 8.000.000 VNĐ; Tham gia các khóa đào tạo ISTQB miễn phí.',
    '["Automation Testing", "Selenium", "Playwright", "Java", "Python"]',
    '["Tỉ mỉ, cẩn thận, có kiến thức cơ bản về quy trình kiểm thử phần mềm"]',
    4,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000004',
    '10000000-0000-4000-8000-000000000003',
    'FPT Tech Day Experience: Trải nghiệm Nhập môn Công nghệ & AI',
    'it_software',
    'active',
    'partner_schools',
    'TP. Hồ Chí Minh & Hà Nội',
    'part_time',
    '2-4 tuần',
    'Trung học Phổ thông',
    'Chương trình kiến tập và hướng nghiệp sớm dành cho học sinh THPT. Tham gia lập trình robot cơ bản, tìm hiểu về bảo mật Internet và tham quan trung tâm dữ liệu FPT.',
    'Chứng chỉ hoàn thành FPT Tech Experience; Quà tặng công nghệ và học bổng khuyến khích tài năng trẻ.',
    '["Tư duy logic", "Khám phá công nghệ", "Làm việc nhóm", "Giao tiếp"]',
    '["Dành cho học sinh khối 10-12 các trường THPT đối tác", "Yêu thích khoa học kỹ thuật và công nghệ thông tin"]',
    20,
    '2026-11-30 23:59:59.000000',
    NOW(6),
    NOW(6)
),

-- [VNG Corporation - 4 Posts]
(
    '40000000-0000-4000-8000-000000000005',
    '32000000-0000-4000-8000-000000000002',
    '2D/3D Game Artist Intern (Đồ họa & Thiết kế Nhân vật Game)',
    'design_media',
    'active',
    'public',
    'TP. Hồ Chí Minh',
    'full_time',
    '3-6 tháng',
    'Đại học / Cao đẳng',
    'Thiết kế concept art nhân vật, vũ khí, hiệu ứng VFX và môi trường cho các tựa game mobile và metaverse của VNG Game Studios.',
    'Hỗ trợ thực tập 7.000.000 - 10.000.000 VNĐ; Làm việc tại VNG Campus với phòng gym, bể bơi và khu giải trí sáng tạo.',
    '["Photoshop", "Blender", "3D Modeling", "Concept Art", "ZBrush"]',
    '["Có portfolio hoặc sản phẩm vẽ tay / 3D minh họa", "Đam mê ngành công nghiệp game và thẩm mỹ thị giác tốt"]',
    4,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000006',
    '32000000-0000-4000-8000-000000000002',
    'UI/UX Product Designer Trainee (Thiết kế Sản phẩm Số)',
    'design_media',
    'active',
    'public',
    'TP. Hồ Chí Minh',
    'full_time',
    '3-6 tháng',
    'Đại học / Cao đẳng',
    'Nghiên cứu hành vi người dùng, xây dựng wireframe, prototype tương tác và thiết kế giao diện ứng dụng số cho hệ sinh thái Zalo và Zing.',
    'Trợ cấp thực tập cạnh tranh; Mentor bởi các Lead Designer kỳ cựu; Cơ hội làm việc trên sản phẩm hàng chục triệu người dùng.',
    '["Figma", "UI/UX Design", "User Research", "Wireframing", "Design System"]',
    '["Thành thạo Figma", "Tư duy thiết kế lấy người dùng làm trung tâm (User-Centered Design)"]',
    3,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000007',
    '32000000-0000-4000-8000-000000000002',
    'Unity / C++ Game Developer Intern',
    'it_software',
    'active',
    'public',
    'TP. Hồ Chí Minh',
    'full_time',
    '4-6 tháng',
    'Đại học / Cao đẳng',
    'Lập trình logic gameplay, tối ưu hóa FPS, xử lý đồng bộ mạng cho các dự án game multiplayer bằng Unity/C# hoặc Unreal/C++.',
    'Mức phụ cấp thực tập 8.000.000 - 12.000.000 VNĐ; Trang bị máy tính cấu hình cao; Đào tạo Game Engine chuyên sâu.',
    '["Unity", "C#", "C++", "Data Structures", "Game Mathematics"]',
    '["Nắm vững cấu trúc dữ liệu, giải thuật và toán học máy tính", "Đã từng phát triển game demo trên Unity"]',
    5,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000008',
    '32000000-0000-4000-8000-000000000002',
    'VNG Youth Discovery: Trải nghiệm Sáng tạo Game & Công nghệ Số',
    'design_media',
    'active',
    'partner_schools',
    'TP. Hồ Chí Minh',
    'part_time',
    '1 tháng',
    'Trung học Phổ thông',
    'Chương trình khám phá quy trình làm Game và sản phẩm số: từ ý tưởng kịch bản, thiết kế nhân vật đến trải nghiệm lập trình đơn giản.',
    'Giấy chứng nhận trải nghiệm sáng tạo VNG; Tham gia cuộc thi ý tưởng Game dành cho học sinh THPT.',
    '["Sáng tạo nội dung", "Kể chuyện (Storytelling)", "Tư duy thẩm mỹ", "Làm việc nhóm"]',
    '["Dành cho học sinh các trường THPT đối tác", "Yêu thích sáng tạo nghệ thuật và giải trí số"]',
    15,
    '2026-11-30 23:59:59.000000',
    NOW(6),
    NOW(6)
),

-- [Shopee Vietnam - 4 Posts]
(
    '40000000-0000-4000-8000-000000000009',
    '32000000-0000-4000-8000-000000000003',
    'E-commerce Business Operations & Category Intern',
    'business_marketing',
    'active',
    'public',
    'TP. Hồ Chí Minh',
    'full_time',
    '3-6 tháng',
    'Đại học / Cao đẳng',
    'Quản trị danh mục ngành hàng, phân tích xu hướng thị trường, hỗ trợ xây dựng các chiến dịch siêu sale ngày đôi (9.9, 11.11, 12.12).',
    'Phụ cấp 6.000.000 - 8.000.000 VNĐ; Trải nghiệm môi trường làm việc năng động chuẩn quốc tế; Cơ hội tham gia Shopee GLP.',
    '["E-Commerce", "Market Analysis", "Excel", "Data Interpretation", "Communication"]',
    '["Sinh viên chuyên ngành Kinh doanh quốc tế, Quản trị, Marketing hoặc TMĐT", "Kỹ năng phân tích số liệu Excel tốt"]',
    6,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000010',
    '32000000-0000-4000-8000-000000000003',
    'Digital Marketing & Growth Trainee',
    'business_marketing',
    'active',
    'public',
    'TP. Hồ Chí Minh',
    'full_time',
    '3-5 tháng',
    'Đại học / Cao đẳng',
    'Thực thi các chiến dịch quảng cáo đa kênh (Facebook Ads, Google Ads, TikTok Shop), đo lường chỉ số ROI, CAC, LTV và tối ưu hóa chuyển đổi.',
    'Trợ cấp thực tập cạnh tranh; Đào tạo chuyên sâu về Growth Hacking & Performance Marketing.',
    '["Digital Marketing", "Facebook Ads", "Google Analytics", "Content Marketing", "ROI Analysis"]',
    '["Hiểu biết về Marketing số", "Nhạy bén với xu hướng mạng xã hội và hành vi người tiêu dùng"]',
    4,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000011',
    '32000000-0000-4000-8000-000000000003',
    'Business Intelligence & Data Analyst Trainee',
    'business_marketing',
    'active',
    'public',
    'TP. Hồ Chí Minh & Hà Nội',
    'full_time',
    '4-6 tháng',
    'Đại học / Cao đẳng',
    'Trích xuất, làm sạch dữ liệu lớn và xây dựng các báo cáo Dashboard trực quan (Tableau/PowerBI) phục vụ ra quyết định kinh doanh.',
    'Phụ cấp 7.000.000 - 9.000.000 VNĐ; Trực tiếp làm việc với kho dữ liệu quy mô hàng triệu giao dịch mỗi ngày.',
    '["SQL", "Python", "Power BI", "Tableau", "Statistical Analysis"]',
    '["Thành thạo truy vấn SQL", "Tư duy logic mạch lạc và kỹ năng trình bày dữ liệu (Data Storytelling)"]',
    5,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000012',
    '32000000-0000-4000-8000-000000000003',
    'Logistics & Supply Chain Operations Intern (Shopee Xpress)',
    'business_marketing',
    'active',
    'public',
    'TP. Hồ Chí Minh & Bình Dương',
    'full_time',
    '3-4 tháng',
    'Đại học / Cao đẳng',
    'Tối ưu hóa quy trình vận hành kho bãi, tuyến giao hàng chặng cuối (Last-Mile Delivery) và quản lý hiệu suất đơn hàng.',
    'Trợ cấp thực tập theo quy định; Phụ cấp cơm trưa và đi lại tại các trung tâm phân loại hàng hóa lớn.',
    '["Logistics", "Supply Chain Management", "Process Optimization", "Excel"]',
    '["Sinh viên chuyên ngành Logistics, Quản lý Công nghiệp hoặc Chuỗi cung ứng"]',
    4,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),

-- [Techcombank - 3 Posts]
(
    '40000000-0000-4000-8000-000000000013',
    '32000000-0000-4000-8000-000000000004',
    'Fintech Product Management Intern (Ngân hàng số Mobile)',
    'finance_banking',
    'active',
    'public',
    'Hà Nội & TP. Hồ Chí Minh',
    'full_time',
    '4-6 tháng',
    'Đại học / Cao đẳng',
    'Hỗ trợ Product Owner nghiên cứu tính năng mới, viết user story, kiểm tra trải nghiệm luồng thanh toán và quản lý vòng đời sản phẩm ngân hàng số.',
    'Trợ cấp thực tập 8.000.000 - 11.000.000 VNĐ; Môi trường Agile/Scrum tài chính chuyên nghiệp hàng đầu Việt Nam.',
    '["Product Management", "Agile/Scrum", "Fintech", "User Flow", "Business Analysis"]',
    '["Tư duy sản phẩm tốt, đam mê lĩnh vực công nghệ tài chính", "Giao tiếp tiếng Anh tự tin"]',
    3,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000014',
    '32000000-0000-4000-8000-000000000004',
    'Financial Risk & Credit Analytics Trainee',
    'finance_banking',
    'active',
    'public',
    'Hà Nội',
    'full_time',
    '3-5 tháng',
    'Đại học / Cao đẳng',
    'Phân tích mô hình chấm điểm tín dụng (Credit Scoring), rà soát rủi ro danh mục cho vay cá nhân và doanh nghiệp bằng dữ liệu định lượng.',
    'Phụ cấp 7.000.000 - 9.000.000 VNĐ; Được hướng dẫn bởi các chuyên gia tài chính và rủi ro cao cấp.',
    '["Financial Modeling", "Risk Management", "Data Analysis", "Python/R", "Excel VBA"]',
    '["Sinh viên chuyên ngành Tài chính - Ngân hàng, Toán kinh tế hoặc Kinh tế lượng", "Điểm tích lũy khá giỏi"]',
    4,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000015',
    '32000000-0000-4000-8000-000000000004',
    'Digital Customer Experience (CX) Trainee',
    'finance_banking',
    'active',
    'public',
    'Hà Nội & TP. Hồ Chí Minh',
    'hybrid',
    '3-4 tháng',
    'Đại học / Cao đẳng',
    'Thu thập ý kiến phản hồi của khách hàng qua NPS/CSAT, phân tích hành trình khách hàng số và đề xuất cải tiến dịch vụ ngân hàng.',
    'Hỗ trợ thực tập hấp dẫn; Đào tạo bài bản về phương pháp Design Thinking và trải nghiệm khách hàng vượt trội.',
    '["Customer Journey Mapping", "Design Thinking", "Data Analytics", "Customer Service"]',
    '["Kỹ năng giao tiếp và lắng nghe xuất sắc", "Có sự thấu cảm cao với trải nghiệm người dùng"]',
    3,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),

-- [Viettel Cyber Security - 3 Posts]
(
    '40000000-0000-4000-8000-000000000016',
    '32000000-0000-4000-8000-000000000005',
    'Security Operations Center (SOC) Tier 1 Analyst Intern',
    'it_software',
    'active',
    'public',
    'Hà Nội',
    'full_time',
    '4-6 tháng',
    'Đại học / Cao đẳng',
    'Giám sát các cảnh báo an ninh mạng 24/7 trên hệ thống SIEM/SOAR, phân tích mã độc cơ bản và xử lý bước đầu các sự cố tấn công mạng.',
    'Trợ cấp thực tập 7.000.000 - 10.000.000 VNĐ; Trực tiếp tiếp cận trung tâm điều hành an toàn thông tin hàng đầu Việt Nam.',
    '["Cybersecurity", "SIEM", "Network Security", "Wireshark", "Linux"]',
    '["Có kiến thức vững chắc về giao thức mạng TCP/IP, hệ điều hành Linux/Windows Server"]',
    5,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000017',
    '32000000-0000-4000-8000-000000000005',
    'Penetration Testing & Web Security Trainee',
    'it_software',
    'active',
    'public',
    'Hà Nội & TP. Hồ Chí Minh',
    'full_time',
    '3-6 tháng',
    'Đại học / Cao đẳng',
    'Đánh giá an ninh ứng dụng web/mobile (Web/API/Mobile Pentest), tìm kiếm lỗ hổng theo chuẩn OWASP Top 10 và viết báo cáo khắc phục.',
    'Mức đãi ngộ cạnh tranh; Được làm việc cùng các chuyên gia bảo mật đạt giải thưởng quốc tế CTF.',
    '["Penetration Testing", "Burp Suite", "OWASP Top 10", "Python/Bash", "Web Security"]',
    '["Đam mê mảng bảo mật tấn công (Offensive Security)", "Có thành tích tham gia các cuộc thi CTF là lợi thế lớn"]',
    4,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000018',
    '32000000-0000-4000-8000-000000000005',
    'Cloud Security & DevSecOps Trainee',
    'it_software',
    'active',
    'public',
    'Hà Nội',
    'hybrid',
    '3-5 tháng',
    'Đại học / Cao đẳng',
    'Triển khai các biện pháp bảo mật trên hạ tầng đám mây (AWS/GCP/OpenStack), tích hợp quy trình kiểm thử bảo mật tự động vào CI/CD pipeline.',
    'Trợ cấp thực tập tốt; Hỗ trợ kinh phí thi các chứng chỉ quốc tế (AWS Security, CKA, Security+).',
    '["Cloud Security", "Docker", "Kubernetes", "CI/CD", "DevSecOps", "Linux"]',
    '["Sinh viên chuyên ngành An toàn thông tin, Mạng máy tính hoặc CNTT"]',
    3,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),

-- [Dentsu Vietnam - 3 Posts]
(
    '40000000-0000-4000-8000-000000000019',
    '32000000-0000-4000-8000-000000000006',
    'Creative Copywriter & Content Strategist Intern',
    'design_media',
    'active',
    'public',
    'TP. Hồ Chí Minh',
    'full_time',
    '3-5 tháng',
    'Đại học / Cao đẳng',
    'Sáng tạo slogan, kịch bản video viral, nội dung bài viết cho các chiến dịch thương hiệu lớn của các nhãn hàng quốc tế tại Việt Nam.',
    'Phụ cấp 5.000.000 - 7.000.000 VNĐ; Môi trường Agency quốc tế sáng tạo không giới hạn; Cố vấn bởi Creative Director.',
    '["Creative Writing", "Copywriting", "Storytelling", "Content Strategy", "Social Media"]',
    '["Khả năng viết lách linh hoạt, nhạy bén ngôn từ và xu hướng giới trẻ", "Tiếng Anh tốt"]',
    4,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000020',
    '32000000-0000-4000-8000-000000000006',
    'Graphic & Motion Designer Trainee',
    'design_media',
    'active',
    'public',
    'TP. Hồ Chí Minh',
    'full_time',
    '3-6 tháng',
    'Đại học / Cao đẳng',
    'Thiết kế ấn phẩm truyền thông đa phương tiện (Key Visual, Banner, Infographic, Motion Graphic) cho các chiến dịch quảng cáo số.',
    'Trợ cấp thực tập hấp dẫn; Xây dựng portfolio ấn tượng với các dự án thực tế của các nhãn hàng hàng đầu.',
    '["Adobe Illustrator", "Photoshop", "After Effects", "Motion Graphics", "Typography"]',
    '["Gửi kèm Portfolio thiết kế hoặc video motion", "Gu thẩm mỹ hiện đại, cập nhật xu hướng thiết kế toàn cầu"]',
    3,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000021',
    '32000000-0000-4000-8000-000000000006',
    'Social Media Campaign & PR Coordinator Trainee',
    'business_marketing',
    'active',
    'public',
    'TP. Hồ Chí Minh',
    'hybrid',
    '3-4 tháng',
    'Đại học / Cao đẳng',
    'Phối hợp với mạng lưới KOLs/Influencers, quản trị fanpage chiến dịch, theo dõi thảo luận xã hội (Social Listening) và tổ chức sự kiện họp báo.',
    'Trợ cấp thực tập; Tham gia các sự kiện truyền thông lớn; Mở rộng quan hệ với các đối tác truyền thông.',
    '["PR & Event", "Influencer Marketing", "Social Listening", "Communication", "Event Planning"]',
    '["Năng động, giao tiếp linh hoạt, có kỹ năng tổ chức và giải quyết vấn đề tốt"]',
    4,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE
    `enterpriseId` = VALUES(`enterpriseId`),
    `title` = VALUES(`title`),
    `field` = VALUES(`field`),
    `status` = 'active',
    `audience` = VALUES(`audience`),
    `location` = VALUES(`location`),
    `workType` = VALUES(`workType`),
    `duration` = VALUES(`duration`),
    `educationLevel` = VALUES(`educationLevel`),
    `description` = VALUES(`description`),
    `benefits` = VALUES(`benefits`),
    `skillsJson` = VALUES(`skillsJson`),
    `requirementsJson` = VALUES(`requirementsJson`),
    `slots` = VALUES(`slots`),
    `deadline` = VALUES(`deadline`),
    `updatedAt` = NOW(6);

-- ----------------------------------------------------------------------------
-- 5. SEED TARGET SCHOOLS FOR PARTNER-SCHOOL POSTS
-- ----------------------------------------------------------------------------
INSERT INTO `internship_post_target_schools` (`postId`, `schoolId`, `createdAt`)
VALUES
-- FPT Tech Camp target THPT Nguyễn Trãi & THPT FPT
('40000000-0000-4000-8000-000000000004', '20000000-0000-4000-8000-000000000001', NOW(6)),
('40000000-0000-4000-8000-000000000004', '10000000-0000-4000-8000-000000000001', NOW(6)),
-- VNG Youth Discovery target THPT Nguyễn Trãi
('40000000-0000-4000-8000-000000000008', '20000000-0000-4000-8000-000000000001', NOW(6))
ON DUPLICATE KEY UPDATE `postId` = VALUES(`postId`);

-- ----------------------------------------------------------------------------
-- 6. SEED 4 STUDENT PROJECTS (DỰ ÁN NGHIÊN CỨU & ĐỔI MỚI SÁNG TẠO)
-- ----------------------------------------------------------------------------
INSERT INTO `projects` (
    `id`, `schoolId`, `mentorTeacherId`, `title`, `category`,
    `description`, `fundingGoal`, `projectUrl`, `startAt`, `endAt`, `status`, `createdAt`, `updatedAt`
) VALUES
(
    '50000000-0000-4000-8000-000000000001',
    '22000000-b512-4ede-852b-f4a508f3e837',
    '22000000-a084-4652-8a62-805d1613cf38',
    'Ứng dụng AI phân loại rác & Tái chế thông minh trong học đường (EcoSmart AI)',
    'career_technical',
    'Hệ thống tích hợp camera AI nhận diện các loại rác thải tại nguồn và tự động phân loại, kết nối ứng dụng tích điểm xanh đổi quà cho học sinh sinh viên.',
    25000000.00,
    'https://github.com/talenthub-demo/ecosmart-ai',
    '2026-09-01 08:00:00.000000',
    '2026-12-30 17:00:00.000000',
    'in_progress',
    NOW(6),
    NOW(6)
),
(
    '50000000-0000-4000-8000-000000000002',
    'da811c4f-2f74-4fdd-80b0-dd6f26109783',
    '22000000-7a01-474d-8565-b769341ee9d2',
    'Game Giáo dục 3D: Hành trình Khám phá Di sản Lịch sử Việt Nam (HeritageQuest)',
    'career_arts',
    'Tựa game nhập vai tương tác 3D tái hiện các di tích lịch sử và văn hóa dân tộc, giúp học sinh tiếp thu kiến thức lịch sử một cách sinh động qua công nghệ Unreal Engine.',
    35000000.00,
    'https://github.com/talenthub-demo/heritage-quest-3d',
    '2026-09-10 08:00:00.000000',
    '2027-01-15 17:00:00.000000',
    'in_progress',
    NOW(6),
    NOW(6)
),
(
    '50000000-0000-4000-8000-000000000003',
    '22000000-b512-4ede-852b-f4a508f3e837',
    '22000000-dc34-49ed-81d4-78446b313553',
    'Nền tảng Sàn kết nối Nông sản số cho Hợp tác xã Thanh niên Khởi nghiệp (AgriBridge)',
    'career_business',
    'Mô hình thương mại điện tử kết nối trực tiếp các sản phẩm OCOP của thanh niên nông thôn với thị trường thành thị, minh bạch hóa chuỗi cung ứng bằng QR truy xuất nguồn gốc.',
    40000000.00,
    'https://github.com/talenthub-demo/agri-bridge-ecom',
    '2026-08-15 08:00:00.000000',
    '2026-12-15 17:00:00.000000',
    'in_progress',
    NOW(6),
    NOW(6)
),
(
    '50000000-0000-4000-8000-000000000004',
    '20000000-0000-4000-8000-000000000001',
    '20000000-0000-4000-8000-000000000053',
    'Hệ thống Cảnh báo Sớm An toàn Mạng & Phòng chống Bắt nạt Học đường (EduShield)',
    'career_technical',
    'Giải pháp phần mềm hỗ trợ nhà trường phát hiện các hành vi đe dọa, bắt nạt trực tuyến và cảnh báo rủi ro lừa đảo mạng thông qua thuật toán xử lý ngôn ngữ tự nhiên.',
    20000000.00,
    'https://github.com/talenthub-demo/edushield-safety',
    '2026-09-01 08:00:00.000000',
    '2026-11-30 17:00:00.000000',
    'in_progress',
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE
    `schoolId` = VALUES(`schoolId`),
    `mentorTeacherId` = VALUES(`mentorTeacherId`),
    `title` = VALUES(`title`),
    `category` = VALUES(`category`),
    `description` = VALUES(`description`),
    `fundingGoal` = VALUES(`fundingGoal`),
    `projectUrl` = VALUES(`projectUrl`),
    `status` = 'in_progress',
    `updatedAt` = NOW(6);

-- ----------------------------------------------------------------------------
-- 7. SEED PROJECT SPONSORSHIPS (DOANH NGHIỆP TÀI TRỢ DỰ ÁN)
-- ----------------------------------------------------------------------------
INSERT INTO `project_sponsorships` (
    `id`, `enterpriseId`, `projectId`, `amount`, `currency`, `status`, `note`, `createdAt`, `updatedAt`
) VALUES
(
    '51000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000003',
    '50000000-0000-4000-8000-000000000001',
    25000000.00,
    'VND',
    'paid',
    'FPT Software tài trợ trọn gói quỹ nghiên cứu phát triển thuật toán AI và thiết bị camera phần cứng.',
    NOW(6),
    NOW(6)
),
(
    '51000000-0000-4000-8000-000000000002',
    '32000000-0000-4000-8000-000000000002',
    '50000000-0000-4000-8000-000000000002',
    35000000.00,
    'VND',
    'paid',
    'VNG Corporation tài trợ kinh phí đồ họa 3D, bản quyền engine và hỗ trợ cố vấn kỹ thuật Game.',
    NOW(6),
    NOW(6)
),
(
    '51000000-0000-4000-8000-000000000003',
    '32000000-0000-4000-8000-000000000003',
    '50000000-0000-4000-8000-000000000003',
    30000000.00,
    'VND',
    'paid',
    'Shopee Vietnam tài trợ quỹ khuyến khích khởi nghiệp số và hỗ trợ truyền thông gian hàng sinh viên.',
    NOW(6),
    NOW(6)
),
(
    '51000000-0000-4000-8000-000000000004',
    '32000000-0000-4000-8000-000000000005',
    '50000000-0000-4000-8000-000000000004',
    20000000.00,
    'VND',
    'paid',
    'Viettel Cyber Security tài trợ giải pháp máy chủ kiểm thử an toàn mạng và thiết bị bảo vệ đầu cuối.',
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE
    `amount` = VALUES(`amount`),
    `status` = VALUES(`status`),
    `note` = VALUES(`note`),
    `updatedAt` = NOW(6);

-- ----------------------------------------------------------------------------
-- 8. SEED SCHOOL - ENTERPRISE PARTNERSHIPS
-- ----------------------------------------------------------------------------
INSERT INTO `school_enterprise_partnerships` (
    `id`, `schoolId`, `enterpriseId`, `status`, `requestedByUserId`, `reviewedByUserId`, `reviewedAt`, `createdAt`, `updatedAt`
) VALUES
-- FPT Software & ĐH FPT
(
    '52000000-0000-4000-8000-000000000001',
    '22000000-b512-4ede-852b-f4a508f3e837',
    '10000000-0000-4000-8000-000000000003',
    'approved',
    '10000000-0000-4000-8000-000000000014',
    '22000000-8d33-4ff2-8547-b8e8e91895b5',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- FPT Software & BTEC FPT
(
    '52000000-0000-4000-8000-000000000002',
    'da811c4f-2f74-4fdd-80b0-dd6f26109783',
    '10000000-0000-4000-8000-000000000003',
    'approved',
    '10000000-0000-4000-8000-000000000014',
    '20000000-0000-4000-8000-000000000010',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- VNG & BTEC FPT
(
    '52000000-0000-4000-8000-000000000003',
    'da811c4f-2f74-4fdd-80b0-dd6f26109783',
    '32000000-0000-4000-8000-000000000002',
    'approved',
    '31000000-0000-4000-8000-000000000012',
    '20000000-0000-4000-8000-000000000010',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- VNG & ĐH FPT
(
    '52000000-0000-4000-8000-000000000004',
    '22000000-b512-4ede-852b-f4a508f3e837',
    '32000000-0000-4000-8000-000000000002',
    'approved',
    '31000000-0000-4000-8000-000000000012',
    '22000000-8d33-4ff2-8547-b8e8e91895b5',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- Shopee & ĐH FPT
(
    '52000000-0000-4000-8000-000000000005',
    '22000000-b512-4ede-852b-f4a508f3e837',
    '32000000-0000-4000-8000-000000000003',
    'approved',
    '31000000-0000-4000-8000-000000000013',
    '22000000-8d33-4ff2-8547-b8e8e91895b5',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- Techcombank & ĐH FPT
(
    '52000000-0000-4000-8000-000000000006',
    '22000000-b512-4ede-852b-f4a508f3e837',
    '32000000-0000-4000-8000-000000000004',
    'approved',
    '31000000-0000-4000-8000-000000000014',
    '22000000-8d33-4ff2-8547-b8e8e91895b5',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- Viettel Cyber Security & THPT Nguyễn Trãi
(
    '52000000-0000-4000-8000-000000000007',
    '20000000-0000-4000-8000-000000000001',
    '32000000-0000-4000-8000-000000000005',
    'approved',
    '31000000-0000-4000-8000-000000000015',
    '20000000-0000-4000-8000-000000000010',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- Dentsu & ĐH FPT
(
    '52000000-0000-4000-8000-000000000008',
    '22000000-b512-4ede-852b-f4a508f3e837',
    '32000000-0000-4000-8000-000000000006',
    'approved',
    '31000000-0000-4000-8000-000000000016',
    '22000000-8d33-4ff2-8547-b8e8e91895b5',
    NOW(6),
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE
    `status` = 'approved',
    `updatedAt` = NOW(6);

SET FOREIGN_KEY_CHECKS = 1;