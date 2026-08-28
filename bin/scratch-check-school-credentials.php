<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$rows = $pdo->query("SELECT brd.*, b.name, b.code FROM badge_rule_definitions brd JOIN badges b ON b.id = brd.badgeId")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID: {$r['id']} | Badge: {$r['name']} ({$r['code']}) | Criteria: {$r['thresholdCriteria']}\n";
}
