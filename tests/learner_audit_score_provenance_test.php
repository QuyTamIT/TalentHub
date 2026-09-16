<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Service/ScoreViewer.php';
require_once dirname(__DIR__) . '/app/learner/data/Service/StatisticsService.php';

use TalentHub\Learner\Data\Service\ScoreViewer;
use TalentHub\Learner\Data\Service\StatisticsService;

function createAuditTestDb(): PDO
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
    email VARCHAR(255) NOT NULL,
    role VARCHAR(32) NOT NULL DEFAULT 'student',
    status VARCHAR(64) NOT NULL DEFAULT 'active'
);

CREATE TABLE student_profiles (
    id VARCHAR(64) PRIMARY KEY,
    userId VARCHAR(64) NOT NULL,
    talentScore DECIMAL(5,2),
    createdAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    updatedAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE skills (
    id VARCHAR(64) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(64) NOT NULL DEFAULT 'technical'
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
    verifiedAt DATETIME
);

CREATE TABLE learner_evidences (
    id VARCHAR(64) PRIMARY KEY,
    learnerId VARCHAR(64) NOT NULL,
    skillId VARCHAR(64) NOT NULL,
    status VARCHAR(64) NOT NULL DEFAULT 'verified',
    revokedAt DATETIME
);
SQL
    );

    return $pdo;
}

echo "Running Learner Audit & Score Provenance Tests (P6)...\n";

$pdo = createAuditTestDb();

// Seed user & profile
$pdo->exec("INSERT INTO users (id, fullName, email, role) VALUES ('usr-100', 'Le Van C', 'levanc@example.com', 'student')");
$pdo->exec("INSERT INTO student_profiles (id, userId, talentScore) VALUES ('sp-100', 'usr-100', 95.0)");

// Seed skills
$pdo->exec("INSERT INTO skills (id, name) VALUES ('sk-1', 'Python'), ('sk-2', 'Docker'), ('sk-3', 'Git')");

// Seed student_skills:
// 1. Legitimate scored skill with evidence
$pdo->exec("INSERT INTO learner_evidences (id, learnerId, skillId, status) VALUES ('ev-1', 'sp-100', 'sk-1', 'verified')");
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, scoreState, sourceType, sourceEvidenceId) VALUES ('ss-1', 'sp-100', 'sk-1', 80.0, 'scored', 'teacher', 'ev-1')");

// 2. Legacy unlinked score (no evaluationId, no evidenceId) -> should become missing_source
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, scoreState, sourceType) VALUES ('ss-2', 'sp-100', 'sk-2', 90.0, 'scored', 'teacher')");

// 3. Skill pointing to revoked evidence -> should be reset
$pdo->exec("INSERT INTO learner_evidences (id, learnerId, skillId, status, revokedAt) VALUES ('ev-3', 'sp-100', 'sk-3', 'revoked', datetime('now'))");
$pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, scoreState, sourceType, sourceEvidenceId) VALUES ('ss-3', 'sp-100', 'sk-3', 85.0, 'scored', 'teacher', 'ev-3')");

// Require the auditor class from bin/audit-score-provenance.php
require_once dirname(__DIR__) . '/bin/audit-score-provenance.php';

// Test 1 (T26): Dry-run does NOT modify database
$auditorDry = new ScoreProvenanceAuditor($pdo, false);
$metricsDry = $auditorDry->run();
assert($metricsDry['unlinked_scores_found'] === 1, "Dry-run must detect 1 unlinked legacy score");
assert($metricsDry['revoked_evidence_found'] === 1, "Dry-run must detect 1 revoked evidence link");

// Verify DB unchanged after dry-run
$checkSkill2 = $pdo->query("SELECT scoreState FROM student_skills WHERE id = 'ss-2'")->fetchColumn();
assert($checkSkill2 === 'scored', "Dry-run must NOT alter scoreState in database");
echo "  [PASS] T26: Dry-run preserves database state intact.\n";

