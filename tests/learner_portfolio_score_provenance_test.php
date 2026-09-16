<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Service/EvidenceBackedScoreService.php';
require_once dirname(__DIR__) . '/app/learner/data/Service/ScoreViewer.php';

use TalentHub\Http\ApiException;
use TalentHub\Learner\Data\Service\EvidenceBackedScoreService;
use TalentHub\Learner\Data\Service\ScoreViewer;
use TalentHub\Modules\Student\Repository\PortfolioRepository;
use TalentHub\Support\Uuid;

function createProvenanceTestDb(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys=OFF;');

    $pdo->exec(<<<'SQL'
CREATE TABLE roles (
    id VARCHAR(64) PRIMARY KEY,
    code VARCHAR(64) NOT NULL
);

CREATE TABLE users (
    id VARCHAR(64) PRIMARY KEY,
    roleId VARCHAR(64) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'active',
    fullName VARCHAR(255) NOT NULL
);

CREATE TABLE schools (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE classes (
    id VARCHAR(64) PRIMARY KEY,
    schoolId VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'active'
);

CREATE TABLE student_profiles (
    id VARCHAR(64) PRIMARY KEY,
    userId VARCHAR(64) NOT NULL,
    classId VARCHAR(64) NOT NULL,
    studyStatus VARCHAR(64) NOT NULL DEFAULT 'active',
    talentScore NUMERIC NULL,
    updatedAt TEXT NULL
);

CREATE TABLE teacher_profiles (
    id VARCHAR(64) PRIMARY KEY,
    userId VARCHAR(64) NOT NULL,
    schoolId VARCHAR(64) NOT NULL,
    isSchoolAdmin INTEGER DEFAULT 0
);

CREATE TABLE teacher_class_assignments (
    id VARCHAR(64) PRIMARY KEY,
    teacherId VARCHAR(64) NOT NULL,
    classId VARCHAR(64) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'active'
);

CREATE TABLE projects (
    id VARCHAR(64) PRIMARY KEY,
    schoolId VARCHAR(64) NOT NULL,
    mentorTeacherId VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'in_progress'
);

CREATE TABLE project_members (
    id VARCHAR(64) PRIMARY KEY,
    projectId VARCHAR(64) NOT NULL,
    studentId VARCHAR(64) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'active',
    leftAt TEXT NULL
);

CREATE TABLE enterprises (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE internship_posts (
    id VARCHAR(64) PRIMARY KEY,
    enterpriseId VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'active'
);

CREATE TABLE internship_applications (
    id VARCHAR(64) PRIMARY KEY,
    postId VARCHAR(64) NOT NULL,
    studentId VARCHAR(64) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'accepted'
);

CREATE TABLE internship_mentor_assignments (
    id VARCHAR(64) PRIMARY KEY,
    applicationId VARCHAR(64) UNIQUE NOT NULL,
    mentorTeacherId VARCHAR(64) NOT NULL
);

CREATE TABLE skills (
    id VARCHAR(64) PRIMARY KEY,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'technical',
    status VARCHAR(64) NOT NULL DEFAULT 'active'
);

CREATE TABLE project_submissions (
    id VARCHAR(64) PRIMARY KEY,
    projectId VARCHAR(64) NOT NULL,
    studentId VARCHAR(64) NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    revision INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(64) NOT NULL DEFAULT 'draft',
    notes TEXT NOT NULL,
    repositoryUrl TEXT NULL,
    demoUrl TEXT NULL,
    startDate TEXT NULL,
    endDate TEXT NULL,
    hours NUMERIC NULL,
    stage VARCHAR(64) NULL,
    submittedAt TEXT NULL,
    reviewedAt TEXT NULL,
    reviewedByUserId VARCHAR(64) NULL,
    feedback TEXT NULL,
    updatedAt TEXT NOT NULL
);

CREATE TABLE learner_internship_reports (
    id VARCHAR(64) PRIMARY KEY,
    applicationId VARCHAR(64) NOT NULL,
    studentId VARCHAR(64) NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    revision INTEGER NOT NULL DEFAULT 1,
    status VARCHAR(64) NOT NULL DEFAULT 'draft',
    notes TEXT NOT NULL,
    repositoryUrl TEXT NULL,
    demoUrl TEXT NULL,
    startDate TEXT NULL,
    endDate TEXT NULL,
    hours NUMERIC NULL,
    stage VARCHAR(64) NULL,
    submittedAt TEXT NULL,
    reviewedAt TEXT NULL,
    reviewedByUserId VARCHAR(64) NULL,
    feedback TEXT NULL,
    updatedAt TEXT NOT NULL
);

CREATE TABLE learner_portfolio_skills (
    kind VARCHAR(32) NOT NULL,
    reportId VARCHAR(64) NOT NULL,
    skillId VARCHAR(64) NOT NULL
);
CREATE TABLE project_skill_tags(projectId TEXT,skillId TEXT);

CREATE TABLE learner_portfolio_history (
    id VARCHAR(64) PRIMARY KEY,
    kind VARCHAR(32) NOT NULL,
    reportId VARCHAR(64) NOT NULL,
    version INTEGER NOT NULL,
    status VARCHAR(64) NOT NULL,
    actorUserId VARCHAR(64) NOT NULL,
    snapshotJson TEXT NOT NULL,
    createdAt TEXT NOT NULL
);

CREATE TABLE learner_evaluations (
    id VARCHAR(64) PRIMARY KEY,
    seriesId VARCHAR(64) NOT NULL,
    revision INTEGER NOT NULL DEFAULT 1,
    studentId VARCHAR(64) NOT NULL,
    teacherId VARCHAR(64) NOT NULL,
    legacyAssessmentId VARCHAR(64) NULL,
    contextType VARCHAR(64) NOT NULL DEFAULT 'general',
    contextId VARCHAR(64) NULL,
    overallScore NUMERIC NULL,
    comment TEXT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'draft',
    publishedAt TEXT NULL,
    actorUserId VARCHAR(64) NOT NULL,
    updatedAt TEXT NULL,
    scoreMethod VARCHAR(64) NULL,
    formulaVersion VARCHAR(64) NULL,
    calculationJson TEXT NULL,
    supersededAt TEXT NULL,
    revokedAt TEXT NULL,
    UNIQUE(seriesId, revision)
);

CREATE TABLE learner_evaluation_items (
    id VARCHAR(64) PRIMARY KEY,
    evaluationId VARCHAR(64) NOT NULL,
    itemKind VARCHAR(64) NOT NULL DEFAULT 'skill',
    itemCode VARCHAR(64) NULL,
    skillId VARCHAR(64) NOT NULL,
    label VARCHAR(255) NULL,
    score NUMERIC NOT NULL,
    maxScore NUMERIC NOT NULL DEFAULT 100,
    confirmed INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE learner_skill_evidence (
    id VARCHAR(64) PRIMARY KEY,
    studentId VARCHAR(64) NOT NULL,
    skillId VARCHAR(64) NOT NULL,
    verificationStatus VARCHAR(50) NOT NULL DEFAULT 'verified',
    evidenceKind VARCHAR(20) NOT NULL DEFAULT 'skill',
    sourceType VARCHAR(32) NOT NULL,
    sourceId VARCHAR(64) NOT NULL,
    sourceVersion INTEGER NOT NULL,
    score NUMERIC NULL,
    observedAt TEXT NOT NULL,
    actorUserId VARCHAR(64) NOT NULL,
    expiresAt TEXT NULL,
    revokedAt TEXT NULL,
    supersedesId VARCHAR(64) NULL,
    eventKey VARCHAR(64) NULL UNIQUE,
    comment TEXT NULL,
    studentSkillId VARCHAR(64) NULL,
    evidenceType VARCHAR(50) NULL,
    evidenceRef VARCHAR(191) NULL,
    createdAt TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE student_skills (
    id VARCHAR(64) PRIMARY KEY,
    studentId VARCHAR(64) NOT NULL,
    skillId VARCHAR(64) NOT NULL,
    levelScore NUMERIC NULL,
    sourceType VARCHAR(32) NOT NULL,
    verificationStatus VARCHAR(50) NOT NULL DEFAULT 'pending',
    verifiedAt TEXT NULL,
    scoreState VARCHAR(32) NULL,
    sourceEvaluationId VARCHAR(64) NULL,
    sourceEvidenceId VARCHAR(64) NULL,
    formulaVersion VARCHAR(64) NULL,
    createdAt TEXT DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(studentId, skillId, sourceType)
);
SQL);

    // Seed base users, roles, schools, classes
    $pdo->exec(<<<'SQL'
INSERT INTO roles VALUES ('r_student', 'student'), ('r_teacher', 'teacher');
INSERT INTO schools VALUES ('sch_1', 'School Alpha'), ('sch_2', 'School Beta');
INSERT INTO classes VALUES ('cls_1', 'sch_1', 'Class 10A', 'active'), ('cls_2', 'sch_2', 'Class 10B', 'active');

-- Students
INSERT INTO users VALUES ('u_stu1', 'r_student', 'active', 'Nguyen Van A');
INSERT INTO student_profiles VALUES ('sp_1', 'u_stu1', 'cls_1', 'active', NULL, CURRENT_TIMESTAMP);

INSERT INTO users VALUES ('u_stu2', 'r_student', 'active', 'Tran Thi B');
INSERT INTO student_profiles VALUES ('sp_2', 'u_stu2', 'cls_1', 'active', NULL, CURRENT_TIMESTAMP);

INSERT INTO users VALUES ('u_stu_other_school', 'r_student', 'active', 'Le Van C');
INSERT INTO student_profiles VALUES ('sp_other', 'u_stu_other_school', 'cls_2', 'active', NULL, CURRENT_TIMESTAMP);

-- Teachers
INSERT INTO users VALUES ('u_t1', 'r_teacher', 'active', 'Teacher Mentor Alpha');
INSERT INTO teacher_profiles VALUES ('tp_1', 'u_t1', 'sch_1', 0);

INSERT INTO users VALUES ('u_t_unassigned', 'r_teacher', 'active', 'Teacher Unassigned Alpha');
INSERT INTO teacher_profiles VALUES ('tp_unassigned', 'u_t_unassigned', 'sch_1', 0);

INSERT INTO users VALUES ('u_t_other_school', 'r_teacher', 'active', 'Teacher Beta');
INSERT INTO teacher_profiles VALUES ('tp_other', 'u_t_other_school', 'sch_2', 0);

-- Skills
INSERT INTO skills VALUES
    ('sk_react', 'react', 'ReactJS', 'technical', 'active'),
    ('sk_node', 'node', 'NodeJS', 'technical', 'active'),
    ('sk_sql', 'sql', 'SQL Database', 'technical', 'active');

-- Projects
INSERT INTO projects VALUES
    ('prj_1', 'sch_1', 'tp_1', 'E-commerce Web App', 'in_progress'),
    ('prj_other_sch', 'sch_2', 'tp_other', 'Beta School Project', 'in_progress');

INSERT INTO project_members VALUES
    ('pm_1', 'prj_1', 'sp_1', 'active', NULL),
    ('pm_2', 'prj_1', 'sp_2', 'active', NULL);
SQL);
    $pdo->exec("INSERT INTO project_skill_tags VALUES ('prj_1','sk_react'),('prj_1','sk_node'),('prj_1','sk_sql')");
    $migration = require dirname(__DIR__) . '/Database/migrations/learner/022_extend_portfolio_skill_evidence.php';
    foreach ($migration->migration->statements('sqlite') as $sql) $pdo->exec($sql);

    return $pdo;
}

function testCheck(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$pdo = createProvenanceTestDb();
$notifications = [];
$repo = new PortfolioRepository($pdo, function (string $type, string $recipient, array $payload) use (&$notifications): void {
    $notifications[] = compact('type', 'recipient', 'payload');
});
$scoreService = new EvidenceBackedScoreService($pdo);
$scoreService->assertSchemaReady(true);

// =========================================================================
// Scenario 1 (T11 - Báo cáo nộp chưa duyệt: Không cấp kỹ năng/điểm)
// =========================================================================
$submitted = $repo->save('sp_1', 'project', 'prj_1', 0, [
    'notes' => 'Hoàn thành module frontend và backend kết nối cơ sở dữ liệu.',
    'repositoryUrl' => 'https://github.com/example/ecommerce',
    'submit' => true,
]);

testCheck($submitted['status'] === 'submitted', 'Scenario 1: Status must be submitted');
testCheck((int)$submitted['version'] === 1, 'Scenario 1: Version must be 1');

// Verify evidence table has NO entries for this report
$evidenceCount = (int)$pdo->query("SELECT COUNT(*) FROM learner_skill_evidence WHERE sourceId = '{$submitted['id']}'")->fetchColumn();
testCheck($evidenceCount === 0, 'Scenario 1 (T11): No evidence created for submitted unreviewed report');

// Verify projection leaves student_skills and talentScore empty
$scoreService->projectOfficialScores('sp_1');
$studentSkillsCount = (int)$pdo->query("SELECT COUNT(*) FROM student_skills WHERE studentId = 'sp_1' AND scoreState = 'scored'")->fetchColumn();
testCheck($studentSkillsCount === 0, 'Scenario 1 (T11): No scored skills generated for student');
$talentScore = $pdo->query("SELECT talentScore FROM student_profiles WHERE id = 'sp_1'")->fetchColumn();
testCheck($talentScore === null, 'Scenario 1 (T11): talentScore must remain NULL for unreviewed report');
echo "Scenario 1 (T11): PASS - Unreviewed report generates no evidence or score\n";

// =========================================================================
// Scenario 2 (T12 - Báo cáo đã duyệt nhưng chưa chấm: evidence_only, score = NULL)
// =========================================================================
$verified = $repo->review('u_t1', 'project', $submitted['id'], 1, 'verified', 'Dự án tốt, kỹ năng phù hợp.', ['sk_react', 'sk_node'], ['sk_react'=>80,'sk_node'=>75]);

testCheck($verified['status'] === 'verified', 'Scenario 2: Status must be verified');

// Check learner_skill_evidence
$evidenceRows = $pdo->query("SELECT * FROM learner_skill_evidence WHERE sourceId = '{$submitted['id']}' ORDER BY skillId")->fetchAll();
testCheck(count($evidenceRows) === 2, 'Scenario 2 (T12): 2 evidence records created for 2 skills');

foreach ($evidenceRows as $ev) {
    testCheck($ev['score'] === null, 'Scenario 2 (T12): Evidence score MUST be NULL for portfolio review');
    testCheck($ev['verificationStatus'] === 'verified', 'Scenario 2 (T12): Verification status must be verified');
    testCheck($ev['evidenceKind'] === 'skill', 'Scenario 2 (T12): evidenceKind must be skill');
    testCheck($ev['sourceType'] === 'project_submission', 'Scenario 2 (T12): sourceType must be project_submission');
    testCheck((int)$ev['sourceVersion'] === 2, 'Scenario 2 (T12): sourceVersion must be 2');
    testCheck($ev['actorUserId'] === 'u_t1', 'Scenario 2 (T12): actorUserId must match teacherUserId');
    testCheck($ev['revokedAt'] === null, 'Scenario 2 (T12): revokedAt must be NULL');
    testCheck(!empty($ev['eventKey']), 'Scenario 2 (T12): eventKey must be populated');
}

// Check student_skills after projection (automatically triggered or projected)
$studentSkills = $pdo->query("SELECT * FROM student_skills WHERE studentId = 'sp_1' ORDER BY skillId")->fetchAll();
testCheck(count($studentSkills) === 2, 'Scenario 2 (T12): student_skills has 2 projection rows');

foreach ($studentSkills as $ss) {
    testCheck($ss['scoreState'] === 'evidence_only', 'Scenario 2 (T12): scoreState must be evidence_only');
    testCheck($ss['levelScore'] === null, 'Scenario 2 (T12): levelScore must be NULL');
    testCheck($ss['verificationStatus'] === 'pending', 'Scenario 2 (T12): verificationStatus must be pending for evidence_only');
    testCheck(!empty($ss['sourceEvidenceId']), 'Scenario 2 (T12): sourceEvidenceId must point to evidence record');
}

// Check student_profiles.talentScore remains NULL
$talentScore = $pdo->query("SELECT talentScore FROM student_profiles WHERE id = 'sp_1'")->fetchColumn();
testCheck($talentScore === null, 'Scenario 2 (T12): talentScore must remain NULL when all skills are evidence_only');

// Query via EvidenceBackedScoreService for student
$viewer = new ScoreViewer('student', 'u_stu1', 'sch_1');
$scores = $scoreService->forStudent('sp_1', $viewer);
testCheck($scores['summary']['score'] === null, 'Scenario 2 (T12): ScoreViewer summary score must be NULL');
testCheck(count($scores['skills']) === 2, 'Scenario 2 (T12): 2 skills visible');
testCheck($scores['skills'][0]['state'] === 'evidence_only', 'Scenario 2 (T12): Skill state is evidence_only');
testCheck($scores['skills'][0]['score'] === null, 'Scenario 2 (T12): Skill score is NULL');
echo "Scenario 2 (T12): PASS - Verified report creates evidence-only skill with NULL score\n";

// =========================================================================
// Scenario 3 (T13 - Chấm điểm đánh giá trên báo cáo hợp lệ: scored, talentScore)
// =========================================================================
// Teacher creates a published evaluation for this verified project submission context
$evalId = Uuid::v4();
$evalItemId = Uuid::v4();
$pdo->prepare(<<<'SQL'
INSERT INTO learner_evaluations (
    id, seriesId, revision, studentId, teacherId, contextType, contextId,
    overallScore, comment, status, publishedAt, actorUserId, scoreMethod, formulaVersion, calculationJson
) VALUES (
    :id, :seriesId, 1, 'sp_1', 'tp_1', 'project_submission', :contextId,
    85.0, 'Đánh giá năng lực dự án xuất sắc', 'published', CURRENT_TIMESTAMP, 'u_t1',
    'rubric_weighted', 'rubric-weighted-1.0', '{"total":85}'
)
SQL)->execute([
    'id' => $evalId,
    'seriesId' => Uuid::v4(),
    'contextId' => $submitted['id'],
]);

$pdo->prepare(<<<'SQL'
INSERT INTO learner_evaluation_items (
    id, evaluationId, itemKind, itemCode, skillId, label, score, maxScore, confirmed
) VALUES (
    :id, :evalId, 'skill', 'react', 'sk_react', 'Kỹ năng ReactJS', 85.0, 100.0, 1
)
SQL)->execute([
    'id' => $evalItemId,
    'evalId' => $evalId,
]);

// Run projection
$scoreService->projectOfficialScores('sp_1');

// Check student_skills
$reactSkill = $pdo->query("SELECT * FROM student_skills WHERE studentId = 'sp_1' AND skillId = 'sk_react'")->fetch();
testCheck($reactSkill['scoreState'] === 'scored', 'Scenario 3 (T13): sk_react transitioned to scored');
testCheck((float)$reactSkill['levelScore'] === 85.0, 'Scenario 3 (T13): sk_react levelScore is 85.0');
testCheck($reactSkill['sourceEvaluationId'] === $evalId, 'Scenario 3 (T13): sourceEvaluationId points to evaluation');

$nodeSkill = $pdo->query("SELECT * FROM student_skills WHERE studentId = 'sp_1' AND skillId = 'sk_node'")->fetch();
testCheck($nodeSkill['scoreState'] === 'evidence_only', 'Scenario 3 (T13): sk_node remains evidence_only');
testCheck($nodeSkill['levelScore'] === null, 'Scenario 3 (T13): sk_node levelScore is NULL');

// Check talentScore is updated to 85.0 (mean of only scored skills)
$talentScore = (float)$pdo->query("SELECT talentScore FROM student_profiles WHERE id = 'sp_1'")->fetchColumn();
testCheck($talentScore === 85.0, 'Scenario 3 (T13): talentScore is exactly 85.0 based on skill-mean-1.0');

// Query through service: evidence_refs contains both evaluation and evidence ID
$scores = $scoreService->forStudent('sp_1', $viewer);
$reactOutput = array_values(array_filter($scores['skills'], fn($s) => $s['skill_id'] === 'sk_react'))[0];
testCheck($reactOutput['state'] === 'scored', 'Scenario 3 (T13): Output state scored');
testCheck($reactOutput['score'] === 85.0, 'Scenario 3 (T13): Output score 85.0');
testCheck(in_array($evalId, $reactOutput['evidence_refs'], true), 'Scenario 3 (T13): evidence_refs has evaluation ID');
testCheck(count($reactOutput['evidence_refs']) >= 2, 'Scenario 3 (T13): evidence_refs has both evaluation and evidence ID');
echo "Scenario 3 (T13): PASS - Evaluation on verified report projects scored skill and talentScore\n";

// =========================================================================
// Scenario 4 (Kiểm soát phân quyền & Cô lập môi trường)
// =========================================================================
// 1. Unassigned teacher from the same school attempts to review
try {
    $repo->review('u_t_unassigned', 'project', $submitted['id'], 2, 'revoked', 'Unauthorized attempt');
    testCheck(false, 'Scenario 4: Unassigned teacher review must be rejected');
} catch (ApiException $e) {
    testCheck($e->status === 403, 'Scenario 4: Must return 403 for unassigned teacher');
}

// 2. Teacher from another school attempts to review
try {
    $repo->review('u_t_other_school', 'project', $submitted['id'], 2, 'revoked', 'Cross-school attempt');
    testCheck(false, 'Scenario 4: Cross-school teacher review must be rejected');
} catch (ApiException $e) {
    testCheck($e->status === 403, 'Scenario 4: Must return 403 for cross-school teacher');
}

// 3. Other student cannot see student 1's scores
$otherViewer = new ScoreViewer('student', 'u_stu2', 'sch_1');
try {
    $scoreService->forStudent('sp_1', $otherViewer);
    testCheck(false, 'Scenario 4: Other learner cannot access peer scores');
} catch (DomainException $e) {
    testCheck($e->getMessage() === 'SCORE_ACCESS_DENIED', 'Scenario 4: Access denied for cross-student read');
}

// 4. Student 2's own scores are completely isolated
$student2Scores = $scoreService->forStudent('sp_2', $otherViewer);
testCheck($student2Scores['summary']['score'] === null, 'Scenario 4: Student 2 has no scores');
testCheck(count($student2Scores['skills']) === 0, 'Scenario 4: Student 2 has no skills');
echo "Scenario 4 (Permission & Isolation): PASS - Unauthorized mentor rejected and cross-learner isolated\n";

// =========================================================================
// Scenario 5 (T14 - Thu hồi minh chứng & Cập nhật điểm phụ thuộc)
// =========================================================================
$revoked = $repo->review('u_t1', 'project', $submitted['id'], 2, 'revoked', 'Phát hiện mã nguồn vi phạm bản quyền.');

testCheck($revoked['status'] === 'revoked', 'Scenario 5: Status must be revoked');
testCheck((int)$revoked['version'] === 3, 'Scenario 5: Version must be 3');

// Check learner_skill_evidence.revokedAt is updated (non-destructive audit)
$revokedEvidence = $pdo->query("SELECT * FROM learner_skill_evidence WHERE sourceId = '{$submitted['id']}'")->fetchAll();
testCheck(count($revokedEvidence) === 2, 'Scenario 5 (T14): Evidence records are preserved');
foreach ($revokedEvidence as $ev) {
    testCheck($ev['revokedAt'] !== null, 'Scenario 5 (T14): revokedAt must be recorded with timestamp');
}

// Check student_skills and talentScore after revocation
$studentSkillsAfterRevoke = $pdo->query("SELECT * FROM student_skills WHERE studentId = 'sp_1' ORDER BY skillId")->fetchAll();
foreach ($studentSkillsAfterRevoke as $ss) {
    testCheck(in_array($ss['scoreState'], ['missing_source', 'unverified'], true), 'Scenario 5 (T14): scoreState demoted from scored/evidence_only');
    testCheck($ss['levelScore'] === null, 'Scenario 5 (T14): levelScore reset to NULL');
}

$talentScoreAfterRevoke = $pdo->query("SELECT talentScore FROM student_profiles WHERE id = 'sp_1'")->fetchColumn();
testCheck($talentScoreAfterRevoke === null, 'Scenario 5 (T14): talentScore automatically reset to NULL');

// Check history in learner_portfolio_history has all versions
$history = $pdo->query("SELECT * FROM learner_portfolio_history WHERE reportId = '{$submitted['id']}' ORDER BY version")->fetchAll();
testCheck(count($history) === 3, 'Scenario 5 (T14): Portfolio history contains draft/submitted, verified, and revoked');
testCheck($history[0]['status'] === 'submitted', 'Scenario 5 (T14): History v1 status');
testCheck($history[1]['status'] === 'verified', 'Scenario 5 (T14): History v2 status');
testCheck($history[2]['status'] === 'revoked', 'Scenario 5 (T14): History v3 status');
echo "Scenario 5 (T14): PASS - Revocation invalidates evidence, resets skill state and talentScore\n";

// =========================================================================
// Scenario 6 (T15 - Idempotency & Chống nhân đôi sự kiện)
// =========================================================================
// Student creates a new revision after revocation and submits
$resubmitted = $repo->save('sp_1', 'project', 'prj_1', 3, [
    'notes' => 'Mã nguồn đã được viết lại toàn bộ hoàn toàn hợp lệ.',
    'newRevision' => true,
    'submit' => true,
]);

testCheck($resubmitted['status'] === 'submitted', 'Scenario 6: Resubmitted status');
testCheck((int)$resubmitted['version'] === 4, 'Scenario 6: Version is 4');

// Mentor verifies again with skill sk_react
$reverified = $repo->review('u_t1', 'project', $submitted['id'], 4, 'verified', 'Mã nguồn viết lại tốt.', ['sk_react'], ['sk_react'=>85]);

// Count evidence records for this report: should have 2 revoked (v2) + 1 new (v5)
$allEvidence = $pdo->query("SELECT * FROM learner_skill_evidence WHERE sourceId = '{$submitted['id']}' ORDER BY observedAt, id")->fetchAll();
$activeEvidence = array_filter($allEvidence, fn($e) => $e['revokedAt'] === null);
testCheck(count($activeEvidence) === 1, 'Scenario 6 (T15): Exactly 1 active evidence record');
$activeEv = array_values($activeEvidence)[0];
testCheck($activeEv['skillId'] === 'sk_react', 'Scenario 6 (T15): Active evidence is sk_react');
testCheck($activeEv['supersedesId'] !== null, 'Scenario 6 (T15): supersedesId points to previous evidence for sk_react');

// Test idempotency of projectOfficialScores: calling multiple times does not duplicate student_skills
$scoreService->projectOfficialScores('sp_1');
$scoreService->projectOfficialScores('sp_1');
$scoreService->projectOfficialScores('sp_1');

$distinctReactRows = (int)$pdo->query("SELECT COUNT(*) FROM student_skills WHERE studentId = 'sp_1' AND skillId = 'sk_react'")->fetchColumn();
testCheck($distinctReactRows === 1, 'Scenario 6 (T15): No duplicate student_skills rows for sk_react');

$evidenceCountPerEventKey = $pdo->query("SELECT eventKey, COUNT(*) as cnt FROM learner_skill_evidence GROUP BY eventKey HAVING cnt > 1")->fetchAll();
testCheck(count($evidenceCountPerEventKey) === 0, 'Scenario 6 (T15): No duplicate eventKey in learner_skill_evidence');

echo "Scenario 6 (T15): PASS - Idempotency verified with no duplicate evidence or projection rows\n";
echo "learner_portfolio_score_provenance_test: OK\n";
