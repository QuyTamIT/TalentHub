<?php
$env = parse_ini_file('.env');
$pdo = new PDO("mysql:host=" . $env['DB_HOST'] . ";dbname=" . $env['DB_DATABASE'], $env['DB_USERNAME'], $env['DB_PASSWORD']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->prepare("SELECT id, title, startAt, capacity, status FROM activities WHERE title LIKE '%Test Điểm danh QR 2026%'");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));

$stmt = $pdo->prepare("SELECT * FROM activity_registration_policies WHERE activityId = (SELECT id FROM activities WHERE title LIKE '%Test Điểm danh QR 2026%' LIMIT 1)");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));
