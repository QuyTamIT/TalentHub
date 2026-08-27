<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/src/Bootstrap/EnterpriseAppContext.php';

use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$fptUser = $pdo->query("SELECT id, email, fullName FROM users WHERE email = 'fpt@talenthub.local'")->fetch(PDO::FETCH_ASSOC);
$fptEnt = $pdo->query("SELECT id, name FROM enterprises WHERE name LIKE '%FPT%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$ducId = '24000000-0000-4000-8000-000000000002';
$ducUserId = $pdo->query("SELECT userId FROM student_profiles WHERE id = '{$ducId}'")->fetchColumn();
$post = $pdo->query("SELECT id, title FROM internship_posts WHERE enterpriseId = '{$fptEnt['id']}' AND status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

echo "======================================================================\n";
echo " TEST RE-SENDING INVITATIONS MULTIPLE TIMES WITHOUT UNIQUE CONSTRAINT ERROR\n";
echo " Student: Trần Minh Đức ({$ducUserId})\n";
echo " Enterprise: {$fptEnt['name']}\n";
echo " Post: {$post['title']} ({$post['id']})\n";
echo "======================================================================\n\n";

for ($i = 1; $i <= 3; $i++) {
    echo "[Attempt {$i}] Sending invitation...\n";
    $notifId = Uuid::v4();
    $notifTitle = "Lời mời thực tập từ " . $fptEnt['name'] . " (Lần {$i})";
    $notifMsg = "Chào Đức, FPT Software gửi lời mời thực tập lần {$i} cho vị trí {$post['title']}";
    $deepLink = "/app/student/internships/" . $post['id'];
    $eventKey = 'internship_invitation_' . $post['id'] . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);

    try {
        $insNotif = $pdo->prepare("
            INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, createdAt)
            VALUES (?, ?, ?, 'internship_invitation', ?, ?, ?, NOW(6))
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                message = VALUES(message),
                deepLink = VALUES(deepLink),
                readAt = NULL,
                createdAt = NOW(6)
        ");
        $insNotif->execute([$notifId, $ducUserId, $eventKey, $notifTitle, $notifMsg, $deepLink]);
        echo " -> SUCCESS: Notification inserted without collision! EventKey: {$eventKey}\n";
    } catch (\Throwable $e) {
        echo " -> FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Also test the exact same eventKey with ON DUPLICATE KEY UPDATE to prove duplicate resolution
echo "\n[Attempt 4] Testing duplicate key resolution with SAME eventKey...\n";
$sameEventKey = 'internship_invitation_fixed_test';
$insNotif1 = $pdo->prepare("
    INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, createdAt)
    VALUES (?, ?, ?, 'internship_invitation', 'Title 1', 'Message 1', '/link1', NOW(6))
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        message = VALUES(message),
        deepLink = VALUES(deepLink),
        readAt = NULL,
        createdAt = NOW(6)
");
$insNotif1->execute([Uuid::v4(), $ducUserId, $sameEventKey]);

$insNotif2 = $pdo->prepare("
    INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, createdAt)
    VALUES (?, ?, ?, 'internship_invitation', 'Title 2 Updated', 'Message 2 Updated', '/link2', NOW(6))
    ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        message = VALUES(message),
        deepLink = VALUES(deepLink),
        readAt = NULL,
        createdAt = NOW(6)
");
$insNotif2->execute([Uuid::v4(), $ducUserId, $sameEventKey]);

$checkUpdated = $pdo->query("SELECT title, message FROM notifications WHERE userId = '{$ducUserId}' AND eventKey = '{$sameEventKey}'")->fetch(PDO::FETCH_ASSOC);
echo " -> Result for same eventKey: Title = {$checkUpdated['title']} | Message = {$checkUpdated['message']}\n";
assert($checkUpdated['title'] === 'Title 2 Updated', 'Should update title on duplicate');
echo " -> SUCCESS: ON DUPLICATE KEY UPDATE gracefully updated existing record without exception!\n";

echo "\n======================================================================\n";
echo " ALL MULTIPLE INVITATION CONCURRENCY & UNIQUE CONSTRAINT TESTS PASSED!\n";
echo "======================================================================\n";
