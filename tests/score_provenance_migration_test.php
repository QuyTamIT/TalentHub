<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bin/bootstrap.php';
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("PRAGMA foreign_keys=ON;
    CREATE TABLE assessments (id TEXT PRIMARY KEY);
    CREATE TABLE learner_evaluations (id TEXT PRIMARY KEY);
    CREATE TABLE student_skills (id TEXT PRIMARY KEY, studentId TEXT, skillId TEXT, sourceType TEXT, levelScore NUMERIC NOT NULL, UNIQUE(studentId,skillId,sourceType));
    CREATE INDEX skill_student_idx ON student_skills(studentId);
    CREATE TABLE proofs (id TEXT PRIMARY KEY, studentSkillId TEXT REFERENCES student_skills(id));
    INSERT INTO student_skills VALUES ('skill','student','python','teacher',80);
    INSERT INTO proofs VALUES ('proof','skill')");
$migration = require dirname(__DIR__).'/Database/migrations/20260915000300_add_score_provenance_metadata.php';
$context = new TalentHub\Database\Migration\MigrationContext($pdo);
for ($i=0; $i<2; $i++) { $migration->preflight($context); $migration->up($context); }
$pdo->exec("UPDATE student_skills SET levelScore=NULL,scoreState='evidence_only' WHERE id='skill'");
if ($pdo->query('SELECT levelScore FROM student_skills')->fetchColumn() !== null) throw new RuntimeException('Score column still non-nullable');
if ($pdo->query('PRAGMA foreign_key_check')->fetchAll() !== []) throw new RuntimeException('Foreign keys damaged');
if ((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name='skill_student_idx'")->fetchColumn() !== 1) throw new RuntimeException('Index lost');
if ($pdo->query('SELECT studentSkillId FROM proofs')->fetchColumn() !== 'skill') throw new RuntimeException('Proof lost');
echo "score_provenance_migration_test: OK\n";
