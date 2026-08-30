<?php
require __DIR__ . '/bin/bootstrap.php';
$pdo = (new \TalentHub\Database\Connection(require __DIR__ . '/config/database.php'))->connect();
$stmt = $pdo->query("SHOW COLUMNS FROM activities");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['Field'] . " - " . $row['Type'] . PHP_EOL;
}
