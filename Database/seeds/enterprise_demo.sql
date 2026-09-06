-- ============================================================================
-- TalentHub - Enterprise & Opportunities Dataset (Demo & Production Seed)
-- Compatible with MySQL 8+ / MariaDB (Laragon, HeidiSQL, CLI)
-- 6 Enterprises, 21 Internship Posts, 4 Projects, 4 Sponsorships, 10 Partnerships
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
    'Công ty Cổ phần Sữa Việt Nam (Vinamilk)',
    'active',
    '/assets/images/vinamilk-logo.svg',
    'Hàng tiêu dùng nhanh (FMCG), Kinh tế & Quản trị Chuỗi cung ứng',
    '10,000+ nhân viên',
    1976,
    'Công ty Cổ phần Sữa Việt Nam (Vinamilk) là doanh nghiệp dinh dưỡng hàng đầu Việt Nam và thuộc Top 40 công ty sữa lớn nhất thế giới, tiên phong đổi mới sáng tạo, phát triển bền vững và đào tạo thế hệ tài năng kinh tế, marketing và chuỗi cung ứng.',
    'vinamilk@talenthub.local',
    '028 5415 5555',
    'https://www.vinamilk.com.vn',
    '0300588569',
    'Số 10 Tân Trào, Phường Tân Phú, Quận 7, TP. Hồ Chí Minh',
    'verified',
    NOW(6),
    NOW(6)
),
(
    '32000000-0000-4000-8000-000000000020',
    'Ngân hàng TMCP Quân đội (MB Bank)',
    'active',
    '/assets/images/mbbank-logo.svg',
    'Tài chính, Ngân hàng số & Fintech',
    '15,000+ nhân viên',
    1994,
    'Ngân hàng TMCP Quân đội (MB Bank) là một trong những định chế tài chính - ngân hàng số hàng đầu Việt Nam, tiên phong trong ứng dụng công nghệ Fintech, phục vụ hơn 25 triệu khách hàng và thúc đẩy phát triển kinh tế số.',
    'mbbank@talenthub.local',
    '1900 545426',
    'https://mbbank.com.vn',
    '0100283873',
    'Tòa nhà MB Grand Tower, Hà Nội & Chi nhánh Cần Thơ / TP.HCM',
    'verified',
    NOW(6),
    NOW(6)
),
(
    '32000000-0000-4000-8000-000000000005',
    'Công ty TNHH Phần mềm FPT',
    'active',
    '/assets/images/fpt-software-logo.svg',
    'Công nghệ thông tin & Trí tuệ nhân tạo (IT & AI)',
    '10,000+ nhân viên',
    1999,
    'FPT Software là công ty công nghệ và dịch vụ phần mềm hàng đầu thế giới có trụ sở chính tại Việt Nam, tiên phong trong chuyển đổi số, AI và đào tạo phát triển tài năng trẻ.',
    'fpt@talenthub.local',
    '024 7300 7575',
    'https://fptsoftware.com',
    '0101234567',
    'Tòa nhà FPT, Phố Duy Tân, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Hà Nội',
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
    'vinamilk@talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'Công ty Cổ phần Sữa Việt Nam (Vinamilk)',
    'active',
    NOW(6),
    NOW(6)
),
(
    '31000000-0000-4000-8000-000000000020',
    '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    'mbbank@talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'Ban Tuyển Dụng MB Bank',
    'active',
    NOW(6),
    NOW(6)
),
(
    '31000000-0000-4000-8000-000000000021',
    '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    'biz@talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'Ban Tuyển Dụng MB Bank',
    'active',
    NOW(6),
    NOW(6)
),
(
    '31000000-0000-4000-8000-000000000015',
    '8dcbaaac-be69-5d75-92e0-cdd0289642e3',
    'fpt@talenthub.local',
    '$2y$10$DfZIjnlaXyoliKkRzgRB8.vJdFFpmbvUBkuUE0kAQI21XUIQn5/0W',
    'FPT Software Talent Acquisition',
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
    '33000000-0000-4000-8000-000000000020',
    '32000000-0000-4000-8000-000000000020',
    '31000000-0000-4000-8000-000000000020',
    'admin',
    NOW(6),
    NOW(6)
),
(
    '33000000-0000-4000-8000-000000000021',
    '32000000-0000-4000-8000-000000000020',
    '31000000-0000-4000-8000-000000000021',
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
-- [FPT Software - 3 Posts]
(
    '10909e00-1e49-4373-97a0-c9519c74d659',
    '10000000-0000-4000-8000-000000000003',
    'Frontend Developer (ReactJS / Vue.js)',
    'it_software',
    'active',
    'public',
    'Hà Nội & TP. Hồ Chí Minh',
    'full_time',
    '3 - 6 tháng',
    'Đại học / Cao đẳng',
    'Tham gia phát triển các ứng dụng web quy mô lớn bằng ReactJS, Vue.js, TypeScript và tích hợp RESTful API / GraphQL cho các khách hàng quốc tế của FPT Software.',
    'Trợ cấp thực tập từ 6.000.000 - 9.000.000 VNĐ/tháng; Cố vấn kỹ thuật 1-1 từ Senior Web Architect; Hỗ trợ con dấu thực tập tốt nghiệp và cơ hội lên nhân viên chính thức.',
    '["React", "Vue.js", "JavaScript", "TypeScript", "HTML/CSS", "REST API", "Git"]',
    '["Sinh viên năm 3-4 chuyên ngành CNTT, Kỹ thuật Phần mềm hoặc tương đương", "Có kiến thức vững về HTML5, CSS3, JavaScript/TypeScript và framework ReactJS hoặc Vue.js", "Có tư duy thẩm mỹ UI/UX tốt và khả năng làm việc nhóm Agile/Scrum"]',
    6,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000003',
    'Thực tập sinh Trí tuệ Nhân tạo & LLM (AI/GenAI Intern)',
    'it_software',
    'active',
    'public',
    'Hà Nội & TP. Hồ Chí Minh',
    'full_time',
    '4 - 6 tháng',
    'Đại học / Cao đẳng',
    'Tham gia nghiên cứu và ứng dụng các mô hình GenAI, LLM (RAG, Fine-tuning), xử lý ngôn ngữ tự nhiên (NLP) và Computer Vision vào các giải pháp số thực tế tại FPT Software.',
    'Trợ cấp hàng tháng từ 7.000.000 - 10.000.000 VNĐ; Tiếp cận hạ tầng GPU server hiện đại; Cố vấn 1-1 từ AI Specialist; Cơ hội chuyển thẳng thành nhân viên chính thức.',
    '["Python", "PyTorch", "Generative AI", "NLP", "Machine Learning", "Computer Vision"]',
    '["Sinh viên năm 3-4 chuyên ngành Khoa học Máy tính, Trí tuệ Nhân tạo hoặc Khoa học Dữ liệu", "GPA từ 3.0/4.0 trở lên, có tư duy toán học và giải thuật tốt", "Thành thạo Python, có kinh nghiệm với PyTorch/TensorFlow và các mô hình học sâu"]',
    5,
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
    'Hà Nội & Đà Nẵng',
    'hybrid',
    '3 - 4 tháng',
    'Đại học / Cao đẳng',
    'Xây dựng và thực thi các kịch bản kiểm thử tự động (Automation Test) cho Web, API và Mobile bằng Selenium, Playwright, Cypress hoặc Appium; tham gia kiểm thử hiệu năng hệ thống.',
    'Trợ cấp thực tập từ 5.000.000 - 8.000.000 VNĐ; Tài trợ thi chứng chỉ quốc tế ISTQB; Đào tạo quy trình kiểm thử chuẩn quốc tế.',
    '["Automation Testing", "Selenium", "Playwright", "Java", "Python", "API Testing", "Git"]',
    '["Sinh viên năm cuối hoặc mới tốt nghiệp chuyên ngành CNTT, Đảm bảo chất lượng phần mềm", "Tỉ mỉ, cẩn thận, nắm vững quy trình kiểm thử phần mềm (STLC/SDLC)", "Biết lập trình cơ bản bằng Java hoặc Python"]',
    4,
    '2026-12-31 23:59:59.000000',
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

-- [Vinamilk - 3 Posts]
(
    '40000000-0000-4000-8000-000000000031',
    '32000000-0000-4000-8000-000000000003',
    'Quản trị viên Tập sự Marketing & Phát triển Thương hiệu (Brand Marketing Trainee)',
    'business_marketing',
    'active',
    'public',
    'TP. Hồ Chí Minh & Cần Thơ',
    'full_time',
    '3-6 tháng',
    'Đại học / Cao đẳng',
    'Tham gia trực tiếp cùng đội ngũ Brand Marketing của Vinamilk trong việc xây dựng chiến lược truyền thông đa kênh, nghiên cứu hành vi người tiêu dùng ngành hàng dinh dưỡng, sáng tạo nội dung quảng bá và tối ưu hóa chuyển đổi chiến dịch.',
    'Phụ cấp thực tập từ 8.000.000 - 12.000.000 VNĐ/tháng, tham gia chương trình Vinamilk Management Trainee, đào tạo 1-1 với Brand Manager kỳ cựu và cơ hội ký hợp đồng chính thức sau kỳ thực tập.',
    '["Phân tích thị trường", "Digital Marketing", "Sáng tạo nội dung", "Kỹ năng thuyết trình", "Quản trị thương hiệu"]',
    '["Sáng tạo chiến dịch quảng bá, Nghiên cứu thị trường, Kỹ năng thuyết trình", "Sinh viên năm 3-4 chuyên ngành Marketing, Quản trị Kinh doanh, Truyền thông hoặc Kinh tế", "Tiếng Anh giao tiếp tốt (ưu tiên TOEIC 750+ / IELTS 6.0+), tự tin thuyết trình và làm việc nhóm"]',
    6,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000032',
    '32000000-0000-4000-8000-000000000003',
    'Thực tập sinh Quản trị Chuỗi cung ứng & Logistics (Supply Chain Intern)',
    'business_marketing',
    'active',
    'public',
    'TP. Hồ Chí Minh / Bình Dương',
    'full_time',
    '3-6 tháng',
    'Đại học / Cao đẳng',
    'Tham gia quản trị vận hành kho bãi hiện đại, trung tâm phân phối Mega Market của Vinamilk, tối ưu hóa mạng lưới điều độ đơn hàng, kiểm soát tồn kho và phân tích dữ liệu chuỗi cung ứng lạnh (Cold Chain).',
    'Phụ cấp thực tập từ 7.500.000 - 10.500.000 VNĐ/tháng; Hỗ trợ xe đưa đón và phụ cấp cơm trưa tại các nhà máy/kho vận Mega Factory; Đào tạo chuyên sâu về hệ thống ERP SAP S/4HANA.',
    '["Quản lý kho vận", "Tối ưu hóa đơn hàng", "Phân tích dữ liệu vận hành", "Excel nâng cao"]',
    '["Quản lý kho vận, Tối ưu hóa đơn hàng, Phân tích dữ liệu vận hành", "Sinh viên chuyên ngành Logistics, Quản lý Chuỗi cung ứng, Quản lý Công nghiệp hoặc Kinh tế đối ngoại", "Tư duy logic tốt, thành thạo Excel / Phân tích số liệu và cẩn thận, trách nhiệm"]',
    5,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000033',
    '32000000-0000-4000-8000-000000000003',
    'Thực tập sinh Tài chính - Kế toán Doanh nghiệp (Corporate Finance Trainee)',
    'business_marketing',
    'active',
    'public',
    'TP. Hồ Chí Minh / Hà Nội',
    'full_time',
    '3-6 tháng',
    'Đại học / Cao đẳng',
    'Hỗ trợ lập báo cáo tài chính định kỳ, phân tích biến động chi phí giá thành sản xuất (Cost Accounting), đối soát công nợ đối tác thương mại và tham gia xây dựng mô hình dự báo tài chính doanh nghiệp.',
    'Phụ cấp thực tập từ 8.000.000 - 11.000.000 VNĐ/tháng; Trực tiếp làm việc với hệ thống kế toán quản trị chuyên nghiệp chuẩn mực IFRS; Cơ hội ưu tiên tuyển dụng chuyên viên tài chính.',
    '["Lập báo cáo tài chính", "Kế toán chi phí", "Excel nâng cao", "Phân tích tài chính"]',
    '["Lập báo cáo tài chính, Kế toán chi phí, Kỹ năng Excel nâng cao", "Sinh viên năm cuối chuyên ngành Tài chính - Ngân hàng, Kế toán - Kiểm toán hoặc Kinh tế", "Nắm chắc nguyên lý kế toán và phân tích báo cáo tài chính, cẩn trọng, bảo mật thông tin"]',
    4,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),

-- [MB Bank - 2 Posts]
(
    '40000000-0000-4000-8000-000000000021',
    '32000000-0000-4000-8000-000000000020',
    'Thực tập sinh Phân tích Dữ liệu Kinh doanh (Business Intelligence Intern)',
    'business_marketing',
    'active',
    'public',
    'Cần Thơ / TP.HCM / Hybrid',
    'hybrid',
    '3 - 6 tháng',
    'Đại học / Cao đẳng năm 3 - 4',
    'Tham gia phân tích hành vi người dùng trên App MBBank, xây dựng báo cáo phân tích dữ liệu kinh doanh và trực quan hóa Dashboard chỉ số KPI bằng PowerBI/SQL.',
    'Phụ cấp thực tập từ 8.000.000 - 12.000.000 VNĐ/tháng, cơ hội chuyển chính thức Chuyên viên Phân tích Dữ liệu tại MB Bank sau kỳ thực tập.',
    '["SQL", "PowerBI", "Excel nâng cao", "Phân tích dữ liệu"]',
    '["SQL, Excel nâng cao, PowerBI, Tư duy phân tích số liệu", "Tư duy phân tích số liệu nhạy bén và đam mê ngành tài chính - ngân hàng số", "Kỹ năng làm việc nhóm, giao tiếp và thuyết trình báo cáo dữ liệu tốt"]',
    5,
    '2026-12-31 23:59:59.000000',
    NOW(6),
    NOW(6)
),
(
    '40000000-0000-4000-8000-000000000022',
    '32000000-0000-4000-8000-000000000020',
    'Thực tập sinh Digital Marketing & Truyền thông Thương hiệu',
    'business_marketing',
    'active',
    'public',
    'TP.HCM / Cần Thơ',
    'full_time',
    '3 - 6 tháng',
    'Đại học / Cao đẳng',
    'Tham gia sáng tạo nội dung truyền thông cho các chiến dịch quảng bá sản phẩm thẻ, ngân hàng số MBBank; phối hợp vận hành quảng cáo đa kênh và quản trị cộng đồng người dùng.',
    'Phụ cấp thực tập từ 7.000.000 - 10.000.000 VNĐ/tháng, đào tạo kỹ năng Digital Marketing thực chiến cùng chuyên gia MB Bank.',
    '["Digital Marketing", "Content Creator", "SEO", "Google Analytics", "Video Editing"]',
    '["Sáng tạo nội dung, Chạy ads đa kênh, Kỹ năng làm việc nhóm", "Khả năng viết content sáng tạo và nắm bắt xu hướng Gen Z trên TikTok/Facebook/YouTube", "Chủ động, cầu tiến, có tinh thần trách nhiệm cao trong công việc"]',
    4,
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
-- Reconcile the complete target set for these demo posts so reruns remove stale
-- or historically incorrect targets before the integrity assertion executes.
DELETE FROM `internship_post_target_schools`
WHERE `postId` IN (
    '40000000-0000-4000-8000-000000000004',
    '40000000-0000-4000-8000-000000000008'
);

INSERT INTO `internship_post_target_schools` (`postId`, `schoolId`, `createdAt`)
VALUES
-- FPT Tech Camp target THPT Nguyễn Trãi
('40000000-0000-4000-8000-000000000004', '20000000-0000-4000-8000-000000000001', NOW(6)),
-- VNG Youth Discovery target THPT Nguyễn Trãi
('40000000-0000-4000-8000-000000000008', '20000000-0000-4000-8000-000000000001', NOW(6))
ON DUPLICATE KEY UPDATE `postId` = VALUES(`postId`);

-- ----------------------------------------------------------------------------
-- 6. SEED 3 STUDENT PROJECTS (DỰ ÁN NGHIÊN CỨU & ĐỔI MỚI SÁNG TẠO)
-- ----------------------------------------------------------------------------
INSERT INTO `projects` (
    `id`, `schoolId`, `mentorTeacherId`, `title`, `category`,
    `description`, `fundingGoal`, `projectUrl`, `startAt`, `endAt`, `status`, `createdAt`, `updatedAt`
) VALUES
(
    '50000000-0000-4000-8000-000000000001',
    'da811c4f-2f74-4fdd-80b0-dd6f26109783',
    '22000000-a084-4652-8a62-805d1613cf38',
    'Ứng dụng AI phân loại rác & Tái chế thông minh',
    'AI & Phần mềm',
    'Hệ thống Computer Vision và AI Edge tích hợp thùng rác thông minh tự động nhận diện và phân loại rác hữu cơ, rác tái chế và rác vô cơ với độ chính xác trên 95%.',
    25000000.00,
    'https://github.com/talenthub-demo/ai-smart-recycle',
    '2026-08-01 08:00:00.000000',
    '2026-12-31 17:00:00.000000',
    'in_progress',
    NOW(6),
    NOW(6)
),
(
    '50000000-0000-4000-8000-000000000002',
    '22000000-b512-4ede-852b-f4a508f3e837',
    '22000000-7a01-474d-8565-b769341ee9d2',
    'Game Giáo dục 3D: Hành trình Khám phá Di sản Lịch sử',
    'Đồ họa 3D & Đa phương tiện',
    'Trò chơi nhập vai 3D tái hiện các di tích lịch sử và văn hóa Việt Nam trên nền tảng Unity/Unreal Engine, giúp học sinh trải nghiệm học sử trực quan, hấp dẫn.',
    35000000.00,
    'https://github.com/talenthub-demo/heritage-quest-3d',
    '2026-08-01 08:00:00.000000',
    '2026-12-31 17:00:00.000000',
    'in_progress',
    NOW(6),
    NOW(6)
),
(
    '50000000-0000-4000-8000-000000000003',
    '23000000-0000-4000-8000-000000000001',
    '22000000-dc34-49ed-81d4-78446b313553',
    'Nền tảng Sàn kết nối Nông sản số & Truy xuất nguồn gốc',
    'Kinh tế số & Thương mại điện tử',
    'Sàn thương mại điện tử kết nối nông sản OCOP Đồng bằng Sông Cửu Long, tích hợp tem QR truy xuất nguồn gốc nông trại và thanh toán trực tuyến an toàn.',
    30000000.00,
    'https://github.com/talenthub-demo/agri-bridge-ecom',
    '2026-08-01 08:00:00.000000',
    '2026-12-31 17:00:00.000000',
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
    20000000.00,
    'VND',
    'paid',
    'FPT Software tài trợ kinh phí nghiên cứu mô hình AI phân loại rác và thiết bị IoT.',
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
    'VNG Corporation tài trợ trọn gói kinh phí đồ họa 3D và cố vấn sản phẩm game.',
    NOW(6),
    NOW(6)
),
(
    '51000000-0000-4000-8000-000000000003',
    '32000000-0000-4000-8000-000000000003',
    '50000000-0000-4000-8000-000000000003',
    15000000.00,
    'VND',
    'paid',
    'Công ty Cổ phần Sữa Việt Nam (Vinamilk) tài trợ học bổng Ươm mầm Tài năng Dinh dưỡng và Chuỗi cung ứng bền vững.',
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
-- Vinamilk & ĐH FPT
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
-- MB Bank & ĐH FPT
(
    '52000000-0000-4000-8000-000000000006',
    '22000000-b512-4ede-852b-f4a508f3e837',
    '32000000-0000-4000-8000-000000000020',
    'approved',
    '31000000-0000-4000-8000-000000000020',
    '22000000-8d33-4ff2-8547-b8e8e91895b5',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- FPT Software & ĐH Cần Thơ
(
    '52000000-0000-4000-8000-000000000007',
    '23000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000003',
    'approved',
    '31000000-0000-4000-8000-000000000015',
    '10000000-0000-4000-8000-000000000013',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- MB Bank & ĐH Cần Thơ
(
    '52000000-0000-4000-8000-000000000021',
    '23000000-0000-4000-8000-000000000001',
    '32000000-0000-4000-8000-000000000020',
    'approved',
    '31000000-0000-4000-8000-000000000020',
    '10000000-0000-4000-8000-000000000013',
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
),
-- FPT Software & THPT Nguyễn Trãi (targets post 400...004)
(
    '52000000-0000-4000-8000-000000000009',
    '20000000-0000-4000-8000-000000000001',
    '10000000-0000-4000-8000-000000000003',
    'approved',
    '10000000-0000-4000-8000-000000000014',
    '20000000-0000-4000-8000-000000000010',
    NOW(6),
    NOW(6),
    NOW(6)
),
-- VNG & THPT Nguyễn Trãi (targets post 400...008)
(
    '52000000-0000-4000-8000-000000000010',
    '20000000-0000-4000-8000-000000000001',
    '32000000-0000-4000-8000-000000000002',
    'approved',
    '31000000-0000-4000-8000-000000000012',
    '20000000-0000-4000-8000-000000000010',
    NOW(6),
    NOW(6),
    NOW(6)
)
ON DUPLICATE KEY UPDATE
    `status` = 'approved',
    `updatedAt` = NOW(6);

SET FOREIGN_KEY_CHECKS = 1;
