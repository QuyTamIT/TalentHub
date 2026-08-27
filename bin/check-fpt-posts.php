<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$fptUser = $pdo->query("SELECT id, fullName, email FROM users WHERE email = 'fpt@talenthub.local'")->fetch(PDO::FETCH_ASSOC);
$entMember = $pdo->query("SELECT * FROM enterprise_members WHERE userId = '{$fptUser['id']}'")->fetch(PDO::FETCH_ASSOC);
echo "FPT Enterprise ID: {$entMember['enterpriseId']}\n";

$posts = $pdo->query("SELECT id, title, enterpriseId, status FROM internship_posts WHERE enterpriseId = '{$entMember['enterpriseId']}'")->fetchAll(PDO::FETCH_ASSOC);
print_r($posts);
