<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$fptEnterpriseId = '31000000-0000-4000-8000-000000000015';

$posts = [
    [
        'id' => '11000000-0000-4000-8000-000000000001',
        'enterpriseId' => $fptEnterpriseId,
        'title' => 'Thực tập sinh Trí tuệ Nhân tạo & LLM (AI Engineer Intern)',
        'field' => 'Trí tuệ Nhân tạo',
        'status' => 'active',
        'audience' => 'partner_schools',
        'location' => 'Hà Nội',
        'workType' => 'Full-time / Hybrid',
        'duration' => '3 tháng',
        'educationLevel' => 'Cao đẳng / Đại học',
        'description' => 'Tham gia nghiên cứu và phát triển các mô hình AI/LLM, ứng dụng NLP và LangChain vào hệ thống thực tế.',
        'benefits' => 'Trợ cấp thực tập hấp dẫn, cơ hội trở thành nhân viên chính thức.',
        'skillsJson' => json_encode(['AI', 'NLP', 'LangChain', 'Python']),
        'requirementsJson' => json_encode(['Có kiến thức về Python, Machine Learning', 'Tư duy logic tốt']),
        'slots' => 5,
    ],
    [
        'id' => '11000000-0000-4000-8000-000000000002',
        'enterpriseId' => $fptEnterpriseId,
        'title' => 'Thực tập sinh Kỹ thuật Phần mềm (Software Engineer Intern)',
        'field' => 'Kỹ thuật Phần mềm',
        'status' => 'active',
        'audience' => 'partner_schools',
        'location' => 'Hà Nội',
        'workType' => 'Full-time / On-site',
        'duration' => '3 tháng',
        'educationLevel' => 'Cao đẳng / Đại học',
        'description' => 'Tham gia phát triển dự án Web Fullstack, bảo trì và tối ưu hóa hệ thống backend microservices.',
        'benefits' => 'Trợ cấp thực tập, đào tạo bởi chuyên gia senior.',
        'skillsJson' => json_encode(['JavaScript', 'PHP', 'Python', 'MySQL']),
        'requirementsJson' => json_encode(['Nắm vững kiến thức lập trình cơ bản', 'Tinh thần học hỏi cao']),
        'slots' => 5,
    ]
];

$stmt = $pdo->prepare("
    INSERT INTO internship_posts (id, enterpriseId, title, field, status, audience, location, workType, duration, educationLevel, description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(6), NOW(6))
    ON DUPLICATE KEY UPDATE title = VALUES(title), field = VALUES(field), status = 'active', audience = VALUES(audience), location = VALUES(location), workType = VALUES(workType), duration = VALUES(duration), educationLevel = VALUES(educationLevel), description = VALUES(description), benefits = VALUES(benefits), skillsJson = VALUES(skillsJson), requirementsJson = VALUES(requirementsJson), slots = VALUES(slots), updatedAt = NOW(6)
");

foreach ($posts as $p) {
    $stmt->execute([
        $p['id'],
        $p['enterpriseId'],
        $p['title'],
        $p['field'],
        $p['status'],
        $p['audience'],
        $p['location'],
        $p['workType'],
        $p['duration'],
        $p['educationLevel'],
        $p['description'],
        $p['benefits'],
        $p['skillsJson'],
        $p['requirementsJson'],
        $p['slots'],
    ]);
    echo "Seeded post: {$p['title']} ({$p['id']})\n";
}

// Target BTEC FPT school
$btecSchoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';
foreach ($posts as $p) {
    try {
        $pdo->prepare("INSERT IGNORE INTO internship_post_target_schools (postId, schoolId, createdAt) VALUES (?, ?, NOW(6))")
            ->execute([$p['id'], $btecSchoolId]);
    } catch (\Throwable $e) {}
}
echo "Target schools configured.\n";
