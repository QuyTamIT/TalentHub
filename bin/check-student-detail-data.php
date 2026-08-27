<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$rows = $pdo->query("
    SELECT sp.id, u.fullName, u.email, sp.phone, sp.dateOfBirth, sp.studyStatus,
           c.name as className, c.gradeLevel, s.name as schoolName,
           spd.headline, spd.bio
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    JOIN classes c ON c.id = sp.classId
    JOIN schools s ON s.id = c.schoolId
    LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
    WHERE s.name LIKE '%BTEC%' OR s.name LIKE '%Cần Thơ%'
    ORDER BY s.name, u.fullName
")->fetchAll(PDO::FETCH_ASSOC);

$studentIds = array_column($rows, 'id');
$in = implode(',', array_fill(0, count($studentIds), '?'));
$skillsStmt = $pdo->prepare("
    SELECT ss.studentId, s.name as skillName, ss.levelScore, ss.verificationStatus
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skillId
    WHERE ss.studentId IN ($in)
    ORDER BY ss.levelScore DESC
");
$skillsStmt->execute($studentIds);
$skillsMap = [];
foreach ($skillsStmt->fetchAll(PDO::FETCH_ASSOC) as $sRow) {
    $skillsMap[$sRow['studentId']][] = $sRow['skillName'];
}
echo "=== SKILLS MAP ===\n";
print_r($skillsMap);
