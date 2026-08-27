<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$btecSchoolId = 'da811c4f-2f74-4fdd-80b0-dd6f26109783';
$enterprises = $pdo->query("SELECT id FROM enterprises")->fetchAll(PDO::FETCH_COLUMN);
$schoolUserId = $pdo->query("SELECT id FROM users WHERE email = 'school@talenthub.local' LIMIT 1")->fetchColumn();

foreach ($enterprises as $entId) {
    $exists = $pdo->query("SELECT id FROM school_enterprise_partnerships WHERE schoolId = '{$btecSchoolId}' AND enterpriseId = '{$entId}' LIMIT 1")->fetchColumn();
    if (!$exists) {
        $pId = TalentHub\Support\Uuid::v4();
        $pdo->prepare("
            INSERT INTO school_enterprise_partnerships (id, schoolId, enterpriseId, status, requestedByUserId, reviewedByUserId, reviewedAt, createdAt, updatedAt)
            VALUES (?, ?, ?, 'approved', ?, ?, NOW(), NOW(), NOW())
        ")->execute([$pId, $btecSchoolId, $entId, $schoolUserId, $schoolUserId]);
        echo "Created partnership for enterprise: {$entId}\n";
    }
}
echo "Partnerships verified.\n";
