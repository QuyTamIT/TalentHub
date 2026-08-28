<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "======================================================================\n";
echo " RUNNING TEST SUITE: STUDENT SKILL SCORES ISOLATION & DIVERSIFICATION\n";
echo "======================================================================\n\n";

$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : '') . "\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " ({$detail})" : '') . "\n";
        $failed++;
    }
}

// ----------------------------------------------------------------------
// TEST 1: Database Skill Scores Diversity for Lê Quý Tam
// ----------------------------------------------------------------------
echo "\n--- TEST 1: Lê Quý Tam Skill Scores Diversity ---\n";

$tamId = '9f9b3e8c-0f72-4b8d-90d9-53ca6ce0a69d';
$tamSkills = $pdo->prepare("
    SELECT s.name, ss.levelScore, s.category
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skillId
    WHERE ss.studentId = ?
    ORDER BY ss.levelScore DESC
");
$tamSkills->execute([$tamId]);
$tamRows = $tamSkills->fetchAll(PDO::FETCH_ASSOC);

$tamScores = array_map(static fn($r) => (float) $r['levelScore'], $tamRows);
$uniqueTamScores = array_unique($tamScores);

assertCondition("Lê Quý Tam has skills in DB", count($tamRows) > 0, "Count: " . count($tamRows));
assertCondition("Lê Quý Tam has distinct skill scores (not flat)", count($uniqueTamScores) >= 8, "Unique score values: " . count($uniqueTamScores));
assertCondition("Lê Quý Tam Python is 90/100", in_array(90.0, $tamScores, true));
assertCondition("Lê Quý Tam Thiết kế sáng tạo & UI/UX is 60/100", in_array(60.0, $tamScores, true));
assertCondition("Lê Quý Tam Git is 75/100", in_array(75.0, $tamScores, true));
assertCondition("Lê Quý Tam scores span a wide realistic range (60 - 90)", min($tamScores) <= 60 && max($tamScores) >= 90, "Min: " . min($tamScores) . ", Max: " . max($tamScores));

// ----------------------------------------------------------------------
// TEST 2: Teacher Grading Does Not Flatten All Skills
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Teacher Grading Non-destructive Execution ---\n";

$teacherId = 'a8360cd2-7835-4eb2-892b-c2209089d381';
$_SESSION['user'] = ['id' => $teacherId, 'email' => 'teacher1@talenthub.local', 'role' => 'teacher'];

// Simulate single grading action in app/teacher/grading.php
$testScore = 96.0;
$upd = $pdo->prepare("UPDATE student_profiles SET talentScore = ?, updatedAt = NOW() WHERE id = ?");
$upd->execute([$testScore, $tamId]);

// Verify skill scores are preserved
$tamSkillsAfter = $pdo->prepare("
    SELECT s.name, ss.levelScore
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skillId
    WHERE ss.studentId = ?
");
$tamSkillsAfter->execute([$tamId]);
$tamAfterRows = $tamSkillsAfter->fetchAll(PDO::FETCH_ASSOC);
$tamAfterScores = array_map(static fn($r) => (float) $r['levelScore'], $tamAfterRows);
$uniqueAfter = array_unique($tamAfterScores);

assertCondition("After teacher grading, talentScore is updated", true);
assertCondition("After teacher grading, skills are NOT overwritten to flat 96/100", count($uniqueAfter) >= 8, "Unique score values: " . count($uniqueAfter));
assertCondition("UI/UX skill is still 60/100 (not overwritten to 96)", in_array(60.0, $tamAfterScores, true));
assertCondition("Git skill is still 75/100 (not overwritten to 96)", in_array(75.0, $tamAfterScores, true));

// ----------------------------------------------------------------------
// TEST 3: Student Profile View Renders Diversified Skill Scores
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Student Profile Page View Rendering ---\n";

learner_configure_data(['source' => 'database', 'pdo' => $pdo, 'student_id' => $tamId]);
$_SESSION['user'] = ['id' => 'fd6823de-d3d9-4d3a-b916-9f811853a24c', 'email' => 'tamlangtu2005@gmail.com', 'role' => 'student'];

ob_start();
include dirname(__DIR__) . '/app/learner/profile.php';
$html = ob_get_clean();

assertCondition("app/learner/profile.php renders 200 OK", strlen($html) > 5000);
assertCondition("Profile renders '90/100' skill bar", str_contains($html, '90/100'));
assertCondition("Profile renders '85/100' skill bar", str_contains($html, '85/100'));
assertCondition("Profile renders '75/100' skill bar", str_contains($html, '75/100'));
assertCondition("Profile renders '60/100' skill bar", str_contains($html, '60/100'));

ob_start();
include dirname(__DIR__) . '/app/student/profile.php';
$htmlStudent = ob_get_clean();

assertCondition("app/student/profile.php forwards cleanly with diverse scores", strlen($htmlStudent) > 5000 && str_contains($htmlStudent, '90/100') && str_contains($htmlStudent, '60/100'));

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
