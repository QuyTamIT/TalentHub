<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
echo "school_members:\n";
print_r($pdo->query('DESCRIBE school_members')->fetchAll(PDO::FETCH_ASSOC));
echo "enterprise_members:\n";
print_r($pdo->query('DESCRIBE enterprise_members')->fetchAll(PDO::FETCH_ASSOC));
echo "roles:\n";
print_r($pdo->query('SELECT * FROM roles')->fetchAll(PDO::FETCH_ASSOC));
