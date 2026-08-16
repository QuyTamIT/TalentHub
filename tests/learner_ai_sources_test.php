<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityExperienceSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseAssessmentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseConsentSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;
use TalentHub\Learner\Ai\Sources\Database\DatabasePublishedEvaluationSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseSkillSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseStudentProfileSource;

function sources_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

function sources_fixture(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, studyStatus TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, category TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE student_skills (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, skillId TEXT NOT NULL, levelScore REAL NOT NULL, sourceType TEXT NOT NULL, verificationStatus TEXT NOT NULL, verifiedAt TEXT NULL, updatedAt TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT NOT NULL, type TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE learner_assessment_versions (id TEXT PRIMARY KEY, testId TEXT NOT NULL, version TEXT NOT NULL, scoringVersion TEXT NOT NULL, status TEXT NOT NULL, publishedAt TEXT NULL)');
    $pdo->exec('CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT NOT NULL, studentId TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE learner_assessment_attempt_metadata (id TEXT PRIMARY KEY, attemptId TEXT NOT NULL, versionId TEXT NOT NULL, status TEXT NOT NULL, submittedAt TEXT NULL)');
    $pdo->exec('CREATE TABLE test_results (id TEXT PRIMARY KEY, attemptId TEXT NOT NULL, resultCode TEXT NOT NULL, dimensionScoresJson TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, category TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT NOT NULL, studentId TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');
    $pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, activityId TEXT NOT NULL, checkinId TEXT NOT NULL, hours REAL NOT NULL, status TEXT NOT NULL, confirmedAt TEXT NULL)');
    $pdo->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, activityId TEXT NOT NULL, overallScore REAL NULL, comment TEXT NULL, status TEXT NOT NULL, publishedAt TEXT NULL)');
    $pdo->exec('CREATE TABLE enterprises (id TEXT PRIMARY KEY, status TEXT NOT NULL, verificationStatus TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT NOT NULL, title TEXT NOT NULL, location TEXT NOT NULL, deadline TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE learner_ai_consent_events (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, scope TEXT NOT NULL, action TEXT NOT NULL, policyVersion TEXT NOT NULL, occurredAt TEXT NOT NULL, requestId TEXT NOT NULL)');

    $pdo->exec("INSERT INTO student_profiles (id, studyStatus) VALUES ('student-a', 'active'), ('student-b', 'graduated')");
    $pdo->exec("INSERT INTO skills (id, code, name, category, status) VALUES ('skill-python', 'python', 'Python', 'technology', 'active'), ('skill-iot', 'iot', 'IoT', 'technology', 'active'), ('skill-rejected', 'rejected', 'Rejected', 'technology', 'active'), ('skill-inactive', 'inactive', 'Inactive', 'technology', 'inactive')");
    $pdo->exec("INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, verifiedAt, updatedAt) VALUES ('student-skill-python', 'student-a', 'skill-python', 87, 'assessment', 'verified', '2026-08-01 09:00:00', '2026-08-01 09:00:00'), ('student-skill-iot', 'student-a', 'skill-iot', 61, 'self_declared', 'self_declared', NULL, '2026-08-02 09:00:00'), ('student-skill-rejected', 'student-a', 'skill-rejected', 98, 'assessment', 'rejected', NULL, '2026-08-03 09:00:00'), ('student-skill-inactive', 'student-a', 'skill-inactive', 90, 'assessment', 'verified', '2026-08-04 09:00:00', '2026-08-04 09:00:00'), ('student-skill-b', 'student-b', 'skill-python', 75, 'assessment', 'verified', '2026-08-05 09:00:00', '2026-08-05 09:00:00')");

    $pdo->exec("INSERT INTO talent_tests (id, code, type, status) VALUES ('test-published', 'holland', 'interest', 'published'), ('test-draft', 'draft-test', 'skills', 'draft')");
    $pdo->exec("INSERT INTO learner_assessment_versions (id, testId, version, scoringVersion, status, publishedAt) VALUES ('version-published', 'test-published', '1.0.0', 'score-1', 'published', '2026-08-01 00:00:00'), ('version-draft', 'test-draft', '1.0.0', 'score-1', 'draft', NULL)");
    $pdo->exec("INSERT INTO test_attempts (id, testId, studentId, status) VALUES ('attempt-published', 'test-published', 'student-a', 'submitted'), ('attempt-draft', 'test-draft', 'student-a', 'submitted'), ('attempt-b', 'test-published', 'student-b', 'submitted')");
    $pdo->exec("INSERT INTO learner_assessment_attempt_metadata (id, attemptId, versionId, status, submittedAt) VALUES ('metadata-published', 'attempt-published', 'version-published', 'submitted', '2026-08-02 11:00:00'), ('metadata-draft', 'attempt-draft', 'version-draft', 'submitted', '2026-08-03 11:00:00'), ('metadata-b', 'attempt-b', 'version-published', 'submitted', '2026-08-04 11:00:00')");
    $pdo->exec("INSERT INTO test_results (id, attemptId, resultCode, dimensionScoresJson) VALUES ('result-published', 'attempt-published', 'RIASEC', '{\"R\":82,\"I\":71}'), ('result-draft', 'attempt-draft', 'DRAFT', '{\"S\":99}'), ('result-b', 'attempt-b', 'B', '{\"A\":65}')");

    $pdo->exec("INSERT INTO activities (id, category) VALUES ('activity-confirmed', 'workshop'), ('activity-pending', 'seminar'), ('activity-b', 'project')");
    $pdo->exec("INSERT INTO activity_registrations (id, activityId, studentId) VALUES ('registration-confirmed', 'activity-confirmed', 'student-a'), ('registration-pending', 'activity-pending', 'student-a'), ('registration-b', 'activity-b', 'student-b')");
    $pdo->exec("INSERT INTO checkins (id, registrationId, status, confirmedAt) VALUES ('checkin-confirmed', 'registration-confirmed', 'confirmed', '2026-08-05 10:00:00'), ('checkin-not-confirmed', 'registration-pending', 'checked_in', NULL), ('checkin-b', 'registration-b', 'confirmed', '2026-08-06 10:00:00')");
    $pdo->exec("INSERT INTO experience_logs (id, studentId, activityId, checkinId, hours, status, confirmedAt) VALUES ('experience-confirmed', 'student-a', 'activity-confirmed', 'checkin-confirmed', 4.5, 'confirmed', '2026-08-05 12:00:00'), ('experience-checkin-pending', 'student-a', 'activity-pending', 'checkin-not-confirmed', 6, 'confirmed', '2026-08-06 12:00:00'), ('experience-pending', 'student-a', 'activity-confirmed', 'checkin-confirmed', 7, 'pending', NULL), ('experience-b', 'student-b', 'activity-b', 'checkin-b', 8, 'confirmed', '2026-08-06 12:00:00')");

    $pdo->exec("INSERT INTO assessments (id, studentId, activityId, overallScore, comment, status, publishedAt) VALUES ('evaluation-published', 'student-a', 'activity-confirmed', 92, 'Teacher-only comment', 'published', '2026-08-07 08:00:00'), ('evaluation-draft', 'student-a', 'activity-confirmed', 100, 'Draft comment', 'draft', NULL), ('evaluation-b', 'student-b', 'activity-b', 80, 'Other learner comment', 'published', '2026-08-08 08:00:00')");

    $pdo->exec("INSERT INTO enterprises (id, status, verificationStatus) VALUES ('enterprise-active', 'active', 'verified'), ('enterprise-inactive', 'inactive', 'verified'), ('enterprise-unverified', 'active', 'pending')");
    $pdo->exec("INSERT INTO internship_posts (id, enterpriseId, title, location, deadline, status) VALUES ('opportunity-active', 'enterprise-active', 'IoT Intern', 'Da Nang', '2026-09-01', 'active'), ('opportunity-inactive', 'enterprise-active', 'Closed Intern', 'Ha Noi', '2026-09-02', 'inactive'), ('opportunity-enterprise-inactive', 'enterprise-inactive', 'Blocked Intern', 'Hue', '2026-09-03', 'active'), ('opportunity-unverified', 'enterprise-unverified', 'Pending Intern', 'Can Tho', '2026-09-04', 'active')");

    $pdo->exec("INSERT INTO learner_ai_consent_events (id, studentId, scope, action, policyVersion, occurredAt, requestId) VALUES ('consent-a1', 'student-a', 'assessment', 'granted', 'policy-1', '2026-08-01 00:00:00', 'request-01'), ('consent-a2', 'student-a', 'assessment', 'revoked', 'policy-1', '2026-08-02 00:00:00', 'request-02'), ('consent-a3', 'student-a', 'skills', 'revoked', 'policy-1', '2026-08-01 00:00:00', 'request-03'), ('consent-a4', 'student-a', 'skills', 'granted', 'policy-1', '2026-08-03 00:00:00', 'request-04'), ('consent-a5', 'student-a', 'activity', 'granted', 'policy-1', '2026-08-04 00:00:00', 'request-05'), ('consent-a6', 'student-a', 'evaluation', 'granted', 'policy-1', '2026-08-05 00:00:00', 'request-06'), ('consent-a7', 'student-a', 'evaluation', 'revoked', 'policy-1', '2026-08-05 00:00:00', 'request-07'), ('consent-b1', 'student-b', 'assessment', 'granted', 'policy-1', '2026-08-01 00:00:00', 'request-08')");

    return $pdo;
}

