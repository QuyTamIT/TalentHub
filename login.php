<?php
declare(strict_types=1);
require __DIR__.'/bin/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Service\LoginRateLimiter;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

$session=new SessionManager(require __DIR__.'/config/session.php');$session->start();
$loginCsrfToken=$session->csrfToken();
$requestedNext=is_string($_GET['next']??null)?$_GET['next']:null;
$requiredRole=is_string($_POST['role_required']??null)
    ? $_POST['role_required']
    : (is_string($_GET['role_required']??null)?$_GET['role_required']:null);

// Role-based access message
$roleMessages=[
    'student'=>['label'=>'Học viên','icon'=>'student','desc'=>'Vui lòng đăng nhập tài khoản Học viên để truy cập khu vực này.'],
    'teacher'=>['label'=>'Giáo viên','icon'=>'teacher','desc'=>'Vui lòng đăng nhập tài khoản Giáo viên để truy cập khu vực này.'],
    'school'=>['label'=>'Nhà trường','icon'=>'school','desc'=>'Vui lòng đăng nhập tài khoản Nhà trường để truy cập khu vực này.'],
    'enterprise'=>['label'=>'Doanh nghiệp','icon'=>'business','desc'=>'Vui lòng đăng nhập tài khoản Doanh nghiệp để truy cập khu vực này.'],
    'admin'=>['label'=>'Quản trị viên','icon'=>'admin','desc'=>'Vui lòng đăng nhập tài khoản Quản trị viên để truy cập khu vực này.'],
    'platform_admin'=>['label'=>'Quản trị viên','icon'=>'admin','desc'=>'Vui lòng đăng nhập tài khoản Quản trị viên để truy cập khu vực này.'],
];
$roleAlert=null;
if($requiredRole!==null&&isset($roleMessages[$requiredRole])){
    $isDbUserValid = static function (?array $candidate): bool {
        if (!$candidate || empty($candidate['id'])) {
            return false;
        }
        try {
            $dbPdo = (new Connection(require __DIR__ . '/config/database.php'))->connect();
            $stmt = $dbPdo->prepare('SELECT id, status FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => (string)$candidate['id']]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return is_array($row) && (($row['status'] ?? '') === 'active');
        } catch (\Throwable) {
            return false;
        }
    };

    $roleSessionName = SessionManager::sessionNameForRole($requiredRole);
    if (isset($_COOKIE[$roleSessionName])) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $rSession = new SessionManager(array_merge(require __DIR__.'/config/session.php', ['name' => $roleSessionName]));
        $rSession->start();
        $rUser = $rSession->user();
        if ($rUser !== null && \TalentHub\Rbac\RoleCodes::matches((string)($rUser['role'] ?? ''), $requiredRole)) {
            if ($isDbUserValid($rUser)) {
                header('Location: '.app_href(AuthPortalRouter::destination((string)$rUser['role'], $requestedNext)));
                exit;
            }
            SessionManager::clearAllRoleSessions();
        }
        session_write_close();
        $session->start();
    }
    $currentUser=$session->user();
    $currentRole=$currentUser['role']??null;
    if($currentRole!==null && \TalentHub\Rbac\RoleCodes::matches((string)$currentRole, $requiredRole)){
        if ($isDbUserValid($currentUser)) {
            header('Location: '.app_href(AuthPortalRouter::destination((string)$currentRole, $requestedNext)));
            exit;
        }
        SessionManager::clearAllRoleSessions();
    }
    $roleAlert=$roleMessages[$requiredRole];
}

