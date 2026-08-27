<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$fptUser = $pdo->query("SELECT id, fullName, email FROM users WHERE email = 'fpt@talenthub.local'")->fetch(PDO::FETCH_ASSOC);
echo "User ID: {$fptUser['id']}\n";

$memberships = $pdo->query("SELECT * FROM enterprise_members WHERE userId = '{$fptUser['id']}'")->fetchAll(PDO::FETCH_ASSOC);
print_r($memberships);
