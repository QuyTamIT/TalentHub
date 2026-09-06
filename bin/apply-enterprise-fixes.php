<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " APPLYING ENTERPRISE PORTAL & SPONSORSHIPS FIXES\n";
echo "======================================================================\n\n";

$schoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783'; // BTEC FPT
$enterpriseId = '10000000-0000-4000-8000-000000000003'; // FPT Software
$mentorTeacherId = 'ef67c7f4-bc9b-4353-a484-e6ee21291c32'; // ThS. Nguyễn Văn Hùng

// ----------------------------------------------------------------------
// 1. Clean Up Dummy Teacher "abc" -> "ThS. Nguyễn Văn Hùng"
// ----------------------------------------------------------------------
echo "1. Cleaning up dummy teacher records ('abc')...\n";

$stTeacherUser = $pdo->prepare("UPDATE users SET fullName = 'ThS. Nguyễn Văn Hùng' WHERE fullName = 'abc' OR email = 'teacher1@talenthub.local'");
$stTeacherUser->execute();
echo "   Updated users: " . $stTeacherUser->rowCount() . " rows\n";

$stTeacherProf = $pdo->prepare("
    UPDATE teacher_profiles
    SET specialization = 'Kỹ thuật phần mềm & Trí tuệ nhân tạo (AI)',
        bio = 'Giảng viên chuyên ngành CNTT & AI - Cao đẳng Quốc tế BTEC FPT'
    WHERE userId = 'd5c748e4-ee8a-4411-8db5-bc6c15dd5379'
");
$stTeacherProf->execute();
echo "   Updated teacher_profiles for d5c748e4: " . $stTeacherProf->rowCount() . " rows\n";

// ----------------------------------------------------------------------
// 2. Seed Real Research & Innovation Projects
// ----------------------------------------------------------------------
echo "\n2. Seeding Real Research & Innovation Projects...\n";

$projects = [
    [
        'id' => '50000000-0000-4000-8000-000000000001',
        'title' => 'Smart Garden IoT - Hệ Thống Vườn Thông Minh Tự Động',
        'category' => 'IoT & AI Nhúng',
        'description' => 'Hệ thống vườn thông minh tự động ứng dụng mạng lưới cảm biến IoT và vi điều khiển ESP32, phân tích dữ liệu độ ẩm đất và dự đoán tưới tiêu tối ưu.',
        'fundingGoal' => 50000000.00,
        'schoolId' => $schoolId,
        'mentorTeacherId' => $mentorTeacherId,
        'status' => 'in_progress',
    ],
    [
        'id' => '50000000-0000-4000-8000-000000000002',
        'title' => 'Hệ thống phân loại rác thông minh YOLOv8',
        'category' => 'Trí tuệ nhân tạo & Thị giác máy tính',
        'description' => 'Hệ thống nhận diện và phân loại rác thải tự động thời gian thực sử dụng Computer Vision YOLOv8 trên vi xử lý Jetson Nano, tự động phân luồng rác tái chế.',
        'fundingGoal' => 30000000.00,
        'schoolId' => $schoolId,
        'mentorTeacherId' => $mentorTeacherId,
        'status' => 'in_progress',
    ],
    [
        'id' => '50000000-0000-4000-8000-000000000003',
        'title' => 'AI for Healthcare - Chẩn đoán X-quang phổi',
        'category' => 'AI Y tế & Chuyển đổi số',
        'description' => 'Ứng dụng mô hình Deep Learning phân tích ảnh chụp X-quang lồng ngực hỗ trợ bác sĩ phát hiện sớm tổn thương phổi và các bệnh lý hô hấp.',
        'fundingGoal' => 40000000.00,
        'schoolId' => $schoolId,
        'mentorTeacherId' => $mentorTeacherId,
        'status' => 'in_progress',
    ],
];

foreach ($projects as $p) {
    $stProj = $pdo->prepare("
        INSERT INTO projects (id, schoolId, mentorTeacherId, title, category, description, fundingGoal, status, createdAt, updatedAt)
        VALUES (:id, :schoolId, :mentorTeacherId, :title, :category, :description, :fundingGoal, :status, NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            category = VALUES(category),
            description = VALUES(description),
            fundingGoal = VALUES(fundingGoal),
            schoolId = VALUES(schoolId),
            mentorTeacherId = VALUES(mentorTeacherId),
            status = VALUES(status),
            updatedAt = NOW(6)
    ");
    $stProj->execute($p);
    echo "   Upserted project: {$p['title']}\n";
}

// ----------------------------------------------------------------------
// 3. Seed Project Members (Students)
// ----------------------------------------------------------------------
echo "\n3. Seeding Project Members...\n";

// Student IDs
$tamId   = '9f9b3e8c-0f72-4b8d-90d9-53ca6ce0a69d'; // Lê Quý Tam
$ducId   = '1a1dddd2-b913-49bd-96eb-08b610642a8a'; // Trần Minh Đức
$anhId   = 'f3150ce0-7a99-4d5f-8b03-c293b91e37e5'; // Vũ Đức Anh
$namId   = 'bad18cb9-80d1-4d0b-a133-4febf3a0774e'; // Nguyễn Hoàng Nam
$baoId   = 'b0b1bd1a-41e4-401b-b2da-25edabbcdc67'; // Phạm Gia Bảo
$thaoId  = '1af6f972-103b-4246-a171-f529ec4a587c'; // Lê Thị Thu Thảo

$members = [
    // Smart Garden IoT
    ['id' => '51000000-0000-4000-8000-000000000001', 'projectId' => '50000000-0000-4000-8000-000000000001', 'studentId' => $tamId,  'role' => 'Trưởng nhóm IoT & Lập trình ESP32'],
    ['id' => '51000000-0000-4000-8000-000000000002', 'projectId' => '50000000-0000-4000-8000-000000000001', 'studentId' => $ducId,  'role' => 'Kỹ sư Backend & Web Dashboard'],
    ['id' => '51000000-0000-4000-8000-000000000003', 'projectId' => '50000000-0000-4000-8000-000000000001', 'studentId' => $anhId,  'role' => 'Kỹ sư AI & Mô hình dự đoán'],

    // YOLOv8
    ['id' => '51000000-0000-4000-8000-000000000004', 'projectId' => '50000000-0000-4000-8000-000000000002', 'studentId' => $namId,  'role' => 'Trưởng nhóm & Kỹ sư Computer Vision'],
    ['id' => '51000000-0000-4000-8000-000000000005', 'projectId' => '50000000-0000-4000-8000-000000000002', 'studentId' => $baoId,  'role' => 'Lập trình viên Nhúng (Jetson Nano)'],
    ['id' => '51000000-0000-4000-8000-000000000006', 'projectId' => '50000000-0000-4000-8000-000000000002', 'studentId' => $thaoId, 'role' => 'Thiết kế giao diện & Thu thập dữ liệu'],

    // AI Healthcare
    ['id' => '51000000-0000-4000-8000-000000000007', 'projectId' => '50000000-0000-4000-8000-000000000003', 'studentId' => $ducId,  'role' => 'Trưởng nhóm & Kiến trúc sư AI'],
    ['id' => '51000000-0000-4000-8000-000000000008', 'projectId' => '50000000-0000-4000-8000-000000000003', 'studentId' => $tamId,  'role' => 'Kỹ sư Huấn luyện Deep Learning'],
];

foreach ($members as $m) {
    $stMem = $pdo->prepare("
        INSERT INTO project_members (id, projectId, studentId, role, status, joinedAt, createdAt, updatedAt)
        VALUES (:id, :projectId, :studentId, :role, 'active', NOW(6), NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE role = VALUES(role), status = 'active', updatedAt = NOW(6)
    ");
    $stMem->execute($m);
}
echo "   Seeded " . count($members) . " project members.\n";

// ----------------------------------------------------------------------
// 4. Seed Initial Paid Sponsorships (Smart Garden IoT: 38M, YOLOv8: 15M, AI Health: 25M)
// Total Capital Mobilized: 78,000,000 VNĐ
// ----------------------------------------------------------------------
echo "\n4. Seeding Initial Paid Sponsorships & Payment Orders...\n";

$sponsorships = [
    [
        'id' => '52000000-0000-4000-8000-000000000001',
        'enterpriseId' => $enterpriseId,
        'projectId' => '50000000-0000-4000-8000-000000000001', // Smart Garden IoT
        'amount' => 38000000.00,
        'currency' => 'VND',
        'status' => 'paid',
        'note' => 'Tài trợ ươm mầm sáng tạo từ FPT Software CSR cho đề án Smart Garden IoT.',
        'orderId' => '53000000-0000-4000-8000-000000000001',
    ],
    [
        'id' => '52000000-0000-4000-8000-000000000002',
        'enterpriseId' => $enterpriseId,
        'projectId' => '50000000-0000-4000-8000-000000000002', // YOLOv8
        'amount' => 15000000.00,
        'currency' => 'VND',
        'status' => 'paid',
        'note' => 'Tài trợ trang bị Jetson Nano và camera AI phân loại rác thải.',
        'orderId' => '53000000-0000-4000-8000-000000000002',
    ],
    [
        'id' => '52000000-0000-4000-8000-000000000003',
        'enterpriseId' => $enterpriseId,
        'projectId' => '50000000-0000-4000-8000-000000000003', // AI Healthcare
        'amount' => 25000000.00,
        'currency' => 'VND',
        'status' => 'paid',
        'note' => 'Tài trợ hạ tầng GPU Cloud huấn luyện mô hình chẩn đoán y tế.',
        'orderId' => '53000000-0000-4000-8000-000000000003',
    ],
];

foreach ($sponsorships as $s) {
    $stSpon = $pdo->prepare("
        INSERT INTO project_sponsorships (id, enterpriseId, projectId, amount, currency, status, note, createdAt, updatedAt)
        VALUES (:id, :enterpriseId, :projectId, :amount, :currency, :status, :note, NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = VALUES(status), note = VALUES(note), updatedAt = NOW(6)
    ");
    $stSpon->execute([
        'id' => $s['id'],
        'enterpriseId' => $s['enterpriseId'],
        'projectId' => $s['projectId'],
        'amount' => $s['amount'],
        'currency' => $s['currency'],
        'status' => $s['status'],
        'note' => $s['note'],
    ]);

    // Check payment_orders table
    $hasPaymentOrders = (bool)$pdo->query("SHOW TABLES LIKE 'payment_orders'")->fetchColumn();
    if ($hasPaymentOrders) {
        $stOrder = $pdo->prepare("
            INSERT INTO payment_orders (id, enterpriseId, sponsorshipId, amount, currency, provider, paymentStatus, providerReference, paidAt, createdAt, updatedAt)
            VALUES (:id, :enterpriseId, :sponsorshipId, :amount, :currency, 'vnpay', 'paid', 'VNPAY_INIT_SEED', NOW(6), NOW(6), NOW(6))
            ON DUPLICATE KEY UPDATE paymentStatus = 'paid', paidAt = NOW(6), updatedAt = NOW(6)
        ");
        $stOrder->execute([
            'id' => $s['orderId'],
            'enterpriseId' => $s['enterpriseId'],
            'sponsorshipId' => $s['id'],
            'amount' => $s['amount'],
            'currency' => $s['currency'],
        ]);
    }
}
echo "   Seeded " . count($sponsorships) . " sponsorships.\n";

// ----------------------------------------------------------------------
// 5. Seed Student Skills (Python, PyTorch, React, Machine Learning, etc.)
// ----------------------------------------------------------------------
echo "\n5. Seeding Student Skills for Candidate Profiles...\n";

// Map Skill Code -> Skill ID
$skillMap = [];
$skillRows = $pdo->query("SELECT id, code, name FROM skills")->fetchAll(PDO::FETCH_ASSOC);
foreach ($skillRows as $sr) {
    $skillMap[$sr['code']] = $sr['id'];
}

$studentSkillsData = [
    // Lê Quý Tam
    $tamId => [
        ['code' => 'python', 'score' => 95.00],
        ['code' => 'pytorch', 'score' => 90.00],
        ['code' => 'machine_learning', 'score' => 92.00],
        ['code' => 'computer_vision', 'score' => 88.00],
        ['code' => 'langchain', 'score' => 85.00],
        ['code' => 'prompt_engineering', 'score' => 90.00],
        ['code' => 'rest_api', 'score' => 92.00],
        ['code' => 'git', 'score' => 88.00],
        ['code' => 'docker', 'score' => 82.00],
        ['code' => 'mysql', 'score' => 86.00],
    ],
    // Vũ Đức Anh
    $anhId => [
        ['code' => 'python', 'score' => 92.00],
        ['code' => 'machine_learning', 'score' => 94.00],
        ['code' => 'pytorch', 'score' => 88.00],
        ['code' => 'computer_vision', 'score' => 90.00],
        ['code' => 'data_analysis', 'score' => 86.00],
        ['code' => 'mysql', 'score' => 85.00],
        ['code' => 'git', 'score' => 88.00],
    ],
    // Trần Minh Đức
    $ducId => [
        ['code' => 'react', 'score' => 95.00],
        ['code' => 'typescript', 'score' => 92.00],
        ['code' => 'javascript', 'score' => 94.00],
        ['code' => 'nodejs', 'score' => 90.00],
        ['code' => 'html', 'score' => 96.00],
        ['code' => 'css', 'score' => 92.00],
        ['code' => 'python', 'score' => 85.00],
        ['code' => 'rest_api', 'score' => 92.00],
        ['code' => 'git', 'score' => 90.00],
    ],
    // Nguyễn Hoàng Nam
    $namId => [
        ['code' => 'python', 'score' => 88.00],
        ['code' => 'computer_vision', 'score' => 86.00],
        ['code' => 'pytorch', 'score' => 82.00],
        ['code' => 'git', 'score' => 80.00],
    ],
    // Phạm Gia Bảo
    $baoId => [
        ['code' => 'python', 'score' => 85.00],
        ['code' => 'docker', 'score' => 82.00],
        ['code' => 'mysql', 'score' => 80.00],
        ['code' => 'git', 'score' => 80.00],
    ],
    // Lê Thị Thu Thảo
    $thaoId => [
        ['code' => 'react', 'score' => 88.00],
        ['code' => 'javascript', 'score' => 85.00],
        ['code' => 'html', 'score' => 90.00],
        ['code' => 'css', 'score' => 88.00],
        ['code' => 'rest_api', 'score' => 82.00],
    ],
];

$stSkillInsert = $pdo->prepare("
    INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt)
    VALUES (UUID(), :studentId, :skillId, :levelScore, 'assessment', 'verified', NOW(6), NOW(6), NOW(6))
    ON DUPLICATE KEY UPDATE levelScore = VALUES(levelScore), verificationStatus = 'verified', verifiedAt = NOW(6), updatedAt = NOW(6)
");

$seededSkills = 0;
foreach ($studentSkillsData as $stId => $skills) {
    foreach ($skills as $sk) {
        $skillId = $skillMap[$sk['code']] ?? null;
        if ($skillId) {
            // Check if already exists
            $stCheck = $pdo->prepare("SELECT id FROM student_skills WHERE studentId = ? AND skillId = ?");
            $stCheck->execute([$stId, $skillId]);
            $existingId = $stCheck->fetchColumn();

            if ($existingId) {
                $stUp = $pdo->prepare("UPDATE student_skills SET levelScore = ?, verificationStatus = 'verified', verifiedAt = NOW(6), updatedAt = NOW(6) WHERE id = ?");
                $stUp->execute([$sk['score'], $existingId]);
            } else {
                $stSkillInsert->execute([
                    'studentId' => $stId,
                    'skillId' => $skillId,
                    'levelScore' => $sk['score'],
                ]);
            }
            $seededSkills++;
        }
    }
}
echo "   Seeded/Updated {$seededSkills} student skills.\n";

// ----------------------------------------------------------------------
// 6. Seed Student Experience Logs & Activity Registrations
// ----------------------------------------------------------------------
echo "\n6. Seeding Experience Logs (Activity Hours)...\n";

$activityIoTId = '475f9c54-4250-4109-a3a2-f9bd8e6ac5dc'; // IoT Lab - Cảm biến thông minh & AI Nhúng

$studentsForExp = [$tamId, $anhId, $ducId, $namId, $baoId, $thaoId];
foreach ($studentsForExp as $stId) {
    // 1. activity_registrations
    $stReg = $pdo->prepare("
        INSERT INTO activity_registrations (id, activityId, studentId, status, registeredAt, attendanceResolvedAt, updatedAt)
        VALUES (UUID(), :activityId, :studentId, 'attended', NOW(6), NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE status = 'attended', attendanceResolvedAt = NOW(6)
    ");
    try {
        $stReg->execute(['activityId' => $activityIoTId, 'studentId' => $stId]);
    } catch (\Throwable $e) {}

    // 2. experience_logs
    $stExp = $pdo->prepare("
        INSERT INTO experience_logs (id, studentId, activityId, hours, status, confirmedAt, createdAt)
        VALUES (UUID(), :studentId, :activityId, :hours, 'confirmed', NOW(6), NOW(6))
        ON DUPLICATE KEY UPDATE hours = VALUES(hours), status = 'confirmed', confirmedAt = NOW(6)
    ");
    try {
        $stExp->execute([
            'studentId' => $stId,
            'activityId' => $activityIoTId,
            'hours' => 24.00,
        ]);
    } catch (\Throwable $e) {}
}
echo "   Seeded experience logs for students.\n";

echo "\n======================================================================\n";
echo " ENTERPRISE MIGRATIONS COMPLETED SUCCESSFULLY!\n";
echo "======================================================================\n";
