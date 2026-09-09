<?php
declare(strict_types=1);

// Test-only local router; no production route, and no fallback to the application's database.
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'test'
    || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'],true)) {
    http_response_code(404); exit;
}
$root=dirname(__DIR__);
$path=realpath((string)getenv('RUBRIC_TEST_MANIFEST'));
if ($path===false || !str_starts_with(str_replace('\\','/',$path),str_replace('\\','/',realpath($root.'/.codex_tmp')).'/')) {
    http_response_code(503);exit('Missing disposable manifest.');
}
$manifest=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
if (empty($manifest['username']) || empty($manifest['password'])) { http_response_code(503);exit('Missing scoped fixture credentials.'); }
foreach (['DB_HOST'=>$manifest['host'],'DB_PORT'=>(string)$manifest['port'],'DB_DATABASE'=>$manifest['database'],
    'DB_USERNAME'=>$manifest['username'],'DB_PASSWORD'=>$manifest['password'],'TALENTHUB_LEARNER_SOURCE'=>'database',
    'TALENTHUB_ALLOW_DEMO_AUTOLOGIN'=>'false','SESSION_SAVE_PATH'=>$root.'/.codex_tmp/competency-sessions'] as $key=>$value) {
    $_ENV[$key]=$_SERVER[$key]=$value;putenv($key.'='.$value);
}
require __DIR__.'/teacher_competency_fixture.php';
$pdo=(new TalentHub\Database\Connection(require $root.'/config/database.php'))->connect();
competencyVerify($pdo,$manifest);
$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($uri==='/__rubric_session') {
    $key=$_GET['user'] ?? '';
    if (!in_array($key,['teacherUser','studentUser','otherTeacherUser','otherStudentUser','secondStudentUser'],true)) { http_response_code(400);exit; }
    $id=$manifest['ids'][$key];
    $user=(new TalentHub\Auth\Service\AuthService(new TalentHub\Auth\Repository\AuthRepository($pdo)))->current($id);
    $session=new TalentHub\Auth\Session\SessionManager(array_merge(require $root.'/config/session.php',[
        'name'=>TalentHub\Auth\Session\SessionManager::sessionNameForRole($user['role']),
    ]));
    $session->start();$session->login($user);
    header('Content-Type: application/json');echo json_encode(['ok'=>true]);return;
}
$allowed=['/app/teacher/assessments/index.php','/app/teacher/grading.php','/app/learner/evaluation.php','/app/learner/talent-passport.php','/app/learner/api/v1/notifications.php'];
if (in_array($uri,$allowed,true)) {
    $_SERVER['SCRIPT_NAME']=$uri;
    $_SERVER['SCRIPT_FILENAME']=$root.$uri;
    require $root.$uri;return;
}
if (preg_match('#\A/(?:app/learner/)?assets/[a-zA-Z0-9_./-]+\.(css|js|png|jpe?g|svg|webp|woff2?|ico)\z#',$uri)
    && !str_contains($uri,'..')) return false;
http_response_code(404);
