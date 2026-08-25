<?php
declare(strict_types=1);
require __DIR__.'/bin/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Service\LoginRateLimiter;
use TalentHub\Auth\Session\SessionManager;
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
];
$roleAlert=null;
if($requiredRole!==null&&isset($roleMessages[$requiredRole])){
    $roleSessionName = SessionManager::sessionNameForRole($requiredRole);
    if (isset($_COOKIE[$roleSessionName])) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $rSession = new SessionManager(array_merge(require __DIR__.'/config/session.php', ['name' => $roleSessionName]));
        $rSession->start();
        $rUser = $rSession->user();
        if ($rUser !== null && \TalentHub\Rbac\RoleCodes::matches((string)($rUser['role'] ?? ''), $requiredRole)) {
            header('Location: '.app_href(AuthPortalRouter::destination((string)$rUser['role'], $requestedNext)));
            exit;
        }
        session_write_close();
        $session->start();
    }
    $currentUser=$session->user();
    $currentRole=$currentUser['role']??null;
    if($currentRole!==null && \TalentHub\Rbac\RoleCodes::matches((string)$currentRole, $requiredRole)){
        header('Location: '.app_href(AuthPortalRouter::destination((string)$currentRole, $requestedNext)));
        exit;
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
        $limiter->assertAllowed($emailValue,$ip);$session->assertLoginAllowed();
        try{$user=$auth->login(['email'=>$emailValue,'password'=>$password],$requestId,$ip);}catch(ApiException $exception){if($exception->errorCode==='INVALID_CREDENTIALS'){$limiter->recordFailure($emailValue,$ip);$session->recordLoginFailure();}throw $exception;}
        if($requiredRole!==null && isset($roleMessages[$requiredRole]) && !\TalentHub\Rbac\RoleCodes::matches((string)$user['role'],$requiredRole)){
            throw new ApiException(403,'ROLE_MISMATCH','Tài khoản không thuộc vai trò '.$roleMessages[$requiredRole]['label'].'.');
        }
        $limiter->clearIdentity($emailValue,$ip);$session->clearLoginFailures();$session->login($user);
        SessionManager::writeUserToRoleSession($user, require __DIR__.'/config/session.php');
        header('Location: '.app_href(AuthPortalRouter::destination($user['role'],$requestedNext)));exit;
    }catch(ApiException $exception){http_response_code($exception->status);$errorMessage=$exception->getMessage();foreach($exception->details as $detail){$fieldErrors[$detail['field']]=$detail['message'];}if(isset($exception->headers['Retry-After'])){header('Retry-After: '.$exception->headers['Retry-After']);}}
    catch(Throwable){$errorMessage='Không thể kết nối dịch vụ đăng nhập. Vui lòng thử lại sau.';}
}

function authEscape(mixed $value): string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <link rel="icon" href="./assets/images/logo.svg" type="image/svg+xml">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Đăng nhập TalentHub để tiếp tục vào không gian học tập và quản lý của bạn.">
    <title>Đăng nhập | TalentHub</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
<main class="auth-layout">
    <section class="auth-brand" aria-labelledby="auth-brand-title">
        <a class="auth-brand__logo" href="./index.php" aria-label="TalentHub - Về trang chủ"><img src="./assets/images/logo.svg" alt="TalentHub" width="200" height="40"></a>
        <div class="auth-brand__content">
            <p class="auth-eyebrow">Một tài khoản, đúng không gian</p>
            <h1 id="auth-brand-title">Tiếp tục hành trình phát triển tài năng</h1>
            <p>TalentHub tự nhận diện vai trò và đưa bạn đến dashboard phù hợp ngay sau khi đăng nhập.</p>
            <ul class="auth-role-list" aria-label="Các khu vực trên TalentHub">
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
            <a class="auth-mobile-logo" href="./index.php"><img src="./assets/images/logo.svg" alt="TalentHub" width="200" height="40"></a>
            <div class="auth-heading"><p class="auth-kicker">Chào mừng trở lại</p><h2 id="login-title">Đăng nhập tài khoản</h2><p>Nhập thông tin đã đăng ký hoặc được tổ chức cấp.</p></div>
            <?php if($registrationSucceeded): ?><div class="auth-alert auth-alert--success" role="status"><strong>Đăng ký thành công.</strong> <?= $registrationPending ? 'Yêu cầu đã được gửi đến Admin. Tài khoản chỉ được tạo sau khi hồ sơ được duyệt và yêu cầu chưa xử lý sẽ hết hạn sau 3 ngày.' : 'Bạn có thể đăng nhập bằng tài khoản vừa tạo.' ?></div><?php endif; ?>
            <?php if(is_array($roleAlert)): ?><div class="auth-alert auth-alert--warning" role="alert"><strong>Yêu cầu đăng nhập <?=authEscape($roleAlert['label'])?>:</strong> <?=authEscape($roleAlert['desc'])?></div><?php endif; ?>
            <?php if($errorMessage!==null): ?><div class="auth-alert auth-alert--error" role="alert"><?=authEscape($errorMessage)?></div><?php endif; ?>
            <form class="auth-form" method="post" action="./login.php" data-auth-form>
                <input type="hidden" name="csrfToken" value="<?=authEscape($loginCsrfToken)?>">
                <?php if($requestedNext!==null): ?><input type="hidden" name="next" value="<?=authEscape($requestedNext)?>"><?php endif; ?>
                <?php if($requiredRole!==null): ?><input type="hidden" name="role_required" value="<?=authEscape($requiredRole)?>"><?php endif; ?>
                <div class="auth-field"><label for="email">Email</label><input id="email" name="email" type="email" value="<?=authEscape($emailValue)?>" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false" maxlength="255" required autofocus aria-describedby="email-hint<?php if(isset($fieldErrors['email'])): ?> email-error<?php endif; ?>" <?php if(isset($fieldErrors['email'])): ?>aria-invalid="true"<?php endif; ?>><span id="email-hint" class="auth-field__hint">Email cá nhân hoặc email do tổ chức cấp.</span><?php if(isset($fieldErrors['email'])): ?><span class="auth-field__error" id="email-error"><?=authEscape($fieldErrors['email'])?></span><?php endif; ?></div>
                <div class="auth-field"><div class="auth-field__label-row"><label for="password">Mật khẩu</label></div><div class="auth-password"><input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" required <?php if(isset($fieldErrors['password'])): ?>aria-invalid="true" aria-describedby="password-error"<?php endif; ?>><button type="button" class="auth-password__toggle" data-password-toggle aria-controls="password" aria-pressed="false">Hiện</button></div><?php if(isset($fieldErrors['password'])): ?><span class="auth-field__error" id="password-error"><?=authEscape($fieldErrors['password'])?></span><?php endif; ?></div>
                <button class="auth-submit" type="submit" data-submit><span>Đăng nhập</span><span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Chưa có tài khoản? <a href="./role-selection.php">Chọn vai trò để đăng ký</a></p>
            <a class="auth-back" href="./index.php">← Về trang chủ</a>
        </div>
    </section>
</main>
<script src="./assets/js/auth.js" defer></script>
</body>
</html>
