<?php
declare(strict_types=1);

use TalentHub\Http\ApiException;
use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Modules\Business\Service\EnterpriseMatchService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/LearnerForwardMigration.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/ForwardMigrationDefinition.php';
require_once dirname(__DIR__) . '/src/Modules/Business/Repository/EnterpriseTalentRepository.php';
require_once dirname(__DIR__) . '/src/Modules/Business/Service/EnterpriseMatchService.php';

function enterprise_match_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE enterprise_talent_access_grants (id TEXT, studentId TEXT, enterpriseId TEXT, consentId TEXT, scope TEXT, grantedAt TEXT, expiresAt TEXT, revokedAt TEXT)');
$pdo->exec('CREATE TABLE privacy_consents (id TEXT, studentId TEXT, scope TEXT, isGranted INTEGER, revokedAt TEXT)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT DEFAULT \'active\')');
$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT, status TEXT)');
$pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, name TEXT, code TEXT, status TEXT)');
$pdo->exec('CREATE TABLE student_skills (id TEXT, studentId TEXT, skillId TEXT, levelScore REAL, verificationStatus TEXT)');
$pdo->exec('CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, description TEXT, skillsJson TEXT, requirementsJson TEXT, status TEXT, deadline TEXT, createdAt TEXT, updatedAt TEXT)');

$migration = require dirname(__DIR__) . '/database/migrations/learner/014_create_enterprise_ai_match_rankings.php';
foreach ($migration->migration->statements('sqlite') as $sql) {
    $pdo->exec($sql);
}

$now = gmdate('Y-m-d H:i:s');
$pdo->exec("INSERT INTO users VALUES ('u1', 'Alice', 'active'), ('u2', 'Bob', 'active'), ('u3', 'Cathy', 'active')");
$pdo->exec("INSERT INTO student_profiles VALUES ('s1', 'u1', null, 'active'), ('s2', 'u2', null, 'active'), ('s3', 'u3', null, 'active')");
$pdo->exec("INSERT INTO skills VALUES ('k1', 'PHP', 'php', 'active'), ('k2', 'SQL', 'sql', 'active'), ('k3', 'Python', 'python', 'active')");
$pdo->exec("INSERT INTO privacy_consents VALUES ('c1', 's1', 'enterprise_talent_discovery', 1, NULL), ('c2', 's2', 'enterprise_talent_discovery', 0, '2026-01-01'), ('c3', 's3', 'enterprise_talent_discovery', 1, NULL)");
$pdo->exec("INSERT INTO enterprise_talent_access_grants VALUES ('g1', 's1', 'e1', 'c1', 'enterprise_talent_discovery', '2026-01-01', '2099-01-01', NULL), ('g2', 's2', 'e1', 'c2', 'enterprise_talent_discovery', '2026-01-01', '2099-01-01', NULL), ('g3', 's3', 'e1', 'c3', 'enterprise_talent_discovery', '2026-01-01', '2099-01-01', NULL)");
$pdo->exec("INSERT INTO student_skills VALUES ('ss1', 's1', 'k1', 90, 'verified'), ('ss2', 's1', 'k3', 40, 'verified')");

$pdo->exec("INSERT INTO internship_posts VALUES ('job-1', 'e1', 'PHP Developer', 'PHP Backend', '[\"PHP\", \"SQL\"]', '[]', 'active', '2099-12-31 00:00:00', '$now', '$now')");

$repository = new EnterpriseTalentRepository($pdo);
$application = (string) file_get_contents(dirname(__DIR__) . '/src/Bootstrap/Application.php');
$matrix = (string) file_get_contents(dirname(__DIR__) . '/src/Rbac/EndpointPermissionMatrix.php');
enterprise_match_assert(str_contains($application, '/api/v1/businesses/me/ai-matches') && str_contains($matrix, 'POST /api/v1/businesses/me/ai-matches'), 'enterprise AI matching endpoint is RBAC protected');

$capturedPayload = null;
$mockProvider = static function (array $job, array $candidates) use (&$capturedPayload): array {
    $capturedPayload = ['job' => $job, 'candidates' => $candidates];
    // Return model ranking with candidate_ref
    return [
        'model_version' => 'gemini-1.5-pro',
        'items' => [
            [
                'candidate_ref' => 'candidate_1',
                'match_score' => 88.5,
                'reason_codes' => ['verified_skill_match', 'skill_gap'],
            ],
        ],
    ];
};

$service = new EnterpriseMatchService($repository, $mockProvider);

