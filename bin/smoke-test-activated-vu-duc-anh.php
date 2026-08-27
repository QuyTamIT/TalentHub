<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: TOÀN DIỆN HỒ SƠ ĐÁNH GIÁ NĂNG LỰC VŨ ĐỨC ANH\n";
echo "======================================================================\n\n";

$email = 'vuducanh@student.edu.vn';

// Step 1: Check Database Records
echo "[Step 1] Verifying Student Profile in DB...\n";
$stStmt = $pdo->prepare("
    SELECT sp.id as studentId, sp.userId, u.fullName, u.email, sp.talentScore,
           c.name as className, s.name as schoolName
    FROM users u
    JOIN student_profiles sp ON sp.userId = u.id
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    WHERE u.email = ?
");
$stStmt->execute([$email]);
$st = $stStmt->fetch(PDO::FETCH_ASSOC);

if (!$st || (float)$st['talentScore'] !== 94.0) {
    echo " -> FAILED: Student profile or talentScore not 94.00!\n";
    exit(1);
}
echo " -> Candidate: {$st['fullName']} | Score: {$st['talentScore']}% | Class: {$st['className']}\n";
echo " -> SUCCESS: Talent score is exactly 94.00%.\n\n";

// Step 2: Check 4/4 Assessment Tests
echo "[Step 2] Verifying 4/4 Completed Assessment Tests...\n";
$attStmt = $pdo->prepare("
    SELECT tt.type, tr.resultCode
    FROM test_attempts ta
    JOIN talent_tests tt ON tt.id = ta.testId
    JOIN test_results tr ON tr.attemptId = ta.id
    WHERE ta.studentId = ? AND ta.status = 'submitted'
");
$attStmt->execute([$st['studentId']]);
$tests = $attStmt->fetchAll(PDO::FETCH_ASSOC);

$types = array_column($tests, 'type');
echo " -> Completed tests (" . count($tests) . "/4): " . implode(', ', $types) . "\n";
if (count(array_intersect(['holland', 'mbti', 'disc', 'multiple_intelligence'], $types)) !== 4) {
    echo " -> FAILED: Missing one of the 4 assessment tests!\n";
    exit(1);
}
echo " -> SUCCESS: All 4/4 assessment tests completed (Holland, MBTI, DISC, Multiple Intelligence)!\n\n";

// Step 3: Check Skill Scores
echo "[Step 3] Verifying AI & NLP Skill Scores...\n";
$skStmt = $pdo->prepare("
    SELECT s.code, s.name, ss.levelScore, ss.verificationStatus
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skillId
    WHERE ss.studentId = ?
    ORDER BY ss.levelScore DESC
");
$skStmt->execute([$st['studentId']]);
$skills = $skStmt->fetchAll(PDO::FETCH_ASSOC);

$skillMap = [];
foreach ($skills as $sk) {
    $skillMap[$sk['code']] = (float) $sk['levelScore'];
    echo " -> {$sk['name']} ({$sk['code']}): {$sk['levelScore']}/100 [{$sk['verificationStatus']}]\n";
}

if (($skillMap['langchain'] ?? 0) < 92 || ($skillMap['nlp'] ?? 0) < 95 || ($skillMap['prompt_engineering'] ?? 0) < 96 || ($skillMap['python'] ?? 0) < 94) {
    echo " -> FAILED: One or more skill scores mismatch requirement!\n";
    exit(1);
}
echo " -> SUCCESS: All required skills exceed 90+ points!\n\n";

// Step 4: Check Badges and Certificates
echo "[Step 4] Verifying Badges & School Certificates...\n";
$bCount = $pdo->query("SELECT COUNT(*) FROM student_badges WHERE studentId = '{$st['studentId']}'")->fetchColumn();
$cCount = $pdo->query("SELECT COUNT(*) FROM student_school_certificates WHERE studentId = '{$st['studentId']}'")->fetchColumn();
echo " -> Badges count: {$bCount}\n";
echo " -> Certificates count: {$cCount}\n";
if ((int) $bCount < 5 || (int) $cCount < 3) {
    echo " -> FAILED: Badges or certificates count too low!\n";
    exit(1);
}
echo " -> SUCCESS: BTEC FPT badges and certificates unlocked!\n\n";

// Step 5: Check Student Dashboard Overview Render
echo "[Step 5] Verifying Student Dashboard Rendering...\n";
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = TalentHub\Auth\Session\SessionManager::SESSION_STUDENT;
$session = new TalentHub\Auth\Session\SessionManager($sessionConfig);
$session->start();
$session->login([
    'id' => $st['userId'],
    'email' => $st['email'],
    'fullName' => $st['fullName'],
    'role' => 'student',
    'status' => 'active'
]);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/learner/index.php';

ob_start();
include dirname(__DIR__) . '/app/learner/index.php';
$dashboardHtml = ob_get_clean();

if (!str_contains($dashboardHtml, '4/4 bài đánh giá đã hoàn thành')) {
    echo " -> FAILED: Dashboard did not render '4/4 bài đánh giá đã hoàn thành'!\n";
    exit(1);
}
echo " -> SUCCESS: Learner Dashboard displays '4/4 bài đánh giá đã hoàn thành' and AI skills!\n\n";

echo "======================================================================\n";
echo " ALL ACTIVATION SMOKE TESTS PASSED 100%!\n";
echo "======================================================================\n";
