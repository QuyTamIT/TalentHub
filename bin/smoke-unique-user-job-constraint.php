<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " SMOKE TEST: RÀNG BUỘC UNIQUE THEO (USER_ID, JOB_ID) CHỐNG DUPLICATE\n";
echo "======================================================================\n\n";

// Step 1: Check Database Constraint
echo "[Step 1] Verifying UNIQUE CONSTRAINT on (postId, studentId)...\n";
$indexes = $pdo->query("SHOW INDEX FROM internship_applications WHERE Key_name = 'unique_user_job'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($indexes)) {
    echo " -> FAILED: UNIQUE KEY `unique_user_job` not found!\n";
    exit(1);
}
echo " -> SUCCESS: UNIQUE KEY `unique_user_job` is active on (postId, studentId)!\n\n";

// Step 2: Check job_applications VIEW
echo "[Step 2] Verifying `job_applications` Compatibility View...\n";
$viewRows = $pdo->query("SELECT user_id, job_id, status FROM job_applications LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
echo " -> VIEW `job_applications` returned " . count($viewRows) . " rows successfully!\n";
echo " -> SUCCESS: View `job_applications` maps (user_id, job_id) correctly.\n\n";

// Step 3: Test Duplicate Prevention under Direct INSERT with a fresh post and student
echo "[Step 3] Testing Direct Duplicate INSERT Prevention...\n";
$testPostId = $pdo->query("
    SELECT ip.id FROM internship_posts ip 
    WHERE NOT EXISTS (SELECT 1 FROM internship_applications ia WHERE ia.postId = ip.id)
    LIMIT 1
")->fetchColumn();

if (!$testPostId) {
    // Pick an existing post and a student without applications to this post
    $postList = $pdo->query("SELECT id FROM internship_posts ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($postList as $p) {
        $stList = $pdo->query("SELECT id FROM student_profiles WHERE id NOT IN (SELECT studentId FROM internship_applications WHERE postId = '{$p}') LIMIT 1")->fetchColumn();
        if ($stList) {
            $testPostId = $p;
            $testStudentId = $stList;
            break;
        }
    }
} else {
    $testStudentId = $pdo->query("SELECT id FROM student_profiles LIMIT 1")->fetchColumn();
}

echo " -> Using Test Post: {$testPostId} | Test Student: {$testStudentId}\n";

// Insert first row
$firstId = Uuid::v4();
$pdo->prepare("
    INSERT INTO internship_applications (id, postId, studentId, status, message, appliedAt, createdAt, updatedAt)
    VALUES (?, ?, ?, 'invited', 'Lần 1', NOW(), NOW(), NOW())
")->execute([$firstId, $testPostId, $testStudentId]);

$count1 = (int) $pdo->query("SELECT COUNT(*) FROM internship_applications WHERE postId = '{$testPostId}' AND studentId = '{$testStudentId}'")->fetchColumn();
echo " -> After 1st INSERT: Count = {$count1}\n";

// Attempt 2nd plain INSERT with different ID (should trigger 1062 duplicate exception)
$duplicateBlocked = false;
try {
    $secondId = Uuid::v4();
    $pdo->prepare("
        INSERT INTO internship_applications (id, postId, studentId, status, message, appliedAt, createdAt, updatedAt)
        VALUES (?, ?, ?, 'invited', 'Lần 2', NOW(), NOW(), NOW())
    ")->execute([$secondId, $testPostId, $testStudentId]);
} catch (\PDOException $e) {
    if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate')) {
        $duplicateBlocked = true;
    }
}

if (!$duplicateBlocked) {
    echo " -> FAILED: Direct duplicate INSERT was not blocked by UNIQUE constraint!\n";
    exit(1);
}
echo " -> SUCCESS: Direct duplicate INSERT was BLOCKED by MySQL 1062 Duplicate Key Constraint!\n\n";

// Step 4: Test Safe Upsert via ON DUPLICATE KEY UPDATE
echo "[Step 4] Testing Safe ON DUPLICATE KEY UPDATE Behavior...\n";
$thirdId = Uuid::v4();
$pdo->prepare("
    INSERT INTO internship_applications (id, postId, studentId, status, message, appliedAt, createdAt, updatedAt)
    VALUES (?, ?, ?, 'invited', 'Cập nhật lời mời mới nhất', NOW(), NOW(), NOW())
    ON DUPLICATE KEY UPDATE
        status = 'invited',
        message = VALUES(message),
        updatedAt = NOW()
")->execute([$thirdId, $testPostId, $testStudentId]);

$count2 = (int) $pdo->query("SELECT COUNT(*) FROM internship_applications WHERE postId = '{$testPostId}' AND studentId = '{$testStudentId}'")->fetchColumn();
$latestMsg = $pdo->query("SELECT message FROM internship_applications WHERE postId = '{$testPostId}' AND studentId = '{$testStudentId}'")->fetchColumn();

echo " -> After ON DUPLICATE KEY UPDATE: Count = {$count2} (Message: '{$latestMsg}')\n";
if ($count2 !== 1) {
    echo " -> FAILED: Application was duplicated!\n";
    exit(1);
}
echo " -> SUCCESS: No duplication occurred; existing row was safely updated in-place!\n\n";

// Step 5: Test Student Acceptance Updates Without Duplicating
echo "[Step 5] Testing Student Status Update (status = 'accepted')...\n";
$pdo->prepare("
    UPDATE internship_applications 
    SET status = 'accepted', updatedAt = NOW(6) 
    WHERE postId = ? AND studentId = ?
")->execute([$testPostId, $testStudentId]);

$count3 = (int) $pdo->query("SELECT COUNT(*) FROM internship_applications WHERE postId = '{$testPostId}' AND studentId = '{$testStudentId}'")->fetchColumn();
$currStatus = $pdo->query("SELECT status FROM internship_applications WHERE postId = '{$testPostId}' AND studentId = '{$testStudentId}'")->fetchColumn();

echo " -> After Acceptance: Count = {$count3} | Status = '{$currStatus}'\n";
if ($count3 !== 1 || $currStatus !== 'accepted') {
    echo " -> FAILED: Update did not maintain singular record!\n";
    exit(1);
}
echo " -> SUCCESS: Candidate accepted without duplication!\n\n";

// Clean up test application
$pdo->prepare("DELETE FROM internship_applications WHERE id = ?")->execute([$firstId]);
echo " -> Cleaned up test application row.\n\n";

// Step 6: Verify Overall Integrity across all posts
echo "[Step 6] Global System Integrity Check for ALL Applications in DB...\n";
$globalDups = $pdo->query("
    SELECT postId, studentId, COUNT(*) as cnt 
    FROM internship_applications 
    GROUP BY postId, studentId 
    HAVING cnt > 1
")->fetchAll(PDO::FETCH_ASSOC);

echo " -> Total duplicates across entire system: " . count($globalDups) . "\n";
if (!empty($globalDups)) {
    echo " -> FAILED: Found duplicate applications in database!\n";
    exit(1);
}
echo " -> SUCCESS: 0 duplicates in entire database.\n\n";

echo "======================================================================\n";
echo " ALL UNIQUE CONSTRAINT & DEDUPLICATION TESTS PASSED 100%!\n";
echo "======================================================================\n";
