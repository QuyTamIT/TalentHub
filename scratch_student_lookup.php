<?php
declare(strict_types=1);
require __DIR__ . '/bin/bootstrap.php';
$config = require __DIR__ . '/config/database.php';
$pdo = (new TalentHub\Database\Connection($config))->connect();
$statement = $pdo->prepare('SELECT sp.id, sp.userId, u.email, u.fullName FROM student_profiles sp LEFT JOIN users u ON u.id = sp.userId WHERE sp.id = :id OR u.fullName LIKE :name ORDER BY u.fullName');
$statement->execute(['id' => '22000000-53d8-4897-8d68-ab3f78db0ce9', 'name' => '%Minh Khoa%']);
echo json_encode($statement->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE), PHP_EOL;
