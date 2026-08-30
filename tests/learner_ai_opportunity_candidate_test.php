<?php

declare(strict_types=1);

use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;
use TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource;
use TalentHub\Learner\Ai\Sources\Database\DatabaseOpportunitySource;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

function candidate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "candidate_contract_violation={$message}\n");
        exit(1);
    }
}

function candidate_expect_invalid(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException) {
        return;
    }
    candidate_assert(false, $message);
}

function candidate_test_input(array $overrides = []): RecommendationInput
{
    $payload = array_merge([
        'education_band' => 'high',
        'skills' => [
            ['code' => 'python', 'score' => 82, 'verification_status' => 'verified'],
            ['code' => 'sql', 'score' => 35, 'verification_status' => 'active'],
        ],
        'assessments' => [
            [
                'dimension_scores' => ['Logical Thinking' => 88, 'creativity' => 61],
                'submitted_at' => '2026-08-01T00:00:00.000000+00:00',
            ],
        ],
        'activities' => [
            ['experience_id' => 'exp-1', 'activity_category' => 'Robotics Club', 'hours' => 20],
            ['experience_id' => 'exp-2', 'activity_category' => 'STEM', 'tags' => ['IoT', 'python']],
        ],
        'profile' => ['grade_level' => 11, 'class_name' => '12A1'],
        'gender' => 'female',
        'email' => 'student@example.com',
    ], $overrides);

    return new RecommendationInput($payload, [], [], [
        ['source_type' => 'skill', 'source_id' => 'python-skill-1', 'observed_at' => '2026-08-01T00:00:00.000000+00:00', 'safe_value' => ['code' => 'python', 'level_score' => 82]],
        ['source_type' => 'opportunity', 'source_id' => 'internship-1', 'observed_at' => '2026-08-20T00:00:00.000000+00:00', 'safe_value' => ['title' => 'Data Internship']],
    ]);
}

$profile = LearnerOpportunityProfile::fromInput(candidate_test_input([
    'education_band' => 'high',
    'skills' => [
        ['code' => 'python', 'score' => 82, 'verification_status' => 'verified'],
        ['code' => 'sql', 'score' => 35, 'verification_status' => 'active'],
    ],
]));
candidate_assert($profile->educationBand() === 'high', 'profile education band');
candidate_assert($profile->skillScore('python') === 82, 'verified python skill score');
candidate_assert($profile->skillScore('email') === null, 'unknown skill score is null');
candidate_assert($profile->skills() === ['python' => 82, 'sql' => 35], 'skills map');
candidate_assert($profile->assessmentDimensions() === ['logical_thinking' => 88.0, 'creativity' => 61.0], 'assessment dimensions');
candidate_assert($profile->experienceTags() === ['robotics_club', 'stem', 'iot', 'python'], 'experience tags');
candidate_assert($profile->evidenceRefs() === ['skill:python-skill-1', 'opportunity:internship-1'], 'evidence refs');
candidate_assert($profile->skillScore('PYTHON') === 82, 'skill lookup is code-normalized');

$normalizedProfile = LearnerOpportunityProfile::fromInput(candidate_test_input([
    'skills' => [['code' => 'IoT cơ bản', 'score' => 55], ['code' => 'data_analysis', 'level_score' => 70.4]],
]));
candidate_assert($normalizedProfile->skillScore('iot_co_ban') === 55, 'vietnamese display name normalizes to canonical code');
candidate_assert($normalizedProfile->skillScore('data_analysis') === 70, 'level_score normalizes to integer');

$gradeProfile = LearnerOpportunityProfile::fromInput(candidate_test_input(['education_band' => null]));
candidate_assert($gradeProfile->educationBand() === 'high', 'grade 11 derives high band');
$collegeProfile = LearnerOpportunityProfile::fromInput(candidate_test_input(['education_band' => null, 'profile' => ['grade_level' => 13]]));
candidate_assert($collegeProfile->educationBand() === 'college', 'grade 13 derives college band');

