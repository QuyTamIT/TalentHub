<?php
declare(strict_types=1);

use TalentHub\Bootstrap\Application;
use TalentHub\Http\Request;
use TalentHub\Http\JsonResponse;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/LearnerForwardMigration.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/ForwardMigrationDefinition.php';
require_once dirname(__DIR__) . '/src/Bootstrap/Application.php';

function api_assert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup database tables
$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT, fullName TEXT, passwordHash TEXT, status TEXT, roleId TEXT, avatarUrl TEXT, createdAt TEXT, updatedAt TEXT)');
$pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, code TEXT, name TEXT, description TEXT)');
$pdo->exec('CREATE TABLE permissions (id TEXT PRIMARY KEY, code TEXT, name TEXT, description TEXT)');
$pdo->exec('CREATE TABLE role_permissions (roleId TEXT, permissionId TEXT, PRIMARY KEY(roleId, permissionId))');
$pdo->exec('CREATE TABLE user_roles (userId TEXT, roleId TEXT, PRIMARY KEY(userId, roleId))');
$pdo->exec('CREATE TABLE user_permissions (userId TEXT, permissionId TEXT, isGranted INTEGER, PRIMARY KEY(userId, permissionId))');
$pdo->exec('CREATE TABLE enterprises (id TEXT PRIMARY KEY, name TEXT, status TEXT, verificationStatus TEXT, taxCode TEXT, address TEXT, logoUrl TEXT)');
$pdo->exec('CREATE TABLE enterprise_members (id TEXT PRIMARY KEY, enterpriseId TEXT, userId TEXT, role TEXT, status TEXT)');
$pdo->exec('CREATE TABLE internship_posts (id TEXT PRIMARY KEY, enterpriseId TEXT, title TEXT, description TEXT, skillsJson TEXT, requirementsJson TEXT, status TEXT, deadline TEXT, createdAt TEXT, updatedAt TEXT)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT DEFAULT \'active\')');
$pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, name TEXT, code TEXT, status TEXT)');
$pdo->exec('CREATE TABLE student_skills (id TEXT PRIMARY KEY, studentId TEXT, skillId TEXT, levelScore REAL, verificationStatus TEXT, createdAt TEXT)');
$pdo->exec('CREATE TABLE privacy_consents (id TEXT PRIMARY KEY, studentId TEXT, scope TEXT, isGranted INTEGER, policyVersion TEXT, grantedAt TEXT, revokedAt TEXT, createdAt TEXT)');
$pdo->exec('CREATE TABLE enterprise_talent_access_grants (id TEXT PRIMARY KEY, studentId TEXT, enterpriseId TEXT, consentId TEXT, scope TEXT, grantedAt TEXT, expiresAt TEXT, revokedAt TEXT, createdAt TEXT, updatedAt TEXT)');

$rankingMigration = require dirname(__DIR__) . '/database/migrations/learner/014_create_enterprise_ai_match_rankings.php';
foreach ($rankingMigration->migration->statements('sqlite') as $sql) {
    $pdo->exec($sql);
}

// Seed user, enterprise and permissions
$now = gmdate('Y-m-d H:i:s');
$pdo->exec("INSERT INTO roles VALUES ('role-ent', 'enterprise', 'Enterprise', 'Enterprise Role'), ('role-stu', 'student', 'Student', 'Student Role')");
$pdo->exec("INSERT INTO users VALUES ('user-ent-1', 'ent@example.com', 'Enterprise Admin', 'hash', 'active', 'role-ent', null, '$now', '$now')");
$pdo->exec("INSERT INTO permissions VALUES ('perm-search', 'talent.search_consented', 'Search Consented Talent', '')");
$pdo->exec("INSERT INTO role_permissions VALUES ('role-ent', 'perm-search')");
$pdo->exec("INSERT INTO user_roles VALUES ('user-ent-1', 'role-ent')");

$pdo->exec("INSERT INTO enterprises VALUES ('ent-1', 'Acme Corp', 'active', 'verified', '010101', 'Hanoi', null)");
$pdo->exec("INSERT INTO enterprise_members VALUES ('mem-1', 'ent-1', 'user-ent-1', 'admin', 'active')");

// Seed Job
$pdo->exec("INSERT INTO internship_posts VALUES ('job-active-1', 'ent-1', 'PHP Backend Intern', 'Join our backend team', '[\"PHP\", \"SQL\"]', '[]', 'active', '2099-12-31 00:00:00', '$now', '$now')");
$pdo->exec("INSERT INTO internship_posts VALUES ('job-expired-1', 'ent-1', 'Old Post', 'Old', '[\"PHP\"]', '[]', 'active', '2020-01-01 00:00:00', '$now', '$now')");
$pdo->exec("INSERT INTO internship_posts VALUES ('job-other-ent', 'ent-2', 'Other Corp Post', 'Other', '[\"PHP\"]', '[]', 'active', '2099-12-31 00:00:00', '$now', '$now')");

