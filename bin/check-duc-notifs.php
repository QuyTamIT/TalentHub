<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$ducUserId = '24000000-0000-4000-8000-000000000012';
$rows = $pdo->query("SELECT id, notificationType, title, deepLink, readAt, createdAt FROM notifications WHERE userId = '{$ducUserId}' ORDER BY createdAt DESC")->fetchAll(PDO::FETCH_ASSOC);

echo "Trần Minh Đức notifications count: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "ID: {$r['id']} | Read: " . ($r['readAt'] ?: 'UNREAD') . " | Title: {$r['title']} | Created: {$r['createdAt']}\n";
}
