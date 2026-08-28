<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " APPLYING STUDENT PORTAL & DATABASE FIXES (LÊ QUÝ TAM)\n";
echo "======================================================================\n\n";

$studentEmail = 'tamlangtu2005@gmail.com';
$teacherEmail = 'teacher@talenthub.local';

// 1. Get Student & Teacher IDs
$stStudent = $pdo->prepare("
    SELECT sp.id as studentId, sp.userId, u.fullName, c.id as classId, c.name as className, c.schoolId, s.name as schoolName
    FROM users u
    JOIN student_profiles sp ON sp.userId = u.id
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    WHERE u.email = ?
");
$stStudent->execute([$studentEmail]);
$student = $stStudent->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "ERROR: Student account {$studentEmail} not found!\n";
    exit(1);
}

$studentId = (string) $student['studentId'];
$userId = (string) $student['userId'];
$schoolId = (string) ($student['schoolId'] ?: 'da811c4f-2f74-4fdd-80b0-dd6f26109783');

$stTeacher = $pdo->prepare("
    SELECT tp.id as teacherId, tp.userId, u.fullName
    FROM users u
    JOIN teacher_profiles tp ON tp.userId = u.id
    WHERE u.email = ?
");
$stTeacher->execute([$teacherEmail]);
$teacher = $stTeacher->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    // If not found by email, find any teacher profile with name ThS. Nguyễn Văn Hùng
    $stTeacher2 = $pdo->prepare("
        SELECT tp.id as teacherId, tp.userId, u.fullName
        FROM users u
        JOIN teacher_profiles tp ON tp.userId = u.id
        WHERE u.fullName LIKE '%Nguyễn Văn Hùng%'
        LIMIT 1
    ");
    $stTeacher2->execute();
    $teacher = $stTeacher2->fetch(PDO::FETCH_ASSOC);
}

$teacherId = $teacher ? (string) $teacher['teacherId'] : 'ef67c7f4-bc9b-4353-a484-e6ee21291c32';
$teacherName = $teacher ? (string) $teacher['fullName'] : 'ThS. Nguyễn Văn Hùng';

echo "[Step 1] Identified Student: {$student['fullName']} (ID: {$studentId})\n";
echo "         Identified Teacher: {$teacherName} (ID: {$teacherId})\n\n";

// ----------------------------------------------------------------------
// TASK 1: Published Teacher Evaluation for Lê Quý Tam (Overall 85/100)
// ----------------------------------------------------------------------
echo "[Step 2] Updating Teacher Evaluation (85/100) for Lê Quý Tam...\n";

// Ensure active activity exists for assessment
$activityId = '80000000-0000-4000-8000-000000000001';
$chkAct = $pdo->prepare("SELECT id FROM activities WHERE id = ?");
$chkAct->execute([$activityId]);
if (!$chkAct->fetchColumn()) {
    $insAct = $pdo->prepare("
        INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status, visibility, createdAt, updatedAt)
        VALUES (?, ?, ?, 'Đồ án Thực hành Phát triển Phần mềm & AI 2026', 'academic', NOW(6), DATE_ADD(NOW(6), INTERVAL 30 DAY), 50, 'completed', 'school_only', NOW(6), NOW(6))
    ");
    $insAct->execute([$activityId, $schoolId, $teacherId]);
}

// Ensure activity registration exists
$chkReg = $pdo->prepare("SELECT id FROM activity_registrations WHERE activityId = ? AND studentId = ?");
$chkReg->execute([$activityId, $studentId]);
if (!$chkReg->fetchColumn()) {
    $insReg = $pdo->prepare("
        INSERT INTO activity_registrations (id, activityId, studentId, status, registeredAt, attendanceResolvedAt, updatedAt)
        VALUES (?, ?, ?, 'attended', NOW(6), NOW(6), NOW(6))
    ");
    $insReg->execute([Uuid::v4(), $activityId, $studentId]);
}

// Upsert Criteria
$criteriaDefs = [
    'crit-execution' => ['name' => 'Kỹ năng chuyên môn & Thực thi', 'desc' => 'Khả năng lập trình, áp dụng công nghệ và hoàn thiện sản phẩm', 'min' => 0, 'max' => 100, 'order' => 1],
    'crit-logic' => ['name' => 'Tư duy logic & Thuật toán', 'desc' => 'Tư duy phân tích, thiết kế giải thuật và xử lý vấn đề kỹ thuật', 'min' => 0, 'max' => 100, 'order' => 2],
    'crit-teamwork' => ['name' => 'Làm việc nhóm & Giao tiếp', 'desc' => 'Phối hợp với đồng đội, trao đổi kỹ thuật và báo cáo tiến độ', 'min' => 0, 'max' => 100, 'order' => 3],
    'crit-initiative' => ['name' => 'Chủ động & Đổi mới sáng tạo', 'desc' => 'Tinh thần tự học công nghệ mới và đề xuất giải pháp tối ưu', 'min' => 0, 'max' => 100, 'order' => 4],
];

$criteriaIds = [];
$upsertCrit = $pdo->prepare("
    INSERT INTO assessment_criteria (id, code, name, description, minScore, maxScore, displayOrder, status, createdAt, updatedAt)
    VALUES (:id, :code, :name, :desc, :min, :max, :order, 'active', NOW(6), NOW(6))
    ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), minScore = VALUES(minScore), maxScore = VALUES(maxScore), displayOrder = VALUES(displayOrder), status = 'active', updatedAt = NOW(6)
");

foreach ($criteriaDefs as $code => $c) {
    // Check if criteria exists by code
    $stc = $pdo->prepare("SELECT id FROM assessment_criteria WHERE code = ?");
    $stc->execute([$code]);
    $cid = $stc->fetchColumn();
    if (!$cid) {
        $cid = Uuid::v4();
        $upsertCrit->execute([
            'id' => $cid,
            'code' => $code,
            'name' => $c['name'],
            'desc' => $c['desc'],
            'min' => $c['min'],
            'max' => $c['max'],
            'order' => $c['order'],
        ]);
    }
    $criteriaIds[$code] = $cid;
}

// Upsert Assessment for Le Quy Tam
$assessmentId = 'a1000000-0000-4000-8000-000000000099';
$chkAss = $pdo->prepare("SELECT id FROM assessments WHERE studentId = ?");
$chkAss->execute([$studentId]);
$existingAssId = $chkAss->fetchColumn();
if ($existingAssId) {
    $assessmentId = (string) $existingAssId;
}

$comment = "Lê Quý Tam thể hiện tư duy logic xuất sắc, làm chủ tốt các công nghệ phần mềm hiện đại và hoàn thành đồ án đúng hạn với chất lượng cao. Cần tiếp tục duy trì tinh thần chủ động và rèn luyện thêm kỹ năng thuyết trình dự án.";

$stmtSaveAss = $pdo->prepare("
    INSERT INTO assessments (id, teacherId, studentId, activityId, overallScore, comment, status, publishedAt, version, createdAt, updatedAt)
    VALUES (:id, :teacherId, :studentId, :activityId, 85.00, :comment, 'published', NOW(6), 1, NOW(6), NOW(6))
    ON DUPLICATE KEY UPDATE teacherId = VALUES(teacherId), activityId = VALUES(activityId), overallScore = 85.00, comment = VALUES(comment), status = 'published', publishedAt = NOW(6), updatedAt = NOW(6)
");
$stmtSaveAss->execute([
    'id' => $assessmentId,
    'teacherId' => $teacherId,
    'studentId' => $studentId,
    'activityId' => $activityId,
    'comment' => $comment,
]);

// Delete old assessment scores for this assessment and insert new ones
$pdo->prepare("DELETE FROM assessment_scores WHERE assessmentId = ?")->execute([$assessmentId]);

$scoresData = [
    'crit-execution' => 88.00,
    'crit-logic' => 90.00,
    'crit-teamwork' => 80.00,
    'crit-initiative' => 82.00,
];

$insScore = $pdo->prepare("
    INSERT INTO assessment_scores (id, assessmentId, criteriaId, score, createdAt, updatedAt)
    VALUES (?, ?, ?, ?, NOW(6), NOW(6))
");

foreach ($scoresData as $code => $score) {
    $insScore->execute([
        Uuid::v4(),
        $assessmentId,
        $criteriaIds[$code],
        $score,
    ]);
}

// Update student profile talentScore
$pdo->prepare("UPDATE student_profiles SET talentScore = 85.00, updatedAt = NOW(6) WHERE id = ?")->execute([$studentId]);

echo " -> Teacher evaluation published: Overall Score = 85.00/100, Reviewer = {$teacherName}\n\n";

// ----------------------------------------------------------------------
// TASK 2: Update Radar Chart & Test Results with Distinct Scores
// ----------------------------------------------------------------------
echo "[Step 3] Updating Radar Chart & Aptitude Test Results with Distinct Values...\n";

// Update Multiple Intelligence test results
$miScores = [
    'LOGI' => 88,
    'SPAT' => 82,
    'INTRA' => 76,
    'INTER' => 72,
    'LING' => 68,
    'BODY' => 58,
    'NAT' => 52,
    'MUSIC' => 45,
];

$hollandScores = [
    'R' => 92,
    'I' => 85,
    'E' => 70,
    'A' => 65,
    'C' => 60,
    'S' => 55,
];

$mbtiScores = [
    'E' => 45,
    'I' => 82,
    'S' => 40,
    'N' => 85,
    'T' => 88,
    'F' => 35,
    'J' => 78,
    'P' => 42,
];

$discScores = [
    'D' => 82,
    'I' => 65,
    'S' => 55,
    'C' => 88,
];

// Update in test_results for student attempts
$attempts = $pdo->prepare("
    SELECT tr.id as resultId, a.id as attemptId, t.code, t.type
    FROM test_results tr
    JOIN test_attempts a ON a.id = tr.attemptId
    JOIN talent_tests t ON t.id = a.testId
    WHERE a.studentId = ?
");
$attempts->execute([$studentId]);
$results = $attempts->fetchAll(PDO::FETCH_ASSOC);

$updResult = $pdo->prepare("
    UPDATE test_results
    SET dimensionScoresJson = :scores,
        resultCode = :code,
        summary = :summary
    WHERE id = :id
");

foreach ($results as $res) {
    $type = (string) $res['type'];
    $code = (string) $res['code'];

    if ($type === 'multiple_intelligence' || str_contains($code, 'multiple_intelligence')) {
        $updResult->execute([
            'id' => $res['resultId'],
            'scores' => json_encode($miScores),
            'code' => 'LOGI-SPAT',
            'summary' => 'Trí thông minh Logic - Toán học và Không gian nổi trội, phù hợp với các ngành Kỹ thuật, Lập trình và Công nghệ.',
        ]);
        echo " -> Updated Multiple Intelligence: Logic 88, Spatial 82, Intra 76, Inter 72, Ling 68, Body 58, Nat 52, Music 45\n";
    } elseif ($type === 'holland' || str_contains($code, 'holland')) {
        $updResult->execute([
            'id' => $res['resultId'],
            'scores' => json_encode($hollandScores),
            'code' => 'RIE',
            'summary' => 'Thiên hướng Kỹ thuật (Realistic - 92%), Nghiên cứu (Investigative - 85%) và Quản lý (Enterprising - 70%).',
        ]);
        echo " -> Updated Holland RIASEC: R 92, I 85, E 70, A 65, C 60, S 55\n";
    } elseif ($type === 'mbti' || str_contains($code, 'mbti')) {
        $updResult->execute([
            'id' => $res['resultId'],
            'scores' => json_encode($mbtiScores),
            'code' => 'INTJ',
            'summary' => 'Nhà kiến tạo - Tư duy chiến lược độc lập, khả năng phân tích hệ thống và giải quyết bài toán phức tạp.',
        ]);
        echo " -> Updated MBTI: INTJ (T: 88, N: 85, I: 82, J: 78)\n";
    } elseif ($type === 'disc' || str_contains($code, 'disc')) {
        $updResult->execute([
            'id' => $res['resultId'],
            'scores' => json_encode($discScores),
            'code' => 'CD',
            'summary' => 'Chuẩn xác & Quyết đoán - Phong cách làm việc hướng đến chất lượng cao, logic và mục tiêu rõ ràng.',
        ]);
        echo " -> Updated DISC: CD (C: 88, D: 82, I: 65, S: 55)\n";
    }
}
echo "\n";

// ----------------------------------------------------------------------
// TASK 3: Clean all "(Dữ liệu demo)" & "(dữ liệu mô phỏng)" strings
// ----------------------------------------------------------------------
echo "[Step 4] Cleaning '(Dữ liệu demo)' and '(dữ liệu mô phỏng)' across database...\n";

// 1. Clean school_certificate_catalog
$cleanCertCat = $pdo->prepare("
    UPDATE school_certificate_catalog
    SET issuerName = REPLACE(REPLACE(REPLACE(issuerName, ' (Dữ liệu demo)', ''), ' (dữ liệu demo)', ''), ' (dữ liệu mô phỏng)', '')
    WHERE issuerName LIKE '%demo%' OR issuerName LIKE '%mô phỏng%'
");
$cleanCertCat->execute();
echo " -> Cleaned school_certificate_catalog issuerName (" . $cleanCertCat->rowCount() . " rows updated)\n";

// 2. Clean schools
$cleanSchools = $pdo->prepare("
    UPDATE schools
    SET name = REPLACE(REPLACE(REPLACE(name, ' (Dữ liệu demo)', ''), ' (dữ liệu demo)', ''), ' (dữ liệu mô phỏng)', ''),
        address = REPLACE(REPLACE(REPLACE(address, ' (Dữ liệu demo)', ''), ' (dữ liệu demo)', ''), ' (dữ liệu mô phỏng)', '')
    WHERE name LIKE '%demo%' OR name LIKE '%mô phỏng%' OR address LIKE '%demo%' OR address LIKE '%mô phỏng%'
");
$cleanSchools->execute();
echo " -> Cleaned schools name & address (" . $cleanSchools->rowCount() . " rows updated)\n";

// 3. Clean teacher_profiles
$cleanTeachers = $pdo->prepare("
    UPDATE teacher_profiles
    SET bio = REPLACE(REPLACE(REPLACE(bio, ' (Dữ liệu demo)', ''), ' (dữ liệu demo)', ''), ' (dữ liệu mô phỏng)', '')
    WHERE bio LIKE '%demo%' OR bio LIKE '%mô phỏng%'
");
$cleanTeachers->execute();
echo " -> Cleaned teacher_profiles bio (" . $cleanTeachers->rowCount() . " rows updated)\n";

// 4. Clean badges
$cleanBadges = $pdo->prepare("
    UPDATE badges
    SET name = REPLACE(REPLACE(REPLACE(name, ' (Dữ liệu demo)', ''), ' (dữ liệu demo)', ''), ' (dữ liệu mô phỏng)', ''),
        description = REPLACE(REPLACE(REPLACE(description, ' (Dữ liệu demo)', ''), ' (dữ liệu demo)', ''), ' (dữ liệu mô phỏng)', '')
    WHERE name LIKE '%demo%' OR description LIKE '%demo%' OR name LIKE '%mô phỏng%' OR description LIKE '%mô phỏng%'
");
$cleanBadges->execute();
echo " -> Cleaned badges name & description (" . $cleanBadges->rowCount() . " rows updated)\n\n";

// ----------------------------------------------------------------------
// TASK 4: Deduplicate Enterprises & Consolidate Foreign Keys
// ----------------------------------------------------------------------
echo "[Step 5] Deduplicating Enterprises and Consolidating Relationships...\n";

// Canonical FPT Software enterprise ID:
$canonicalFptId = '10000000-0000-4000-8000-000000000003';
$duplicateFptIds = [
    '31000000-0000-4000-8000-000000000015',
    '32000000-0000-4000-8000-000000000005',
];

// Update canonical FPT Software details to be complete
$pdo->prepare("
    UPDATE enterprises
    SET name = 'Công ty TNHH Phần mềm FPT (FPT Software)',
        industry = 'Công nghệ thông tin',
        description = 'FPT Software là công ty thành viên thuộc Tập đoàn FPT, nhà cung cấp dịch vụ công nghệ thông tin và chuyển đổi số hàng đầu tại Việt Nam và toàn cầu.',
        address = 'Tòa nhà FPT, Phố Duy Tân, Phường Dịch Vọng Hậu, Quận Cầu Giấy, TP. Hà Nội',
        phone = '024-7300-7300',
        website = 'https://fptsoftware.com',
        verificationStatus = 'verified',
        status = 'active',
        updatedAt = NOW(6)
    WHERE id = ?
")->execute([$canonicalFptId]);

foreach ($duplicateFptIds as $dupId) {
    // 1. Move internship posts to canonical ID
    $pdo->prepare("UPDATE internship_posts SET enterpriseId = ? WHERE enterpriseId = ?")->execute([$canonicalFptId, $dupId]);

    // 2. Move enterprise_members to canonical ID (prevent duplicates)
    $members = $pdo->prepare("SELECT id, userId FROM enterprise_members WHERE enterpriseId = ?");
    $members->execute([$dupId]);
    foreach ($members->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $chkMem = $pdo->prepare("SELECT id FROM enterprise_members WHERE enterpriseId = ? AND userId = ?");
        $chkMem->execute([$canonicalFptId, $m['userId']]);
        if ($chkMem->fetchColumn()) {
            $pdo->prepare("DELETE FROM enterprise_members WHERE id = ?")->execute([$m['id']]);
        } else {
            $pdo->prepare("UPDATE enterprise_members SET enterpriseId = ? WHERE id = ?")->execute([$canonicalFptId, $m['id']]);
        }
    }

    // 3. Move school_enterprise_partnerships to canonical ID (prevent duplicates)
    $partnerships = $pdo->prepare("SELECT id, schoolId FROM school_enterprise_partnerships WHERE enterpriseId = ?");
    $partnerships->execute([$dupId]);
    foreach ($partnerships->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $chkPart = $pdo->prepare("SELECT id FROM school_enterprise_partnerships WHERE enterpriseId = ? AND schoolId = ?");
        $chkPart->execute([$canonicalFptId, $p['schoolId']]);
        if ($chkPart->fetchColumn()) {
            $pdo->prepare("DELETE FROM school_enterprise_partnerships WHERE id = ?")->execute([$p['id']]);
        } else {
            $pdo->prepare("UPDATE school_enterprise_partnerships SET enterpriseId = ? WHERE id = ?")->execute([$canonicalFptId, $p['id']]);
        }
    }

    // 4. Delete duplicate enterprise row
    $pdo->prepare("DELETE FROM enterprises WHERE id = ?")->execute([$dupId]);
    echo " -> Merged duplicate FPT Software enterprise {$dupId} into canonical {$canonicalFptId}\n";
}

// Clean duplicate MB Bank enterprise rows if any
$mbBanks = $pdo->query("SELECT id, name FROM enterprises WHERE name LIKE '%MB Bank%' OR name LIKE '%Quân đội%'")->fetchAll(PDO::FETCH_ASSOC);
if (count($mbBanks) > 1) {
    $canonicalMbId = $mbBanks[0]['id'];
    for ($i = 1; $i < count($mbBanks); $i++) {
        $dupMbId = $mbBanks[$i]['id'];
        $pdo->prepare("UPDATE internship_posts SET enterpriseId = ? WHERE enterpriseId = ?")->execute([$canonicalMbId, $dupMbId]);
        $pdo->prepare("UPDATE enterprise_members SET enterpriseId = ? WHERE enterpriseId = ?")->execute([$canonicalMbId, $dupMbId]);
        $pdo->prepare("UPDATE school_enterprise_partnerships SET enterpriseId = ? WHERE enterpriseId = ?")->execute([$canonicalMbId, $dupMbId]);
        $pdo->prepare("DELETE FROM enterprises WHERE id = ?")->execute([$dupMbId]);
        echo " -> Merged duplicate MB Bank enterprise {$dupMbId} into {$canonicalMbId}\n";
    }
}

echo " -> Enterprise deduplication and relationship consolidation complete!\n\n";

echo "======================================================================\n";
echo " ALL DATABASE UPDATES COMPLETED SUCCESSFULLY!\n";
echo "======================================================================\n";
