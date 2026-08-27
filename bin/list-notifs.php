<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$rows = $pdo->query("SELECT id, userId, notificationType, title, deepLink, readAt, createdAt FROM notifications ORDER BY createdAt DESC")->fetchAll(PDO::FETCH_ASSOC);

echo "Total notifications: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "ID: {$r['id']} | User: {$r['userId']} | Type: {$r['notificationType']} | Read: " . ($r['readAt'] ?: 'UNREAD') . " | Title: {$r['title']}\n";
}
