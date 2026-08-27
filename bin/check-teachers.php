<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$teachers = $pdo->query("SELECT tp.*, u.fullName, u.email FROM teacher_profiles tp JOIN users u ON u.id = tp.userId")->fetchAll(PDO::FETCH_ASSOC);
print_r($teachers);
