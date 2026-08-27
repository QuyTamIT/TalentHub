<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
print_r($pdo->query('DESCRIBE users')->fetchAll(PDO::FETCH_ASSOC));
