<?php
require __DIR__ . '/bin/bootstrap.php';
$config = require __DIR__ . '/config/database.php';
$connection = new \TalentHub\Database\Connection($config);
$pdo = $connection->connect();
try {
    $pdo->exec('ALTER TABLE projects ADD COLUMN topic VARCHAR(255) DEFAULT NULL AFTER category');
    echo "Added topic column.\n";
} catch (Exception $e) {
    echo "Error or already exists: " . $e->getMessage() . "\n";
}
