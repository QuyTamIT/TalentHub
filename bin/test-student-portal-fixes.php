<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

learner_configure_data(['source' => 'database', 'pdo' => $pdo]);

echo "======================================================================\n";
echo " RUNNING COMPREHENSIVE TEST SUITE: STUDENT PORTAL FIXES\n";
echo "======================================================================\n\n";

$studentEmail = 'tamlangtu2005@gmail.com';
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
// Test 1: Student Profile & Teacher Evaluation for Lê Quý Tam
// ----------------------------------------------------------------------
echo "--- TEST 1: Published Teacher Evaluation (85/100) ---\n";

$st = $pdo->prepare("
    SELECT sp.id as studentId, sp.userId, sp.talentScore, u.fullName
    FROM users u
    JOIN student_profiles sp ON sp.userId = u.id
    WHERE u.email = ?
");
$st->execute([$studentEmail]);
$student = $st->fetch(PDO::FETCH_ASSOC);

assertCondition("Student profile found for {$studentEmail}", (bool)$student, $student['fullName'] ?? '');
$studentId = (string)($student['studentId'] ?? '');

$repoFactory = learner_repository_factory();
$evaluations = $repoFactory->assessment()->publishedEvaluationsForStudent($studentId);

assertCondition("Published evaluations count > 0", count($evaluations) > 0, "Count: " . count($evaluations));

if (count($evaluations) > 0) {
    $latestEval = $evaluations[0];
    $scoreVal = (float)$latestEval['overall_score'];
    $classification = \TalentHub\Support\GradeClassifier::getClassification($scoreVal);
    $ranking = \TalentHub\Support\GradeClassifier::getRankingPercentile($scoreVal);

    assertCondition("Overall Score is 85/100", $scoreVal === 85.0, "Score: " . $latestEval['overall_score']);
    assertCondition("Classification for 85 points is 'Giỏi'", $classification === 'Giỏi', "Actual: {$classification}");
    assertCondition("Ranking for 85 points is 'Top 15% Chuyên ngành'", $ranking === 'Top 15% Chuyên ngành', "Actual: {$ranking}");
    assertCondition("Reviewer is ThS. Nguyễn Văn Hùng", str_contains((string)$latestEval['reviewer_name'], 'Nguyễn Văn Hùng'), "Reviewer: " . $latestEval['reviewer_name']);
    assertCondition("Evaluation has detailed criteria scores", count($latestEval['scores']) >= 4, "Criteria count: " . count($latestEval['scores']));
    assertCondition("Comment is populated", !empty($latestEval['comment']), mb_substr((string)$latestEval['comment'], 0, 40) . '...');
}

// Check grading scale boundary conditions
assertCondition("GradeClassifier: >= 90 is 'Xuất sắc'", \TalentHub\Support\GradeClassifier::getClassification(92.0) === 'Xuất sắc');
assertCondition("GradeClassifier: 80 - 89.9 is 'Giỏi'", \TalentHub\Support\GradeClassifier::getClassification(85.0) === 'Giỏi' && \TalentHub\Support\GradeClassifier::getClassification(80.0) === 'Giỏi');
assertCondition("GradeClassifier: 65 - 79.9 is 'Khá'", \TalentHub\Support\GradeClassifier::getClassification(75.0) === 'Khá' && \TalentHub\Support\GradeClassifier::getClassification(65.0) === 'Khá');
assertCondition("GradeClassifier: 50 - 64.9 is 'Trung bình'", \TalentHub\Support\GradeClassifier::getClassification(55.0) === 'Trung bình');
assertCondition("GradeClassifier: < 50 is 'Cần cải thiện'", \TalentHub\Support\GradeClassifier::getClassification(45.0) === 'Cần cải thiện');

assertCondition("GradeClassifier: >= 92 is 'Top 5%'", \TalentHub\Support\GradeClassifier::getRankingPercentile(93.0) === 'Top 5% Chuyên ngành');
assertCondition("GradeClassifier: 85 is 'Top 15%'", \TalentHub\Support\GradeClassifier::getRankingPercentile(85.0) === 'Top 15% Chuyên ngành');

// ----------------------------------------------------------------------
// Test 2: Radar Chart & Aptitude Scores (No flat 50s)
// ----------------------------------------------------------------------
echo "\n--- TEST 2: Radar Chart & Aptitude Dimension Scores ---\n";

$stResults = $pdo->prepare("
    SELECT tr.resultCode, tr.dimensionScoresJson, t.type
    FROM test_results tr
    JOIN test_attempts a ON a.id = tr.attemptId
    JOIN talent_tests t ON t.id = a.testId
    WHERE a.studentId = ?
");
$stResults->execute([$studentId]);
$results = $stResults->fetchAll(PDO::FETCH_ASSOC);

assertCondition("Student has test results", count($results) >= 4, "Count: " . count($results));

foreach ($results as $r) {
    $type = (string)$r['type'];
    $scores = json_decode((string)$r['dimensionScoresJson'], true) ?: [];
    $values = array_values($scores);
    $all50 = count($values) > 0 && count(array_unique($values)) === 1 && $values[0] == 50;

    assertCondition(
        "Test type '{$type}' has distinctive scores (not flat 50)",
        !$all50 && count($scores) > 0,
        "Code: {$r['resultCode']}, Sample Scores: " . json_encode(array_slice($scores, 0, 3))
    );
}

// ----------------------------------------------------------------------
// Test 3: No Residual Demo Strings
// ----------------------------------------------------------------------
echo "\n--- TEST 3: Residual Demo Text Cleanliness ---\n";

$demoCerts = $pdo->query("SELECT COUNT(*) FROM school_certificate_catalog WHERE issuerName LIKE '%demo%' OR issuerName LIKE '%mô phỏng%'")->fetchColumn();
assertCondition("Zero '(Dữ liệu demo)' in school_certificate_catalog", $demoCerts == 0, "Found: {$demoCerts}");

$demoSchools = $pdo->query("SELECT COUNT(*) FROM schools WHERE name LIKE '%demo%' OR name LIKE '%mô phỏng%' OR address LIKE '%demo%' OR address LIKE '%mô phỏng%'")->fetchColumn();
assertCondition("Zero '(Dữ liệu demo)' in schools", $demoSchools == 0, "Found: {$demoSchools}");

$demoBadges = $pdo->query("SELECT COUNT(*) FROM badges WHERE name LIKE '%demo%' OR description LIKE '%demo%' OR name LIKE '%mô phỏng%'")->fetchColumn();
assertCondition("Zero '(Dữ liệu demo)' in badges", $demoBadges == 0, "Found: {$demoBadges}");

$demoTeachers = $pdo->query("SELECT COUNT(*) FROM teacher_profiles WHERE bio LIKE '%demo%' OR bio LIKE '%mô phỏng%'")->fetchColumn();
assertCondition("Zero '(Dữ liệu demo)' in teacher_profiles", $demoTeachers == 0, "Found: {$demoTeachers}");

// ----------------------------------------------------------------------
// Test 4: Ecosystem Enterprise Deduplication
// ----------------------------------------------------------------------
echo "\n--- TEST 4: Ecosystem Enterprise Deduplication ---\n";

require_once dirname(__DIR__) . '/app/learner/includes/ecosystem-data.php';

$enterprises = learner_ecosystem_enterprises();
$enterpriseNames = array_map(fn($e) => $e['name'], $enterprises);
$uniqueNames = array_unique(array_map(fn($name) => mb_strtolower(trim((string)preg_replace('/\s*\(.*?\)\s*/', '', $name))), $enterpriseNames));

assertCondition("Enterprise list count > 0", count($enterprises) > 0, "Total: " . count($enterprises));
assertCondition("No duplicate enterprise brand names in ecosystem list", count($enterpriseNames) === count($uniqueNames), "Names: " . implode(', ', $enterpriseNames));

// ----------------------------------------------------------------------
// Test 5: Page Rendering Smoke Tests
// ----------------------------------------------------------------------
echo "\n--- TEST 5: Student & Learner Page Renders ---\n";

$pagesToTest = [
    '/app/learner/evaluation.php' => dirname(__DIR__) . '/app/learner/evaluation.php',
    '/app/student/assessments.php' => dirname(__DIR__) . '/app/student/assessments.php',
    '/app/learner/discover.php' => dirname(__DIR__) . '/app/learner/discover.php',
    '/app/learner/ecosystem.php' => dirname(__DIR__) . '/app/learner/ecosystem.php',
    '/app/learner/profile.php' => dirname(__DIR__) . '/app/learner/profile.php',
    '/app/learner/index.php' => dirname(__DIR__) . '/app/learner/index.php',
];

foreach ($pagesToTest as $route => $filePath) {
    $script = "
        putenv('APP_ENV=local');
        putenv('TALENTHUB_LEARNER_SOURCE=database');
        \$_SESSION['user'] = [
            'id' => '{$student['userId']}',
            'email' => '{$studentEmail}',
            'role' => 'student',
            'roleId' => 'student',
            'fullName' => '{$student['fullName']}',
        ];
        \$_SESSION['userId'] = '{$student['userId']}';
        \$_SESSION['role'] = 'student';
        ob_start();
        try {
            require '{$filePath}';
            \$content = ob_get_clean();
            \$hasHtml = str_contains(\$content, '<html') || str_contains(\$content, '<!DOCTYPE');
            echo \$hasHtml ? 'OK:' . strlen(\$content) : 'NO_HTML:' . strlen(\$content);
        } catch (Throwable \$e) {
            ob_end_clean();
            echo 'ERROR:' . \$e->getMessage();
        }
    ";

    $renderScriptPath = __DIR__ . '/_scratch_render.php';
    file_put_contents($renderScriptPath, "<?php\n" . $script);
    $res = shell_exec("php " . escapeshellarg($renderScriptPath));
    @unlink($renderScriptPath);

    $isOk = is_string($res) && str_starts_with(trim($res), 'OK:');
    assertCondition("Render page {$route}", $isOk, trim((string)$res));
}

echo "\n======================================================================\n";
echo " SUMMARY: Passed: {$passed} | Failed: {$failed}\n";
echo "======================================================================\n";

if ($failed > 0) {
    exit(1);
}
