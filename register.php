<?php
declare(strict_types=1);
require __DIR__.'/bin/bootstrap.php';

use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthPortalRouter;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Database\Connection;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

$session=new SessionManager(require __DIR__.'/config/session.php');$session->start();
if(($current=$session->user())!==null){header('Location: '.AuthPortalRouter::destination((string)$current['role']));exit;}

$values=['fullName'=>'','email'=>'','phone'=>'','dateOfBirth'=>'','classId'=>''];$fieldErrors=[];$errorMessage=null;$classes=[];$repository=null;
try{$pdo=(new Connection(require __DIR__.'/config/database.php'))->connect();$repository=new AuthRepository($pdo);$classes=$repository->registrationClasses();}
catch(Throwable){$errorMessage='Chưa thể tải danh sách lớp. Vui lòng thử lại khi dịch vụ dữ liệu sẵn sàng.';}

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    foreach(array_keys($values) as $field){$values[$field]=trim((string)($_POST[$field]??''));}
    $password=(string)($_POST['password']??'');$confirmation=(string)($_POST['passwordConfirmation']??'');
    if(!hash_equals($password,$confirmation)){$fieldErrors['passwordConfirmation']='Mật khẩu nhập lại chưa khớp.';}
    if($repository===null){$errorMessage='Không thể kết nối dịch vụ đăng ký. Vui lòng thử lại sau.';}
    elseif($fieldErrors===[]){
        try{
            $auth=new AuthService($repository);$user=$auth->registerStudent([...$values,'password'=>$password],RequestId::make(null),$_SERVER['REMOTE_ADDR']??null);
            $_SESSION['authFlash']=['type'=>'registered','email'=>$user['email']];header('Location: /login.php');exit;
        }catch(ApiException $exception){$errorMessage=$exception->getMessage();foreach($exception->details as $detail){$fieldErrors[$detail['field']]=$detail['message'];}}
        catch(Throwable){$errorMessage='Không thể hoàn tất đăng ký. Vui lòng thử lại sau.';}
    }
}

function registerEscape(mixed $value): string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$grouped=[];foreach($classes as $class){$grouped[$class['schoolName']][]=$class;}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tạo tài khoản học viên TalentHub và bắt đầu xây dựng hồ sơ năng lực.">
    <title>Đăng ký học viên | TalentHub</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page auth-page--register">
