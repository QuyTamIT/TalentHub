<?php
declare(strict_types=1);
require dirname(__DIR__).'/bin/bootstrap.php';
use TalentHub\Database\Connection;
// Read-only integration against configured local DB. No account/profile/migration mutations.
$pdo=(new Connection(require dirname(__DIR__).'/config/database.php'))->connect();
$mode=$argv[1]??'student';
$record=$pdo->query('SELECT sp.id,sp.userId FROM student_profiles sp JOIN users u ON u.id=sp.userId WHERE u.status=\'active\' ORDER BY sp.id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$record) throw new RuntimeException('Requires one existing local learner for read-only route test');
putenv('TALENTHUB_ALLOW_DEMO_AUTOLOGIN=false');
$GLOBALS['__TALENTHUB_TEST_PDO__']=$pdo;
$GLOBALS['__TALENTHUB_TEST_SESSION__']=[];
if ($mode!=='guest') $GLOBALS['__TALENTHUB_TEST_SESSION__']['user']=['id'=>$record['userId'],'role'=>$mode==='student'?'student':'teacher','status'=>'active'];
$_SERVER['REQUEST_METHOD']='GET';
$_GET['studentId']='other-student'; // Must be ignored: ownership comes from session.
ob_start();
require dirname(__DIR__).'/app/learner/talent-passport-cv.php';
$html=ob_get_clean();
$status=http_response_code() ?: 200;
if ($mode==='student' && ($status!==200 || !str_contains($html,'data-cv-content'))) throw new RuntimeException('Authenticated CV rendering failed');
if ($mode!=='student' && (!in_array($status,[401,403],true) || str_contains($html,'data-cv-content'))) throw new RuntimeException('Unauthenticated/other-role access not denied');
echo '[PASS] CV route '.$mode.' HTTP '.$status.PHP_EOL;
