<?php

declare(strict_types=1);

use TalentHub\Modules\Business\Repository\EnterpriseTalentRepository;
use TalentHub\Modules\Business\Service\EnterpriseMatchService;

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/LearnerForwardMigration.php';
require_once dirname(__DIR__) . '/app/learner/data/Migrations/ForwardMigrationDefinition.php';

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
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT)');
$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT, status TEXT)');
$pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, name TEXT, code TEXT, status TEXT)');
$pdo->exec('CREATE TABLE student_skills (id TEXT, studentId TEXT, skillId TEXT, levelScore REAL, verificationStatus TEXT)');
$migration=require dirname(__DIR__).'/Database/migrations/learner/014_create_enterprise_ai_match_rankings.php';foreach($migration->migration->statements('sqlite') as $sql)$pdo->exec($sql);
$pdo->exec("INSERT INTO users VALUES ('u1','Alice','active'),('u2','Bob','active')");
$pdo->exec("INSERT INTO student_profiles VALUES ('s1','u1'),('s2','u2')");
$pdo->exec("INSERT INTO skills VALUES ('k1','PHP','php','active'),('k2','Python','python','active')");
$pdo->exec("INSERT INTO privacy_consents VALUES ('c1','s1','enterprise_talent_discovery',1,NULL),('c2','s2','enterprise_talent_discovery',0,'2026-01-01')");
$pdo->exec("INSERT INTO enterprise_talent_access_grants VALUES ('g1','s1','e1','c1','enterprise_talent_discovery','2026-01-01','2099-01-01',NULL),('g2','s2','e1','c2','enterprise_talent_discovery','2026-01-01','2099-01-01',NULL)");
$pdo->exec("INSERT INTO student_skills VALUES ('ss1','s1','k1',90,'verified'),('ss2','s1','k2',40,'verified')");

$repository = new EnterpriseTalentRepository($pdo);
$application=(string)file_get_contents(dirname(__DIR__).'/src/Bootstrap/Application.php');$matrix=(string)file_get_contents(dirname(__DIR__).'/src/Rbac/EndpointPermissionMatrix.php');enterprise_match_assert(str_contains($application,'/api/v1/businesses/me/ai-matches')&&str_contains($matrix,'POST /api/v1/businesses/me/ai-matches'),'enterprise AI matching endpoint is RBAC protected');
$service = new EnterpriseMatchService($repository);
$provider = static fn(array $job, array $candidates): array => ['s1' => 88.5];
$result = $service->match('e1', ['title' => 'Backend developer', 'required_skills' => ['PHP']], $provider);
enterprise_match_assert($result['state'] === 'ready_model', 'provider ranking is marked as model-ready');
enterprise_match_assert(count($result['items']) === 1 && $result['items'][0]['student_id'] === 's1', 'consent and grant prefilter excludes revoked candidates');
enterprise_match_assert($result['items'][0]['match_score'] === 88.5, 'model score is returned');
enterprise_match_assert(in_array('skill_match', $result['items'][0]['reason_codes'], true), 'reason code explains matching skill');
enterprise_match_assert(isset($result['items'][0]['evidence']) && $result['items'][0]['evidence'] !== [], 'candidate includes evidence');

$again = $service->match('e1', ['required_skills' => ['PHP']], $provider);
enterprise_match_assert(array_column($again['items'], 'student_id') === ['s1'], 'ranking is deterministic');

$outage = (new EnterpriseMatchService($repository, static function (): array { throw new RuntimeException('provider down'); }))
    ->match('e1', ['required_skills' => ['PHP']]);
enterprise_match_assert(in_array($outage['state'], ['provider_outage_cached', 'provider_outage_deterministic'], true), 'provider outage is explicit');
enterprise_match_assert($outage['state']==='provider_outage_cached','provider outage uses durable ranking cache');

$none = $service->match('e1', ['required_skills' => ['Go']]);
enterprise_match_assert($none['state'] === 'no_candidates' && $none['items'] === [], 'no candidates is explicit');

echo "enterprise_ai_matching_test: OK\n";
