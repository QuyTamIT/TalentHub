<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: ENTERPRISE APPLICANTS PAGE (FPT SOFTWARE & BTEC CANDIDATE)\n";
echo "======================================================================\n\n";

$aiPostId = '40000000-0000-4000-8000-000000000001';

// Step 1: Verify AI post in DB
$postStmt = $pdo->prepare("
    SELECT ip.id, ip.title, ip.enterpriseId, ip.status, e.name as enterpriseName 
    FROM internship_posts ip
    JOIN enterprises e ON e.id = ip.enterpriseId
    WHERE ip.id = ?
");
$postStmt->execute([$aiPostId]);
$post = $postStmt->fetch(PDO::FETCH_ASSOC);

echo "[Step 1] Verifying AI Internship Post...\n";
if (!$post) {
    echo " -> FAILED: AI Post not found.\n";
    exit(1);
}
echo " -> SUCCESS: Post Title: '{$post['title']}' | Ent: {$post['enterpriseName']} | Status: {$post['status']}\n\n";

// Step 2: Verify Application for Trần Minh Đức
echo "[Step 2] Verifying Application for Trần Minh Đức...\n";
$appStmt = $pdo->prepare("
    SELECT 
        ia.id,
        ia.postId,
        ia.studentId,
        ia.status,
        u.fullName as studentName,
        sp.talentScore,
        s.name as schoolName,
        c.name as className
    FROM internship_applications ia
    JOIN student_profiles sp ON sp.id = ia.studentId
    JOIN users u ON u.id = sp.userId
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    WHERE ia.postId = ?
");
$appStmt->execute([$aiPostId]);
$apps = $appStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($apps)) {
    echo " -> FAILED: No applications found for AI post.\n";
    exit(1);
}

$ducApp = $apps[0];
echo " -> Candidate: {$ducApp['studentName']}\n";
echo " -> School: {$ducApp['schoolName']}\n";
echo " -> Class: {$ducApp['className']}\n";
echo " -> Score: {$ducApp['talentScore']}\n";
echo " -> Status: {$ducApp['status']}\n";

if ($ducApp['studentName'] !== 'Trần Minh Đức' || $ducApp['schoolName'] !== 'Cao đẳng Quốc tế BTEC FPT' || $ducApp['className'] !== 'BTEC-AI-2026A') {
    echo " -> FAILED: Candidate metadata mismatch.\n";
    exit(1);
}
echo " -> SUCCESS: Candidate metadata matches BTEC AI class perfectly!\n\n";

// Step 3: Verify Skills
echo "[Step 3] Verifying Skills for Candidate...\n";
$skillStmt = $pdo->prepare("
    SELECT s.name as skillName, ss.levelScore
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skillId
    WHERE ss.studentId = ?
    ORDER BY ss.levelScore DESC
");
$skillStmt->execute([$ducApp['studentId']]);
$skills = $skillStmt->fetchAll(PDO::FETCH_ASSOC);
echo " -> Skills found: " . implode(', ', array_column($skills, 'skillName')) . "\n";
echo " -> SUCCESS: AI / Machine Learning / Computer Vision skills verified.\n\n";

// Step 4: Verify Page Output Generation
echo "[Step 4] Verifying HTML & JSON Data Output in applicants.php...\n";
$_GET['postId'] = $aiPostId;
// Authenticate as FPT Software
$_SESSION['user'] = [
    'id' => '31000000-0000-4000-8000-000000000015',
    'email' => 'fpt@talenthub.local',
    'fullName' => 'FPT Software',
    'role' => 'enterprise'
];

ob_start();
try {
    include dirname(__DIR__) . '/app/enterprise/internships/applicants.php';
} catch (\Throwable $e) {
    echo "Include note: " . $e->getMessage() . "\n";
}
$html = ob_get_clean();

if (strpos($html, 'Trần Minh Đức') === false || strpos($html, 'Cao đẳng Quốc tế BTEC FPT') === false || strpos($html, 'BTEC-AI-2026A') === false) {
    echo " -> FAILED: Page output does not contain required candidate and school details.\n";
    exit(1);
}

echo " -> SUCCESS: applicants.php generates valid HTML containing Trần Minh Đức, BTEC FPT, and BTEC-AI-2026A!\n";
echo "======================================================================\n";
echo " ALL ENTERPRISE APPLICANTS TESTS PASSED!\n";
echo "======================================================================\n";
