<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "========================================================\n";
echo "   SEEDING & CONFIGURING MB BANK ENTERPRISE ECOSYSTEM\n";
echo "========================================================\n\n";

$pdo->beginTransaction();

try {
    $now = date('Y-m-d H:i:s');
    $enterpriseRoleId = '8dcbaaac-be69-5d75-92e0-cdd0289642e3';
    $studentRoleId = 'c8b7001c-6496-5dcf-ab0a-dd384c1ba673';
    $teacherRoleId = 'bf777e38-4e3f-58aa-b93b-0145c1103de9';
    $passwordHash = '$2y$10$F64ESoYD/SgJV3Oyjsv.kO9dySgc7wt0UgSPweEhu2cjcW.idEGcy'; // 123456

    // --------------------------------------------------------------------------
    // 1. Ensure Skills Exist for Economic / Marketing / BI
    // --------------------------------------------------------------------------
    echo "1. Registering Economic, Marketing & Analytics Skills...\n";
    $skills = [
        ['id' => '80000000-0000-4000-8000-000000000001', 'code' => 'powerbi', 'name' => 'PowerBI', 'category' => 'technical'],
        ['id' => '80000000-0000-4000-8000-000000000002', 'code' => 'toeic_850', 'name' => 'Tiếng Anh TOEIC 850', 'category' => 'academic'],
        ['id' => '80000000-0000-4000-8000-000000000003', 'code' => 'digital_marketing', 'name' => 'Digital Marketing', 'category' => 'business'],
        ['id' => '80000000-0000-4000-8000-000000000004', 'code' => 'content_creator', 'name' => 'Content Creator', 'category' => 'creative'],
        ['id' => '80000000-0000-4000-8000-000000000005', 'code' => 'seo', 'name' => 'SEO', 'category' => 'business'],
        ['id' => '80000000-0000-4000-8000-000000000006', 'code' => 'google_analytics', 'name' => 'Google Analytics', 'category' => 'technical'],
        ['id' => '80000000-0000-4000-8000-000000000007', 'code' => 'video_editing', 'name' => 'Video Editing', 'category' => 'creative'],
        ['id' => '80000000-0000-4000-8000-000000000008', 'code' => 'excel_advanced', 'name' => 'Excel nâng cao', 'category' => 'technical'],
    ];

    $skillStmt = $pdo->prepare("
        INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt)
        VALUES (:id, :code, :name, :category, 'active', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), status = 'active', updatedAt = NOW(6)
    ");
    foreach ($skills as $sk) {
        $skillStmt->execute($sk);
    }

    // --------------------------------------------------------------------------
    // 2. Ensure Classes for CTU & FPTU Exist
    // --------------------------------------------------------------------------
    echo "2. Ensuring Major Classes (CTU & FPTU)...\n";
    $classes = [
        [
            'id' => '23000000-0000-4000-8000-000000000003',
            'schoolId' => '23000000-0000-4000-8000-000000000001', // ĐH Cần Thơ
            'name' => 'K47 Kinh doanh Quốc tế (Năm 4)',
            'gradeLevel' => 4,
            'academicYear' => '2022-2026'
        ],
        [
            'id' => '22000000-ba56-41f9-800b-26b41b0a9b5d',
            'schoolId' => '22000000-b512-4ede-852b-f4a508f3e837', // ĐH FPT
            'name' => 'K18 Digital Marketing (Năm 4)',
            'gradeLevel' => 4,
            'academicYear' => '2022-2026'
        ]
    ];

    $classStmt = $pdo->prepare("
        INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt)
        VALUES (:id, :schoolId, :name, :gradeLevel, :academicYear, 'active', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE name = VALUES(name), gradeLevel = VALUES(gradeLevel), academicYear = VALUES(academicYear), status = 'active', updatedAt = NOW(6)
    ");
    foreach ($classes as $c) {
        $classStmt->execute($c);
    }

    // --------------------------------------------------------------------------
    // 3. Create / Update MB Bank Enterprise & User Accounts
    // --------------------------------------------------------------------------
    echo "3. Seeding MB Bank Enterprise & Account Profiles...\n";
    $mbEnterpriseId = '32000000-0000-4000-8000-000000000020';

    $entStmt = $pdo->prepare("
        INSERT INTO enterprises (
            id, name, status, logoUrl, industry, companySize, foundedYear,
            description, email, phone, website, taxCode, address, verificationStatus, createdAt, updatedAt
        ) VALUES (
            :id, :name, :status, :logoUrl, :industry, :companySize, :foundedYear,
            :description, :email, :phone, :website, :taxCode, :address, :verificationStatus, NOW(6), NOW(6)
        ) ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            status = VALUES(status),
            logoUrl = VALUES(logoUrl),
            industry = VALUES(industry),
            companySize = VALUES(companySize),
            foundedYear = VALUES(foundedYear),
            description = VALUES(description),
            email = VALUES(email),
            phone = VALUES(phone),
            website = VALUES(website),
            taxCode = VALUES(taxCode),
            address = VALUES(address),
            verificationStatus = VALUES(verificationStatus),
            updatedAt = NOW(6)
    ");

    $entStmt->execute([
        'id' => $mbEnterpriseId,
        'name' => 'Ngân hàng TMCP Quân đội (MB Bank)',
        'status' => 'active',
        'logoUrl' => '/assets/images/mbbank-logo.svg',
        'industry' => 'Tài chính, Ngân hàng số & Fintech',
        'companySize' => '15,000+ nhân viên',
        'foundedYear' => 1994,
        'description' => 'Ngân hàng TMCP Quân đội (MB Bank) là một trong những định chế tài chính - ngân hàng số hàng đầu Việt Nam, tiên phong trong ứng dụng công nghệ Fintech, phục vụ hơn 25 triệu khách hàng và thúc đẩy phát triển kinh tế số.',
        'email' => 'mbbank@talenthub.local',
        'phone' => '1900 545426',
        'website' => 'https://mbbank.com.vn',
        'taxCode' => '0100283873',
        'address' => 'Tòa nhà MB Grand Tower, Hà Nội & Chi nhánh Cần Thơ / TP.HCM',
        'verificationStatus' => 'verified'
    ]);

    // Seed MB Bank Users (Primary & Aliases)
    $mbUsers = [
        [
            'id' => '31000000-0000-4000-8000-000000000020',
            'email' => 'mbbank@talenthub.local',
            'fullName' => 'Ban Tuyển Dụng MB Bank'
        ],
        [
            'id' => '31000000-0000-4000-8000-000000000021',
            'email' => 'biz@talenthub.local',
            'fullName' => 'Ban Tuyển Dụng MB Bank'
        ],
        [
            'id' => '31000000-0000-4000-8000-000000000022',
            'email' => 'mbbank.careers@talenthub.local',
            'fullName' => 'Ban Tuyển Dụng MB Bank'
        ]
    ];

    $userStmt = $pdo->prepare("
        INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt)
        VALUES (:id, :roleId, :email, :passwordHash, :fullName, 'active', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            roleId = VALUES(roleId),
            passwordHash = VALUES(passwordHash),
            fullName = VALUES(fullName),
            status = 'active',
            updatedAt = NOW(6)
    ");

    $memStmt = $pdo->prepare("
        INSERT INTO enterprise_members (id, enterpriseId, userId, memberRole, createdAt, updatedAt)
        VALUES (:id, :enterpriseId, :userId, 'admin', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            enterpriseId = VALUES(enterpriseId),
            memberRole = 'admin',
            updatedAt = NOW(6)
    ");

    foreach ($mbUsers as $u) {
        $userStmt->execute([
            'id' => $u['id'],
            'roleId' => $enterpriseRoleId,
            'email' => $u['email'],
            'passwordHash' => $passwordHash,
            'fullName' => $u['fullName']
        ]);

        $memId = '33000000-' . substr($u['id'], 9);
        $memStmt->execute([
            'id' => $memId,
            'enterpriseId' => $mbEnterpriseId,
            'userId' => $u['id']
        ]);
    }

    // --------------------------------------------------------------------------
    // 4. Seed School Partnerships for MB Bank
    // --------------------------------------------------------------------------
    echo "4. Establishing School Partnerships with MB Bank...\n";
    $partnerships = [
        [
            'id' => '52000000-0000-4000-8000-000000000021',
            'schoolId' => '23000000-0000-4000-8000-000000000001', // Đại học Cần Thơ
            'enterpriseId' => $mbEnterpriseId
        ],
        [
            'id' => '52000000-0000-4000-8000-000000000022',
            'schoolId' => '22000000-b512-4ede-852b-f4a508f3e837', // Đại học FPT
            'enterpriseId' => $mbEnterpriseId
        ],
        [
            'id' => '52000000-0000-4000-8000-000000000023',
            'schoolId' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783', // BTEC FPT
            'enterpriseId' => $mbEnterpriseId
        ],
        [
            'id' => '52000000-0000-4000-8000-000000000024',
            'schoolId' => '20000000-0000-4000-8000-000000000001', // THPT Nguyễn Trãi
            'enterpriseId' => $mbEnterpriseId
        ]
    ];

    $partStmt = $pdo->prepare("
        INSERT INTO school_enterprise_partnerships (
            id, schoolId, enterpriseId, status, requestedByUserId, reviewedByUserId, reviewedAt, createdAt, updatedAt
        ) VALUES (
            :id, :schoolId, :enterpriseId, 'approved', '31000000-0000-4000-8000-000000000020', '10000000-0000-4000-8000-000000000013', NOW(6), NOW(6), NOW(6)
        ) ON DUPLICATE KEY UPDATE
            status = 'approved',
            updatedAt = NOW(6)
    ");
    foreach ($partnerships as $p) {
        $partStmt->execute($p);
    }

    // --------------------------------------------------------------------------
    // 5. Seed MB Bank 2 Internship Posts
    // --------------------------------------------------------------------------
    echo "5. Seeding MB Bank 2 Standard Internship Posts...\n";
    $posts = [
        [
            'id' => '40000000-0000-4000-8000-000000000021',
            'enterpriseId' => $mbEnterpriseId,
            'title' => 'Thực tập sinh Phân tích Dữ liệu Kinh doanh (Business Intelligence Intern)',
            'field' => 'Phân tích dữ liệu & Kinh tế số',
            'status' => 'active',
            'audience' => 'public',
            'location' => 'Cần Thơ / TP.HCM / Hybrid',
            'workType' => 'hybrid',
            'duration' => '3 - 6 tháng',
            'educationLevel' => 'Đại học / Cao đẳng năm 3 - 4',
            'description' => 'Tham gia phân tích hành vi người dùng trên App MBBank, xây dựng báo cáo phân tích dữ liệu kinh doanh và trực quan hóa Dashboard chỉ số KPI bằng PowerBI/SQL.',
            'benefits' => 'Phụ cấp thực tập từ 8.000.000 - 12.000.000 VNĐ/tháng, cơ hội chuyển chính thức Chuyên viên Phân tích Dữ liệu tại MB Bank sau kỳ thực tập.',
            'skillsJson' => json_encode(['SQL', 'PowerBI', 'Excel nâng cao', 'Phân tích dữ liệu'], JSON_UNESCAPED_UNICODE),
            'requirementsJson' => json_encode([
                'SQL, Excel nâng cao, PowerBI, Tư duy phân tích số liệu',
                'Tư duy phân tích số liệu nhạy bén và đam mê ngành tài chính - ngân hàng số',
                'Kỹ năng làm việc nhóm, giao tiếp và thuyết trình báo cáo dữ liệu tốt'
            ], JSON_UNESCAPED_UNICODE),
            'slots' => 5,
            'deadline' => '2026-12-31 23:59:59'
        ],
        [
            'id' => '40000000-0000-4000-8000-000000000022',
            'enterpriseId' => $mbEnterpriseId,
            'title' => 'Thực tập sinh Digital Marketing & Truyền thông Thương hiệu',
            'field' => 'Marketing & Truyền thông số',
            'status' => 'active',
            'audience' => 'public',
            'location' => 'TP.HCM / Cần Thơ',
            'workType' => 'full_time',
            'duration' => '3 - 6 tháng',
            'educationLevel' => 'Đại học / Cao đẳng',
            'description' => 'Tham gia sáng tạo nội dung truyền thông cho các chiến dịch quảng bá sản phẩm thẻ, ngân hàng số MBBank; phối hợp vận hành quảng cáo đa kênh và quản trị cộng đồng người dùng.',
            'benefits' => 'Phụ cấp thực tập từ 7.000.000 - 10.000.000 VNĐ/tháng, đào tạo kỹ năng Digital Marketing thực chiến cùng chuyên gia MB Bank.',
            'skillsJson' => json_encode(['Digital Marketing', 'Content Creator', 'SEO', 'Google Analytics', 'Video Editing'], JSON_UNESCAPED_UNICODE),
            'requirementsJson' => json_encode([
                'Sáng tạo nội dung, Chạy ads đa kênh, Kỹ năng làm việc nhóm',
                'Khả năng viết content sáng tạo và nắm bắt xu hướng Gen Z trên TikTok/Facebook/YouTube',
                'Chủ động, cầu tiến, có tinh thần trách nhiệm cao trong công việc'
            ], JSON_UNESCAPED_UNICODE),
            'slots' => 4,
            'deadline' => '2026-12-31 23:59:59'
        ]
    ];

    $postStmt = $pdo->prepare("
        INSERT INTO internship_posts (
            id, enterpriseId, title, field, status, audience, location, workType, duration,
            educationLevel, description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt
        ) VALUES (
            :id, :enterpriseId, :title, :field, :status, :audience, :location, :workType, :duration,
            :educationLevel, :description, :benefits, :skillsJson, :requirementsJson, :slots, :deadline, NOW(6), NOW(6)
        ) ON DUPLICATE KEY UPDATE
            enterpriseId = VALUES(enterpriseId),
            title = VALUES(title),
            field = VALUES(field),
            status = VALUES(status),
            audience = VALUES(audience),
            location = VALUES(location),
            workType = VALUES(workType),
            duration = VALUES(duration),
            educationLevel = VALUES(educationLevel),
            description = VALUES(description),
            benefits = VALUES(benefits),
            skillsJson = VALUES(skillsJson),
            requirementsJson = VALUES(requirementsJson),
            slots = VALUES(slots),
            deadline = VALUES(deadline),
            updatedAt = NOW(6)
    ");
    foreach ($posts as $pst) {
        $postStmt->execute($pst);
    }

    // --------------------------------------------------------------------------
    // 6. Seed 2 Featured Economic & Marketing Students
    // --------------------------------------------------------------------------
    echo "6. Seeding 2 Featured Students (Hoàng Thị Mai Linh & Phạm Quốc Bảo)...\n";

    // Student 1: Hoàng Thị Mai Linh
    $linhUserId = '23000000-0000-4000-8000-000000000008';
    $linhStudentId = '23100000-0000-4000-8000-000000000008';
    $linhClassId = '23000000-0000-4000-8000-000000000003'; // CTU K47 Kinh doanh Quốc tế

    $userStmt->execute([
        'id' => $linhUserId,
        'roleId' => $studentRoleId,
        'email' => 'hoang.mai.linh@student.ctu.edu.vn',
        'passwordHash' => $passwordHash,
        'fullName' => 'Hoàng Thị Mai Linh'
    ]);

    $spStmt = $pdo->prepare("
        INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus, createdAt, updatedAt)
        VALUES (:id, :userId, :classId, :dateOfBirth, :phone, 'active', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            classId = VALUES(classId),
            dateOfBirth = VALUES(dateOfBirth),
            phone = VALUES(phone),
            studyStatus = 'active',
            updatedAt = NOW(6)
    ");
    $spStmt->execute([
        'id' => $linhStudentId,
        'userId' => $linhUserId,
        'classId' => $linhClassId,
        'dateOfBirth' => '2004-05-18',
        'phone' => '0912345890'
    ]);

    $spdStmt = $pdo->prepare("
        INSERT INTO student_profile_details (studentId, location, bio, avatarUrl, headline, createdAt, updatedAt)
        VALUES (:studentId, :location, :bio, :avatarUrl, :headline, NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            location = VALUES(location),
            bio = VALUES(bio),
            avatarUrl = VALUES(avatarUrl),
            headline = VALUES(headline),
            updatedAt = NOW(6)
    ");
    $spdStmt->execute([
        'studentId' => $linhStudentId,
        'location' => 'Cần Thơ',
        'bio' => 'Sinh viên năm cuối ngành Kinh doanh Quốc tế ĐH Cần Thơ. Đạt chứng chỉ Tiếng Anh TOEIC 850, thành thạo PowerBI, SQL và phân tích dữ liệu thị trường kinh tế số.',
        'avatarUrl' => '/assets/images/avatars/student-female-1.png',
        'headline' => 'Chuyên viên Phân tích Kinh doanh tương lai | Kinh doanh Quốc tế ĐH Cần Thơ'
    ]);

    // Student 2: Phạm Quốc Bảo
    $baoUserId = '22000000-0000-4000-8000-000000000009';
    $baoStudentId = '22100000-0000-4000-8000-000000000009';
    $baoClassId = '22000000-ba56-41f9-800b-26b41b0a9b5d'; // FPTU K18 Digital Marketing

    $userStmt->execute([
        'id' => $baoUserId,
        'roleId' => $studentRoleId,
        'email' => 'pham.quoc.bao@student.fpt.edu.vn',
        'passwordHash' => $passwordHash,
        'fullName' => 'Phạm Quốc Bảo'
    ]);

    $spStmt->execute([
        'id' => $baoStudentId,
        'userId' => $baoUserId,
        'classId' => $baoClassId,
        'dateOfBirth' => '2004-09-12',
        'phone' => '0934567891'
    ]);

    $spdStmt->execute([
        'studentId' => $baoStudentId,
        'location' => 'TP. Hồ Chí Minh / Cần Thơ',
        'bio' => 'Sinh viên ngành Digital Marketing ĐH FPT với kinh nghiệm sáng tạo nội dung đa nền tảng, tối ưu hóa công cụ tìm kiếm (SEO), vận hành quảng cáo và phân tích Google Analytics.',
        'avatarUrl' => '/assets/images/avatars/student-male-1.png',
        'headline' => 'Digital Marketer & Content Creator | Chuyên ngành Digital Marketing ĐH FPT'
    ]);

    // --------------------------------------------------------------------------
    // 7. Seed Student Skills & Verified Status
    // --------------------------------------------------------------------------
    echo "7. Seeding Verified Skills for Linh & Bảo...\n";
    $studentSkills = [
        // Mai Linh
        ['id' => '81000000-0000-4000-8000-000000000001', 'studentId' => $linhStudentId, 'skillId' => '22000000-ab76-46d3-8833-5809a8cc24a3', 'score' => 92.0], // Phân tích dữ liệu
        ['id' => '81000000-0000-4000-8000-000000000002', 'studentId' => $linhStudentId, 'skillId' => '80000000-0000-4000-8000-000000000001', 'score' => 90.0], // PowerBI
        ['id' => '81000000-0000-4000-8000-000000000003', 'studentId' => $linhStudentId, 'skillId' => 'fccb1a25-c75d-403c-8a0e-859354ca517f', 'score' => 88.0], // SQL
        ['id' => '81000000-0000-4000-8000-000000000004', 'studentId' => $linhStudentId, 'skillId' => '80000000-0000-4000-8000-000000000002', 'score' => 95.0], // Tiếng Anh TOEIC 850
        ['id' => '81000000-0000-4000-8000-000000000005', 'studentId' => $linhStudentId, 'skillId' => '80000000-0000-4000-8000-000000000008', 'score' => 90.0], // Excel nâng cao

        // Quốc Bảo
        ['id' => '81000000-0000-4000-8000-000000000011', 'studentId' => $baoStudentId, 'skillId' => '80000000-0000-4000-8000-000000000004', 'score' => 90.0], // Content Creator
        ['id' => '81000000-0000-4000-8000-000000000012', 'studentId' => $baoStudentId, 'skillId' => '80000000-0000-4000-8000-000000000005', 'score' => 88.0], // SEO
        ['id' => '81000000-0000-4000-8000-000000000013', 'studentId' => $baoStudentId, 'skillId' => '80000000-0000-4000-8000-000000000006', 'score' => 85.0], // Google Analytics
        ['id' => '81000000-0000-4000-8000-000000000014', 'studentId' => $baoStudentId, 'skillId' => '80000000-0000-4000-8000-000000000007', 'score' => 86.0], // Video Editing
        ['id' => '81000000-0000-4000-8000-000000000015', 'studentId' => $baoStudentId, 'skillId' => '80000000-0000-4000-8000-000000000003', 'score' => 88.0], // Digital Marketing
    ];

    $ssStmt = $pdo->prepare("
        INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt)
        VALUES (:id, :studentId, :skillId, :score, 'assessment', 'verified', NOW(6), NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            levelScore = VALUES(levelScore),
            sourceType = 'assessment',
            verificationStatus = 'verified',
            verifiedAt = NOW(6),
            updatedAt = NOW(6)
    ");
    foreach ($studentSkills as $ssk) {
        $ssStmt->execute([
            'id' => $ssk['id'],
            'studentId' => $ssk['studentId'],
            'skillId' => $ssk['skillId'],
            'score' => $ssk['score']
        ]);
    }

    // --------------------------------------------------------------------------
    // 8. Seed Activities, Registrations & Assessments (Score 90 for Linh, 86 for Bảo)
    // --------------------------------------------------------------------------
    echo "8. Seeding Activities, Registrations & Assessments (Linh: 90/100, Bảo: 86/100)...\n";
    $teacherProfileId = '20000000-0000-4000-8000-000000000050';
    $activities = [
        [
            'id' => '83000000-0000-4000-8000-000000000001',
            'schoolId' => '23000000-0000-4000-8000-000000000001', // CTU
            'createdByTeacherId' => $teacherProfileId,
            'title' => 'Cuộc thi Phân tích Dữ liệu Kinh doanh & Tài chính số CTU 2026',
            'category' => 'academic',
            'startAt' => '2026-08-01 08:00:00.000000',
            'endAt' => '2026-08-20 17:00:00.000000',
            'capacity' => 100,
            'status' => 'published',
            'visibility' => 'public'
        ],
        [
            'id' => '83000000-0000-4000-8000-000000000002',
            'schoolId' => '22000000-b512-4ede-852b-f4a508f3e837', // FPTU
            'createdByTeacherId' => $teacherProfileId,
            'title' => 'FPTU Digital Marketing & Brand Innovation Challenge 2026',
            'category' => 'business',
            'startAt' => '2026-08-01 08:00:00.000000',
            'endAt' => '2026-08-20 17:00:00.000000',
            'capacity' => 120,
            'status' => 'published',
            'visibility' => 'public'
        ]
    ];

    $actStmt = $pdo->prepare("
        INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status, visibility, createdAt, updatedAt)
        VALUES (:id, :schoolId, :createdByTeacherId, :title, :category, :startAt, :endAt, :capacity, :status, :visibility, NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            category = VALUES(category),
            status = VALUES(status),
            visibility = VALUES(visibility),
            updatedAt = NOW(6)
    ");
    foreach ($activities as $act) {
        $actStmt->execute($act);
    }

    $registrations = [
        [
            'id' => '84000000-0000-4000-8000-000000000001',
            'activityId' => '83000000-0000-4000-8000-000000000001',
            'studentId' => $linhStudentId,
            'status' => 'approved'
        ],
        [
            'id' => '84000000-0000-4000-8000-000000000002',
            'activityId' => '83000000-0000-4000-8000-000000000002',
            'studentId' => $baoStudentId,
            'status' => 'approved'
        ]
    ];

    $regStmt = $pdo->prepare("
        INSERT INTO activity_registrations (id, activityId, studentId, status, registeredAt, updatedAt)
        VALUES (:id, :activityId, :studentId, :status, NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            updatedAt = NOW(6)
    ");
    foreach ($registrations as $r) {
        $regStmt->execute($r);
    }

    $assessments = [
        [
            'id' => '82000000-0000-4000-8000-000000000001',
            'studentId' => $linhStudentId,
            'teacherId' => $teacherProfileId,
            'activityId' => '83000000-0000-4000-8000-000000000001',
            'overallScore' => 9.0, // Multiplied by 10 => 90 in EnterpriseTalentRepository
            'comment' => 'Sinh viên có tư duy phân tích định lượng xuất sắc, năng lực tiếng Anh TOEIC 850 và kỹ năng thiết kế báo cáo PowerBI vượt trội.'
        ],
        [
            'id' => '82000000-0000-4000-8000-000000000002',
            'studentId' => $baoStudentId,
            'teacherId' => $teacherProfileId,
            'activityId' => '83000000-0000-4000-8000-000000000002',
            'overallScore' => 8.6, // Multiplied by 10 => 86 in EnterpriseTalentRepository
            'comment' => 'Năng động, nhạy bén với xu hướng truyền thông mạng xã hội, có tư duy sáng tạo nội dung và kỹ năng SEO, Google Analytics tốt.'
        ]
    ];

    $asStmt = $pdo->prepare("
        INSERT INTO assessments (id, studentId, teacherId, activityId, overallScore, comment, status, publishedAt, version, createdAt, updatedAt)
        VALUES (:id, :studentId, :teacherId, :activityId, :overallScore, :comment, 'published', NOW(6), 1, NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            activityId = VALUES(activityId),
            overallScore = VALUES(overallScore),
            comment = VALUES(comment),
            status = 'published',
            publishedAt = NOW(6),
            updatedAt = NOW(6)
    ");
    foreach ($assessments as $a) {
        $asStmt->execute($a);
    }

    $pdo->commit();
    echo "\n>>> MB BANK SEEDING COMPLETED SUCCESSFULLY! <<<\n\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "ERROR during seeding: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
