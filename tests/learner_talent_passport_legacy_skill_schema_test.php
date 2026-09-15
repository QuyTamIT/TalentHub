<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once __DIR__ . '/learner_evidence_backed_scores_test.php';

foreach (['level', 'levelScore'] as $legacyColumn) {
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT)');
    $pdo->exec("CREATE TABLE student_skills (studentId TEXT, skillId TEXT, {$legacyColumn} REAL, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT)");
    $pdo->exec("INSERT INTO skills VALUES ('skill-1', 'PY', 'Python', 'technical', 'active')");
    $pdo->exec("INSERT INTO student_skills VALUES ('student-1', 'skill-1', 82.5, 'assessment', 'verified', '2026-08-01 10:00:00')");

    $skills = (new DatabaseTalentPassportRepository($pdo))->skills('student-1');
    scoreCheck(count($skills) === 1 && $skills[0]['name'] === 'Python', 'Legacy skill label must remain available');
    scoreCheck(array_key_exists('level_score', $skills[0]) && $skills[0]['level_score'] === null, "Unverified legacy {$legacyColumn} must not become a displayed score");
    scoreCheck(($skills[0]['score_state'] ?? '') === 'missing_source', 'Legacy skill must explicitly indicate missing score provenance');
    scoreCheck((float) $pdo->query("SELECT {$legacyColumn} FROM student_skills")->fetchColumn() === 82.5, 'Reading skills must preserve stored historical scores');
    echo "[PASS] {$legacyColumn} schema retains labels without trusting historical numbers\n";
}

foreach (['missing_table', 'incomplete_schema'] as $scenario) {
    $pdo = scoreFixture();
    $viewer = new \TalentHub\Learner\Data\Service\ScoreViewer('student', 'u1');
    $before = (new DatabaseTalentPassportRepository($pdo, $viewer))->skills('st1');
    $python = array_values(array_filter($before, static fn(array $row): bool => $row['skill_id'] === 'py'));
    scoreCheck(count($python) === 1 && $python[0]['level_score'] === 0.0, 'Valid teacher-assessed zero must remain zero');
    if ($scenario === 'missing_table') {
        $pdo->exec('DROP TABLE learner_skill_evidence');
    } else {
        $pdo->exec('ALTER TABLE learner_evaluations DROP COLUMN actorUserId');
    }
    $after = (new DatabaseTalentPassportRepository($pdo, $viewer))->skills('st1');
    foreach ($after as $skill) {
        if (in_array($skill['skill_id'], ['py', 'old', 'self'], true)) {
            scoreCheck($skill['level_score'] === null, "{$scenario} must not restore legacy or self-declared scores");
        }
    }
    scoreCheck((int) $pdo->query("SELECT levelScore FROM student_skills WHERE id='legacy'")->fetchColumn() === 88, 'Fallback must never rewrite historical rows');
    echo "[PASS] {$scenario} cannot restore stale or self-declared numeric scores\n";
}

echo "learner_talent_passport_legacy_skill_schema_test: OK\n";
