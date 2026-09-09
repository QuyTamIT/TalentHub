<?php
declare(strict_types=1);
require dirname(__DIR__).'/bin/bootstrap.php';
use TalentHub\Database\Connection;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Http\Request;
use TalentHub\Modules\Student\Service\PortfolioHttp;
$pdo=(new Connection(require dirname(__DIR__).'/config/database.php'))->connect();
$session=new SessionManager([]);
$query=$pdo->query("SELECT u.id,r.code FROM users u JOIN roles r ON r.id=u.roleId WHERE u.status='active' AND r.code IN ('student','teacher') ORDER BY r.code,u.id");
$counts=['student'=>0,'teacher'=>0];
foreach($query->fetchAll(PDO::FETCH_ASSOC) as $user){
    $role=$user['code'];if($counts[$role]>=2)continue;
    $_SESSION=['user'=>['id'=>$user['id'],'role'=>$role]];
    $payload=PortfolioHttp::handle($pdo,$session,new Request('GET','/portfolio',[],'',[],['studentId'=>'not-used']),$role);
    if(!isset($payload['csrfToken']))throw new RuntimeException('Missing CSRF bootstrap');
    $counts[$role]++;
}
if($counts['student']===0||$counts['teacher']===0)throw new RuntimeException('Needs existing active learner and teacher for local read probe');
echo '[PASS] Read-only local MySQL GET: '.$counts['student'].' learners, '.$counts['teacher'].' teachers; no record writes or personal output'.PHP_EOL;
