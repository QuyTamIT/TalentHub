<?php
require __DIR__ . '/../bin/bootstrap.php';
$pdo = (new \TalentHub\Database\Connection(require __DIR__ . '/../config/database.php'))->connect();
print_r($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