candidate_expect_invalid(
    static fn (): LearnerOpportunityProfile => LearnerOpportunityProfile::fromInput(candidate_test_input(['education_band' => 'doctorate'])),
    'unknown education band rejected'
);
candidate_expect_invalid(
    static fn (): LearnerOpportunityProfile => LearnerOpportunityProfile::fromInput(candidate_test_input([
        'skills' => [['code' => 'python', 'score' => 82], ['code' => 'Python', 'score' => 40]],
    ])),
    'duplicate canonical skill code rejected'
);
candidate_expect_invalid(
    static fn (): LearnerOpportunityProfile => LearnerOpportunityProfile::fromInput(candidate_test_input([
        'skills' => [['code' => 'python', 'score' => 150]],
    ])),
    'out of range skill score rejected'
);

$candidate = OpportunityCandidate::fromEvidence([
    'source_type' => 'opportunity',
    'source_id' => 'internship-1',
    'safe_value' => [
        'catalog_id' => 'internship-1',
        'item_type' => 'internship',
        'title' => 'Data Internship',
        'provider_name' => 'Verified Enterprise',
        'required_skills' => [['code' => 'python', 'minimum_score' => 60], ['code' => 'sql', 'minimum_score' => 50]],
        'learning_outcomes' => [['code' => 'dashboard', 'label' => 'Dashboard dữ liệu']],
        'education_bands' => ['high', 'college'],
        'deadline_at' => '2026-10-01T00:00:00.000000+00:00',
        'availability' => ['remaining' => 2],
        'status' => 'active',
        'url' => '/app/learner/ecosystem.php?tab=opportunities&focus=internship-1',
    ],
]);
candidate_assert($candidate->catalogId() === 'internship-1', 'candidate catalog id');
candidate_assert($candidate->catalogType() === 'internship', 'candidate catalog type');
candidate_assert($candidate->title() === 'Data Internship', 'candidate title');
candidate_assert($candidate->providerName() === 'Verified Enterprise', 'candidate provider name');
candidate_assert($candidate->canonicalUrl() === '/app/learner/ecosystem.php?tab=opportunities&focus=internship-1', 'candidate canonical url');
candidate_assert($candidate->requiredSkills() === [
    ['code' => 'python', 'minimum_score' => 60, 'label' => 'python'],
    ['code' => 'sql', 'minimum_score' => 50, 'label' => 'sql'],
], 'candidate required skills');
candidate_assert($candidate->learningOutcomes() === [['code' => 'dashboard', 'label' => 'Dashboard dữ liệu']], 'candidate learning outcomes');
candidate_assert($candidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === true, 'active candidate is eligible');

$payload = $candidate->providerPayload();
candidate_assert(($payload['catalog_id'] ?? '') === 'internship-1' && ($payload['title'] ?? '') === 'Data Internship', 'provider payload exposes canonical fields');
candidate_assert(!array_key_exists('gender', $payload) && !array_key_exists('eligibility', $payload), 'provider payload is allow-listed');

$displaySkillCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => [
        'catalog_id' => 'project-7',
        'item_type' => 'project',
        'title' => 'Smart Campus IoT',
        'required_skills' => [['code' => 'IoT cơ bản', 'minimum_score' => 0]],
        'url' => 'https://talenthub.vn/projects/smart-campus-iot',
    ],
]);
candidate_assert(array_column($displaySkillCandidate->requiredSkills(), 'code') === ['iot_co_ban'], 'display skill name normalizes to canonical code');
candidate_assert($displaySkillCandidate->canonicalUrl() === 'https://talenthub.vn/projects/smart-campus-iot', 'verified https external url accepted');

$closedCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c2', 'item_type' => 'project', 'title' => 'Closed', 'status' => 'closed', 'url' => '/x'],
]);
candidate_assert($closedCandidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'closed candidate is not eligible');

$expiredCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c3', 'item_type' => 'project', 'title' => 'Expired', 'status' => 'active', 'deadline_at' => '2026-08-01T00:00:00.000000+00:00', 'url' => '/x'],
]);
candidate_assert($expiredCandidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'expired candidate is not eligible');

$fullCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c4', 'item_type' => 'project', 'title' => 'Full', 'status' => 'active', 'availability' => ['remaining' => 0], 'url' => '/x'],
]);
candidate_assert($fullCandidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'full candidate is not eligible');

$collegeOnlyCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c5', 'item_type' => 'project', 'title' => 'College only', 'status' => 'active', 'education_bands' => ['college'], 'url' => '/x'],
]);
candidate_assert($collegeOnlyCandidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'education band mismatch is not eligible');

$noBandProfile = LearnerOpportunityProfile::fromInput(candidate_test_input(['education_band' => null, 'profile' => []]));
candidate_assert($collegeOnlyCandidate->isEligibleFor($noBandProfile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'unknown learner band is not eligible against restricted candidate');
$openCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c6', 'item_type' => 'project', 'title' => 'Open', 'status' => 'active', 'education_bands' => ['high', 'college'], 'url' => '/x'],
]);
candidate_assert($openCandidate->isEligibleFor($noBandProfile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'restricted candidate rejects null learner band');
$unrestrictedCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c7', 'item_type' => 'project', 'title' => 'Unrestricted', 'status' => 'active', 'url' => '/x'],
]);
candidate_assert($unrestrictedCandidate->isEligibleFor($noBandProfile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === true, 'candidate without education bands accepts null learner band');

$missingStatusCandidate = OpportunityCandidate::fromEvidence([
    'safe_value' => ['catalog_id' => 'c7-no-status', 'item_type' => 'project', 'title' => 'Missing status', 'url' => '/x'],
]);
candidate_assert($missingStatusCandidate->isEligibleFor($profile, new DateTimeImmutable('2026-08-29T00:00:00Z')) === false, 'candidate without canonical status is not eligible');

candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'missing-type', 'title' => 'Missing type', 'status' => 'active', 'url' => '/x']]),
    'missing canonical catalog type rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'missing-url', 'item_type' => 'project', 'title' => 'Missing URL', 'status' => 'active']]),
    'missing canonical URL rejected'
);

candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c8', 'item_type' => 'project', 'title' => '', 'url' => '/x']]),
    'empty title rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => '', 'item_type' => 'project', 'title' => 'X', 'url' => '/x']]),
    'empty catalog id rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c9', 'item_type' => 'project', 'title' => 'X', 'url' => 'javascript:alert(1)']]),
    'javascript url rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c10', 'item_type' => 'project', 'title' => 'X', 'url' => 'data:text/html,x']]),
    'data url rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c11', 'item_type' => 'project', 'title' => 'X', 'url' => '//evil.example.com/x']]),
    'protocol-relative url rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c12', 'item_type' => 'project', 'title' => 'X', 'url' => 'http://external.example.com/x']]),
    'plain http external url rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c13', 'item_type' => 'project', 'title' => 'X', 'education_bands' => ['phd'], 'url' => '/x']]),
    'unknown education band rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c14', 'item_type' => 'project', 'title' => 'X', 'required_skills' => [['code' => 'python', 'minimum_score' => 101]], 'url' => '/x']]),
    'out of range minimum score rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c15', 'item_type' => 'project', 'title' => 'X', 'required_skills' => [['code' => 'python', 'minimum_score' => 10], ['code' => 'Python', 'minimum_score' => 20]], 'url' => '/x']]),
    'duplicate canonical skill code rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c16', 'item_type' => 'project', 'title' => 'X', 'required_skills' => [['code' => 'Must know SQL and be able to write production ready queries against relational databases', 'minimum_score' => 0]], 'url' => '/x']]),
    'requirement prose rejected as skill code'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c17', 'item_type' => 'project', 'title' => 'X', 'gender' => 'female', 'url' => '/x']]),
    'protected trait key rejected'
);
candidate_expect_invalid(
    static fn (): OpportunityCandidate => OpportunityCandidate::fromEvidence(['safe_value' => ['catalog_id' => 'c18', 'item_type' => 'project', 'title' => 'X', 'deadline_at' => 'not-a-date', 'url' => '/x']]),
    'malformed deadline rejected'
);

