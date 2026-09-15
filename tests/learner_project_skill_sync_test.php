<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';
require dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Ai\Sources\AiSourceRegistry;
use TalentHub\Learner\Data\Contracts\TalentPassportRepository;
use TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository;
use TalentHub\Modules\School\Repository\SchoolProjectRepository;
use TalentHub\Modules\Student\Repository\PortfolioRepository;

$assert = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec(<<<'SQL'
CREATE TABLE roles(id VARCHAR(64) PRIMARY KEY,code VARCHAR(64));
CREATE TABLE users(id VARCHAR(64) PRIMARY KEY,roleId VARCHAR(64),status VARCHAR(64),fullName VARCHAR(255),email VARCHAR(255));
CREATE TABLE schools(id VARCHAR(64) PRIMARY KEY,name VARCHAR(255),status VARCHAR(64));
CREATE TABLE classes(id VARCHAR(64) PRIMARY KEY,schoolId VARCHAR(64),name VARCHAR(255),gradeLevel VARCHAR(64),academicYear VARCHAR(64));
CREATE TABLE student_profiles(id VARCHAR(64) PRIMARY KEY,userId VARCHAR(64),classId VARCHAR(64),studyStatus VARCHAR(64));
CREATE TABLE teacher_profiles(id VARCHAR(64) PRIMARY KEY,userId VARCHAR(64),schoolId VARCHAR(64));
CREATE TABLE projects(id VARCHAR(64) PRIMARY KEY,schoolId VARCHAR(64),mentorTeacherId VARCHAR(64),title VARCHAR(255),status VARCHAR(64),category VARCHAR(64),description TEXT,fundingGoal TEXT,projectUrl TEXT,startAt TEXT,endAt TEXT,createdAt TEXT,updatedAt TEXT);
CREATE TABLE project_members(id VARCHAR(64) PRIMARY KEY,projectId VARCHAR(64),studentId VARCHAR(64),status VARCHAR(64),role VARCHAR(64),contribution TEXT,joinedAt TEXT);
CREATE UNIQUE INDEX uq_project_members_student ON project_members(projectId, studentId);
CREATE TABLE enterprises(id VARCHAR(64) PRIMARY KEY,name VARCHAR(255),logoUrl TEXT);
CREATE TABLE project_sponsorships(id VARCHAR(64) PRIMARY KEY,projectId VARCHAR(64),enterpriseId VARCHAR(64),amount REAL,status VARCHAR(64),createdAt TEXT);
CREATE TABLE internship_posts(id VARCHAR(64) PRIMARY KEY,enterpriseId VARCHAR(64),title VARCHAR(255),status VARCHAR(64),skillsJson TEXT);
CREATE TABLE internship_applications(id VARCHAR(64) PRIMARY KEY,postId VARCHAR(64),studentId VARCHAR(64),status VARCHAR(64));
CREATE TABLE internship_mentor_assignments(id VARCHAR(64) PRIMARY KEY,applicationId VARCHAR(64) UNIQUE,mentorTeacherId VARCHAR(64));
CREATE TABLE skills(id VARCHAR(64) PRIMARY KEY,code VARCHAR(64),name VARCHAR(255),category VARCHAR(64),status VARCHAR(64));
CREATE TABLE student_skills(studentId VARCHAR(64),skillId VARCHAR(64),levelScore REAL,sourceType VARCHAR(64),verificationStatus VARCHAR(64),verifiedAt TEXT);
SQL);

$portfolioMigration = require dirname(__DIR__) . '/Database/migrations/learner/021_create_learner_portfolio_reports.php';
foreach ($portfolioMigration->migration->statements('sqlite') as $statement) {
    $pdo->exec($statement);
}
$evidenceMigration = require dirname(__DIR__) . '/Database/migrations/learner/022_extend_portfolio_skill_evidence.php';
foreach ($evidenceMigration->migration->statements('sqlite') as $statement) {
    $pdo->exec($statement);
}
$outboxMigration = require dirname(__DIR__) . '/Database/migrations/learner/007_create_ai_data_outbox.php';
foreach ($outboxMigration->migration->statements('sqlite') as $statement) {
    $pdo->exec($statement);
}

