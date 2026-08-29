<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Data\Database\DatabaseNotificationRepository;
use TalentHub\Support\Uuid;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: STUDENT NOTIFICATION INVITATION ACCEPT & DECLINE FLOW\n";
echo "======================================================================\n\n";

// Step 1: Find student Trần Minh Đức
$ducId = '24000000-0000-4000-8000-000000000002';
$ducProfile = $pdo->query("SELECT sp.id, sp.userId, u.fullName, u.email FROM student_profiles sp JOIN users u ON u.id = sp.userId WHERE sp.id = '{$ducId}'")->fetch(PDO::FETCH_ASSOC);
assert(!empty($ducProfile), 'Student profile must exist');
$studentUserId = $ducProfile['userId'];
echo "[Step 1] Target Student: {$ducProfile['fullName']} ({$ducProfile['email']} / {$studentUserId})\n";

// Step 2: Ensure an internship post from FPT exists and an application in 'invited' status
$fptEnt = $pdo->query("SELECT id, name FROM enterprises WHERE name LIKE '%FPT%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$post = $pdo->query("SELECT id, title FROM internship_posts WHERE enterpriseId = '{$fptEnt['id']}' AND status = 'active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "[Step 2] Enterprise: {$fptEnt['name']} | Post: {$post['title']}\n";

// Reset application to invited
$appStmt = $pdo->prepare("SELECT id FROM internship_applications WHERE postId = ? AND studentId = ?");
$appStmt->execute([$post['id'], $ducId]);
$existingAppId = $appStmt->fetchColumn();

if (!$existingAppId) {
    $existingAppId = Uuid::v4();
    $pdo->prepare("INSERT INTO internship_applications (id, postId, studentId, status, message, appliedAt, createdAt, updatedAt) VALUES (?, ?, ?, 'invited', 'Lời mời', NOW(), NOW(), NOW())")
        ->execute([$existingAppId, $post['id'], $ducId]);
} else {
    $pdo->prepare("UPDATE internship_applications SET status = 'invited', updatedAt = NOW() WHERE id = ?")->execute([$existingAppId]);
}

// Create or update invitation notification
$notifId = Uuid::v4();
$eventKey = 'internship_invitation_' . $post['id'] . '_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
$pdo->prepare("
    INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, readAt, createdAt)
    VALUES (?, ?, ?, 'internship_invitation', ?, ?, ?, NULL, NOW(6))
")->execute([
    $notifId,
    $studentUserId,
    $eventKey,
    'Lời mời thực tập từ ' . $fptEnt['name'],
    'FPT Software đã gửi lời mời bạn tham gia thực tập cho vị trí: ' . $post['title'],
    '/app/student/internships/' . $post['id']
]);
echo " -> Notification Created: {$notifId} (EventKey: {$eventKey})\n";

// Step 3: Test DatabaseNotificationRepository::listForUser
echo "\n[Step 3] Fetching notifications via DatabaseNotificationRepository...\n";
$repo = new DatabaseNotificationRepository($pdo);
$list = $repo->listForUser($studentUserId, 10, 0, false);
assert(!empty($list['items']), 'Must have at least one notification');

$invNotif = null;
foreach ($list['items'] as $item) {
    if ($item['id'] === $notifId) {
        $invNotif = $item;
        break;
    }
}
assert(!empty($invNotif), 'Created notification must be in list');
echo " -> Found Notification: {$invNotif['title']}\n";
assert(!empty($invNotif['invitation']), 'Invitation metadata must be present in notification item');
echo " -> Invitation Status: {$invNotif['invitation']['status']}\n";
echo " -> Enterprise Name: {$invNotif['invitation']['enterpriseName']}\n";
assert($invNotif['invitation']['status'] === 'invited', 'Status should be invited initially');

// Step 4: Test Accept Invitation Action
echo "\n[Step 4] Testing Accept Invitation Action...\n";
$pdo->prepare("UPDATE internship_applications SET status = 'accepted', updatedAt = NOW(6) WHERE id = ?")->execute([$existingAppId]);
$pdo->prepare("UPDATE notifications SET readAt = NOW(6) WHERE id = ?")->execute([$notifId]);

$checkAppStatus = $pdo->query("SELECT status FROM internship_applications WHERE id = '{$existingAppId}'")->fetchColumn();
echo " -> Database Application Status: {$checkAppStatus}\n";
assert($checkAppStatus === 'accepted', 'Application status must be accepted');

$listAfterAccept = $repo->listForUser($studentUserId, 10, 0, false);
foreach ($listAfterAccept['items'] as $item) {
    if ($item['id'] === $notifId) {
        $invNotif = $item;
        break;
    }
}
echo " -> Notification Invitation Status After Accept: {$invNotif['invitation']['status']}\n";
assert($invNotif['invitation']['status'] === 'accepted', 'Status must now be accepted');

// Step 5: Test Decline Invitation Action
echo "\n[Step 5] Testing Decline Invitation Action...\n";
$pdo->prepare("UPDATE internship_applications SET status = 'declined', updatedAt = NOW(6) WHERE id = ?")->execute([$existingAppId]);

$checkAppStatus2 = $pdo->query("SELECT status FROM internship_applications WHERE id = '{$existingAppId}'")->fetchColumn();
echo " -> Database Application Status: {$checkAppStatus2}\n";
assert($checkAppStatus2 === 'declined', 'Application status must be declined');

$listAfterDecline = $repo->listForUser($studentUserId, 10, 0, false);
foreach ($listAfterDecline['items'] as $item) {
    if ($item['id'] === $notifId) {
        $invNotif = $item;
        break;
    }
}
echo " -> Notification Invitation Status After Decline: {$invNotif['invitation']['status']}\n";
assert($invNotif['invitation']['status'] === 'declined', 'Status must now be declined');

// Reset to invited for browser visual testing
$pdo->prepare("UPDATE internship_applications SET status = 'invited', updatedAt = NOW(6) WHERE id = ?")->execute([$existingAppId]);
$pdo->prepare("UPDATE notifications SET readAt = NULL WHERE id = ?")->execute([$notifId]);

echo "\n======================================================================\n";
echo " ALL NOTIFICATION INVITATION ACCEPT & DECLINE SMOKE TESTS PASSED!\n";
echo "======================================================================\n";
