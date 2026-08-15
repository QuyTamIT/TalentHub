<?php
declare(strict_types=1);
namespace TalentHub\Auth\Session;

use TalentHub\Http\ApiException;

final class SessionManager
{
    /** @param array{name:string,lifetime:int,secure:bool,sameSite:string} $config */
    public function __construct(private readonly array $config) {}

    public function start(): void
    {
        if(session_status()===PHP_SESSION_ACTIVE){return;}
        session_name($this->config['name']);
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$this->config['secure'],'httponly'=>true,'samesite'=>$this->config['sameSite']]);
        ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
        session_start();
        if(isset($_SESSION['lastSeenAt'])&&time()-(int)$_SESSION['lastSeenAt']>$this->config['lifetime']){$this->destroy();session_start();}
        $_SESSION['lastSeenAt']=time();
    }

    /** @param array{id:string,email:string,fullName:string,role:string,status:string} $user */
    public function login(array $user): void{session_regenerate_id(true);$_SESSION['user']=$user;$_SESSION['csrfToken']=bin2hex(random_bytes(32));$_SESSION['lastSeenAt']=time();}
    /** @return array{id:string,email:string,fullName:string,role:string,status:string}|null */
    public function user(): ?array{$user=$_SESSION['user']??null;return is_array($user)?$user:null;}
    /** @return array{id:string,email:string,fullName:string,role:string,status:string} */
    public function requireUser(): array{return $this->user()??throw new ApiException(401,'AUTHENTICATION_REQUIRED','Bạn cần đăng nhập.');}
    public function csrfToken(): string{if(!isset($_SESSION['csrfToken'])){$_SESSION['csrfToken']=bin2hex(random_bytes(32));}return (string)$_SESSION['csrfToken'];}
    public function assertCsrf(?string $token): void{if($token===null||!hash_equals($this->csrfToken(),$token)){throw new ApiException(403,'CSRF_TOKEN_INVALID','CSRF token không hợp lệ.');}}
    public function assertLoginAllowed(): void
    {
        $window=(int)($_SESSION['loginWindowAt']??0);if($window===0||time()-$window>=300){$_SESSION['loginWindowAt']=time();$_SESSION['loginAttempts']=0;}
        if((int)($_SESSION['loginAttempts']??0)>=5){throw new ApiException(429,'RATE_LIMIT_EXCEEDED','Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau.');}
    }
    public function recordLoginFailure(): void{$_SESSION['loginAttempts']=(int)($_SESSION['loginAttempts']??0)+1;}
    public function clearLoginFailures(): void{unset($_SESSION['loginAttempts'],$_SESSION['loginWindowAt']);}
    /** @param array{id:string,email:string,fullName:string,role:string,status:string} $user */
    public function refreshUser(array $user): void{$_SESSION['user']=$user;}
    public function destroy(): void{$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE){session_destroy();}}
}