$sourcePdo = new PDO('sqlite::memory:');
$sourcePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sourcePdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, classId TEXT, tenantId TEXT, gradeLevel INTEGER)');
$sourcePdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, gradeLevel INTEGER)');
$sourcePdo->exec('CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT, status TEXT, verificationStatus TEXT)');
$sourcePdo->exec('CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, location TEXT, deadline TEXT, status TEXT, slots INTEGER, createdAt TEXT, description TEXT, benefits TEXT, educationLevel TEXT, skillsJson TEXT, requirementsJson TEXT, field TEXT, workType TEXT, duration TEXT)');
$sourcePdo->exec('CREATE TABLE projects (id TEXT PRIMARY KEY, schoolId TEXT, title TEXT, status TEXT, updatedAt TEXT, category TEXT, description TEXT, projectUrl TEXT, endAt TEXT)');
$sourcePdo->exec('CREATE TABLE learner_ai_catalog_items (catalog_id TEXT PRIMARY KEY, item_type TEXT, category TEXT, title TEXT, summary TEXT, publish_status TEXT, deadline_at TEXT, eligibility_json TEXT, capacity INTEGER, enrolled_count INTEGER, url TEXT, action_json TEXT, school_id TEXT, tenant_id TEXT, updated_at TEXT, provider_name TEXT, location TEXT, difficulty TEXT, required_skills_json TEXT, learning_outcomes_json TEXT, education_bands_json TEXT)');

$sourcePdo->exec("INSERT INTO classes VALUES ('class-db-1','school-db-1',11)");
$sourcePdo->exec("INSERT INTO student_profiles VALUES ('student-db-1','class-db-1','school-db-1',11)");
$sourcePdo->exec("INSERT INTO enterprises VALUES ('enterprise-db-1','Verified Enterprise','active','verified')");
$sourcePdo->exec(<<<'SQL'
INSERT INTO internship_posts VALUES (
  'internship-db-1','enterprise-db-1','Data Internship','Ha Noi','2026-10-01 00:00:00','active',3,'2026-08-20 00:00:00',
  'Build a real analytics dashboard.','Mentoring and project feedback.','THPT',
  '["Python",{"name":"SQL cơ bản","minimum_score":45}]',
  '["Hoàn thành bài tập thử SQL và trình bày kết quả"]','data','hybrid','8 weeks'
)
SQL);
$sourcePdo->exec(<<<'SQL'
INSERT INTO projects VALUES (
  'project-expired','school-db-1','Expired School Project','in_progress','2026-07-01 00:00:00',
  'project','This project has expired.','/app/learner/projects.php?id=project-expired','2026-08-01 00:00:00'
)
SQL);
$projectId = '50000000-0000-4000-8000-000000000001';
$sourcePdo->exec(<<<SQL
INSERT INTO projects VALUES (
  '{$projectId}','school-db-1','EcoSmart AI','in_progress','2026-08-21 00:00:00',
  'career_technical','A current school project.','https://github.com/talenthub-demo/ecosmart-ai','2026-11-01 00:00:00'
)
SQL);

$catalogInsert = $sourcePdo->prepare('INSERT INTO learner_ai_catalog_items VALUES (:id,:type,:category,:title,:summary,:status,:deadline,:eligibility,:capacity,:enrolled,:url,:action,:school,:tenant,:updated,:provider,:location,:difficulty,:skills,:outcomes,:bands)');
$catalogInsert->execute([
    'id' => 'catalog-safe', 'type' => 'project', 'category' => 'data', 'title' => 'Safe Data Project',
    'summary' => 'Canonical catalog project.', 'status' => 'published', 'deadline' => '2026-10-15 00:00:00',
    'eligibility' => '{"grade_levels":["11"]}', 'capacity' => 5, 'enrolled' => 1,
    'url' => '/app/learner/projects.php?id=catalog-safe', 'action' => '{"type":"view_project","project_id":"catalog-safe"}',
    'school' => 'school-db-1', 'tenant' => 'school-db-1', 'updated' => '2026-08-20 00:00:00',
    'provider' => 'TalentHub School', 'location' => 'Ha Noi', 'difficulty' => 'intermediate',
    'skills' => '[{"code":"python","minimum_score":60,"label":"Python"}]',
    'outcomes' => '[{"code":"dashboard","label":"Dashboard dữ liệu"}]', 'bands' => '["high"]',
]);
$catalogInsert->execute([
    'id' => 'catalog-protected', 'type' => 'project', 'category' => 'data', 'title' => 'Protected Project',
    'summary' => 'Must never be exposed.', 'status' => 'published', 'deadline' => '2026-10-15 00:00:00',
    'eligibility' => '{"gender":"female","grade_levels":["11"]}', 'capacity' => 5, 'enrolled' => 0,
    'url' => '/app/learner/projects.php?id=catalog-protected', 'action' => '{"type":"view_project","project_id":"catalog-protected"}',
    'school' => 'school-db-1', 'tenant' => 'school-db-1', 'updated' => '2026-08-20 00:00:00',
    'provider' => 'Unsafe Provider', 'location' => 'Ha Noi', 'difficulty' => 'introductory',
    'skills' => '[]', 'outcomes' => '[]', 'bands' => '["high"]',
]);

