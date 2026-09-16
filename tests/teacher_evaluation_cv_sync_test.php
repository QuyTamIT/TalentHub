<?php
declare(strict_types=1);
// Reuse the complete disposable CV fixture and its existing privacy regressions.
require __DIR__ . '/learner_passport_cv_repository_test.php';
require_once __DIR__ . '/../app/learner/data/Database/DatabaseAssessmentRepository.php';
$pdo->exec("CREATE TABLE skill_groups(code TEXT PRIMARY KEY,name TEXT,status TEXT,displayOrder INT);
CREATE TABLE assessment_skill_group_scores(assessmentId TEXT,groupCode TEXT,score REAL);
INSERT INTO skill_groups VALUES ('backend','Lập trình Backend','active',1);
INSERT INTO assessments(id,teacherId,studentId,status,comment,publishedAt) VALUES ('assessment','teacher','$student','published','Latest feedback','2026-09-16');
UPDATE learner_evaluations SET status='published',publishedAt='2026-09-16',legacyAssessmentId='assessment' WHERE id='ev2';
INSERT INTO assessment_skill_group_scores VALUES ('assessment','backend',85.25);");
$snapshot = $repo->forStudent($student);
if (count($snapshot['skills']) !== 1 || $snapshot['skills'][0]['score'] !== 85.25) throw new RuntimeException('CV repository lost graded group');
$cv = \TalentHub\Learner\Data\ReadModel\PassportCvViewModel::build($snapshot, 'now');
if ($cv['skills'][0]['score'] !== 85.25) throw new RuntimeException('CV must preserve exact graded score');
$passport = new \TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository($pdo, new \TalentHub\Learner\Data\Service\ScoreViewer('student', $user, $school));
if ($passport->skills($student)[0]['score'] !== 85.25) throw new RuntimeException('Passport must show the same group score');
$pdo->exec("UPDATE assessment_skill_group_scores SET score=0 WHERE assessmentId='assessment'");
if ($repo->forStudent($student)['skills'][0]['score'] !== 0.0) throw new RuntimeException('CV did not refresh a lower regrade');
$pdo->exec("DELETE FROM assessment_skill_group_scores WHERE assessmentId='assessment'");
if ($repo->forStudent($student)['skills'] !== []) throw new RuntimeException('CV retains removed group');
echo "teacher_evaluation_cv_sync_test: OK\n";