$pdo->exec(<<<'SQL'
INSERT INTO roles VALUES ('rs','student'),('rt','teacher');
INSERT INTO users VALUES ('us1','rs','active','Student One','stu1@test.local'),('ut1','rt','active','Teacher One','t1@test.local');
INSERT INTO schools VALUES ('s1','School One','active');
INSERT INTO classes VALUES ('c1','s1','Class 1','12','2026');
INSERT INTO student_profiles VALUES ('stu1','us1','c1','active');
INSERT INTO teacher_profiles VALUES ('t1','ut1','s1');
INSERT INTO projects VALUES ('p1','s1','t1','Robotics','in_progress','general','Build a robot',NULL,NULL,NULL,NULL,'2026-01-01 00:00:00','2026-01-01 00:00:00');
INSERT INTO project_members VALUES ('m1','p1','stu1','active','member',NULL,'2026-01-01 00:00:00');
INSERT INTO enterprises VALUES ('e1','Acme',NULL);
INSERT INTO internship_posts VALUES ('post1','e1','PHP Intern','active','["PHP","New Internship Skill"]');
INSERT INTO internship_applications VALUES ('a1','post1','stu1','accepted');
INSERT INTO internship_mentor_assignments VALUES ('ima1','a1','t1');
INSERT INTO skills VALUES ('sk1','php','PHP','technical','active'),('sk2','legacy','Legacy','technical','inactive'),('sk3','python','Python','technical','active');
CREATE TABLE project_skill_tags(id VARCHAR(64) PRIMARY KEY,projectId VARCHAR(64),skillId VARCHAR(64),verifiedAt TEXT,createdAt TEXT);
INSERT INTO project_skill_tags VALUES ('tag1','p1','sk1','2026-01-01','2026-01-01');
INSERT INTO student_skills VALUES ('stu1','sk3',80,'assessment','verified','2026-01-01 00:00:00');
SQL);

$repo = new DatabaseTalentPassportRepository($pdo);
$events = [];
$portfolio = new PortfolioRepository($pdo, static function (string $type, string $recipient, array $payload) use (&$events): void {
    $events[] = [$type, $recipient, $payload];
});

$skillCodes = static function (array $skills): array {
    $codes = array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['code'] ?? ''), $skills)));
    sort($codes);
    return $codes;
};
$snapshotHash = static function (DatabaseTalentPassportRepository $passport, string $studentId, bool $includeProjects = false): string {
    $skills = $passport->skills($studentId);
    $method = new ReflectionMethod($passport, 'portfolioVerifiedSkills');
    $method->setAccessible(true);
    $portfolioSkills = $method->invoke($passport, $studentId);
    $projects = [];
    if ($includeProjects) {
        $optional = new ReflectionMethod($passport, 'optionalFacts');
        $optional->setAccessible(true);
        $facts = $optional->invoke($passport, $studentId);
        $projects = is_array($facts['projects'] ?? null) ? $facts['projects'] : [];
    }
    $stub = new class($skills, $portfolioSkills, $projects) implements TalentPassportRepository {
        /** @param list<array<string,mixed>> $skills @param list<array<string,mixed>> $portfolioSkills @param list<array<string,mixed>> $projects */
        public function __construct(private readonly array $skills, private readonly array $portfolioSkills, private readonly array $projects) {}
        public function aggregateForStudent(string $studentId): array
        {
            return [
                'skills' => $this->skills,
                'portfolio_skills' => $this->portfolioSkills,
                'projects' => $this->projects,
                'internships' => [],
                'certificates' => [],
                'badges' => [],
                'source_availability' => [],
            ];
        }
    };
    $registry = new AiSourceRegistry();
    $registry->registerTalentPassportSources($stub);
    return $registry->buildInput($studentId, ['skills', 'activity'])->contentHash();
};
$projectIdsFrom = static function (DatabaseTalentPassportRepository $passport, string $studentId): array {
    $optional = new ReflectionMethod($passport, 'optionalFacts');
    $optional->setAccessible(true);
    $facts = $optional->invoke($passport, $studentId);
    $ids = array_values(array_filter(array_map(
        static fn (array $row): string => (string) ($row['id'] ?? ''),
        is_array($facts['projects'] ?? null) ? $facts['projects'] : [],
    )));
    sort($ids);
    return $ids;
};
$passportSource = (string) file_get_contents(dirname(__DIR__) . '/app/learner/data/Database/DatabaseTalentPassportRepository.php');
$assert(
    substr_count($passportSource, "INNER JOIN project_members pm ON pm.projectId = p.id AND pm.status = 'active'") === 2,
    'Shared and aggregate project reads both require active membership',
);
$assert(
    str_contains($passportSource, "INNER JOIN project_members pm ON pm.projectId = r.projectId AND pm.studentId = r.studentId AND pm.status = 'active'"),
    'portfolioVerifiedSkills requires an active project_members row',
);

