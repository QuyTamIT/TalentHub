<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

echo "=== 1. UPDATING ENTERPRISE (FPT SOFTWARE) ===\n";

$fptEnterpriseId = '10000000-0000-4000-8000-000000000003';
$altEnterpriseId = '32000000-0000-4000-8000-000000000005';
$now = gmdate('Y-m-d H:i:s.u');

// Update main FPT Software enterprise record
$stmt = $pdo->prepare("
    INSERT INTO enterprises (
        id, name, status, logoUrl, industry, companySize, foundedYear, description,
        email, phone, website, taxCode, address, verificationStatus, createdAt, updatedAt
    ) VALUES (
        :id, 'Công ty TNHH Phần mềm FPT', 'active', '/assets/images/fpt-software-logo.svg',
        'Công nghệ thông tin & Trí tuệ nhân tạo (IT & AI)', '10,000+ nhân viên', 1999,
        'FPT Software là công ty công nghệ và dịch vụ phần mềm hàng đầu thế giới có trụ sở chính tại Việt Nam, tiên phong trong chuyển đổi số, AI và đào tạo phát triển tài năng trẻ.',
        'fpt@talenthub.local', '024 7300 7575', 'https://fptsoftware.com', '0101234567',
        'Tòa nhà FPT, Phố Duy Tân, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Hà Nội', 'verified', :now1, :now2
    ) ON DUPLICATE KEY UPDATE
        name = 'Công ty TNHH Phần mềm FPT',
        status = 'active',
        logoUrl = '/assets/images/fpt-software-logo.svg',
        industry = 'Công nghệ thông tin & Trí tuệ nhân tạo (IT & AI)',
        companySize = '10,000+ nhân viên',
        foundedYear = 1999,
        description = 'FPT Software là công ty công nghệ và dịch vụ phần mềm hàng đầu thế giới có trụ sở chính tại Việt Nam, tiên phong trong chuyển đổi số, AI và đào tạo phát triển tài năng trẻ.',
        email = 'fpt@talenthub.local',
        phone = '024 7300 7575',
        website = 'https://fptsoftware.com',
        taxCode = '0101234567',
        address = 'Tòa nhà FPT, Phố Duy Tân, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Hà Nội',
        verificationStatus = 'verified',
        updatedAt = :now3
");
$stmt->execute(['id' => $fptEnterpriseId, 'now1' => $now, 'now2' => $now, 'now3' => $now]);
echo " [OK] Main FPT Software enterprise updated ({$fptEnterpriseId}).\n";

// Also update the secondary enterprise ID (old Viettel record) to FPT Software so all queries resolve to FPT Software
$stmt = $pdo->prepare("
    INSERT INTO enterprises (
        id, name, status, logoUrl, industry, companySize, foundedYear, description,
        email, phone, website, taxCode, address, verificationStatus, createdAt, updatedAt
    ) VALUES (
        :id, 'Công ty TNHH Phần mềm FPT', 'active', '/assets/images/fpt-software-logo.svg',
        'Công nghệ thông tin & Trí tuệ nhân tạo (IT & AI)', '10,000+ nhân viên', 1999,
        'FPT Software là công ty công nghệ và dịch vụ phần mềm hàng đầu thế giới có trụ sở chính tại Việt Nam, tiên phong trong chuyển đổi số, AI và đào tạo phát triển tài năng trẻ.',
        'fpt.careers@talenthub.local', '024 7300 7575', 'https://fptsoftware.com', '0101234567',
        'Tòa nhà FPT, Phố Duy Tân, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Hà Nội', 'verified', :now1, :now2
    ) ON DUPLICATE KEY UPDATE
        name = 'Công ty TNHH Phần mềm FPT',
        status = 'active',
        logoUrl = '/assets/images/fpt-software-logo.svg',
        industry = 'Công nghệ thông tin & Trí tuệ nhân tạo (IT & AI)',
        companySize = '10,000+ nhân viên',
        foundedYear = 1999,
        description = 'FPT Software là công ty công nghệ và dịch vụ phần mềm hàng đầu thế giới có trụ sở chính tại Việt Nam, tiên phong trong chuyển đổi số, AI và đào tạo phát triển tài năng trẻ.',
        email = 'fpt.careers@talenthub.local',
        phone = '024 7300 7575',
        website = 'https://fptsoftware.com',
        taxCode = '0101234567',
        address = 'Tòa nhà FPT, Phố Duy Tân, Phường Dịch Vọng Hậu, Quận Cầu Giấy, Hà Nội',
        verificationStatus = 'verified',
        updatedAt = :now3
");
$stmt->execute(['id' => $altEnterpriseId, 'now1' => $now, 'now2' => $now, 'now3' => $now]);
echo " [OK] Secondary enterprise record updated to FPT Software ({$altEnterpriseId}).\n";

// Update any sponsorships mentioning Viettel
$pdo->exec("UPDATE project_sponsorships SET note = REPLACE(note, 'Viettel Cyber Security', 'FPT Software') WHERE note LIKE '%Viettel%'");

echo "=== 2. UPDATING ENTERPRISE USERS & AUTH ===\n";

$roleIdEnterprise = '8dcbaaac-be69-5d75-92e0-cdd0289642e3';
$hash123456 = password_hash('123456', PASSWORD_DEFAULT);

$entUsers = [
    [
        'id' => '10000000-0000-4000-8000-000000000014',
        'email' => 'business@test.talenthub.local',
        'fullName' => 'FPT Software',
    ],
    [
        'id' => '2e22d474-7f51-4118-9bdf-f0462c725629',
        'email' => 'enterprise@talenthub.local',
        'fullName' => 'FPT Software',
    ],
    [
        'id' => '31000000-0000-4000-8000-000000000015',
        'email' => 'fpt@talenthub.local',
        'fullName' => 'FPT Software',
    ],
    [
        'id' => '31000000-0000-4000-8000-000000000099',
        'email' => 'fpt.careers@talenthub.local',
        'fullName' => 'FPT Software',
    ],
];

foreach ($entUsers as $eu) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR id = ? LIMIT 1");
    $stmt->execute([$eu['email'], $eu['id']]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE users SET email = ?, fullName = ?, passwordHash = ?, roleId = ?, status = 'active' WHERE id = ?");
        $stmt->execute([$eu['email'], $eu['fullName'], $hash123456, $roleIdEnterprise, $existing]);
        $uid = (string)$existing;
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, 'active', ?, ?)");
        $stmt->execute([$eu['id'], $roleIdEnterprise, $eu['email'], $hash123456, $eu['fullName'], $now, $now]);
        $uid = $eu['id'];
    }

    $stmt = $pdo->prepare("SELECT id FROM enterprise_members WHERE userId = ? LIMIT 1");
    $stmt->execute([$uid]);
    $emId = $stmt->fetchColumn();
    if ($emId) {
        $pdo->prepare("UPDATE enterprise_members SET enterpriseId = ?, memberRole = 'admin', updatedAt = ? WHERE id = ?")
            ->execute([$fptEnterpriseId, $now, $emId]);
    } else {
        $newEmId = Uuid::v4();
        $pdo->prepare("INSERT INTO enterprise_members (id, enterpriseId, userId, memberRole, createdAt, updatedAt) VALUES (?, ?, ?, 'admin', ?, ?)")
            ->execute([$newEmId, $fptEnterpriseId, $uid, $now, $now]);
    }

    echo " [OK] Enterprise user {$eu['email']} ({$uid}) active with password '123456' linked to FPT Software.\n";
}

