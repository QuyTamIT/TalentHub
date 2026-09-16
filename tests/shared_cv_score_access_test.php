<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/Database/DatabasePassportCvRepository.php';
require_once __DIR__ . '/learner_evidence_backed_scores_test.php';

use TalentHub\Learner\Data\Service\ScoreViewer;
use TalentHub\Learner\Data\Service\ProfileSharingService;
use TalentHub\Learner\Data\Database\DatabasePassportCvRepository;
use TalentHub\Learner\Data\ReadModel\PassportCvViewModel;

$pdo = scoreFixture();
$ids = ['st1'=>'11111111-1111-4111-8111-111111111111', 'u1'=>'22222222-2222-4222-8222-222222222222',
    'c1'=>'33333333-3333-4333-8333-333333333333', 's1'=>'44444444-4444-4444-8444-444444444444'];
foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
    foreach ($pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC) as $col) {
        foreach ($ids as $old=>$new) $pdo->prepare("UPDATE {$table} SET {$col['name']}=? WHERE {$col['name']}=?")->execute([$new,$old]);
    }
}
// No portfolio is needed for this fixture; exercise the actual CV repository and guest page.
$pdo->exec("DROP TABLE project_submissions; DROP TABLE project_members; DROP TABLE projects;
    ALTER TABLE users ADD COLUMN email TEXT DEFAULT 'private@example.test';
    ALTER TABLE classes ADD COLUMN gradeLevel INTEGER DEFAULT 12;
    ALTER TABLE classes ADD COLUMN academicYear TEXT DEFAULT '2026';
    ALTER TABLE student_profiles ADD COLUMN dateOfBirth TEXT;
    ALTER TABLE student_profiles ADD COLUMN phone TEXT DEFAULT 'private-phone';
    CREATE TABLE schools (id TEXT, name TEXT, status TEXT);
    CREATE TABLE student_profile_details (studentId TEXT, location TEXT, bio TEXT, headline TEXT, avatarUrl TEXT);
    CREATE TABLE activities (id TEXT, title TEXT);
    CREATE TABLE experience_logs (id TEXT,studentId TEXT,activityId TEXT,status TEXT,confirmedAt TEXT);
    CREATE TABLE privacy_consents (id TEXT,studentId TEXT,scope TEXT,isGranted INTEGER,revokedAt TEXT);
    CREATE TABLE student_profile_shares (id TEXT,studentId TEXT,consentId TEXT,tokenHash TEXT,sharedFieldsJson TEXT,expiresAt TEXT,revokedAt TEXT,createdAt TEXT)");
$pdo->prepare("INSERT INTO schools VALUES (?,'School','active')")->execute([$ids['s1']]);
$pdo->prepare("INSERT INTO student_profile_details VALUES (?,'private-location','private-bio','private-headline',NULL)")->execute([$ids['st1']]);
$pdo->prepare("INSERT INTO privacy_consents VALUES ('consent',?,'profile_share',1,NULL)")->execute([$ids['st1']]);
$token = str_repeat('a',64);
$pdo->prepare("INSERT INTO student_profile_shares VALUES ('share',?,'consent',?,'[\"fullName\",\"skills\"]','2999-01-01',NULL,'2026-09-15')")->execute([$ids['st1'],hash('sha256',$token)]);
$_SESSION = [];
$viewer = ScoreViewer::fromShareToken($pdo,$token);
$repo = new DatabasePassportCvRepository($pdo,$viewer);
$data = $repo->forStudent($ids['st1']);
$cv = PassportCvViewModel::build($data,'review');
scoreCheck($cv['name']==='Learner' && count($cv['skills'])===1 && $cv['skills'][0]['score']===0,'Valid guest CV lost identity or official skill');
scoreCheck($cv['email']==='' && $cv['phone']==='' && $cv['school']==='' && $cv['headline']==='', 'Unshared personal fields leaked');
scoreCheck($cv['evaluations']===[] && $cv['assessments']===[], 'Private assessment details leaked');
$shared = (new ProfileSharingService($pdo))->resolveShare($token);
scoreCheck(count($shared['skills'])===1, 'Sharing service lost canonical skills');
$qr = ScoreViewer::fromPassportCode($pdo,'TP-11111111');
scoreCheck(count((new DatabasePassportCvRepository($pdo,$qr))->forStudent($ids['st1'])['skills'])===1, 'QR reader cannot open CV');
scoreCheck(scoreDenied(fn()=>ScoreViewer::fromPassportCode($pdo,'TP-%')), 'Wildcard passport accepted');
scoreCheck(scoreDenied(fn()=>(new DatabasePassportCvRepository($pdo))->forStudent($ids['st1'])), 'Anonymous private repository read accepted');

$GLOBALS['__TALENTHUB_TEST_PDO__'] = $pdo;
$_GET = ['token'=>$token];
ob_start();
require dirname(__DIR__).'/app/learner/shared-profile.php';
$html = ob_get_clean();
scoreCheck(http_response_code()===200 && str_contains($html,'Python'), 'Shared CV page did not render official skill');
scoreCheck(!str_contains($html,'private@example.test') && !str_contains($html,'private-phone'), 'Guest HTML leaked contact details');
$pdo->exec("UPDATE student_profile_shares SET sharedFieldsJson='[\"fullName\"]'");
scoreCheck($repo->forStudent($ids['st1'])['skills']===[], 'Removed skills consent not respected');
$pdo->exec("UPDATE student_profile_shares SET revokedAt='2026-09-15'");
scoreCheck(scoreDenied(fn()=>$repo->forStudent($ids['st1'])), 'Revoked share still renders CV');
echo "shared_cv_score_access_test: OK\n";
