<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== 1. DESCRIBE internship_posts ===\n";
foreach ($pdo->query("DESCRIBE internship_posts")->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "{$col['Field']} ({$col['Type']}) - Null: {$col['Null']}\n";
}

echo "\n=== 2. INTERNSHIP POSTS FOR FPT SOFTWARE ===\n";
$fptPosts = $pdo->query("
    SELECT ip.id, ip.title, ip.enterpriseId, e.name as enterpriseName, ip.status, ip.createdAt
    FROM internship_posts ip
    JOIN enterprises e ON e.id = ip.enterpriseId
    WHERE e.name LIKE '%FPT%'
")->fetchAll(PDO::FETCH_ASSOC);
print_r($fptPosts);

echo "\n=== 5. CHECK CANDIDATES (Đức & Anh) ===\n";
$st = $pdo->query("
    SELECT sp.id, sp.userId, u.fullName, u.email, spd.headline, spd.bio,
           (SELECT GROUP_CONCAT(s.name SEPARATOR ', ') FROM student_skills ss JOIN skills s ON s.id=ss.skillId WHERE ss.studentId=sp.id) as skills
    FROM student_profiles sp
    JOIN users u ON u.id=sp.userId
    LEFT JOIN student_profile_details spd ON spd.studentId=sp.id
    WHERE u.fullName LIKE '%Trần Minh Đức%' OR u.fullName LIKE '%Võ Đức Anh%'
");
print_r($st->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== 7. TEST ENTERPRISE TALENT SERVICE GET TALENT ===\n";
$fptUser = $pdo->query("SELECT id FROM users WHERE email = 'fpt@talenthub.local'")->fetchColumn();
$talentRepo = new \TalentHub\Modules\Business\Repository\EnterpriseTalentRepository($pdo);
$talentService = new \TalentHub\Modules\Business\Service\EnterpriseTalentService($talentRepo);

try {
    $t1 = $talentService->getTalent($fptUser, '24000000-0000-4000-8000-000000000002');
    echo "[OK] Trần Minh Đức talentScore: " . $t1['talent_score'] . " | School: " . $t1['schoolName'] . " | Class: " . $t1['className'] . "\n";
    echo "     Projects count: " . count($t1['projects']) . "\n";
} catch (Throwable $e) {
    echo "[ERR Duc] " . $e->getMessage() . "\n";
}

try {
    $t2 = $talentService->getTalent($fptUser, 'a49dadc0-65f0-5862-a380-34c2d43ecbc6');
    echo "[OK] Võ Đức Anh talentScore: " . $t2['talent_score'] . " | School: " . $t2['schoolName'] . " | Class: " . $t2['className'] . "\n";
    echo "     Projects count: " . count($t2['projects']) . "\n";
} catch (Throwable $e) {
    echo "[ERR Anh] " . $e->getMessage() . "\n";
}

