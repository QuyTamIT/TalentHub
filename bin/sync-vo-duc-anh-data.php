<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " ĐỒNG BỘ ĐIỂM SỐ VÀ KỸ NĂNG CỦA VÕ ĐỨC ANH CHO ENTERPRISE DASHBOARD\n";
echo "======================================================================\n\n";

$studentId = 'a49dadc0-65f0-5862-a380-34c2d43ecbc6'; // Võ Đức Anh

// 1. Check Student
$st = $pdo->query("
    SELECT sp.id as studentId, u.fullName, u.email, sp.talentScore, c.name as className, s.name as schoolName
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    WHERE sp.id = '{$studentId}'
")->fetch(PDO::FETCH_ASSOC);

if (!$st) {
    echo "ERROR: Student Võ Đức Anh not found!\n";
    exit(1);
}
echo "[Step 1] Found Candidate: {$st['fullName']} ({$st['email']})\n";
echo " -> Class: {$st['className']} | School: {$st['schoolName']}\n\n";

// 2. Update Student Profile Score to 94.00 and Headline
$pdo->prepare("
    UPDATE student_profiles 
    SET talentScore = 94.00, updatedAt = NOW() 
    WHERE id = ?
")->execute([$studentId]);

$pdo->prepare("
    UPDATE student_profile_details 
    SET headline = 'Kỹ sư Trí tuệ Nhân tạo & Xử lý Ngôn ngữ Tự nhiên (NLP)',
        bio = 'Sinh viên chuyên ngành AI & Xử lý ngôn ngữ tự nhiên (NLP) - BTEC-AI-2026A. Thành thạo LangChain, NLP, Python và PyTorch.',
        updatedAt = NOW()
    WHERE studentId = ?
")->execute([$studentId]);
echo "[Step 2] Updated talentScore = 94.00 (★ 94 điểm) & Headline AI/NLP\n\n";

// 3. Update Skills for Võ Đức Anh: NLP, LangChain, Python, PyTorch
echo "[Step 3] Updating Skills: NLP, LangChain, Python, PyTorch...\n";

// Delete old skills
$pdo->prepare("DELETE FROM student_skills WHERE studentId = ?")->execute([$studentId]);

$skillsToLink = [
    ['code' => 'nlp', 'name' => 'NLP', 'score' => 95.00, 'id' => '80000000-0000-4000-8000-000000000051'],
    ['code' => 'langchain', 'name' => 'LangChain', 'score' => 94.00, 'id' => '80000000-0000-4000-8000-000000000052'],
    ['code' => 'python', 'name' => 'Python', 'score' => 94.00, 'id' => '22000000-952a-427e-8406-1ad950b1f892'],
    ['code' => 'pytorch', 'name' => 'PyTorch', 'score' => 93.00, 'id' => 'fd427644-23fe-425d-aee1-80820baa4b76'],
];

$upsertSkill = $pdo->prepare("
    INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt)
    VALUES (:id, :code, :name, 'technical', 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code), category = 'technical', status = 'active', updatedAt = NOW()
");

$insertStudentSkill = $pdo->prepare("
    INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt)
    VALUES (?, ?, ?, ?, 'teacher', 'verified', NOW(), NOW(), NOW())
");

foreach ($skillsToLink as $sk) {
    $upsertSkill->execute([
        'id' => $sk['id'],
        'code' => $sk['code'],
        'name' => $sk['name'],
    ]);

    $insertStudentSkill->execute([
        Uuid::v4(),
        $studentId,
        $sk['id'],
        $sk['score'],
    ]);
    echo " -> Added Skill: {$sk['name']} ({$sk['score']}/100) [Verified]\n";
}
echo "\n";

echo "======================================================================\n";
echo " ĐỒNG BỘ DỮ LIỆU THÀNH CÔNG!\n";
echo "======================================================================\n";