$errorMessage=null;$emailValue='';$fieldErrors=[];$flash=$_SESSION['authFlash']??null;unset($_SESSION['authFlash']);
$registrationSucceeded=is_array($flash)&&in_array(($flash['type']??null),['registered','registered-pending'],true);
$registrationPending=is_array($flash)&&($flash['type']??null)==='registered-pending';
if($registrationSucceeded){$emailValue=(string)($flash['email']??'');}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $emailValue=trim((string)($_POST['email']??''));$password=(string)($_POST['password']??'');$requestedNext=is_string($_POST['next']??null)?$_POST['next']:$requestedNext;
    try{
        $session->assertCsrf(is_string($_POST['csrfToken']??null)?$_POST['csrfToken']:null);
        $pdo=(new Connection(require __DIR__.'/config/database.php'))->connect();$repository=new AuthRepository($pdo);$auth=new AuthService($repository);$limiter=new LoginRateLimiter($pdo);$ip=$_SERVER['REMOTE_ADDR']??null;$requestId=RequestId::make(null);
        $user=$auth->login(['email'=>$emailValue,'password'=>$password],$requestId,$ip);
        if($requiredRole!==null && isset($roleMessages[$requiredRole]) && !\TalentHub\Rbac\RoleCodes::matches((string)$user['role'],$requiredRole)){
            throw new ApiException(403,'ROLE_MISMATCH','Tài khoản không thuộc vai trò '.$roleMessages[$requiredRole]['label'].'.');
        }
        $limiter->clearIdentity($emailValue,$ip);
        $session->clearLoginFailures();
        $session->login($user);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user;
        $_SESSION['user_name'] = $user['fullName'] ?? ($user['full_name'] ?? ($user['name'] ?? $user['email']));
        $_SESSION['fullName'] = $user['fullName'] ?? ($user['full_name'] ?? ($user['name'] ?? $user['email']));
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        SessionManager::writeUserToRoleSession($user, require __DIR__.'/config/session.php');
        header('Location: '.app_href(AuthPortalRouter::destination($user['role'],$requestedNext)));exit;
    }catch(ApiException $exception){http_response_code($exception->status);$errorMessage=$exception->getMessage();foreach($exception->details as $detail){$fieldErrors[$detail['field']]=$detail['message'];}if(isset($exception->headers['Retry-After'])){header('Retry-After: '.$exception->headers['Retry-After']);}}
    catch(Throwable $e){error_log('[Login Error] '.$e->getMessage());$errorMessage='Dịch vụ đăng nhập đang tạm thời gián đoạn. Vui lòng thử lại sau.';}
}

function authEscape(mixed $value): string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}

