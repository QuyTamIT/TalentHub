<?php
$env = parse_ini_file('.env');
$pdo = new PDO("mysql:host=" . $env['DB_HOST'] . ";dbname=" . $env['DB_DATABASE'], $env['DB_USERNAME'], $env['DB_PASSWORD']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->query("SHOW CREATE TABLE student_skills");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