echo "=== 3. SEEDING SCHOOLS & PARTNERSHIPS ===\n";

$schools = [
    [
        'id' => '22000000-b512-4ede-852b-f4a508f3e837',
        'name' => 'Đại học FPT',
        'level' => 'Đại học',
        'email' => 'fpt.university@fe.edu.vn',
        'address' => 'Khu Công nghệ cao Hòa Lạc, Km29 Đại lộ Thăng Long, Thạch Thất, Hà Nội',
    ],
    [
        'id' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783',
        'name' => 'Cao đẳng Quốc tế BTEC FPT',
        'level' => 'Cao đẳng Quốc tế',
        'email' => 'btec.fpt@fe.edu.vn',
        'address' => 'Tòa nhà FPT Polytechnic, Phố Trịnh Văn Bô, Nam Từ Liêm, Hà Nội',
    ],
    [
        'id' => '23000000-0000-4000-8000-000000000001',
        'name' => 'Đại học Cần Thơ',
        'level' => 'Đại học',
        'email' => 'dhct@ctu.edu.vn',
        'address' => 'Khu II, Đường 3/2, Phường Xuân Khánh, Quận Ninh Kiều, TP. Cần Thơ',
    ],
];

foreach ($schools as $sch) {
    $stmt = $pdo->prepare("
        INSERT INTO schools (id, name, status, level, email, address, createdAt, updatedAt)
        VALUES (:id, :name, 'active', :level, :email, :address, :now1, :now2)
        ON DUPLICATE KEY UPDATE name = :name2, level = :level2, status = 'active', updatedAt = :now3
    ");
    $stmt->execute([
        'id' => $sch['id'],
        'name' => $sch['name'],
        'level' => $sch['level'],
        'email' => $sch['email'],
        'address' => $sch['address'],
        'name2' => $sch['name'],
        'level2' => $sch['level'],
        'now1' => $now,
        'now2' => $now,
        'now3' => $now
    ]);

    foreach ([$fptEnterpriseId, $altEnterpriseId] as $entId) {
        $stmt = $pdo->prepare("SELECT id FROM school_enterprise_partnerships WHERE schoolId = ? AND enterpriseId = ? LIMIT 1");
        $stmt->execute([$sch['id'], $entId]);
        if (!$stmt->fetchColumn()) {
            $partId = Uuid::v4();
            $pdo->prepare("INSERT INTO school_enterprise_partnerships (id, schoolId, enterpriseId, status, requestedByUserId, reviewedByUserId, reviewedAt, createdAt, updatedAt) VALUES (?, ?, ?, 'approved', '10000000-0000-4000-8000-000000000014', '10000000-0000-4000-8000-000000000013', ?, ?, ?)")
                ->execute([$partId, $sch['id'], $entId, $now, $now, $now]);
        } else {
            $pdo->prepare("UPDATE school_enterprise_partnerships SET status = 'approved', updatedAt = ? WHERE schoolId = ? AND enterpriseId = ?")
                ->execute([$now, $sch['id'], $entId]);
        }
    }
    echo " [OK] School {$sch['name']} & partnership approved.\n";
}

// Classes for schools
$classes = [
    ['id' => '22000000-ba56-41f9-800b-26b41b0a9b5c', 'schoolId' => '22000000-b512-4ede-852b-f4a508f3e837', 'name' => 'K18 CNTT (Năm 4)', 'gradeLevel' => 4, 'academicYear' => '2025-2026'],
    ['id' => 'bc0be670-12fd-545b-a70f-3ebf8ce3fad7', 'schoolId' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783', 'name' => 'BTEC-SE-2026A', 'gradeLevel' => 3, 'academicYear' => '2025-2026'],
    ['id' => 'a1e2894b-2386-5404-9695-78a78f5a60d3', 'schoolId' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783', 'name' => 'BTEC-AI-2026A', 'gradeLevel' => 3, 'academicYear' => '2025-2026'],
    ['id' => '23000000-0000-4000-8000-000000000002', 'schoolId' => '23000000-0000-4000-8000-000000000001', 'name' => 'K47 CNTT (Năm 4)', 'gradeLevel' => 4, 'academicYear' => '2025-2026'],
];

foreach ($classes as $cls) {
    $stmt = $pdo->prepare("
        INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt)
        VALUES (:id, :schoolId, :name, :gradeLevel, :academicYear, 'active', :now1, :now2)
        ON DUPLICATE KEY UPDATE name = :name2, status = 'active', updatedAt = :now3
    ");
    $stmt->execute([
        'id' => $cls['id'],
        'schoolId' => $cls['schoolId'],
        'name' => $cls['name'],
        'gradeLevel' => $cls['gradeLevel'],
        'academicYear' => $cls['academicYear'],
        'name2' => $cls['name'],
        'now1' => $now,
        'now2' => $now,
        'now3' => $now
    ]);
}

echo "=== 4. SEEDING SKILLS CATALOG ===\n";

$skillsCatalog = [
    ['code' => 'react', 'name' => 'React', 'category' => 'technical'],
    ['code' => 'nodejs', 'name' => 'Node.js', 'category' => 'technical'],
    ['code' => 'typescript', 'name' => 'TypeScript', 'category' => 'technical'],
    ['code' => 'sql', 'name' => 'SQL', 'category' => 'technical'],
    ['code' => 'python', 'name' => 'Python', 'category' => 'technical'],
    ['code' => 'pytorch', 'name' => 'PyTorch', 'category' => 'technical'],
    ['code' => 'machine_learning', 'name' => 'Machine Learning', 'category' => 'technical'],
    ['code' => 'computer_vision', 'name' => 'Computer Vision', 'category' => 'technical'],
    ['code' => 'java', 'name' => 'Java', 'category' => 'technical'],
    ['code' => 'springboot', 'name' => 'Spring Boot', 'category' => 'technical'],
    ['code' => 'docker', 'name' => 'Docker', 'category' => 'technical'],
    ['code' => 'mysql', 'name' => 'MySQL', 'category' => 'technical'],
    ['code' => 'html', 'name' => 'HTML', 'category' => 'technical'],
    ['code' => 'css', 'name' => 'CSS', 'category' => 'technical'],
    ['code' => 'javascript', 'name' => 'JavaScript', 'category' => 'technical'],
    ['code' => 'vuejs', 'name' => 'Vue.js', 'category' => 'technical'],
    ['code' => 'cyber_security', 'name' => 'An toàn thông tin', 'category' => 'technical'],
    ['code' => 'ai_ml', 'name' => 'AI / Machine Learning', 'category' => 'technical'],
    ['code' => 'rest_api', 'name' => 'REST API', 'category' => 'technical'],
    ['code' => 'git', 'name' => 'Git', 'category' => 'technical'],
];

$skillIdMap = [];
foreach ($skillsCatalog as $sk) {
    $stmt = $pdo->prepare("SELECT id FROM skills WHERE name = ? OR code = ? LIMIT 1");
    $stmt->execute([$sk['name'], $sk['code']]);
    $skId = $stmt->fetchColumn();
    if (!$skId) {
        $skId = Uuid::v4();
        $pdo->prepare("INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, 'active', ?, ?)")
            ->execute([$skId, $sk['code'], $sk['name'], $sk['category'], $now, $now]);
    } else {
        $pdo->prepare("UPDATE skills SET name = ?, code = ?, status = 'active', updatedAt = ? WHERE id = ?")
            ->execute([$sk['name'], $sk['code'], $now, $skId]);
    }
    $skillIdMap[$sk['name']] = (string)$skId;
}
echo " [OK] Skills catalog seeded with " . count($skillIdMap) . " skills.\n";

echo "=== 5. SEEDING 4 PROMINENT IT & AI STUDENTS ===\n";

$roleIdStudent = 'c8b7001c-6496-5dcf-ab0a-dd384c1ba673';
$expDate = gmdate('Y-m-d H:i:s.u', time() + 365 * 86400);

$studentsData = [
    [
        'studentId' => '22000000-53d8-4897-8d68-ab3f78db0ce9',
        'userId' => '22000000-acfb-45ba-864b-db973365e710',
        'email' => 'nguyen.van.an@student.fpt.edu.vn',
        'fullName' => 'Nguyễn Văn An',
        'schoolId' => '22000000-b512-4ede-852b-f4a508f3e837',
        'classId' => '22000000-ba56-41f9-800b-26b41b0a9b5c',
        'studyStatus' => 'active',
        'headline' => 'Fullstack Web Developer (React, Node.js)',
        'bio' => 'Sinh viên năm 4 ngành CNTT Đại học FPT với định hướng Fullstack Web Developer. Thành thạo React, Node.js, TypeScript, SQL và kiến trúc Microservices. Đã thực hiện nhiều đồ án web thực tế chuẩn Agile/Scrum.',
        'location' => 'Hà Nội',
        'skills' => [
            ['name' => 'React', 'score' => 95.00],
            ['name' => 'Node.js', 'score' => 92.00],
            ['name' => 'TypeScript', 'score' => 92.00],
            ['name' => 'SQL', 'score' => 94.00],
            ['name' => 'REST API', 'score' => 90.00],
            ['name' => 'Git', 'score' => 90.00],
        ],
        'projects' => [
            [
                'title' => 'TalentHub Enterprise - Cổng kết nối Doanh nghiệp & Nhân tài',
                'description' => 'Nền tảng Fullstack kết nối sinh viên CNTT với doanh nghiệp, tích hợp tìm kiếm real-time và quản lý thực tập.',
                'category' => 'Web Application',
            ],
            [
                'title' => 'Hệ thống Quản lý Học tập LMS Trực tuyến',
                'description' => 'Hệ thống quản trị khóa học, bài thi trắc nghiệm và chấm code tự động cho sinh viên ĐH FPT.',
                'category' => 'Fullstack Web',
            ]
        ],
        'certificates' => [
            ['title' => 'Meta Full-Stack Developer Certificate', 'org' => 'Coursera & Meta'],
            ['title' => 'AWS Certified Cloud Practitioner', 'org' => 'Amazon Web Services (AWS)'],
        ],
    ],
    [
        'studentId' => '24000000-0000-4000-8000-000000000002',
        'userId' => '24000000-0000-4000-8000-000000000012',
        'email' => 'tran.minh.duc@student.btec.fpt.edu.vn',
        'fullName' => 'Trần Minh Đức',
        'schoolId' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783',
        'classId' => 'a1e2894b-2386-5404-9695-78a78f5a60d3',
        'studyStatus' => 'active',
        'headline' => 'AI Engineer (Machine Learning & Computer Vision)',
        'bio' => 'Sinh viên BTEC FPT chuyên ngành Trí tuệ nhân tạo & Khoa học dữ liệu. Có 120 giờ thực án huấn luyện mô hình PyTorch, Computer Vision (YOLO, OpenCV) và xử lý dữ liệu lớn (Big Data).',
        'location' => 'Hà Nội',
        'skills' => [
            ['name' => 'Python', 'score' => 92.00],
            ['name' => 'PyTorch', 'score' => 88.00],
            ['name' => 'Machine Learning', 'score' => 88.00],
            ['name' => 'Computer Vision', 'score' => 86.00],
            ['name' => 'AI / Machine Learning', 'score' => 88.00],
        ],
        'projects' => [
            [
                'title' => 'Hệ thống Camera AI Giám sát An toàn Lao động',
                'description' => 'Mô hình Computer Vision nhận diện trang bị bảo hộ lao động (mũ, áo phản quang) theo thời gian thực đạt độ chính xác 94%.',
                'category' => 'AI / Computer Vision',
            ],
            [
                'title' => 'Mô hình Dự báo Doanh số Thương mại Điện tử',
                'description' => 'Ứng dụng thuật toán Machine Learning (XGBoost, Random Forest) phân tích hành vi người tiêu dùng.',
                'category' => 'Data Science',
            ]
        ],
        'certificates' => [
            ['title' => 'DeepLearning.AI Machine Learning Specialization', 'org' => 'DeepLearning.AI / Stanford Online'],
            ['title' => 'NVIDIA Deep Learning Institute - Computer Vision', 'org' => 'NVIDIA DLI'],
        ],
    ],
    [
        'studentId' => '23000000-0000-4000-8000-000000000003',
        'userId' => '23000000-0000-4000-8000-000000000013',
        'email' => 'le.hoang.nam@student.ctu.edu.vn',
        'fullName' => 'Lê Hoàng Nam',
        'schoolId' => '23000000-0000-4000-8000-000000000001',
        'classId' => '23000000-0000-4000-8000-000000000002',
        'studyStatus' => 'active',
        'headline' => 'Backend Developer (Java, Spring Boot, Docker)',
        'bio' => 'Sinh viên CNTT ĐH Cần Thơ chuyên sâu kiến trúc backend hướng dịch vụ (Microservices). Kinh nghiệm lập trình Java 17, Spring Boot 3, tối ưu hóa truy vấn MySQL và đóng gói triển khai Docker.',
        'location' => 'Cần Thơ',
        'skills' => [
            ['name' => 'Java', 'score' => 88.00],
            ['name' => 'Spring Boot', 'score' => 86.00],
            ['name' => 'Docker', 'score' => 84.00],
            ['name' => 'MySQL', 'score' => 85.00],
            ['name' => 'REST API', 'score' => 86.00],
        ],
        'projects' => [
            [
                'title' => 'Cổng Thanh toán & Xử lý Đơn hàng E-Commerce',
                'description' => 'Hệ thống Microservices xử lý 5,000 requests/giây xây dựng bằng Java Spring Boot, Redis Cache và RabbitMQ.',
                'category' => 'Backend Microservices',
            ],
            [
                'title' => 'Hệ thống Quản lý Chuỗi Cung ứng Thủy sản Đồng bằng Sông Cửu Long',
                'description' => 'Phần mềm truy xuất nguồn gốc nông sản kết nối cơ sở dữ liệu phân tán MySQL.',
                'category' => 'Enterprise Backend',
            ]
        ],
        'certificates' => [
            ['title' => 'Oracle Certified Professional: Java SE 17 Developer', 'org' => 'Oracle University'],
            ['title' => 'Docker Certified Associate', 'org' => 'Docker Inc.'],
        ],
    ],
    [
        'studentId' => 'a49dadc0-65f0-5862-a380-34c2d43ecbc6',
        'userId' => '011eaed6-65ff-5807-a5d8-2f69813693fa',
        'email' => 'vo.duc.anh@student.btec.fpt.edu.vn',
        'fullName' => 'Võ Đức Anh',
        'schoolId' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783',
        'classId' => 'bc0be670-12fd-545b-a70f-3ebf8ce3fad7',
        'studyStatus' => 'active',
        'headline' => 'Frontend Developer (Vue.js, JavaScript, HTML/CSS)',
        'bio' => 'Sinh viên BTEC FPT đam mê công nghệ Web Frontend. Thành thạo HTML5, CSS3, JavaScript ES6+ và framework Vue.js / Pinia. Chú trọng tối ưu UX/UI, trải nghiệm responsive trên đa thiết bị.',
        'location' => 'TP. Hồ Chí Minh',
        'skills' => [
            ['name' => 'HTML', 'score' => 82.00],
            ['name' => 'CSS', 'score' => 80.00],
            ['name' => 'JavaScript', 'score' => 82.00],
            ['name' => 'Vue.js', 'score' => 80.00],
            ['name' => 'REST API', 'score' => 80.00],
        ],
        'projects' => [
            [
                'title' => 'Dashboard Quản lý Nhân sự & Chấm công Doanh nghiệp',
                'description' => 'Giao diện web SPA hiện đại xây dựng bằng Vue.js 3, Vite và TailwindCSS với 30+ biểu đồ tương tác.',
                'category' => 'Frontend Web App',
            ],
            [
                'title' => 'Website Thương mại Điện tử BTEC Shop',
                'description' => 'Giao diện mua sắm trực tuyến tối ưu tốc độ tải trang Lighthouse 98/100.',
                'category' => 'Web Design & Frontend',
            ]
        ],
        'certificates' => [
            ['title' => 'Certified Frontend Web Developer with Vue.js', 'org' => 'Coursera & Vue School'],
        ],
    ]
];

foreach ($studentsData as $st) {
    // 1. User
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? OR email = ? LIMIT 1");
    $stmt->execute([$st['userId'], $st['email']]);
    $uid = $stmt->fetchColumn();
    if (!$uid) {
        $uid = $st['userId'];
        $pdo->prepare("INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, 'active', ?, ?)")
            ->execute([$uid, $roleIdStudent, $st['email'], $hash123456, $st['fullName'], $now, $now]);
    } else {
        $pdo->prepare("UPDATE users SET fullName = ?, email = ?, status = 'active', updatedAt = ? WHERE id = ?")
            ->execute([$st['fullName'], $st['email'], $now, $uid]);
        $uid = (string)$uid;
    }

    // 2. Student Profile
    $stmt = $pdo->prepare("SELECT id FROM student_profiles WHERE id = ? OR userId = ? LIMIT 1");
    $stmt->execute([$st['studentId'], $uid]);
    $spId = $stmt->fetchColumn();
    if (!$spId) {
        $spId = $st['studentId'];
        $pdo->prepare("INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus, createdAt, updatedAt) VALUES (?, ?, ?, '2004-05-15', '0908889999', ?, ?, ?)")
            ->execute([$spId, $uid, $st['classId'], $st['studyStatus'], $now, $now]);
    } else {
        $pdo->prepare("UPDATE student_profiles SET classId = ?, studyStatus = ?, updatedAt = ? WHERE id = ?")
            ->execute([$st['classId'], $st['studyStatus'], $now, $spId]);
        $spId = (string)$spId;
    }

    // 3. Student Profile Details
    $stmt = $pdo->prepare("
        INSERT INTO student_profile_details (studentId, location, bio, headline, createdAt, updatedAt)
        VALUES (:studentId, :location, :bio, :headline, :now1, :now2)
        ON DUPLICATE KEY UPDATE location = :location2, bio = :bio2, headline = :headline2, updatedAt = :now3
    ");
    $stmt->execute([
        'studentId' => $spId,
        'location' => $st['location'],
        'bio' => $st['bio'],
        'headline' => $st['headline'],
        'location2' => $st['location'],
        'bio2' => $st['bio'],
        'headline2' => $st['headline'],
        'now1' => $now,
        'now2' => $now,
        'now3' => $now,
    ]);

    // 4. Student Skills
    foreach ($st['skills'] as $sk) {
        $skId = $skillIdMap[$sk['name']] ?? null;
        if ($skId) {
            $stmt = $pdo->prepare("SELECT id FROM student_skills WHERE studentId = ? AND skillId = ? LIMIT 1");
            $stmt->execute([$spId, $skId]);
            if (!$stmt->fetchColumn()) {
                $ssId = Uuid::v4();
                $pdo->prepare("INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt) VALUES (?, ?, ?, ?, 'teacher', 'verified', ?, ?, ?)")
                    ->execute([$ssId, $spId, $skId, $sk['score'], $now, $now, $now]);
            } else {
                $pdo->prepare("UPDATE student_skills SET levelScore = ?, sourceType = 'teacher', verificationStatus = 'verified', verifiedAt = ?, updatedAt = ? WHERE studentId = ? AND skillId = ?")
                    ->execute([$sk['score'], $now, $now, $spId, $skId]);
            }
        }
    }

    // 5. Consents (isGranted = 1, scope = 'enterprise_talent_discovery' & 'enterprise_talent_contact')
    $scopes = ['enterprise_talent_discovery', 'enterprise_talent_contact'];
    $consentIds = [];
    foreach ($scopes as $scope) {
        $stmt = $pdo->prepare("SELECT id FROM privacy_consents WHERE studentId = ? AND scope = ? LIMIT 1");
        $stmt->execute([$spId, $scope]);
        $cId = $stmt->fetchColumn();
        if (!$cId) {
            $cId = Uuid::v4();
            $pdo->prepare("INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, createdAt) VALUES (?, ?, ?, 1, '1.0', ?, ?)")
                ->execute([$cId, $spId, $scope, $now, $now]);
        } else {
            $pdo->prepare("UPDATE privacy_consents SET isGranted = 1, revokedAt = NULL, grantedAt = ? WHERE id = ?")
                ->execute([$now, $cId]);
            $cId = (string)$cId;
        }
        $consentIds[$scope] = $cId;
    }

    // 6. Enterprise Talent Access Grants (For FPT Software)
    foreach ([$fptEnterpriseId, $altEnterpriseId] as $entId) {
        foreach ($scopes as $scope) {
            $cId = $consentIds[$scope];
            $stmt = $pdo->prepare("SELECT id FROM enterprise_talent_access_grants WHERE studentId = ? AND enterpriseId = ? AND scope = ? LIMIT 1");
            $stmt->execute([$spId, $entId, $scope]);
            $gId = $stmt->fetchColumn();
            if (!$gId) {
                $gId = Uuid::v4();
                $pdo->prepare("INSERT INTO enterprise_talent_access_grants (id, studentId, enterpriseId, consentId, scope, grantedAt, expiresAt, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$gId, $spId, $entId, $cId, $scope, $now, $expDate, $now, $now]);
            } else {
                $pdo->prepare("UPDATE enterprise_talent_access_grants SET revokedAt = NULL, expiresAt = ?, updatedAt = ? WHERE id = ?")
                    ->execute([$expDate, $now, $gId]);
            }
        }
    }

    // 7. Projects & Certificates
    foreach ($st['projects'] as $proj) {
        $stmt = $pdo->prepare("SELECT id FROM projects WHERE schoolId = ? AND title = ? LIMIT 1");
        $stmt->execute([$st['schoolId'], $proj['title']]);
        $pId = $stmt->fetchColumn();
        if (!$pId) {
            $pId = Uuid::v4();
            $pdo->prepare("INSERT INTO projects (id, schoolId, title, category, description, fundingGoal, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, 10000000.00, 'in_progress', ?, ?)")
                ->execute([$pId, $st['schoolId'], $proj['title'], $proj['category'], $proj['description'], $now, $now]);
        }
    }

    foreach ($st['certificates'] as $cert) {
        $stmt = $pdo->prepare("SELECT id FROM certificates WHERE studentId = ? AND title = ? LIMIT 1");
        $stmt->execute([$spId, $cert['title']]);
        if (!$stmt->fetchColumn()) {
            $certId = Uuid::v4();
            $pdo->prepare("INSERT INTO certificates (id, studentId, title, issuingOrganization, issueDate, verificationStatus, createdAt, updatedAt) VALUES (?, ?, ?, ?, '2025-12-01', 'verified', ?, ?)")
                ->execute([$certId, $spId, $cert['title'], $cert['org'], $now, $now]);
        }
    }

    echo " [OK] Candidate {$st['fullName']} ({$spId}) seeded & granted full access for FPT Software.\n";
}

echo "\n=== SEEDING COMPLETED SUCCESSFULLY! ===\n";