$sourceClock = new DateTimeImmutable('2026-08-29T00:00:00Z');
$opportunityRows = (new DatabaseOpportunitySource($sourcePdo, $sourceClock))->forStudent('student-db-1');
candidate_assert(count($opportunityRows) === 1, 'database opportunity source returns the active internship');
$internship = $opportunityRows[0];
candidate_assert(($internship['provider_name'] ?? null) === 'Verified Enterprise', 'database opportunity source uses canonical enterprise name');
candidate_assert(($internship['summary'] ?? null) === 'Build a real analytics dashboard.', 'database opportunity source exposes canonical description');
candidate_assert(($internship['benefits'] ?? null) === 'Mentoring and project feedback.', 'database opportunity source exposes canonical benefits');
candidate_assert(($internship['education_bands'] ?? null) === ['high'], 'database opportunity source maps THPT to high education band');
candidate_assert(array_column($internship['required_skills'] ?? [], 'code') === ['python', 'sql_co_ban'], 'database opportunity source canonicalizes skillsJson skill codes');
candidate_assert(!in_array('hoan_thanh_bai_tap_thu_sql_va_trinh_bay_ket_qua', array_column($internship['required_skills'] ?? [], 'code'), true), 'requirements prose never becomes a skill code');
candidate_assert(($internship['requirements'] ?? null) === ['Hoàn thành bài tập thử SQL và trình bày kết quả'], 'requirementsJson remains display prose');

$internshipCandidate = OpportunityCandidate::fromEvidence([
    'source_type' => 'opportunity',
    'source_id' => (string) $internship['catalog_id'],
    'safe_value' => $internship,
]);
candidate_assert($internshipCandidate->providerName() === 'Verified Enterprise', 'database-backed candidate preserves canonical provider');
candidate_assert($internshipCandidate->isEligibleFor($profile, $sourceClock), 'database-backed active internship passes hard gates');

$catalogRows = (new DatabaseCatalogSource($sourcePdo, $sourceClock))->readForStudent('student-db-1');
candidate_assert(array_column($catalogRows, 'catalog_id') === ['catalog-safe', $projectId], 'catalog source includes the active same-school project and excludes expired/protected rows');
$catalogRow = array_values(array_filter($catalogRows, static fn (array $row): bool => ($row['catalog_id'] ?? '') === 'catalog-safe'))[0];
candidate_assert(($catalogRow['provider_name'] ?? null) === 'TalentHub School', 'catalog evidence exposes provider name');
candidate_assert(($catalogRow['location'] ?? null) === 'Ha Noi' && ($catalogRow['difficulty'] ?? null) === 'intermediate', 'catalog evidence exposes location and difficulty');
candidate_assert(($catalogRow['required_skills'][0]['code'] ?? null) === 'python', 'catalog evidence exposes required skills');
candidate_assert(($catalogRow['learning_outcomes'][0]['code'] ?? null) === 'dashboard', 'catalog evidence exposes learning outcomes');
candidate_assert(($catalogRow['education_bands'] ?? null) === ['high'], 'catalog evidence exposes education bands');
$projectRow = array_values(array_filter($catalogRows, static fn (array $row): bool => ($row['catalog_id'] ?? '') === $projectId))[0];
candidate_assert(
    ($projectRow['url'] ?? '') === '/app/learner/project.php?id=' . rawurlencode($projectId),
    'school project catalog always emits the internal detail URL'
);
candidate_assert(!str_contains((string) ($projectRow['url'] ?? ''), 'github.com'), 'GitHub demo URL is never canonical');

echo "learner_ai_opportunity_candidate_test: OK\n";