<main class="auth-layout">
    <section class="auth-brand auth-brand--register" aria-labelledby="auth-brand-title">
        <a class="auth-brand__logo" href="/index.php" aria-label="TalentHub - Về trang chủ"><img src="/assets/images/logo.svg" alt="TalentHub" width="200" height="40"></a>
        <div class="auth-brand__content">
            <p class="auth-eyebrow">Khởi tạo hồ sơ học viên</p>
            <h1 id="auth-brand-title">Bắt đầu từ năng lực của chính bạn</h1>
            <p>Tài khoản đăng ký công khai luôn được tạo với vai trò Học viên và gắn với lớp đang theo học.</p>
            <div class="auth-data-summary" aria-label="Dữ liệu hồ sơ được lưu">
                <p><strong>Thông tin tài khoản</strong><span>Email, họ tên và mật khẩu được mã hóa an toàn.</span></p>
                <p><strong>Thông tin học tập</strong><span>Lớp, ngày sinh và số điện thoại phục vụ hồ sơ học viên.</span></p>
                <p><strong>Vai trò mặc định</strong><span>Student · không thể tự nâng quyền khi đăng ký.</span></p>
            </div>
        </div>
        <p class="auth-brand__footer">Thông tin trường và lớp được lấy từ dữ liệu đang hoạt động trên TalentHub.</p>
    </section>
    <section class="auth-panel" aria-labelledby="register-title">
        <div class="auth-panel__inner auth-panel__inner--wide">
            <a class="auth-mobile-logo" href="/index.php"><img src="/assets/images/logo.svg" alt="TalentHub" width="200" height="40"></a>
            <div class="auth-heading"><div class="auth-heading__row"><div><p class="auth-kicker">Tài khoản mới</p><h2 id="register-title">Đăng ký học viên</h2></div><span class="auth-role-badge">Student</span></div><p>Điền đúng thông tin đang sử dụng tại trường của bạn.</p></div>
            <?php if($errorMessage!==null): ?><div class="auth-alert auth-alert--error" role="alert"><?=registerEscape($errorMessage)?></div><?php endif; ?>
            <form class="auth-form auth-form--register" method="post" action="/register.php" data-auth-form novalidate>
                <div class="auth-form-grid">
                    <div class="auth-field auth-field--full"><label for="fullName">Họ và tên</label><input id="fullName" name="fullName" value="<?=registerEscape($values['fullName'])?>" autocomplete="name" minlength="2" maxlength="150" required <?php if(isset($fieldErrors['fullName'])): ?>aria-invalid="true" aria-describedby="fullName-error"<?php endif; ?>><?php if(isset($fieldErrors['fullName'])): ?><span class="auth-field__error" id="fullName-error"><?=registerEscape($fieldErrors['fullName'])?></span><?php endif; ?></div>
                    <div class="auth-field"><label for="email">Email</label><input id="email" name="email" type="email" value="<?=registerEscape($values['email'])?>" autocomplete="email" inputmode="email" maxlength="255" required <?php if(isset($fieldErrors['email'])): ?>aria-invalid="true" aria-describedby="email-error"<?php endif; ?>><?php if(isset($fieldErrors['email'])): ?><span class="auth-field__error" id="email-error"><?=registerEscape($fieldErrors['email'])?></span><?php endif; ?></div>
                    <div class="auth-field"><label for="phone">Số điện thoại</label><input id="phone" name="phone" type="tel" value="<?=registerEscape($values['phone'])?>" autocomplete="tel" inputmode="tel" minlength="6" maxlength="30" pattern="[0-9+() .-]+" required <?php if(isset($fieldErrors['phone'])): ?>aria-invalid="true" aria-describedby="phone-error"<?php endif; ?>><?php if(isset($fieldErrors['phone'])): ?><span class="auth-field__error" id="phone-error"><?=registerEscape($fieldErrors['phone'])?></span><?php endif; ?></div>
                    <div class="auth-field"><label for="dateOfBirth">Ngày sinh</label><input id="dateOfBirth" name="dateOfBirth" type="date" value="<?=registerEscape($values['dateOfBirth'])?>" max="<?=date('Y-m-d')?>" autocomplete="bday" required <?php if(isset($fieldErrors['dateOfBirth'])): ?>aria-invalid="true" aria-describedby="dateOfBirth-error"<?php endif; ?>><?php if(isset($fieldErrors['dateOfBirth'])): ?><span class="auth-field__error" id="dateOfBirth-error"><?=registerEscape($fieldErrors['dateOfBirth'])?></span><?php endif; ?></div>
                    <div class="auth-field"><label for="classId">Lớp đang học</label><select id="classId" name="classId" required <?php if($classes===[]): ?>disabled<?php endif; ?> <?php if(isset($fieldErrors['classId'])): ?>aria-invalid="true" aria-describedby="classId-error"<?php endif; ?>><option value="">Chọn trường và lớp</option><?php foreach($grouped as $schoolName=>$schoolClasses): ?><optgroup label="<?=registerEscape($schoolName)?>"><?php foreach($schoolClasses as $class): ?><option value="<?=registerEscape($class['id'])?>" <?=hash_equals($values['classId'],$class['id'])?'selected':''?>><?=registerEscape($class['name'])?> · Khối <?=registerEscape($class['gradeLevel'])?> · <?=registerEscape($class['academicYear'])?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select><?php if(isset($fieldErrors['classId'])): ?><span class="auth-field__error" id="classId-error"><?=registerEscape($fieldErrors['classId'])?></span><?php endif; ?></div>
                    <div class="auth-field"><label for="password">Mật khẩu</label><div class="auth-password"><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="255" required aria-describedby="password-hint"><button type="button" class="auth-password__toggle" data-password-toggle aria-controls="password" aria-pressed="false">Hiện</button></div><span class="auth-field__hint" id="password-hint">Tối thiểu 12 ký tự.</span></div>
                    <div class="auth-field"><label for="passwordConfirmation">Nhập lại mật khẩu</label><div class="auth-password"><input id="passwordConfirmation" name="passwordConfirmation" type="password" autocomplete="new-password" minlength="12" maxlength="255" required data-password-confirm <?php if(isset($fieldErrors['passwordConfirmation'])): ?>aria-invalid="true" aria-describedby="passwordConfirmation-error"<?php endif; ?>><button type="button" class="auth-password__toggle" data-password-toggle aria-controls="passwordConfirmation" aria-pressed="false">Hiện</button></div><span class="auth-field__error" id="passwordConfirmation-error" data-password-match><?=isset($fieldErrors['passwordConfirmation'])?registerEscape($fieldErrors['passwordConfirmation']):''?></span></div>
                </div>
                <button class="auth-submit" type="submit" data-submit <?php if($classes===[]): ?>disabled<?php endif; ?>><span>Tạo tài khoản học viên</span><span aria-hidden="true">→</span></button>
            </form>
            <p class="auth-switch">Đã có tài khoản? <a href="/login.php">Đăng nhập</a></p>
            <a class="auth-back" href="/index.php">← Về trang chủ</a>
        </div>
    </section>
</main>
<script src="/assets/js/auth.js" defer></script>
</body>
</html>
