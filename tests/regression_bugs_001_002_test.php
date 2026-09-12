<?php

declare(strict_types=1);

/**
 * Regression test for:
 * - BUG-001: app/learner/ecosystem.php syntax and execution
 * - BUG-002: projects.topic column and SchoolProjectRepository createProject
 */

require_once __DIR__ . '/../bin/bootstrap.php';

$config = require __DIR__ . '/../config/database.php';
$pdo = (new \TalentHub\Database\Connection($config))->connect();

$failures = [];

echo "=== Running Regression Tests for BUG-001 & BUG-002 ===\n\n";

// Test 1: Syntax check of app/learner/ecosystem.php
echo "[Test 1] PHP Lint check of app/learner/ecosystem.php...\n";
$lintOutput = [];
$returnVar = 0;
exec('D:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe -l ' . escapeshellarg(dirname(__DIR__) . '/app/learner/ecosystem.php'), $lintOutput, $returnVar);
if ($returnVar !== 0) {
    $msg = "Lint failed: " . implode("\n", $lintOutput);
    echo "  FAILED: $msg\n";
    $failures[] = $msg;
} else {
    echo "  PASSED: Syntax valid.\n";
}

// Test 2: Check topic column in projects table
echo "\n[Test 2] Checking 'topic' column existence in table 'projects'...\n";
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'projects' 
      AND column_name = 'topic'
");
$stmt->execute();
$hasTopic = (int) $stmt->fetchColumn() === 1;
if (!$hasTopic) {
    $msg = "Column 'topic' does not exist in 'projects' table.";
    echo "  FAILED: $msg\n";
    $failures[] = $msg;
} else {
    echo "  PASSED: Column 'topic' exists in 'projects'.\n";
}

// Test 3: Verify SchoolProjectRepository insertion with topic if column exists
if ($hasTopic) {
    echo "\n[Test 3] Testing SchoolProjectRepository createProject with topic...\n";
    try {
        $schoolRepo = new \TalentHub\Modules\School\Repository\SchoolProjectRepository($pdo);
        // Find a school admin user
        $adminStmt = $pdo->query("SELECT u.id, sm.schoolId as school_id FROM users u JOIN school_members sm ON sm.userId = u.id WHERE u.email = 'fpt.admin@talenthub.vn' LIMIT 1");
        $schoolRow = $adminStmt->fetch(PDO::FETCH_ASSOC);
        if ($schoolRow) {
            $created = $schoolRepo->createProject(
                userId: $schoolRow['id'],
                schoolId: $schoolRow['school_id'],
                input: [
                    'title' => 'Regression Test Project ' . uniqid(),
                    'category' => 'technology',
                    'topic' => 'AI and Education Integration',
                    'description' => 'Test project to verify topic column persistence.',
                    'fundingGoal' => 1000000,
                    'status' => 'in_progress'
                ],
                requestId: 'REQ-REGRESSION-' . uniqid()
            );
            if (!empty($created['id']) && ($created['topic'] ?? '') === 'AI and Education Integration') {
                echo "  PASSED: Project created with topic successfully (ID: {$created['id']})\n";
            } else {
                $msg = "Project created but topic did not match. Result: " . json_encode($created);
                echo "  FAILED: $msg\n";
                $failures[] = $msg;
            }
        } else {
            echo "  SKIPPED: No school admin user found to test insertion.\n";
        }
    } catch (\Throwable $e) {
        $msg = "Exception creating project: " . $e->getMessage();
        echo "  FAILED: $msg\n";
        $failures[] = $msg;
    }
}

echo "\n=======================================================\n";
if (empty($failures)) {
    echo "ALL REGRESSION TESTS PASSED!\n";
    exit(0);
} else {
    echo "REGRESSION FAILURES DETECTED: " . count($failures) . " failure(s)\n";
    exit(1);
}