/** @param array<mixed> $value */
function sources_assert_minimized(array $value): void
{
    $forbiddenKeys = ['email', 'phone', 'date_of_birth', 'user_id', 'student_id', 'teacher_id', 'full_name', 'comment', 'token', 'password', 'cv_url'];
    $walk = static function (array $item) use (&$walk, $forbiddenKeys): void {
        foreach ($item as $key => $value) {
            sources_assert(!in_array((string) $key, $forbiddenKeys, true), "minimized source excludes {$key}");
            if (is_array($value)) {
                $walk($value);
            }
        }
    };
    $walk($value);
}

$repositoryRoot = dirname(__DIR__);
$bootstrap = $repositoryRoot . '/app/learner/ai/bootstrap.php';
sources_assert(is_file($bootstrap), 'Task 6 learner AI bootstrap exists');
require_once $bootstrap;

$pdo = sources_fixture();
$studentSource = new DatabaseStudentProfileSource($pdo);
$skillSource = new DatabaseSkillSource($pdo);
$assessmentSource = new DatabaseAssessmentSource($pdo);
$experienceSource = new DatabaseActivityExperienceSource($pdo);
$evaluationSource = new DatabasePublishedEvaluationSource($pdo);
$opportunitySource = new DatabaseOpportunitySource($pdo);
$consentSource = new DatabaseConsentSource($pdo);
$consentPolicy = new ConsentPolicy($consentSource);

