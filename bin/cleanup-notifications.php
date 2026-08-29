<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$ducUserId = '24000000-0000-4000-8000-000000000012';
$ducStudentId = '24000000-0000-4000-8000-000000000002';

// 1. Find all internship invitation notifications for Trần Minh Đức
$stmt = $pdo->prepare("
    SELECT id, title, deepLink, createdAt 
    FROM notifications 
    WHERE userId = ? AND (notificationType = 'internship_invitation' OR title LIKE '%Lời mời thực tập%')
    ORDER BY createdAt DESC
");
$stmt->execute([$ducUserId]);
$invNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($invNotifs) . " invitation notifications for Trần Minh Đức.\n";

if (!empty($invNotifs)) {
    // Keep the first (newest) one, delete the rest
    $keepId = $invNotifs[0]['id'];
    echo "Keeping newest notification: {$keepId} - {$invNotifs[0]['title']}\n";

    // Standardize title and message for the kept notification
    $pdo->prepare("
        UPDATE notifications 
        SET title = 'Lời mời thực tập từ FPT Software',
            message = 'Công ty TNHH Phần mềm FPT (FPT Software) gửi lời mời bạn tham gia thực tập vị trí Frontend Developer.',
            readAt = NULL,
            createdAt = NOW(6)
        WHERE id = ?
    ")->execute([$keepId]);

    // Delete other invitation notifications for Duc
    $deleteStmt = $pdo->prepare("
        DELETE FROM notifications 
        WHERE userId = ? AND (notificationType = 'internship_invitation' OR title LIKE '%Lời mời thực tập%') AND id != ?
    ");
    $deleteStmt->execute([$ducUserId, $keepId]);
    echo "Deleted duplicate notifications.\n";
}

// Also delete non-invitation garbage / duplicate test records for Duc if any (or keep legitimate ones)
$pdo->prepare("DELETE FROM notifications WHERE userId = ? AND title = 'Title 2 Updated'")->execute([$ducUserId]);

// Reset application status to 'invited' for Duc
$pdo->prepare("
    UPDATE internship_applications 
    SET status = 'invited', updatedAt = NOW(6) 
    WHERE studentId = ?
")->execute([$ducStudentId]);

echo "Reset internship_applications status to 'invited'.\n";

// Show remaining notifications for Duc
$remaining = $pdo->query("SELECT id, title, readAt, createdAt FROM notifications WHERE userId = '{$ducUserId}' ORDER BY createdAt DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "\nRemaining notifications for Trần Minh Đức:\n";
foreach ($remaining as $r) {
    echo " - ID: {$r['id']} | Read: " . ($r['readAt'] ?: 'UNREAD') . " | Title: {$r['title']}\n";
}
