<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " KÍCH HOẠT ĐẦY ĐỦ ĐIỂM ĐÁNH GIÁ NĂNG LỰC: VŨ ĐỨC ANH\n";
echo "======================================================================\n\n";

$email = 'vuducanh@student.edu.vn';

// 1. Get Student & User ID
$st = $pdo->prepare("
    SELECT sp.id as studentId, sp.userId, u.fullName, c.id as classId, c.name as className, c.schoolId, s.name as schoolName
    FROM users u
    JOIN student_profiles sp ON sp.userId = u.id
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    WHERE u.email = ?
");
$st->execute([$email]);
$student = $st->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "ERROR: Student account {$email} not found!\n";
    exit(1);
}

$studentId = (string) $student['studentId'];
$userId = (string) $student['userId'];
$schoolId = (string) ($student['schoolId'] ?: 'da811c4f-2f74-4fdd-80b0-dd6f26109783');
$issuerUserId = '2b102e3b-9e3a-43fe-a7f2-2bad676bbf97'; // ThS. Nguyễn Văn Hùng / Ban Đào tạo BTEC

echo "[Step 1] Found Student: {$student['fullName']} (ID: {$studentId})\n";
echo " -> Class: {$student['className']} | School: {$student['schoolName']}\n\n";

// 2. Update Student Profile Score to 94.00
$updProfile = $pdo->prepare("
    UPDATE student_profiles 
    SET talentScore = 94.00, studyStatus = 'active', updatedAt = NOW() 
    WHERE id = ?
");
$updProfile->execute([$studentId]);
echo "[Step 2] Updated Student Profile talentScore = 94.00 (Điểm trung bình năng lực AI: 94 điểm)\n\n";

// 3. Update & Verify Skills with Exact Scores:
// langchain: 92/100, nlp: 95/100, prompt_engineering: 96/100, python: 94/100, pytorch: 93/100
echo "[Step 3] Updating Skill Assessment Scores...\n";

$skillScores = [
    'langchain' => ['name' => 'LangChain', 'score' => 92.00, 'id' => '80000000-0000-4000-8000-000000000052'],
    'nlp' => ['name' => 'NLP', 'score' => 95.00, 'id' => '80000000-0000-4000-8000-000000000051'],
    'prompt_engineering' => ['name' => 'Prompt Engineering', 'score' => 96.00, 'id' => '80000000-0000-4000-8000-000000000053'],
    'python' => ['name' => 'Python', 'score' => 94.00, 'id' => '22000000-952a-427e-8406-1ad950b1f892'],
    'pytorch' => ['name' => 'PyTorch', 'score' => 93.00, 'id' => 'fd427644-23fe-425d-aee1-80820baa4b76'],
    'ai_machine_learning' => ['name' => 'AI / Machine Learning', 'score' => 94.00, 'id' => '98e947c4-95ac-4c2a-8faa-f9360e49899b'],
];

$upsertSkill = $pdo->prepare("
    INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt)
    VALUES (:id, :code, :name, 'technical', 'active', NOW(), NOW())
    ON DUPLICATE KEY UPDATE name = VALUES(name), code = VALUES(code), category = 'technical', status = 'active', updatedAt = NOW()
");

$upsertStudentSkill = $pdo->prepare("
    INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, createdAt, updatedAt)
    VALUES (:id, :studentId, :skillId, :levelScore, 'teacher', 'verified', NOW(), NOW(), NOW())
    ON DUPLICATE KEY UPDATE levelScore = VALUES(levelScore), sourceType = 'teacher', verificationStatus = 'verified', verifiedAt = NOW(), updatedAt = NOW()
");

foreach ($skillScores as $code => $info) {
    $upsertSkill->execute([
        'id' => $info['id'],
        'code' => $code,
        'name' => $info['name'],
    ]);

    // Check if record exists
    $existingSs = $pdo->prepare("SELECT id FROM student_skills WHERE studentId = ? AND skillId = ?");
    $existingSs->execute([$studentId, $info['id']]);
    $ssId = $existingSs->fetchColumn();

    if ($ssId) {
        $updSs = $pdo->prepare("
            UPDATE student_skills 
            SET levelScore = ?, sourceType = 'teacher', verificationStatus = 'verified', verifiedAt = NOW(), updatedAt = NOW()
            WHERE id = ?
        ");
        $updSs->execute([$info['score'], $ssId]);
    } else {
        $upsertStudentSkill->execute([
            'id' => Uuid::v4(),
            'studentId' => $studentId,
            'skillId' => $info['id'],
            'levelScore' => $info['score']
        ]);
    }
    echo " -> {$info['name']} ({$code}): {$info['score']}/100 [VERIFIED]\n";
}
echo "\n";

// 4. Mark 4/4 Assessment Tests Complete (Holland, MBTI, DISC, Multiple Intelligence)
echo "[Step 4] Creating 4/4 Assessment Test Attempts & Results...\n";

// Find talent_tests by type
$delOldAttempts = $pdo->prepare("
    DELETE tr FROM test_results tr 
    JOIN test_attempts ta ON ta.id = tr.attemptId 
    WHERE ta.studentId = ?
");
$delOldAttempts->execute([$studentId]);
$pdo->prepare("DELETE FROM test_attempts WHERE studentId = ?")->execute([$studentId]);

$testInsertAttempt = $pdo->prepare("
    INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt, createdAt, updatedAt)
    VALUES (?, ?, ?, 'submitted', DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), NOW(), NOW())
");

$testInsertResult = $pdo->prepare("
    INSERT INTO test_results (id, attemptId, resultCode, summary, dimensionScoresJson, scoringVersion, createdAt)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");

// 4.1 Holland (IRC - Investigative / Realistic / Conventional)
$hollandTestId = $pdo->query("SELECT id FROM talent_tests WHERE type = 'holland' LIMIT 1")->fetchColumn();
if ($hollandTestId) {
    $attId = Uuid::v4();
    $testInsertAttempt->execute([$attId, $hollandTestId, $studentId]);
    $testInsertResult->execute([
        Uuid::v4(),
        $attId,
        'IRC',
        'Nhóm năng khiếu IRC nổi trội cho thấy tiềm năng vượt trội trong nghiên cứu Trí tuệ Nhân tạo, xử lý dữ liệu và phát triển ứng dụng LLM/NLP.',
        json_encode(['I' => 96, 'R' => 90, 'C' => 88, 'A' => 60, 'E' => 65, 'S' => 55]),
        'holland-riasec-1.0'
    ]);
    echo " -> [1/4] Holland RIASEC: IRC (Investigative 96, Realistic 90) [COMPLETED]\n";
}

// 4.2 MBTI (INTJ - The Architect / Nhà kiến tạo hệ thống)
$mbtiTestId = $pdo->query("SELECT id FROM talent_tests WHERE type = 'mbti' LIMIT 1")->fetchColumn();
if ($mbtiTestId) {
    $attId = Uuid::v4();
    $testInsertAttempt->execute([$attId, $mbtiTestId, $studentId]);
    $testInsertResult->execute([
        Uuid::v4(),
        $attId,
        'INTJ',
        'Kiểu tính cách INTJ (Nhà kiến tạo hệ thống) có tư duy chiến lược sâu sắc, khả năng lập trình logic và phân tích mô hình ngôn ngữ tự nhiên xuất sắc.',
        json_encode(['I' => 92, 'N' => 95, 'T' => 96, 'J' => 90]),
        'mbti-personality-1.0'
    ]);
    echo " -> [2/4] MBTI: INTJ (Tư duy kiến trúc hệ thống AI) [COMPLETED]\n";
}

// 4.3 DISC (CDIS - Conscientiousness / Dominance)
$discTestId = $pdo->query("SELECT id FROM talent_tests WHERE type = 'disc' LIMIT 1")->fetchColumn();
if ($discTestId) {
    $attId = Uuid::v4();
    $testInsertAttempt->execute([$attId, $discTestId, $studentId]);
    $testInsertResult->execute([
        Uuid::v4(),
        $attId,
        'CDIS',
        'Phong cách CDIS thể hiện tính chuẩn xác kỹ thuật cao, kiên trì tối ưu hóa prompt và thiết kế pipeline LangChain hiệu quả.',
        json_encode(['C' => 95, 'D' => 85, 'I' => 70, 'S' => 75]),
        'disc-education-1.0'
    ]);
    echo " -> [3/4] DISC: CDIS (Chuẩn xác, tối ưu hóa kỹ thuật) [COMPLETED]\n";
}

// 4.4 Multiple Intelligence (LOGI-LING-INTRA)
$miTestId = $pdo->query("SELECT id FROM talent_tests WHERE type = 'multiple_intelligence' LIMIT 1")->fetchColumn();
if ($miTestId) {
    $attId = Uuid::v4();
    $testInsertAttempt->execute([$attId, $miTestId, $studentId]);
    $testInsertResult->execute([
        Uuid::v4(),
        $attId,
        'LOGI-LING-INTRA',
        'Trí thông minh Logic-Toán học và Ngôn ngữ đạt điểm tối đa, cực kỳ thích hợp cho chuyên ngành Xử lý Ngôn ngữ Tự nhiên (NLP).',
        json_encode(['LOGI' => 98, 'LING' => 96, 'INTRA' => 90, 'SPAT' => 80, 'INTER' => 75, 'BODY' => 60, 'MUSIC' => 50, 'NAT' => 50]),
        'multiple-intelligence-1.0'
    ]);
    echo " -> [4/4] Multiple Intelligence: LOGI-LING-INTRA (Logic 98%, Ngôn ngữ 96%) [COMPLETED]\n\n";
}

// 5. Unlock Badges (Huy hiệu BTEC FPT & Hệ thống)
echo "[Step 5] Unlocking Badges for BTEC FPT Student...\n";
$btecBadges = $pdo->query("
    SELECT b.id, b.name, b.code, br.id as ruleId 
    FROM badges b 
    LEFT JOIN badge_rule_definitions br ON br.badgeId = b.id 
    WHERE b.schoolId = '{$schoolId}' OR b.schoolId IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

$insertRule = $pdo->prepare("
    INSERT INTO badge_rule_definitions (id, badgeId, ruleType, thresholdCriteria, version, isActive, createdAt, updatedAt)
    VALUES (?, ?, 'threshold', '{\"fact\": \"submitted_assessment_type_count\", \"value\": 4, \"operator\": \"gte\"}', 1, 1, NOW(), NOW())
");

$insertBadge = $pdo->prepare("
    INSERT INTO student_badges (id, studentId, badgeId, ruleDefinitionId, awardedAt, awardedBy, awardContext)
    VALUES (?, ?, ?, ?, NOW(), 'system', ?)
    ON DUPLICATE KEY UPDATE awardedAt = NOW()
");

$badgeCount = 0;
foreach ($btecBadges as $b) {
    $ruleId = $b['ruleId'];
    if (!$ruleId) {
        $ruleId = Uuid::v4();
        $insertRule->execute([$ruleId, $b['id']]);
    }

    // Check if already awarded
    $exists = $pdo->prepare("SELECT id FROM student_badges WHERE studentId = ? AND badgeId = ?");
    $exists->execute([$studentId, $b['id']]);
    if (!$exists->fetchColumn()) {
        $insertBadge->execute([
            Uuid::v4(),
            $studentId,
            $b['id'],
            $ruleId,
            json_encode([
                'fact' => 'submitted_assessment_type_count',
                'target' => 4,
                'current' => 4,
                'evaluatedAt' => date('c'),
                'ruleVersion' => 1,
                'ruleDefinitionId' => $ruleId
            ])
        ]);
        $badgeCount++;
        echo " -> Awarded Badge: {$b['name']} ({$b['code']})\n";
    }
}
echo "\n";

// 6. Issue Certificates (Chứng chỉ BTEC FPT)
echo "[Step 6] Issuing School Certificates from BTEC FPT...\n";
$btecCerts = $pdo->query("SELECT id, name, code FROM school_certificate_catalog WHERE schoolId = '{$schoolId}'")->fetchAll(PDO::FETCH_ASSOC);

$insertCert = $pdo->prepare("
    INSERT INTO student_school_certificates (id, studentId, certificateCatalogId, status, issuedAt, issuedBy, evidenceContext, createdAt, updatedAt)
    VALUES (?, ?, ?, 'issued', NOW(), ?, ?, NOW(), NOW())
    ON DUPLICATE KEY UPDATE status = 'issued', issuedAt = NOW(), updatedAt = NOW()
");

foreach ($btecCerts as $c) {
    $exists = $pdo->prepare("SELECT id FROM student_school_certificates WHERE studentId = ? AND certificateCatalogId = ?");
    $exists->execute([$studentId, $c['id']]);
    if (!$exists->fetchColumn()) {
        $insertCert->execute([
            Uuid::v4(),
            $studentId,
            $c['id'],
            $issuerUserId,
            json_encode([
                'averageScore' => 94.00,
                'evaluator' => 'Ban Đào tạo Cao đẳng Quốc tế BTEC FPT',
                'specialization' => 'Kỹ sư Trí tuệ Nhân tạo & Xử lý Ngôn ngữ Tự nhiên (NLP)'
            ])
        ]);
        echo " -> Issued Certificate: {$c['name']}\n";
    }
}
echo "\n";

// 7. Create Active AI Roadmap for Vũ Đức Anh
echo "[Step 7] Generating AI Career Roadmap...\n";
$hasRoadmap = $pdo->query("SELECT id FROM learner_ai_roadmaps WHERE studentId = '{$studentId}'")->fetchColumn();

if (!$hasRoadmap) {
    $snapshotId = Uuid::v4();
    $snapInsert = $pdo->prepare("
        INSERT INTO learner_recommendation_input_snapshots (
            id, studentId, schemaVersion, contentHash, consentScopesJson, qualityFlagsJson, payloadJson, sourceUpdatedAt, createdAt
        ) VALUES (
            ?, ?, 'learner-input-1.0.0', SHA2(NOW(), 256), ?, ?, ?, ?, NOW()
        )
    ");
    $snapInsert->execute([
        $snapshotId,
        $studentId,
        json_encode(['assessment', 'skills', 'activity', 'evaluation']),
        json_encode(['complete_assessment_set' => true, 'synthetic_demo' => true]),
        json_encode([
            'student' => ['id' => $studentId, 'major' => 'Trí tuệ Nhân tạo & NLP', 'school' => 'Cao đẳng Quốc tế BTEC FPT'],
            'assessments' => [
                ['test_code' => 'holland', 'result_code' => 'IRC'],
                ['test_code' => 'mbti', 'result_code' => 'INTJ'],
                ['test_code' => 'disc', 'result_code' => 'CDIS'],
                ['test_code' => 'multiple_intelligence', 'result_code' => 'LOGI-LING-INTRA'],
            ]
        ]),
        json_encode(['assessment' => date('Y-m-d H:i:s')])
    ]);

    $runId = Uuid::v4();
    $recRunInsert = $pdo->prepare("
        INSERT INTO learner_recommendation_runs (
            id, studentId, snapshotId, idempotencyKey, engineType, status,
            provider, modelVersion, promptVersion, startedAt, completedAt, createdAt
        ) VALUES (
            ?, ?, ?, ?, 'model', 'completed',
            'synthetic_demo', 'talenthub-persona-v1', 'learner-roadmap-prompt-1.2.0',
            NOW(), NOW(), NOW()
        )
    ");
    $recRunInsert->execute([$runId, $studentId, $snapshotId, 'btec-roadmap-' . bin2hex(random_bytes(8))]);

    $roadmapInsert = $pdo->prepare("
        INSERT INTO learner_ai_roadmaps (
            id, studentId, runId, versionNumber, contractVersion, status,
            executiveSummary, primaryDirectionJson, alternativeDirectionsJson, insightsJson,
            confidenceBand, evidenceSummaryJson, providerRequestId, responseHash,
            generatedAt, createdAt
        ) VALUES (
            ?, ?, ?, 1, 'learner-roadmap-1.0.0', 'active',
            ?, ?, ?, ?,
            'high', ?, 'btec-nlp-roadmap-vuducanh', SHA2(NOW(), 256),
            NOW(), NOW()
        )
    ");

    $roadmapInsert->execute([
        Uuid::v4(),
        $studentId,
        $runId,
        'Lộ trình 90 ngày dành cho Vũ Đức Anh tập trung vào Kỹ sư Trí tuệ Nhân tạo & Xử lý Ngôn ngữ Tự nhiên (NLP/LLM Engineer), dựa trên 4 bài đánh giá xuất sắc và bộ kỹ năng Python, LangChain, Prompt Engineering và PyTorch.',
        json_encode([
            'code' => 'ky_su_nlp_llm_engineer',
            'label' => 'Kỹ sư Trí tuệ Nhân tạo & Xử lý Ngôn ngữ Tự nhiên (NLP / LLM)',
            'rationale' => 'Điểm số vượt trội trong Prompt Engineering (96/100), NLP (95/100), Python (94/100), LangChain (92/100) và tổ hợp Logic-Ngôn ngữ tối ưu.'
        ]),
        json_encode([
            ['code' => 'ai_solution_architect', 'label' => 'Kiến trúc sư Giải pháp AI', 'rationale' => 'Tư duy hệ thống INTJ và năng lực giải quyết vấn đề phức tạp.'],
            ['code' => 'genai_application_developer', 'label' => 'Lập trình viên Ứng dụng Generative AI', 'rationale' => 'Thành thạo tích hợp LangChain, vector database và fine-tuning LLM.']
        ]),
        json_encode([
            ['category' => 'strength', 'title' => 'Tư duy Logic & Xử lý Ngôn ngữ Tự nhiên vượt trội', 'summary' => 'Nắm vững nguyên lý LLM, kỹ thuật Prompt Engineering nâng cao và xây dựng agent với LangChain.', 'evidence_ref_ids' => []],
            ['category' => 'potential', 'title' => 'Sẵn sàng tiếp nhận vị trí thực tập AI tại FPT Software', 'summary' => 'Hồ sơ năng lực đạt 94/100 điểm, thuộc Top 5% sinh viên xuất sắc chuyên ngành AI BTEC FPT.', 'evidence_ref_ids' => []]
        ]),
        json_encode(['assessment_count' => 4, 'complete_assessment_set' => true, 'synthetic_demo' => true])
    ]);
    echo " -> AI Career Roadmap Generated Successfully!\n\n";
} else {
    echo " -> AI Career Roadmap Already Active.\n\n";
}

echo "======================================================================\n";
echo " TỔNG HỢP TRẠNG THÁI NĂNG LỰC VŨ ĐỨC ANH:\n";
echo "======================================================================\n";
echo " - Điểm đánh giá năng lực AI: 94 điểm (talentScore = 94.00)\n";
echo " - Tiến độ đánh giá năng lực: 4/4 bài hoàn thành (100%)\n";
echo " - Kỹ năng chuyên môn:\n";
echo "    + Prompt Engineering: 96/100 [Verified]\n";
echo "    + NLP: 95/100 [Verified]\n";
echo "    + Python: 94/100 [Verified]\n";
echo "    + AI / Machine Learning: 94/100 [Verified]\n";
echo "    + PyTorch: 93/100 [Verified]\n";
echo "    + LangChain: 92/100 [Verified]\n";
echo " - Huy hiệu & Chứng chỉ: Đã mở khóa toàn bộ chứng chỉ BTEC FPT & Huy hiệu năng lực\n";
echo " - Sẵn sàng cho Doanh nghiệp (FPT Software) quét tuyển dụng & gửi lời mời thực tập!\n";
echo "======================================================================\n";
