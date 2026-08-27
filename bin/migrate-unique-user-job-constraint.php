<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " MIGRATION: KHÓA RÀNG BUỘC UNIQUE (POST_ID, STUDENT_ID) / CHỐNG DUPLICATE\n";
echo "======================================================================\n\n";

// Step 1: Deduplication - Find any duplicate rows for (postId, studentId)
echo "[Step 1] Checking & Cleaning Duplicates in internship_applications...\n";

$dupGroups = $pdo->query("
    SELECT postId, studentId, COUNT(*) as cnt
    FROM internship_applications
    GROUP BY postId, studentId
    HAVING cnt > 1
")->fetchAll(PDO::FETCH_ASSOC);

if (!empty($dupGroups)) {
    echo " -> Found " . count($dupGroups) . " duplicate candidate-job pairs. Cleaning up...\n";
    foreach ($dupGroups as $g) {
        $pId = $g['postId'];
        $sId = $g['studentId'];

        // Get all rows for this pair, keeping accepted/latest first
        $rows = $pdo->query("
            SELECT id, status, updatedAt, createdAt
            FROM internship_applications
            WHERE postId = '{$pId}' AND studentId = '{$sId}'
            ORDER BY (status = 'accepted') DESC, updatedAt DESC, createdAt DESC
        ")->fetchAll(PDO::FETCH_ASSOC);

        $keepId = $rows[0]['id'];
        echo "   - (Post: {$pId}, Student: {$sId}) -> Keeping ID: {$keepId} (Status: {$rows[0]['status']})\n";

        for ($i = 1; $i < count($rows); $i++) {
            $delId = $rows[$i]['id'];
            // Clean child foreign keys if any
            $pdo->exec("DELETE FROM application_profile_snapshots WHERE applicationId = '{$delId}'");
            $pdo->exec("DELETE FROM application_status_history WHERE applicationId = '{$delId}'");
            $pdo->exec("DELETE FROM internship_mentor_assignments WHERE applicationId = '{$delId}'");
            $pdo->exec("DELETE FROM internship_applications WHERE id = '{$delId}'");
            echo "     * Deleted duplicate ID: {$delId}\n";
        }
    }
} else {
    echo " -> 0 duplicates found in internship_applications. Database is clean!\n";
}
echo "\n";

// Step 2: Ensure UNIQUE KEY unique_user_job (postId, studentId) exists
echo "[Step 2] Applying UNIQUE KEY unique_user_job on (postId, studentId)...\n";

$indexes = $pdo->query("SHOW INDEX FROM internship_applications WHERE Key_name = 'unique_user_job'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($indexes)) {
    $pdo->exec("ALTER TABLE internship_applications ADD UNIQUE KEY unique_user_job (postId, studentId)");
    echo " -> Created UNIQUE KEY `unique_user_job` on `internship_applications`(`postId`, `studentId`) successfully!\n";
} else {
    echo " -> UNIQUE KEY `unique_user_job` already exists on `internship_applications`.\n";
}

// Step 3: Create / Replace VIEW job_applications for compatibility
echo "\n[Step 3] Creating VIEW `job_applications` for (user_id, job_id) mapping...\n";
$pdo->exec("
    CREATE OR REPLACE VIEW job_applications AS 
    SELECT 
        id,
        studentId AS user_id,
        studentId AS candidate_id,
        studentId,
        postId AS job_id,
        postId,
        status,
        message,
        reviewerNote,
        reviewedAt,
        reviewedBy,
        appliedAt,
        createdAt,
        updatedAt
    FROM internship_applications
");
echo " -> VIEW `job_applications` created successfully!\n\n";

// Step 4: Verify All Indexes on internship_applications
echo "[Step 4] Verifying Final Indexes on `internship_applications`:\n";
$allIndexes = $pdo->query("SHOW INDEX FROM internship_applications")->fetchAll(PDO::FETCH_ASSOC);
$uniqueKeys = [];
foreach ($allIndexes as $idx) {
    if ($idx['Non_unique'] == 0) {
        $uniqueKeys[$idx['Key_name']][] = $idx['Column_name'];
    }
}
foreach ($uniqueKeys as $kName => $cols) {
    echo " - UNIQUE KEY `{$kName}`: (" . implode(', ', $cols) . ")\n";
}

echo "\n======================================================================\n";
echo " MIGRATION & RÀNG BUỘC UNIQUE THÀNH CÔNG 100%!\n";
echo "======================================================================\n";