$baselineSkills = $repo->skills('stu1');
$assert($skillCodes($baselineSkills) === ['python'], 'Baseline skills include only the scored student_skills row');
$baselineHash = $snapshotHash($repo, 'stu1');
$assert(strlen($baselineHash) === 64, 'Baseline snapshot hash is SHA-256');
$assert((int) $pdo->query('SELECT COUNT(*) FROM learner_ai_data_outbox')->fetchColumn() === 0, 'Outbox starts empty');

$draft = $portfolio->save('stu1', 'project', 'p1', 0, ['notes' => 'Evidence', 'repositoryUrl' => 'https://example.test/repo', 'submit' => true]);
$error = static function (callable $fn, int $status) use ($assert): void {
    try {
        $fn();
    } catch (ApiException $exception) {
        $assert($exception->status === $status, "Expected {$status}, got {$exception->status}");
        return;
    }
    throw new RuntimeException("Expected ApiException {$status}");
};
$error(static fn () => $portfolio->review('ut1', 'project', $draft['id'], $draft['version'], 'verified', '', ['sk2']), 422);

$verified = $portfolio->review('ut1', 'project', $draft['id'], $draft['version'], 'verified', 'Confirmed', ['sk1', 'sk1'], ['sk1'=>0]);
$assert($verified['status'] === 'verified', 'Project report is verified');
$afterVerify = $repo->skills('stu1');
$assert(in_array('php', $skillCodes($afterVerify), true), 'Verified portfolio skill is visible in skills()');
$assert(in_array('python', $skillCodes($afterVerify), true), 'Existing student_skills rows are preserved');
$php = null;
foreach ($afterVerify as $row) {
    if (($row['code'] ?? null) === 'php') {
        $php = $row;
        break;
    }
}
$assert(is_array($php) && ($php['verification_status'] ?? null) === 'verified', 'Portfolio skill is marked verified');
$assert(($php['source_type'] ?? null) === 'project_submission', 'Portfolio skill keeps project evidence provenance');
$assert(($php['skill_status'] ?? null) === 'active', 'Only active skills are merged');
$assert(($php['level_score']??null) === 0.0, 'Genuine project score zero is preserved');

$verifiedHash = $snapshotHash($repo, 'stu1');
$assert($verifiedHash !== $baselineHash, 'Snapshot hash changes after a verified portfolio skill is added');
$verifiedMembershipHash = $snapshotHash($repo, 'stu1', true);
$projectsWhileActive = $projectIdsFrom($repo, 'stu1');
$assert(in_array('p1', $projectsWhileActive, true), 'Active membership keeps the project in the Talent Passport aggregate');

$pdo->exec("UPDATE project_members SET status='removed' WHERE id='m1'");
$assert(!in_array('php', $skillCodes($repo->skills('stu1')), true), 'Removed membership drops the verified project skill immediately');
$assert(in_array('python', $skillCodes($repo->skills('stu1')), true), 'Unrelated student_skills remain after membership removal');
$projectsWhileRemoved = $projectIdsFrom($repo, 'stu1');
$assert(!in_array('p1', $projectsWhileRemoved, true), 'Removed membership drops the project from the Talent Passport aggregate');
$removedMembershipHash = $snapshotHash($repo, 'stu1', true);
$assert($removedMembershipHash !== $verifiedMembershipHash, 'AiSourceRegistry snapshot hash changes when project membership is removed');

