<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$rows = $pdo->query("SELECT * FROM school_enterprise_partnerships")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

$enterprises = $pdo->query("SELECT id, name FROM enterprises")->fetchAll(PDO::FETCH_ASSOC);
print_r($enterprises);
