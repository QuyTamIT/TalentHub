<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Service/ScoreViewer.php';

use TalentHub\Learner\Data\Service\ScoreViewer;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Modules\Business\Service\EnterpriseMatchService;

function createEnterpriseTestDb(): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys=OFF;');

    $pdo->exec(<<<'SQL'
CREATE TABLE users (
    id VARCHAR(64) PRIMARY KEY,
    fullName VARCHAR(255) NOT NULL,
    avatarUrl VARCHAR(255),
    status VARCHAR(64) NOT NULL DEFAULT 'active'
);

CREATE TABLE schools (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(64) NOT NULL
);

CREATE TABLE student_profiles (
    id VARCHAR(64) PRIMARY KEY,
    userId VARCHAR(64) NOT NULL,
    schoolId VARCHAR(64) NOT NULL,
    major VARCHAR(255),
    talentScore DECIMAL(5,2),
    status VARCHAR(64) NOT NULL DEFAULT 'active',
    internshipStatus VARCHAR(64) DEFAULT 'available',
    jobReadiness VARCHAR(64) DEFAULT 'ready',
    talentPoolOptIn INTEGER DEFAULT 1,
    shareWithEnterprise INTEGER DEFAULT 1,
    headline VARCHAR(255),
    bio TEXT,
    location VARCHAR(255),
    phone VARCHAR(64),
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE skills (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'technical',
    status VARCHAR(64) NOT NULL DEFAULT 'active'
);

CREATE TABLE student_skills (
    id VARCHAR(64) PRIMARY KEY,
    studentId VARCHAR(64) NOT NULL,
    skillId VARCHAR(64) NOT NULL,
    levelScore DECIMAL(5,2),
    scoreState VARCHAR(64) DEFAULT 'scored',
    sourceType VARCHAR(64) DEFAULT 'teacher',
    sourceEvaluationId VARCHAR(64),
    sourceEvidenceId VARCHAR(64),
    verificationStatus VARCHAR(64) DEFAULT 'verified',
    verifiedAt DATETIME,
    status VARCHAR(64) DEFAULT 'active'
);

CREATE TABLE enterprise_internship_posts (
    id VARCHAR(64) PRIMARY KEY,
    enterpriseId VARCHAR(64) NOT NULL,
    title VARCHAR(255) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'active',
    location VARCHAR(255)
);

CREATE TABLE enterprise_post_skills (
    id VARCHAR(64) PRIMARY KEY,
    postId VARCHAR(64) NOT NULL,
    skillId VARCHAR(64) NOT NULL,
    weight DECIMAL(3,2) DEFAULT 1.0
);
SQL
    );

    return $pdo;
}

$pdo = createEnterpriseTestDb();

// Seed School & User
$pdo->exec("INSERT INTO schools (id, name, code) VALUES ('sch-1', 'Đại học Công nghệ', 'HUT')");

// Student 1: Valid scored profile with talentScore = 85
$pdo->exec("INSERT INTO users (id, fullName, status) VALUES ('usr-1', 'Nguyen Van A', 'active')");
$pdo->exec("INSERT INTO student_profiles (id, userId, schoolId, major, talentScore) VALUES ('sp-1', 'usr-1', 'sch-1', 'CNTT', 85.0)");

// Student 2: Unscored profile (talentScore = NULL), has only evidence_only & missing_source skills
$pdo->exec("INSERT INTO users (id, fullName, status) VALUES ('usr-2', 'Tran Thi B', 'active')");
$pdo->exec("INSERT INTO student_profiles (id, userId, schoolId, major, talentScore) VALUES ('sp-2', 'usr-2', 'sch-1', 'Marketing', NULL)");

// Skills
$pdo->exec("INSERT INTO skills (id, name, category) VALUES ('sk-php', 'PHP', 'technical')");
$pdo->exec("INSERT INTO skills (id, name, category) VALUES ('sk-sql', 'SQL', 'technical')");
$pdo->exec("INSERT INTO skills (id, name, category) VALUES ('sk-comm', 'Communication', 'soft')");

// Student 1 skills: 1 scored (PHP: 85), 1 missing_source (SQL: 90)
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, scoreState, verificationStatus) VALUES ('ss-1', 'sp-1', 'sk-php', 85.0, 'scored', 'verified')");
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, scoreState, verificationStatus) VALUES ('ss-2', 'sp-1', 'sk-sql', 90.0, 'missing_source', 'pending')");

// Student 2 skills: 1 evidence_only (PHP: null score)
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, scoreState, verificationStatus) VALUES ('ss-3', 'sp-2', 'sk-php', NULL, 'evidence_only', 'verified')");

$repo = new EnterpriseTalentRepository($pdo);
$matchService = new EnterpriseMatchService($repo);

echo "Running Enterprise Official Score Query Tests...\n";

// Test 1: findFeaturedTalents returns talentScore directly, and preserves NULL for unscored student
$featured = $repo->findFeaturedTalents(10);
assert(count($featured) >= 2, "Featured talents should return both active students");

$foundSp1 = null;
$foundSp2 = null;
foreach ($featured as $talent) {
    if ($talent['id'] === 'sp-1') $foundSp1 = $talent;
    if ($talent['id'] === 'sp-2') $foundSp2 = $talent;
}

