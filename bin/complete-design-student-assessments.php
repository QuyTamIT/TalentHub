<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Support\Uuid;

require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/AssessmentScorer.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/ScoringResult.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/LikertScore.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/ScorerRegistry.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/HollandScorer.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/MbtiScorer.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/DiscScorer.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/MultipleIntelligenceScorer.php';

$email = 'chau.thietke@talenthub.local';
$catalogs = [
    'holland_college' => ['primary' => 'A', 'secondary' => ['S', 'I']],
    'mbti_college' => ['axis' => ['EI' => 'E', 'SN' => 'N', 'TF' => 'F', 'JP' => 'P']],
    'disc_college' => ['primary' => 'I', 'secondary' => ['S']],
    'multiple_intelligence_college' => ['primary' => 'SPAT', 'secondary' => ['LING', 'INTER']],
];

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$student = $pdo->prepare(
    'SELECT sp.id AS studentId, u.fullName
     FROM users u
     INNER JOIN student_profiles sp ON sp.userId = u.id
     WHERE u.email = ? LIMIT 1'
);
$student->execute([$email]);
$row = $student->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    fwrite(STDERR, "Student not found: {$email}\n");
    exit(1);
}
$studentId = (string) $row['studentId'];
$fullName = (string) $row['fullName'];

echo "======================================================================\n";
echo " HOÀN THÀNH 4/4 BÀI TEST — {$fullName}\n";
echo "======================================================================\n\n";

$registry = new ScorerRegistry([
    'holland-riasec-1.0' => new HollandScorer(),
    'mbti-education-1.0' => new MbtiScorer(),
    'disc-education-1.0' => new DiscScorer(),
    'multiple-intelligence-1.0' => new MultipleIntelligenceScorer(),
]);