// Seed Candidate
$pdo->exec("INSERT INTO users VALUES ('user-stu-1', 'stu1@example.com', 'Student One', 'hash', 'active', 'role-stu', null, '$now', '$now')");
$pdo->exec("INSERT INTO student_profiles VALUES ('stu-1', 'user-stu-1', null, 'active')");
$pdo->exec("INSERT INTO skills VALUES ('sk-php', 'PHP', 'php', 'active'), ('sk-sql', 'SQL', 'sql', 'active')");
$pdo->exec("INSERT INTO student_skills VALUES ('ssk-1', 'stu-1', 'sk-php', 90.0, 'verified', '$now'), ('ssk-2', 'stu-1', 'sk-sql', 80.0, 'verified', '$now')");
$pdo->exec("INSERT INTO privacy_consents VALUES ('con-1', 'stu-1', 'enterprise_talent_discovery', 1, '1.0', '$now', null, '$now')");
$pdo->exec("INSERT INTO enterprise_talent_access_grants VALUES ('gr-1', 'stu-1', 'ent-1', 'con-1', 'enterprise_talent_discovery', '$now', '2099-12-31 00:00:00', null, '$now', '$now')");

$_ENV['TALENTHUB_AI_ENABLED'] = 'false';
$app = Application::createWithPdo($pdo);

// Helper to simulate request
$dispatch = static function (Request $req) use ($app): JsonResponse {
    return $app->handle($req);
};

// 1. Missing CSRF / Auth
$unauthReq = new Request('POST', '/api/v1/businesses/me/ai-matches', ['content-type' => 'application/json'], json_encode(['jobId' => 'job-active-1', 'requiredSkills' => ['PHP']]));
$res1 = $dispatch($unauthReq);
api_assert($res1->status === 401 || $res1->status === 403, 'unauthenticated request is rejected');

// Setup mock session
$sessionToken = bin2hex(random_bytes(16));
$csrfToken = bin2hex(random_bytes(16));
$_SESSION = [
    'user' => ['id' => 'user-ent-1', 'email' => 'ent@example.com', 'fullName' => 'Enterprise Admin', 'role' => 'enterprise'],
    'csrf_token' => $csrfToken,
];

// 2. Missing or invalid X-Idempotency-Key
$badKeyReq = new Request('POST', '/api/v1/businesses/me/ai-matches', [
    'content-type' => 'application/json',
    'x-csrf-token' => $csrfToken,
    'x-idempotency-key' => 'short',
], json_encode(['jobId' => 'job-active-1', 'requiredSkills' => ['PHP']]));
$res2 = $dispatch($badKeyReq);
api_assert($res2->status === 422, 'short idempotency key is rejected with 422');

// 3. Disallowed body keys (e.g. injected free text or prompt)
$validKey = 'valid-idempotency-key-123456789';
$badKeysReq = new Request('POST', '/api/v1/businesses/me/ai-matches', [
    'content-type' => 'application/json',
    'x-csrf-token' => $csrfToken,
    'x-idempotency-key' => $validKey,
], json_encode(['jobId' => 'job-active-1', 'requiredSkills' => ['PHP'], 'injectedField' => 'malicious']));
$res3 = $dispatch($badKeysReq);
api_assert($res3->status === 422, 'unknown body field is rejected with 422');

// 4. Protected trait input rejection
$protectedReq = new Request('POST', '/api/v1/businesses/me/ai-matches', [
    'content-type' => 'application/json',
    'x-csrf-token' => $csrfToken,
    'x-idempotency-key' => $validKey,
], json_encode(['jobId' => 'job-active-1', 'requiredSkills' => ['PHP', 'giới tính nam']]));
$res4 = $dispatch($protectedReq);
api_assert($res4->status === 422, 'protected trait in requiredSkills is rejected with 422');

// 5. Expired or Other Enterprise Job
$otherJobReq = new Request('POST', '/api/v1/businesses/me/ai-matches', [
    'content-type' => 'application/json',
    'x-csrf-token' => $csrfToken,
    'x-idempotency-key' => $validKey,
], json_encode(['jobId' => 'job-other-ent', 'requiredSkills' => ['PHP']]));
$res5 = $dispatch($otherJobReq);
api_assert($res5->status === 404, 'other enterprise job returns 404');

// 6. A syntactically valid request reaches the strict service and never receives a rule result.
$validReq = new Request('POST', '/api/v1/businesses/me/ai-matches', [
    'content-type' => 'application/json',
    'x-csrf-token' => $csrfToken,
    'x-idempotency-key' => 'valid-idempotency-key-987654321',
], json_encode(['jobId' => 'job-active-1', 'requiredSkills' => ['PHP']]));
$res6 = $dispatch($validReq);
api_assert($res6->status === 200, 'valid request returns a canonical strict state');
$data6 = $res6->payload['data'] ?? [];
api_assert(($data6['state'] ?? null) === 'provider_unavailable' && ($data6['items'] ?? null) === [], 'disabled provider exposes unavailable state without deterministic AI matches');
api_assert(!str_contains(json_encode($data6), 'ready_rule'), 'endpoint never emits a rule fallback state');

echo "enterprise_ai_matches_api_test: OK\n";
