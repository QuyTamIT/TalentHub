<?php
require __DIR__ . '/bin/bootstrap.php';
$config = require __DIR__ . '/config/database.php';
$connection = new \TalentHub\Database\Connection($config);
$pdo = $connection->connect();
$stmt = $pdo->query('SHOW CREATE TABLE projects');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
$stmt2 = $pdo->query('SHOW CREATE TABLE project_members');
if ($stmt2) print_r($stmt2->fetch(PDO::FETCH_ASSOC));