assert($foundSp1 !== null, "sp-1 should be found in featured talents");
assert((float) $foundSp1['talentScore'] === 85.0, "sp-1 talentScore must be 85.0");

assert($foundSp2 !== null, "sp-2 should be found in featured talents");
assert($foundSp2['talentScore'] === null, "sp-2 talentScore must be NULL, never 0 or ungrounded fallback");
echo "  [PASS] T33: Featured talents preserves NULL for unscored student and returns projected talentScore.\n";

// Test 2: findTalentById preserves NULL talentScore and loads skill scoreState
$talent1 = $repo->findTalentById('sp-1');
assert($talent1 !== null, "sp-1 must be found");
assert((float) $talent1['talentScore'] === 85.0, "sp-1 talentScore should be 85.0");

$skills1 = $repo->skillsWithDetailsForStudent('sp-1');
$phpSkill = null;
$sqlSkill = null;
foreach ($skills1 as $sk) {
    if ($sk['skillId'] === 'sk-php') $phpSkill = $sk;
    if ($sk['skillId'] === 'sk-sql') $sqlSkill = $sk;
}
assert($phpSkill !== null && ($phpSkill['scoreState'] ?? '') === 'scored', "PHP skill must be scored");
assert($sqlSkill !== null && ($sqlSkill['scoreState'] ?? '') === 'missing_source', "SQL skill must be missing_source");

$talent2 = $repo->findTalentById('sp-2');
assert($talent2 !== null, "sp-2 must be found");
assert($talent2['talentScore'] === null, "sp-2 talentScore must be null");
echo "  [PASS] T35: Talent detail preserves NULL talentScore and exposes scoreState.\n";

// Test 3: matchCandidates and EnterpriseMatchService ignore missing_source and evidence_only
$pdo->exec("INSERT INTO enterprise_internship_posts (id, enterpriseId, title) VALUES ('post-1', 'ent-1', 'PHP Developer')");
$pdo->exec("INSERT INTO enterprise_post_skills (id, postId, skillId) VALUES ('ps-1', 'post-1', 'sk-php')");
$pdo->exec("INSERT INTO enterprise_post_skills (id, postId, skillId) VALUES ('ps-2', 'post-1', 'sk-sql')");

$matches = $matchService->matchPostToTalents('post-1', 10);
assert(count($matches) > 0, "Matches should return candidates");

$matchedCandidate1 = null;
$matchedCandidate2 = null;
foreach ($matches as $m) {
    if ($m['candidate']['id'] === 'sp-1') $matchedCandidate1 = $m;
    if ($m['candidate']['id'] === 'sp-2') $matchedCandidate2 = $m;
}

assert($matchedCandidate1 !== null, "sp-1 must match");
// sp-1 has PHP scored, but SQL missing_source. Only PHP should count towards verified matched skills!
$matchedSkillIds = array_column($matchedCandidate1['matched_skills'], 'skillId');
assert(in_array('sk-php', $matchedSkillIds, true), "PHP should be matched");
assert(!in_array('sk-sql', $matchedSkillIds, true), "SQL (missing_source) must NOT be counted as verified matched skill");

// Check recommendation reasons do NOT refer to "Điểm đánh giá năng lực giáo viên" but "Trung bình kỹ năng đã được chấm"
foreach ($matchedCandidate1['reasons'] as $reason) {
    assert(!str_contains($reason, 'Điểm đánh giá năng lực giáo viên'), "Must not use old teacher assessment label");
}

// sp-2 has PHP with evidence_only. Must not be scored skill!
if ($matchedCandidate2 !== null) {
    $matchedSkillIds2 = array_column($matchedCandidate2['matched_skills'], 'skillId');
    assert(!in_array('sk-php', $matchedSkillIds2, true), "PHP (evidence_only) must not be counted as scored verified match");
    // Reason must omit teacher score when talentScore is null
    foreach ($matchedCandidate2['reasons'] as $reason) {
        assert(!str_contains($reason, 'Trung bình kỹ năng'), "Must omit average skill score reason when talentScore is null");
    }
}
echo "  [PASS] T31: Matching ignores missing_source/evidence_only and labels scores transparently.\n";

// Test 4: Assessor name privacy and Test Answers privacy via ScoreViewer
$enterpriseViewer = new ScoreViewer('enterprise', 'ent-user-1', 'ent-1');
assert($enterpriseViewer->canViewAssessorName() === false, "Enterprise viewer cannot view assessor name");
assert($enterpriseViewer->canViewTestAnswers() === false, "Enterprise viewer cannot view student test question answers");

$studentViewer = new ScoreViewer('student', 'usr-1', null);
assert($studentViewer->canViewAssessorName() === true, "Student viewer can view assessor name for own profile");
assert($studentViewer->canViewTestAnswers() === true, "Student viewer can view own test answers");

$teacherViewer = new ScoreViewer('teacher', 'teach-1', 'sch-1');
assert($teacherViewer->canViewAssessorName() === true, "Teacher viewer can view assessor name");
assert($teacherViewer->canViewTestAnswers() === true, "Teacher viewer can view test answers");

echo "  [PASS] Assessor and Test Answer privacy verified across roles.\n";

echo "ALL Enterprise Official Score Query Tests PASSED!\n";
