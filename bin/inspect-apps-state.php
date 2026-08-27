<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "=== ENTERPRISES ===\n";
$ents = $pdo->query("SELECT id, name, email FROM enterprises")->fetchAll(PDO::FETCH_ASSOC);
print_r($ents);

echo "\n=== ENTERPRISE MEMBERS ===\n";
$members = $pdo->query("SELECT em.*, u.email as userEmail FROM enterprise_members em JOIN users u ON u.id = em.userId")->fetchAll(PDO::FETCH_ASSOC);
print_r($members);

echo "\n=== INTERNSHIP POSTS ===\n";
$posts = $pdo->query("SELECT id, enterpriseId, title, status FROM internship_posts")->fetchAll(PDO::FETCH_ASSOC);
print_r($posts);

echo "\n=== INTERNSHIP APPLICATIONS ===\n";
$apps = $pdo->query("SELECT ia.*, u.fullName, u.email FROM internship_applications ia JOIN student_profiles sp ON sp.id = ia.studentId JOIN users u ON u.id = sp.userId")->fetchAll(PDO::FETCH_ASSOC);
print_r($apps);