$pdo->exec("UPDATE project_members SET status='active' WHERE id='m1'");
$assert(in_array('php', $skillCodes($repo->skills('stu1')), true), 'Restored membership brings the verified project skill back');
$assert($snapshotHash($repo, 'stu1', true) === $verifiedMembershipHash, 'Restored membership restores the learner snapshot hash');

$outboxAfterVerify = $pdo->query("SELECT event_type, aggregate_type, affected_student_ids FROM learner_ai_data_outbox ORDER BY occurred_at, id")->fetchAll(PDO::FETCH_ASSOC);
$assert(count($outboxAfterVerify) === 1, 'Verify writes one AI outbox event');
$assert(($outboxAfterVerify[0]['event_type'] ?? null) === 'portfolio.verified', 'Outbox event type is portfolio.verified');
$assert(($outboxAfterVerify[0]['aggregate_type'] ?? null) === 'portfolio_report', 'Outbox aggregate is the portfolio report');
$assert(str_contains((string) ($outboxAfterVerify[0]['affected_student_ids'] ?? ''), 'stu1'), 'Outbox names the verified student');

$pdo->prepare("INSERT INTO learner_portfolio_skills(kind,reportId,skillId) VALUES ('project',:id,'sk2')")->execute(['id' => $verified['id']]);
$assert(!in_array('legacy', $skillCodes($repo->skills('stu1')), true), 'Inactive skills never enter the learner skill snapshot');
$pdo->prepare("DELETE FROM learner_portfolio_skills WHERE kind='project' AND reportId=? AND skillId='sk2'")->execute([$verified['id']]);

$revoked = $portfolio->review('ut1', 'project', $verified['id'], $verified['version'], 'revoked', 'Evidence withdrawn');
$assert($revoked['status'] === 'revoked', 'Project report is revoked');
$afterRevoke = $repo->skills('stu1');
$assert($skillCodes($afterRevoke) === ['python'], 'Revoked portfolio skill disappears from skills() immediately');
$revokedHash = $snapshotHash($repo, 'stu1');
$assert($revokedHash !== $verifiedHash, 'Snapshot hash changes after the verified skill is revoked');
$assert($revokedHash === $baselineHash, 'Revocation restores the previous skill snapshot hash');

$outboxAfterRevoke = $pdo->query('SELECT event_type FROM learner_ai_data_outbox')->fetchAll(PDO::FETCH_COLUMN);
$assert(count($outboxAfterRevoke) === 2, 'Revoke appends a second AI outbox event');
$assert(in_array('portfolio.verified', $outboxAfterRevoke, true) && in_array('portfolio.revoked', $outboxAfterRevoke, true), 'Outbox contains both verify and revoke events');

$intern = $portfolio->save('stu1', 'internship', 'a1', 0, [
    'notes' => 'Internship evidence',
    'stage' => 'completed',
    'startDate' => '2026-01-01',
    'endDate' => '2026-02-01',
    'hours' => 120.25,
    'submit' => true,
]);
$internVerified = $portfolio->review('ut1', 'internship', $intern['id'], $intern['version'], 'verified', 'Placement confirmed');
$assert($internVerified['status'] === 'verified', 'Internship report is verified');
$afterIntern = $repo->skills('stu1');
$assert(in_array('php', $skillCodes($afterIntern), true), 'Verified internship skill is visible in skills()');
$internHash = $snapshotHash($repo, 'stu1');
$assert($internHash !== $revokedHash, 'Internship verification changes the snapshot hash');
$phpIntern = null;
foreach ($afterIntern as $row) {
    if (($row['code'] ?? null) === 'php') {
        $phpIntern = $row;
        break;
    }
}
$assert(is_array($phpIntern) && ($phpIntern['source_type'] ?? null) === 'internship_completion', 'Internship skill keeps completion evidence provenance');
$assert(array_key_exists('level_score',$phpIntern) && $phpIntern['level_score'] === null, 'Internship completion remains unscored');
$assert(in_array('new-internship-skill',$skillCodes($afterIntern),true) || count($afterIntern)>=3, 'Unknown internship skill creates safe catalog evidence');

