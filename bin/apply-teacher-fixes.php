<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " APPLYING TEACHER PORTAL FIXES (DATABASE)\n";
echo "======================================================================\n\n";

// 1. Assign Lê Quý Tam to BTEC-AI-2026A
$aiClassId = 'a1e2894b-2386-5404-9695-78a78f5a60d3';
$leQuyTamStudentId = '9f9b3e8c-0f72-4b8d-90d9-53ca6ce0a69d';

$st = $pdo->prepare("UPDATE student_profiles SET classId = ? WHERE id = ?");
$st->execute([$aiClassId, $leQuyTamStudentId]);
echo "[1] Updated Lê Quý Tam classId to BTEC-AI-2026A ({$aiClassId}).\n";

// 2. Standardize Activity 'Fix yourself' -> 'IoT Lab - Cảm biến thông minh & AI Nhúng'
$activityId = '475f9c54-4250-4109-a3a2-f9bd8e6ac5dc';
$updAct = $pdo->prepare("
    UPDATE activities
    SET title = 'IoT Lab - Cảm biến thông minh & AI Nhúng',
        category = 'career_technical',
        updatedAt = NOW()
    WHERE id = ?
");
$updAct->execute([$activityId]);
echo "[2] Updated activity title to 'IoT Lab - Cảm biến thông minh & AI Nhúng'.\n";

// Upsert into activity_details
$detailStmt = $pdo->prepare("
    INSERT INTO activity_details (
        activityId, responsibleTeacherId, audienceScope, displayCategory, filterCategory,
        summary, description, experienceHighlights, skillTags, eligibilityRules, benefitItems,
        locationName, locationAddress, deliveryMode, organizerName, targetAudience, feeAmount, currency,
        createdAt, updatedAt
    ) VALUES (
        :activityId, :responsibleTeacherId, 'school_only', 'IoT & AI Nhúng', 'career_technical',
        :summary, :description, :experienceHighlights, :skillTags, :eligibilityRules, :benefitItems,
        :locationName, :locationAddress, 'in_person', 'Cao đẳng Quốc tế BTEC FPT', 'Sinh viên BTEC FPT', 0.00, 'VND',
        NOW(), NOW()
    ) ON DUPLICATE KEY UPDATE
        displayCategory = VALUES(displayCategory),
        filterCategory = VALUES(filterCategory),
        summary = VALUES(summary),
        description = VALUES(description),
        locationName = VALUES(locationName),
        locationAddress = VALUES(locationAddress),
        deliveryMode = VALUES(deliveryMode),
        experienceHighlights = VALUES(experienceHighlights),
        skillTags = VALUES(skillTags),
        eligibilityRules = VALUES(eligibilityRules),
        benefitItems = VALUES(benefitItems),
        organizerName = VALUES(organizerName),
        targetAudience = VALUES(targetAudience),
        updatedAt = NOW()
");

$detailStmt->execute([
    'activityId' => $activityId,
    'responsibleTeacherId' => 'ef67c7f4-bc9b-4353-a484-e6ee21291c32',
    'summary' => 'Xưởng thực hành lập trình vi điều khiển ESP32 và tích hợp mô hình AI nhận diện.',
    'description' => 'Xưởng thực hành lập trình vi điều khiển ESP32 và tích hợp mô hình AI nhận diện.',
    'experienceHighlights' => json_encode(['Lập trình vi điều khiển ESP32', 'Tích hợp mô hình AI nhận diện', 'Thực hành cảm biến thông minh'], JSON_UNESCAPED_UNICODE),
    'skillTags' => json_encode(['IoT', 'ESP32', 'Embedded AI', 'Python', 'C++'], JSON_UNESCAPED_UNICODE),
    'eligibilityRules' => json_encode(['Sinh viên Cao đẳng Quốc tế BTEC FPT', 'Đăng ký trước thời hạn'], JSON_UNESCAPED_UNICODE),
    'benefitItems' => json_encode(['Ghi nhận 4 giờ trải nghiệm', 'Chứng nhận hoàn thành workshop IoT & AI'], JSON_UNESCAPED_UNICODE),
    'locationName' => 'Phòng Thực hành B305 - BTEC Cần Thơ',
    'locationAddress' => 'Phòng Thực hành B305 - BTEC Cần Thơ',
]);
echo "[3] Upserted activity_details with location and description.\n";

// 3. Verify students in BTEC-AI-2026A
$stStudents = $pdo->prepare("
    SELECT u.fullName, u.email, sp.talentScore
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    WHERE sp.classId = ? AND sp.studyStatus = 'active'
    ORDER BY u.fullName ASC
");
$stStudents->execute([$aiClassId]);
$aiStudents = $stStudents->fetchAll(PDO::FETCH_ASSOC);

echo "\nStudents in BTEC-AI-2026A (" . count($aiStudents) . " total):\n";
foreach ($aiStudents as $s) {
    echo "  - {$s['fullName']} ({$s['email']}) -> Score: {$s['talentScore']}\n";
}

echo "\nDatabase fixes applied successfully.\n";
