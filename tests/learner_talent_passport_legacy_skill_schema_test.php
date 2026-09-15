<?php

declare(strict_types=1);

use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;

require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT)');
$pdo->exec('CREATE TABLE student_skills (studentId TEXT, skillId TEXT, level REAL, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT)');
$pdo->exec("INSERT INTO skills VALUES ('skill-1', 'PY', 'Python', 'technical', 'active')");
$pdo->exec("INSERT INTO student_skills VALUES ('student-1', 'skill-1', 82.5, 'assessment', 'verified', '2026-08-01 10:00:00')");

$repo = new DatabaseTalentPassportRepository($pdo);
$method = new ReflectionMethod($repo, 'skills');
$method->setAccessible(true);
$skills = $method->invoke($repo, 'student-1');

if (($skills[0]['level_score'] ?? null) !== 82.5) {
    fwrite(STDERR, "Legacy student_skills.level was not mapped to level_score.\n");
    exit(1);
}

echo "learner_talent_passport_legacy_skill_schema_test: OK\n";
