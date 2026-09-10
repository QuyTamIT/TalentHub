<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "Starting data remediation for student activity registrations...\n";

$updateStmt = $pdo->prepare("
    UPDATE activity_registrations ar
    INNER JOIN activities a ON a.id = ar.activityId
    SET ar.status = 'approved',
        ar.updatedAt = UTC_TIMESTAMP(6)
    WHERE ar.status = 'attended'
      AND a.endAt >= '2026-09-09 00:00:00'
      AND ar.id NOT IN (
          SELECT c.registrationId FROM checkins c WHERE c.status = 'confirmed'
      )
");
$updateStmt->execute();
$affected = $updateStmt->rowCount();
echo "Updated {$affected} registration(s) from 'attended' to 'approved'.\n";
