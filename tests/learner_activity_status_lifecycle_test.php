<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$studentId = '22000000-53d8-4897-8d68-ab3f78db0ce9';
learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => $studentId]);

require_once dirname(__DIR__) . '/app/learner/includes/activity-data.php';

$active = learner_activity_active_registrations($studentId);
$history = learner_activity_attendance_history($studentId);

echo "Active count: " . count($active) . "\n";
echo "History count: " . count($history) . "\n";

// Active registrations must contain future activities
$hasMusic = false;
$hasStartup = false;
$hasGreen = false;
foreach ($active as $act) {
    $title = (string) ($act['title'] ?? '');
    if (str_contains($title, 'Music Studio')) $hasMusic = true;
    if (str_contains($title, 'Startup Demo')) $hasStartup = true;
    if (str_contains($title, 'Green Campus')) $hasGreen = true;
}

if (!$hasMusic || !$hasStartup || !$hasGreen) {
    throw new RuntimeException("Test failed: All 3 September activities should be in active registrations! (Music: " . ($hasMusic ? 'Y' : 'N') . ", Startup: " . ($hasStartup ? 'Y' : 'N') . ", Green: " . ($hasGreen ? 'Y' : 'N') . ")");
}

// History must NOT contain future activities with zero checkin
foreach ($history as $h) {
    $title = (string) ($h['title'] ?? '');
    if (str_contains($title, 'Music Studio') || str_contains($title, 'Startup Demo') || str_contains($title, 'Green Campus')) {
        throw new RuntimeException("Test failed: Future activity {$title} should NOT be in history!");
    }
}

// History must contain the 2 attended activities (Digital Marketing and Hackathon)
$hasMarketing = false;
$hasHackathon = false;
foreach ($history as $h) {
    $title = (string) ($h['title'] ?? '');
    if (str_contains($title, 'Digital Marketing')) {
        $hasMarketing = true;
        if (($h['status'] ?? '') !== 'attended' || empty($h['checked_in_at']) || (float)($h['experience_hours'] ?? 0) <= 0) {
            throw new RuntimeException("Test failed: Digital Marketing should have status=attended, valid checked_in_at, and hours > 0");
        }
    }
    if (str_contains($title, 'Hackathon')) {
        $hasHackathon = true;
        if (($h['status'] ?? '') !== 'attended' || empty($h['checked_in_at']) || (float)($h['experience_hours'] ?? 0) <= 0) {
            throw new RuntimeException("Test failed: Hackathon should have status=attended, valid checked_in_at, and hours > 0");
        }
    }
}

if (!$hasMarketing || !$hasHackathon) {
    throw new RuntimeException("Test failed: Past confirmed activities must be present in history!");
}

echo "PASS: Lifecycle separation test passed successfully!\n";