// --- Regressions for review findings: transactional integrity of skill evidence ---

// Migration-level CHECK rejects out-of-range scores but allows NULL, 0 and 100.
$checkRejected = 0;
foreach ([150.0, -1.0, INF] as $badScore) {
    try {
        $pdo->prepare('INSERT INTO learner_portfolio_skills(kind,reportId,skillId,score,sourceType,evidenceStatus) VALUES (?,?,?,?,?,?)')->execute(['project','range-check','sk1',$badScore,'project_report','verified']);
    } catch (PDOException) { $checkRejected++; }
}
$assert($checkRejected === 3, 'Score CHECK constraint rejects out-of-range and non-finite values');
$pdo->prepare('INSERT INTO learner_portfolio_skills(kind,reportId,skillId,score,sourceType,evidenceStatus) VALUES (?,?,?,?,?,?)')->execute(['internship','range-check','sk1',null,'internship_completion','verified']);
$pdo->prepare('INSERT INTO learner_portfolio_skills(kind,reportId,skillId,score,sourceType,evidenceStatus) VALUES (?,?,?,?,?,?)')->execute(['project','range-check','sk3',0.0,'project_report','verified']);
$pdo->prepare('INSERT INTO learner_portfolio_skills(kind,reportId,skillId,score,sourceType,evidenceStatus) VALUES (?,?,?,?,?,?)')->execute(['project','range-check2','sk3',100.0,'project_report','verified']);
$pdo->exec("DELETE FROM learner_portfolio_skills WHERE reportId IN ('range-check','range-check2')");

// A new revision withdraws current evidence but keeps it in history.
$resubmitted = $portfolio->save('stu1','project','p1',$revoked['version'],['newRevision'=>true,'notes'=>'Revised evidence','repositoryUrl'=>'https://example.test/repo2','submit'=>true]);
$assert($resubmitted['status']==='submitted','New revision can resubmit the project report');
$reverified = $portfolio->review('ut1','project',$resubmitted['id'],$resubmitted['version'],'verified','Accepted again',['sk1'],['sk1'=>55]);
$evidence = $pdo->query('SELECT skillId,score,sourceType FROM learner_portfolio_skills WHERE kind=\'project\' AND reportId='.$pdo->quote((string)$reverified['id']))->fetchAll(PDO::FETCH_ASSOC);
$assert(count($evidence)===1 && abs((float)$evidence[0]['score']-55.0)<0.001 && $evidence[0]['sourceType']==='project_report','Re-verified report stores the scored project evidence');
$revisionDraft = $portfolio->save('stu1','project','p1',$reverified['version'],['newRevision'=>true,'notes'=>'Rework needed']);
$assert($revisionDraft['status']==='draft','New revision returns the report to draft');
$remainingEvidence = (int)$pdo->query('SELECT COUNT(*) FROM learner_portfolio_skills WHERE kind=\'project\' AND reportId='.$pdo->quote((string)$revisionDraft['id']))->fetchColumn();
$assert($remainingEvidence===0,'New revision withdraws the current project skill evidence');
$revisionEvents = $pdo->query("SELECT event_type FROM learner_ai_data_outbox WHERE aggregate_type='portfolio_report' AND aggregate_id=".$pdo->quote((string)$revisionDraft['id']).' AND aggregate_version='.(int)$revisionDraft['version'])->fetchAll(PDO::FETCH_COLUMN);
$assert(in_array('portfolio.revoked',$revisionEvents,true),'New revision publishes an AI invalidation event');
$historyVerified = null;
foreach ($revisionDraft['history'] as $historyEvent) { if (($historyEvent['status']??null)==='verified') $historyVerified=$historyEvent; }
$assert(is_array($historyVerified) && ($historyVerified['skillIds']??[])===['sk1'],'History keeps the withdrawn evidence snapshot');

