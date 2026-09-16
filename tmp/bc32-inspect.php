<?php
require dirname(__DIR__) . '/bin/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$snapshot = [];
foreach (['skills', 'assessments', 'assessment_scores', 'assessment_criteria', 'learner_evaluations', 'learner_evaluation_items', 'learner_skill_evidence', 'student_skills'] as $table) {
    $snapshot[$table] = hash('sha256', serialize($pdo->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll()));
}
$path = __DIR__ . '/bc32-before.json';
if (($argv[1] ?? '') === 'before') file_put_contents($path, json_encode($snapshot));
else {
    if ($snapshot !== json_decode(file_get_contents($path), true)) throw new RuntimeException('Canonical data changed');
    echo "PASS: all 8 canonical tables unchanged\n";
    echo 'Groups: ' . $pdo->query('SELECT COUNT(*) FROM skill_groups')->fetchColumn() . "\n";
    echo 'Unmapped skills: ' . $pdo->query('SELECT COUNT(*) FROM skills s WHERE NOT EXISTS (SELECT 1 FROM skill_group_members m WHERE m.skillId=s.id)')->fetchColumn() . "\n";
}
