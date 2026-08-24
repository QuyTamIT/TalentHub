<?php
require __DIR__ . '/bootstrap.php';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '');
$pdo->exec('DROP DATABASE IF EXISTS talenthub_test_api');
$pdo->exec('CREATE DATABASE talenthub_test_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
echo "OK" . PHP_EOL;