$demoAccounts=[];
$demoAccountsByRole=[];
$testPassword=(string)(Environment::optional('TALENTHUB_TEST_PASSWORD')??'');
$adminPassword=(string)(Environment::optional('TALENTHUB_ADMIN_PASSWORD')??'');
if($testPassword!==''&&$adminPassword!==''){
    $roleMeta=[
        'student'=>['dot'=>'student','label'=>'Học viên','desc'=>'Hồ sơ năng lực, đánh giá và lộ trình phát triển'],
        'teacher'=>['dot'=>'teacher','label'=>'Giáo viên','desc'=>'Chấm điểm, đồng hành và theo dõi học viên'],
        'school'=>['dot'=>'school','label'=>'Nhà trường','desc'=>'Quản trị lớp, giáo viên và báo cáo trường'],
        'enterprise'=>['dot'=>'business','label'=>'Doanh nghiệp','desc'=>'Tuyển dụng, kết nối nhân tài và dự án'],
        'platform_admin'=>['dot'=>'admin','label'=>'Quản trị viên','desc'=>'Quản lý toàn hệ thống và phê duyệt tổ chức'],
    ];
    $studentPriority=[
        'sv.tam.ai@btec.local'=>0,
        'chau.thietke@talenthub.local'=>1,
        'sv.minh.design@btec.local'=>2,
        'sv.ha.mkt@btec.local'=>3,
        'sv.quang.biz@btec.local'=>4,
        'sv.tuyet.data@btec.local'=>5,
        'sv.duyen.log@btec.local'=>6,
        'sv.an.cn@btec.local'=>7,
    ];
    try{
        $pdo=(new Connection(require __DIR__.'/config/database.php'))->connect();
        $sql="SELECT u.id AS userId,u.email,u.fullName,r.code AS role,r.description AS roleDesc,
                sp.id AS studentId,sp.talentScore,c.name AS className,spd.headline,
                tp.specialization AS teacherSpec,e.name AS enterpriseName
            FROM users u
            INNER JOIN roles r ON r.id=u.roleId
            LEFT JOIN student_profiles sp ON sp.userId=u.id
            LEFT JOIN classes c ON c.id=sp.classId
            LEFT JOIN student_profile_details spd ON spd.studentId=sp.id
            LEFT JOIN teacher_profiles tp ON tp.userId=u.id
            LEFT JOIN enterprise_members em ON em.userId=u.id
            LEFT JOIN enterprises e ON e.id=em.enterpriseId
            WHERE u.status='active'
            ORDER BY FIELD(r.code,'student','teacher','school','enterprise','platform_admin'),u.email ASC";
        $rows=$pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC)?:[];
        $studentIds=[];
        foreach($rows as $row){
            $sid=(string)($row['studentId']??'');
            if($sid!==''){$studentIds[$sid]=true;}
        }
        $assessmentByStudent=[];
        $roadmapByStudent=[];
        $appsByStudent=[];
        if($studentIds!==[]){
            $idList=implode(',',array_map(static fn(string $id):string=>$pdo->quote($id),array_keys($studentIds)));
            foreach($pdo->query(
                "SELECT ta.studentId, COUNT(DISTINCT tt.type) AS completedTypes
                 FROM test_attempts ta
                 INNER JOIN talent_tests tt ON tt.id=ta.testId
                 WHERE ta.status='submitted' AND ta.studentId IN ({$idList})
                 GROUP BY ta.studentId"
            ) as $ar){
                $assessmentByStudent[(string)$ar['studentId']]=(int)$ar['completedTypes'];
            }
            foreach($pdo->query(
                "SELECT studentId, status, confidenceBand, primaryDirectionJson
                 FROM learner_ai_roadmaps
                 WHERE status='active' AND studentId IN ({$idList})"
            ) as $rr){
                $roadmapByStudent[(string)$rr['studentId']]=$rr;
            }
            foreach($pdo->query(
                "SELECT studentId, COUNT(*) AS total,
                        SUM(status IN ('submitted','reviewing','interview','accepted','invited')) AS openCount,
                        SUM(status='accepted') AS acceptedCount
                 FROM internship_applications
                 WHERE studentId IN ({$idList})
                 GROUP BY studentId"
            ) as $ap){
                $appsByStudent[(string)$ap['studentId']]=$ap;
            }
        }
        foreach($rows as $row){
            $role=\TalentHub\Rbac\RoleCodes::canonical((string)($row['role']??''));
            if(!isset($roleMeta[$role])){continue;}
            if($requiredRole!==null&&!\TalentHub\Rbac\RoleCodes::matches($role,$requiredRole)){continue;}
            $email=(string)$row['email'];
            $name=trim((string)($row['fullName']??''));
            $desc=trim((string)($row['roleDesc']??''));
            if($desc===''){$desc=$roleMeta[$role]['desc'];}
            $statusLines=[];
            $badges=[];
            if($role==='student'){
                $studentId=(string)($row['studentId']??'');
                $headline=trim((string)($row['headline']??''));
                $className=trim((string)($row['className']??''));
                if($headline!==''){$desc=$headline;}
                elseif($className!==''){$desc='Sinh viên lớp '.$className;}
                $assessed=$assessmentByStudent[$studentId]??0;
                $statusLines[]=$assessed>=4
                    ? 'Đánh giá: hoàn thành 4/4 bài test'
                    : 'Đánh giá: '.$assessed.'/4 bài test'.($assessed===0?' (chưa làm)':' (đang làm dở)');
                $road=$roadmapByStudent[$studentId]??null;
                if(is_array($road)){
                    $direction='';
                    $raw=(string)($road['primaryDirectionJson']??'');
                    if($raw!==''){
                        $decoded=json_decode($raw,true);
                        if(is_array($decoded)){
                            $direction=trim((string)($decoded['title']??$decoded['label']??$decoded['name']??''));
                        }
                    }
                    $band=trim((string)($road['confidenceBand']??''));
                    $statusLines[]='AI roadmap: đã phân tích '
                        .($direction!==''?' · '.$direction:'')
                        .($band!==''?' ('.$band.')':'');
                    $badges[]=['tone'=>'success','text'=>'Có lộ trình AI'];
                }else{
                    $statusLines[]=$assessed>=4
                        ? 'AI roadmap: đủ dữ liệu, chưa tạo / có thể tạo ngay'
                        : 'AI roadmap: chưa đủ dữ liệu (cần 4/4 bài test)';
                    $badges[]=$assessed>=4
                        ? ['tone'=>'warn','text'=>'Chưa có AI']
                        : ['tone'=>'muted','text'=>'Thiếu đánh giá'];
                }
                if($className!==''){$statusLines[]='Lớp: '.$className;}
                $score=$row['talentScore'];
                if($score!==null&&$score!==''){$statusLines[]='Talent score: '.(string)$score;}
                $apps=$appsByStudent[$studentId]??null;
                if(is_array($apps)&&(int)$apps['total']>0){
                    $statusLines[]='Ứng tuyển TTS: '.(int)$apps['total'].' đơn'
                        .((int)$apps['acceptedCount']>0?' · '.(int)$apps['acceptedCount'].' đã nhận':'')
                        .((int)$apps['openCount']>0?' · '.(int)$apps['openCount'].' đang xử lý':'');
                }
                if($assessed>=4){$badges[]=['tone'=>'success','text'=>'4/4 test'];}
                elseif($assessed>0){$badges[]=['tone'=>'warn','text'=>$assessed.'/4 test'];}
            }elseif($role==='teacher'){
                $spec=trim((string)($row['teacherSpec']??''));
                if($spec!==''){$desc=$spec;$statusLines[]='Chuyên môn: '.$spec;}
            }elseif($role==='enterprise'){
                $ent=trim((string)($row['enterpriseName']??''));
                if($ent!==''){$desc=$ent;$statusLines[]='Doanh nghiệp: '.$ent;}
            }elseif($role==='school'){
                $statusLines[]='Quản trị portal nhà trường BTEC FPT Cần Thơ';
            }elseif($role==='platform_admin'){
                $statusLines[]='Quản trị toàn hệ thống TalentHub';
            }
            $account=[
                'role'=>$role,
                'dot'=>$roleMeta[$role]['dot'],
                'label'=>$roleMeta[$role]['label'],
                'name'=>$name,
                'email'=>$email,
                'password'=>$role==='platform_admin'?$adminPassword:$testPassword,
                'desc'=>$desc,
                'status'=>$statusLines,
                'badges'=>$badges,
                'priority'=>$studentPriority[strtolower($email)]??1000,
            ];
            $demoAccounts[]=$account;
            $demoAccountsByRole[$role][]=$account;
        }
        if(isset($demoAccountsByRole['student'])){
            usort($demoAccountsByRole['student'],static function(array $a,array $b):int{
                return ($a['priority']<=>$b['priority'])?:strcasecmp($a['email'],$b['email']);
            });
        }
    }catch(Throwable){$demoAccounts=[];$demoAccountsByRole=[];}
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="./assets/images/logo.svg" type="image/svg+xml">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="description" content="Đăng nhập TalentHub để tiếp tục vào không gian học tập và quản lý của bạn.">
    <title>Đăng nhập | TalentHub</title>
    <meta name="description" content="Đăng nhập FTalentHub để tiếp tục vào không gian học tập và quản lý của bạn.">
    <title>Đăng nhập | FTalentHub</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/polish.css">