// 1. Model success matching
$ready = $service->match('e1', ['title' => 'Backend developer', 'required_skills' => ['PHP', 'SQL']]);
enterprise_match_assert($ready['state'] === 'ready_model', 'model result is explicit ready_model');
enterprise_match_assert($ready['analysis_origin'] === 'model', 'AI result has model origin');
enterprise_match_assert($ready['freshness_status'] === 'current', 'fresh model ranking is current');
enterprise_match_assert(count($ready['items']) === 1 && $ready['items'][0]['student_id'] === 's1', 'consented candidate is returned');
enterprise_match_assert($ready['items'][0]['match_score'] === 88.5, 'model score is returned');
enterprise_match_assert($ready['items'][0]['matched_skills'] === ['PHP'], 'matched skills are server-derived');
enterprise_match_assert(in_array('SQL', $ready['items'][0]['skill_gaps'], true), 'skill gaps are server-derived');
enterprise_match_assert(in_array('verified_skill_match', $ready['items'][0]['reason_codes'], true), 'reason code explains matching skill');
enterprise_match_assert(!array_filter($ready['items'], static fn(array $item): bool => ($item['student_id'] ?? null) === 's3'), 'service never invents a deterministic AI score for a candidate omitted by the model');

// 2. Privacy of provider payload
$candidateProjections = $capturedPayload['candidates'] ?? [];
enterprise_match_assert(count($candidateProjections) === 2, 'skill-gap candidate remains eligible for provider evaluation');
enterprise_match_assert($candidateProjections[0]['candidate_ref'] === 'candidate_1', 'opaque candidate_ref is used');
enterprise_match_assert(!isset($candidateProjections[0]['student_id']), 'student_id is not exposed to provider');
enterprise_match_assert(!isset($candidateProjections[0]['display_name']), 'display_name is not exposed to provider');

// 3. Provider outage with durable LKG from DB cache
$failingProvider = static function (): array {
    throw new RuntimeException('Gemini service unavailable');
};
$staleService = new EnterpriseMatchService($repository, $failingProvider);
$cached = $staleService->match('e1', ['title' => 'Backend developer', 'required_skills' => ['PHP', 'SQL']]);
enterprise_match_assert($cached['state'] === 'stale_model', 'provider outage uses durable LKG explicitly as stale_model');
enterprise_match_assert($cached['freshness_status'] === 'stale', 'stale model has freshness_status=stale');
enterprise_match_assert($cached['last_known_good'] === true, 'stale model marks last_known_good=true');

$expiredHash = hash('sha256', json_encode(['job_id' => '', 'required_skills' => ['PHP', 'SQL']], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$repository->storeMatchRanking('e1', $expiredHash, [
    'schema_version' => 'enterprise-match-2.0.0',
    'analysis_origin' => 'model',
    'model_version' => 'gemini-1.5-pro',
    'generated_at' => '2020-01-01T00:00:00+00:00',
    'items' => $ready['items'],
]);
$expired = $staleService->match('e1', ['title' => 'Backend developer', 'required_skills' => ['PHP', 'SQL']]);
enterprise_match_assert($expired['state'] === 'provider_unavailable' && $expired['items'] === [], 'expired LKG is never presented as a current or stale model result');

// 4. Provider outage without LKG (new job query)
$unavailable = $staleService->match('e1', ['title' => 'Python Dev', 'required_skills' => ['Python']]);
enterprise_match_assert($unavailable['state'] === 'provider_unavailable', 'new query with outage returns provider_unavailable');
enterprise_match_assert($unavailable['items'] === [], 'no deterministic outage ranking under AI label');
enterprise_match_assert(($unavailable['analysis_origin'] ?? null) === null, 'no rule origin on outage');

// 5. No eligible candidates
$none = $service->match('e2', ['required_skills' => ['Rust']]);
enterprise_match_assert($none['state'] === 'no_candidates' && $none['items'] === [], 'no candidates is explicit');

// 6. Protected-input rejection
$protectedRejected = false;
try {
    $service->match('e1', ['title' => 'Dev', 'required_skills' => ['PHP', 'giới tính nam']]);
} catch (ApiException $e) {
    if ($e->getCode() === 422 || $e->status === 422) {
        $protectedRejected = true;
    }
}
enterprise_match_assert($protectedRejected, 'protected term in job/skills input is rejected with 422 VALIDATION_FAILED');

echo "enterprise_ai_matching_test: OK\n";
