<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$u = $pdo->query("SELECT email, passwordHash FROM users WHERE id = '24000000-0000-4000-8000-000000000012'")->fetch(PDO::FETCH_ASSOC);
if (!password_verify('123456', $u['passwordHash'])) {
    $newHash = password_hash('123456', PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET passwordHash = ? WHERE id = '24000000-0000-4000-8000-000000000012'")->execute([$newHash]);
    echo "Updated student password to 123456 for " . $u['email'] . "\n";
} else {
    echo "Student password is already 123456 for " . $u['email'] . "\n";
}