// A failing AI outbox publish rolls the whole review back.
$collideSubmitted = $portfolio->save('stu1','project','p1',$revisionDraft['version'],['notes'=>'Collision probe','submit'=>true]);
$error(static fn () => $portfolio->review('ut1','project',$collideSubmitted['id'],$collideSubmitted['version'],'verified','NaN probe',['sk1'],['sk1'=>NAN]), 422);
$collisionVersion = (int)$collideSubmitted['version'] + 1;
$pdo->prepare("INSERT INTO learner_ai_data_outbox (id,aggregate_type,aggregate_id,event_type,aggregate_version,payload_hash,affected_student_ids,delivery_status,occurred_at) VALUES ('outbox-collision','portfolio_report',?,'collision',?,'hash','[\"stu1\"]','pending','2026-01-01 00:00:00')")->execute([(string)$collideSubmitted['id'],$collisionVersion]);
$error(static fn () => $portfolio->review('ut1','project',$collideSubmitted['id'],$collideSubmitted['version'],'verified','Should roll back',['sk1'],['sk1'=>70]), 500);
$afterCollision = $pdo->query('SELECT status FROM project_submissions WHERE id='.$pdo->quote((string)$collideSubmitted['id']))->fetchColumn();
$assert($afterCollision==='submitted','Failed outbox publish rolls the report back to submitted');
$collisionEvidence = (int)$pdo->query('SELECT COUNT(*) FROM learner_portfolio_skills WHERE reportId='.$pdo->quote((string)$collideSubmitted['id']))->fetchColumn();
$assert($collisionEvidence===0,'Rolled-back review stores no skill evidence');
$pdo->exec("DELETE FROM learner_ai_data_outbox WHERE id='outbox-collision'");
$retryVerified = $portfolio->review('ut1','project',$collideSubmitted['id'],$collideSubmitted['version'],'verified','Retry after collision',['sk1'],['sk1'=>70]);
$assert($retryVerified['status']==='verified','Review succeeds once the outbox conflict is cleared');

// Internship skill derivation happens inside the review transaction.
$pdo->exec("INSERT INTO internship_posts VALUES ('post2','e1','Ops Intern','active','[\"Transient Rollback Skill\"]')");
$pdo->exec("INSERT INTO internship_applications VALUES ('a2','post2','stu1','accepted')");
$pdo->exec("INSERT INTO internship_mentor_assignments VALUES ('ima2','a2','t1')");
$internDraft = $portfolio->save('stu1','internship','a2',0,['notes'=>'Internship evidence 2','stage'=>'completed','startDate'=>'2026-01-01','endDate'=>'2026-02-01','hours'=>80,'submit'=>true]);
$failingPortfolio = new PortfolioRepository($pdo, static function (string $type, string $recipient, array $payload): void { throw new RuntimeException('Notification transport down'); });
$error(static fn () => $failingPortfolio->review('ut1','internship',$internDraft['id'],$internDraft['version'],'verified','Should roll back'), 500);
$leakedSkills = (int)$pdo->query("SELECT COUNT(*) FROM skills WHERE name='Transient Rollback Skill'")->fetchColumn();
$assert($leakedSkills===0,'Failed internship review leaves no derived skill catalog rows');
$internStatus = $pdo->query('SELECT status FROM learner_internship_reports WHERE id='.$pdo->quote((string)$internDraft['id']))->fetchColumn();
$assert($internStatus==='submitted','Failed internship review keeps the report submitted');
$internEvidence = (int)$pdo->query('SELECT COUNT(*) FROM learner_portfolio_skills WHERE kind=\'internship\' AND reportId='.$pdo->quote((string)$internDraft['id']))->fetchColumn();
$assert($internEvidence===0,'Failed internship review stores no completion evidence');
$internVerified2 = $portfolio->review('ut1','internship',$internDraft['id'],$internDraft['version'],'verified','Placement confirmed 2');
$assert($internVerified2['status']==='verified','Internship review succeeds after the transient failure');
$derivedSkills = (int)$pdo->query("SELECT COUNT(*) FROM skills WHERE name='Transient Rollback Skill'")->fetchColumn();
$assert($derivedSkills===1,'Successful internship review derives the catalog skill exactly once');
$internEvidence2 = (int)$pdo->query('SELECT COUNT(*) FROM learner_portfolio_skills WHERE kind=\'internship\' AND reportId='.$pdo->quote((string)$internDraft['id']))->fetchColumn();
$assert($internEvidence2===1,'Successful internship review stores the completion evidence');