// Test 2 (T20): Apply mode reconciles unlinked scores to 'missing_source' and handles revoked evidence
$auditorApply = new ScoreProvenanceAuditor($pdo, true);
$metricsApply = $auditorApply->run();

$updatedSkill2 = $pdo->query("SELECT scoreState FROM student_skills WHERE id = 'ss-2'")->fetchColumn();
assert($updatedSkill2 === 'missing_source', "Unlinked score must be updated to 'missing_source'");

$updatedSkill3 = $pdo->query("SELECT scoreState, levelScore FROM student_skills WHERE id = 'ss-3'")->fetch(PDO::FETCH_ASSOC);
assert($updatedSkill3['scoreState'] === 'missing_source' || $updatedSkill3['levelScore'] === null, "Revoked skill must be invalidated");
echo "  [PASS] T20: Legacy unlinked scores reconciled to missing_source and revoked evidence reset.\n";

// Test 3 (T21 & talentScore projection): TalentScore recalculated using only valid scored skills
// Valid scored skill is only ss-1 (levelScore = 80.0). So talentScore should be 80.0 (not 95.0, not mean of 80, 90, 85)
$updatedProfile = $pdo->query("SELECT talentScore FROM student_profiles WHERE id = 'sp-100'")->fetchColumn();
assert((float) $updatedProfile === 80.0, "talentScore must be projected from only valid scored skills (80.0), got: " . var_export($updatedProfile, true));
echo "  [PASS] T21: TalentScore recalculation strictly adheres to skill-mean-1.0 without missing_source.\n";

// Test 4 (Zero PII in Auditor logs/output)
$maskedId = ScoreProvenanceAuditor::maskIdentifier('sp-100200300');
assert(!str_contains($maskedId, '100200'), "Masked identifier must not show middle digits");
assert(str_contains($maskedId, '***'), "Masked identifier must contain masking asterisks");
echo "  [PASS] PII Masking: Zero student PII exposure in auditor reports.\n";

// Test 5 (T28): Academic tiering removes arbitrary "Top %"
assert(method_exists(StatisticsService::class, 'formatAcademicClassification'), "StatisticsService must provide academic classification");
$classHigh = StatisticsService::formatAcademicClassification(92.0);
assert($classHigh === 'Xuất sắc', "92.0 must be classified as 'Xuất sắc', got: {$classHigh}");
assert(!str_contains($classHigh, 'Top'), "Must never use 'Top %' tags");

$classMid = StatisticsService::formatAcademicClassification(82.0);
assert($classMid === 'Tốt', "82.0 must be classified as 'Tốt', got: {$classMid}");

$classNull = StatisticsService::formatAcademicClassification(null);
assert($classNull === 'Chưa đủ dữ liệu', "Null score must be classified as 'Chưa đủ dữ liệu'");
echo "  [PASS] T28: Academic classification standard replaces arbitrary Top % rankings.\n";

// Test 6 (T24): HTML escaping in score provenance component
ob_start();
$provenance = [
    'score' => 85,
    'source' => '<script>alert("xss")</script>Teacher',
    'evaluator' => '<b onmouseover=alert(1)>Dr. Minh</b>',
    'timestamp' => '2026-09-15 10:00:00',
    'evidence' => 'Valid & <verified>',
];
include dirname(__DIR__) . '/app/learner/includes/score-provenance.php';
$htmlOutput = ob_get_clean();
assert(!str_contains($htmlOutput, '<script>'), "Score provenance must escape HTML tags in source");
assert(!str_contains($htmlOutput, '<b onmouseover'), "Score provenance must escape HTML tags in evaluator");
assert(str_contains($htmlOutput, '&lt;script&gt;'), "Script tag must be HTML entity encoded");
echo "  [PASS] T24: Score provenance component strictly escapes HTML/XSS payloads.\n";

echo "ALL Learner Audit & Score Provenance Tests PASSED!\n";
