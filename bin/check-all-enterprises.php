<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$rows = $pdo->query("SELECT id, name, email FROM enterprises")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

$users = $pdo->query("SELECT id, fullName, email, role FROM users WHERE email LIKE '%fpt%' OR role = 'enterprise'")->fetchAll(PDO::FETCH_ASSOC);
print_r($users);
