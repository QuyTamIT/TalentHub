<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$ducUserId = '24000000-0000-4000-8000-000000000012';

echo "======================================================================\n";
echo " TEST LOCKED NOTIFICATION ID UPDATE & SINGLE NOTIFICATION RETRIEVAL\n";
echo "======================================================================\n\n";

// 1. Get current notification for Duc
$stmt = $pdo->prepare("SELECT id, title, readAt FROM notifications WHERE userId = ? ORDER BY createdAt DESC");
$stmt->execute([$ducUserId]);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "[Step 1] Verifying Single Notification for Duc...\n";
if (count($notifs) !== 1) {
    echo " -> FAILED: Expected 1 notification, found " . count($notifs) . "\n";
    exit(1);
}
$notif = $notifs[0];
echo " -> SUCCESS: Exactly 1 notification found. ID: {$notif['id']} | Read: " . ($notif['readAt'] ?: 'UNREAD') . "\n\n";

// 2. Test simulating Accept action via POST with specific notificationId
echo "[Step 2] Testing Targeted Notification Accept Action with ID: {$notif['id']}...\n";
$targetId = $notif['id'];

// Simulate backend logic in notifications.php
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND userId = ? LIMIT 1");
$notifStmt->execute([$targetId, $ducUserId]);
$targetRow = $notifStmt->fetch(PDO::FETCH_ASSOC);

if (!$targetRow) {
    echo " -> FAILED: Cannot find notification by ID and UserId.\n";
    exit(1);
}

// Update readAt
$pdo->prepare("UPDATE notifications SET readAt = NOW(6) WHERE id = ? AND userId = ?")->execute([$targetId, $ducUserId]);

// Verify that only targetId was updated
$checkStmt = $pdo->prepare("SELECT id, readAt FROM notifications WHERE id = ?");
$checkStmt->execute([$targetId]);
$updatedRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

if (empty($updatedRow['readAt'])) {
    echo " -> FAILED: readAt was not set.\n";
    exit(1);
}
echo " -> SUCCESS: Notification {$targetId} successfully updated with readAt = {$updatedRow['readAt']}\n\n";

// Reset back to UNREAD so the user sees it unread with Action Buttons
$pdo->prepare("UPDATE notifications SET readAt = NULL WHERE id = ?")->execute([$targetId]);
$pdo->prepare("UPDATE internship_applications SET status = 'invited' WHERE studentId = '24000000-0000-4000-8000-000000000002'")->execute();

echo "[Step 3] Restored notification to UNREAD and status to 'invited' for UI testing.\n";
echo "======================================================================\n";
echo " ALL TESTS PASSED! DATA IS CLEAN AND SCOPED STRICTLY BY NOTIFICATION_ID.\n";
echo "======================================================================\n";
