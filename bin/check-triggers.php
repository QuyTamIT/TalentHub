<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$triggers = $pdo->query("SHOW TRIGGERS")->fetchAll(PDO::FETCH_ASSOC);
echo "Triggers in database (" . count($triggers) . "):\n";
foreach ($triggers as $trg) {
    echo " - Trigger: {$trg['Trigger']} | Table: {$trg['Table']} | Event: {$trg['Event']} | Timing: {$trg['Timing']}\n";
}
