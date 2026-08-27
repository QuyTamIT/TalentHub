<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$stRows = $pdo->query("
    SELECT sp.id as studentId, sp.userId, u.fullName, u.email, c.name as className, s.name as schoolName, sp.talentScore
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    ORDER BY u.email
")->fetchAll(PDO::FETCH_ASSOC);

echo "Total Student Profiles (" . count($stRows) . "):\n";
foreach ($stRows as $r) {
    echo "ID: {$r['studentId']} | Email: " . str_pad($r['email'], 35) . " | Name: " . str_pad($r['fullName'], 25) . " | Class: " . str_pad($r['className'] ?? 'N/A', 15) . " | Score: " . ($r['talentScore'] ?? 'NULL') . "\n";
}