$pdo->beginTransaction();
try {
    $attemptIds = $pdo->prepare('SELECT id FROM test_attempts WHERE studentId = ?');
    $attemptIds->execute([$studentId]);
    $oldIds = $attemptIds->fetchAll(PDO::FETCH_COLUMN);
    foreach ($oldIds as $oldId) {
        $pdo->prepare('DELETE FROM learner_assessment_answers WHERE attemptId = ?')->execute([$oldId]);
        $pdo->prepare('DELETE FROM learner_assessment_attempt_metadata WHERE attemptId = ?')->execute([$oldId]);
        $pdo->prepare('DELETE FROM test_results WHERE attemptId = ?')->execute([$oldId]);
    }
    $pdo->prepare('DELETE FROM test_attempts WHERE studentId = ?')->execute([$studentId]);

    $submittedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    $startedAt = (new DateTimeImmutable($submittedAt, new DateTimeZone('UTC')))->modify('-45 minutes')->format('Y-m-d H:i:s.u');
    $step = 0;

    foreach ($catalogs as $code => $bias) {
        $meta = $pdo->query(
            "SELECT t.id AS testId, t.type, v.id AS versionId, v.version, v.scoringVersion, v.schemaHash
             FROM talent_tests t
             INNER JOIN learner_assessment_versions v ON v.testId = t.id
             WHERE t.code = " . $pdo->quote($code) . " AND v.status = 'published'
             LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (!$meta) {
            throw new RuntimeException("Missing published catalog: {$code}");
        }

        $questions = $pdo->query(
            "SELECT qv.questionId AS qid, qv.position, qv.dimensionCode, qv.required
             FROM learner_assessment_question_versions qv
             WHERE qv.versionId = " . $pdo->quote($meta['versionId']) . '
             ORDER BY qv.position'
        )->fetchAll(PDO::FETCH_ASSOC);
        if ($questions === []) {
            throw new RuntimeException("No questions for catalog: {$code}");
        }

        $answers = buildDesignAnswers($code, $questions, $bias);
        $scorerQuestions = array_map(static fn (array $q): array => [
            'question_id' => $q['qid'],
            'dimension_code' => $q['dimensionCode'],
            'required' => (int) $q['required'],
        ], $questions);
        $scored = $registry->forVersion((string) $meta['scoringVersion'])->score($scorerQuestions, $answers)->toArray();

        ksort($answers, SORT_STRING);
        $inputHash = hash('sha256', json_encode([
            'assessment_version' => $meta['version'],
            'scoring_version' => $meta['scoringVersion'],
            'schema_hash' => $meta['schemaHash'],
            'answers' => $answers,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $attemptId = Uuid::v4();
        $pdo->prepare(
            'INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt, createdAt, updatedAt)
             VALUES (?, ?, ?, \'submitted\', ?, ?, ?, ?)'
        )->execute([$attemptId, $meta['testId'], $studentId, $startedAt, $submittedAt, $submittedAt, $submittedAt]);

        $pdo->prepare(
            'INSERT INTO learner_assessment_attempt_metadata
             (id, attemptId, versionId, status, expiresAt, submittedAt, inputHash, createdAt, updatedAt)
             VALUES (?, ?, ?, \'submitted\', NULL, ?, ?, ?, ?)'
        )->execute([Uuid::v4(), $attemptId, $meta['versionId'], $submittedAt, $inputHash, $submittedAt, $submittedAt]);

        $insertAnswer = $pdo->prepare(
            'INSERT INTO learner_assessment_answers (id, attemptId, questionId, answerJson, answeredAt)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($answers as $qid => $value) {
            $insertAnswer->execute([
                Uuid::v4(),
                $attemptId,
                $qid,
                json_encode($value, JSON_THROW_ON_ERROR),
                $submittedAt,
            ]);
        }

        $pdo->prepare(
            'INSERT INTO test_results (id, attemptId, resultCode, summary, dimensionScoresJson, scoringVersion, createdAt)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            Uuid::v4(),
            $attemptId,
            $scored['result_code'],
            $scored['summary'],
            json_encode($scored['dimension_scores'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $meta['scoringVersion'],
            $submittedAt,
        ]);

        $step++;
        echo "[{$step}/4] {$code} → {$scored['result_code']}\n";
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$verify = $pdo->prepare(
    "SELECT tt.type, tt.code, ta.status, tr.resultCode
     FROM test_attempts ta
     INNER JOIN talent_tests tt ON tt.id = ta.testId
     INNER JOIN test_results tr ON tr.attemptId = ta.id
     INNER JOIN learner_assessment_attempt_metadata m ON m.attemptId = ta.id
     WHERE ta.studentId = ? AND ta.status = 'submitted'
     ORDER BY tt.type"
);
$verify->execute([$studentId]);
$done = $verify->fetchAll(PDO::FETCH_ASSOC);

echo "\n======================================================================\n";
echo " KẾT QUẢ\n";
echo "======================================================================\n";
foreach ($done as $item) {
    echo " - {$item['type']} ({$item['code']}): {$item['resultCode']} [{$item['status']}]\n";
}
echo ' Tổng: ' . count($done) . "/4 bài test đã hoàn thành\n";
echo "======================================================================\n";

/**
 * @param list<array{qid:string,position:int,dimensionCode:string,required:int}> $questions
 * @param array<string,mixed> $bias
 * @return array<string,int>
 */
function buildDesignAnswers(string $code, array $questions, array $bias): array
{
    $answers = [];
    if (str_starts_with($code, 'holland_')) {
        $primary = (string) $bias['primary'];
        $secondary = $bias['secondary'] ?? [];
        foreach ($questions as $q) {
            preg_match('/\A([RIASEC])(?::([+-]))?\z/i', (string) $q['dimensionCode'], $m);
            $dim = strtoupper($m[1] ?? 'R');
            $reversed = ($m[2] ?? '+') === '-';
            $base = $dim === $primary ? 5 : (in_array($dim, $secondary, true) ? 4 : 2);
            $answers[$q['qid']] = $reversed ? (6 - $base) : $base;
        }
        return $answers;
    }
    if (str_starts_with($code, 'mbti_')) {
        $axisPrefs = $bias['axis'];
        foreach ($questions as $q) {
            preg_match('/\A(EI|SN|TF|JP):([EISNTFJP])\z/i', (string) $q['dimensionCode'], $m);
            $axis = strtoupper($m[1] ?? 'EI');
            $pole = strtoupper($m[2] ?? 'E');
            $preferred = $axisPrefs[$axis] ?? $pole;
            $answers[$q['qid']] = ($pole === $preferred) ? 5 : 2;
        }
        return $answers;
    }
    if (str_starts_with($code, 'disc_')) {
        $primary = (string) $bias['primary'];
        $secondary = $bias['secondary'] ?? [];
        foreach ($questions as $q) {
            preg_match('/\A([DISC])(?::([+-]))?\z/i', (string) $q['dimensionCode'], $m);
            $dim = strtoupper($m[1] ?? 'D');
            $reversed = ($m[2] ?? '+') === '-';
            $base = $dim === $primary ? 5 : (in_array($dim, $secondary, true) ? 4 : 2);
            $answers[$q['qid']] = $reversed ? (6 - $base) : $base;
        }
        return $answers;
    }
    $primary = (string) $bias['primary'];
    $secondary = $bias['secondary'] ?? [];
    foreach ($questions as $q) {
        preg_match('/\A(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)(?::([+-]))?\z/i', (string) $q['dimensionCode'], $m);
        $dim = strtoupper($m[1] ?? 'LING');
        $reversed = ($m[2] ?? '+') === '-';
        $base = $dim === $primary ? 5 : (in_array($dim, $secondary, true) ? 4 : 2);
        $answers[$q['qid']] = $reversed ? (6 - $base) : $base;
    }
    return $answers;
}