</head>
<body class="auth-page">
<a class="skip-link" href="#main-content">Bỏ qua đến nội dung chính</a>
<main class="auth-layout" id="main-content">
    <section class="auth-brand" aria-labelledby="auth-brand-title">
        <a class="auth-brand__logo" href="<?= htmlspecialchars(function_exists('app_href') ? app_href('/index.php') : './index.php'); ?>" aria-label="FTalentHub - Về trang chủ">
            <img src="<?= htmlspecialchars(function_exists('app_href') ? app_href('/assets/images/talenthub-logo.png') : './assets/images/talenthub-logo.png'); ?>" alt="FTalentHub Logo" class="auth-brand__logo-img">
        </a>
        <div class="auth-brand__content">
            <p class="auth-eyebrow">Một tài khoản, đúng không gian</p>
            <h1 id="auth-brand-title">Tiếp tục hành trình phát triển tài năng</h1>
            <p>FTalentHub tự nhận diện vai trò và đưa bạn đến dashboard phù hợp ngay sau khi đăng nhập.</p>
            <ul class="auth-role-list" aria-label="Các khu vực trên FTalentHub">
                <li><span class="auth-role-dot auth-role-dot--student"></span><strong>Học viên</strong><span>Hồ sơ năng lực và trải nghiệm</span></li>
                <li><span class="auth-role-dot auth-role-dot--teacher"></span><strong>Giáo viên</strong><span>Đồng hành và đánh giá</span></li>
                <li><span class="auth-role-dot auth-role-dot--school"></span><strong>Nhà trường</strong><span>Quản trị và phân tích</span></li>
                <li><span class="auth-role-dot auth-role-dot--business"></span><strong>Doanh nghiệp</strong><span>Kết nối nhân tài</span></li>
            </ul>
        </div>
        <p class="auth-brand__footer">Nền tảng hướng nghiệp và phát triển năng lực toàn diện.</p>
    </section>
    <section class="auth-panel" aria-labelledby="login-title">
        <div class="auth-panel__inner">
            <a class="auth-mobile-logo" href="<?= htmlspecialchars(function_exists('app_href') ? app_href('/index.php') : './index.php'); ?>" aria-label="FTalentHub - Về trang chủ">
                <img src="<?= htmlspecialchars(function_exists('app_href') ? app_href('/assets/images/talenthub-logo.png') : './assets/images/talenthub-logo.png'); ?>" alt="FTalentHub Logo" class="auth-mobile-logo__img">
            </a>
            <div class="auth-heading"><p class="auth-kicker">Chào mừng trở lại</p><h2 id="login-title">Đăng nhập tài khoản</h2><p>Nhập thông tin đã đăng ký hoặc được tổ chức cấp.</p></div>
            <?php if($registrationSucceeded): ?>
                <div class="auth-alert auth-alert--success" role="status">
                    <strong>Đăng ký thành công.</strong>
                    <?php if($registrationPending): ?>
                        <?php if((is_array($flash) && (($flash['role'] ?? '') === 'teacher' || !empty($flash['schoolName'])))): ?>
                            Yêu cầu đã được gửi đến Nhà trường <strong><?= authEscape($flash['schoolName'] ?? 'đã chọn') ?></strong>. Hồ sơ giáo viên đang chờ Nhà trường phê duyệt. Tài khoản sẽ được kích hoạt sau khi Nhà trường xác nhận.
                        <?php else: ?>
                            Yêu cầu đã được gửi đến Admin. Tài khoản chỉ được tạo sau khi hồ sơ được duyệt và yêu cầu chưa xử lý sẽ hết hạn sau 3 ngày.
                        <?php endif; ?>
                    <?php else: ?>
                        Bạn có thể đăng nhập bằng tài khoản vừa tạo.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if(is_array($roleAlert)): ?><div class="auth-alert auth-alert--warning" role="alert"><strong>Yêu cầu đăng nhập <?=authEscape($roleAlert['label'])?>:</strong> <?=authEscape($roleAlert['desc'])?></div><?php endif; ?>
            <?php if($errorMessage!==null): ?><div class="auth-alert auth-alert--error" role="alert"><?=authEscape($errorMessage)?></div><?php endif; ?>
            <?php if($demoAccounts!==[]): ?>
            <div class="auth-demo" data-demo-accounts>
                <p class="auth-demo__title">Tài khoản mẫu để khám phá hệ thống</p>
                <p class="auth-demo__hint"><?= count($demoAccounts) ?> tài khoản · sinh viên có diễn giải trạng thái đánh giá &amp; AI</p>
                <button type="button" class="auth-demo__open" data-open-demo-modal>
                    Xem tất cả tài khoản &amp; đăng nhập nhanh
                </button>
            </div>
            <?php endif; ?>
            <form class="auth-form" method="post" action="<?= htmlspecialchars(app_href('/login.php')) ?>" data-auth-form>
                <input type="hidden" name="csrfToken" value="<?=authEscape($loginCsrfToken)?>">
                <?php if($requestedNext!==null): ?><input type="hidden" name="next" value="<?=authEscape($requestedNext)?>"><?php endif; ?>
                <?php if($requiredRole!==null): ?><input type="hidden" name="role_required" value="<?=authEscape($requiredRole)?>"><?php endif; ?>
                <div class="auth-field"><label for="email">Email</label><input id="email" name="email" type="email" value="<?=authEscape($emailValue)?>" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" required autofocus aria-describedby="email-hint<?php if(isset($fieldErrors['email'])): ?> email-error<?php endif; ?>" <?php if(isset($fieldErrors['email'])): ?>aria-invalid="true"<?php endif; ?>><span id="email-hint" class="auth-field__hint">Email cá nhân hoặc email do tổ chức cấp.</span><?php if(isset($fieldErrors['email'])): ?><span class="auth-field__error" id="email-error"><?=authEscape($fieldErrors['email'])?></span><?php endif; ?></div>
                <div class="auth-field"><div class="auth-field__label-row"><label for="password">Mật khẩu</label></div><div class="auth-password"><input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" required <?php if(isset($fieldErrors['password'])): ?>aria-invalid="true" aria-describedby="password-error"<?php endif; ?>><button type="button" class="auth-password__toggle" data-password-toggle aria-controls="password" aria-pressed="false">Hiện</button></div><?php if(isset($fieldErrors['password'])): ?><span class="auth-field__error" id="password-error"><?=authEscape($fieldErrors['password'])?></span><?php endif; ?></div>
                <button class="auth-submit" type="submit" data-submit><span>Đăng nhập</span><span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Chưa có tài khoản? <a href="<?= htmlspecialchars(app_href('/role-selection.php')) ?>">Chọn vai trò để đăng ký</a></p>
            <a class="auth-back" href="<?= htmlspecialchars(app_href('/index.php')) ?>">← Về trang chủ</a>
        </div>
    </section>
