<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
print_r($pdo->query('DESCRIBE student_profile_details')->fetchAll(PDO::FETCH_ASSOC));