$profile = $studentSource->forStudent('student-a');
sources_assert($profile === ['study_status' => 'active'], 'student profile returns only the learner study status');
sources_assert($studentSource->forStudent("student-a' OR 1=1 --") === [], 'student profile query binds the student id');

$skills = $skillSource->forStudent('student-a');
sources_assert(count($skills) === 2, 'skills exclude rejected, inactive, and other learner rows');
sources_assert($skills[0]['verification_status'] === 'verified', 'verified skill preserves verification state');
sources_assert($skills[1]['verification_status'] === 'self_declared', 'self-declared skill preserves verification state');
sources_assert($skills[0]['verified_at'] === '2026-08-01T09:00:00+00:00', 'skill timestamp is RFC 3339');

$assessments = $assessmentSource->forStudent('student-a');
sources_assert(count($assessments) === 1, 'assessment source returns only submitted results on published definitions and versions');
sources_assert($assessments[0]['result_code'] === 'RIASEC', 'assessment source returns minimized scored result');
sources_assert($assessments[0]['dimension_scores'] === ['R' => 82, 'I' => 71], 'assessment source decodes the fixed score payload');
sources_assert($assessments[0]['submitted_at'] === '2026-08-02T11:00:00+00:00', 'assessment timestamp is RFC 3339');

$experience = $experienceSource->forStudent('student-a');
sources_assert(count($experience) === 1, 'experience exposes hours only after confirmed check-in and confirmed experience');
sources_assert($experience[0]['hours'] === 4.5, 'confirmed experience exposes its minimized hours');
sources_assert($experience[0]['confirmed_at'] === '2026-08-05T12:00:00+00:00', 'experience timestamp is RFC 3339');

$evaluations = $evaluationSource->forStudent('student-a');
sources_assert(count($evaluations) === 1, 'published evaluation source excludes drafts and other learner rows');
sources_assert($evaluations[0]['overall_score'] === 92.0, 'published evaluation returns score but no teacher comment');
sources_assert($evaluations[0]['published_at'] === '2026-08-07T08:00:00+00:00', 'published evaluation timestamp is RFC 3339');

$contractUnavailable = new PDO('sqlite::memory:');
$contractUnavailable->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, studentId TEXT NOT NULL, activityId TEXT NOT NULL, overallScore REAL NULL, status TEXT NOT NULL)');
sources_assert((new DatabasePublishedEvaluationSource($contractUnavailable))->forStudent('student-a') === [], 'published evaluation fails closed when the publishedAt contract is unavailable');

$opportunities = $opportunitySource->forStudent('student-a');
sources_assert(count($opportunities) === 1, 'opportunity source returns only active opportunities from active verified enterprises');
sources_assert($opportunities[0]['title'] === 'IoT Intern', 'opportunity source keeps a minimized opportunity record');
sources_assert($opportunities[0]['deadline_at'] === '2026-09-01T00:00:00+00:00', 'opportunity deadline is RFC 3339');
sources_assert($opportunitySource->forStudent('unknown-student') === [], 'opportunity query binds and checks the student id');

$events = $consentSource->forStudent('student-a');
sources_assert(count($events) === 7, 'consent source returns only the learner append-only events');
sources_assert($events[0]['occurred_at'] === '2026-08-05T00:00:00+00:00', 'consent timestamp is RFC 3339');
sources_assert($consentPolicy->allowedScopes('student-a') === ['activity', 'skills'], 'consent policy returns only scopes whose latest append-only action is granted');
sources_assert($consentPolicy->allowedScopes('student-b') === ['assessment'], 'consent policy does not use another learner events');

sources_assert_minimized([$profile, $skills, $assessments, $experience, $evaluations, $opportunities, $events]);

echo "learner_ai_sources_test: OK\n";