</main>
<?php if($demoAccounts!==[]): ?>
<?php
$roleOrder=['student'=>'Học viên','teacher'=>'Giáo viên','school'=>'Nhà trường','enterprise'=>'Doanh nghiệp','platform_admin'=>'Quản trị'];
$firstRole=null;
foreach($roleOrder as $roleKey=>$roleTitle){
    if(isset($demoAccountsByRole[$roleKey])&&$demoAccountsByRole[$roleKey]!==[]){$firstRole=$roleKey;break;}
}
?>
<div class="auth-demo-modal" id="auth-demo-modal" role="dialog" aria-modal="true" aria-labelledby="auth-demo-modal-title" hidden>
    <div class="auth-demo-modal__backdrop" data-close-demo-modal></div>
    <div class="auth-demo-modal__dialog" tabindex="-1">
        <div class="auth-demo-modal__header">
            <div>
                <h2 id="auth-demo-modal-title">Tài khoản mẫu</h2>
                <p>Chọn tab theo vai trò, rồi đăng nhập nhanh.</p>
            </div>
            <button type="button" class="auth-demo-modal__close" data-close-demo-modal aria-label="Đóng">×</button>
        </div>
        <div class="auth-demo-modal__tabs" role="tablist" aria-label="Vai trò tài khoản">
            <?php foreach($roleOrder as $roleKey=>$roleTitle):
                if(!isset($demoAccountsByRole[$roleKey])||$demoAccountsByRole[$roleKey]===[]){continue;}
                $isActive=$roleKey===$firstRole;
                $count=count($demoAccountsByRole[$roleKey]);
                $dot=$demoAccountsByRole[$roleKey][0]['dot'];
            ?>
            <button type="button"
                class="auth-demo-modal__tab<?= $isActive ? ' is-active' : '' ?>"
                role="tab"
                id="demo-tab-<?=authEscape($roleKey)?>"
                aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                aria-controls="demo-panel-<?=authEscape($roleKey)?>"
                data-demo-tab="<?=authEscape($roleKey)?>">
                <span class="auth-role-dot auth-role-dot--<?=authEscape($dot)?>"></span>
                <span><?=authEscape($roleTitle)?></span>
                <em><?=$count?></em>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="auth-demo-modal__body">
            <?php foreach($roleOrder as $roleKey=>$roleTitle):
                if(!isset($demoAccountsByRole[$roleKey])||$demoAccountsByRole[$roleKey]===[]){continue;}
                $group=$demoAccountsByRole[$roleKey];
                $isActive=$roleKey===$firstRole;
            ?>
            <section class="auth-demo-modal__panel<?= $isActive ? ' is-active' : '' ?>"
                role="tabpanel"
                id="demo-panel-<?=authEscape($roleKey)?>"
                aria-labelledby="demo-tab-<?=authEscape($roleKey)?>"
                data-demo-panel="<?=authEscape($roleKey)?>"
                <?= $isActive ? '' : 'hidden' ?>>
                <ul class="auth-demo-modal__list">
                    <?php foreach($group as $account): ?>
                    <li class="auth-demo-modal__card">
                        <div class="auth-demo-modal__card-main">
                            <div class="auth-demo-modal__identity">
                                <strong><?=authEscape($account['name']!==''?$account['name']:$account['label'])?></strong>
                                <span class="auth-demo-modal__email"><?=authEscape($account['email'])?></span>
                                <span class="auth-demo-modal__desc"><?=authEscape($account['desc'])?></span>
                            </div>
                            <?php if(($account['badges']??[])!==[]): ?>
                            <div class="auth-demo-modal__badges">
                                <?php foreach($account['badges'] as $badge): ?>
                                <span class="auth-demo-badge auth-demo-badge--<?=authEscape($badge['tone'])?>"><?=authEscape($badge['text'])?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php if(($account['status']??[])!==[]): ?>
                            <ul class="auth-demo-modal__status">
                                <?php foreach($account['status'] as $line): ?>
                                <li><?=authEscape($line)?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="auth-demo-modal__login" data-demo-login data-email="<?=authEscape($account['email'])?>" data-password="<?=authEscape($account['password'])?>">
                            Đăng nhập
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="./assets/js/auth.js" defer></script>
</body>
</html>

