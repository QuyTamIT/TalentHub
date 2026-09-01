<?php
require 'vendor/autoload.php';
$config = require 'config/database.php';
$pdo = new PDO($config['dsn'], $config['username'], $config['password']);
$stmt = $pdo->query("DESCRIBE internship_applications");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
