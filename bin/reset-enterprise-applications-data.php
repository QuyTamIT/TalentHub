<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " CLEANUP: XÓA SẠCH ỨNG VIÊN, LỜI MỜI VÀ ĐƠN ỨNG TUYỂN (RESET VỀ 0)\n";
echo "======================================================================\n\n";

// 1. Delete dependent child tables
echo "[Step 1] Deleting dependent application snapshots and status histories...\n";
$pdo->exec("DELETE FROM application_profile_snapshots");
$pdo->exec("DELETE FROM application_status_history");
$pdo->exec("DELETE FROM internship_mentor_assignments");
echo " -> Deleted child records in snapshots, status history, mentor assignments.\n\n";

// 2. Delete all internship applications
echo "[Step 2] Deleting all applications in `internship_applications`...\n";
$delCount = $pdo->exec("DELETE FROM internship_applications");
echo " -> Deleted {$delCount} applications from `internship_applications`.\n\n";

// 3. Delete invitation/application notifications
echo "[Step 3] Deleting invitation and application notifications...\n";
$delNotifs = $pdo->exec("
    DELETE FROM notifications 
    WHERE notificationType LIKE '%internship%' 
       OR notificationType LIKE '%invitation%' 
       OR notificationType LIKE '%application%'
       OR eventKey LIKE '%internship%'
");
echo " -> Deleted {$delNotifs} notifications from `notifications`.\n\n";

// 4. Verify table counts
echo "[Step 4] Verifying Final Database Counts:\n";
$appCount = (int) $pdo->query("SELECT COUNT(*) FROM internship_applications")->fetchColumn();
$notifCount = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE notificationType LIKE '%internship%'")->fetchColumn();
$jobAppCount = (int) $pdo->query("SELECT COUNT(*) FROM job_applications")->fetchColumn();

echo " - `internship_applications` count: {$appCount}\n";
echo " - `job_applications` (view) count: {$jobAppCount}\n";
echo " - `notifications` (invitations) count: {$notifCount}\n\n";

if ($appCount === 0 && $jobAppCount === 0 && $notifCount === 0) {
    echo "======================================================================\n";
    echo " CLEANUP SUCCESS: DATABASE RESET VỀ 0 HOÀN TOÀN!\n";
    echo "======================================================================\n";
} else {
    echo "ERROR: Cleanup did not reach 0 records!\n";
    exit(1);
}
