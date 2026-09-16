<?php
require __DIR__ . '/../bin/bootstrap.php';
use TalentHub\Database\Connection;

$config = require __DIR__ . '/../config/database.php';
$pdo = (new Connection($config))->connect();

$groups = $pdo->query('SELECT * FROM skill_groups')->fetchAll(PDO::FETCH_ASSOC);
echo "GROUPS:\n";
foreach ($groups as $g) {
    echo $g['code'] . ' => ' . $g['name'] . "\n";
}

$studentId = '81f79757-32ba-41ef-adc5-a87b0310fdfe';

$deleted = $pdo->exec("DELETE FROM skills WHERE code IN ('ai_ml', 'backend', 'business', 'data_bi', 'english', 'frontend', 'iot_embedded', 'marketing', 'physical', 'security', 'soft_skills', 'ui_ux')");
echo "CLEANED SKILLS TABLE, DELETED: $deleted\n";
echo "CURRENT SKILLS COUNT: " . $pdo->query('SELECT COUNT(*) FROM skills')->fetchColumn() . "\n";














