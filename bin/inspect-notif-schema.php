<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$cols = $pdo->query("DESCRIBE notifications")->fetchAll(PDO::FETCH_ASSOC);
echo "Columns in notifications:\n";
foreach ($cols as $c) {
    echo " - " . $c['Field'] . " (" . $c['Type'] . ")\n";
}

$appCols = $pdo->query("DESCRIBE internship_applications")->fetchAll(PDO::FETCH_ASSOC);
echo "\nColumns in internship_applications:\n";
foreach ($appCols as $c) {
    echo " - " . $c['Field'] . " (" . $c['Type'] . ")\n";
}