$schoolPdo = new PDO('sqlite::memory:');
$schoolPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$schoolPdo->exec('PRAGMA foreign_keys=ON');
$schoolPdo->exec(<<<'SQL'
CREATE TABLE projects (
    id VARCHAR(64) PRIMARY KEY,
    schoolId VARCHAR(64),
    mentorTeacherId VARCHAR(64),
    title VARCHAR(255),
    category VARCHAR(64),
    topic TEXT,
    description TEXT,
    projectUrl TEXT,
    fundingGoal TEXT,
    startAt TEXT,
    endAt TEXT,
    status VARCHAR(64),
    createdAt TEXT,
    updatedAt TEXT
);
CREATE TABLE project_members (
    id VARCHAR(64) PRIMARY KEY,
    projectId VARCHAR(64),
    studentId VARCHAR(64),
    role VARCHAR(64),
    status VARCHAR(64),
    joinedAt TEXT,
    createdAt TEXT,
    updatedAt TEXT
);
CREATE TABLE project_sponsorships (
    id VARCHAR(64) PRIMARY KEY,
    projectId VARCHAR(64),
    enterpriseId VARCHAR(64),
    amount REAL,
    status VARCHAR(64)
);
CREATE TABLE audit_logs (
    id VARCHAR(64) PRIMARY KEY,
    userId VARCHAR(64),
    action VARCHAR(64),
    entityType VARCHAR(64),
    entityId VARCHAR(64),
    requestId VARCHAR(64),
    metadata TEXT
);
CREATE TABLE skills (id VARCHAR(64) PRIMARY KEY, code VARCHAR(64), name VARCHAR(255), status VARCHAR(64));
SQL);
foreach ($outboxMigration->migration->statements('sqlite') as $statement) {
    $schoolPdo->exec($statement);
}
$now = '2026-01-01 00:00:00.000000';
$schoolPdo->exec("INSERT INTO projects (id, schoolId, mentorTeacherId, title, category, topic, description, projectUrl, fundingGoal, startAt, endAt, status, createdAt, updatedAt) VALUES ('proj-outbox', 'school-1', NULL, 'Demo', 'general', NULL, 'Desc', NULL, NULL, NULL, NULL, 'in_progress', '{$now}', '{$now}')");
$schoolPdo->exec("INSERT INTO project_members (id, projectId, studentId, role, status, joinedAt, createdAt, updatedAt) VALUES ('mem-1', 'proj-outbox', 'stu1', 'member', 'active', '{$now}', '{$now}', '{$now}')");
$schoolRepo = new SchoolProjectRepository($schoolPdo);
$firstUpdate = $schoolRepo->updateProject('school-1', 'user-1', 'proj-outbox', ['title' => 'Demo v2']);
$assert(($firstUpdate['title'] ?? null) === 'Demo v2', 'First project update succeeds');
usleep(2000);
$secondUpdate = $schoolRepo->updateProject('school-1', 'user-1', 'proj-outbox', ['title' => 'Demo v3']);
$assert(($secondUpdate['title'] ?? null) === 'Demo v3', 'Second project update succeeds');
$outboxRows = $schoolPdo->query("SELECT aggregate_id, aggregate_version, event_type, delivery_status FROM learner_ai_data_outbox WHERE aggregate_type = 'project' AND aggregate_id = 'proj-outbox' ORDER BY aggregate_version, id")->fetchAll(PDO::FETCH_ASSOC);
$assert(count($outboxRows) === 2, 'Two consecutive project updates write two outbox rows');
$assert((string) $outboxRows[0]['aggregate_version'] !== (string) $outboxRows[1]['aggregate_version'], 'Repeated project updates use distinct aggregate_version values');
$assert(($outboxRows[0]['event_type'] ?? null) === 'project.changed' && ($outboxRows[1]['event_type'] ?? null) === 'project.changed', 'Both outbox rows are project.changed events');
$assert(($outboxRows[0]['delivery_status'] ?? null) === 'pending' && ($outboxRows[1]['delivery_status'] ?? null) === 'pending', 'Both outbox rows are recorded successfully');

echo "learner_project_skill_sync_test: OK\n";
