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
$requestedNext=is_string($_GET['next']??null)?$_GET['next']:null;
if(($current=$session->user())!==null){header('Location: '.AuthPortalRouter::destination((string)$current['role'],$requestedNext));exit;}

$errorMessage=null;$emailValue='';$fieldErrors=[];$flash=$_SESSION['authFlash']??null;unset($_SESSION['authFlash']);
if(is_array($flash)){$emailValue=(string)($flash['email']??'');}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    $emailValue=trim((string)($_POST['email']??''));$password=(string)($_POST['password']??'');$requestedNext=is_string($_POST['next']??null)?$_POST['next']:$requestedNext;
    try{
        $pdo=(new Connection(require __DIR__.'/config/database.php'))->connect();$repository=new AuthRepository($pdo);$auth=new AuthService($repository);$limiter=new LoginRateLimiter($pdo);$ip=$_SERVER['REMOTE_ADDR']??null;$requestId=RequestId::make(null);
        $limiter->assertAllowed($emailValue,$ip);$session->assertLoginAllowed();
        try{$user=$auth->login(['email'=>$emailValue,'password'=>$password],$requestId,$ip);}catch(ApiException $exception){if($exception->errorCode==='INVALID_CREDENTIALS'){$limiter->recordFailure($emailValue,$ip);$session->recordLoginFailure();}throw $exception;}
        $limiter->clearIdentity($emailValue,$ip);$session->clearLoginFailures();$session->login($user);header('Location: '.AuthPortalRouter::destination($user['role'],$requestedNext));exit;
    }catch(ApiException $exception){$errorMessage=$exception->getMessage();foreach($exception->details as $detail){$fieldErrors[$detail['field']]=$detail['message'];}if(isset($exception->headers['Retry-After'])){header('Retry-After: '.$exception->headers['Retry-After']);}}
    catch(Throwable){$errorMessage='Không thể kết nối dịch vụ đăng nhập. Vui lòng thử lại sau.';}
}

function authEscape(mixed $value): string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
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
        <a class="auth-brand__logo" href="/index.php" aria-label="TalentHub - Về trang chủ"><img src="/assets/images/logo.svg" alt="TalentHub" width="200" height="40"></a>
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
            <a class="auth-mobile-logo" href="/index.php"><img src="/assets/images/logo.svg" alt="TalentHub" width="200" height="40"></a>
            <div class="auth-heading"><p class="auth-kicker">Chào mừng trở lại</p><h2 id="login-title">Đăng nhập tài khoản</h2><p>Nhập thông tin đã đăng ký hoặc được tổ chức cấp.</p></div>
            <?php if(is_array($flash)): ?><div class="auth-alert auth-alert--success" role="status"><strong>Đăng ký thành công.</strong> Bạn có thể đăng nhập bằng tài khoản vừa tạo.</div><?php endif; ?>
            <?php if($errorMessage!==null): ?><div class="auth-alert auth-alert--error" role="alert"><?=authEscape($errorMessage)?></div><?php endif; ?>
            <form class="auth-form" method="post" action="/login.php" data-auth-form>
                <?php if($requestedNext!==null): ?><input type="hidden" name="next" value="<?=authEscape($requestedNext)?>"><?php endif; ?>
                <div class="auth-field"><label for="email">Email</label><input id="email" name="email" type="email" value="<?=authEscape($emailValue)?>" autocomplete="email" inputmode="email" maxlength="255" required autofocus aria-describedby="email-hint"><span id="email-hint" class="auth-field__hint">Email cá nhân hoặc email do tổ chức cấp.</span></div>
                <div class="auth-field"><div class="auth-field__label-row"><label for="password">Mật khẩu</label></div><div class="auth-password"><input id="password" name="password" type="password" autocomplete="current-password" minlength="12" maxlength="255" required><button type="button" class="auth-password__toggle" data-password-toggle aria-controls="password" aria-pressed="false">Hiện</button></div></div>
                <button class="auth-submit" type="submit" data-submit><span>Đăng nhập</span><span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Chưa có tài khoản học viên? <a href="/register.php">Đăng ký ngay</a></p>
            <a class="auth-back" href="/index.php">← Về trang chủ</a>
        </div>
    </section>
</main>
<script src="/assets/js/auth.js" defer></script>
</body>
</html>
