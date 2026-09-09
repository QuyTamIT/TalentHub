<?php
declare(strict_types=1);
// Creates only a unique test schema; never modifies existing project records.
require_once dirname(__DIR__).'/bin/bootstrap.php';
use TalentHub\Database\Connection;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\Request;
use TalentHub\Http\ApiException;
use TalentHub\Modules\Student\Service\PortfolioHttp;
use TalentHub\Modules\Student\Repository\PortfolioRepository;
$portfolioConfig=require dirname(__DIR__).'/config/database.php';
$portfolioAdmin=(new Connection($portfolioConfig))->connect();
$portfolioSchema='talenthub_sync_review_issue07_'.bin2hex(random_bytes(6));
if(!preg_match('/\Atalenthub_sync_review_issue07_[a-f0-9]{12}\z/',$portfolioSchema)||$portfolioSchema===$portfolioConfig['database'])throw new RuntimeException('Unsafe test schema');
$portfolioCreated=false;
try {
    $portfolioAdmin->exec("CREATE DATABASE `{$portfolioSchema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $portfolioCreated=true;
    $portfolioConfig['database']=$portfolioSchema;
    $portfolioTestPdo=(new Connection($portfolioConfig))->connect();
    $GLOBALS['portfolio_test_pdo']=$portfolioTestPdo;
    require __DIR__.'/learner_portfolio_repository_test.php';
    if($portfolioTestPdo->query('SELECT DATABASE()')->fetchColumn()!==$portfolioSchema)throw new RuntimeException('Wrong runtime schema');
    // Exercise real default notifications and HTTP dispatch, not the injected test notifier.
    $portfolioTestPdo->exec("CREATE TABLE notifications(id CHAR(36) PRIMARY KEY,userId VARCHAR(255),eventKey VARCHAR(191),notificationType VARCHAR(100),title VARCHAR(255),message TEXT,deepLink VARCHAR(255),readAt DATETIME(6),createdAt DATETIME(6),UNIQUE(userId,eventKey));
CREATE TABLE learner_notification_preferences(studentId VARCHAR(255),notificationType VARCHAR(100),inAppEnabled INT,emailEnabled INT,updatedAt DATETIME(6));");
    $repo=new PortfolioRepository($portfolioTestPdo);
    $current=$repo->listForStudent('stu1')['projects'][0]['report'];
    $session=new SessionManager([]);
    $_SESSION=['user'=>['id'=>'us1','role'=>'student'],'csrfToken'=>'fixture-token'];
    $request=static fn(array $body,string $token='fixture-token')=>new Request('POST','/portfolio',['content-type'=>'application/json','x-csrf-token'=>$token],json_encode($body,JSON_THROW_ON_ERROR));
    $input=['kind'=>'project','contextId'=>'p1','expectedVersion'=>(int)$current['version'],'notes'=>'HTTP report','submit'=>true];
    try{PortfolioHttp::handle($portfolioTestPdo,$session,$request($input,'wrong'),'student');throw new RuntimeException('CSRF accepted');}catch(ApiException $e){if($e->status!==403)throw $e;}
    $submitted=PortfolioHttp::handle($portfolioTestPdo,$session,$request($input),'student')['report'];
    $_SESSION=['user'=>['id'=>'ut1','role'=>'teacher'],'csrfToken'=>'fixture-token'];
    $verified=PortfolioHttp::handle($portfolioTestPdo,$session,$request(['kind'=>'project','reportId'=>$submitted['id'],'expectedVersion'=>$submitted['version'],'decision'=>'verified','feedback'=>'Confirmed by assigned mentor','skillIds'=>['sk1']]),'teacher')['report'];
    if($verified['status']!=='verified'||(int)$portfolioTestPdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn()!==2)throw new RuntimeException('HTTP/default notification lifecycle failed');
    $snapshot=$repo->verifiedForStudent('stu1');
    if(count($snapshot['projects'])!==1||count($snapshot['skills'])!==1)throw new RuntimeException('Fresh verified aggregate failed');
    $portfolioTestPdo->exec("INSERT INTO internship_mentor_assignments VALUES ('restored','a1','t1')");
    $internship=$repo->listForStudent('stu1')['internships'][0]['report'];
    $approvedInternship=PortfolioHttp::handle($portfolioTestPdo,$session,$request(['kind'=>'internship','reportId'=>$internship['id'],'expectedVersion'=>$internship['version'],'decision'=>'verified','feedback'=>'Verified completed placement','skillIds'=>['sk1']]),'teacher')['report'];
    require_once dirname(__DIR__).'/app/learner/data/ReadModel/PassportCvViewModel.php';
    $cv=\TalentHub\Learner\Data\ReadModel\PassportCvViewModel::build(['verified_portfolio'=>$repo->verifiedForStudent('stu1'),'internships'=>[['application_id'=>'a1','status'=>'accepted','title'=>'Intern']]],'now');
    if(count($cv['internships'])!==1||!str_contains($cv['internships'][0]['status_label'],'Hoàn thành')||!str_contains($cv['internships'][0]['details'],'120.25'))throw new RuntimeException('Completed internship not linked to CV');
    PortfolioHttp::handle($portfolioTestPdo,$session,$request(['kind'=>'internship','reportId'=>$internship['id'],'expectedVersion'=>$approvedInternship['version'],'decision'=>'revoked','feedback'=>'Withdrawn evidence','skillIds'=>[]]),'teacher');
    if($repo->verifiedForStudent('stu1')['internships']!==[])throw new RuntimeException('Revoked internship remains verified');
    echo "[PASS] MySQL internship approval -> fresh CV completion/decimal hours -> revocation removes evidence\n";
    echo "[PASS] MySQL isolated schema: lifecycle, strict prepared statements, HTTP/CSRF and transactional default notifications\n";
} finally {
    unset($GLOBALS['portfolio_test_pdo']);
    if($portfolioCreated) {
        // Exact random schema created by this process only, never the configured application database.
        $portfolioAdmin->exec("DROP DATABASE `{$portfolioSchema}`");
        echo "[CLEANUP] Removed only the temporary Issue07 test schema\n";
    }
}
