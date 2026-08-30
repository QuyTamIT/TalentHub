<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "======================================================================\n";
echo " RESTORING DIVERSIFIED SKILL SCORES FOR STUDENTS\n";
echo "======================================================================\n";

$skillScoresMap = [
    // Lê Quý Tam (AI/ML & Python focus, with balanced soft & creative skills)
    '9f9b3e8c-0f72-4b8d-90d9-53ca6ce0a69d' => [
        'Python' => 90.0,
        'AI / Machine Learning' => 88.0,
        'PyTorch' => 86.0,
        'Machine Learning' => 85.0,
        'Computer Vision' => 84.0,
        'Phân tích dữ liệu' => 82.0,
        'MySQL' => 78.0,
        'Git' => 75.0,
        'Tiếng Anh TOEIC 850' => 85.0,
        'Nghiên cứu khoa học' => 80.0,
        'Làm việc nhóm' => 82.0,
        'Giao tiếp & Thuyết trình' => 76.0,
        'Khởi nghiệp & Quản trị' => 70.0,
        'Digital Marketing' => 68.0,
        'Sáng tạo nội dung' => 65.0,
        'Thiết kế sáng tạo & UI/UX' => 60.0,
        'Docker' => 74.0,
        'REST API' => 80.0,
        'LangChain' => 82.0,
        'Prompt Engineering' => 85.0,
    ],
    // Vũ Đức Anh (Machine Learning & Data focus)
    'f3150ce0-7a99-4d5f-8b03-c293b91e37e5' => [
        'Machine Learning' => 88.0,
        'Phân tích dữ liệu' => 86.0,
        'Computer Vision' => 85.0,
        'Python' => 84.0,
        'PyTorch' => 82.0,
        'MySQL' => 80.0,
        'Git' => 78.0,
        'Tiếng Anh TOEIC 850' => 82.0,
        'Nghiên cứu khoa học' => 78.0,
        'Làm việc nhóm' => 85.0,
        'Giao tiếp & Thuyết trình' => 80.0,
        'Khởi nghiệp & Quản trị' => 72.0,
        'Digital Marketing' => 72.0,
        'Sáng tạo nội dung' => 70.0,
        'Thiết kế sáng tạo & UI/UX' => 65.0,
        'AI / Machine Learning' => 86.0,
    ],
];

foreach ($skillScoresMap as $stId => $skills) {
    foreach ($skills as $skillName => $score) {
        $stmt = $pdo->prepare("
            UPDATE student_skills ss
            JOIN skills s ON s.id = ss.skillId
            SET ss.levelScore = ?, ss.updatedAt = NOW()
            WHERE ss.studentId = ? AND s.name = ?
        ");
        $stmt->execute([$score, $stId, $skillName]);
    }
    echo "  [DONE] Restored skill scores for student ID {$stId}\n";
}

echo "\nAll skill scores restored successfully.\n";
