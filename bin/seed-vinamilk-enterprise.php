<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "====================================================================\n";
echo "   SEEDING & CONFIGURING VINAMILK ENTERPRISE ECOSYSTEM (FMCG / BIZ)\n";
echo "====================================================================\n\n";

$pdo->beginTransaction();

try {
    $now = date('Y-m-d H:i:s');
    $enterpriseRoleId = '8dcbaaac-be69-5d75-92e0-cdd0289642e3';
    $studentRoleId = 'c8b7001c-6496-5dcf-ab0a-dd384c1ba673';
    $passwordHash = password_hash('123456', PASSWORD_DEFAULT);

    // --------------------------------------------------------------------------
    // 1. Ensure Economic / Marketing / Logistics / Finance Skills Exist
    // --------------------------------------------------------------------------
    echo "1. Registering Economic, Marketing, Logistics & Finance Skills...\n";
    $skills = [
        ['id' => '80000000-0000-4000-8000-000000000001', 'code' => 'powerbi', 'name' => 'PowerBI', 'category' => 'technical'],
        ['id' => '80000000-0000-4000-8000-000000000002', 'code' => 'toeic_850', 'name' => 'Tiếng Anh TOEIC 850', 'category' => 'academic'],
        ['id' => '80000000-0000-4000-8000-000000000003', 'code' => 'digital_marketing', 'name' => 'Digital Marketing', 'category' => 'business'],
        ['id' => '80000000-0000-4000-8000-000000000004', 'code' => 'content_creator', 'name' => 'Sáng tạo nội dung', 'category' => 'creative'],
        ['id' => '80000000-0000-4000-8000-000000000008', 'code' => 'excel_advanced', 'name' => 'Excel nâng cao', 'category' => 'technical'],
        ['id' => '80000000-0000-4000-8000-000000000031', 'code' => 'market_analysis', 'name' => 'Phân tích thị trường', 'category' => 'business'],
        ['id' => '80000000-0000-4000-8000-000000000032', 'code' => 'toeic_800', 'name' => 'Tiếng Anh TOEIC 800', 'category' => 'academic'],
        ['id' => '80000000-0000-4000-8000-000000000033', 'code' => 'presentation_skills', 'name' => 'Kỹ năng thuyết trình', 'category' => 'soft_skill'],
        ['id' => '80000000-0000-4000-8000-000000000034', 'code' => 'warehouse_mgmt', 'name' => 'Quản lý kho vận', 'category' => 'business'],
        ['id' => '80000000-0000-4000-8000-000000000035', 'code' => 'order_opt', 'name' => 'Tối ưu hóa đơn hàng', 'category' => 'business'],
        ['id' => '80000000-0000-4000-8000-000000000036', 'code' => 'ops_analytics', 'name' => 'Phân tích dữ liệu vận hành', 'category' => 'technical'],
        ['id' => '80000000-0000-4000-8000-000000000037', 'code' => 'financial_reporting', 'name' => 'Lập báo cáo tài chính', 'category' => 'business'],
        ['id' => '80000000-0000-4000-8000-000000000038', 'code' => 'cost_accounting', 'name' => 'Kế toán chi phí', 'category' => 'business'],
        ['id' => '80000000-0000-4000-8000-000000000039', 'code' => 'brand_management', 'name' => 'Quản trị thương hiệu', 'category' => 'business'],
    ];

    $skillStmt = $pdo->prepare("
        INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt)
        VALUES (:id, :code, :name, :category, 'active', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), status = 'active', updatedAt = NOW(6)
    ");
    foreach ($skills as $sk) {
        $skillStmt->execute($sk);
    }
    echo "  [OK] Skills registered.\n";

    // --------------------------------------------------------------------------
    // 2. Ensure Class for CTU (Đại học Cần Thơ - Khoa Kinh tế / QTKD)
    // --------------------------------------------------------------------------
    echo "2. Ensuring Major Classes (CTU)...\n";
    $ctuSchoolId = '23000000-0000-4000-8000-000000000001';
    $ctuClassId = '23000000-0000-4000-8000-000000000004';

    $classStmt = $pdo->prepare("
        INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt)
        VALUES (:id, :schoolId, :name, :gradeLevel, :academicYear, 'active', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE name = VALUES(name), gradeLevel = VALUES(gradeLevel), academicYear = VALUES(academicYear), status = 'active', updatedAt = NOW(6)
    ");
    $classStmt->execute([
        'id' => $ctuClassId,
        'schoolId' => $ctuSchoolId,
        'name' => 'K47 Quản trị Kinh doanh (Năm 4)',
        'gradeLevel' => 4,
        'academicYear' => '2022-2026'
    ]);
    echo "  [OK] CTU Business Administration class verified.\n";

    // --------------------------------------------------------------------------
    // 3. Create / Update Vinamilk Enterprise & User Accounts
    // --------------------------------------------------------------------------
    echo "3. Seeding Vinamilk Enterprise & Account Profiles...\n";
    $vinamilkEnterpriseId = '32000000-0000-4000-8000-000000000003';

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
        'id' => $vinamilkEnterpriseId,
        'name' => 'Công ty Cổ phần Sữa Việt Nam (Vinamilk)',
        'status' => 'active',
        'logoUrl' => '/assets/images/vinamilk-logo.svg',
        'industry' => 'Hàng tiêu dùng nhanh (FMCG), Kinh tế & Quản trị Chuỗi cung ứng',
        'companySize' => '10,000+ nhân viên',
        'foundedYear' => 1976,
        'description' => 'Công ty Cổ phần Sữa Việt Nam (Vinamilk) là doanh nghiệp dinh dưỡng hàng đầu Việt Nam và thuộc Top 40 công ty sữa lớn nhất thế giới, tiên phong đổi mới sáng tạo, phát triển bền vững và đào tạo thế hệ tài năng kinh tế, marketing và chuỗi cung ứng.',
        'email' => 'vinamilk@talenthub.local',
        'phone' => '028 5415 5555',
        'website' => 'https://www.vinamilk.com.vn',
        'taxCode' => '0300588569',
        'address' => 'Số 10 Tân Trào, Phường Tân Phú, Quận 7, TP. Hồ Chí Minh',
        'verificationStatus' => 'verified'
    ]);

    // Seed Vinamilk Users (Primary: vinamilk@talenthub.local & biz@talenthub.local & careers)
    $vinamilkUsers = [
        [
            'id' => '31000000-0000-4000-8000-000000000013',
            'email' => 'vinamilk@talenthub.local',
            'fullName' => 'Công ty Cổ phần Sữa Việt Nam (Vinamilk)'
        ],
        [
            'id' => '31000000-0000-4000-8000-000000000021',
            'email' => 'biz@talenthub.local',
            'fullName' => 'Công ty Cổ phần Sữa Việt Nam (Vinamilk)'
        ],
        [
            'id' => '31000000-0000-4000-8000-000000000023',
            'email' => 'vinamilk.careers@talenthub.local',
            'fullName' => 'Ban Tuyển Dụng Vinamilk'
        ]
    ];

    $userStmt = $pdo->prepare("
        INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt)
        VALUES (:id, :roleId, :email, :passwordHash, :fullName, 'active', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            roleId = VALUES(roleId),
            passwordHash = VALUES(passwordHash),
            fullName = VALUES(fullName),
            email = VALUES(email),
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

    foreach ($vinamilkUsers as $u) {
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
            'enterpriseId' => $vinamilkEnterpriseId,
            'userId' => $u['id']
        ]);
    }
    echo "  [OK] Vinamilk Enterprise accounts seeded.\n";

    // --------------------------------------------------------------------------
    // 4. Seed School Partnerships for Vinamilk
    // --------------------------------------------------------------------------
    echo "4. Establishing School Partnerships with Vinamilk...\n";
    $partnerships = [
        [
            'id' => '52000000-0000-4000-8000-000000000031',
            'schoolId' => '23000000-0000-4000-8000-000000000001', // Đại học Cần Thơ
            'enterpriseId' => $vinamilkEnterpriseId
        ],
        [
            'id' => '52000000-0000-4000-8000-000000000032',
            'schoolId' => '22000000-b512-4ede-852b-f4a508f3e837', // Đại học FPT
            'enterpriseId' => $vinamilkEnterpriseId
        ],
        [
            'id' => '52000000-0000-4000-8000-000000000033',
            'schoolId' => 'da811c4f-2f74-4fdd-80b0-dd6f26109783', // BTEC FPT
            'enterpriseId' => $vinamilkEnterpriseId
        ],
        [
            'id' => '52000000-0000-4000-8000-000000000034',
            'schoolId' => '20000000-0000-4000-8000-000000000001', // THPT Nguyễn Trãi
            'enterpriseId' => $vinamilkEnterpriseId
        ]
    ];

    $partStmt = $pdo->prepare("
        INSERT INTO school_enterprise_partnerships (
            id, schoolId, enterpriseId, status, requestedByUserId, reviewedByUserId, reviewedAt, createdAt, updatedAt
        ) VALUES (
            :id, :schoolId, :enterpriseId, 'approved', '31000000-0000-4000-8000-000000000013', '10000000-0000-4000-8000-000000000013', NOW(6), NOW(6), NOW(6)
        ) ON DUPLICATE KEY UPDATE
            status = 'approved',
            updatedAt = NOW(6)
    ");
    foreach ($partnerships as $p) {
        $partStmt->execute($p);
    }
    echo "  [OK] School partnerships established.\n";

    // --------------------------------------------------------------------------
    // 5. Clean Old Posts and Seed 3 Standard Economics/FMCG Posts for Vinamilk
    // --------------------------------------------------------------------------
    echo "5. Cleaning old posts and seeding Vinamilk 3 Standard Internship Posts...\n";
    
    // First, delete any old application profile snapshots & applications for old Shopee posts
    $oldPostsStmt = $pdo->prepare("SELECT id FROM internship_posts WHERE enterpriseId = ?");
    $oldPostsStmt->execute([$vinamilkEnterpriseId]);
    $oldPostIds = $oldPostsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if (!empty($oldPostIds)) {
        $inPostIds = "'" . implode("','", $oldPostIds) . "'";
        $pdo->exec("DELETE FROM application_profile_snapshots WHERE applicationId IN (SELECT id FROM internship_applications WHERE postId IN ($inPostIds))");
        $pdo->exec("DELETE FROM application_status_history WHERE applicationId IN (SELECT id FROM internship_applications WHERE postId IN ($inPostIds))");
        $pdo->exec("DELETE FROM internship_applications WHERE postId IN ($inPostIds)");
        if ($pdo->query("SHOW TABLES LIKE 'internship_post_target_schools'")->rowCount() > 0) {
            $pdo->exec("DELETE FROM internship_post_target_schools WHERE postId IN ($inPostIds)");
        }
        $pdo->exec("DELETE FROM internship_posts WHERE enterpriseId = '$vinamilkEnterpriseId'");
    }

    $posts = [
        [
            'id' => '40000000-0000-4000-8000-000000000031',
            'enterpriseId' => $vinamilkEnterpriseId,
            'title' => 'Quản trị viên Tập sự Marketing & Phát triển Thương hiệu (Brand Marketing Trainee)',
            'field' => 'Marketing & Phát triển Thương hiệu',
            'status' => 'active',
            'audience' => 'public',
            'location' => 'TP. Hồ Chí Minh & Cần Thơ',
            'workType' => 'Full-time / Hybrid',
            'duration' => '3 - 6 tháng',
            'educationLevel' => 'Đại học / Cao đẳng năm 3 - 4',
            'description' => 'Tham gia trực tiếp cùng đội ngũ Brand Marketing của Vinamilk trong việc xây dựng chiến lược truyền thông đa kênh, nghiên cứu hành vi người tiêu dùng ngành hàng dinh dưỡng, sáng tạo nội dung quảng bá và tối ưu hóa chuyển đổi chiến dịch.',
            'benefits' => 'Phụ cấp thực tập từ 8.000.000 - 12.000.000 VNĐ/tháng, tham gia chương trình Vinamilk Management Trainee, đào tạo 1-1 với Brand Manager kỳ cựu và cơ hội ký hợp đồng chính thức sau kỳ thực tập.',
            'skillsJson' => json_encode(['Phân tích thị trường', 'Digital Marketing', 'Sáng tạo nội dung', 'Kỹ năng thuyết trình', 'Quản trị thương hiệu'], JSON_UNESCAPED_UNICODE),
            'requirementsJson' => json_encode([
                'Sáng tạo chiến dịch quảng bá, Nghiên cứu thị trường, Kỹ năng thuyết trình',
                'Sinh viên năm 3-4 chuyên ngành Marketing, Quản trị Kinh doanh, Truyền thông hoặc Kinh tế',
                'Tiếng Anh giao tiếp tốt (ưu tiên TOEIC 750+ / IELTS 6.0+), tự tin thuyết trình và làm việc nhóm'
            ], JSON_UNESCAPED_UNICODE),
            'slots' => 6,
            'deadline' => '2026-12-31 23:59:59'
        ],
        [
            'id' => '40000000-0000-4000-8000-000000000032',
            'enterpriseId' => $vinamilkEnterpriseId,
            'title' => 'Thực tập sinh Quản trị Chuỗi cung ứng & Logistics (Supply Chain Intern)',
            'field' => 'Quản trị Chuỗi cung ứng & Logistics',
            'status' => 'active',
            'audience' => 'public',
            'location' => 'TP. Hồ Chí Minh / Bình Dương',
            'workType' => 'Full-time / Hybrid',
            'duration' => '3 - 6 tháng',
            'educationLevel' => 'Đại học / Cao đẳng năm 3 - 4',
            'description' => 'Tham gia quản trị vận hành kho bãi hiện đại, trung tâm phân phối Mega Market của Vinamilk, tối ưu hóa mạng lưới điều độ đơn hàng, kiểm soát tồn kho và phân tích dữ liệu chuỗi cung ứng lạnh (Cold Chain).',
            'benefits' => 'Phụ cấp thực tập từ 7.500.000 - 10.500.000 VNĐ/tháng; Hỗ trợ xe đưa đón và phụ cấp cơm trưa tại các nhà máy/kho vận Mega Factory; Đào tạo chuyên sâu về hệ thống ERP SAP S/4HANA.',
            'skillsJson' => json_encode(['Quản lý kho vận', 'Tối ưu hóa đơn hàng', 'Phân tích dữ liệu vận hành', 'Excel nâng cao'], JSON_UNESCAPED_UNICODE),
            'requirementsJson' => json_encode([
                'Quản lý kho vận, Tối ưu hóa đơn hàng, Phân tích dữ liệu vận hành',
                'Sinh viên chuyên ngành Logistics, Quản lý Chuỗi cung ứng, Quản lý Công nghiệp hoặc Kinh tế đối ngoại',
                'Tư duy logic tốt, thành thạo Excel / Phân tích số liệu và cẩn thận, trách nhiệm'
            ], JSON_UNESCAPED_UNICODE),
            'slots' => 5,
            'deadline' => '2026-12-31 23:59:59'
        ],
        [
            'id' => '40000000-0000-4000-8000-000000000033',
            'enterpriseId' => $vinamilkEnterpriseId,
            'title' => 'Thực tập sinh Tài chính - Kế toán Doanh nghiệp (Corporate Finance Trainee)',
            'field' => 'Tài chính Doanh nghiệp & Kế toán',
            'status' => 'active',
            'audience' => 'public',
            'location' => 'TP. Hồ Chí Minh / Hà Nội',
            'workType' => 'Full-time / Hybrid',
            'duration' => '3 - 6 tháng',
            'educationLevel' => 'Đại học / Cao đẳng năm 3 - 4',
            'description' => 'Hỗ trợ lập báo cáo tài chính định kỳ, phân tích biến động chi phí giá thành sản xuất (Cost Accounting), đối soát công nợ đối tác thương mại và tham gia xây dựng mô hình dự báo tài chính doanh nghiệp.',
            'benefits' => 'Phụ cấp thực tập từ 8.000.000 - 11.000.000 VNĐ/tháng; Trực tiếp làm việc với hệ thống kế toán quản trị chuyên nghiệp chuẩn mực IFRS; Cơ hội ưu tiên tuyển dụng chuyên viên tài chính.',
            'skillsJson' => json_encode(['Lập báo cáo tài chính', 'Kế toán chi phí', 'Excel nâng cao', 'Phân tích tài chính'], JSON_UNESCAPED_UNICODE),
            'requirementsJson' => json_encode([
                'Lập báo cáo tài chính, Kế toán chi phí, Kỹ năng Excel nâng cao',
                'Sinh viên năm cuối chuyên ngành Tài chính - Ngân hàng, Kế toán - Kiểm toán hoặc Kinh tế',
                'Nắm chắc nguyên lý kế toán và phân tích báo cáo tài chính, cẩn trọng, bảo mật thông tin'
            ], JSON_UNESCAPED_UNICODE),
            'slots' => 4,
            'deadline' => '2026-12-31 23:59:59'
        ]
    ];

    $postStmt = $pdo->prepare("
        INSERT INTO internship_posts (
            id, enterpriseId, title, field, status, audience, location, workType, duration, educationLevel,
            description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt
        ) VALUES (
            :id, :enterpriseId, :title, :field, :status, :audience, :location, :workType, :duration, :educationLevel,
            :description, :benefits, :skillsJson, :requirementsJson, :slots, :deadline, NOW(6), NOW(6)
        ) ON DUPLICATE KEY UPDATE
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

    foreach ($posts as $p) {
        $postStmt->execute($p);
    }
    echo "  [OK] 3 Vinamilk Economics / Marketing / Logistics / Finance posts created.\n";

    // --------------------------------------------------------------------------
    // 6. Seed Student Lê Hoàng Yến Nhi (CTU - Economics / QTKD)
    // --------------------------------------------------------------------------
    echo "6. Seeding Student Lê Hoàng Yến Nhi (Đại học Cần Thơ - Khoa Kinh tế)...\n";
    $yenNhiUserId = '23000000-0000-4000-8000-000000000018';
    $yenNhiStudentId = '23100000-0000-4000-8000-000000000018';

    $userStmt->execute([
        'id' => $yenNhiUserId,
        'roleId' => $studentRoleId,
        'email' => 'lehoangyennhi@student.ctu.edu.vn',
        'passwordHash' => $passwordHash,
        'fullName' => 'Lê Hoàng Yến Nhi'
    ]);

    $spStmt = $pdo->prepare("
        INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus, createdAt, updatedAt)
        VALUES (:id, :userId, :classId, '2004-06-15', '0912 345 678', 'active', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            classId = VALUES(classId),
            studyStatus = 'active',
            phone = VALUES(phone),
            updatedAt = NOW(6)
    ");
    $spStmt->execute([
        'id' => $yenNhiStudentId,
        'userId' => $yenNhiUserId,
        'classId' => $ctuClassId
    ]);

    // Student profile details (if table exists)
    if ($pdo->query("SHOW TABLES LIKE 'student_profile_details'")->rowCount() > 0) {
        $spdStmt = $pdo->prepare("
            INSERT INTO student_profile_details (studentId, headline, bio, location, createdAt, updatedAt)
            VALUES (:studentId, :headline, :bio, :location, NOW(6), NOW(6))
            ON DUPLICATE KEY UPDATE
                headline = VALUES(headline),
                bio = VALUES(bio),
                location = VALUES(location),
                updatedAt = NOW(6)
        ");
        $spdStmt->execute([
            'studentId' => $yenNhiStudentId,
            'headline' => 'Cử nhân Quản trị Kinh doanh & Marketing Trainee | Đại học Cần Thơ',
            'bio' => 'Sinh viên năm cuối Khoa Kinh tế - Chuyên ngành Quản trị Kinh doanh tại Đại học Cần Thơ. Đam mê nghiên cứu hành vi người tiêu dùng ngành hàng FMCG, xây dựng chiến dịch Brand Marketing và phân tích thị trường. Năng động, có chứng chỉ tiếng Anh TOEIC 800 và kỹ năng thuyết trình sắc bén.',
            'location' => 'Cần Thơ / TP. Hồ Chí Minh'
        ]);
    }

    // Student Skills
    $yenNhiSkills = [
        ['skillId' => '80000000-0000-4000-8000-000000000031', 'score' => 92.00], // Phân tích thị trường
        ['skillId' => '80000000-0000-4000-8000-000000000003', 'score' => 90.00], // Digital Marketing
        ['skillId' => '80000000-0000-4000-8000-000000000032', 'score' => 95.00], // Tiếng Anh TOEIC 800
        ['skillId' => '80000000-0000-4000-8000-000000000004', 'score' => 88.00], // Sáng tạo nội dung
        ['skillId' => '80000000-0000-4000-8000-000000000033', 'score' => 90.00], // Kỹ năng thuyết trình
    ];

    $ssStmt = $pdo->prepare("
        INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt)
        VALUES (:id, :studentId, :skillId, :levelScore, 'assessment', 'verified', NOW(6), NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            levelScore = VALUES(levelScore),
            verificationStatus = 'verified',
            updatedAt = NOW(6)
    ");
    foreach ($yenNhiSkills as $idx => $ysk) {
        $ssId = '81000000-0000-4000-8000-0000000000' . sprintf('%02d', 31 + $idx);
        $ssStmt->execute([
            'id' => $ssId,
            'studentId' => $yenNhiStudentId,
            'skillId' => $ysk['skillId'],
            'levelScore' => $ysk['score']
        ]);
    }
    echo "  [OK] Student Lê Hoàng Yến Nhi created with Economic/Marketing skills.\n";

    // Privacy Consent for Yen Nhi
    $consentId = '8055586d-d6ae-4905-9b6c-fa2527bd4018';
    $consentStmt = $pdo->prepare("
        INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, createdAt)
        VALUES (:id, :studentId, 'enterprise_talent_contact', 1, '1.0', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE isGranted = 1, grantedAt = NOW(6)
    ");
    $consentStmt->execute([
        'id' => $consentId,
        'studentId' => $yenNhiStudentId
    ]);
    echo "  [OK] Privacy Consent recorded.\n";

    // --------------------------------------------------------------------------
    // 7. Seed Application of Lê Hoàng Yến Nhi for Brand Marketing Trainee Post
    // --------------------------------------------------------------------------
    echo "7. Seeding Application & Immutable Profile Snapshot...\n";
    $applicationId = '41000000-0000-4000-8000-000000000001';
    $brandMarketingPostId = '40000000-0000-4000-8000-000000000031';

    $appStmt = $pdo->prepare("
        INSERT INTO internship_applications (
            id, postId, studentId, status, message, reviewerNote, appliedAt, createdAt, updatedAt
        ) VALUES (
            :id, :postId, :studentId, 'submitted', :message, NULL, NOW(6), NOW(6), NOW(6)
        ) ON DUPLICATE KEY UPDATE
            postId = VALUES(postId),
            studentId = VALUES(studentId),
            status = 'submitted',
            message = VALUES(message),
            reviewerNote = NULL,
            updatedAt = NOW(6)
    ");
    $appStmt->execute([
        'id' => $applicationId,
        'postId' => $brandMarketingPostId,
        'studentId' => $yenNhiStudentId,
        'message' => 'Em chào Quý Anh/Chị Tuyển dụng Vinamilk. Em xin ứng tuyển vị trí Quản trị viên Tập sự Marketing & Phát triển Thương hiệu với mong muốn cống hiến và phát triển cùng thương hiệu sữa quốc gia.'
    ]);

    // Application Status History
    $historyStmt = $pdo->prepare("
        INSERT INTO application_status_history (id, applicationId, fromStatus, toStatus, changedByUserId, changedByRole, note, createdAt)
        VALUES (:id, :applicationId, 'submitted', 'submitted', :changedByUserId, 'student', 'Ứng viên nộp hồ sơ trực tuyến qua TalentHub', NOW(6))
        ON DUPLICATE KEY UPDATE note = VALUES(note)
    ");
    $historyStmt->execute([
        'id' => '42000000-0000-4000-8000-000000000001',
        'applicationId' => $applicationId,
        'changedByUserId' => $yenNhiUserId
    ]);

    // Immutable Application Snapshot (for View CV Modal)
    $snapshotPayload = [
        'schemaVersion' => '1.0.0',
        'consentId' => $consentId,
        'capturedAt' => date('Y-m-d\TH:i:s.u\Z'),
        'student' => [
            'studentProfileId' => $yenNhiStudentId,
            'fullName' => 'Lê Hoàng Yến Nhi',
            'email' => 'lehoangyennhi@student.ctu.edu.vn',
            'phone' => '0912 345 678',
            'dateOfBirth' => '2004-06-15',
            'studyStatus' => 'Sinh viên năm cuối (Quản trị Kinh doanh)',
            'schoolName' => 'Đại học Cần Thơ',
            'className' => 'K47 Quản trị Kinh doanh (Năm 4)',
            'location' => 'Cần Thơ / TP. Hồ Chí Minh',
            'headline' => 'Cử nhân Quản trị Kinh doanh & Marketing Trainee | Đại học Cần Thơ',
            'bio' => 'Sinh viên năm cuối Khoa Kinh tế - Chuyên ngành Quản trị Kinh doanh tại Đại học Cần Thơ. Đam mê nghiên cứu hành vi người tiêu dùng ngành hàng FMCG, xây dựng chiến dịch Brand Marketing và phân tích thị trường. Năng động, có chứng chỉ tiếng Anh TOEIC 800 và kỹ năng thuyết trình sắc bén.',
        ],
        'skills' => [
            ['skillName' => 'Phân tích thị trường', 'level' => 'Xuất sắc (92/100)'],
            ['skillName' => 'Digital Marketing', 'level' => 'Nâng cao (90/100)'],
            ['skillName' => 'Tiếng Anh TOEIC 800', 'level' => 'Thành thạo (95/100)'],
            ['skillName' => 'Sáng tạo nội dung & Truyền thông', 'level' => 'Nâng cao (88/100)'],
            ['skillName' => 'Kỹ năng thuyết trình & Teamwork', 'level' => 'Thành thạo (90/100)'],
        ],
        'projects' => [
            [
                'title' => 'Chiến dịch Brand Marketing: Nông sản Xanh ĐBSCL',
                'role' => 'Trưởng nhóm Marketing & Nghiên cứu Thị trường',
                'summary' => 'Nghiên cứu thị trường và hành vi tiêu dùng đối với sản phẩm sạch tại khu vực ĐBSCL, xây dựng chiến lược truyền thông tích hợp (IMC) và đạt giải Nhất cuộc thi Sáng kiến Kinh doanh Trẻ 2025.'
            ],
            [
                'title' => 'Đồ án Tối ưu hóa Hành trình Trải nghiệm Khách hàng FMCG',
                'role' => 'Chuyên viên Phân tích Dữ liệu Khách hàng',
                'summary' => 'Khảo sát hơn 500 người tiêu dùng, phân tích insight khách hàng và đề xuất mô hình phân phối O2O kết hợp kênh truyền thống và thương mại điện tử.'
            ]
        ],
        'certificates' => [
            [
                'name' => 'Chứng chỉ Tiếng Anh TOEIC 800/990',
                'issuingOrganization' => 'Educational Testing Service (ETS)',
                'issueDate' => '2025-11-15'
            ],
            [
                'name' => 'Google Digital Marketing & E-commerce Professional Certificate',
                'issuingOrganization' => 'Google Career Certificates',
                'issueDate' => '2026-03-20'
            ],
            [
                'name' => 'HubSpot Content Marketing Certified',
                'issuingOrganization' => 'HubSpot Academy',
                'issueDate' => '2026-01-10'
            ]
        ],
        'experience' => [
            'totalConfirmedHours' => 180,
            'totalActivitiesAttended' => 12,
            'summary' => '180 giờ trải nghiệm thực tế trong các dự án marketing và hoạt động sinh viên khoa Kinh tế'
        ]
    ];

    if ($pdo->query("SHOW TABLES LIKE 'application_profile_snapshots'")->rowCount() > 0) {
        $snapStmt = $pdo->prepare("
            INSERT INTO application_profile_snapshots (id, applicationId, consentId, schemaVersion, snapshotPayload, createdAt)
            VALUES (:id, :applicationId, :consentId, '1.0.0', :snapshotPayload, NOW(6))
            ON DUPLICATE KEY UPDATE
                consentId = VALUES(consentId),
                schemaVersion = '1.0.0',
                snapshotPayload = VALUES(snapshotPayload)
        ");
        $snapStmt->execute([
            'id' => '43000000-0000-4000-8000-000000000001',
            'applicationId' => $applicationId,
            'consentId' => $consentId,
            'snapshotPayload' => json_encode($snapshotPayload, JSON_UNESCAPED_UNICODE)
        ]);
        echo "  [OK] Immutable Profile Snapshot attached.\n";
    }

    $pdo->commit();
    echo "\n>>> SUCCESS: VINAMILK ECOSYSTEM & DATA ISOLATION SEED COMPLETED! <<<\n\n";

} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[ERROR] Seeding failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
