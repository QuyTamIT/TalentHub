<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';

// Explicitly opt in: this integration check writes only inside a rolled-back transaction.
if (getenv('TALENTHUB_TEST_MYSQL_HISTORY') !== '1') {
    echo "SKIP score_projection_mysql_history_test: set TALENTHUB_TEST_MYSQL_HISTORY=1 for local MySQL\n";
    exit(0);
}
$config = require dirname(__DIR__) . '/config/database.php';
if (!in_array($config['host'], ['localhost','127.0.0.1','::1'], true)) throw new RuntimeException('Local MySQL required');
$pdo = (new TalentHub\Database\Connection($config))->connect();
$students = $pdo->query("SELECT DISTINCT ss.studentId FROM student_skills ss
    JOIN student_profiles sp ON sp.id=ss.studentId JOIN classes c ON c.id=sp.classId
    WHERE c.status='active' ORDER BY ss.studentId")->fetchAll(PDO::FETCH_COLUMN);
$columns = 'id,studentId,skillId,levelScore,sourceType,verificationStatus,verifiedAt,createdAt,updatedAt';
$pdo->beginTransaction();
try {
    $legacy = $pdo->query("SELECT {$columns} FROM student_skills
        WHERE sourceEvaluationId IS NULL AND sourceEvidenceId IS NULL
        AND (scoreState IS NULL OR scoreState IN ('missing_source','unverified')) ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (!$legacy) throw new RuntimeException('No legacy rows available; test would be vacuous');
    $service = new TalentHub\Learner\Data\Service\EvidenceBackedScoreService($pdo);
    foreach ($students as $id) $service->projectOfficialScores($id);
    $query = $pdo->prepare("SELECT {$columns} FROM student_skills WHERE id=?");
    foreach ($legacy as $row) {
        $query->execute([$row['id']]);
        if ($query->fetch(PDO::FETCH_ASSOC) !== $row) throw new RuntimeException('Projection changed legacy source fields or timestamps');
    }
    echo 'score_projection_mysql_history_test: OK (' . count($legacy) . " preserved rows; transaction rolled back)\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
}
