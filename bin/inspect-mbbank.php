<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "=== ENTERPRISES ===\n";
print_r($pdo->query('SELECT id, name, email, industry, address FROM enterprises')->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== USERS (enterprise) ===\n";
echo "\n=== ALL INTERNSHIP POSTS ===\n";
print_r($pdo->query("SELECT id, enterpriseId, title, status FROM internship_posts")->fetchAll(PDO::FETCH_ASSOC));



echo "\n=== USERS NAMED BAO ===\n";
print_r($pdo->query("
    SELECT u.id, u.fullName, u.email, sp.id AS studentId, s.name AS schoolName
    FROM users u
    LEFT JOIN student_profiles sp ON sp.userId = u.id
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    WHERE u.fullName LIKE '%Bảo%'
")->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== MAI LINH STUDENT SKILLS IN DB ===\n";
print_r($pdo->query("
    SELECT ss.*, sk.name as skillName
    FROM student_skills ss
    JOIN skills sk ON sk.id = ss.skillId
    WHERE ss.studentId = '23100000-0000-4000-8000-000000000008'
")->fetchAll(PDO::FETCH_ASSOC));


















