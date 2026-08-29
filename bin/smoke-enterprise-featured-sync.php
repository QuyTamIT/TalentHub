<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: ĐỒNG BỘ ĐIỂM SỐ & KỸ NĂNG VÕ ĐỨC ANH (ENTERPRISE DASHBOARD)\n";
echo "======================================================================\n\n";

$studentId = 'a49dadc0-65f0-5862-a380-34c2d43ecbc6'; // Võ Đức Anh

// Step 1: Check Database Records
echo "[Step 1] Verifying Candidate Profile & Score in DB...\n";
$stStmt = $pdo->prepare("
    SELECT sp.id as studentId, sp.userId, u.fullName, u.email, sp.talentScore,
           c.name as className, s.name as schoolName
    FROM users u
    JOIN student_profiles sp ON sp.userId = u.id
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    WHERE sp.id = ?
");
$stStmt->execute([$studentId]);
$st = $stStmt->fetch(PDO::FETCH_ASSOC);

if (!$st || (float)$st['talentScore'] !== 94.0) {
    echo " -> FAILED: Candidate profile or talentScore not 94.00!\n";
    exit(1);
}
echo " -> Candidate: {$st['fullName']} | Score: {$st['talentScore']}% | Class: {$st['className']}\n";
echo " -> School: {$st['schoolName']}\n";
echo " -> SUCCESS: Talent score is exactly 94.00%.\n\n";

// Step 2: Check AI & NLP Skills in DB
echo "[Step 2] Verifying AI & NLP Skills in DB...\n";
$skStmt = $pdo->prepare("
    SELECT s.code, s.name, ss.levelScore, ss.verificationStatus
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skillId
    WHERE ss.studentId = ?
    ORDER BY 
        CASE 
            WHEN s.code = 'nlp' THEN 1
            WHEN s.code = 'langchain' THEN 2
            WHEN s.code = 'python' THEN 3
            WHEN s.code = 'pytorch' THEN 4
            ELSE 5
        END ASC
");
$skStmt->execute([$studentId]);
$skills = $skStmt->fetchAll(PDO::FETCH_ASSOC);

$skillNames = array_column($skills, 'name');
echo " -> Skills found (" . count($skills) . "): " . implode(', ', $skillNames) . "\n";
foreach ($skills as $sk) {
    echo "    + {$sk['name']}: {$sk['levelScore']}/100 [{$sk['verificationStatus']}]\n";
}

if (!in_array('NLP', $skillNames, true) || !in_array('LangChain', $skillNames, true) || !in_array('Python', $skillNames, true) || !in_array('PyTorch', $skillNames, true)) {
    echo " -> FAILED: Missing one of the required AI skills (NLP, LangChain, Python, PyTorch)!\n";
    exit(1);
}
echo " -> SUCCESS: All 4 AI skills (NLP, LangChain, Python, PyTorch) present!\n\n";

// Step 3: Check Enterprise Dashboard Featured Talents Rendering
echo "[Step 3] Verifying Enterprise Dashboard Rendering...\n";
$sessionConfig = require dirname(__DIR__) . '/config/session.php';
$sessionConfig['name'] = TalentHub\Auth\Session\SessionManager::SESSION_ENTERPRISE;
$session = new TalentHub\Auth\Session\SessionManager($sessionConfig);
$session->start();
$session->login([
    'id' => '31000000-0000-4000-8000-000000000015',
    'email' => 'fpt@talenthub.local',
    'fullName' => 'FPT Software',
    'role' => 'enterprise',
    'status' => 'active'
]);

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/app/enterprise/index.php';

ob_start();
include dirname(__DIR__) . '/app/enterprise/index.php';
$dashboardHtml = ob_get_clean();

// Locate Võ Đức Anh item in $featuredTalents
$found = null;
foreach ($featuredTalents as $t) {
    if ($t['name'] === 'Võ Đức Anh') {
        $found = $t;
        break;
    }
}

if (!$found) {
    echo " -> FAILED: Võ Đức Anh not found in \$featuredTalents list!\n";
    exit(1);
}

echo " -> Found Candidate in Featured Talents:\n";
echo "    + Name: {$found['name']}\n";
echo "    + Score: ★ {$found['talent_score']} điểm\n";
echo "    + Avatar: {$found['avatar_letter']} (Color: {$found['avatar_bg']})\n";
echo "    + Meta: {$found['meta_description']}\n";

if ($found['talent_score'] !== 94) {
    echo " -> FAILED: Score mismatch! Expected 94, got {$found['talent_score']}\n";
    exit(1);
}

$expectedMeta = 'BTEC-AI-2026A • Cao đẳng Quốc tế BTEC FPT • NLP, LangChain, Python, PyTorch';
if ($found['meta_description'] !== $expectedMeta) {
    echo " -> FAILED: Meta mismatch! Expected '{$expectedMeta}', got '{$found['meta_description']}'\n";
    exit(1);
}

echo " -> SUCCESS: All featured talent fields match required specs perfectly!\n\n";

echo "======================================================================\n";
echo " ALL SMOKE TESTS PASSED 100%!\n";
echo "======================================================================\n";
